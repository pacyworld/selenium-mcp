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
		description: 'launches browser',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'browser' => ['type' => 'string', 'enum' => ['chrome', 'firefox', 'edge', 'safari'], 'description' => 'Browser to launch (chrome, firefox, edge, or safari)'],
				'options' => [
					'type' => 'object',
					'properties' => [
						'headless' => ['type' => 'boolean', 'description' => 'Run browser in headless mode'],
						'arguments' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Additional browser arguments'],
						'acceptInsecureCerts' => ['type' => 'boolean', 'description' => 'Accept invalid/self-signed TLS certificates'],
						'platformName' => ['type' => 'string', 'description' => 'Target platform for Grid routing (e.g. WINDOWS, UNIX, LINUX, MAC)'],
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
			$message = "Browser started with session_id: {$sessionId}";

			if ($this->manager->isBidiEnabled()) {
				$this->manager->connectBidi();
				$message .= ' (BiDi enabled: console logs, JS errors, and network activity are being captured)';
			}

			return ToolResult::text($message);
		} catch (\Exception $e) {
			return ToolResult::error("Error starting browser: {$e->getMessage()}");
		}
	}

	#[McpTool(
		name: 'navigate',
		description: 'navigates to a URL',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'url' => ['type' => 'string', 'description' => 'URL to navigate to'],
			],
			'required' => ['url'],
		]
	)]
	public function navigate(string $url): ToolResult
	{
		try {
			$driver = $this->manager->getDriver();
			$driver->get($url);
			return ToolResult::text("Navigated to {$url}");
		} catch (\Exception $e) {
			return ToolResult::error("Error navigating: {$e->getMessage()}");
		}
	}

	#[McpTool(
		name: 'take_screenshot',
		description: "captures a screenshot of the current page. Prefer using the accessibility://current resource for understanding page content. Use get_element_text, get_element_attribute, or execute_script to verify element state. Only use screenshots when visual layout or styling needs to be verified.",
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'outputPath' => ['type' => 'string', 'description' => 'Optional path where to save the screenshot. If not provided, returns an image/png content block.'],
			],
		]
	)]
	public function take_screenshot(string $outputPath = ''): ToolResult
	{
		try {
			$driver = $this->manager->getDriver();
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
		description: 'closes the current browser session',
		inputSchema: ['type' => 'object']
	)]
	public function close_session(): ToolResult
	{
		try {
			$sessionId = $this->manager->closeSession();
			return ToolResult::text("Browser session {$sessionId} closed");
		} catch (\Exception $e) {
			return ToolResult::error("Error closing session: {$e->getMessage()}");
		}
	}
}
