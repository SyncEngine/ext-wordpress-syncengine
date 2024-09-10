<?php

namespace SyncEngine\WordPress\Api;

class Client
{
	private $token;
	private $host;
	private $auth_header;
	private $version;
	private $root;

	public function __construct( $host, $token, $auth_header, $version ) {
		$this->host        = $host;
		$this->token       = $token;
		$this->auth_header = $auth_header;
		$this->version     = $version;

		$this->root = trailingslashit( $this->host ) . 'api/v' . $this->version . '/';
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

		$url = $this->root . trailingslashit( ltrim( $endpoint, '/' ) );

		$response = wp_remote_request( $url, $options );

		if ( is_wp_error( $response ) ) {
			wp_die( $response );
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	public function status() {
		return $this->request( 'status' );
	}

	public function listEndpoints() {
		return $this->request( 'endpoints' );
	}

	public function executeEndpoint( $endpoint ) {
		return $this->request( $endpoint );
	}
}
