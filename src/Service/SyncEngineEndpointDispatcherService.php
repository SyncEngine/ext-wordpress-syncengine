<?php

namespace SyncEngine\WordPress\Service;

class SyncEngineEndpointDispatcherService extends Singleton
{
	/**
	 * @param array<int, string> $endpoints
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $meta
	 *
	 * @return array<string, mixed>
	 */
	public function triggerEndpoints( $endpoints, $payload = [], $meta = [] ) {
		$client = SyncEngineClientService::get_instance()->getClient();
		if ( ! $client ) {
			return [];
		}

		$source = (string) ( $meta['source'] ?? 'unknown' );
		$trigger = (string) ( $meta['trigger'] ?? '' );

		$results = [];
		foreach ( (array) $endpoints as $endpoint ) {
			$endpoint = (string) $endpoint;
			if ( '' === $endpoint ) {
				continue;
			}

			$result = $client->triggerEndpoint( $endpoint, $payload );
			$results[ $endpoint ] = $result;

			SyncEngineDispatchLogService::get_instance()->add( $source, $trigger, $endpoint, $payload, (array) $result );
		}

		return $results;
	}
}