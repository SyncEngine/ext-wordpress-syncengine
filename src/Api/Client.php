<?php

namespace SyncEngine\WordPress\Api;

class Client
{
	private $token;
	private $host;
	private $options;
	private $version;
	private $root;
	private $localhost = false;

	public function __construct( $host, $token, $options = [] ) {
		$this->host    = $host;
		$this->token   = $token;
		$this->version = $options['version'] ?: 1;
		$this->options = $options;

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
			return $response;
		}

		if ( wp_remote_retrieve_response_code( $response ) != 200 ) {
			return wp_remote_retrieve_response_code( $response ) . ': ' . wp_remote_retrieve_response_message( $response ) . ' (' . $url . ')';
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	public function status() {
		$result = $this->request( 'status', 'GET', [ 'version' => false ] );
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}
		if ( is_string( $result ) ) {
			return $result;
		}
		return $result['status'] ?? '';
	}

	public function listEndpoints() {
		$result = $this->request( 'endpoints', 'GET', [ 'version' => false ] );
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}
		if ( is_string( $result ) ) {
			return $result;
		}
		return $result;
	}

	public function executeEndpoint( $endpoint ) {
		$result = $this->request( $endpoint, 'GET', [ 'version' => false ] );
		if ( is_wp_error( $result ) ) {
			return [ 'success' => false, 'error' => $result->get_error_message() ];
		}
		if ( is_string( $result ) ) {
			return [ 'success' => false, 'error' => $result ];
		}
		return $result;
	}
}
