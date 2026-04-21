<?php
/**
 * Offer discount calculator.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Offers;

use LibreFunnels\Domain\Registered_Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates safe offer prices without touching WooCommerce totals directly.
 */
final class Discount_Calculator {
	/**
	 * Calculates a discounted unit price.
	 *
	 * @param float|int|string $price Unit price before the bump discount.
	 * @param string           $type  Discount type.
	 * @param float|int|string $amount Discount amount.
	 * @return float
	 */
	public function calculate_price( $price, $type, $amount ) {
		$price  = max( 0.0, (float) $price );
		$type   = Registered_Meta::sanitize_discount_type( $type );
		$amount = max( 0.0, (float) $amount );

		if ( 'percentage' === $type ) {
			return max( 0.0, $price - ( $price * min( 100.0, $amount ) / 100 ) );
		}

		if ( 'fixed' === $type ) {
			return max( 0.0, $price - $amount );
		}

		return $price;
	}
}
