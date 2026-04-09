<?php

namespace SyncEngine\WordPress\Rest;

use SyncEngine\WordPress\Service\Singleton;

class RestRoute extends Singleton
{
	const REFRESH_THROTTLE_TRANSIENT = 'syncengine_refresh_throttle';
	const REFRESH_THROTTLE_SECONDS   = 30;

	public function register() {
		register_rest_route(
			'syncengine/v1',
			'status',
			[
				'callback'            => [ $this, 'statusCallback' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'syncengine/v1',
			'refresh',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'refreshCallback' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function statusCallback() {
		return [
			'success' => true,
			'status'  => 'active',
		];
	}

	public function refreshCallback() {
		if ( get_transient( self::REFRESH_THROTTLE_TRANSIENT ) ) {
			return [
				'success'   => true,
				'refreshed' => false,
				'reason'    => 'throttled',
			];
		}

		set_transient( self::REFRESH_THROTTLE_TRANSIENT, 1, self::REFRESH_THROTTLE_SECONDS );

		do_action( 'syncengine_cache_refresh', [
			'source' => 'rest_route',
			'route'  => 'syncengine/v1/refresh',
		] );

		return [
			'success'   => true,
			'refreshed' => true,
			'timestamp' => time(),
		];
	}
}
