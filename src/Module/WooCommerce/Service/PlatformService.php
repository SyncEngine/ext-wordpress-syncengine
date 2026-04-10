<?php

namespace SyncEngine\WordPress\Module\WooCommerce\Service;

use SyncEngine\WordPress\Service\AbstractPlatformService;
use SyncEngine\WordPress\Service\RefreshTrustService;

class PlatformService extends AbstractPlatformService
{
	const TRANSIENT_TRIGGER_ENDPOINT_MAP = 'syncengine_wc_trigger_endpoint_map';
	const WC_WEBSERVICE_CLASS = 'SyncEngine/WooCommerceRestV3:WooCommerceRestV3';
	const TRIGGER_NEW_CUSTOMER = 'new_customer';
	const TRIGGER_NEW_ORDER = 'new_order';
	const TRIGGER_UPDATED_ORDER = 'updated_order';
	const TRIGGER_UPDATED_PRODUCT = 'updated_product';

	/**
	 * @return array<int, string>
	 */
	protected function getTriggerEvents() {
		return [
			self::TRIGGER_NEW_CUSTOMER,
			self::TRIGGER_NEW_ORDER,
			self::TRIGGER_UPDATED_ORDER,
			self::TRIGGER_UPDATED_PRODUCT,
		];
	}

	/**
	 * @return array<string, string>
	 */
	protected function getBlueprintClassMap() {
		return [
			'SyncEngine/WooCommerceRestV3:NewCustomer'    => self::TRIGGER_NEW_CUSTOMER,
			'SyncEngine/WooCommerceRestV3:NewOrder'       => self::TRIGGER_NEW_ORDER,
			'SyncEngine/WooCommerceRestV3:UpdatedOrder'   => self::TRIGGER_UPDATED_ORDER,
			'SyncEngine/WooCommerceRestV3:UpdatedProduct' => self::TRIGGER_UPDATED_PRODUCT,
		];
	}

	/**
	 * @return array<int, int>
	 */
	protected function getLocalConnectionIds( $client ) {
		return $this->getLocalWooConnectionIds( $client );
	}

	/**
	 * @return string
	 */
	protected function getTransientKey() {
		return self::TRANSIENT_TRIGGER_ENDPOINT_MAP;
	}

	/**
	 * @return string
	 */
	protected function getSource() {
		return 'woocommerce';
	}

	/**
	 * @return array<int, int>
	 */
	private function getLocalWooConnectionIds( $client ) {
		$connections = $client->listConnections();
		if ( is_wp_error( $connections ) ) {
			\SyncEngine\WordPress\Service\ErrorNoticeService::get_instance()->addError( 'listConnections', $connections );
			return [];
		}
		if ( ! is_array( $connections ) ) {
			return [];
		}

		$localHosts = $this->getLocalStoreHosts();
		if ( ! $localHosts ) {
			return [];
		}

		$ids = [];
		$refs = [];
		foreach ( $connections as $connection ) {
			if ( ! is_array( $connection ) ) {
				continue;
			}

			$id = (int) ( $connection['id'] ?? 0 );
			$config = (array) ( $connection['config'] ?? [] );
			$webservice = (array) ( $config['webservice'] ?? [] );
			$class = (string) ( $webservice['_class'] ?? '' );

			if ( self::WC_WEBSERVICE_CLASS !== $class ) {
				continue;
			}

			$host = $this->normalizeStoreHost( (string) ( $webservice['host'] ?? '' ) );
			if ( $id && $host && in_array( $host, $localHosts, true ) ) {
				$ids[] = $id;
				$ref = trim( (string) ( $connection['ref'] ?? '' ) );
				if ( '' !== $ref ) {
					$refs[] = $ref;
				}
			}
		}

		RefreshTrustService::get_instance()->rememberConnectionRefs( $refs );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @return array<int, string>
	 */
	private function getLocalStoreHosts() {
		$hosts = [];

		foreach ( [ home_url(), site_url() ] as $url ) {
			$host = $this->normalizeStoreHost( (string) $url );
			if ( $host ) {
				$hosts[] = $host;
			}
		}

		return array_values( array_unique( $hosts ) );
	}

	private function normalizeStoreHost( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return '';
		}

		$host = strtolower( (string) $parts['host'] );
		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path = trim( (string) ( $parts['path'] ?? '' ), '/' );

		$path = preg_replace( '#/wp-json/wc/v3$#i', '', $path );
		$path = preg_replace( '#/wp-json$#i', '', $path );
		$path = trim( (string) $path, '/' );

		return $host . $port . ( $path ? '/' . $path : '' );
	}
}