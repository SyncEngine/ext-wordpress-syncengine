<?php

namespace SyncEngine\WordPress\Service;

abstract class AbstractPlatformService extends Singleton
{
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

		set_transient( $this->getTransientKey(), $map, 5 * MINUTE_IN_SECONDS );

		return $map;
	}

	/**
	 * @return array<int, string>
	 */
	public function getEndpointsForTrigger( $trigger ) {
		$map = $this->getTriggerEndpointMap();
		return (array) ( $map[ $trigger ] ?? [] );
	}

	public function triggerEndpoints( $trigger, $payload = [] ) {
		$endpoints = $this->getEndpointsForTrigger( $trigger );

		return EndpointDispatcherService::get_instance()->triggerEndpoints(
			$endpoints,
			$payload,
			[
				'source'  => $this->getSource(),
				'trigger' => (string) $trigger,
			]
		);
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
