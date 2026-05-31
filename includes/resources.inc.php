<?php
/**
 * Selenium MCP Server — Resource Registration
 *
 * @package    SeleniumMCP
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use EnchiladaMCP\McpResource;

// Browser Status Resource
$server->register(new class($sessionManager) {
	private \Selenium\SessionManager $manager;

	public function __construct(\Selenium\SessionManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpResource(
		uriTemplate: 'browser-status://current',
		name: 'browser-status',
		description: 'Current browser session status',
		mimeType: 'text/plain'
	)]
	public function getStatus(): array
	{
		$session = $this->manager->getCurrentSession();
		$text = $session
			? "Active browser session: {$session}"
			: "No active browser session";

		return [
			'contents' => [[
				'uri' => 'browser-status://current',
				'mimeType' => 'text/plain',
				'text' => $text,
			]],
		];
	}
});

// Accessibility Snapshot Resource
$server->register(new class($sessionManager) {
	private \Selenium\SessionManager $manager;

	#[McpResource(
		uriTemplate: 'accessibility://current',
		name: 'accessibility-snapshot',
		description: 'Accessibility tree snapshot of the current page. A compact, structured representation of interactive elements and text content, much smaller than full HTML. Useful for understanding page layout and finding elements to interact with.',
		mimeType: 'application/json'
	)]
	public function getSnapshot(): array
	{
		if (!$this->manager->hasSession()) {
			throw new \RuntimeException('No active browser session. Start a browser first.');
		}

		$driver = $this->manager->getDriver();
		$script = file_get_contents(APPLICATION_ROOT . 'includes/accessibility-snapshot.js');
		$tree = $driver->executeScript($script);

		return [
			'contents' => [[
				'uri' => 'accessibility://current',
				'mimeType' => 'application/json',
				'text' => json_encode($tree ?? new \stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
			]],
		];
	}

	public function __construct(\Selenium\SessionManager $manager)
	{
		$this->manager = $manager;
	}
});
