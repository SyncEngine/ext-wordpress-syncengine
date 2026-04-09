<?php

namespace SyncEngine\WordPress\Controller;

use SyncEngine\WordPress\Api\Client;
use SyncEngine\WordPress\Service\ErrorNoticeService;
use SyncEngine\WordPress\Service\DispatchLogService;
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
		register_setting(
			'syncengine',
			'syncengine',
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
			]
		);

		$this->register_section_api();
		$this->register_section_hooks();
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
		$result = null;

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

		if ( ! empty( $_GET['clear_dispatch_log'] ) ) {
			DispatchLogService::get_instance()->clearLog();
			$url = remove_query_arg( 'clear_dispatch_log', $url );
		}

		$context = (object) [
			'api'      => $api,
			'url'      => $url,
			'settings' => (array) $settings,
			'result'   => $result,
		];

		do_action( 'syncengine_admin_process_actions', $context );

		$url = (string) ( $context->url ?? $url );
		$result = $context->result ?? $result;

		$status    = $api->status();
		$endpoints = $api->listEndpoints();
		$dispatchLog = DispatchLogService::get_instance()->getLatest( 25 );

		if ( is_wp_error( $endpoints ) ) {
			ErrorNoticeService::get_instance()->addError( 'listEndpoints', $endpoints );
			$endpoints = [];
		}

		$context->status = $status;
		$context->endpoints = $endpoints;
		$context->url = $url;
		$context->result = $result;

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
			</form>

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
				<div class="code" style="background: #fff; padding: 1em;">
					<pre style="margin: 0"><?= json_encode( $result, JSON_PRETTY_PRINT ) ?></pre>
				</div>
			</div>
			<?php endif; ?>

			<div style="margin-top: 2em;">
				<h2><?= __( 'Trigger Debug', 'syncengine' ) ?></h2>
				<p>
					<a class="button" href="<?= add_query_arg( 'clear_dispatch_log', true, $url ) ?>"><?= __( 'Clear dispatch log', 'syncengine' ) ?></a>
				</p>

				<div class="code" style="background: #fff; padding: 1em; margin-bottom: 1em;">
					<h3 style="margin-top: 0;"><?= __( 'Recent Dispatches (latest 25)', 'syncengine' ) ?></h3>
					<?php if ( ! empty( $dispatchLog ) ): ?>
					<table class="widefat striped" style="margin-top: .5em;">
						<thead>
							<tr>
								<th><?= __( 'Time', 'syncengine' ) ?></th>
								<th><?= __( 'Source', 'syncengine' ) ?></th>
								<th><?= __( 'Trigger', 'syncengine' ) ?></th>
								<th><?= __( 'Endpoint', 'syncengine' ) ?></th>
								<th><?= __( 'Success', 'syncengine' ) ?></th>
								<th><?= __( 'Payload Size', 'syncengine' ) ?></th>
								<th><?= __( 'Error', 'syncengine' ) ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $dispatchLog as $entry ): ?>
							<tr>
								<td><?= date_i18n( 'Y-m-d H:i:s', (int) ( $entry['timestamp'] ?? 0 ) ) ?></td>
								<td><?= esc_html( (string) ( $entry['source'] ?? '' ) ) ?></td>
								<td><?= esc_html( (string) ( $entry['trigger'] ?? '' ) ) ?></td>
								<td><?= esc_html( (string) ( $entry['endpoint'] ?? '' ) ) ?></td>
								<td><?= ! empty( $entry['success'] ) ? 'yes' : 'no' ?></td>
								<td><?= (int) ( $entry['payload_size'] ?? 0 ) ?></td>
								<td><?= esc_html( (string) ( $entry['error'] ?? '' ) ) ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php else: ?>
					<p><?= __( 'No dispatch log entries yet.', 'syncengine' ) ?></p>
					<?php endif; ?>
				</div>

				<?php do_action( 'syncengine_admin_render_trigger_debug_sections', $context ); ?>
			</div>

			<?php do_action( 'syncengine_admin_render_sections', $context ); ?>
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

	public function settings_api_field_hooks() {
		$options = (array) get_option( $this->option_name );
		$hooks = array_values( array_filter( (array) ( $options['hooks']['custom'] ?? [] ), 'is_array' ) );
		$rowCount = max( count( $hooks ) + 3, 5 );

		$api_settings = (array) ( $options['api'] ?? [] );
		$api = new Client(
			$api_settings['host'] ?? '',
			$api_settings['token'] ?? '',
			$api_settings,
		);
		$availableEndpoints = $api->listEndpoints();
		if ( is_wp_error( $availableEndpoints ) || ! is_array( $availableEndpoints ) ) {
			$availableEndpoints = [];
		}
		?>
		<p><?= esc_html__( 'Define extra WordPress action hooks that should directly trigger one or more SyncEngine endpoints. Endpoints must be entered as endpoint slugs, separated by commas.', 'syncengine' ) ?></p>
		<table class="widefat striped" style="max-width: 1100px;">
			<thead>
				<tr>
					<th><?= esc_html__( 'WordPress Hook', 'syncengine' ) ?></th>
					<th><?= esc_html__( 'Endpoint Slugs', 'syncengine' ) ?></th>
					<th style="width: 110px;"><?= esc_html__( 'Priority', 'syncengine' ) ?></th>
					<th style="width: 140px;"><?= esc_html__( 'Accepted Args', 'syncengine' ) ?></th>
				</tr>
			</thead>
			<tbody>
				<?php for ( $index = 0; $index < $rowCount; $index++ ): ?>
				<?php $row = (array) ( $hooks[ $index ] ?? [] ); ?>
				<tr>
					<td>
						<input
							type="text"
							name="<?= esc_attr( $this->option_name ) ?>[hooks][custom][<?= (int) $index ?>][hook]"
							value="<?= esc_attr( (string) ( $row['hook'] ?? '' ) ) ?>"
							placeholder="save_post"
							style="width: 100%;"
						/>
					</td>
					<td>
						<input
							type="text"
							name="<?= esc_attr( $this->option_name ) ?>[hooks][custom][<?= (int) $index ?>][endpoints]"
							value="<?= esc_attr( implode( ', ', array_values( (array) ( $row['endpoints'] ?? [] ) ) ) ) ?>"
							placeholder="automation/my-endpoint, automation/my-other-endpoint"
							style="width: 100%;"
						/>
					</td>
					<td>
						<input
							type="number"
							min="1"
							step="1"
							name="<?= esc_attr( $this->option_name ) ?>[hooks][custom][<?= (int) $index ?>][priority]"
							value="<?= esc_attr( (string) ( $row['priority'] ?? 10 ) ) ?>"
							style="width: 100%;"
						/>
					</td>
					<td>
						<input
							type="number"
							min="0"
							step="1"
							name="<?= esc_attr( $this->option_name ) ?>[hooks][custom][<?= (int) $index ?>][accepted_args]"
							value="<?= esc_attr( (string) ( $row['accepted_args'] ?? 99 ) ) ?>"
							style="width: 100%;"
						/>
					</td>
				</tr>
				<?php endfor; ?>
			</tbody>
		</table>
		<p class="description">
			<?= esc_html__( 'Use WordPress action hook names only. Each configured hook dispatches a payload containing hook metadata and normalized hook arguments.', 'syncengine' ) ?>
		</p>
		<?php if ( ! empty( $availableEndpoints ) ): ?>
		<p class="description"><strong><?= esc_html__( 'Available endpoint slugs:', 'syncengine' ) ?></strong></p>
		<div class="code" style="background: #fff; padding: 1em; max-width: 1100px; max-height: 220px; overflow: auto;">
			<pre style="margin: 0"><?php
				$endpointNames = [];
				foreach ( $availableEndpoints as $endpoint ) {
					if ( empty( $endpoint['endpoint'] ) ) {
						continue;
					}
					$endpointNames[] = (string) $endpoint['endpoint'];
				}
				echo esc_html( implode( PHP_EOL, $endpointNames ) );
			?></pre>
		</div>
		<?php endif; ?>
		<?php
	}

	public function sanitize_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : [];
		$existing = (array) get_option( $this->option_name, [] );

		$existing['api'] = $this->sanitize_api_settings( (array) ( $settings['api'] ?? [] ), (array) ( $existing['api'] ?? [] ) );
		$existing['hooks'] = $this->sanitize_hooks_settings( (array) ( $settings['hooks'] ?? [] ) );

		return $existing;
	}

	private function sanitize_api_settings( $settings, $existing = [] ) {
		$existing['host'] = trim( (string) ( $settings['host'] ?? $existing['host'] ?? '' ) );
		$existing['token'] = trim( (string) ( $settings['token'] ?? $existing['token'] ?? '' ) );
		$existing['auth_header'] = trim( (string) ( $settings['auth_header'] ?? $existing['auth_header'] ?? '' ) );

		return $existing;
	}

	private function sanitize_hooks_settings( $settings ) {
		$customHooks = [];

		foreach ( (array) ( $settings['custom'] ?? [] ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$hook = trim( preg_replace( '/[^A-Za-z0-9_.:-]/', '', (string) ( $row['hook'] ?? '' ) ) );
			$endpoints = array_filter( array_map( 'trim', explode( ',', (string) ( $row['endpoints'] ?? '' ) ) ) );
			$endpoints = array_values( array_unique( array_map( function ( $endpoint ) {
				return trim( (string) $endpoint, " \t\n\r\0\x0B/" );
			}, $endpoints ) ) );

			if ( '' === $hook || empty( $endpoints ) ) {
				continue;
			}

			$customHooks[] = [
				'hook'          => $hook,
				'endpoints'     => $endpoints,
				'priority'      => max( 1, (int) ( $row['priority'] ?? 10 ) ),
				'accepted_args' => max( 0, (int) ( $row['accepted_args'] ?? 99 ) ),
			];
		}

		return [
			'custom' => $customHooks,
		];
	}
}
