<?php
/**
 * Enchilada WebSocket — Transport Interface
 *
 * Defines the I/O contract for WebSocket communication. Implementations
 * provide the actual byte-level read/write operations, allowing the
 * WebSocket protocol layer to remain I/O-agnostic.
 *
 * Built-in: StreamTransport (PHP stream_socket_client)
 * Extensible: implement this interface for ext-ev, ReactPHP, Swoole,
 * kqueue, or any other async I/O mechanism.
 *
 * @package    EnchiladaWebSocket
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace EnchiladaWebSocket;

interface WebSocketTransportInterface
{
	/**
	 * Establish a TCP or TLS connection to the given host and port.
	 *
	 * @param string $host Hostname or IP address
	 * @param int $port Port number
	 * @param bool $tls Whether to use TLS (wss://)
	 * @param float $timeout Connection timeout in seconds
	 * @throws \RuntimeException on connection failure
	 */
	public function connect(string $host, int $port, bool $tls = false, float $timeout = 5.0): void;

	/**
	 * Read up to $length bytes from the connection.
	 *
	 * In non-blocking mode, returns empty string if no data is available.
	 * In blocking mode, waits until data arrives or connection closes.
	 *
	 * @param int $length Maximum bytes to read
	 * @return string Raw bytes read (empty string if non-blocking and no data)
	 * @throws \RuntimeException on read error or closed connection
	 */
	public function read(int $length): string;

	/**
	 * Read exactly $length bytes from the connection (blocking).
	 *
	 * Loops until all bytes are received or connection closes.
	 *
	 * @param int $length Exact number of bytes to read
	 * @return string Exactly $length bytes
	 * @throws \RuntimeException if connection closes before all bytes arrive
	 */
	public function readExact(int $length): string;

	/**
	 * Write data to the connection.
	 *
	 * @param string $data Raw bytes to write
	 * @return int Number of bytes written
	 * @throws \RuntimeException on write error
	 */
	public function write(string $data): int;

	/**
	 * Get the underlying stream/socket resource for external event loop integration.
	 *
	 * Use this to register the fd with stream_select(), kqueue, ext-ev, etc.
	 * Returns null if not connected.
	 *
	 * @return resource|null The raw PHP stream resource
	 */
	public function getStream(): mixed;

	/**
	 * Check if the transport is currently connected.
	 */
	public function isConnected(): bool;

	/**
	 * Close the connection and release resources.
	 */
	public function close(): void;

	/**
	 * Set blocking mode on the underlying stream.
	 *
	 * @param bool $blocking true for blocking I/O, false for non-blocking
	 */
	public function setBlocking(bool $blocking): void;
}
