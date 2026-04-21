<?php
/**
 * Order bump repository.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Offers;

use LibreFunnels\Domain\Registered_Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Reads order bump definitions from funnel step metadata.
 */
final class Order_Bump_Repository {
	/**
	 * Gets all configured order bumps for a step.
	 *
	 * @param int $step_id Step ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_bumps_for_step( $step_id ) {
		$step_id = absint( $step_id );

		if ( 0 === $step_id ) {
			return array();
		}

		return Registered_Meta::sanitize_order_bumps(
			get_post_meta( $step_id, LIBREFUNNELS_ORDER_BUMPS_META, true )
		);
	}

	/**
	 * Gets enabled order bumps for a step.
	 *
	 * @param int $step_id Step ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_enabled_bumps_for_step( $step_id ) {
		$enabled = array();

		foreach ( $this->get_bumps_for_step( $step_id ) as $bump ) {
			if ( ! empty( $bump['enabled'] ) ) {
				$enabled[] = $bump;
			}
		}

		return $enabled;
	}
}
