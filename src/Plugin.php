<?php

namespace SyncEngine\WordPress;

use SyncEngine\WordPress\Controller\AdminController;
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
		add_action( 'plugins_loaded', array( $this, 'action_plugins_loaded' ), 20 );

		if ( is_admin() ) {
			AdminController::get_instance()->register();
		}
	}

	public function action_plugins_loaded() {
		\SyncEngine\WordPress\Module\WordPressCore\Plugin::get_instance()->register();
		\SyncEngine\WordPress\Module\WooCommerce\Plugin::get_instance()->register();
	}

	public function action_rest_api_init() {
		RestQuery::get_instance()->register();
		RestRoute::get_instance()->register();
	}
}
