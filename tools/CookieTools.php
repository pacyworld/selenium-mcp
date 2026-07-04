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
		description: "adds a cookie to the current browser session. The browser must be on a page from the cookie's domain before setting it.",
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Name of the cookie'],
				'value' => ['type' => 'string', 'description' => 'Value of the cookie'],
				'domain' => ['type' => 'string', 'description' => 'Domain the cookie is visible to'],
				'path' => ['type' => 'string', 'description' => 'Path the cookie is visible to'],
				'secure' => ['type' => 'boolean', 'description' => 'Whether the cookie is a secure cookie'],
				'httpOnly' => ['type' => 'boolean', 'description' => 'Whether the cookie is HTTP only'],
				'expiry' => ['type' => 'number', 'description' => 'Expiry date of the cookie as a Unix timestamp (seconds since epoch)'],
				'session_id' => ['type' => 'string', 'description' => 'Session ID from start_browser (optional; targets the most recently started session if omitted)'],
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
		description: 'retrieves cookies from the current browser session. Returns all cookies or a specific cookie by name.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Name of a specific cookie to retrieve. If omitted, all cookies are returned.'],
				'session_id' => ['type' => 'string', 'description' => 'Session ID from start_browser (optional; targets the most recently started session if omitted)'],
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
		description: 'deletes cookies from the current browser session. Can delete a specific cookie by name or all cookies.',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Name of the cookie to delete. If omitted, all cookies are deleted.'],
				'session_id' => ['type' => 'string', 'description' => 'Session ID from start_browser (optional; targets the most recently started session if omitted)'],
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
