<?php
/**
 * Offer child order factory.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Payments;

use LibreFunnels\Offers\Discount_Calculator;

defined( 'ABSPATH' ) || exit;

/**
 * Creates HPOS-compatible child orders for post-purchase offers.
 */
final class Offer_Child_Order_Factory {
	/**
	 * Discount calculator.
	 *
	 * @var Discount_Calculator
	 */
	private $discount_calculator;

	/**
	 * Optional order creator.
	 *
	 * @var callable|null
	 */
	private $order_creator;

	/**
	 * Creates the factory.
	 *
	 * @param Discount_Calculator|null $discount_calculator Optional calculator.
	 * @param callable|null            $order_creator       Optional order creator.
	 */
	public function __construct( Discount_Calculator $discount_calculator = null, $order_creator = null ) {
		$this->discount_calculator = $discount_calculator ? $discount_calculator : new Discount_Calculator();
		$this->order_creator       = is_callable( $order_creator ) ? $order_creator : null;
	}

	/**
	 * Creates a child order for an accepted offer.
	 *
	 * @param object              $parent_order Parent WooCommerce order.
	 * @param array<string,mixed> $offer        Offer data.
	 * @param array<string,mixed> $context      Runtime context.
	 * @return Payment_Result
	 */
	public function create_child_order( $parent_order, array $offer, array $context = array() ) {
		if ( ! is_object( $parent_order ) || ! method_exists( $parent_order, 'get_id' ) ) {
			return Payment_Result::failure(
				'parent_order_missing',
				__( 'LibreFunnels could not create the offer order because the original order is missing.', 'librefunnels' )
			);
		}

		$product = $this->get_offer_product( $offer );

		if ( ! $product ) {
			return Payment_Result::failure(
				'offer_product_not_found',
				__( 'LibreFunnels could not create the offer order because the offer product was not found.', 'librefunnels' )
			);
		}

		$child_order = $this->create_order( $parent_order );

		if ( is_wp_error( $child_order ) ) {
			return Payment_Result::failure( $child_order->get_error_code(), $child_order->get_error_message() );
		}

		if ( ! is_object( $child_order ) ) {
			return Payment_Result::failure(
				'child_order_creation_failed',
				__( 'WooCommerce could not create a child order for this offer.', 'librefunnels' )
			);
		}

		$this->copy_order_context( $parent_order, $child_order );
		$this->add_offer_product( $child_order, $product, $offer );
		$this->mark_child_order( $child_order, $parent_order, $offer, $context );

		if ( method_exists( $child_order, 'calculate_totals' ) ) {
			$child_order->calculate_totals();
		}

		if ( method_exists( $child_order, 'save' ) ) {
			$child_order->save();
		}

		return Payment_Result::success(
			'child_order_created',
			__( 'LibreFunnels created a child order for the accepted offer.', 'librefunnels' ),
			array(
				'child_order'     => $child_order,
				'child_order_id'  => method_exists( $child_order, 'get_id' ) ? absint( $child_order->get_id() ) : 0,
				'parent_order_id' => absint( $parent_order->get_id() ),
			)
		);
	}

	/**
	 * Gets the offer product.
	 *
	 * @param array<string,mixed> $offer Offer data.
	 * @return object|null
	 */
	private function get_offer_product( array $offer ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product_id   = isset( $offer['product_id'] ) ? absint( $offer['product_id'] ) : 0;
		$variation_id = isset( $offer['variation_id'] ) ? absint( $offer['variation_id'] ) : 0;
		$lookup_id    = $variation_id ? $variation_id : $product_id;

		if ( $lookup_id < 1 ) {
			return null;
		}

		$product = wc_get_product( $lookup_id );

		return is_object( $product ) ? $product : null;
	}

	/**
	 * Creates an empty WooCommerce order.
	 *
	 * @param object $parent_order Parent order.
	 * @return object|\WP_Error|null
	 */
	private function create_order( $parent_order ) {
		$args = array();

		if ( method_exists( $parent_order, 'get_customer_id' ) ) {
			$args['customer_id'] = absint( $parent_order->get_customer_id() );
		}

		if ( is_callable( $this->order_creator ) ) {
			return call_user_func( $this->order_creator, $args );
		}

		if ( function_exists( 'wc_create_order' ) ) {
			return wc_create_order( $args );
		}

		return null;
	}

