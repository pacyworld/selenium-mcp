<?php

/* Enchilada Framework 3.0 
 * Non-blocking HTTP Client (curl_multi)
 *
 * Provides a multi-request HTTP client built on curl_multi_* that can be
 * driven by a PHP event loop or manual polling.
 */

class EnchiladaMultiHTTP {

	const CONTENT_TYPE_JSON = 'Content-Type: application/json';
	const CONTENT_TYPE_FORM_ENCODED = 'Content-type: application/x-www-form-urlencoded';
	const CONTENT_TYPE_NONE = NULL;
	const DEFAULT_HTTP_REQUEST_TIMEOUT = 10;

	protected $api_endpoint;
	protected $debug = APPLICATION_DEBUG;
	protected $useragent = APPLICATION_USERAGENT;
	protected $request_timeout;

	// This is meant to be overriden
	protected $default_headers = array();

	// Extra stuff to pass to CURL
	protected $ca_cert;
	protected $plaintext_auth;

	/** @var resource */
	protected $multiHandle;

	/**
	 * Map of requestId => [
	 *   'handle'   => resource,
	 *   'format'   => 'json'|'raw',
	 *   'response' => string|null,
	 * ]
	 */
	protected $requests = array();

	/** @var int */
	protected $nextRequestId = 1;

	/**
	 * Create a new instance.
	 *
	 * @param string $api_endpoint Base API endpoint URL.
	 * @throws Exception if cURL multi is not available.
	 */
	public function __construct($api_endpoint) {
		if (!function_exists('curl_multi_init')) {
			throw new Exception('cURL multi support is required for EnchiladaMultiHTTP');
		}

		$this->api_endpoint = $api_endpoint;

		if (defined('APPLICATION_HTTP_TIMEOUT')) {
			$this->request_timeout = APPLICATION_HTTP_TIMEOUT;
		} else {
			$this->request_timeout = self::DEFAULT_HTTP_REQUEST_TIMEOUT;
		}

		$this->multiHandle = curl_multi_init();
	}

	public function __destruct() {
		foreach ($this->requests as $req) {
			if (isset($req['handle'])) {
				curl_multi_remove_handle($this->multiHandle, $req['handle']);
				curl_close($req['handle']);
			}
		}

		if (is_resource($this->multiHandle)) {
			curl_multi_close($this->multiHandle);
		}
	}

	/**
	 * Queue a new HTTP request.
	 *
	 * @param string     $method       API method/path appended to base endpoint.
	 * @param array|null $data        Request payload or query parameters.
	 * @param string     $http_verb   HTTP verb (GET, POST, PUT, PATCH, DELETE).
	 * @param array      $extra_headers Additional headers.
	 * @param int|null   $timeout     Per-request timeout.
	 * @param string     $format      'json' (default) or 'form' or 'raw'.
	 * @return int       Request ID that can be used to retrieve the response.
	 */
	public function queue($method, $data = null, $http_verb = 'GET', array $extra_headers = array(), $timeout = null, $format = 'json') {
		$url = $this->build_request($method);
		$headers = $this->build_headers($extra_headers);
		$payload = $this->build_payload($data, $format);

		// For GET requests, encode array data as query parameters
		if ($http_verb === 'GET' && is_array($data) && !empty($data)) {
			$query = http_build_query($data);
			$url .= (strpos($url, '?') === false ? '?' : '&') . $query;
			// GET requests should not send a body
			$payload = null;
		}

		$handle = curl_init();

		$effectiveTimeout = $timeout !== null ? $timeout : $this->request_timeout;

		curl_setopt_array($handle, array(
			CURLOPT_URL => $url,
			CURLOPT_TIMEOUT => $effectiveTimeout,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CUSTOMREQUEST => $http_verb,
			CURLOPT_USERAGENT => $this->useragent,
		));

		// Automatically set Content-Type header based on format if not already present
		$hasContentType = false;
		foreach ($headers as $h) {
			if (stripos($h, 'Content-Type:') === 0) {
				$hasContentType = true;
				break;
			}
		}
		if (!$hasContentType && $http_verb !== 'GET') {
			if ($format === 'json') {
				$headers[] = self::CONTENT_TYPE_JSON;
			} elseif (is_array($data) && $format !== 'raw') {
				$headers[] = self::CONTENT_TYPE_FORM_ENCODED;
			}
		}

		curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);

