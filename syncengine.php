<?php
/**
 * @author Jory Hogeveen
 *
 * Plugin Name: SyncEngine
 * Description: Integration Manager
 * Version: 1.0
 * Author: SyncEngine
 * Author URI: http://www.syncengine.io/
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

SyncEngine::get_instance();

class SyncEngine
{
	const DIR = __DIR__;

	private static $_instance;

	public static function get_instance()
	{
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	protected function __construct() {
		include "vendor/autoload.php";

		if ( class_exists( 'SyncEngine\WordPress\Plugin' ) ) {
			\SyncEngine\WordPress\Plugin::get_instance();
		}
	}
}
