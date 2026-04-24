<?php
/**
 * Safe fallback payment adapter.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Fallback adapter for gateways without one-click post-purchase support.
 */
final class Fallback_Adapter implements Payment_Adapter_Interface {
	/**
	 * Gets the adapter ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'fallback';
	}

	/**
	 * Gets the adapter label.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'WooCommerce confirmation fallback', 'librefunnels' );
	}

	/**
	 * Whether this adapter handles a gateway.
	 *
	 * @param string      $gateway_id Gateway ID.
	 * @param object|null $gateway    Gateway object when available.
	 * @return bool
	 */
	public function supports_gateway( $gateway_id, $gateway = null ) {
		unset( $gateway_id, $gateway );

		return true;
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
			'one_click'          => false,
			'accept_confirm'     => true,
			'child_orders'       => false,
			'refunds'            => false,
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
		unset( $order, $offer, $context );

		return Payment_Result::confirmation_required(
			'gateway_requires_confirmation',
			__( 'This payment method needs WooCommerce checkout confirmation before the offer can be charged.', 'librefunnels' )
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
		unset( $order, $context );

		return Payment_Result::failure(
			'gateway_refund_unavailable',
			__( 'This payment method does not expose automatic LibreFunnels offer refunds yet.', 'librefunnels' )
		);
	}
}
