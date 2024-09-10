<?php

namespace SyncEngine\WordPress\Controller;

use SyncEngine\WordPress\Api\Client;
use SyncEngine\WordPress\Service\Singleton;

class AdminController extends Singleton
{
	const CAPABILITY = 'syncengine';
	protected $option_name = 'syncengine';

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

	public function action_admin_init() {
		register_setting( 'syncengine', 'syncengine' );

		add_settings_section(
			'api',
			__( 'SyncEngine API' ),
			array( $this, 'settings_api_section' ),
			'syncengine'
		);

		add_settings_field(
			'host',
			__( 'Domain/Host' ),
			array( $this, 'settings_api_field_input' ),
			'syncengine',
			'api',
			[
				'name'        => 'host',
				'placeholder' => __( 'https://' ),
				'section'     => 'api',
				'setting'     => $this->option_name,
			]
		);

		add_settings_field(
			'token',
			__( 'Token' ),
			array( $this, 'settings_api_field_input' ),
			'syncengine',
			'api',
			[
				'type'        => is_super_admin() ? 'text' : 'password',
				'name'        => 'token',
				'placeholder' => __( '#' ),
				'section'     => 'api',
				'setting'     => $this->option_name,
			]
		);

		add_settings_field(
			'auth_header',
			__( 'Auth Header' ),
			array( $this, 'settings_api_field_input' ),
			'syncengine',
			'api',
			[
				'name'        => 'auth_header',
				'placeholder' => __( 'Bearer token (default)' ),
				'section'     => 'api',
				'setting'     => $this->option_name,
			]
		);

		add_settings_field(
			'version',
			__( 'Version' ),
			array( $this, 'settings_api_field_input' ),
			'syncengine',
			'api',
			[
				'type'        => 'number',
				'name'        => 'version',
				'placeholder' => __( '1' ),
				'section'     => 'api',
				'setting'     => $this->option_name,
			]
		);
	}

	public function page() {
		$settings = get_option( $this->option_name );

		$api_settings = $settings['api'] ?? [];

		$api = new Client(
			$api_settings['host'] ?? '',
			$api_settings['token'] ?? '',
			$api_settings,
		);

		$status = $api->status();

		?>
		<div class="wrap">
			<form action='options.php' method='post'>
				<h1><?= __( 'SyncEngine', 'syncengine' ) ?></h1>
				<?php
				settings_fields( 'syncengine' );
				do_settings_sections( 'syncengine' );
				submit_button();
				?>

				Status: <?= $status ?>
			</form>
		</div>
		<?php
	}

	public function settings_api_section( $args ) {
		return $args['title'] ?? '';
	}

	public function settings_api_field_input( $args ) {
		$type = $args['type'] ?? 'text';
		$options = get_option( $args['setting'] );
		$id = $args['setting'] . '_' . $args['section'] . '_' . $args['name'];
		$name = $args['setting'] . '[' . $args['section'] . ']' . '[' . $args['name'] . ']';

		$value = $options[ $args['section'] ][ $args['name'] ] ?? '';
		?>
		<input id="<?= $id ?>" type="<?= $type ?>" name="<?= $name; ?>" value="<?= $value; ?>" placeholder="<?= $args['placeholder'] ?? $args['label'] ?? $args['title'] ?>" />
		<?php
	}
}
