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
					],
				],
			],
			'required' => ['browser'],
		]
	)]
	public function start_browser(string $browser, array $options = []): array
	{
		try {
			$sessionId = $this->manager->createSession($browser, $options);
			$message = "Browser started with session_id: {$sessionId}";

			$bidiUrl = $this->manager->getBidiUrl();
			if ($bidiUrl) {
				$message .= ' (BiDi enabled: console logs, JS errors, and network activity are being captured)';
			}

			return ['content' => [['type' => 'text', 'text' => $message]]];
		} catch (\Exception $e) {
			return ['content' => [['type' => 'text', 'text' => "Error starting browser: {$e->getMessage()}"]], 'isError' => true];
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
	public function navigate(string $url): array
	{
		try {
			$driver = $this->manager->getDriver();
			$driver->get($url);
			return ['content' => [['type' => 'text', 'text' => "Navigated to {$url}"]]];
		} catch (\Exception $e) {
			return ['content' => [['type' => 'text', 'text' => "Error navigating: {$e->getMessage()}"]], 'isError' => true];
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
	public function close_session(): array
	{
		try {
			$sessionId = $this->manager->closeSession();
			return ['content' => [['type' => 'text', 'text' => "Browser session {$sessionId} closed"]]];
		} catch (\Exception $e) {
			return ['content' => [['type' => 'text', 'text' => "Error closing session: {$e->getMessage()}"]], 'isError' => true];
		}
	}
}
