<?php

namespace SyncEngine\WordPress\Service;

abstract class AbstractPlatformService extends Singleton
{
	use FilterTagTrait;

	/**
	 * @return array<string, array<int, string>>
	 */
	public function getTriggerEndpointMap( $refresh = false ) {
		if ( ! $refresh ) {
			$cached = get_transient( $this->getTransientKey() );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$map = [];
		foreach ( $this->getTriggerEvents() as $event ) {
			$map[ $event ] = [];
		}

		$client = ClientService::get_instance()->getClient();
		if ( ! $client ) {
			return $map;
		}

		$automations = $client->listAutomations();
		if ( is_wp_error( $automations ) ) {
			ErrorNoticeService::get_instance()->addError( 'listAutomations', $automations );
			return $map;
		}
		if ( ! is_array( $automations ) ) {
			return $map;
		}

		$localConnectionIds = $this->getLocalConnectionIds( $client );
		if ( ! $localConnectionIds ) {
			return $map;
		}

		$classMap = $this->getBlueprintClassMap();

		foreach ( $automations as $automation ) {
			if ( ! is_array( $automation ) ) {
				continue;
			}

			$endpoint = trim( (string) ( $automation['endpoint'] ?? '' ) );
			if ( ! $endpoint ) {
				continue;
			}

			$blueprint = (array) ( $automation['config']['_blueprint'] ?? [] );
			$blueprintClass = (string) ( $blueprint['_class'] ?? '' );
			$blueprintConnection = isset( $blueprint['connection'] ) ? (int) $blueprint['connection'] : 0;

			if ( ! isset( $classMap[ $blueprintClass ] ) || ! $blueprintConnection ) {
				continue;
			}

			if ( ! in_array( $blueprintConnection, $localConnectionIds, true ) ) {
				continue;
			}

			$map[ $classMap[ $blueprintClass ] ][] = $endpoint;
		}

		foreach ( $map as $event => $endpoints ) {
			$map[ $event ] = array_values( array_unique( $endpoints ) );
		}

		$ttl = (int) apply_filters( 'syncengine_trigger_endpoint_map_ttl', 5 * MINUTE_IN_SECONDS, $this->getSource() );
		if ( $ttl > 0 ) {
			set_transient( $this->getTransientKey(), $map, $ttl );
		} else {
			delete_transient( $this->getTransientKey() );
		}

		return $map;
	}

	/**
	 * @return array<int, string>
	 */
	public function getEndpointsForTrigger( $trigger ) {
		$map = $this->getTriggerEndpointMap();
		return (array) ( $map[ $trigger ] ?? [] );
	}

	public function triggerEndpoints( $trigger, $payload = [], $context = [] ) {
		$trigger = (string) $trigger;
		$context = (array) $context;
		$sourceTag = $this->normalizeFilterTag( $this->getSource() );
		$triggerTag = $this->normalizeFilterTag( $trigger );

		$meta = [
			'source'  => $this->getSource(),
			'trigger' => $trigger,
			'context' => $context,
		];

		$payload = apply_filters( 'syncengine_trigger_payload', (array) $payload, $meta );
		if ( '' !== $sourceTag ) {
			$payload = apply_filters( 'syncengine_trigger_payload_' . $sourceTag, (array) $payload, $meta );
		}
		if ( '' !== $triggerTag ) {
			$payload = apply_filters( 'syncengine_trigger_payload_' . $triggerTag, (array) $payload, $meta );
		}
		if ( '' !== $sourceTag && '' !== $triggerTag ) {
			$payload = apply_filters( 'syncengine_trigger_payload_' . $sourceTag . '_' . $triggerTag, (array) $payload, $meta );
		}

		$shouldDispatch = apply_filters( 'syncengine_trigger_should_dispatch', true, $meta, (array) $payload );
		if ( '' !== $sourceTag ) {
			$shouldDispatch = apply_filters( 'syncengine_trigger_should_dispatch_' . $sourceTag, (bool) $shouldDispatch, $meta, (array) $payload );
		}
		if ( '' !== $triggerTag ) {
			$shouldDispatch = apply_filters( 'syncengine_trigger_should_dispatch_' . $triggerTag, (bool) $shouldDispatch, $meta, (array) $payload );
		}
		if ( '' !== $sourceTag && '' !== $triggerTag ) {
			$shouldDispatch = apply_filters( 'syncengine_trigger_should_dispatch_' . $sourceTag . '_' . $triggerTag, (bool) $shouldDispatch, $meta, (array) $payload );
		}

		if ( ! $shouldDispatch ) {
			return [];
		}

		$endpoints = $this->getEndpointsForTrigger( $trigger );
		$results = EndpointDispatcherService::get_instance()->triggerEndpoints(
			$endpoints,
			$payload,
			$meta
		);

		do_action( 'syncengine_trigger_dispatched', $results, $meta, (array) $payload );
		if ( '' !== $sourceTag ) {
			do_action( 'syncengine_trigger_dispatched_' . $sourceTag, $results, $meta, (array) $payload );
		}
		if ( '' !== $triggerTag ) {
			do_action( 'syncengine_trigger_dispatched_' . $triggerTag, $results, $meta, (array) $payload );
		}
		if ( '' !== $sourceTag && '' !== $triggerTag ) {
			do_action( 'syncengine_trigger_dispatched_' . $sourceTag . '_' . $triggerTag, $results, $meta, (array) $payload );
		}

		return $results;
	}

	public function clearTriggerEndpointMapCache() {
		delete_transient( $this->getTransientKey() );
	}

	/**
	 * @return array<int, string>
	 */
	abstract protected function getTriggerEvents();

	/**
	 * @return array<string, string>
	 */
	abstract protected function getBlueprintClassMap();

	/**
	 * @return array<int, int>
	 */
	abstract protected function getLocalConnectionIds( $client );

	/**
	 * @return string
	 */
	abstract protected function getTransientKey();

	/**
	 * @return string
	 */
	abstract protected function getSource();
}
