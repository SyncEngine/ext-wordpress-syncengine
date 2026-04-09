<?php

namespace SyncEngine\WordPress\Rest;

use SyncEngine\WordPress\Service\Singleton;

class RestRoute extends Singleton
{
	public function register() {
		register_rest_route(
			'syncengine/v1',
			'status',
			[
				'callback' => [ $this, 'statusCallback' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function statusCallback() {
		return [
			'success' => true,
			'status'  => 'active'
		];
	}
}
