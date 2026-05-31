<?php
/**
 * Enchilada WebSocket — Frame Encoder/Decoder
 *
 * Pure data transformation: encodes and decodes RFC 6455 WebSocket frames.
 * No I/O — operates only on byte strings.
 *
 * @package    EnchiladaWebSocket
 * @author     Daniel Morante
 * @copyright  2026 The Daniel Morante Company, Inc.
 * @license    BSD-2-Clause
 */

namespace EnchiladaWebSocket;

class WebSocketFrame
{
	// Opcodes (RFC 6455 Section 5.2)
	const OPCODE_CONTINUATION = 0x0;
	const OPCODE_TEXT         = 0x1;
	const OPCODE_BINARY       = 0x2;
	const OPCODE_CLOSE        = 0x8;
	const OPCODE_PING         = 0x9;
	const OPCODE_PONG         = 0xA;

	public int $opcode;
	public string $payload;
	public bool $fin;
	public bool $masked;

	public function __construct(int $opcode, string $payload, bool $fin = true, bool $masked = true)
	{
		$this->opcode = $opcode;
		$this->payload = $payload;
		$this->fin = $fin;
		$this->masked = $masked;
	}

	/**
	 * Encode this frame into a wire-format byte string.
	 *
	 * Clients MUST mask frames per RFC 6455 Section 5.3.
	 */
	public function encode(): string
	{
		$payload = $this->payload;
		$length = strlen($payload);
		$frame = '';

		// First byte: FIN + opcode
		$firstByte = ($this->fin ? 0x80 : 0x00) | ($this->opcode & 0x0F);
		$frame .= chr($firstByte);

		// Second byte: MASK flag + payload length
		$maskBit = $this->masked ? 0x80 : 0x00;

		if ($length < 126) {
			$frame .= chr($maskBit | $length);
		} elseif ($length < 65536) {
			$frame .= chr($maskBit | 126);
			$frame .= pack('n', $length);
		} else {
			$frame .= chr($maskBit | 127);
			$frame .= pack('J', $length);
		}

		// Masking key + masked payload (client frames)
		if ($this->masked) {
			$maskKey = random_bytes(4);
			$frame .= $maskKey;

			$maskedPayload = '';
			for ($i = 0; $i < $length; $i++) {
				$maskedPayload .= chr(ord($payload[$i]) ^ ord($maskKey[$i % 4]));
			}
			$frame .= $maskedPayload;
		} else {
			$frame .= $payload;
		}

		return $frame;
	}

	/**
	 * Decode a frame header from raw bytes read from the transport.
	 *
	 * Returns the frame and the number of header bytes consumed.
	 * The caller must provide the full frame data (header + payload).
	 *
	 * For streaming use, call decodeHeader() first, then read payload bytes.
	 *
	 * @param WebSocketTransportInterface $transport Transport to read from
	 * @return self Decoded frame
	 */
	public static function readFrom(WebSocketTransportInterface $transport): self
	{
		// Read first 2 bytes (minimum frame header)
		$header = $transport->readExact(2);

		$firstByte = ord($header[0]);
		$secondByte = ord($header[1]);

		$fin = (bool) ($firstByte & 0x80);
		$opcode = $firstByte & 0x0F;
		$masked = (bool) ($secondByte & 0x80);
		$length = $secondByte & 0x7F;

		// Extended payload length
		if ($length === 126) {
			$extLen = $transport->readExact(2);
			$length = unpack('n', $extLen)[1];
		} elseif ($length === 127) {
			$extLen = $transport->readExact(8);
			$length = unpack('J', $extLen)[1];
		}

		// Masking key (server frames are typically unmasked, but handle both)
		$maskKey = null;
		if ($masked) {
			$maskKey = $transport->readExact(4);
		}

		// Payload
		$payload = '';
		if ($length > 0) {
			$payload = $transport->readExact($length);

			if ($masked && $maskKey !== null) {
				$unmasked = '';
				for ($i = 0; $i < $length; $i++) {
					$unmasked .= chr(ord($payload[$i]) ^ ord($maskKey[$i % 4]));
				}
				$payload = $unmasked;
			}
		}

		return new self($opcode, $payload, $fin, $masked);
	}

	/**
	 * Create a text frame.
	 */
	public static function text(string $payload, bool $masked = true): self
	{
		return new self(self::OPCODE_TEXT, $payload, true, $masked);
	}

	/**
	 * Create a binary frame.
	 */
	public static function binary(string $payload, bool $masked = true): self
	{
		return new self(self::OPCODE_BINARY, $payload, true, $masked);
	}

	/**
	 * Create a close frame.
	 *
	 * @param int $code Close status code (1000 = normal)
	 * @param string $reason Optional close reason
	 */
	public static function close(int $code = 1000, string $reason = '', bool $masked = true): self
	{
		$payload = pack('n', $code) . $reason;
		return new self(self::OPCODE_CLOSE, $payload, true, $masked);
	}

	/**
	 * Create a ping frame.
	 */
	public static function ping(string $payload = '', bool $masked = true): self
	{
		return new self(self::OPCODE_PING, $payload, true, $masked);
	}

	/**
	 * Create a pong frame (response to ping).
	 */
	public static function pong(string $payload = '', bool $masked = true): self
	{
		return new self(self::OPCODE_PONG, $payload, true, $masked);
	}

	/**
	 * Check if this is a control frame.
	 */
	public function isControl(): bool
	{
		return ($this->opcode & 0x08) !== 0;
	}
}
