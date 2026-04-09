<?php

namespace SyncEngine\WordPress\Module\WordPressCore;

use SyncEngine\WordPress\Module\WordPressCore\Controller\AdminController as WordPressCoreAdminController;
use SyncEngine\WordPress\Module\WordPressCore\Service\PlatformService;
use SyncEngine\WordPress\Module\WordPressCore\Service\WordPressCoreTriggerService;
use SyncEngine\WordPress\Service\Singleton;

class Plugin extends Singleton
{
	private $registered = false;

	public function register() {
		if ( $this->registered ) {
			return;
		}

		WordPressCoreTriggerService::get_instance()->register();
		if ( is_admin() ) {
			WordPressCoreAdminController::get_instance()->register();
		}

		add_action( 'update_option_syncengine', [ $this, 'action_update_option_syncengine' ], 10, 2 );

		$this->registered = true;
	}

	public function action_update_option_syncengine( $old_value, $value ) {
		PlatformService::get_instance()->clearTriggerEndpointMapCache();
	}
}
