<?php
/**
 * Offer payment service.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates safe post-purchase offer payment decisions.
 */
final class Offer_Payment_Service {
	/**
	 * Registry.
	 *
	 * @var Adapter_Registry
	 */
	private $registry;

	/**
	 * Creates the service.
	 *
	 * @param Adapter_Registry|null $registry Optional registry.
	 */
	public function __construct( Adapter_Registry $registry = null ) {
		$this->registry = $registry ? $registry : new Adapter_Registry();
	}

	/**
	 * Gets payment strategy for a step.
	 *
	 * @param string      $step_type Step type.
	 * @param object|null $order     WooCommerce order object.
	 * @return array<string,mixed>
	 */
	public function get_strategy_for_step( $step_type, $order = null ) {
		$step_type = sanitize_key( $step_type );

		if ( ! $this->is_post_purchase_step_type( $step_type ) ) {
			return array(
				'mode'         => 'cart_before_checkout',
				'adapterId'    => 'cart',
				'adapterLabel' => __( 'WooCommerce cart', 'librefunnels' ),
				'oneClick'     => false,
				'message'      => __( 'This offer is added before checkout, so WooCommerce collects payment normally.', 'librefunnels' ),
				'orderId'      => 0,
				'orderKey'     => '',
			);
		}

		$adapter    = $this->registry->get_adapter_for_order( $order );
		$gateway_id = $this->registry->get_gateway_id_from_order( $order );
		$capability = $adapter->get_capabilities( $gateway_id, $order );
		$one_click  = ! empty( $capability['one_click'] ) && is_object( $order );

		return array(
			'mode'         => $one_click ? 'one_click' : 'accept_and_confirm',
			'adapterId'    => $adapter->get_id(),
			'adapterLabel' => $adapter->get_label(),
			'oneClick'     => $one_click,
			'message'      => $one_click
				? __( 'This payment method can process the offer without sending the shopper back through checkout.', 'librefunnels' )
				: __( 'Accepting this offer sends the product through WooCommerce checkout confirmation, so the original order is not changed by an unsupported gateway charge.', 'librefunnels' ),
			'orderId'      => $this->get_order_id( $order ),
			'orderKey'     => $this->get_order_key( $order ),
		);
	}

	/**
	 * Attempts a one-click offer charge through the resolved adapter.
	 *
	 * @param object              $order   WooCommerce order object.
	 * @param array<string,mixed> $offer   Offer data.
	 * @param array<string,mixed> $context Runtime context.
	 * @return Payment_Result
	 */
	public function charge_offer( $order, array $offer, array $context = array() ) {
		$adapter = $this->registry->get_adapter_for_order( $order );

		return $adapter->charge_offer( $order, $offer, $context );
	}

	/**
	 * Reads an order from request data when an order key is available.
	 *
	 * @param array<string,mixed> $data Request data.
	 * @return object|null
	 */
	public function get_order_from_request_data( array $data ) {
		$order_id = isset( $data['librefunnels_order_id'] ) ? absint( $data['librefunnels_order_id'] ) : 0;
		$key      = isset( $data['librefunnels_order_key'] ) ? sanitize_text_field( (string) $data['librefunnels_order_key'] ) : '';

		if ( 0 === $order_id || '' === $key || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		if ( ! is_object( $order ) || ! method_exists( $order, 'get_order_key' ) ) {
			return null;
		}

		return hash_equals( (string) $order->get_order_key(), $key ) ? $order : null;
	}

	/**
	 * Whether a step type is a post-purchase offer.
	 *
	 * @param string $step_type Step type.
	 * @return bool
	 */
	public function is_post_purchase_step_type( $step_type ) {
		return in_array( sanitize_key( $step_type ), array( 'upsell', 'downsell', 'cross_sell' ), true );
	}

	/**
	 * Gets an order ID.
	 *
	 * @param object|null $order Order object.
	 * @return int
	 */
	private function get_order_id( $order ) {
		return is_object( $order ) && method_exists( $order, 'get_id' ) ? absint( $order->get_id() ) : 0;
	}

	/**
	 * Gets an order key.
	 *
	 * @param object|null $order Order object.
	 * @return string
	 */
	private function get_order_key( $order ) {
		return is_object( $order ) && method_exists( $order, 'get_order_key' ) ? sanitize_text_field( (string) $order->get_order_key() ) : '';
	}
}
