<?php
/**
 * Selenium MCP Server — Window, Frame & Alert Tools
 *
 * @package    SeleniumMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use EnchiladaMCP\McpTool;
use EnchiladaMCP\ToolResult;
use Selenium\SessionManager;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

class WindowTools
{
	private SessionManager $manager;

	public function __construct(SessionManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'window',
		description: 'manages browser windows and tabs',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'action' => ['type' => 'string', 'enum' => ['list', 'switch', 'switch_latest', 'close'], 'description' => 'Window action to perform'],
				'handle' => ['type' => 'string', 'description' => 'Window handle (required for switch)'],
			],
			'required' => ['action'],
		]
	)]
	public function window(string $action, string $handle = ''): ToolResult
	{
		try {
			$driver = $this->manager->getDriver();

			switch ($action) {
				case 'list':
					$handles = $driver->getWindowHandles();
					$current = $driver->getWindowHandle();
					$result = json_encode(['current' => $current, 'all' => $handles], JSON_PRETTY_PRINT);
					return ToolResult::text($result);

				case 'switch':
					if (empty($handle)) {
						throw new \RuntimeException('handle is required for switch action');
					}
					$driver->switchTo()->window($handle);
					return ToolResult::text("Switched to window: {$handle}");

				case 'switch_latest':
					$handles = $driver->getWindowHandles();
					if (empty($handles)) {
						throw new \RuntimeException('No windows available');
					}
					$latest = end($handles);
					$driver->switchTo()->window($latest);
					return ToolResult::text("Switched to latest window: {$latest}");

				case 'close':
					$driver->close();
					$handles = [];
					try {
						$handles = $driver->getWindowHandles();
					} catch (\Exception $e) {
						// session gone
					}
					if (!empty($handles)) {
						$driver->switchTo()->window($handles[0]);
						return ToolResult::text("Window closed. Switched to: {$handles[0]}");
					}
					$this->manager->closeSession();
					return ToolResult::text('Last window closed. Session ended.');

				default:
					return ToolResult::error("Unknown action: {$action}");
			}
		} catch (\Exception $e) {
			return ToolResult::error("Error in window {$action}: {$e->getMessage()}");
		}
	}

	#[McpTool(
		name: 'frame',
		description: 'switches focus to a frame or back to the main page',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'action' => ['type' => 'string', 'enum' => ['switch', 'default'], 'description' => 'Frame action to perform'],
				'by' => ['type' => 'string', 'enum' => ['id', 'css', 'xpath', 'name', 'tag', 'class'], 'description' => 'Locator strategy for frame element'],
				'value' => ['type' => 'string', 'description' => 'Value for the locator strategy'],
				'index' => ['type' => 'number', 'description' => 'Frame index (0-based)'],
				'timeout' => ['type' => 'number', 'description' => 'Max wait in ms'],
			],
			'required' => ['action'],
		]
	)]
	public function frame(string $action, string $by = '', string $value = '', int $index = -1, int $timeout = 10000): ToolResult
	{
		try {
			$driver = $this->manager->getDriver();

			if ($action === 'default') {
				$driver->switchTo()->defaultContent();
				return ToolResult::text('Switched to default content');
			}

			// action === 'switch'
			if ($index >= 0) {
				$driver->switchTo()->frame($index);
			} elseif (!empty($by) && !empty($value)) {
				$locator = $this->getLocator($by, $value);
				$element = $driver->wait($timeout / 1000)->until(
					WebDriverExpectedCondition::presenceOfElementLocated($locator)
				);
				$driver->switchTo()->frame($element);
			} else {
				throw new \RuntimeException('Provide either by/value to locate frame, or index to switch by position');
			}

			return ToolResult::text('Switched to frame');
		} catch (\Exception $e) {
			return ToolResult::error("Error in frame {$action}: {$e->getMessage()}");
		}
	}

	#[McpTool(
		name: 'alert',
		description: 'handles a browser alert, confirm, or prompt dialog',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'action' => ['type' => 'string', 'enum' => ['accept', 'dismiss', 'get_text', 'send_text'], 'description' => 'Action to perform on the alert'],
				'text' => ['type' => 'string', 'description' => 'Text to send (required for send_text)'],
				'timeout' => ['type' => 'number', 'description' => 'Max wait in ms'],
			],
			'required' => ['action'],
		]
	)]
	public function alert(string $action, string $text = '', int $timeout = 5000): ToolResult
	{
		try {
			$driver = $this->manager->getDriver();
			$driver->wait($timeout / 1000)->until(
				WebDriverExpectedCondition::alertIsPresent()
			);
			$alert = $driver->switchTo()->alert();

			switch ($action) {
				case 'accept':
					$alert->accept();
					return ToolResult::text('Alert accepted');

				case 'dismiss':
					$alert->dismiss();
					return ToolResult::text('Alert dismissed');

				case 'get_text':
					$alertText = $alert->getText();
					return ToolResult::text($alertText);

				case 'send_text':
					if (empty($text)) {
						throw new \RuntimeException('text is required for send_text action');
					}
					$alert->sendKeys($text);
					$alert->accept();
					return ToolResult::text("Text \"{$text}\" sent to prompt and accepted");

				default:
					return ToolResult::error("Unknown action: {$action}");
			}
		} catch (\Exception $e) {
			return ToolResult::error("Error in alert {$action}: {$e->getMessage()}");
		}
	}

	private function getLocator(string $by, string $value): WebDriverBy
	{
		return match ($by) {
			'id' => WebDriverBy::id($value),
			'css' => WebDriverBy::cssSelector($value),
			'xpath' => WebDriverBy::xpath($value),
			'name' => WebDriverBy::name($value),
			'tag' => WebDriverBy::tagName($value),
			'class' => WebDriverBy::className($value),
			default => throw new \RuntimeException("Unsupported locator strategy: {$by}"),
		};
	}
}
