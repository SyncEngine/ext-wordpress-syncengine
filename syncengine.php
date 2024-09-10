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
	private static $dir = '';
	private static $url = '';

	private static $_instance;

	public static function get_instance()
	{
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public static function get_dir()
	{
		return self::$dir;
	}

	public static function get_url()
	{
		return self::$url;
	}

	protected function __construct() {
		include "vendor/autoload.php";

		self::$dir = plugin_dir_path( __FILE__ );
		self::$url = plugin_dir_url( __FILE__ );

		if ( class_exists( 'SyncEngine\WordPress\Plugin' ) ) {
			\SyncEngine\WordPress\Plugin::get_instance();
		}
	}
}
