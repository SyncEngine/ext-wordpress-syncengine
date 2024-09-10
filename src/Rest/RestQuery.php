<?php

namespace SyncEngine\WordPress\Rest;

use SyncEngine\WordPress\Service\Singleton;

class RestQuery extends Singleton
{
	public function register()
	{
		$post_types = get_post_types();
		foreach ( $post_types as $post_type ) {
			$name = ( is_object( $post_type ) ) ? $post_type->name : $post_type;
			add_filter( 'rest_' . $name . '_query', array( $this, 'query_fields' ), 100000, 2 );
		}

		$taxonomies = get_taxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			$name = ( is_object( $taxonomy ) ) ? $taxonomy->name : $taxonomy;
			add_filter( 'rest_' . $name . '_query', array( $this, 'query_fields' ), 100000, 2 );
		}

		add_filter( 'rest_user_query', array( $this, 'query_fields' ), 100000, 2 );
		add_filter( 'rest_attachment_query', array( $this, 'query_fields' ), 100000, 2 );
	}

	public function query_fields( $args, $request )
	{
		if ( isset( $request['meta_query'] ) ) {
			if ( isset( $args['meta_query'] ) ) {
				$args['meta_query'][] = $request['meta_query'];
			} else {
				$args['meta_query'] = $request['meta_query'];
			}
		}

		if ( isset( $request['meta_key'] ) && ! isset( $args['meta_key'] ) ) {
			$args['meta_key'] = $request['meta_key'];
		}

		if ( isset( $request['meta_value'] ) && ! isset( $args['meta_value'] ) ) {
			$args['meta_value'] = $request['meta_value'];
		}

		if ( isset( $request['meta_compare'] ) && ! isset( $args['meta_compare'] ) ) {
			$args['meta_compare'] = $request['meta_compare'];
		}

		return $args;
	}
}
