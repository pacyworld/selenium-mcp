<?php
/**
 * Enchilada WebSocket — Stream Transport
 *
 * Default transport implementation using PHP's stream_socket_client.
 * Works with stream_select() out of the box. Expose getStream() to
 * integrate with any external event loop (kqueue, ext-ev, etc.).
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

		stream_set_timeout($this->stream, (int) $timeout, (int) (($timeout - (int) $timeout) * 1000000));
	}

	public function read(int $length): string
	{
		if ($this->stream === null) {
			throw new \RuntimeException('WebSocket transport not connected');
		}

		$data = @fread($this->stream, $length);

		if ($data === false) {
			$this->stream = null;
			throw new \RuntimeException('WebSocket read error');
		}

		return $data;
	}

	public function readExact(int $length): string
	{
		$buffer = '';
		$remaining = $length;

		while ($remaining > 0) {
			$chunk = $this->read($remaining);

			if ($chunk === '') {
				if (!$this->isConnected()) {
					throw new \RuntimeException("WebSocket connection closed while reading (got " . strlen($buffer) . " of {$length} bytes)");
				}
				usleep(1000);
				continue;
			}

			$buffer .= $chunk;
			$remaining -= strlen($chunk);
		}

		return $buffer;
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
	}

	public function setBlocking(bool $blocking): void
	{
		if ($this->stream === null) {
			throw new \RuntimeException('WebSocket transport not connected');
		}

		stream_set_blocking($this->stream, $blocking);
	}
}
