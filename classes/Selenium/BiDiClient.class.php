<?php
/**
 * Selenium MCP Server — BiDi Client
 *
 * WebSocket BiDi event subscriber. Connects to the browser's BiDi
 * WebSocket URL and subscribes to log + network events. Buffers
 * events for retrieval by the diagnostics tool.
 *
 * @package    SeleniumMCP
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace Selenium;

use EnchiladaWebSocket\WebSocketClient;
use EnchiladaWebSocket\StreamTransport;

class BiDiClient
{
	private ?WebSocketClient $ws = null;
	private array $consoleLogs = [];
	private array $pageErrors = [];
	private array $networkLogs = [];

	/**
	 * Connect to a BiDi WebSocket URL and subscribe to events.
	 */
	public function connect(string $bidiUrl): void
	{
		$this->ws = new WebSocketClient(new StreamTransport());
		$this->ws->connect($bidiUrl);

		// Subscribe to log and network events
		$subscribeMessage = json_encode([
			'method' => 'session.subscribe',
			'params' => [
				'events' => [
					'log.entryAdded',
					'network.responseCompleted',
					'network.fetchError',
				],
			],
			'id' => 1,
		]);

		$this->ws->send($subscribeMessage);

		// Set non-blocking for polling
		$this->ws->getTransport()->setBlocking(false);
	}

	/**
	 * Check if BiDi WebSocket is connected.
	 */
	public function isConnected(): bool
	{
		return $this->ws !== null && $this->ws->isConnected();
	}

	/**
	 * Get the stream resource for event loop integration.
	 *
	 * @return resource|null
	 */
	public function getStream(): mixed
	{
		if ($this->ws === null) {
			return null;
		}
		return $this->ws->getTransport()->getStream();
	}

	/**
	 * Process any pending BiDi events (non-blocking).
	 * Call this when stream_select indicates data on getStream().
	 */
	public function processEvents(): void
	{
		if (!$this->isConnected()) {
			return;
		}

		// Set blocking briefly to read the available frame
		$this->ws->getTransport()->setBlocking(true);
		stream_set_timeout($this->ws->getTransport()->getStream(), 0, 100000); // 100ms timeout

		try {
			$message = $this->ws->receive();
			if ($message === null) {
				return;
			}

			$event = json_decode($message, true);
			if ($event === null) {
				return;
			}

			$this->handleEvent($event);
		} catch (\RuntimeException $e) {
			// Timeout or connection issue — ignore
		} finally {
			if ($this->ws !== null && $this->ws->getTransport()->isConnected()) {
				$this->ws->getTransport()->setBlocking(false);
			}
		}
	}

	/**
	 * Get buffered logs by type.
	 */
	public function getLogs(string $type): array
	{
		return match ($type) {
			'console' => $this->consoleLogs,
			'errors' => $this->pageErrors,
			'network' => $this->networkLogs,
			default => [],
		};
	}

	/**
	 * Clear buffered logs by type.
	 */
	public function clearLogs(string $type): void
	{
		match ($type) {
			'console' => $this->consoleLogs = [],
			'errors' => $this->pageErrors = [],
			'network' => $this->networkLogs = [],
			default => null,
		};
	}

	/**
	 * Disconnect the BiDi WebSocket.
	 */
	public function disconnect(): void
	{
		if ($this->ws !== null) {
			$this->ws->disconnect();
			$this->ws = null;
		}
	}

	private function handleEvent(array $event): void
	{
		$method = $event['method'] ?? '';
		$params = $event['params'] ?? [];

		switch ($method) {
			case 'log.entryAdded':
				$level = $params['level'] ?? 'info';
				$entry = [
					'level' => $level,
					'text' => $params['text'] ?? '',
					'timestamp' => $params['timestamp'] ?? null,
					'type' => $params['type'] ?? null,
				];

				if ($level === 'error' && ($params['type'] ?? '') === 'javascript') {
					$entry['stackTrace'] = $params['stackTrace'] ?? null;
					$this->pageErrors[] = $entry;
				} else {
					$this->consoleLogs[] = $entry;
				}
				break;

			case 'network.responseCompleted':
				$this->networkLogs[] = [
					'type' => 'response',
					'url' => $params['request']['url'] ?? null,
					'status' => $params['response']['status'] ?? null,
					'method' => $params['request']['method'] ?? null,
					'mimeType' => $params['response']['mimeType'] ?? null,
					'timestamp' => time(),
				];
				break;

			case 'network.fetchError':
				$this->networkLogs[] = [
					'type' => 'error',
					'url' => $params['request']['url'] ?? null,
					'method' => $params['request']['method'] ?? null,
					'errorText' => $params['errorText'] ?? null,
					'timestamp' => time(),
				];
				break;
		}
	}
}
