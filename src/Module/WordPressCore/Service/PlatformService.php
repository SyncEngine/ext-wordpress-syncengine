<?php

namespace SyncEngine\WordPress\Module\WordPressCore\Service;

use SyncEngine\WordPress\Service\AbstractPlatformService;
use SyncEngine\WordPress\Service\ErrorNoticeService;

class PlatformService extends AbstractPlatformService
{
	const TRANSIENT_TRIGGER_ENDPOINT_MAP = 'syncengine_wp_core_trigger_endpoint_map';
	const WP_WEBSERVICE_CLASS = 'SyncEngine/WordpressRestV2:WordpressRestV2';

	const TRIGGER_NEW_POST = 'new_post';
	const TRIGGER_UPDATED_POST = 'updated_post';
	const TRIGGER_DELETED_POST = 'deleted_post';
	const TRIGGER_NEW_TERM = 'new_term';
	const TRIGGER_UPDATED_TERM = 'updated_term';
	const TRIGGER_DELETED_TERM = 'deleted_term';
	const TRIGGER_NEW_USER = 'new_user';
	const TRIGGER_UPDATED_USER = 'updated_user';
	const TRIGGER_DELETED_USER = 'deleted_user';

	/**
	 * @return array<int, string>
	 */
	protected function getTriggerEvents() {
		return [
			self::TRIGGER_NEW_POST,
			self::TRIGGER_UPDATED_POST,
			self::TRIGGER_DELETED_POST,
			self::TRIGGER_NEW_TERM,
			self::TRIGGER_UPDATED_TERM,
			self::TRIGGER_DELETED_TERM,
			self::TRIGGER_NEW_USER,
			self::TRIGGER_UPDATED_USER,
			self::TRIGGER_DELETED_USER,
		];
	}

	/**
	 * @return array<string, string>
	 */
	protected function getBlueprintClassMap() {
		return [
			'SyncEngine/WordpressRestV2:NewPost' => self::TRIGGER_NEW_POST,
			'SyncEngine/WordpressRestV2:UpdatedPost' => self::TRIGGER_UPDATED_POST,
			'SyncEngine/WordpressRestV2:DeletedPost' => self::TRIGGER_DELETED_POST,
			'SyncEngine/WordpressRestV2:NewTerm' => self::TRIGGER_NEW_TERM,
			'SyncEngine/WordpressRestV2:UpdatedTerm' => self::TRIGGER_UPDATED_TERM,
			'SyncEngine/WordpressRestV2:DeletedTerm' => self::TRIGGER_DELETED_TERM,
			'SyncEngine/WordpressRestV2:NewUser' => self::TRIGGER_NEW_USER,
			'SyncEngine/WordpressRestV2:UpdatedUser' => self::TRIGGER_UPDATED_USER,
			'SyncEngine/WordpressRestV2:DeletedUser' => self::TRIGGER_DELETED_USER,
		];
	}

	/**
	 * @return array<int, int>
	 */
	protected function getLocalConnectionIds( $client ) {
		$connections = $client->listConnections();
		if ( is_wp_error( $connections ) ) {
			ErrorNoticeService::get_instance()->addError( 'listConnections', $connections );
			return [];
		}
		if ( ! is_array( $connections ) ) {
			return [];
		}

		$localHosts = $this->getLocalSiteHosts();
		if ( ! $localHosts ) {
			return [];
		}

		$ids = [];
		foreach ( $connections as $connection ) {
			if ( ! is_array( $connection ) ) {
				continue;
			}

			$id = (int) ( $connection['id'] ?? 0 );
			$config = (array) ( $connection['config'] ?? [] );
			$webservice = (array) ( $config['webservice'] ?? [] );
			$class = (string) ( $webservice['_class'] ?? '' );

			if ( self::WP_WEBSERVICE_CLASS !== $class ) {
				continue;
			}

			$host = $this->normalizeSiteHost( (string) ( $webservice['host'] ?? '' ) );
			if ( $id && $host && in_array( $host, $localHosts, true ) ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
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
		return 'wordpress_core';
	}

	/**
	 * @return array<int, string>
	 */
	private function getLocalSiteHosts() {
		$hosts = [];

		foreach ( [ home_url(), site_url() ] as $url ) {
			$host = $this->normalizeSiteHost( (string) $url );
			if ( $host ) {
				$hosts[] = $host;
			}
		}

		return array_values( array_unique( $hosts ) );
	}

	private function normalizeSiteHost( $url ) {
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

		$path = preg_replace( '#/wp-json/wp/v2$#i', '', $path );
		$path = preg_replace( '#/wp-json$#i', '', $path );
		$path = trim( (string) $path, '/' );

		return $host . $port . ( $path ? '/' . $path : '' );
	}
}
