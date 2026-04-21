<?php
/**
 * Step offer repository.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Offers;

use LibreFunnels\Domain\Registered_Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Reads primary product offers from step metadata.
 */
final class Step_Offer_Repository {
	/**
	 * Gets the primary offer for a step.
	 *
	 * @param int $step_id Step ID.
	 * @return array<string,mixed>
	 */
	public function get_offer_for_step( $step_id ) {
		$step_id = absint( $step_id );

		if ( 0 === $step_id ) {
			return Registered_Meta::sanitize_step_offer( array() );
		}

		return Registered_Meta::sanitize_step_offer(
			get_post_meta( $step_id, LIBREFUNNELS_STEP_OFFER_META, true )
		);
	}
}
