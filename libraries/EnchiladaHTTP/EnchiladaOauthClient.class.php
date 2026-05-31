<?php

/* Enchilada Framework 3.0 
 * OAuth2 Client (Client Credentials Flow)
 *
 * Helper for obtaining and caching OAuth2 access tokens using the
 * client_credentials grant type.
 */

class EnchiladaOauthClient {

	/** @var EnchiladaHTTP */
	protected $http;

	/** @var string */
	protected $token_endpoint;

	/** @var string */
	protected $client_id;

	/** @var string */
	protected $client_secret;

	/** @var string|null */
	protected $scope;

	/** @var string|null */
	protected $access_token;

	/** @var int|null */
	protected $expires_at;

	/**
	 * Create a new OAuth client for the client_credentials grant.
	 *
	 * @param EnchiladaHTTP $http          HTTP client configured for the auth server base URL.
	 * @param string        $tokenEndpoint Relative or absolute token endpoint path.
	 * @param string        $clientId      OAuth2 client_id.
	 * @param string        $clientSecret  OAuth2 client_secret.
	 * @param string|null   $scope         Optional space-separated scope string.
	 */
	public function __construct(EnchiladaHTTP $http, $tokenEndpoint, $clientId, $clientSecret, $scope = null) {
		$this->http = $http;
		$this->token_endpoint = $tokenEndpoint;
		$this->client_id = $clientId;
		$this->client_secret = $clientSecret;
		$this->scope = $scope;
	}

	/**
	 * Returns a valid access token, fetching a new one if necessary.
	 *
	 * @return string
	 * @throws Exception when a token cannot be obtained.
	 */
	public function getAccessToken() {
		if (!empty($this->access_token) && !empty($this->expires_at) && $this->expires_at > (time() + 60)) {
			return $this->access_token;
		}

		return $this->requestNewAccessToken();
	}

	/**
	 * Apply the Authorization header to the provided headers array.
	 *
	 * @param array $headers Existing headers (array of "Header: value" strings).
	 * @return array Updated headers including Authorization.
	 */
	public function applyAuthorizationHeader(array $headers = array()) {
		$token = $this->getAccessToken();
		$headers[] = 'Authorization: Bearer ' . $token;
		return $headers;
	}

	/**
	 * Performs the client_credentials grant against the token endpoint.
	 *
	 * @return string access_token
	 * @throws Exception when the token response is invalid or missing fields.
	 */
	protected function requestNewAccessToken() {
		$data = array(
			'grant_type' => 'client_credentials',
			'client_id' => $this->client_id,
			'client_secret' => $this->client_secret,
		);

		if (!empty($this->scope)) {
			$data['scope'] = $this->scope;
		}

		$response = $this->http->call($this->token_endpoint, $data, 'POST', array(), null, 'form');

		if (!is_array($response) || empty($response['access_token'])) {
			throw new Exception('Failed to obtain access token from OAuth server');
		}

		$this->access_token = $response['access_token'];

		if (!empty($response['expires_in']) && is_numeric($response['expires_in'])) {
			$this->expires_at = time() + (int)$response['expires_in'];
		} else {
			$this->expires_at = null;
		}

		return $this->access_token;
	}

}
