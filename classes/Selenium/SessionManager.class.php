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

	private ?BiDiClient $bidiClient = null;
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

		$sessionId = $browser . '_' . time();

		$this->drivers[$sessionId] = $driver;
		$this->currentSession = $sessionId;

		return $sessionId;
	}

	/**
	 * Get the active WebDriver instance.
	 *
	 * @throws \RuntimeException if no active session
	 */
	public function getDriver(): RemoteWebDriver
	{
		if ($this->currentSession === null || !isset($this->drivers[$this->currentSession])) {
			throw new \RuntimeException('No active browser session');
		}

		return $this->drivers[$this->currentSession];
	}

	/**
	 * Get the current session ID.
	 */
	public function getCurrentSession(): ?string
	{
		return $this->currentSession;
	}

	/**
	 * Close the current session.
	 */
	public function closeSession(): string
	{
		if ($this->currentSession === null) {
			throw new \RuntimeException('No active browser session');
		}

		$sessionId = $this->currentSession;
		$driver = $this->drivers[$sessionId];

		try {
			$this->disconnectBidi();
			$driver->quit();
		} finally {
			unset($this->drivers[$sessionId]);
			$this->currentSession = null;
		}

		return $sessionId;
	}

	/**
	 * Close all sessions (cleanup on shutdown).
	 */
	public function closeAll(): void
	{
		foreach ($this->drivers as $id => $driver) {
			try {
				$driver->quit();
			} catch (\Exception $e) {
				fwrite(STDERR, "[selenium-mcp] Error closing session {$id}: {$e->getMessage()}\n");
			}
		}

		$this->drivers = [];
		$this->currentSession = null;
	}

	/**
	 * Check if there's an active session.
	 */
	public function hasSession(): bool
	{
		return $this->currentSession !== null && isset($this->drivers[$this->currentSession]);
	}

	/**
	 * Check if BiDi is enabled for the current session.
	 */
	public function isBidiEnabled(): bool
	{
		return $this->getBidiUrl() !== null;
	}

	/**
	 * Get the WebDriver session's BiDi WebSocket URL (if available).
	 *
	 * The Grid returns the webSocketUrl capability when BiDi is supported.
	 */
	public function getBidiUrl(): ?string
	{
		if (!$this->hasSession()) {
			return null;
		}

		$caps = $this->getDriver()->getCapabilities();
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
	 * Connect the BiDi WebSocket and register with the event loop.
	 */
	public function connectBidi(): void
	{
		$bidiUrl = $this->getBidiUrl();
		if ($bidiUrl === null) {
			return;
		}

		try {
			$this->bidiClient = new BiDiClient();
			$this->bidiClient->connect($bidiUrl);

			// Register BiDi stream with transport event loop
			$stream = $this->bidiClient->getStream();
			if ($stream !== null && $this->transport !== null) {
				$bidi = $this->bidiClient;
				$this->transport->addStream($stream, function ($s) use ($bidi) {
					$bidi->processEvents();
				});
			}

			fwrite(STDERR, "[selenium-mcp] BiDi connected: {$bidiUrl}\n");
		} catch (\Exception $e) {
			fwrite(STDERR, "[selenium-mcp] BiDi connection failed: {$e->getMessage()}\n");
			$this->bidiClient = null;
		}
	}

	/**
	 * Disconnect BiDi WebSocket and remove from event loop.
	 */
	public function disconnectBidi(): void
	{
		if ($this->bidiClient === null) {
			return;
		}

		$stream = $this->bidiClient->getStream();
		if ($stream !== null && $this->transport !== null) {
			$this->transport->removeStream($stream);
		}

		$this->bidiClient->disconnect();
		$this->bidiClient = null;
	}

	/**
	 * Get the active BiDi client (if connected).
	 */
	public function getBiDiClient(): ?BiDiClient
	{
		return $this->bidiClient;
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
