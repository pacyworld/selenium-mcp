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
use Selenium\BiDiClient;

class DiagnosticsTools
{
	private SessionManager $manager;
	private ?BiDiClient $bidi = null;

	public function __construct(SessionManager $manager)
	{
		$this->manager = $manager;
	}

	public function setBiDiClient(?BiDiClient $bidi): void
	{
		$this->bidi = $bidi;
	}

	#[McpTool(
		name: 'diagnostics',
		description: 'retrieves browser diagnostics (console logs, JS errors, or network activity) captured via WebDriver BiDi',
		inputSchema: [
			'type' => 'object',
			'properties' => [
				'type' => ['type' => 'string', 'enum' => ['console', 'errors', 'network'], 'description' => 'Type of diagnostic data to retrieve'],
				'clear' => ['type' => 'boolean', 'description' => 'Clear after returning (default: false)'],
			],
			'required' => ['type'],
		]
	)]
	public function diagnostics(string $type, bool $clear = false): ToolResult
	{
		try {
			$this->manager->getDriver(); // ensure session exists

			if ($this->bidi === null || !$this->bidi->isConnected()) {
				return ToolResult::text('Diagnostics not available (BiDi not supported by this browser/driver)');
			}

			$logs = $this->bidi->getLogs($type);

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
				$this->bidi->clearLogs($type);
			}

			return ToolResult::text($result);
		} catch (\Exception $e) {
			return ToolResult::error("Error getting diagnostics: {$e->getMessage()}");
		}
	}
}
