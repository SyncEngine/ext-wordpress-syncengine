<?php

namespace SyncEngine\WordPress\Module\WordPressCore\Service;

use SyncEngine\WordPress\Service\Singleton;

class WordPressCoreRestPayloadService extends Singleton
{
	const REST_CONTEXT = 'edit';

	public function getPostData( $post_id, $fallbackPost = null ) {
		$route = $this->getPostRestRoute( (int) $post_id );
		if ( $route ) {
			$data = $this->requestRestData( $route );
			if ( is_array( $data ) ) {
				return $data;
			}
		}

		if ( is_object( $fallbackPost ) && method_exists( $fallbackPost, 'to_array' ) ) {
			return (array) $fallbackPost->to_array();
		}

		$post = get_post( $post_id );
		if ( ! $post || ! is_object( $post ) ) {
			return [ 'id' => (int) $post_id ];
		}

		$data = (array) $post;
		$data['meta'] = get_post_meta( $post_id );

		return $data;
	}

	public function getTermData( $term_id, $taxonomy = '' ) {
		$route = $this->getTermRestRoute( (int) $term_id, (string) $taxonomy );
		if ( $route ) {
			$data = $this->requestRestData( $route );
			if ( is_array( $data ) ) {
				return $data;
			}
		}

		$term = get_term( $term_id, $taxonomy ?: '' );
		if ( ! $term || is_wp_error( $term ) ) {
			return [ 'id' => (int) $term_id, 'taxonomy' => (string) $taxonomy ];
		}

		$data = (array) $term;
		$data['meta'] = get_term_meta( $term_id );

		return $data;
	}

	public function getUserData( $user_id ) {
		$route = '/wp/v2/users/' . (int) $user_id;
		$data = $this->requestRestData( $route );
		if ( is_array( $data ) ) {
			return $data;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [ 'id' => (int) $user_id ];
		}

		$data = [
			'id'           => (int) $user->ID,
			'user_login'   => (string) $user->user_login,
			'user_email'   => (string) $user->user_email,
			'display_name' => (string) $user->display_name,
			'user_nicename'=> (string) $user->user_nicename,
			'roles'        => array_values( (array) $user->roles ),
		];

		$data['meta'] = get_user_meta( $user_id );

		return $data;
	}

	private function requestRestData( $route ) {
		$route = trim( (string) $route );
		if ( '' === $route ) {
			return null;
		}

		if ( '/' !== substr( $route, 0, 1 ) ) {
			$route = '/' . $route;
		}

		$request = new \WP_REST_Request( 'GET', $route );
		$request->set_param( 'context', self::REST_CONTEXT );

		$response = rest_do_request( $request );
		if ( ! $response || is_wp_error( $response ) ) {
			return null;
		}

		if ( method_exists( $response, 'is_error' ) && $response->is_error() ) {
			return null;
		}

		$status = method_exists( $response, 'get_status' ) ? (int) $response->get_status() : 0;
		if ( $status < 200 || $status > 299 ) {
			return null;
		}

		$server = rest_get_server();
		if ( is_object( $server ) && method_exists( $server, 'response_to_data' ) ) {
			$data = $server->response_to_data( $response, false );
			return is_array( $data ) ? $data : null;
		}

		$data = method_exists( $response, 'get_data' ) ? $response->get_data() : null;
		return is_array( $data ) ? $data : null;
	}

	private function getPostRestRoute( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! is_object( $post ) ) {
			return '';
		}

		$type = get_post_type_object( $post->post_type );
		if ( ! $type || empty( $type->show_in_rest ) || empty( $type->rest_base ) ) {
			return '';
		}

		$namespace = ! empty( $type->rest_namespace ) ? (string) $type->rest_namespace : 'wp/v2';
		return '/' . trim( $namespace, '/' ) . '/' . trim( (string) $type->rest_base, '/' ) . '/' . (int) $post_id;
	}

	private function getTermRestRoute( $term_id, $taxonomy ) {
		$taxonomy = (string) $taxonomy;
		if ( '' === $taxonomy ) {
			return '';
		}

		$tax = get_taxonomy( $taxonomy );
		if ( ! $tax || empty( $tax->show_in_rest ) || empty( $tax->rest_base ) ) {
			return '';
		}

		$namespace = ! empty( $tax->rest_namespace ) ? (string) $tax->rest_namespace : 'wp/v2';
		return '/' . trim( $namespace, '/' ) . '/' . trim( (string) $tax->rest_base, '/' ) . '/' . (int) $term_id;
	}
}
