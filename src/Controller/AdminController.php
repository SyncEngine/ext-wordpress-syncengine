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

		$this->register_section_api();
		//$this->register_section_hooks();
	}

	public function register_section_api() {
		add_settings_section(
			'api',
			__( 'Connect to API' ),
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

		/*
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
		*/
	}

	public function register_section_hooks() {
		add_settings_section(
			'hooks',
			__( 'SyncEngine Hooks' ),
			array( $this, 'settings_api_section' ),
			'syncengine'
		);

		add_settings_field(
			'hooks',
			__( 'Hooks' ),
			array( $this, 'settings_api_field_hooks' ),
			'syncengine',
			'hooks',
		);
	}

	public function page() {
		$settings = get_option( $this->option_name );

		$url = remove_query_arg( 'settings-updated' );

		$api_settings = $settings['api'] ?? [];

		$api = new Client(
			$api_settings['host'] ?? '',
			$api_settings['token'] ?? '',
			$api_settings,
		);

		if ( ! empty( $_GET['refresh'] ) || ! empty( $_GET['settings-updated'] ) ) {
			$api->clearCache();
			$url = remove_query_arg( 'refresh', $url );
		}

		if ( ! empty( $_GET['execute_endpoint'] ) ) {
			$result = $api->executeEndpoint( $_GET['execute_endpoint'] );
			$url = remove_query_arg( 'execute_endpoint', $url );
		}

		$status    = $api->status();
		$endpoints = $api->listEndpoints();

		?>
		<div class="wrap">
			<form action='options.php' method='post'>
				<h1>
					<img src="<?= \SyncEngine::get_url() . 'assets/img/icon.svg' ?>" alt="SyncEngine" style="width: 1.2em;height: 1.2em;display: inline-block;vertical-align: bottom;margin-right: .2em;"/>
					<?= __( 'SyncEngine', 'syncengine' ) ?>
				</h1>
				<?php
				settings_fields( 'syncengine' );
				do_settings_sections( 'syncengine' );
				submit_button();
				?>

				<p>Status: <?= $status ?></p>
	
				<a class="button" href="<?= add_query_arg( 'refresh', true, $url ) ?>">Refresh</a>
				<?php if ( $api->isOnline() && $endpoints ): ?>
				<div>
					<h2><?= __( 'Run automations manually', 'syncengine' ) ?></h2>
					<?php foreach ( $endpoints as $endpoint ): ?>
					<a class="button" href="<?= add_query_arg( 'execute_endpoint', $endpoint['endpoint'], $url ) ?>"><?= $endpoint['name'] ?></a>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
	
				<?php if ( ! empty( $result ) ): ?>
				<div>
					<h2><?= __( 'Execute results', 'syncengine' ) ?></h2>
					<code><?= $result ?></code>
				</div>
				<?php endif; ?>
			
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
