<?php
/**
 * Offer eligibility checks.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Offers;

use LibreFunnels\Routing\Routing_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Validates whether an offer can be presented before cart or order mutation.
 */
final class Offer_Eligibility {
	/**
	 * Checks whether an offer references a purchasable WooCommerce product.
	 *
	 * @param array<string,mixed> $offer Offer data.
	 * @return Routing_Result
	 */
	public function is_product_offer_purchasable( array $offer ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return Routing_Result::failure(
				'woocommerce_unavailable',
				__( 'WooCommerce product services are not available.', 'librefunnels' )
			);
		}

		$product_id   = isset( $offer['product_id'] ) ? absint( $offer['product_id'] ) : 0;
		$variation_id = isset( $offer['variation_id'] ) ? absint( $offer['variation_id'] ) : 0;
		$lookup_id    = $variation_id ? $variation_id : $product_id;

		if ( 0 === $product_id || 0 === $lookup_id ) {
			return Routing_Result::failure(
				'offer_product_missing',
				__( 'This offer does not reference a product.', 'librefunnels' )
			);
		}

		$product = wc_get_product( $lookup_id );

		if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'is_purchasable' ) ) {
			return Routing_Result::failure(
				'offer_product_not_found',
				__( 'This offer product could not be found.', 'librefunnels' )
			);
		}

		if ( ! $product->is_purchasable() ) {
			return Routing_Result::failure(
				'offer_product_not_purchasable',
				__( 'This offer product is not purchasable.', 'librefunnels' )
			);
		}

		return Routing_Result::success(
			$lookup_id,
			'offer_product_purchasable',
			__( 'This offer product is purchasable.', 'librefunnels' )
		);
	}
}
