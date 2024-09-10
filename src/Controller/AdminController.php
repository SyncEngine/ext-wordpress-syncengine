<?php

namespace SyncEngine\WordPress\Controller;

use SyncEngine\WordPress\Service\Singleton;

class AdminController extends Singleton
{
	const CAPABILITY = 'syncengine';

	public function register() {
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
	}

	public function action_admin_menu() {
		$cap = self::CAPABILITY;
		if ( ! is_multisite() && ! current_user_can( $cap ) ) {
			$cap = 'manage_options';
		}

		add_submenu_page(
			'tools.php',
			__( 'SyncEngine', 'syncengine' ),
			__( 'SyncEngine', 'syncengine' ),
			$cap,
			'syncengine',
			array( $this, 'page' ),
		);
	}

	public function page() {
		?>
		<div class="wrap">
			<form action='options.php' method='post'>
				<h1><?= __( 'SyncEngine', 'syncengine' ) ?></h1>
			</form>
		</div>
		<?php
	}

}
