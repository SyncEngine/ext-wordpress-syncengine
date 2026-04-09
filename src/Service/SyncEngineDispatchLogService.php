<?php

namespace SyncEngine\WordPress\Service;

class SyncEngineDispatchLogService extends Singleton
{
	const OPTION_LOG = 'syncengine_dispatch_log';
	const MAX_LOG_ITEMS = 200;

	public function clearLog() {
		delete_option( self::OPTION_LOG );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getLog() {
		$log = get_option( self::OPTION_LOG, [] );
		return is_array( $log ) ? $log : [];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function getLatest( $limit = 25 ) {
		$log = $this->getLog();
		$limit = max( 1, (int) $limit );
		return array_slice( $log, 0, $limit );
	}

	public function add( $source, $trigger, $endpoint, $payload, $result = [] ) {
		$entry = [
			'timestamp'    => time(),
			'source'       => (string) $source,
			'trigger'      => (string) $trigger,
			'endpoint'     => (string) $endpoint,
			'payload_size' => strlen( wp_json_encode( $payload ) ?: '' ),
			'success'      => (bool) ( $result['success'] ?? true ),
			'error'        => (string) ( $result['error'] ?? '' ),
		];

		$log = $this->getLog();
		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::MAX_LOG_ITEMS );

		update_option( self::OPTION_LOG, $log, false );
	}
}