<?php

namespace SyncEngine\WordPress\Rest;

use SyncEngine\WordPress\Service\RefreshTrustService;
use SyncEngine\WordPress\Service\Singleton;

class RestRoute extends Singleton
{
	const REFRESH_THROTTLE_TRANSIENT = 'syncengine_refresh_throttle';
	const REFRESH_THROTTLE_SECONDS   = 10;
	const REFRESH_TRUSTED_THROTTLE_SECONDS = 1;

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

	public function refreshCallback( $request = null ) {
		$isAuthenticated = is_user_logged_in();
		$trustedRefresh = false;
		$connectionRef = '';

		if ( is_object( $request ) && is_callable( [ $request, 'get_header' ] ) ) {
			$connectionRef = trim( (string) $request->get_header( 'x-syncengine-connection' ) );
			$trustedRefresh = '' !== $connectionRef && RefreshTrustService::get_instance()->isTrustedConnectionRef( $connectionRef );
		}

		$throttleKey = self::REFRESH_THROTTLE_TRANSIENT . ( $trustedRefresh ? '_trusted' : '' );
		$throttleTtl = $trustedRefresh
			? (int) apply_filters( 'syncengine_refresh_trusted_throttle_ttl', self::REFRESH_TRUSTED_THROTTLE_SECONDS )
			: (int) apply_filters( 'syncengine_refresh_throttle_ttl', self::REFRESH_THROTTLE_SECONDS );

		$throttleTtl = max( 0, $throttleTtl );

		if ( ! $isAuthenticated && $throttleTtl > 0 && get_transient( $throttleKey ) ) {
			return [
				'success'   => true,
				'refreshed' => false,
				'reason'    => 'throttled',
				'trusted'   => $trustedRefresh,
			];
		}

		if ( ! $isAuthenticated && $throttleTtl > 0 ) {
			set_transient( $throttleKey, 1, $throttleTtl );
		}

		do_action( 'syncengine_cache_refresh', [
			'source' => 'rest_route',
			'route'  => 'syncengine/v1/refresh',
			'authenticated' => $isAuthenticated,
			'trusted' => $trustedRefresh,
			'connection_ref' => $connectionRef,
		] );

		return [
			'success'   => true,
			'refreshed' => true,
			'trusted'   => $trustedRefresh,
			'timestamp' => time(),
		];
	}
}
