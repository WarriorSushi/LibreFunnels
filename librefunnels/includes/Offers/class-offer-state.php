<?php
/**
 * Offer state storage.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Offers;

defined( 'ABSPATH' ) || exit;

/**
 * Stores customer-scoped offer action state in the WooCommerce session.
 */
final class Offer_State {
	/**
	 * WooCommerce session key.
	 */
	const SESSION_KEY = 'librefunnels_offer_actions';

	/**
	 * Optional injected session object.
	 *
	 * @var object|null
	 */
	private $session;

	/**
	 * Creates the state store.
	 *
	 * @param object|null $session Optional session-like object with get/set methods.
	 */
	public function __construct( $session = null ) {
		$this->session = $session;
	}

	/**
	 * Records an offer action for a step.
	 *
	 * @param int    $step_id  Step ID.
	 * @param string $offer_id Offer ID.
	 * @param string $action   Action. Accepts accept or reject.
	 * @return bool
	 */
	public function record_action( $step_id, $offer_id, $action ) {
		$session = $this->get_session();
		$step_id = absint( $step_id );
		$action  = sanitize_key( $action );

		if ( ! $session || 0 === $step_id || ! in_array( $action, array( 'accept', 'reject' ), true ) ) {
			return false;
		}

		$state             = $this->get_state();
		$state[ $step_id ] = array(
			'offer_id'    => sanitize_key( (string) $offer_id ),
			'action'      => $action,
			'recorded_at' => time(),
		);

		$session->set( self::SESSION_KEY, $state );

		return true;
	}

	/**
	 * Gets the recorded action for a step.
	 *
	 * @param int $step_id Step ID.
	 * @return array<string,mixed>|null
	 */
	public function get_action( $step_id ) {
		$state   = $this->get_state();
		$step_id = absint( $step_id );

		if ( 0 === $step_id || ! isset( $state[ $step_id ] ) || ! is_array( $state[ $step_id ] ) ) {
			return null;
		}

		return array(
			'offer_id'    => isset( $state[ $step_id ]['offer_id'] ) ? sanitize_key( (string) $state[ $step_id ]['offer_id'] ) : '',
			'action'      => isset( $state[ $step_id ]['action'] ) ? sanitize_key( (string) $state[ $step_id ]['action'] ) : '',
			'recorded_at' => isset( $state[ $step_id ]['recorded_at'] ) ? absint( $state[ $step_id ]['recorded_at'] ) : 0,
		);
	}

	/**
	 * Checks whether a step already has an action recorded.
	 *
	 * @param int         $step_id Step ID.
	 * @param string|null $action  Optional action to match.
	 * @return bool
	 */
	public function has_action( $step_id, $action = null ) {
		$record = $this->get_action( $step_id );

		if ( null === $record ) {
			return false;
		}

		if ( null === $action ) {
			return true;
		}

		return sanitize_key( $action ) === $record['action'];
	}

	/**
	 * Gets all offer action state.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_state() {
		$session = $this->get_session();

		if ( ! $session ) {
			return array();
		}

		$state = $session->get( self::SESSION_KEY, array() );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Gets a session-like object.
	 *
	 * @return object|null
	 */
	private function get_session() {
		if ( $this->session && method_exists( $this->session, 'get' ) && method_exists( $this->session, 'set' ) ) {
			return $this->session;
		}

		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$woocommerce = WC();

		if ( ! $woocommerce || empty( $woocommerce->session ) || ! method_exists( $woocommerce->session, 'get' ) || ! method_exists( $woocommerce->session, 'set' ) ) {
			return null;
		}

		return $woocommerce->session;
	}
}