	/**
	 * Copies safe order context from the parent.
	 *
	 * @param object $parent_order Parent order.
	 * @param object $child_order  Child order.
	 * @return void
	 */
	private function copy_order_context( $parent_order, $child_order ) {
		if ( method_exists( $child_order, 'set_parent_id' ) && method_exists( $parent_order, 'get_id' ) ) {
			$child_order->set_parent_id( absint( $parent_order->get_id() ) );
		}

		if ( method_exists( $child_order, 'set_customer_id' ) && method_exists( $parent_order, 'get_customer_id' ) ) {
			$child_order->set_customer_id( absint( $parent_order->get_customer_id() ) );
		}

		if ( method_exists( $child_order, 'set_currency' ) && method_exists( $parent_order, 'get_currency' ) ) {
			$child_order->set_currency( sanitize_text_field( (string) $parent_order->get_currency() ) );
		}

		$this->copy_address( $parent_order, $child_order, 'billing' );
		$this->copy_address( $parent_order, $child_order, 'shipping' );
	}

	/**
	 * Copies address data through CRUD setters when available.
	 *
	 * @param object $parent_order Parent order.
	 * @param object $child_order  Child order.
	 * @param string $type         Address type.
	 * @return void
	 */
	private function copy_address( $parent_order, $child_order, $type ) {
		$getter = 'get_' . sanitize_key( $type );
		$setter = 'set_' . sanitize_key( $type );

		if ( method_exists( $parent_order, $getter ) && method_exists( $child_order, $setter ) ) {
			$address = $parent_order->{$getter}();

			if ( is_array( $address ) ) {
				$child_order->{$setter}( $address );
			}
		}
	}

	/**
	 * Adds the accepted offer product to the child order.
	 *
	 * @param object              $child_order Child order.
	 * @param object              $product     Product object.
	 * @param array<string,mixed> $offer       Offer data.
	 * @return void
	 */
	private function add_offer_product( $child_order, $product, array $offer ) {
		if ( ! method_exists( $child_order, 'add_product' ) ) {
			return;
		}

		$quantity       = isset( $offer['quantity'] ) ? max( 1, absint( $offer['quantity'] ) ) : 1;
		$original_price = method_exists( $product, 'get_price' ) ? (float) $product->get_price( 'edit' ) : 0.0;
		$unit_price     = $this->discount_calculator->calculate_price(
			$original_price,
			isset( $offer['discount_type'] ) ? $offer['discount_type'] : 'none',
			isset( $offer['discount_amount'] ) ? $offer['discount_amount'] : 0
		);

		$child_order->add_product(
			$product,
			$quantity,
			array(
				'subtotal' => $original_price * $quantity,
				'total'    => $unit_price * $quantity,
			)
		);
	}

	/**
	 * Adds LibreFunnels metadata to the child order.
	 *
	 * @param object              $child_order  Child order.
	 * @param object              $parent_order Parent order.
	 * @param array<string,mixed> $offer        Offer data.
	 * @param array<string,mixed> $context      Runtime context.
	 * @return void
	 */
	private function mark_child_order( $child_order, $parent_order, array $offer, array $context ) {
		if ( ! method_exists( $child_order, 'update_meta_data' ) ) {
			return;
		}

		$child_order->update_meta_data( '_librefunnels_offer_child_order', 'yes' );
		$child_order->update_meta_data( '_librefunnels_parent_order_id', absint( $parent_order->get_id() ) );
		$child_order->update_meta_data( '_librefunnels_offer_id', isset( $offer['id'] ) ? sanitize_key( (string) $offer['id'] ) : '' );
		$child_order->update_meta_data( '_librefunnels_offer_step_id', isset( $context['step_id'] ) ? absint( $context['step_id'] ) : 0 );
		$child_order->update_meta_data( '_librefunnels_funnel_id', isset( $context['funnel_id'] ) ? absint( $context['funnel_id'] ) : 0 );
		$child_order->update_meta_data( '_librefunnels_offer_payment_status', 'pending' );
	}
}
