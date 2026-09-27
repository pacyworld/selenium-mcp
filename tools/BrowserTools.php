<?php
/**
 * Selenium MCP Server — Browser Management Tools
 *
 * @package    SeleniumMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use EnchiladaMCP\McpTool;
use EnchiladaMCP\ToolResult;
use Selenium\SessionManager;

class BrowserTools
{
	private SessionManager $manager;

	public function __construct(SessionManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'start_browser',
		description: 'Launch a browser and return its session_id.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'browser' => ['type' => 'string', 'enum' => ['chrome', 'firefox', 'edge', 'safari'], ],
				'options' => [
					'type' => 'object',
					'properties' => [
						'headless' => ['type' => 'boolean'],
						'arguments' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Extra browser command-line arguments'],
						'acceptInsecureCerts' => ['type' => 'boolean', 'description' => 'Accept invalid/self-signed TLS certificates'],
						'platformName' => ['type' => 'string', 'description' => 'Grid routing, e.g. WINDOWS, LINUX, MAC'],
					],
				],
			],
			'required' => ['browser'],
		]
	)]
	public function start_browser(string $browser, array $options = []): ToolResult
	{
		try {
			$sessionId = $this->manager->createSession($browser, $options);
			$message = "Browser started with session_id: {$sessionId}. " .
				"Pass this session_id to other tools to target this browser when running multiple concurrent sessions.";

			if ($this->manager->isBidiEnabled($sessionId)) {
				$this->manager->connectBidi($sessionId);
				$message .= ' (BiDi enabled: console logs, JS errors, and network activity are being captured)';
			}

			return ToolResult::text($message);
		} catch (\Exception $e) {
			return ToolResult::error("Error starting browser: {$e->getMessage()}");
		}
	}

	#[McpTool(
		name: 'navigate',
		description: 'Navigate to a URL.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'url' => ['type' => 'string'],
				'session_id' => ['type' => 'string', 'description' => 'From start_browser; default most recent'],
			],
			'required' => ['url'],
		]
	)]
	public function navigate(string $url, string $session_id = ''): ToolResult
	{
		try {
			$driver = $this->manager->getDriver($session_id ?: null);
			$driver->get($url);
			return ToolResult::text("Navigated to {$url}");
		} catch (\Exception $e) {
			return ToolResult::error("Error navigating: {$e->getMessage()}");
		}
	}

	#[McpTool(
		name: 'take_screenshot',
		readOnlyHint: true,
		description: 'Screenshot the current page. Use only to verify visual layout; for content read the accessibility://current resource, for element state use get_element_text/get_element_attribute/execute_script.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'outputPath' => ['type' => 'string', 'description' => 'Save to this server-side path; if omitted, returns a PNG image'],
				'session_id' => ['type' => 'string', 'description' => 'From start_browser; default most recent'],
			],
		]
	)]
	public function take_screenshot(string $outputPath = '', string $session_id = ''): ToolResult
	{
		try {
			$driver = $this->manager->getDriver($session_id ?: null);
			$screenshot = $driver->takeScreenshot();

			if (!empty($outputPath)) {
				file_put_contents($outputPath, $screenshot);
				return ToolResult::text("Screenshot saved to {$outputPath}");
			}

			return ToolResult::image(base64_encode($screenshot));
		} catch (\Exception $e) {
			return ToolResult::error("Error taking screenshot: {$e->getMessage()}");
		}
	}

	#[McpTool(
		name: 'close_session',
		description: 'Close a browser session.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'session_id' => ['type' => 'string', 'description' => 'From start_browser; default most recent'],
			],
		]
	)]
	public function close_session(string $session_id = ''): ToolResult
	{
		try {
			$sessionId = $this->manager->closeSession($session_id ?: null);
			return ToolResult::text("Browser session {$sessionId} closed");
		} catch (\Exception $e) {
			return ToolResult::error("Error closing session: {$e->getMessage()}");
		}
	}
}
