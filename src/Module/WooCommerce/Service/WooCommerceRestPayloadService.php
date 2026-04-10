<?php

namespace SyncEngine\WordPress\Module\WooCommerce\Service;

use SyncEngine\WordPress\Service\Singleton;

class WooCommerceRestPayloadService extends Singleton
{
	const REST_CONTEXT   = 'edit';
	const REST_NAMESPACE = 'wc/v3';

	public function getProductData( int $product_id ): array {
		$route = '/' . self::REST_NAMESPACE . '/products/' . $product_id;
		$data  = $this->requestRestData( $route );
		if ( is_array( $data ) ) {
			return $data;
		}

		return $this->getProductFallback( $product_id );
	}

	public function getProductVariationData( int $variation_id ): array {
		$parent_id = $this->getVariationParentId( $variation_id );
		if ( $parent_id > 0 ) {
			$route = '/' . self::REST_NAMESPACE . '/products/' . $parent_id . '/variations/' . $variation_id;
			$data  = $this->requestRestData( $route );
			if ( is_array( $data ) ) {
				// Ensure product_id is present for convenience, parallel to the native fallback.
				$data['product_id'] = $parent_id;
				return $data;
			}
		}

		return $this->getProductVariationFallback( $variation_id );
	}

	public function getOrderData( int $order_id ): array {
		$route = '/' . self::REST_NAMESPACE . '/orders/' . $order_id;
		$data  = $this->requestRestData( $route );
		if ( is_array( $data ) ) {
			return $data;
		}

		return $this->getOrderFallback( $order_id );
	}

	public function getCustomerData( int $user_id ): array {
		$route = '/' . self::REST_NAMESPACE . '/customers/' . $user_id;
		$data  = $this->requestRestData( $route );
		if ( is_array( $data ) ) {
			return $data;
		}

		return $this->getCustomerFallback( $user_id );
	}

	public function getCouponData( int $coupon_id ): array {
		$route = '/' . self::REST_NAMESPACE . '/coupons/' . $coupon_id;
		$data  = $this->requestRestData( $route );
		if ( is_array( $data ) ) {
			return $data;
		}

		return $this->getCouponFallback( $coupon_id );
	}

	// -------------------------------------------------------------------------
	// Internal REST request
	// -------------------------------------------------------------------------

	private function requestRestData( string $route ): ?array {
		$route = trim( $route );
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

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	private function getVariationParentId( int $variation_id ): int {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return 0;
		}

		$variation = wc_get_product( $variation_id );
		if ( ! $variation || ! method_exists( $variation, 'get_parent_id' ) ) {
			return 0;
		}

		return (int) $variation->get_parent_id();
	}

	// -------------------------------------------------------------------------
	// Fallbacks (native WC/WP object data when REST is unavailable or fails)
	// -------------------------------------------------------------------------

	private function getProductFallback( int $product_id ): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return [ 'id' => $product_id ];
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! method_exists( $product, 'get_data' ) ) {
			return [ 'id' => $product_id ];
		}

		$data = $product->get_data();

		if ( method_exists( $product, 'get_attributes' ) ) {
			$attributes = [];
			foreach ( $product->get_attributes() as $attribute ) {
				$attributes[] = method_exists( $attribute, 'get_data' ) ? $attribute->get_data() : [];
			}
			$data['attributes'] = $attributes;
		}

		return $data;
	}

	private function getProductVariationFallback( int $variation_id ): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return [ 'id' => $variation_id ];
		}

		$variation = wc_get_product( $variation_id );
		if ( ! $variation || ! is_object( $variation ) || ! method_exists( $variation, 'get_data' ) ) {
			return [ 'id' => $variation_id ];
		}

		$data = $variation->get_data();
		if ( method_exists( $variation, 'get_parent_id' ) ) {
			$data['product_id'] = (int) $variation->get_parent_id();
		}

		return $data;
	}

	private function getOrderFallback( int $order_id ): array {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return [ 'id' => $order_id ];
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! is_object( $order ) || ! method_exists( $order, 'get_data' ) ) {
			return [ 'id' => $order_id ];
		}

		$data = $order->get_data();

		if ( method_exists( $order, 'get_items' ) ) {
			$data['line_items'] = array_values( array_map( function ( $item ) {
				return method_exists( $item, 'get_data' ) ? $item->get_data() : [];
			}, $order->get_items() ) );
		}

		if ( method_exists( $order, 'get_shipping_methods' ) ) {
			$data['shipping_lines'] = array_values( array_map( function ( $item ) {
				return method_exists( $item, 'get_data' ) ? $item->get_data() : [];
			}, $order->get_shipping_methods() ) );
		}

		if ( method_exists( $order, 'get_fee_lines' ) ) {
			$data['fee_lines'] = array_values( array_map( function ( $item ) {
				return method_exists( $item, 'get_data' ) ? $item->get_data() : [];
			}, $order->get_fee_lines() ) );
		}

		if ( method_exists( $order, 'get_coupon_codes' ) ) {
			$data['coupon_codes'] = $order->get_coupon_codes();
		}

		return $data;
	}

	private function getCustomerFallback( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [ 'id' => $user_id ];
		}

		return [
			'id'            => (int) $user->ID,
			'user_login'    => (string) $user->user_login,
			'user_email'    => (string) $user->user_email,
			'display_name'  => (string) $user->display_name,
			'user_nicename' => (string) $user->user_nicename,
			'roles'         => array_values( (array) $user->roles ),
		];
	}

	private function getCouponFallback( int $coupon_id ): array {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return [ 'id' => $coupon_id ];
		}

		$coupon = new \WC_Coupon( $coupon_id );
		if ( ! $coupon || ! method_exists( $coupon, 'get_data' ) ) {
			return [ 'id' => $coupon_id ];
		}

		return $coupon->get_data();
	}
}
