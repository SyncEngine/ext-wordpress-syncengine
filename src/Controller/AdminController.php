<?php

namespace SyncEngine\WordPress\Controller;

class AdminController
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
}
