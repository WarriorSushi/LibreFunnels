<?php
/**
 * Mock payment adapter.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Test adapter used for deterministic post-purchase offer behavior.
 */
final class Mock_Adapter implements Payment_Adapter_Interface {
	/**
	 * Gets the adapter ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'mock';
	}

	/**
	 * Gets the adapter label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'LibreFunnels mock gateway', 'librefunnels' );
	}

	/**
	 * Whether this adapter handles a gateway.
	 *
	 * @param string      $gateway_id Gateway ID.
	 * @param object|null $gateway    Gateway object when available.
	 * @return bool
	 */
	public function supports_gateway( $gateway_id, $gateway = null ) {
		unset( $gateway );

		return in_array( sanitize_key( $gateway_id ), array( 'librefunnels_mock', 'librefunnels_test', 'mock' ), true );
	}

	/**
	 * Gets supported capabilities.
	 *
	 * @param string      $gateway_id Gateway ID.
	 * @param object|null $order      WooCommerce order object.
	 * @return array<string,bool>
	 */
	public function get_capabilities( $gateway_id = '', $order = null ) {
		unset( $gateway_id, $order );

		return array(
			'one_click'          => true,
			'accept_confirm'     => true,
			'child_orders'       => true,
			'refunds'            => true,
			'explicit_recovery'  => true,
			'mutates_on_failure' => false,
		);
	}

	/**
	 * Attempts to charge an accepted offer.
	 *
	 * @param object              $order   WooCommerce order object.
	 * @param array<string,mixed> $offer   Offer data.
	 * @param array<string,mixed> $context Runtime context.
	 * @return Payment_Result
	 */
	public function charge_offer( $order, array $offer, array $context = array() ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
			return Payment_Result::failure(
				'mock_order_missing',
				__( 'The mock adapter needs an order before it can simulate an offer charge.', 'librefunnels' )
			);
		}

		if ( ! empty( $context['force_failure'] ) ) {
			return Payment_Result::failure(
				'mock_forced_failure',
				__( 'The mock adapter simulated a failed offer charge.', 'librefunnels' )
			);
		}

		$offer_id = isset( $offer['id'] ) ? sanitize_key( (string) $offer['id'] ) : '';

		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( '_librefunnels_mock_offer_charged', 'yes' );
			$order->update_meta_data( '_librefunnels_mock_offer_id', $offer_id );
		}

		if ( method_exists( $order, 'save' ) ) {
			$order->save();
		}

		return Payment_Result::success(
			'mock_offer_charged',
			__( 'The mock adapter simulated a successful post-purchase offer charge.', 'librefunnels' ),
			array(
				'order_id' => absint( $order->get_id() ),
				'offer_id' => $offer_id,
			)
		);
	}

	/**
	 * Attempts to refund an adapter-managed offer charge.
	 *
	 * @param object              $order   WooCommerce order object.
	 * @param array<string,mixed> $context Runtime context.
	 * @return Payment_Result
	 */
	public function refund_offer( $order, array $context = array() ) {
		unset( $context );

		if ( is_object( $order ) && method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( '_librefunnels_mock_offer_refunded', 'yes' );
		}

		if ( is_object( $order ) && method_exists( $order, 'save' ) ) {
			$order->save();
		}

		return Payment_Result::success(
			'mock_offer_refunded',
			__( 'The mock adapter simulated a successful offer refund.', 'librefunnels' )
		);
	}
}
