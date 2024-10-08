<?php

namespace SyncEngine\WordPress\Api;

class Client
{
	private $token;
	private $host;
	private $options;
	private $root;
	private $localhost = false;

	public function __construct( $host, $token, $options = [] ) {
		$this->host    = $host;
		$this->token   = $token;
		$this->options = $options;
		if ( empty( $this->options['version'] ) ) {
			$this->options['version'] = 1;
		}

		$localhosts = [
			'localhost',
			'127.0.0.1',
			'::1',
		];

		$parts = parse_url( $host );

		if ( in_array( $parts['host'], $localhosts ) ) {
			$this->localhost = true;
		}

		$this->root = trailingslashit( $this->host ) . 'api/';
	}

	public function clearCache() {
		delete_transient( 'syncengine_api_status' );
		delete_transient( 'syncengine_api_endpoints' );
	}

	public function request( $endpoint, $method = 'GET', $options = [] ) {

		$options = array_merge( $this->options, $options );

		$options['method'] = $method;

		if ( empty( $options['auth_header'] ) ) {
			$options['headers'] = [
				'Authorization' => 'Bearer ' . $this->token,
			];
		}
		else {
			$options['headers'] = [
				$options['auth_header'] => $this->token,
			];
		}
		unset( $options['auth_header'] );

		$url = $this->root;

		if ( ! empty( $options['version'] ) ) {
			$url .= 'v' . $options['version'] . '/';
		}

		$url .= trailingslashit( ltrim( $endpoint, '/' ) );

		if ( $this->localhost ) {
			$options['sslverify'] = false;
		}

		$response = wp_remote_request( $url, $options );

		if ( is_wp_error( $response ) ) {
			$this->clearCache();
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 ) {
			$this->clearCache();

			$message = wp_remote_retrieve_response_message( $response );

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( isset( $body['message'] ) ) {
				$message .= ' "' . $body['message'] . '"';
			}

			return $code . ': ' . $message . ' (' . $url . ')';
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	public function status() {
		$cache = get_transient( 'syncengine_api_status' );
		if ( $cache ) {
			return $cache;
		}

		$result = $this->request( 'status', 'GET', [ 'version' => false ] );
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}
		if ( is_string( $result ) ) {
			return $result;
		}

		if ( ! empty( $result['status'] ) ) {
			set_transient( 'syncengine_api_status', $result['status'] );
		}

		return $result['status'] ?? '';
	}

	public function isOnline() {
		return 'online' === strtolower( (string) $this->status() );
	}

	public function listEndpoints() {
		$cache = get_transient( 'syncengine_api_endpoints' );
		if ( $cache ) {
			return $cache;
		}

		$result = $this->request( 'endpoint', 'GET', [ 'version' => false ] );
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}
		if ( is_string( $result ) ) {
			return $result;
		}

		if ( ! empty( $result ) ) {
			set_transient( 'syncengine_api_endpoints', $result );
		}

		return $result;
	}

	public function executeEndpoint( $endpoint ) {
		$result = $this->request( 'endpoint/' . $endpoint, 'GET', [ 'version' => false ] );
		if ( is_wp_error( $result ) ) {
			return [ 'success' => false, 'error' => $result->get_error_message() ];
		}
		if ( is_string( $result ) ) {
			return [ 'success' => false, 'error' => $result ];
		}
		return $result;
	}
}
