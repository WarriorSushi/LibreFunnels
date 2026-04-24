<?php
/**
 * Payment adapter registry.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves payment adapters for WooCommerce gateways.
 */
final class Adapter_Registry {
	/**
	 * Registered adapters.
	 *
	 * @var array<int,Payment_Adapter_Interface>
	 */
	private $adapters;

	/**
	 * Creates the registry.
	 *
	 * @param array<int,Payment_Adapter_Interface>|null $adapters Optional adapters.
	 */
	public function __construct( array $adapters = null ) {
		$this->adapters = $adapters ? $adapters : array(
			new Mock_Adapter(),
			new Fallback_Adapter(),
		);
	}

	/**
	 * Gets all adapters.
	 *
	 * @return array<int,Payment_Adapter_Interface>
	 */
	public function get_adapters() {
		$adapters = $this->adapters;

		if ( function_exists( 'apply_filters' ) ) {
			$adapters = apply_filters( 'librefunnels_payment_adapters', $adapters );
		}

		return array_values(
			array_filter(
				$adapters,
				static function ( $adapter ) {
					return $adapter instanceof Payment_Adapter_Interface;
				}
			)
		);
	}

	/**
	 * Gets an adapter for an order.
	 *
	 * @param object|null $order WooCommerce order object.
	 * @return Payment_Adapter_Interface
	 */
	public function get_adapter_for_order( $order = null ) {
		$gateway_id = $this->get_gateway_id_from_order( $order );
		$gateway    = $this->get_gateway( $gateway_id );
		$fallback   = new Fallback_Adapter();

		foreach ( $this->get_adapters() as $adapter ) {
			if ( $adapter instanceof Fallback_Adapter ) {
				$fallback = $adapter;
				continue;
			}

			if ( $adapter->supports_gateway( $gateway_id, $gateway ) ) {
				return $adapter;
			}
		}

		return $fallback;
	}

	/**
	 * Gets an order's payment gateway ID.
	 *
	 * @param object|null $order WooCommerce order object.
	 * @return string
	 */
	public function get_gateway_id_from_order( $order = null ) {
		if ( is_object( $order ) && method_exists( $order, 'get_payment_method' ) ) {
			return sanitize_key( (string) $order->get_payment_method() );
		}

		return '';
	}

	/**
	 * Gets a gateway object by ID when WooCommerce is available.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return object|null
	 */
	private function get_gateway( $gateway_id ) {
		if ( '' === $gateway_id || ! function_exists( 'WC' ) ) {
			return null;
		}

		$woocommerce = WC();

		if ( ! $woocommerce || ! method_exists( $woocommerce, 'payment_gateways' ) || ! $woocommerce->payment_gateways() ) {
			return null;
		}

		$gateways = $woocommerce->payment_gateways()->payment_gateways();

		if ( ! is_array( $gateways ) ) {
			return null;
		}

		return isset( $gateways[ $gateway_id ] ) && is_object( $gateways[ $gateway_id ] ) ? $gateways[ $gateway_id ] : null;
	}
}
