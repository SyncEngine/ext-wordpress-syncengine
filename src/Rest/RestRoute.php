<?php

namespace SyncEngine\WordPress\Rest;

use SyncEngine\WordPress\Service\Singleton;

class RestRoute extends Singleton
{
	public function register() {
		register_rest_route( 'syncengine/v1', 'status', array( 'callback' => array( $this, 'statusCallback' ) ) );
	}

	public function statusCallback() {
		return [
			'success' => true,
			'status'  => 'active'
		];
	}
}
