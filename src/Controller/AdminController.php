<?php

namespace SyncEngine\WordPress\Controller;

use SyncEngine\WordPress\Api\Client;
use SyncEngine\WordPress\Service\ClientService;
use SyncEngine\WordPress\Service\DispatchLogService;
use SyncEngine\WordPress\Service\ErrorNoticeService;
use SyncEngine\WordPress\Service\Singleton;

class AdminController extends Singleton
{
	const CAPABILITY = 'syncengine';

	protected $option_name = 'syncengine';

	public function register() {
		add_action( 'admin_menu', [ $this, 'action_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'action_admin_init' ] );
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
			[ $this, 'page' ],
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
			__( 'Connect to API', 'syncengine' ),
			[ $this, 'settings_api_section' ],
			'syncengine'
		);

		add_settings_field(
			'host',
			__( 'Domain/Host', 'syncengine' ),
			[ $this, 'settings_api_field_input' ],
			'syncengine',
			'api',
			[
				'name'        => 'host',
				'placeholder' => __( 'https://', 'syncengine' ),
				'section'     => 'api',
				'setting'     => $this->option_name,
			]
		);

		add_settings_field(
			'token',
			__( 'Token', 'syncengine' ),
			[ $this, 'settings_api_field_input' ],
			'syncengine',
			'api',
			[
				'type'        => is_super_admin() ? 'text' : 'password',
				'name'        => 'token',
				'placeholder' => __( '#', 'syncengine' ),
				'section'     => 'api',
				'setting'     => $this->option_name,
			]
		);

		add_settings_field(
			'auth_header',
			__( 'Auth Header', 'syncengine' ),
			[ $this, 'settings_api_field_input' ],
			'syncengine',
			'api',
			[
				'name'        => 'auth_header',
				'placeholder' => __( 'Bearer token (default)', 'syncengine' ),
				'section'     => 'api',
				'setting'     => $this->option_name,
			]
		);
	}

	public function register_section_hooks() {
		add_settings_section(
			'hooks',
			__( 'SyncEngine Hooks', 'syncengine' ),
			[ $this, 'settings_api_section' ],
			'syncengine'
		);

		add_settings_field(
			'hooks',
			__( 'Hooks', 'syncengine' ),
			[ $this, 'settings_api_field_hooks' ],
			'syncengine',
			'hooks',
		);
	}

	public function page() {
		$settings = get_option( $this->option_name );
		$result = null;
		$url = remove_query_arg( 'settings-updated' );
		$api = ClientService::get_instance()->getClient() ?? new Client( '', '', [] );

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

		$status = $api->status();
		$endpoints = $api->listEndpoints();
		$dispatchLog = DispatchLogService::get_instance()->getLatest( 25 );

		if ( is_wp_error( $endpoints ) ) {
			ErrorNoticeService::get_instance()->addError( 'listEndpoints', $endpoints );
			$endpoints = [];
		}

		$nonce = wp_create_nonce( 'syncengine_get_endpoint_status' );

		$context->status = $status;
		$context->endpoints = $endpoints;
		$context->url = $url;
		$context->result = $result;
		?>
		<div class="wrap">
			<h1>
				<img src="<?= \SyncEngine::get_url() . 'assets/img/icon.svg' ?>" alt="SyncEngine" style="width: 1.2em; height: 1.2em; display: inline-block; vertical-align: bottom; margin-right: .2em;"/>
				<?= __( 'SyncEngine', 'syncengine' ) ?>
			</h1>

			<h2 class="nav-tab-wrapper" style="margin-bottom: 1em;">
				<a href="#syncengine-tab-connection" class="nav-tab nav-tab-active" data-syncengine-tab="connection"><?= esc_html__( 'Connector', 'syncengine' ) ?></a>
				<a href="#syncengine-tab-debug" class="nav-tab" data-syncengine-tab="debug"><?= esc_html__( 'Trigger Maps & Debug', 'syncengine' ) ?></a>
				<a href="#syncengine-tab-hooks" class="nav-tab" data-syncengine-tab="hooks"><?= esc_html__( 'Custom Hooks Config', 'syncengine' ) ?></a>
			</h2>

			<div id="syncengine-tab-connection" class="syncengine-tab-panel" data-syncengine-tab-panel="connection">
				<form action="options.php" method="post" style="margin-bottom: 1.5em;">
					<?php settings_fields( 'syncengine' ); ?>
					<h2><?= esc_html__( 'Connection Config', 'syncengine' ) ?></h2>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'syncengine', 'api' ); ?>
					</table>
					<?php submit_button( __( 'Save Connection Settings', 'syncengine' ) ); ?>
				</form>

				<p><strong><?= esc_html__( 'Status:', 'syncengine' ) ?></strong> <?= esc_html( (string) $status ) ?></p>
				<p><a class="button" href="<?= add_query_arg( 'refresh', true, $url ) ?>"><?= esc_html__( 'Refresh', 'syncengine' ) ?></a></p>

				<?php if ( $api->isOnline() && $endpoints ): ?>
					<div>
						<h2><?= __( 'Endpoints', 'syncengine' ) ?></h2>
						<table class="widefat striped" style="margin-top: .5em; max-width: 1100px;">
							<thead>
								<tr>
									<th><?= esc_html__( 'Name', 'syncengine' ) ?></th>
									<th><?= esc_html__( 'Endpoint', 'syncengine' ) ?></th>
									<th><?= esc_html__( 'Status', 'syncengine' ) ?></th>
									<th><?= esc_html__( 'Trace', 'syncengine' ) ?></th>
									<th><?= esc_html__( 'Actions', 'syncengine' ) ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $endpoints as $endpoint ): ?>
									<?php $endpointSlug = trim( (string) ( $endpoint['endpoint'] ?? '' ) ); ?>
									<tr class="syncengine-endpoint-row" data-endpoint="<?= esc_attr( $endpointSlug ) ?>">
										<td><?= esc_html( (string) ( $endpoint['name'] ?? $endpointSlug ) ) ?></td>
										<td><code><?= esc_html( $endpointSlug ) ?></code></td>
										<td class="syncengine-status-cell"><em><?= esc_html__( 'not loaded', 'syncengine' ) ?></em></td>
										<td class="syncengine-trace-cell"></td>
										<td>
											<button class="button syncengine-load-status-btn" type="button" data-endpoint="<?= esc_attr( $endpointSlug ) ?>" data-nonce="<?= esc_attr( $nonce ) ?>"><?= esc_html__( 'Load status', 'syncengine' ) ?></button>
											<a class="button" href="<?= add_query_arg( 'execute_endpoint', $endpointSlug, $url ) ?>"><?= esc_html__( 'Execute', 'syncengine' ) ?></a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $result ) ): ?>
					<div style="margin-top: 1em;">
						<h2><?= __( 'Execute Results', 'syncengine' ) ?></h2>
						<div class="code" style="background: #fff; padding: 1em;">
							<pre style="margin: 0"><?= json_encode( $result, JSON_PRETTY_PRINT ) ?></pre>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<div id="syncengine-tab-debug" class="syncengine-tab-panel" data-syncengine-tab-panel="debug" style="display:none;">
				<div style="margin-top: 1em;">
					<h2><?= __( 'Trigger Debug', 'syncengine' ) ?></h2>
					<p><a class="button" href="<?= add_query_arg( 'clear_dispatch_log', true, $url ) ?>"><?= __( 'Clear Dispatch Log', 'syncengine' ) ?></a></p>

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
					<?php do_action( 'syncengine_admin_render_sections', $context ); ?>
				</div>
			</div>

			<div id="syncengine-tab-hooks" class="syncengine-tab-panel" data-syncengine-tab-panel="hooks" style="display:none;">
				<form action="options.php" method="post" style="margin-top: 1em;">
					<?php settings_fields( 'syncengine' ); ?>
					<h2><?= esc_html__( 'Custom Hooks Config', 'syncengine' ) ?></h2>
					<table class="form-table" role="presentation">
						<?php do_settings_fields( 'syncengine', 'hooks' ); ?>
					</table>
					<?php submit_button( __( 'Save Custom Hooks', 'syncengine' ) ); ?>
				</form>
			</div>

			<script type="text/javascript">
			(function() {
				const tabLinks = document.querySelectorAll('[data-syncengine-tab]');
				const tabPanels = document.querySelectorAll('[data-syncengine-tab-panel]');
				const currentHash = window.location.hash ? window.location.hash.substring(1) : '';

				function showTab(tabName) {
					tabLinks.forEach(function(link) {
						link.classList.toggle('nav-tab-active', link.getAttribute('data-syncengine-tab') === tabName);
					});

					tabPanels.forEach(function(panel) {
						panel.style.display = panel.getAttribute('data-syncengine-tab-panel') === tabName ? '' : 'none';
					});
				}

				if (currentHash === 'syncengine-tab-debug') {
					showTab('debug');
				} else if (currentHash === 'syncengine-tab-hooks') {
					showTab('hooks');
				}

				tabLinks.forEach(function(link) {
					link.addEventListener('click', function(e) {
						e.preventDefault();
						const tab = link.getAttribute('data-syncengine-tab');
						if (!tab) {
							return;
						}
						showTab(tab);
						window.location.hash = 'syncengine-tab-' + tab;
					});
				});

				const ajaxUrl = <?= wp_json_encode( admin_url( 'admin-ajax.php' ) ) ?>;
				const buttons = document.querySelectorAll('.syncengine-load-status-btn');

				buttons.forEach(function(btn) {
					btn.addEventListener('click', function(e) {
						e.preventDefault();
						const endpoint = btn.dataset.endpoint;
						const nonce = btn.dataset.nonce;
						const row = btn.closest('tr');
						const originalText = btn.textContent;

						btn.disabled = true;
						btn.textContent = '<?= esc_js( __( 'Loading...', 'syncengine' ) ) ?>';

						fetch(ajaxUrl, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
							},
							body: new URLSearchParams({
								action: 'syncengine_get_endpoint_status',
								nonce: nonce,
								endpoint: endpoint
							})
						})
						.then(function(response) { return response.json(); })
						.then(function(data) {
							if (data.success) {
								const payload = data.data || {};
								let statusLabel = payload.status || 'unknown';
								if (payload.message) {
									statusLabel += ' - ' + payload.message;
								}

								const runningCount = Array.isArray(payload.running) ? payload.running.length : 0;
								const scheduledCount = Array.isArray(payload.scheduled) ? payload.scheduled.length : 0;
								const queuedCount = Array.isArray(payload.queued) ? payload.queued.length : 0;
								const traceLabel = 'running: ' + runningCount + ' | scheduled: ' + scheduledCount + ' | queued: ' + queuedCount;

								row.querySelector('.syncengine-status-cell').textContent = statusLabel;
								row.querySelector('.syncengine-trace-cell').textContent = traceLabel;
								btn.textContent = '<?= esc_js( __( 'Refresh status', 'syncengine' ) ) ?>';
							} else {
								const message = data && data.data && data.data.message
									? data.data.message
									: '<?= esc_js( __( 'Failed to load status', 'syncengine' ) ) ?>';
								row.querySelector('.syncengine-status-cell').textContent = 'Error: ' + message;
							}

							btn.disabled = false;
						})
						.catch(function(error) {
							row.querySelector('.syncengine-status-cell').textContent = 'Error: ' + error.message;
							btn.disabled = false;
							btn.textContent = originalText;
						});
					});
				});
			})();
			</script>
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
		$name = $args['setting'] . '[' . $args['section'] . '][' . $args['name'] . ']';
		$value = $options[ $args['section'] ][ $args['name'] ] ?? '';
		?>
		<input id="<?= esc_attr( $id ) ?>" type="<?= esc_attr( $type ) ?>" name="<?= esc_attr( $name ) ?>" value="<?= esc_attr( (string) $value ) ?>" placeholder="<?= esc_attr( (string) ( $args['placeholder'] ?? $args['label'] ?? $args['title'] ?? '' ) ) ?>" />
		<?php
	}

	public function settings_api_field_hooks() {
		$options = (array) get_option( $this->option_name );
		$hooks = array_values( array_filter( (array) ( $options['hooks']['custom'] ?? [] ), 'is_array' ) );
		$hooks = ! empty( $hooks ) ? $hooks : [ [] ];

		$api = ClientService::get_instance()->getClient() ?? new Client( '', '', [] );
		$endpointsResponse = $api->listEndpoints();
		$availableEndpoints = [];
		if ( is_array( $endpointsResponse ) ) {
			foreach ( $endpointsResponse as $endpoint ) {
				if ( ! is_array( $endpoint ) || empty( $endpoint['endpoint'] ) ) {
					continue;
				}

				$slug = trim( (string) $endpoint['endpoint'] );
				if ( '' !== $slug ) {
					$availableEndpoints[] = $slug;
				}
			}
		}

		$availableEndpoints = array_values( array_unique( $availableEndpoints ) );
		?>
		<p><?= esc_html__( 'Define extra WordPress action hooks that should directly trigger one or more SyncEngine endpoints.', 'syncengine' ) ?></p>
		<table class="widefat striped" style="max-width: 1100px;">
			<thead>
				<tr>
					<th><?= esc_html__( 'WordPress Hook', 'syncengine' ) ?></th>
					<th><?= esc_html__( 'Endpoints', 'syncengine' ) ?></th>
					<th style="width: 110px;"><?= esc_html__( 'Priority', 'syncengine' ) ?></th>
					<th style="width: 140px;"><?= esc_html__( 'Accepted Args', 'syncengine' ) ?></th>
				</tr>
			</thead>
			<tbody id="syncengine-hooks-custom-rows">
				<?php foreach ( $hooks as $index => $row ): ?>
					<?php $this->render_hooks_row( (string) $index, (array) $row, $availableEndpoints ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p style="margin-top: .7em;">
			<button type="button" class="button" id="syncengine-hooks-add-row"><?= esc_html__( 'Add hook mapping', 'syncengine' ) ?></button>
		</p>
		<p class="description">
			<?= esc_html__( 'Use WordPress action hook names only. Each configured hook dispatches a payload containing hook metadata and normalized hook arguments.', 'syncengine' ) ?>
		</p>
		<script type="text/template" id="syncengine-hooks-row-template">
			<?php $this->render_hooks_row( '__INDEX__', [], $availableEndpoints ); ?>
		</script>
		<script>
			(function () {
				const addButton = document.getElementById('syncengine-hooks-add-row');
				const rowsContainer = document.getElementById('syncengine-hooks-custom-rows');
				const template = document.getElementById('syncengine-hooks-row-template');
				if (!addButton || !rowsContainer || !template) {
					return;
				}

				addButton.addEventListener('click', function () {
					const index = rowsContainer.children.length;
					const html = template.innerHTML.replaceAll('__INDEX__', String(index));
					rowsContainer.insertAdjacentHTML('beforeend', html);
				});
			})();
		</script>
		<?php
	}

	private function render_hooks_row( $index, $row, $availableEndpoints ) {
		$row = (array) $row;
		$selectedEndpoints = array_values( (array) ( $row['endpoints'] ?? [] ) );
		$availableEndpoints = (array) $availableEndpoints;
		?>
		<tr>
			<td>
				<input
					type="text"
					name="<?= esc_attr( $this->option_name ) ?>[hooks][custom][<?= esc_attr( (string) $index ) ?>][hook]"
					value="<?= esc_attr( (string) ( $row['hook'] ?? '' ) ) ?>"
					placeholder="save_post"
					style="width: 100%;"
				/>
			</td>
			<td>
				<?php if ( empty( $availableEndpoints ) ): ?>
					<span class="description"><?= esc_html__( 'No endpoints available. Check API connection and refresh.', 'syncengine' ) ?></span>
				<?php else: ?>
					<div style="max-height: 120px; overflow: auto; border: 1px solid #ddd; padding: .5em; background: #fff;">
						<?php foreach ( $availableEndpoints as $endpoint ): ?>
							<label style="display: block; margin: 0 0 .25em 0;">
								<input
									type="checkbox"
									name="<?= esc_attr( $this->option_name ) ?>[hooks][custom][<?= esc_attr( (string) $index ) ?>][endpoints][]"
									value="<?= esc_attr( (string) $endpoint ) ?>"
									<?= checked( in_array( (string) $endpoint, $selectedEndpoints, true ), true, false ) ?>
								/>
								<?= esc_html( (string) $endpoint ) ?>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</td>
			<td>
				<input
					type="number"
					min="1"
					step="1"
					name="<?= esc_attr( $this->option_name ) ?>[hooks][custom][<?= esc_attr( (string) $index ) ?>][priority]"
					value="<?= esc_attr( (string) ( $row['priority'] ?? 10 ) ) ?>"
					style="width: 100%;"
				/>
			</td>
			<td>
				<input
					type="number"
					min="0"
					step="1"
					name="<?= esc_attr( $this->option_name ) ?>[hooks][custom][<?= esc_attr( (string) $index ) ?>][accepted_args]"
					value="<?= esc_attr( (string) ( $row['accepted_args'] ?? 99 ) ) ?>"
					style="width: 100%;"
				/>
			</td>
		</tr>
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
			$endpointValues = $row['endpoints'] ?? [];
			if ( ! is_array( $endpointValues ) ) {
				$endpointValues = array_filter( array_map( 'trim', explode( ',', (string) $endpointValues ) ) );
			}

			$endpoints = array_values( array_unique( array_map( function ( $endpoint ) {
				return trim( (string) $endpoint, " \t\n\r\0\x0B/" );
			}, array_values( (array) $endpointValues ) ) ) );

			$endpoints = array_values( array_filter( $endpoints, function ( $endpoint ) {
				return '' !== (string) $endpoint;
			} ) );

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

	public function ajax_get_endpoint_status() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'syncengine_get_endpoint_status' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'syncengine' ) ], 403 );
		}

		if ( ! isset( $_POST['endpoint'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Endpoint not specified.', 'syncengine' ) ] );
		}

		$endpoint = trim( (string) $_POST['endpoint'], '/' );
		if ( '' === $endpoint ) {
			wp_send_json_error( [ 'message' => __( 'Invalid endpoint.', 'syncengine' ) ] );
		}

		$api = ClientService::get_instance()->getClient();
		if ( ! $api ) {
			wp_send_json_error( [ 'message' => __( 'API settings not configured.', 'syncengine' ) ] );
		}

		$status = $api->getEndpointStatus( $endpoint, true );

		if ( ! empty( $status['success'] ) ) {
			wp_send_json_success( [
				'status'    => $status['status'] ?? 'unknown',
				'message'   => $status['message'] ?? '',
				'running'   => $status['running'] ?? [],
				'scheduled' => $status['scheduled'] ?? [],
				'queued'    => $status['queued'] ?? [],
			] );
		} else {
			wp_send_json_error( [ 'message' => $status['error'] ?? __( 'Failed to load status.', 'syncengine' ) ] );
		}
	}
}
