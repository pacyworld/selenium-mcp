<?php
/**
 * Selenium MCP Server — Cookie Management Tools
 *
 * @package    SeleniumMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use EnchiladaMCP\McpTool;
use EnchiladaMCP\ToolResult;
use Selenium\SessionManager;
use Facebook\WebDriver\Cookie;

class CookieTools
{
	private SessionManager $manager;

	public function __construct(SessionManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'add_cookie',
		description: "Add a cookie. The browser must already be on a page from the cookie's domain.",
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string'],
				'value' => ['type' => 'string'],
				'domain' => ['type' => 'string'],
				'path' => ['type' => 'string'],
				'secure' => ['type' => 'boolean'],
				'httpOnly' => ['type' => 'boolean'],
				'expiry' => ['type' => 'number', 'description' => 'Unix timestamp (seconds)'],
				'session_id' => ['type' => 'string', 'description' => 'From start_browser; default most recent'],
			],
			'required' => ['name', 'value'],
		]
	)]
	public function add_cookie(
		string $name,
		string $value,
		string $domain = '',
		string $path = '',
		bool $secure = false,
		bool $httpOnly = false,
		int $expiry = 0,
		string $session_id = ''
	): ToolResult {
		try {
			$driver = $this->manager->getDriver($session_id ?: null);

			$cookie = new Cookie($name, $value);
			if (!empty($domain)) $cookie->setDomain($domain);
			if (!empty($path)) $cookie->setPath($path);
			if ($secure) $cookie->setSecure($secure);
			if ($httpOnly) $cookie->setHttpOnly($httpOnly);
			if ($expiry > 0) $cookie->setExpiry($expiry);

			$driver->manage()->addCookie($cookie);
			return ToolResult::text("Cookie \"{$name}\" added");
		} catch (\Exception $e) {
			return ToolResult::error("Error adding cookie: {$e->getMessage()}");
		}
	}

	#[McpTool(
		name: 'get_cookies',
		readOnlyHint: true,
		description: 'Get one cookie by name, or all cookies.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Omit for all cookies'],
				'session_id' => ['type' => 'string', 'description' => 'From start_browser; default most recent'],
			],
		]
	)]
	public function get_cookies(string $name = '', string $session_id = ''): ToolResult
	{
		try {
			$driver = $this->manager->getDriver($session_id ?: null);

			if (!empty($name)) {
				$cookie = $driver->manage()->getCookieNamed($name);
				if ($cookie === null) {
					return ToolResult::error("Cookie \"{$name}\" not found");
				}
				return ToolResult::text(json_encode($cookie, JSON_PRETTY_PRINT));
			}

			$cookies = $driver->manage()->getCookies();
			return ToolResult::text(json_encode($cookies, JSON_PRETTY_PRINT));
		} catch (\Exception $e) {
			return ToolResult::error("Error getting cookies: {$e->getMessage()}");
		}
	}

	#[McpTool(
		name: 'delete_cookie',
		description: 'Delete one cookie by name, or all cookies.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Omit to delete all cookies'],
				'session_id' => ['type' => 'string', 'description' => 'From start_browser; default most recent'],
			],
		]
	)]
	public function delete_cookie(string $name = '', string $session_id = ''): ToolResult
	{
		try {
			$driver = $this->manager->getDriver($session_id ?: null);

			if (!empty($name)) {
				$driver->manage()->deleteCookieNamed($name);
				return ToolResult::text("Cookie \"{$name}\" deleted");
			}

			$driver->manage()->deleteAllCookies();
			return ToolResult::text('All cookies deleted');
		} catch (\Exception $e) {
			return ToolResult::error("Error deleting cookie: {$e->getMessage()}");
		}
	}
}
