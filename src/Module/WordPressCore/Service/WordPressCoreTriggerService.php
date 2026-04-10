<?php

namespace SyncEngine\WordPress\Module\WordPressCore\Service;

use SyncEngine\WordPress\Service\Singleton;

class WordPressCoreTriggerService extends Singleton
{
	public function register() {
		add_action( 'wp_after_insert_post', [ $this, 'action_wp_after_insert_post' ], 20, 4 );
		add_action( 'before_delete_post', [ $this, 'action_before_delete_post' ], 20, 2 );

		add_action( 'created_term', [ $this, 'action_created_term' ], 20, 4 );
		add_action( 'edited_term', [ $this, 'action_edited_term' ], 20, 4 );
		add_action( 'delete_term', [ $this, 'action_delete_term' ], 20, 5 );

		add_action( 'user_register', [ $this, 'action_user_register' ], 20, 1 );
		add_action( 'profile_update', [ $this, 'action_profile_update' ], 20, 2 );
		add_action( 'deleted_user', [ $this, 'action_deleted_user' ], 20, 1 );

		$this->registerCustomHooks();
	}

	private function registerCustomHooks() {
		foreach ( PlatformService::get_instance()->getCustomHookDefinitions() as $definition ) {
			$hook = (string) ( $definition['hook'] ?? '' );
			if ( '' === $hook ) {
				continue;
			}

			$priority = max( 1, (int) ( $definition['priority'] ?? 10 ) );
			$acceptedArgs = max( 0, (int) ( $definition['accepted_args'] ?? 99 ) );

			add_action( $hook, function () use ( $definition ) {
				$this->dispatchCustomHook( $definition, func_get_args() );
			}, $priority, $acceptedArgs );
		}
	}

	public function action_wp_after_insert_post( $post_id, $post, $update, $post_before ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post_id = (int) $post_id;
		$payload = [
			'id' => $post_id,
			'event' => $update ? 'wp_updated_post' : 'wp_new_post',
			'data' => $this->getPayloadDataService()->getPostData( $post_id, $post ),
			'request' => [
				'id' => $post_id,
				'update' => (bool) $update,
			],
		];

		$trigger = $update ? PlatformService::TRIGGER_UPDATED_POST : PlatformService::TRIGGER_NEW_POST;
		$this->dispatchKnownTrigger( $trigger, $payload, [
			'hook' => 'wp_after_insert_post',
			'args' => [ $post_id, $post, $update, $post_before ],
		] );
	}

	public function action_before_delete_post( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post_id = (int) $post_id;
		$payload = [
			'id' => $post_id,
			'event' => 'wp_deleted_post',
			'data' => $this->getPayloadDataService()->getPostData( $post_id, $post ),
			'request' => [ 'id' => $post_id ],
		];

		$this->dispatchKnownTrigger( PlatformService::TRIGGER_DELETED_POST, $payload, [
			'hook' => 'before_delete_post',
			'args' => [ $post_id, $post ],
		] );
	}

	public function action_created_term( $term_id, $tt_id, $taxonomy, $args ) {
		$term_id = (int) $term_id;
		$payload = [
			'id' => $term_id,
			'event' => 'wp_new_term',
			'data' => $this->getPayloadDataService()->getTermData( $term_id, (string) $taxonomy ),
			'request' => [
				'id' => $term_id,
				'tt_id' => (int) $tt_id,
				'taxonomy' => (string) $taxonomy,
				'args' => (array) $args,
			],
		];

		$this->dispatchKnownTrigger( PlatformService::TRIGGER_NEW_TERM, $payload, [
			'hook' => 'created_term',
			'args' => [ $term_id, $tt_id, $taxonomy, $args ],
		] );
	}

	public function action_edited_term( $term_id, $tt_id, $taxonomy, $args ) {
		$term_id = (int) $term_id;
		$payload = [
			'id' => $term_id,
			'event' => 'wp_updated_term',
			'data' => $this->getPayloadDataService()->getTermData( $term_id, (string) $taxonomy ),
			'request' => [
				'id' => $term_id,
				'tt_id' => (int) $tt_id,
				'taxonomy' => (string) $taxonomy,
				'args' => (array) $args,
			],
		];

		$this->dispatchKnownTrigger( PlatformService::TRIGGER_UPDATED_TERM, $payload, [
			'hook' => 'edited_term',
			'args' => [ $term_id, $tt_id, $taxonomy, $args ],
		] );
	}

