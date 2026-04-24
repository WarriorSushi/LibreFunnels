<?php
/**
 * WooCommerce rule facts.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Collects WooCommerce cart/customer facts for rule evaluation.
 */
final class WooCommerce_Fact_Collector {
	/**
	 * Optional injected cart object.
	 *
	 * @var object|null
	 */
	private $cart;

	/**
	 * Optional logged-in resolver.
	 *
	 * @var callable|null
	 */
	private $logged_in_resolver;

	/**
	 * Optional injected order object.
	 *
	 * @var object|null
	 */
	private $order;

	/**
	 * Creates the collector.
	 *
	 * @param object|null   $cart               Optional cart-like object.
	 * @param callable|null $logged_in_resolver Optional logged-in resolver.
	 * @param object|null   $order              Optional order-like object.
	 */
	public function __construct( $cart = null, $logged_in_resolver = null, $order = null ) {
		$this->cart               = $cart;
		$this->logged_in_resolver = is_callable( $logged_in_resolver ) ? $logged_in_resolver : null;
		$this->order              = $order;
	}

	/**
	 * Collects facts for the current cart/customer.
	 *
	 * @param array<string,mixed> $context Optional runtime context.
	 * @return array<string,mixed>
	 */
	public function collect( array $context = array() ) {
		$cart        = $this->get_cart();
		$order_facts = $this->get_order_facts( $context );

		if ( ! $cart ) {
			return array_merge(
				array(
					'cart_product_ids'   => array(),
					'cart_variation_ids' => array(),
					'cart_subtotal'      => 0.0,
					'cart_item_count'    => 0,
					'customer_logged_in' => $this->is_customer_logged_in(),
				),
				$order_facts
			);
		}

		$cart_items = method_exists( $cart, 'get_cart' ) ? $cart->get_cart() : array();

		return array_merge(
			array(
				'cart_product_ids'   => $this->get_product_ids_from_items( $cart_items ),
				'cart_variation_ids' => $this->get_variation_ids_from_items( $cart_items ),
				'cart_subtotal'      => $this->get_cart_subtotal( $cart ),
				'cart_item_count'    => method_exists( $cart, 'get_cart_contents_count' ) ? absint( $cart->get_cart_contents_count() ) : count( $cart_items ),
				'customer_logged_in' => $this->is_customer_logged_in(),
			),
			$order_facts
		);
	}

	/**
	 * Gets product IDs from cart items.
	 *
	 * @param array<int|string,array<string,mixed>> $cart_items Cart items.
	 * @return int[]
	 */
	private function get_product_ids_from_items( array $cart_items ) {
		$product_ids = array();

		foreach ( $cart_items as $cart_item ) {
			if ( is_object( $cart_item ) && method_exists( $cart_item, 'get_product_id' ) ) {
				$product_ids[] = absint( $cart_item->get_product_id() );
				continue;
			}

			if ( isset( $cart_item['product_id'] ) ) {
				$product_ids[] = absint( $cart_item['product_id'] );
			}
		}

		return array_values( array_unique( array_filter( $product_ids ) ) );
	}

	/**
	 * Gets variation IDs from cart items.
	 *
	 * @param array<int|string,array<string,mixed>> $cart_items Cart items.
	 * @return int[]
	 */
	private function get_variation_ids_from_items( array $cart_items ) {
		$variation_ids = array();

		foreach ( $cart_items as $cart_item ) {
			if ( is_object( $cart_item ) && method_exists( $cart_item, 'get_variation_id' ) ) {
				$variation_ids[] = absint( $cart_item->get_variation_id() );
				continue;
			}

			if ( isset( $cart_item['variation_id'] ) ) {
				$variation_ids[] = absint( $cart_item['variation_id'] );
			}
		}

		return array_values( array_unique( array_filter( $variation_ids ) ) );
	}

	/**
	 * Gets cart subtotal.
	 *
	 * @param object $cart Cart object.
	 * @return float
	 */
	private function get_cart_subtotal( $cart ) {
		if ( method_exists( $cart, 'get_subtotal' ) ) {
			return (float) $cart->get_subtotal();
		}

		return 0.0;
	}

	/**
	 * Gets the current WooCommerce cart.
	 *
	 * @return object|null
	 */
	private function get_cart() {
		if ( $this->cart ) {
			return $this->cart;
		}

		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$woocommerce = WC();

		if ( ! $woocommerce || empty( $woocommerce->cart ) ) {
			return null;
		}

		return $woocommerce->cart;
	}

	/**
	 * Gets order facts from an injected order or supplied order ID context.
	 *
	 * @param array<string,mixed> $context Optional runtime context.
	 * @return array<string,mixed>
	 */
	private function get_order_facts( array $context ) {
		$order = $this->get_order( $context );

		if ( ! $order ) {
			return array(
				'order_id'             => 0,
				'order_product_ids'    => array(),
				'order_variation_ids'  => array(),
				'order_total'          => 0.0,
				'order_subtotal'       => 0.0,
				'order_item_count'     => 0,
				'order_status'         => '',
				'order_payment_method' => '',
				'order_currency'       => '',
				'customer_id'          => 0,
			);
		}

		$order_items = method_exists( $order, 'get_items' ) ? $order->get_items( 'line_item' ) : array();

		return array(
			'order_id'             => method_exists( $order, 'get_id' ) ? absint( $order->get_id() ) : 0,
			'order_product_ids'    => $this->get_product_ids_from_items( $order_items ),
			'order_variation_ids'  => $this->get_variation_ids_from_items( $order_items ),
			'order_total'          => method_exists( $order, 'get_total' ) ? (float) $order->get_total( 'edit' ) : 0.0,
			'order_subtotal'       => method_exists( $order, 'get_subtotal' ) ? (float) $order->get_subtotal() : 0.0,
			'order_item_count'     => method_exists( $order, 'get_item_count' ) ? absint( $order->get_item_count() ) : count( $order_items ),
			'order_status'         => method_exists( $order, 'get_status' ) ? sanitize_key( (string) $order->get_status() ) : '',
			'order_payment_method' => method_exists( $order, 'get_payment_method' ) ? sanitize_key( (string) $order->get_payment_method() ) : '',
			'order_currency'       => method_exists( $order, 'get_currency' ) ? sanitize_text_field( (string) $order->get_currency() ) : '',
			'customer_id'          => method_exists( $order, 'get_customer_id' ) ? absint( $order->get_customer_id() ) : 0,
		);
	}

	/**
	 * Gets an order-like object.
	 *
	 * @param array<string,mixed> $context Optional runtime context.
	 * @return object|null
	 */
	private function get_order( array $context ) {
		if ( $this->order ) {
			return $this->order;
		}

		$order_id = isset( $context['order_id'] ) ? absint( $context['order_id'] ) : 0;

		if ( $order_id < 1 && function_exists( 'WC' ) ) {
			$woocommerce = WC();

			if ( $woocommerce && ! empty( $woocommerce->session ) && method_exists( $woocommerce->session, 'get' ) ) {
				$order_id = absint( $woocommerce->session->get( 'order_awaiting_payment', 0 ) );
			}
		}

		if ( $order_id < 1 || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		return is_object( $order ) ? $order : null;
	}

	/**
	 * Checks whether the current customer is logged in.
	 *
	 * @return bool
	 */
	private function is_customer_logged_in() {
		if ( is_callable( $this->logged_in_resolver ) ) {
			return (bool) call_user_func( $this->logged_in_resolver );
		}

		return function_exists( 'is_user_logged_in' ) ? (bool) is_user_logged_in() : false;
	}
}
