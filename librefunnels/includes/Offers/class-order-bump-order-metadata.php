<?php
/**
 * Order bump order metadata.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Offers;

defined( 'ABSPATH' ) || exit;

/**
 * Copies order bump attribution from cart items to WooCommerce order items.
 */
final class Order_Bump_Order_Metadata {
	/**
	 * Registers WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_line_item_metadata' ), 10, 4 );
	}

	/**
	 * Adds LibreFunnels metadata to order bump line items.
	 *
	 * @param \WC_Order_Item_Product $item          Order line item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array<string,mixed>    $values        Cart item values.
	 * @param \WC_Order              $order         Order object.
	 * @return void
	 */
	public function add_line_item_metadata( $item, $cart_item_key, $values, $order ) {
		unset( $cart_item_key, $order );

		if ( ! is_object( $item ) || ! method_exists( $item, 'add_meta_data' ) ) {
			return;
		}

		if ( ! empty( $values['librefunnels_order_bump'] ) ) {
			$item->add_meta_data( '_librefunnels_order_bump', 'yes', true );
			$item->add_meta_data( '_librefunnels_order_bump_id', $this->get_string_value( $values, 'librefunnels_order_bump_id' ), true );
			$item->add_meta_data( '_librefunnels_order_bump_step_id', $this->get_int_value( $values, 'librefunnels_order_bump_step_id' ), true );
			$this->add_discount_metadata( $item, '_librefunnels_order_bump', $values );
			return;
		}

		if ( ! empty( $values['librefunnels_pre_checkout_offer'] ) ) {
			$item->add_meta_data( '_librefunnels_pre_checkout_offer', 'yes', true );
			$item->add_meta_data( '_librefunnels_offer_id', $this->get_string_value( $values, 'librefunnels_offer_id' ), true );
			$item->add_meta_data( '_librefunnels_offer_step_id', $this->get_int_value( $values, 'librefunnels_offer_step_id' ), true );
			$this->add_discount_metadata( $item, '_librefunnels_offer', $values );
		}
	}

	/**
	 * Adds common discount metadata to an order line item.
	 *
	 * @param object              $item   Order line item.
	 * @param string              $prefix Metadata prefix.
	 * @param array<string,mixed> $values Cart item values.
	 * @return void
	 */
	private function add_discount_metadata( $item, $prefix, array $values ) {
		$item->add_meta_data( $prefix . '_discount_type', $this->get_string_value( $values, 'librefunnels_discount_type' ), true );
		$item->add_meta_data( $prefix . '_discount_amount', $this->get_float_value( $values, 'librefunnels_discount_amount' ), true );
		$item->add_meta_data( $prefix . '_original_price', $this->get_float_value( $values, 'librefunnels_original_price' ), true );
	}

	/**
	 * Gets a sanitized string value from cart item data.
	 *
	 * @param array<string,mixed> $values Cart item values.
	 * @param string              $key    Value key.
	 * @return string
	 */
	private function get_string_value( array $values, $key ) {
		return isset( $values[ $key ] ) ? sanitize_key( (string) $values[ $key ] ) : '';
	}

	/**
	 * Gets an absolute integer value from cart item data.
	 *
	 * @param array<string,mixed> $values Cart item values.
	 * @param string              $key    Value key.
	 * @return int
	 */
	private function get_int_value( array $values, $key ) {
		return isset( $values[ $key ] ) ? absint( $values[ $key ] ) : 0;
	}

	/**
	 * Gets a non-negative float value from cart item data.
	 *
	 * @param array<string,mixed> $values Cart item values.
	 * @param string              $key    Value key.
	 * @return float
	 */
	private function get_float_value( array $values, $key ) {
		return isset( $values[ $key ] ) ? max( 0.0, (float) $values[ $key ] ) : 0.0;
	}
}
