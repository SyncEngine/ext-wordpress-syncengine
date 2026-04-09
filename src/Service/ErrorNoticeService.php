<?php

namespace SyncEngine\WordPress\Service;

class ErrorNoticeService extends Singleton
{
	/**
	 * Display an error as an admin notice using WordPress's built-in admin_notices hook.
	 *
	 * @param string $context Where the error occurred (e.g., 'listAutomations', 'listConnections')
	 * @param \WP_Error|string $error The error object or message
	 */
	public function addError( $context, $error ) {
		$message = $error instanceof \WP_Error
			? $error->get_error_message()
			: (string) $error;

		$context = esc_html( (string) $context );
		$message = esc_html( $message );

		add_action( 'admin_notices', function () use ( $context, $message ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<strong>SyncEngine API Error</strong> (<?= $context ?>):<br/>
					<?= $message ?>
				</p>
			</div>
			<?php
		} );
	}
}