		// Attach payload for non-GET requests
		if ($http_verb !== 'GET' && $payload !== null) {
			curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
		}

		// Optional CA certificate
		if (!empty($this->ca_cert)) {
			curl_setopt($handle, CURLOPT_CAINFO, $this->ca_cert);
		}

		// Plain text HTTP authemtication
		if (!empty($this->plaintext_auth)) {
			curl_setopt($handle, CURLOPT_USERPWD, $this->plaintext_auth);
		}

		curl_multi_add_handle($this->multiHandle, $handle);

		$requestId = $this->nextRequestId++;

		$this->requests[$requestId] = array(
			'handle' => $handle,
			'format' => $format,
			'raw' => null,
			'result' => null,
			'error' => null,
		);

		return $requestId;
	}

	/**
	 * Advance the curl_multi state machine.
	 * Call this from an event loop or periodically.
	 */
	public function tick() {
		$running = null;
		// Non-blocking: use a small timeout in select
		do {
			$status = curl_multi_exec($this->multiHandle, $running);
		} while ($status === CURLM_CALL_MULTI_PERFORM);

		// Process any completed transfers
		while ($info = curl_multi_info_read($this->multiHandle)) {
			$handle = $info['handle'];

			$requestId = $this->findRequestIdByHandle($handle);
			if ($requestId === null) {
				curl_multi_remove_handle($this->multiHandle, $handle);
				curl_close($handle);
				continue;
			}

			$raw = curl_multi_getcontent($handle);
			$this->requests[$requestId]['raw'] = $raw;

			if ($info['result'] !== CURLE_OK) {
				$this->requests[$requestId]['error'] = curl_error($handle);
			} else {
				$format = $this->requests[$requestId]['format'];
				if ($format === 'json') {
					$this->requests[$requestId]['result'] = $raw ? json_decode($raw, true) : false;
				} else {
					$this->requests[$requestId]['result'] = $raw;
				}
			}

			if ($this->debug) {
				print_r($raw);
				echo PHP_EOL;
				print_r(curl_getinfo($handle));
				echo PHP_EOL;
			}

			curl_multi_remove_handle($this->multiHandle, $handle);
			curl_close($handle);
		}
	}

	/**
	 * Returns true if there are any pending requests.
	 */
	public function hasPendingRequests() {
		foreach ($this->requests as $id => $req) {
			if ($req['result'] === null && $req['error'] === null) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get the result for a specific request ID (if completed).
	 *
	 * @param int $requestId
	 * @return array|null [ 'result' => mixed, 'error' => string|null, 'raw' => string|null ] or null if not finished.
	 */
	public function getResult($requestId) {
		if (!isset($this->requests[$requestId])) {
			return null;
		}

		$req = $this->requests[$requestId];
		if ($req['result'] === null && $req['error'] === null) {
			return null;
		}

		return array(
			'result' => $req['result'],
			'error' => $req['error'],
			'raw' => $req['raw'],
		);
	}

	/**
	 * Build the full request URL.
	 */
	protected function build_request($method) {
		return sprintf("%s/%s", $this->api_endpoint, $method);
	}

	/**
	 * Merge default and extra headers.
	 */
	protected function build_headers(array $extra_headers = array()) {
		return array_merge($this->default_headers, $extra_headers);
	}

	/**
	 * Formats the payload accordingly.
	 */
	protected function build_payload($data, $format) {
		if ($data === null) {
			return null;
		}

		if (is_array($data)) {
			if ($format === 'json') {
				return json_encode($data);
			} elseif ($format === 'form') {
				return http_build_query($data);
			}
		}

		return $data;
	}

	/**
	 * Find a request ID by its cURL handle.
	 *
	 * @param resource $handle
	 * @return int|null
	 */
	protected function findRequestIdByHandle($handle) {
		foreach ($this->requests as $id => $req) {
			if ($req['handle'] === $handle) {
				return $id;
			}
		}
		return null;
	}

}
