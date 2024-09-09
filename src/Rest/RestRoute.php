<?php

namespace SyncEngine\WordPress\Rest;

class RestRoute
{
	private static $_instance;

	public static function get_instance()
	{
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	protected function __construct() {}

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
