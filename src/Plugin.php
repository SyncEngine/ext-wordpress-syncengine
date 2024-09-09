<?php

namespace SyncEngine\WordPress;

use SyncEngine\WordPress\Rest\RestQuery;
use SyncEngine\WordPress\Rest\RestRoute;

class Plugin
{
	private static $_instance;

	public static function get_instance()
	{
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	protected function __construct() {
		add_action( 'rest_api_init', array( $this, 'action_rest_api_init' ), 100000 );
	}

	public function action_rest_api_init() {
		RestQuery::get_instance()->register();
		RestRoute::get_instance()->register();
	}
}
