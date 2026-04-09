<?php

namespace SyncEngine\WordPress\Service;

class EndpointDispatcherService extends Singleton
{
	use FilterTagTrait;

	/**
	 * @param array<int, string> $endpoints
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $meta
	 *
	 * @return array<string, mixed>
	 */
	public function triggerEndpoints( $endpoints, $payload = [], $meta = [] ) {
		$client = ClientService::get_instance()->getClient();
		if ( ! $client ) {
			return [];
		}

		$source = (string) ( $meta['source'] ?? 'unknown' );
		$trigger = (string) ( $meta['trigger'] ?? '' );
		$triggerTag = $this->normalizeFilterTag( $trigger );

		$payload = apply_filters( 'syncengine_dispatch_payload', (array) $payload, (array) $meta );
		if ( '' !== $triggerTag ) {
			$payload = apply_filters( 'syncengine_dispatch_payload_' . $triggerTag, (array) $payload, (array) $meta );
		}

		$endpoints = apply_filters( 'syncengine_dispatch_endpoints', (array) $endpoints, (array) $payload, (array) $meta );
		if ( '' !== $triggerTag ) {
			$endpoints = apply_filters( 'syncengine_dispatch_endpoints_' . $triggerTag, (array) $endpoints, (array) $payload, (array) $meta );
		}

		$endpoints = array_values( array_filter( array_map( 'strval', (array) $endpoints ) ) );

		$results = [];
		foreach ( (array) $endpoints as $endpoint ) {
			$endpoint = (string) $endpoint;
			if ( '' === $endpoint ) {
				continue;
			}

			$endpointPayload = apply_filters( 'syncengine_dispatch_payload_for_endpoint', (array) $payload, $endpoint, (array) $meta );
			if ( '' !== $triggerTag ) {
				$endpointPayload = apply_filters( 'syncengine_dispatch_payload_for_endpoint_' . $triggerTag, (array) $endpointPayload, $endpoint, (array) $meta );
			}

			$result = $client->triggerEndpoint( $endpoint, $endpointPayload );
			$result = apply_filters( 'syncengine_dispatch_result', $result, $endpoint, (array) $endpointPayload, (array) $meta );
			if ( '' !== $triggerTag ) {
				$result = apply_filters( 'syncengine_dispatch_result_' . $triggerTag, $result, $endpoint, (array) $endpointPayload, (array) $meta );
			}
			$results[ $endpoint ] = $result;

			DispatchLogService::get_instance()->add( $source, $trigger, $endpoint, $endpointPayload, (array) $result );
		}

		do_action( 'syncengine_dispatch_completed', $results, (array) $endpoints, (array) $payload, (array) $meta );
		if ( '' !== $triggerTag ) {
			do_action( 'syncengine_dispatch_completed_' . $triggerTag, $results, (array) $endpoints, (array) $payload, (array) $meta );
		}

		return $results;
	}

}