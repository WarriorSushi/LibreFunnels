<?php
/**
 * Checkout product assignment value handling.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Checkout;

use LibreFunnels\Domain\Registered_Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Reads checkout product assignments from step metadata.
 */
final class Product_Assignment {
	/**
	 * Gets product assignments for a checkout step.
	 *
	 * @param int $step_id Step ID.
	 * @return array<int,array<string,int>>
	 */
	public function get_products_for_step( $step_id ) {
		$step_id = absint( $step_id );

		if ( 0 === $step_id ) {
			return array();
		}

		return Registered_Meta::sanitize_checkout_products(
			get_post_meta( $step_id, LIBREFUNNELS_CHECKOUT_PRODUCTS_META, true )
		);
	}
}
