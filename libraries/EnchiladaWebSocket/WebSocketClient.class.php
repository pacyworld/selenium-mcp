<?php
/**
 * Enchilada WebSocket — Client
 *
 * High-level WebSocket client that orchestrates handshake and framing
 * through any transport implementation. I/O-agnostic: the client never
 * calls stream_select() or any event loop primitive itself.
 *
 * Usage with stream_select():
 *   $ws = new WebSocketClient(new StreamTransport());
 *   $ws->connect('ws://localhost:4444/session/abc/se/bidi');
 *   stream_select([$ws->getTransport()->getStream()], ...);
 *   $message = $ws->receive();
 *
 * Usage with kqueue (future):
 *   KEvent::create($ws->getTransport()->getStream(), Filter::READ, ...);
 *
 * @package    EnchiladaWebSocket
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace EnchiladaWebSocket;

class WebSocketClient
{
	private WebSocketTransportInterface $transport;
	private bool $connected = false;
	private ?\Closure $onMessage = null;
	private ?\Closure $onClose = null;
	private ?\Closure $onError = null;

	public function __construct(WebSocketTransportInterface $transport)
	{
		$this->transport = $transport;
	}

	/**
	 * Connect to a WebSocket server URL.
	 *
	 * @param string $url WebSocket URL (ws:// or wss://)
	 * @param array $headers Additional HTTP headers for the upgrade request
	 * @param float $timeout Connection timeout in seconds
	 * @throws \RuntimeException on connection or handshake failure
	 */
	public function connect(string $url, array $headers = [], float $timeout = 5.0): void
	{
		$parsed = parse_url($url);

		if ($parsed === false || !isset($parsed['host'])) {
			throw new \RuntimeException("Invalid WebSocket URL: {$url}");
		}

		$scheme = $parsed['scheme'] ?? 'ws';
		$host = $parsed['host'];
		$tls = ($scheme === 'wss');
		$port = $parsed['port'] ?? ($tls ? 443 : 80);
		$path = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

		// Establish TCP/TLS connection
		$this->transport->connect($host, $port, $tls, $timeout);

		// Perform WebSocket handshake
		$hostHeader = $host . ($port !== ($tls ? 443 : 80) ? ":{$port}" : '');
		$handshake = new WebSocketHandshake($hostHeader, $path, $headers);

		$request = $handshake->buildRequest();
		$this->transport->write($request);

		// Read and validate response
		$response = WebSocketHandshake::readResponseHeaders($this->transport);
		$handshake->validateResponse($response);

		$this->connected = true;
	}

	/**
	 * Send a text message.
	 *
	 * @param string $message Text payload
	 */
	public function send(string $message): void
	{
		$this->ensureConnected();
		$frame = WebSocketFrame::text($message);
		$this->transport->write($frame->encode());
	}

	/**
	 * Send a binary message.
	 *
	 * @param string $data Binary payload
	 */
	public function sendBinary(string $data): void
	{
		$this->ensureConnected();
		$frame = WebSocketFrame::binary($data);
		$this->transport->write($frame->encode());
	}

	/**
	 * Receive the next message from the server.
	 *
	 * Handles control frames (ping/pong/close) transparently.
	 * Returns null if a close frame is received.
	 *
	 * @return string|null Text/binary payload, or null on close
	 */
	public function receive(): ?string
	{
		$this->ensureConnected();

		$payload = '';

		while (true) {
			$frame = WebSocketFrame::readFrom($this->transport);

			// Handle control frames
			if ($frame->isControl()) {
				$this->handleControlFrame($frame);

				if ($frame->opcode === WebSocketFrame::OPCODE_CLOSE) {
					return null;
				}

				continue;
			}

			// Data frame — accumulate payload (handle fragmentation)
			$payload .= $frame->payload;

			if ($frame->fin) {
				return $payload;
			}
		}
	}

	/**
	 * Send a close frame and close the connection.
	 *
	 * @param int $code Close status code (1000 = normal)
	 * @param string $reason Optional close reason
	 */
	public function disconnect(int $code = 1000, string $reason = ''): void
	{
		if (!$this->connected) {
			return;
		}

		try {
			$frame = WebSocketFrame::close($code, $reason);
			$this->transport->write($frame->encode());
		} catch (\RuntimeException $e) {
			// Connection may already be broken
		}

		$this->connected = false;
		$this->transport->close();
	}

	/**
	 * Send a ping frame.
	 *
	 * @param string $payload Optional ping payload (max 125 bytes)
	 */
	public function ping(string $payload = ''): void
	{
		$this->ensureConnected();
		$frame = WebSocketFrame::ping($payload);
		$this->transport->write($frame->encode());
	}

	/**
	 * Get the underlying transport (for event loop integration).
	 *
	 * Use $client->getTransport()->getStream() to get the raw fd
	 * for stream_select(), kqueue, ext-ev, etc.
	 */
	public function getTransport(): WebSocketTransportInterface
	{
		return $this->transport;
	}

	/**
	 * Check if the WebSocket connection is active.
	 */
	public function isConnected(): bool
	{
		return $this->connected && $this->transport->isConnected();
	}

	/**
	 * Set callback for incoming messages.
	 */
	public function onMessage(callable $callback): void
	{
		$this->onMessage = $callback;
	}

	/**
	 * Set callback for connection close.
	 */
	public function onClose(callable $callback): void
	{
		$this->onClose = $callback;
	}

	/**
	 * Set callback for errors.
	 */
	public function onError(callable $callback): void
	{
		$this->onError = $callback;
	}

	/**
	 * Process any pending data on the WebSocket (non-blocking).
	 *
	 * Call this after stream_select() or kqueue indicates data is ready.
	 * Dispatches to onMessage/onClose callbacks if set.
	 *
	 * @return string|null Message payload, or null if close/no data
	 */
	public function poll(): ?string
	{
		try {
			$message = $this->receive();

			if ($message === null) {
				if ($this->onClose) {
					($this->onClose)();
				}
				return null;
			}

			if ($this->onMessage) {
				($this->onMessage)($message);
			}

			return $message;
		} catch (\RuntimeException $e) {
			$this->connected = false;
			if ($this->onError) {
				($this->onError)($e);
			}
			return null;
		}
	}

	private function handleControlFrame(WebSocketFrame $frame): void
	{
		switch ($frame->opcode) {
			case WebSocketFrame::OPCODE_PING:
				// Respond with pong (same payload)
				$pong = WebSocketFrame::pong($frame->payload);
				$this->transport->write($pong->encode());
				break;

			case WebSocketFrame::OPCODE_CLOSE:
				// Send close response
				$this->connected = false;
				try {
					$close = WebSocketFrame::close(1000);
					$this->transport->write($close->encode());
				} catch (\RuntimeException $e) {
					// Already disconnected
				}
				$this->transport->close();
				break;

			case WebSocketFrame::OPCODE_PONG:
				// Pong received — no action needed
				break;
		}
	}

	private function ensureConnected(): void
	{
		if (!$this->connected) {
			throw new \RuntimeException('WebSocket is not connected');
		}
	}
}
