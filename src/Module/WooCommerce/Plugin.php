<?php

namespace SyncEngine\WordPress\Module\WooCommerce;

use SyncEngine\WordPress\Module\WooCommerce\Controller\AdminController as WooCommerceAdminController;
use SyncEngine\WordPress\Module\WooCommerce\Service\PlatformService;
use SyncEngine\WordPress\Module\WooCommerce\Service\WooCommerceTriggerService;
use SyncEngine\WordPress\Service\Singleton;

class Plugin extends Singleton
{
	private $registered = false;

	public function register() {
		if ( $this->registered || ! $this->isWooCommerceActive() ) {
			return;
		}

		WooCommerceTriggerService::get_instance()->register();
		if ( is_admin() ) {
			WooCommerceAdminController::get_instance()->register();
		}

		add_action( 'update_option_syncengine', [ $this, 'action_update_option_syncengine' ], 10, 2 );
		add_action( 'syncengine_cache_refresh', [ $this, 'action_syncengine_cache_refresh' ], 10, 1 );

		$this->registered = true;
	}

	public function action_update_option_syncengine( $old_value, $value ) {
		PlatformService::get_instance()->clearTriggerEndpointMapCache();
	}

	public function action_syncengine_cache_refresh( $context = [] ) {
		PlatformService::get_instance()->clearTriggerEndpointMapCache();
	}

	private function isWooCommerceActive() {
		return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
	}
}
