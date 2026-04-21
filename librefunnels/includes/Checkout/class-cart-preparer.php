<?php
/**
 * Checkout cart preparation.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Prepares the WooCommerce cart for a funnel checkout step.
 */
final class Cart_Preparer {
	/**
	 * Product assignment reader.
	 *
	 * @var Product_Assignment
	 */
	private $product_assignment;

	/**
	 * Creates the cart preparer.
	 *
	 * @param Product_Assignment|null $product_assignment Optional product assignment reader.
	 */
	public function __construct( Product_Assignment $product_assignment = null ) {
		$this->product_assignment = $product_assignment ? $product_assignment : new Product_Assignment();
	}

	/**
	 * Ensures assigned products are present in the current WooCommerce cart.
	 *
	 * This intentionally does not empty the cart yet. Global checkout takeover and
	 * cart replacement rules belong to a later, explicit checkout-control slice.
	 *
	 * @param int $step_id Checkout step ID.
	 * @return true|\WP_Error
	 */
	public function prepare_for_step( $step_id ) {
		if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_product' ) ) {
			return new \WP_Error( 'woocommerce_unavailable', __( 'WooCommerce cart services are not available.', 'librefunnels' ) );
		}

		$woocommerce = WC();

		if ( ! $woocommerce || ! $woocommerce->cart ) {
			return new \WP_Error( 'cart_unavailable', __( 'The WooCommerce cart is not available.', 'librefunnels' ) );
		}

		foreach ( $this->product_assignment->get_products_for_step( $step_id ) as $assignment ) {
			$product_id   = absint( $assignment['product_id'] );
			$variation_id = absint( $assignment['variation_id'] );
			$quantity     = max( 1, absint( $assignment['quantity'] ) );
			$variation    = isset( $assignment['variation'] ) && is_array( $assignment['variation'] ) ? $assignment['variation'] : array();
			$product      = wc_get_product( $variation_id ? $variation_id : $product_id );

			if ( ! $product || ! $product->is_purchasable() ) {
				return new \WP_Error( 'product_not_purchasable', __( 'A product assigned to this checkout is not purchasable.', 'librefunnels' ) );
			}

			if ( $this->cart_contains_product( $product_id, $variation_id ) ) {
				continue;
			}

			$cart_item_key = $woocommerce->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );

			if ( ! $cart_item_key ) {
				return new \WP_Error( 'add_to_cart_failed', __( 'LibreFunnels could not add an assigned product to the cart.', 'librefunnels' ) );
			}
		}

		$coupon_result = $this->apply_step_coupons( $step_id );

		if ( is_wp_error( $coupon_result ) ) {
			return $coupon_result;
		}

		return true;
	}

	/**
	 * Applies checkout coupons configured for a step.
	 *
	 * @param int $step_id Step ID.
	 * @return true|\WP_Error
	 */
	private function apply_step_coupons( $step_id ) {
		$woocommerce = WC();
		$coupons     = \LibreFunnels\Domain\Registered_Meta::sanitize_coupon_codes(
			get_post_meta( absint( $step_id ), LIBREFUNNELS_CHECKOUT_COUPONS_META, true )
		);

		foreach ( $coupons as $coupon_code ) {
			if ( method_exists( $woocommerce->cart, 'has_discount' ) && $woocommerce->cart->has_discount( $coupon_code ) ) {
				continue;
			}

			if ( ! $woocommerce->cart->apply_coupon( $coupon_code ) ) {
				return new \WP_Error( 'coupon_apply_failed', __( 'LibreFunnels could not apply a checkout coupon.', 'librefunnels' ) );
			}
		}

		return true;
	}

	/**
	 * Checks whether a product assignment is already in the cart.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return bool
	 */
	private function cart_contains_product( $product_id, $variation_id ) {
		$woocommerce = WC();

		foreach ( $woocommerce->cart->get_cart() as $cart_item ) {
			$cart_product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$cart_variation_id = isset( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : 0;

			if ( absint( $product_id ) === $cart_product_id && absint( $variation_id ) === $cart_variation_id ) {
				return true;
			}
		}

		return false;
	}
}
