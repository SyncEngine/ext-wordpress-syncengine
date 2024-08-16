<?php
/**
 * Plugin Name: SyncEngine
 * Description: Integration Manager
 * Version: 1.0
 * Author: Jory Hogeveen
 * Author URI: http://www.syncengine.io/
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

SyncEngine::get_instance();

class SyncEngine
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
