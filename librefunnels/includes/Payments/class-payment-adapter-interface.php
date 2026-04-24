<?php
/**
 * Payment adapter interface.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for post-purchase payment adapters.
 */
interface Payment_Adapter_Interface {
	/**
	 * Gets the adapter ID.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Gets the adapter label.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Whether this adapter handles a gateway.
	 *
	 * @param string      $gateway_id Gateway ID.
	 * @param object|null $gateway    Gateway object when available.
	 * @return bool
	 */
	public function supports_gateway( $gateway_id, $gateway = null );

	/**
	 * Gets supported capabilities for a gateway/order pair.
	 *
	 * @param string      $gateway_id Gateway ID.
	 * @param object|null $order      WooCommerce order object.
	 * @return array<string,bool>
	 */
	public function get_capabilities( $gateway_id = '', $order = null );

	/**
	 * Attempts to charge an accepted post-purchase offer.
	 *
	 * @param object              $order   WooCommerce order object.
	 * @param array<string,mixed> $offer   Offer data.
	 * @param array<string,mixed> $context Runtime context.
	 * @return Payment_Result
	 */
	public function charge_offer( $order, array $offer, array $context = array() );

	/**
	 * Attempts to refund or void an adapter-managed offer charge.
	 *
	 * @param object              $order   WooCommerce order object.
	 * @param array<string,mixed> $context Runtime context.
	 * @return Payment_Result
	 */
	public function refund_offer( $order, array $context = array() );
}
