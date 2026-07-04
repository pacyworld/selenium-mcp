<?php
/**
 * Selenium MCP Server — BiDi Diagnostics Tools
 *
 * @package    SeleniumMCP\Tools
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

use EnchiladaMCP\McpTool;
use EnchiladaMCP\ToolResult;
use Selenium\SessionManager;

class DiagnosticsTools
{
	private SessionManager $manager;

	public function __construct(SessionManager $manager)
	{
		$this->manager = $manager;
	}

	#[McpTool(
		name: 'diagnostics',
		readOnlyHint: true,
		description: 'retrieves browser diagnostics (console logs, JS errors, or network activity) captured via WebDriver BiDi',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'type' => ['type' => 'string', 'enum' => ['console', 'errors', 'network'], 'description' => 'Type of diagnostic data to retrieve'],
				'clear' => ['type' => 'boolean', 'description' => 'Clear after returning (default: false)'],
				'session_id' => ['type' => 'string', 'description' => 'Session ID from start_browser (optional; targets the most recently started session if omitted)'],
			],
			'required' => ['type'],
		]
	)]
	public function diagnostics(string $type, bool $clear = false, string $session_id = ''): ToolResult
	{
		try {
			$sessionId = $session_id ?: null;
			$this->manager->getDriver($sessionId); // ensure session exists

			$bidi = $this->manager->getBiDiClient($sessionId);
			if ($bidi === null || !$bidi->isConnected()) {
				return ToolResult::text('Diagnostics not available (BiDi not supported by this browser/driver)');
			}

			// Drain any pending events before reading logs
			$bidi->drainEvents();

			$logs = $bidi->getLogs($type);

			if (empty($logs)) {
				$emptyMessages = [
					'console' => 'No console logs captured',
					'errors' => 'No page errors captured',
					'network' => 'No network activity captured',
				];
				return ToolResult::text($emptyMessages[$type] ?? 'No data');
			}

			$result = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

			if ($clear) {
				$bidi->clearLogs($type);
			}

			return ToolResult::text($result);
		} catch (\Exception $e) {
			return ToolResult::error("Error getting diagnostics: {$e->getMessage()}");
		}
	}
}
