<?php
/**
 * Selenium MCP Server — Element Interaction Tools
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
use Facebook\WebDriver\Interactions\WebDriverActions;

class ElementTools
{
	private SessionManager $manager;

	public function __construct(SessionManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'interact',
		description: 'Perform a mouse action on an element.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'action' => ['type' => 'string', 'enum' => ['click', 'doubleclick', 'rightclick', 'hover']],
				'by' => ['type' => 'string', 'enum' => ['id', 'css', 'xpath', 'name', 'tag', 'class']],
				'value' => ['type' => 'string', 'description' => 'Locator value'],
				'timeout' => ['type' => 'number', 'description' => 'Element wait in ms (default 10000)'],
				'session_id' => ['type' => 'string', 'description' => 'From start_browser; default most recent'],
			],
			'required' => ['action', 'by', 'value'],
		]
	)]
	public function interact(string $action, string $by, string $value, int $timeout = 10000, string $session_id = ''): ToolResult
	{
		try {
			$driver = $this->manager->getDriver($session_id ?: null);
			$locator = $this->getLocator($by, $value);
			$element = $driver->wait($timeout / 1000)->until(
				WebDriverExpectedCondition::presenceOfElementLocated($locator)
			);

			switch ($action) {
				case 'click':
					$element->click();
					return ToolResult::text('Element clicked');

				case 'doubleclick':
					$actions = new WebDriverActions($driver);
					$actions->doubleClick($element)->perform();
					return ToolResult::text('Double click performed');

				case 'rightclick':
					$actions = new WebDriverActions($driver);
					$actions->contextClick($element)->perform();
					return ToolResult::text('Right click performed');

				case 'hover':
					$actions = new WebDriverActions($driver);
					$actions->moveToElement($element)->perform();
					return ToolResult::text('Hovered over element');

				default:
					return ToolResult::error("Unknown action: {$action}");
			}
		} catch (\Exception $e) {
			return ToolResult::error("Error performing {$action}: {$e->getMessage()}. Try reading the accessibility://current resource to discover available elements and their locators.");
		}
	}

	#[McpTool(
		name: 'send_keys',
		description: 'Type text into an element; clears the field first.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'by' => ['type' => 'string', 'enum' => ['id', 'css', 'xpath', 'name', 'tag', 'class']],
				'value' => ['type' => 'string', 'description' => 'Locator value'],
				'text' => ['type' => 'string'],
				'timeout' => ['type' => 'number', 'description' => 'Element wait in ms (default 10000)'],
				'session_id' => ['type' => 'string', 'description' => 'From start_browser; default most recent'],
			],
			'required' => ['by', 'value', 'text'],
		]
	)]
	public function send_keys(string $by, string $value, string $text, int $timeout = 10000, string $session_id = ''): ToolResult
	{
		try {
			$driver = $this->manager->getDriver($session_id ?: null);
			$locator = $this->getLocator($by, $value);
			$element = $driver->wait($timeout / 1000)->until(
				WebDriverExpectedCondition::presenceOfElementLocated($locator)
			);
			$element->clear();
			$element->sendKeys($text);
			return ToolResult::text("Text \"{$text}\" entered into element");
		} catch (\Exception $e) {
			return ToolResult::error("Error entering text: {$e->getMessage()}");
		}
	}

	#[McpTool(
		name: 'get_element_text',
		readOnlyHint: true,
		description: 'Get the text content of an element.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'by' => ['type' => 'string', 'enum' => ['id', 'css', 'xpath', 'name', 'tag', 'class']],
				'value' => ['type' => 'string', 'description' => 'Locator value'],
				'timeout' => ['type' => 'number', 'description' => 'Element wait in ms (default 10000)'],
				'session_id' => ['type' => 'string', 'description' => 'From start_browser; default most recent'],
			],
			'required' => ['by', 'value'],
		]
	)]
	public function get_element_text(string $by, string $value, int $timeout = 10000, string $session_id = ''): ToolResult
	{
		try {
			$driver = $this->manager->getDriver($session_id ?: null);
			$locator = $this->getLocator($by, $value);
			$element = $driver->wait($timeout / 1000)->until(
				WebDriverExpectedCondition::presenceOfElementLocated($locator)
			);
			$text = $element->getText();
			return ToolResult::text($text);
		} catch (\Exception $e) {
			return ToolResult::error("Error getting element text: {$e->getMessage()}. Try reading the accessibility://current resource to discover available elements and their locators.");
		}
	}

	#[McpTool(
		name: 'get_element_attribute',
		readOnlyHint: true,
		description: 'Get an attribute value of an element. Prefer this over screenshots to verify element state.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'by' => ['type' => 'string', 'enum' => ['id', 'css', 'xpath', 'name', 'tag', 'class']],
				'value' => ['type' => 'string', 'description' => 'Locator value'],
				'attribute' => ['type' => 'string', 'description' => 'e.g. href, value, class'],
				'timeout' => ['type' => 'number', 'description' => 'Element wait in ms (default 10000)'],
				'session_id' => ['type' => 'string', 'description' => 'From start_browser; default most recent'],
			],
			'required' => ['by', 'value', 'attribute'],
		]
	)]
	public function get_element_attribute(string $by, string $value, string $attribute, int $timeout = 10000, string $session_id = ''): ToolResult
	{
		try {
			$driver = $this->manager->getDriver($session_id ?: null);
			$locator = $this->getLocator($by, $value);
			$element = $driver->wait($timeout / 1000)->until(
				WebDriverExpectedCondition::presenceOfElementLocated($locator)
			);
			$attrValue = $element->getAttribute($attribute);
			return ToolResult::text($attrValue ?? '');
		} catch (\Exception $e) {
			return ToolResult::error("Error getting attribute: {$e->getMessage()}. Try reading the accessibility://current resource to discover available elements and their locators.");
		}
	}

	#[McpTool(
		name: 'upload_file',
		description: 'Upload a file through a file input element.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'by' => ['type' => 'string', 'enum' => ['id', 'css', 'xpath', 'name', 'tag', 'class']],
				'value' => ['type' => 'string', 'description' => 'Locator value'],
				'filePath' => ['type' => 'string', 'description' => 'Absolute path'],
				'timeout' => ['type' => 'number', 'description' => 'Element wait in ms (default 10000)'],
				'session_id' => ['type' => 'string', 'description' => 'From start_browser; default most recent'],
			],
			'required' => ['by', 'value', 'filePath'],
		]
	)]
	public function upload_file(string $by, string $value, string $filePath, int $timeout = 10000, string $session_id = ''): ToolResult
	{
		try {
			$driver = $this->manager->getDriver($session_id ?: null);
			$locator = $this->getLocator($by, $value);
			$element = $driver->wait($timeout / 1000)->until(
				WebDriverExpectedCondition::presenceOfElementLocated($locator)
			);
			$element->sendKeys($filePath);
			return ToolResult::text('File upload initiated');
		} catch (\Exception $e) {
			return ToolResult::error("Error uploading file: {$e->getMessage()}");
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
