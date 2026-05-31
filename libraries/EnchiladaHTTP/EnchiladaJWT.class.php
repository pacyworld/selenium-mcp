<?php

/* Enchilada Framework 3.0 
 * JWT Utility
 * 
 * $Id$
 * 
 * Software License Agreement (BSD License)
 * 
 * Copyright (c) 2025, The Daniel Morante Company, Inc.
 * All rights reserved.
 * 
 * Redistribution and use of this software in source and binary forms, with or without modification, are
 * permitted provided that the following conditions are met:
 * 
 *   Redistributions of source code must retain the above
 *   copyright notice, this list of conditions and the
 *   following disclaimer.
 * 
 *   Redistributions in binary form must reproduce the above
 *   copyright notice, this list of conditions and the
 *   following disclaimer in the documentation and/or other
 *   materials provided with the distribution.
 * 
 *   Neither the name of The Daniel Morante Company, Inc. nor the names of its
 *   contributors may be used to endorse or promote products
 *   derived from this software without specific prior
 *   written permission of The Daniel Morante Company, Inc.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
 * PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR
 * TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
 * ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/* 
 * Helper for generating RFC7519 JSON Web Tokens using HMAC-based algorithms.
 * @author Daniel Morante
 */

class EnchiladaJWT {

	const DEFAULT_ALGO = 'HS256';

	/**
	 * Map JWT alg values to hash_hmac algorithms.
	 */
	protected static $hashAlgos = array(
		'HS256' => 'sha256',
		'HS384' => 'sha384',
		'HS512' => 'sha512',
	);

	/**
	 * Generate an RFC7519 JSON Web Token using the given header and payload,
	 * signed by the provided secret.
	 *
	 * @param array $payload_data Claims/payload to include in the token.
	 * @param string $secret Shared secret used for HMAC signing.
	 * @param array $header_data Optional additional header parameters.
	 * @param string $alg JWT algorithm identifier (e.g. HS256, HS384, HS512).
	 * @return string JWT
	 * @throws InvalidArgumentException When the algorithm is unsupported.
	 * @throws RuntimeException When JSON encoding fails.
	 */
	public static function encode(array $payload_data, string $secret, array $header_data = array(), string $alg = self::DEFAULT_ALGO): string {

		if (!isset(self::$hashAlgos[$alg])) {
			throw new InvalidArgumentException('Unsupported JWT algorithm: ' . $alg);
		}

		$hashAlgo = self::$hashAlgos[$alg];

		// Merge default header values; caller-supplied values override defaults.
		$header_data = array_merge(
			array(
				'alg' => $alg,
				'typ' => 'JWT',
			),
			$header_data
		);

		$headerJson = json_encode($header_data);
		if ($headerJson === false) {
			throw new RuntimeException('Failed to JSON-encode JWT header: ' . json_last_error_msg());
		}

		$payloadJson = json_encode($payload_data);
		if ($payloadJson === false) {
			throw new RuntimeException('Failed to JSON-encode JWT payload: ' . json_last_error_msg());
		}

		$header = self::base64url_encode($headerJson);
		$payload = self::base64url_encode($payloadJson);

		$signature = self::base64url_encode(hash_hmac($hashAlgo, $header . '.' . $payload, $secret, true));

		return $header . '.' . $payload . '.' . $signature;
	}

	/**
	 * Encodes input using base64url encoding.
	 *
	 * @param string $data
	 * @return string
	 */
	protected static function base64url_encode(string $data): string {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * Decodes a base64url-encoded string.
	 *
	 * @param string $data
	 * @return string
	 */
	protected static function base64url_decode(string $data): string {
		$remainder = strlen($data) % 4;
		if ($remainder) {
			$data .= str_repeat('=', 4 - $remainder);
		}
		return base64_decode(strtr($data, '-_', '+/'));
	}

}
