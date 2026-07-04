<?php
/**
 * Selenium MCP Server — Keyboard & Script Tools
 *
 * @package    SeleniumMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use EnchiladaMCP\McpTool;
use EnchiladaMCP\ToolResult;
use Selenium\SessionManager;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\Interactions\WebDriverActions;

class InputTools
{
	private SessionManager $manager;

	public function __construct(SessionManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'press_key',
		description: 'simulates pressing a keyboard key',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'key' => ['type' => 'string', 'description' => "Key to press (e.g., 'Enter', 'Tab', 'a', etc.)"],
				'session_id' => ['type' => 'string', 'description' => 'Session ID from start_browser (optional; targets the most recently started session if omitted)'],
			],
			'required' => ['key'],
		]
	)]
	public function press_key(string $key, string $session_id = ''): ToolResult
	{
		try {
			$driver = $this->manager->getDriver($session_id ?: null);
			$resolvedKey = $this->resolveKey($key);

			if ($resolvedKey === null) {
				return [
					'content' => [['type' => 'text', 'text' => "Error pressing key: Unknown key name '{$key}'. Use a single character or a named key like 'Enter', 'Tab', 'Escape', etc."]],
					'isError' => true,
				];
			}

			$actions = new WebDriverActions($driver);
			$actions->keyDown($resolvedKey)->keyUp($resolvedKey)->perform();

			return ToolResult::text("Key '{$key}' pressed");
		} catch (\Exception $e) {
			return ToolResult::error("Error pressing key: {$e->getMessage()}");
		}
	}

	#[McpTool(
		name: 'execute_script',
		description: "executes JavaScript in the browser and returns the result. Use for advanced interactions not covered by other tools (e.g., drag and drop, scrolling, reading computed styles, manipulating the DOM directly). Also useful for batch-reading multiple element values/states in a single call instead of multiple get_element_attribute calls.",
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'script' => ['type' => 'string', 'description' => 'JavaScript code to execute in the browser'],
				'args' => ['type' => 'array', 'description' => 'Optional arguments to pass to the script (accessible via arguments[0], arguments[1], etc.)'],
				'session_id' => ['type' => 'string', 'description' => 'Session ID from start_browser (optional; targets the most recently started session if omitted)'],
			],
			'required' => ['script'],
		]
	)]
	public function execute_script(string $script, array $args = [], string $session_id = ''): ToolResult
	{
		try {
			$driver = $this->manager->getDriver($session_id ?: null);
			$result = $driver->executeScript($script, $args);

			if ($result === null) {
				$text = 'Script executed (no return value)';
			} elseif (is_array($result) || is_object($result)) {
				$text = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
			} else {
				$text = (string) $result;
			}

			return ToolResult::text($text);
		} catch (\Exception $e) {
			return ToolResult::error("Error executing script: {$e->getMessage()}");
		}
	}

	private function resolveKey(string $key): ?string
	{
		if (strlen($key) === 1) {
			return $key;
		}

		$keyMap = [
			'ENTER' => WebDriverKeys::ENTER,
			'RETURN' => WebDriverKeys::RETURN_KEY,
			'TAB' => WebDriverKeys::TAB,
			'ESCAPE' => WebDriverKeys::ESCAPE,
			'BACKSPACE' => WebDriverKeys::BACKSPACE,
			'DELETE' => WebDriverKeys::DELETE,
			'SPACE' => WebDriverKeys::SPACE,
			'UP' => WebDriverKeys::ARROW_UP,
			'DOWN' => WebDriverKeys::ARROW_DOWN,
			'LEFT' => WebDriverKeys::ARROW_LEFT,
			'RIGHT' => WebDriverKeys::ARROW_RIGHT,
			'ARROW_UP' => WebDriverKeys::ARROW_UP,
			'ARROW_DOWN' => WebDriverKeys::ARROW_DOWN,
			'ARROW_LEFT' => WebDriverKeys::ARROW_LEFT,
			'ARROW_RIGHT' => WebDriverKeys::ARROW_RIGHT,
			'HOME' => WebDriverKeys::HOME,
			'END' => WebDriverKeys::END,
			'PAGE_UP' => WebDriverKeys::PAGE_UP,
			'PAGE_DOWN' => WebDriverKeys::PAGE_DOWN,
			'F1' => WebDriverKeys::F1,
			'F2' => WebDriverKeys::F2,
			'F3' => WebDriverKeys::F3,
			'F4' => WebDriverKeys::F4,
			'F5' => WebDriverKeys::F5,
			'F6' => WebDriverKeys::F6,
			'F7' => WebDriverKeys::F7,
			'F8' => WebDriverKeys::F8,
			'F9' => WebDriverKeys::F9,
			'F10' => WebDriverKeys::F10,
			'F11' => WebDriverKeys::F11,
			'F12' => WebDriverKeys::F12,
			'CONTROL' => WebDriverKeys::CONTROL,
			'ALT' => WebDriverKeys::ALT,
			'SHIFT' => WebDriverKeys::SHIFT,
			'META' => WebDriverKeys::META,
			'COMMAND' => WebDriverKeys::COMMAND,
		];

		$normalized = strtoupper(str_replace(' ', '_', $key));
		return $keyMap[$normalized] ?? null;
	}
}
