<?php

namespace SyncEngine\WordPress\Api;

class Client
{
	private $token;
	private $host;
	private $auth_header;
	private $version;
	private $root;
	private $localhost = false;

	public function __construct( $host, $token, $version = 1, $auth_header = null ) {
		$this->host        = $host;
		$this->token       = $token;
		$this->auth_header = $auth_header;
		$this->version     = $version ?: 1;

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
		$options['method'] = $method;

		if ( empty( $this->auth_header ) ) {
			$options['headers'] = [
				'Authorization' => 'Bearer ' . $this->token,
			];
		}
		else {
			$options['headers'] = [
				$this->auth_header => $this->token,
			];
		}

		$url = $this->root;

		if ( isset( $options['version'] ) ) {
			if ( ! empty( $options['version'] ) ) {
				$url .= 'v' . $options['version'] . '/';
			}
		} else {
			if ( ! empty( $this->version ) ) {
				$url .= 'v' . $this->version . '/';
			}
		}

		$url .= trailingslashit( ltrim( $endpoint, '/' ) );

		if ( $this->localhost ) {
			$options['sslverify'] = false;
		}

		$response = wp_remote_request( $url, $options );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	public function status() {
		$result = $this->request( 'status', 'GET', [ 'version' => false ] );
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}
		return $result['status'] ?? '';
	}

	public function listEndpoints() {
		$result = $this->request( 'endpoints', 'GET', [ 'version' => false ] );
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}
		return $result;
	}

	public function executeEndpoint( $endpoint ) {
		$result = $this->request( $endpoint, 'GET', [ 'version' => false ] );
		if ( is_wp_error( $result ) ) {
			return [ 'success' => false, 'error' => $result->get_error_message() ];
		}
		return $result;
	}
}
