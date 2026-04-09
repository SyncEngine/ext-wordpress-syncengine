<?php

namespace SyncEngine\WordPress\Module\WordPressCore\Controller;

use SyncEngine\WordPress\Module\WordPressCore\Service\PlatformService;
use SyncEngine\WordPress\Service\ErrorNoticeService;
use SyncEngine\WordPress\Service\Singleton;

class AdminController extends Singleton
{
	public function register() {
		add_action( 'syncengine_admin_process_actions', [ $this, 'action_syncengine_admin_process_actions' ] );
		add_action( 'syncengine_admin_render_trigger_debug_sections', [ $this, 'action_syncengine_admin_render_trigger_debug_sections' ] );
	}

	public function action_syncengine_admin_process_actions( $context ) {
		if ( ! is_object( $context ) ) {
			return;
		}

		if ( ! empty( $_GET['refresh_wp_trigger_map'] ) ) {
			PlatformService::get_instance()->getTriggerEndpointMap( true );
			$context->url = remove_query_arg( 'refresh_wp_trigger_map', (string) ( $context->url ?? '' ) );
		}
	}

	public function action_syncengine_admin_render_trigger_debug_sections( $context ) {
		if ( ! is_object( $context ) ) {
			return;
		}

		$wpMap = PlatformService::get_instance()->getTriggerEndpointMap();
		$url = (string) ( $context->url ?? '' );

		if ( is_wp_error( $wpMap ) ) {
			ErrorNoticeService::get_instance()->addError( 'getTriggerEndpointMap', $wpMap );
			$wpMap = [];
		}
		?>
		<div class="code" style="background: #fff; padding: 1em; margin-bottom: 1em;">
			<h3 style="margin-top: 0;"><?= __( 'WordPress Core Trigger Endpoint Map', 'syncengine' ) ?></h3>
			<p>
				<a class="button" href="<?= add_query_arg( 'refresh_wp_trigger_map', true, $url ) ?>"><?= __( 'Refresh WordPress trigger endpoint map', 'syncengine' ) ?></a>
			</p>
			<pre style="margin: 0"><?= json_encode( $wpMap, JSON_PRETTY_PRINT ) ?></pre>
		</div>
		<?php
	}
}
