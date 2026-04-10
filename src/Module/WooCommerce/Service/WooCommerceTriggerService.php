<?php

namespace SyncEngine\WordPress\Module\WooCommerce\Service;

use SyncEngine\WordPress\Service\Singleton;

class WooCommerceTriggerService extends Singleton
{
	/**
	 * Buffered trigger queue for this request.
	 *
	 * Key format: "{trigger}:{id}". Last write wins per key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $pendingTriggers = [];

	public function register() {
		add_action( 'woocommerce_created_customer', [ $this, 'action_woocommerce_created_customer' ], 20, 3 );
		add_action( 'woocommerce_update_customer', [ $this, 'action_woocommerce_update_customer' ], 20, 1 );
		add_action( 'delete_user', [ $this, 'action_delete_user' ], 20, 1 );

		add_action( 'woocommerce_new_order', [ $this, 'action_woocommerce_new_order' ], 20, 2 );
		add_action( 'woocommerce_update_order', [ $this, 'action_woocommerce_update_order' ], 20, 2 );
		add_action( 'woocommerce_before_delete_order', [ $this, 'action_woocommerce_before_delete_order' ], 20, 1 );

		add_action( 'woocommerce_new_product', [ $this, 'action_woocommerce_new_product' ], 20, 1 );
		add_action( 'woocommerce_update_product', [ $this, 'action_woocommerce_update_product' ], 20, 1 );
		add_action( 'woocommerce_before_delete_product', [ $this, 'action_woocommerce_before_delete_product' ], 20, 1 );

		add_action( 'woocommerce_new_coupon', [ $this, 'action_woocommerce_new_coupon' ], 20, 1 );
		add_action( 'woocommerce_update_coupon', [ $this, 'action_woocommerce_update_coupon' ], 20, 1 );
		add_action( 'woocommerce_before_delete_coupon', [ $this, 'action_woocommerce_before_delete_coupon' ], 20, 1 );

		add_action( 'woocommerce_new_product_variation', [ $this, 'action_woocommerce_new_product_variation' ], 20, 1 );
		add_action( 'woocommerce_update_product_variation', [ $this, 'action_woocommerce_update_product_variation' ], 20, 1 );
		add_action( 'woocommerce_before_delete_product_variation', [ $this, 'action_woocommerce_before_delete_product_variation' ], 20, 1 );

		// Fallback for stores where WC-specific before_delete hooks are absent (e.g. legacy
		// order storage) or skipped by third-party plugins. Runs at priority 5 so WC-specific
		// hooks at priority 20 still win; the shared queue deduplicates if both fire.
		add_action( 'before_delete_post', [ $this, 'action_before_delete_post_fallback' ], 5, 1 );

		add_action( 'shutdown', [ $this, 'action_shutdown_dispatch_queued_triggers' ], 999 );
	}

	public function action_before_delete_post_fallback( $post_id ) {
		// Skip revisions and autosaves — they share the same hook but are never Woo objects.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		switch ( get_post_type( $post_id ) ) {
			case 'product':
				// Fires only when woocommerce_before_delete_product has NOT already queued
				// this id (non-HPOS stores without the WC hook). Dedup happens in queueTrigger.
				$this->action_woocommerce_before_delete_product( $post_id );
				break;
			case 'product_variation':
				$this->action_woocommerce_before_delete_product_variation( $post_id );
				break;
			case 'shop_coupon':
				$this->action_woocommerce_before_delete_coupon( $post_id );
				break;
			case 'shop_order':
				// Legacy (non-HPOS) order storage: woocommerce_before_delete_order does not
				// fire, so this is the only hook we get. HPOS orders never reach here.
				$this->action_woocommerce_before_delete_order( $post_id );
				break;
		}
	}

	public function action_woocommerce_created_customer( $customer_id, $new_customer_data = [], $password_generated = false ) {
		$this->queueTrigger(
			PlatformService::TRIGGER_NEW_CUSTOMER,
			(int) $customer_id,
			'woocommerce_created_customer',
			[
				'id'                 => (int) $customer_id,
				'new_customer_data'  => $new_customer_data,
				'password_generated' => (bool) $password_generated,
			]
		);
	}

	public function action_woocommerce_update_customer( $customer_id ) {
		$customer_id = (int) $customer_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_UPDATED_CUSTOMER,
			$customer_id,
			'woocommerce_update_customer',
			[ 'id' => $customer_id ]
		);
	}

	public function action_delete_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( 'customer', (array) $user->roles, true ) ) {
			return;
		}

		$this->queueTrigger(
			PlatformService::TRIGGER_DELETED_CUSTOMER,
			$user_id,
			'delete_user',
			[ 'id' => $user_id ]
		);
	}

	public function action_woocommerce_new_order( $order_id, $order = null ) {
		$order_id = (int) $order_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_NEW_ORDER,
			$order_id,
			'woocommerce_new_order',
			[ 'id' => $order_id ]
		);
	}

	public function action_woocommerce_update_order( $order_id, $order = null ) {
		$order_id = (int) $order_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_UPDATED_ORDER,
			$order_id,
			'woocommerce_update_order',
			[ 'id' => $order_id ]
		);
	}

	public function action_woocommerce_before_delete_order( $order_id ) {
		$order_id = (int) $order_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_DELETED_ORDER,
			$order_id,
			'woocommerce_before_delete_order',
			[ 'id' => $order_id ]
		);
	}

	public function action_woocommerce_new_product( $product_id ) {
		$product_id = (int) $product_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_NEW_PRODUCT,
			$product_id,
			'woocommerce_new_product',
			[ 'id' => $product_id ]
		);
	}

	public function action_woocommerce_update_product( $product_id ) {
		$product_id = (int) $product_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_UPDATED_PRODUCT,
			$product_id,
			'woocommerce_update_product',
			[ 'id' => $product_id ]
		);
	}

	public function action_woocommerce_before_delete_product( $product_id ) {
		$product_id = (int) $product_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_DELETED_PRODUCT,
			$product_id,
			'woocommerce_before_delete_product',
			[ 'id' => $product_id ]
		);
	}

	public function action_woocommerce_new_coupon( $coupon_id ) {
		$coupon_id = (int) $coupon_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_NEW_COUPON,
			$coupon_id,
			'woocommerce_new_coupon',
			[ 'id' => $coupon_id ]
		);
	}

	public function action_woocommerce_update_coupon( $coupon_id ) {
		$coupon_id = (int) $coupon_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_UPDATED_COUPON,
			$coupon_id,
			'woocommerce_update_coupon',
			[ 'id' => $coupon_id ]
		);
	}

	public function action_woocommerce_before_delete_coupon( $coupon_id ) {
		$coupon_id = (int) $coupon_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_DELETED_COUPON,
			$coupon_id,
			'woocommerce_before_delete_coupon',
			[ 'id' => $coupon_id ]
		);
	}

	public function action_woocommerce_new_product_variation( $variation_id ) {
		$variation_id = (int) $variation_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_NEW_PRODUCT_VARIATION,
			$variation_id,
			'woocommerce_new_product_variation',
			[ 'id' => $variation_id ]
		);
	}

	public function action_woocommerce_update_product_variation( $variation_id ) {
		$variation_id = (int) $variation_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_UPDATED_PRODUCT_VARIATION,
			$variation_id,
			'woocommerce_update_product_variation',
			[ 'id' => $variation_id ]
		);
	}

	public function action_woocommerce_before_delete_product_variation( $variation_id ) {
		$variation_id = (int) $variation_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_DELETED_PRODUCT_VARIATION,
			$variation_id,
			'woocommerce_before_delete_product_variation',
			[ 'id' => $variation_id ]
		);
	}

	public function action_shutdown_dispatch_queued_triggers() {
		if ( empty( $this->pendingTriggers ) ) {
			return;
		}

		$queued = $this->pendingTriggers;
		$this->pendingTriggers = [];

		foreach ( $queued as $item ) {
			$trigger = (string) ( $item['trigger'] ?? '' );
			$event = (string) ( $item['event'] ?? '' );
			$id = (int) ( $item['id'] ?? 0 );
			$request = (array) ( $item['request'] ?? [] );

			if ( '' === $trigger || '' === $event || $id <= 0 ) {
				continue;
			}

			$payload = [
				'id'      => $id,
				'event'   => $event,
				'data'    => $this->getQueuedData( $trigger, $id ),
				'request' => array_merge( $request, [
					'queued' => true,
					'flush_hook' => 'shutdown',
				] ),
			];

			PlatformService::get_instance()->triggerEndpoints( $trigger, $payload );
		}
	}

	private function queueTrigger( string $trigger, int $id, string $event, array $request = [] ): void
	{
		if ( $id <= 0 || '' === $trigger || '' === $event ) {
			return;
		}

		$request = array_merge( [ 'id' => $id ], $request );
		$key = $trigger . ':' . $id;

		// Last write wins for this trigger+id within the current request.
		$this->pendingTriggers[ $key ] = [
			'trigger' => $trigger,
			'id'      => $id,
			'event'   => $event,
			'request' => $request,
		];
	}

	private function getQueuedData( string $trigger, int $id ): array
	{
		return match ( $trigger ) {
			PlatformService::TRIGGER_NEW_CUSTOMER => [
				'id'   => $id,
				'user' => $this->getWpUserData( $id ),
			],
			PlatformService::TRIGGER_UPDATED_CUSTOMER,
			PlatformService::TRIGGER_DELETED_CUSTOMER => [
				'id'   => $id,
				'user' => $this->getWpUserData( $id ),
			],
			PlatformService::TRIGGER_NEW_ORDER,
			PlatformService::TRIGGER_UPDATED_ORDER,
			PlatformService::TRIGGER_DELETED_ORDER => $this->getOrderData( $id ),
			PlatformService::TRIGGER_NEW_PRODUCT,
			PlatformService::TRIGGER_UPDATED_PRODUCT,
			PlatformService::TRIGGER_DELETED_PRODUCT => $this->getProductData( $id ),
			PlatformService::TRIGGER_NEW_COUPON,
			PlatformService::TRIGGER_UPDATED_COUPON,
			PlatformService::TRIGGER_DELETED_COUPON => $this->getCouponData( $id ),
			PlatformService::TRIGGER_NEW_PRODUCT_VARIATION,
			PlatformService::TRIGGER_UPDATED_PRODUCT_VARIATION,
			PlatformService::TRIGGER_DELETED_PRODUCT_VARIATION => $this->getProductVariationData( $id ),
			default => [ 'id' => $id ],
		};
	}

	private function getCouponData( $coupon_id ) {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return [ 'id' => (int) $coupon_id ];
		}

		$coupon = new \WC_Coupon( $coupon_id );
		if ( ! $coupon || ! method_exists( $coupon, 'get_data' ) ) {
			return [ 'id' => (int) $coupon_id ];
		}

		return $coupon->get_data();
	}

	private function getProductVariationData( $variation_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return [ 'id' => (int) $variation_id ];
		}

		$variation = wc_get_product( $variation_id );
		if ( ! $variation || ! is_object( $variation ) || ! method_exists( $variation, 'get_data' ) ) {
			return [ 'id' => (int) $variation_id ];
		}

		$data = $variation->get_data();
		if ( method_exists( $variation, 'get_parent_id' ) ) {
			$data['product_id'] = (int) $variation->get_parent_id();
		}

		return $data;
	}

	private function getOrderData( $order_id, $order = null ) {
		if ( ! $order && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
		}

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

	private function getProductData( $product_id ) {
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

	private function getWpUserData( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [ 'id' => (int) $user_id ];
		}

		return [
			'id'           => (int) $user->ID,
			'user_login'   => (string) $user->user_login,
			'user_email'   => (string) $user->user_email,
			'display_name' => (string) $user->display_name,
			'user_nicename'=> (string) $user->user_nicename,
			'roles'        => array_values( (array) $user->roles ),
		];
	}
}