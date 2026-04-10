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
		add_action( 'woocommerce_new_order', [ $this, 'action_woocommerce_new_order' ], 20, 2 );
		add_action( 'woocommerce_update_order', [ $this, 'action_woocommerce_update_order' ], 20, 2 );
		add_action( 'woocommerce_update_product', [ $this, 'action_woocommerce_update_product' ], 20, 1 );
		add_action( 'shutdown', [ $this, 'action_shutdown_dispatch_queued_triggers' ], 999 );
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

	public function action_woocommerce_update_product( $product_id ) {
		$product_id = (int) $product_id;
		$this->queueTrigger(
			PlatformService::TRIGGER_UPDATED_PRODUCT,
			$product_id,
			'woocommerce_update_product',
			[ 'id' => $product_id ]
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
			PlatformService::TRIGGER_NEW_ORDER,
			PlatformService::TRIGGER_UPDATED_ORDER => $this->getOrderData( $id ),
			PlatformService::TRIGGER_UPDATED_PRODUCT => $this->getProductData( $id ),
			default => [ 'id' => $id ],
		};
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