	public function action_delete_term( $term, $tt_id, $taxonomy, $deleted_term, $object_ids ) {
		$term_id = (int) $term;
		$data = is_object( $deleted_term ) ? (array) $deleted_term : [ 'id' => $term_id, 'taxonomy' => (string) $taxonomy ];
		$payload = [
			'id' => $term_id,
			'event' => 'wp_deleted_term',
			'data' => $data,
			'request' => [
				'id' => $term_id,
				'tt_id' => (int) $tt_id,
				'taxonomy' => (string) $taxonomy,
				'object_ids' => array_values( array_map( 'intval', (array) $object_ids ) ),
			],
		];

		$this->dispatchKnownTrigger( PlatformService::TRIGGER_DELETED_TERM, $payload, [
			'hook' => 'delete_term',
			'args' => [ $term, $tt_id, $taxonomy, $deleted_term, $object_ids ],
		] );
	}

	public function action_user_register( $user_id ) {
		$user_id = (int) $user_id;
		$payload = [
			'id' => $user_id,
			'event' => 'wp_new_user',
			'data' => $this->getPayloadDataService()->getUserData( $user_id ),
			'request' => [ 'id' => $user_id ],
		];

		$this->dispatchKnownTrigger( PlatformService::TRIGGER_NEW_USER, $payload, [
			'hook' => 'user_register',
			'args' => [ $user_id ],
		] );
	}

	public function action_profile_update( $user_id, $old_user_data ) {
		$user_id = (int) $user_id;
		$payload = [
			'id' => $user_id,
			'event' => 'wp_updated_user',
			'data' => $this->getPayloadDataService()->getUserData( $user_id ),
			'request' => [
				'id' => $user_id,
				'old_user_data' => is_object( $old_user_data ) ? (array) $old_user_data : [],
			],
		];

		$this->dispatchKnownTrigger( PlatformService::TRIGGER_UPDATED_USER, $payload, [
			'hook' => 'profile_update',
			'args' => [ $user_id, $old_user_data ],
		] );
	}

	public function action_deleted_user( $user_id ) {
		$user_id = (int) $user_id;
		$payload = [
			'id' => $user_id,
			'event' => 'wp_deleted_user',
			'data' => [ 'id' => $user_id ],
			'request' => [ 'id' => $user_id ],
		];

		$this->dispatchKnownTrigger( PlatformService::TRIGGER_DELETED_USER, $payload, [
			'hook' => 'deleted_user',
			'args' => [ $user_id ],
		] );
	}

	private function dispatchCustomHook( $definition, $args ) {
		$hook = (string) ( $definition['hook'] ?? current_filter() );
		$normalizedArgs = $this->normalizeValue( array_values( (array) $args ) );

		$payload = [
			'event' => 'wp_custom_hook',
			'data' => [
				'hook' => $hook,
				'args' => $normalizedArgs,
			],
			'request' => [
				'hook' => $hook,
				'args' => $normalizedArgs,
				'arg_count' => count( $normalizedArgs ),
			],
		];

		$payloadId = $this->resolvePayloadId( $args );
		if ( null !== $payloadId ) {
			$payload['id'] = $payloadId;
		}

		PlatformService::get_instance()->triggerCustomHook( $hook, $payload );
	}

	private function dispatchKnownTrigger( $trigger, $payload, $context = [] ) {
		$trigger = (string) $trigger;
		$context = (array) $context;

		return PlatformService::get_instance()->triggerEndpoints( $trigger, $payload, $context );
	}

	private function getPayloadDataService() {
		return WordPressCoreRestPayloadService::get_instance();
	}

	private function resolvePayloadId( $args ) {
		foreach ( (array) $args as $value ) {
			if ( is_numeric( $value ) ) {
				return (int) $value;
			}

			if ( is_object( $value ) ) {
				foreach ( [ 'ID', 'id', 'term_id', 'comment_ID', 'user_id' ] as $property ) {
					if ( isset( $value->{$property} ) && is_numeric( $value->{$property} ) ) {
						return (int) $value->{$property};
					}
				}
			}
		}

		return null;
	}

	private function normalizeValue( $value, $depth = 0 ) {
		if ( $depth >= 4 ) {
			if ( is_scalar( $value ) || null === $value ) {
				return $value;
			}

			return is_object( $value ) ? get_class( $value ) : gettype( $value );
		}

		if ( is_scalar( $value ) || null === $value ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			$normalized = [];
			foreach ( $value as $key => $item ) {
				$normalized[ $key ] = $this->normalizeValue( $item, $depth + 1 );
			}

			return $normalized;
		}

		if ( is_object( $value ) ) {
			if ( method_exists( $value, 'to_array' ) ) {
				return $this->normalizeValue( $value->to_array(), $depth + 1 );
			}

			return $this->normalizeValue( get_object_vars( $value ), $depth + 1 );
		}

		return gettype( $value );
	}
}
