<?php
/**
 * Analytics event hook recorder.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Records local funnel analytics events from internal hooks.
 */
final class Event_Recorder {
	/**
	 * Event store.
	 *
	 * @var Event_Store
	 */
	private $store;

	/**
	 * Creates the recorder.
	 *
	 * @param Event_Store|null $store Optional event store.
	 */
	public function __construct( Event_Store $store = null ) {
		$this->store = $store ? $store : new Event_Store();
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'librefunnels_offer_impression', array( $this, 'record_offer_impression' ), 10, 3 );
		add_action( 'librefunnels_offer_action_recorded', array( $this, 'record_offer_action' ), 10, 4 );
	}

	/**
	 * Records an offer impression.
	 *
	 * @param int                 $funnel_id Funnel ID.
	 * @param int                 $step_id   Step ID.
	 * @param array<string,mixed> $offer     Offer data.
	 * @return void
	 */
	public function record_offer_impression( $funnel_id, $step_id, array $offer ) {
		$this->store->record(
			array(
				'event_type'  => 'offer_impression',
				'funnel_id'   => absint( $funnel_id ),
				'step_id'     => absint( $step_id ),
				'object_type' => 'offer',
				'object_id'   => isset( $offer['id'] ) ? $offer['id'] : '',
				'context'     => array(
					'product_id'    => isset( $offer['product_id'] ) ? absint( $offer['product_id'] ) : 0,
					'discount_type' => isset( $offer['discount_type'] ) ? sanitize_key( (string) $offer['discount_type'] ) : 'none',
				),
			)
		);
	}

	/**
	 * Records an offer action.
	 *
	 * @param int                 $funnel_id Funnel ID.
	 * @param int                 $step_id   Step ID.
	 * @param string              $action    Offer action.
	 * @param array<string,mixed> $offer     Offer data.
	 * @return void
	 */
	public function record_offer_action( $funnel_id, $step_id, $action, array $offer ) {
		$action = sanitize_key( $action );

		if ( ! in_array( $action, array( 'accept', 'reject' ), true ) ) {
			return;
		}

		$this->store->record(
			array(
				'event_type'  => 'offer_' . $action,
				'funnel_id'   => absint( $funnel_id ),
				'step_id'     => absint( $step_id ),
				'route'       => $action,
				'object_type' => 'offer',
				'object_id'   => isset( $offer['id'] ) ? $offer['id'] : '',
				'value'       => isset( $offer['discount_amount'] ) ? (float) $offer['discount_amount'] : 0.0,
				'currency'    => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
				'context'     => array(
					'product_id'    => isset( $offer['product_id'] ) ? absint( $offer['product_id'] ) : 0,
					'discount_type' => isset( $offer['discount_type'] ) ? sanitize_key( (string) $offer['discount_type'] ) : 'none',
				),
			)
		);
	}
}
