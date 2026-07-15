<?php
/**
 * Enchilada WebSocket — Stream Transport
 *
 * Non-blocking transport using PHP stream_socket_client with an
 * internal read buffer. drain() pulls available bytes from the stream;
 * consume() pulls from the buffer. Neither ever blocks.
 *
 * The connect() method is the only blocking call (one-time setup).
 * After connect, the stream is set to non-blocking mode permanently.
 *
 * @package    EnchiladaWebSocket
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace EnchiladaWebSocket;

class StreamTransport implements WebSocketTransportInterface
{
	/** @var resource|null */
	private $stream = null;

	/** @var string Internal read buffer */
	private string $buffer = '';

	public function connect(string $host, int $port, bool $tls = false, float $timeout = 5.0): void
	{
		$scheme = $tls ? 'tls' : 'tcp';
		$address = "{$scheme}://{$host}:{$port}";

		$context = stream_context_create();
		$errno = 0;
		$errstr = '';

		$this->stream = @stream_socket_client(
			$address,
			$errno,
			$errstr,
			$timeout,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if ($this->stream === false) {
			$this->stream = null;
			throw new \RuntimeException("WebSocket connection failed to {$address}: [{$errno}] {$errstr}");
		}

		// Non-blocking from this point forward
		stream_set_blocking($this->stream, false);
	}

	public function drain(): int
	{
		if ($this->stream === null) {
			throw new \RuntimeException('WebSocket transport not connected');
		}

		$total = 0;

		while (true) {
			$chunk = @fread($this->stream, 65536);

			if ($chunk === false) {
				@fclose($this->stream);
				$this->stream = null;
				throw new \RuntimeException('WebSocket read error');
			}

			if ($chunk === '') {
				// Distinguish "no data right now" from peer close (EOF).
				// At EOF the fd stays permanently readable (level-triggered),
				// so leaving the stream open would spin the reactor forever.
				if (feof($this->stream)) {
					@fclose($this->stream);
					$this->stream = null;
					throw new \RuntimeException('WebSocket connection closed by peer');
				}
				break; // No more data available (non-blocking)
			}

			$this->buffer .= $chunk;
			$total += strlen($chunk);
		}

		return $total;
	}

	public function consume(int $length): string
	{
		if ($this->buffer === '') {
			return '';
		}

		if (strlen($this->buffer) <= $length) {
			$data = $this->buffer;
			$this->buffer = '';
			return $data;
		}

		$data = substr($this->buffer, 0, $length);
		$this->buffer = substr($this->buffer, $length);
		return $data;
	}

	public function buffered(): int
	{
		return strlen($this->buffer);
	}

	/**
	 * Prepend data to the front of the read buffer.
	 * Used to put back unconsumed bytes after partial frame parsing.
	 */
	public function prepend(string $data): void
	{
		$this->buffer = $data . $this->buffer;
	}

	public function write(string $data): int
	{
		if ($this->stream === null) {
			throw new \RuntimeException('WebSocket transport not connected');
		}

		$total = strlen($data);
		$written = 0;

		while ($written < $total) {
			$bytes = @fwrite($this->stream, substr($data, $written));

			if ($bytes === false || $bytes === 0) {
				@fclose($this->stream);
				$this->stream = null;
				throw new \RuntimeException('WebSocket write error');
			}

			$written += $bytes;
		}

		return $written;
	}

	public function getStream(): mixed
	{
		return $this->stream;
	}

	public function isConnected(): bool
	{
		if ($this->stream === null) {
			return false;
		}

		return !feof($this->stream);
	}

	public function close(): void
	{
		if ($this->stream !== null) {
			@fclose($this->stream);
			$this->stream = null;
		}
		$this->buffer = '';
	}
}
