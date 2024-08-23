<?php

namespace SyncEngine\ExtWordpress;

use SyncEngine\ExtWordpress\Rest\RestQuery;

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
		add_action( 'rest_api_init', array( RestQuery::get_instance(), 'register' ), 100000 );
	}
}
