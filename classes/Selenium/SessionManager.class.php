<?php
/**
 * Selenium MCP Server — Session Manager
 *
 * Manages WebDriver sessions (create, get, close) against a Selenium Grid.
 * Supports multiple named instances via config.
 *
 * @package    SeleniumMCP
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace Selenium;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Firefox\FirefoxOptions;
use EnchiladaMCP\StdioTransport;

class SessionManager
{
	private array $config;
	private ?string $currentSession = null;

	/** @var array<string, RemoteWebDriver> */
	private array $drivers = [];

	/** @var array<string, BiDiClient> BiDi clients indexed by session ID (one per concurrent session). */
	private array $bidiClients = [];

	private ?StdioTransport $transport = null;

	public function __construct(array $config)
	{
		$this->config = $config;
	}

	/**
	 * Get the configured Grid URL for the default (or named) instance.
	 */
	public function getGridUrl(?string $instance = null): string
	{
		$name = $instance ?? $this->config['default'] ?? 'grid';
		$inst = $this->config['instances'][$name] ?? null;

		if ($inst === null) {
			throw new \RuntimeException("Selenium instance '{$name}' not found in configuration");
		}

		return rtrim($inst['grid_url'] ?? 'http://localhost:4444', '/');
	}

	/**
	 * Get instance configuration.
	 */
	public function getInstanceConfig(?string $instance = null): array
	{
		$name = $instance ?? $this->config['default'] ?? 'grid';
		return $this->config['instances'][$name] ?? [];
	}

	/**
	 * Create a new browser session.
	 *
	 * @param string $browser Browser name (chrome, firefox, edge, safari)
	 * @param array $options Options: headless, arguments
	 * @return string Session ID
	 */
	public function createSession(string $browser, array $options = []): string
	{
		$gridUrl = $this->getGridUrl();
		$capabilities = $this->buildCapabilities($browser, $options);

		$driver = RemoteWebDriver::create($gridUrl, $capabilities);

		// Set timeouts to avoid indefinite hangs on unreachable hosts
		$timeout = $this->getInstanceConfig()['timeout'] ?? 30;
		$driver->manage()->timeouts()->pageLoadTimeout($timeout);

		// Unique even when multiple sessions start within the same second
		// (e.g. two concurrent MCP clients calling start_browser at once).
		$sessionId = $browser . '_' . bin2hex(random_bytes(4));

		$this->drivers[$sessionId] = $driver;
		$this->currentSession = $sessionId;

		return $sessionId;
	}

	/**
	 * Get a WebDriver instance.
	 *
	 * Concurrency note: when multiple logical MCP sessions share one
	 * server process (common with IDE hosts that share a single spawned
	 * stdio process across chat sessions in the same window), relying on
	 * the implicit "current session" without passing $sessionId means a
	 * concurrent start_browser() call from another session can silently
	 * redirect subsequent tool calls to a different browser. Callers that
	 * care about isolation under concurrent use should always pass the
	 * session_id returned by start_browser().
	 *
	 * @param  string|null $sessionId Explicit session ID, or null for the
	 *                                 implicit "current" session (single-session
	 *                                 convenience; not safe under concurrent use)
	 * @throws \RuntimeException if the resolved session has no active driver
	 */
	public function getDriver(?string $sessionId = null): RemoteWebDriver
	{
		$sessionId = $sessionId ?? $this->currentSession;

		if ($sessionId === null || !isset($this->drivers[$sessionId])) {
			if ($sessionId !== null) {
				throw new \RuntimeException("No active browser session with id '{$sessionId}'");
			}
			throw new \RuntimeException('No active browser session');
		}

		return $this->drivers[$sessionId];
	}

	/**
	 * Get the current session ID.
	 */
	public function getCurrentSession(): ?string
	{
		return $this->currentSession;
	}

	/**
	 * Close a browser session.
	 *
	 * @param  string|null $sessionId Explicit session ID, or null for the
	 *                                 implicit "current" session
	 * @throws \RuntimeException if the resolved session doesn't exist
	 */
	public function closeSession(?string $sessionId = null): string
	{
		$sessionId = $sessionId ?? $this->currentSession;

		if ($sessionId === null || !isset($this->drivers[$sessionId])) {
			throw new \RuntimeException('No active browser session');
		}

		$driver = $this->drivers[$sessionId];

		try {
			$this->disconnectBidi($sessionId);
			$driver->quit();
		} finally {
			unset($this->drivers[$sessionId]);
			// Only clear the "current" pointer if we just closed the session
			// it was pointing at -- closing an explicitly-targeted session
			// must never disturb another session's implicit default.
			if ($this->currentSession === $sessionId) {
				$this->currentSession = null;
			}
		}

		return $sessionId;
	}

	/**
	 * Close all sessions (cleanup on shutdown).
	 */
	public function closeAll(): void
	{
		foreach (array_keys($this->drivers) as $id) {
			try {
				$this->disconnectBidi($id);
				$this->drivers[$id]->quit();
			} catch (\Exception $e) {
				fwrite(STDERR, "[selenium-mcp] Error closing session {$id}: {$e->getMessage()}\n");
			}
		}

		$this->drivers = [];
		$this->bidiClients = [];
		$this->currentSession = null;
	}

	/**
	 * Check if a session (explicit or implicit "current") is active.
	 */
	public function hasSession(?string $sessionId = null): bool
	{
		$sessionId = $sessionId ?? $this->currentSession;
		return $sessionId !== null && isset($this->drivers[$sessionId]);
	}

	/**
	 * Check if BiDi is enabled for a session (explicit or implicit "current").
	 */
	public function isBidiEnabled(?string $sessionId = null): bool
	{
		return $this->getBidiUrl($sessionId) !== null;
	}

	/**
	 * Get a session's BiDi WebSocket URL (if available).
	 *
	 * The Grid returns the webSocketUrl capability when BiDi is supported.
	 *
	 * @param string|null $sessionId Explicit session ID, or null for the
	 *                                implicit "current" session
	 */
	public function getBidiUrl(?string $sessionId = null): ?string
	{
		if (!$this->hasSession($sessionId)) {
			return null;
		}

		$caps = $this->getDriver($sessionId)->getCapabilities();
		$wsUrl = $caps->getCapability('webSocketUrl');

		return $wsUrl ?: null;
	}

	/**
	 * Set the StdioTransport for BiDi stream integration.
	 */
	public function setTransport(StdioTransport $transport): void
	{
		$this->transport = $transport;
	}

	/**
	 * Connect the BiDi WebSocket for a session and register it with the
	 * transport's event loop. Each session gets its own BiDi client so
	 * concurrent sessions' console/network/error logs don't collide.
	 *
	 * @param string|null $sessionId Explicit session ID, or null for the
	 *                                implicit "current" session
	 */
	public function connectBidi(?string $sessionId = null): void
	{
		$sessionId = $sessionId ?? $this->currentSession;
		$bidiUrl = $this->getBidiUrl($sessionId);
		if ($bidiUrl === null || $sessionId === null) {
			return;
		}

		try {
			$bidiClient = new BiDiClient();
			$bidiClient->connect($bidiUrl);
			$this->bidiClients[$sessionId] = $bidiClient;

			// Register BiDi stream with transport event loop
			$stream = $bidiClient->getStream();
			if ($stream !== null && $this->transport !== null) {
				$this->transport->addStream($stream, function ($s) use ($bidiClient) {
					$bidiClient->processEvents();
				});
			}

			fwrite(STDERR, "[selenium-mcp] BiDi connected for session {$sessionId}: {$bidiUrl}\n");
		} catch (\Exception $e) {
			fwrite(STDERR, "[selenium-mcp] BiDi connection failed for session {$sessionId}: {$e->getMessage()}\n");
			unset($this->bidiClients[$sessionId]);
		}
	}

	/**
	 * Disconnect a session's BiDi WebSocket and remove it from the event loop.
	 *
	 * @param string|null $sessionId Explicit session ID, or null for the
	 *                                implicit "current" session
	 */
	public function disconnectBidi(?string $sessionId = null): void
	{
		$sessionId = $sessionId ?? $this->currentSession;
		if ($sessionId === null || !isset($this->bidiClients[$sessionId])) {
			return;
		}

		$bidiClient = $this->bidiClients[$sessionId];
		$stream = $bidiClient->getStream();
		if ($stream !== null && $this->transport !== null) {
			$this->transport->removeStream($stream);
		}

		$bidiClient->disconnect();
		unset($this->bidiClients[$sessionId]);
	}

	/**
	 * Get the active BiDi client for a session (if connected).
	 *
	 * @param string|null $sessionId Explicit session ID, or null for the
	 *                                implicit "current" session
	 */
	public function getBiDiClient(?string $sessionId = null): ?BiDiClient
	{
		$sessionId = $sessionId ?? $this->currentSession;
		if ($sessionId === null) {
			return null;
		}
		return $this->bidiClients[$sessionId] ?? null;
	}

	private function buildCapabilities(string $browser, array $options): DesiredCapabilities
	{
		$headless = $options['headless'] ?? ($this->getInstanceConfig()['headless'] ?? false);
		$arguments = $options['arguments'] ?? [];

		switch ($browser) {
			case 'chrome':
				$caps = DesiredCapabilities::chrome();
				$chromeOptions = new ChromeOptions();
				if ($headless) {
					$chromeOptions->addArguments(['--headless=new']);
				}
				if (!empty($arguments)) {
					$chromeOptions->addArguments($arguments);
				}
				$caps->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);
				break;

			case 'firefox':
				$caps = DesiredCapabilities::firefox();
				$ffOptions = new FirefoxOptions();
				if ($headless) {
					$ffOptions->addArguments(['-headless']);
				}
				if (!empty($arguments)) {
					$ffOptions->addArguments($arguments);
				}
				$caps->setCapability(FirefoxOptions::CAPABILITY, $ffOptions);
				break;

			case 'edge':
				$caps = DesiredCapabilities::microsoftEdge();
				if ($headless || !empty($arguments)) {
					$edgeOptions = ['args' => []];
					if ($headless) {
						$edgeOptions['args'][] = '--headless=new';
					}
					$edgeOptions['args'] = array_merge($edgeOptions['args'], $arguments);
					$caps->setCapability('ms:edgeOptions', $edgeOptions);
				}
				break;

			case 'safari':
				$caps = DesiredCapabilities::safari();
				break;

			default:
				throw new \RuntimeException("Unsupported browser: {$browser}");
		}

		// W3C standard capabilities
		$caps->setCapability('webSocketUrl', true);

		$acceptInsecureCerts = $options['acceptInsecureCerts']
			?? ($this->getInstanceConfig()['accept_insecure_certs'] ?? false);
		if ($acceptInsecureCerts) {
			$caps->setCapability('acceptInsecureCerts', true);
		}

		$platformName = $options['platformName'] ?? ($this->getInstanceConfig()['platform'] ?? null);
		if ($platformName !== null) {
			$caps->setCapability('platformName', $platformName);
		}

		return $caps;
	}
}
