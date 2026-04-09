<?php

namespace SyncEngine\WordPress\Service;

use SyncEngine\WordPress\Api\Client;

class ClientService extends Singleton
{
	const OPTION_NAME = 'syncengine';

	public function getApiSettings() {
		$settings = get_option( self::OPTION_NAME );
		return (array) ( $settings['api'] ?? [] );
	}

	public function getClient() {
		$api = $this->getApiSettings();

		$host = trim( (string) ( $api['host'] ?? '' ) );
		$token = trim( (string) ( $api['token'] ?? '' ) );

		if ( ! $host || ! $token ) {
			return null;
		}

		return new Client( $host, $token, $api );
	}
}