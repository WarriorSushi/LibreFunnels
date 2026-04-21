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
	 * Creates the collector.
	 *
	 * @param object|null   $cart               Optional cart-like object.
	 * @param callable|null $logged_in_resolver Optional logged-in resolver.
	 */
	public function __construct( $cart = null, $logged_in_resolver = null ) {
		$this->cart               = $cart;
		$this->logged_in_resolver = is_callable( $logged_in_resolver ) ? $logged_in_resolver : null;
	}

	/**
	 * Collects facts for the current cart/customer.
	 *
	 * @return array<string,mixed>
	 */
	public function collect() {
		$cart = $this->get_cart();

		if ( ! $cart ) {
			return array(
				'cart_product_ids'   => array(),
				'cart_variation_ids' => array(),
				'cart_subtotal'      => 0.0,
				'cart_item_count'    => 0,
				'customer_logged_in' => $this->is_customer_logged_in(),
			);
		}

		$cart_items = method_exists( $cart, 'get_cart' ) ? $cart->get_cart() : array();

		return array(
			'cart_product_ids'   => $this->get_cart_product_ids( $cart_items ),
			'cart_variation_ids' => $this->get_cart_variation_ids( $cart_items ),
			'cart_subtotal'      => $this->get_cart_subtotal( $cart ),
			'cart_item_count'    => method_exists( $cart, 'get_cart_contents_count' ) ? absint( $cart->get_cart_contents_count() ) : count( $cart_items ),
			'customer_logged_in' => $this->is_customer_logged_in(),
		);
	}

	/**
	 * Gets product IDs from cart items.
	 *
	 * @param array<int|string,array<string,mixed>> $cart_items Cart items.
	 * @return int[]
	 */
	private function get_cart_product_ids( array $cart_items ) {
		$product_ids = array();

		foreach ( $cart_items as $cart_item ) {
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
	private function get_cart_variation_ids( array $cart_items ) {
		$variation_ids = array();

		foreach ( $cart_items as $cart_item ) {
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
