<?php
/**
 * WooCommerce order revenue attribution.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Records local revenue events when WooCommerce creates orders.
 */
final class Revenue_Attribution {
	/**
	 * Order meta key used to prevent duplicate revenue events.
	 */
	const ATTRIBUTED_META_KEY = '_librefunnels_revenue_attributed';

	/**
	 * Event store.
	 *
	 * @var object
	 */
	private $store;

	/**
	 * Creates the attribution recorder.
	 *
	 * @param object|null $store Optional event store.
	 */
	public function __construct( $store = null ) {
		$this->store = is_object( $store ) && method_exists( $store, 'record' ) ? $store : new Event_Store();
	}

	/**
	 * Registers WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'record_checkout_order' ), 20, 3 );
	}

	/**
	 * Records attributable revenue for a freshly-created checkout order.
	 *
	 * @param int          $order_id    Order ID.
	 * @param array<mixed> $posted_data Posted checkout data.
	 * @param object|null  $order       WooCommerce order object.
	 * @return void
	 */
	public function record_checkout_order( $order_id, $posted_data = array(), $order = null ) {
		unset( $posted_data );

		$order = $this->normalize_order( $order_id, $order );

		if ( ! $order || $this->has_recorded_order( $order ) ) {
			return;
		}

		$groups   = $this->collect_revenue_groups( $order );
		$recorded = false;

		foreach ( $groups as $group ) {
			$recorded = $this->store->record(
				array(
					'event_type'  => 'order_revenue',
					'funnel_id'   => $group['funnel_id'],
					'step_id'     => $group['step_id'],
					'object_type' => 'order',
					'object_id'   => (string) $this->get_order_id( $order ),
					'value'       => $group['value'],
					'currency'    => $this->get_order_currency( $order ),
					'customer_id' => $this->get_order_customer_id( $order ),
					'context'     => array(
						'order_id'     => $this->get_order_id( $order ),
						'order_status' => $this->get_order_status( $order ),
						'order_total'  => $this->get_order_total( $order ),
						'total_tax'    => $group['total_tax'],
						'line_count'   => count( $group['lines'] ),
						'line_sources' => $group['sources'],
						'lines'        => $group['lines'],
					),
				)
			) || $recorded;
		}

		if ( $recorded ) {
			$this->mark_order_recorded( $order );
		}
	}

	/**
	 * Normalizes a WooCommerce order object.
	 *
	 * @param int         $order_id Order ID.
	 * @param object|null $order    Potential order object.
	 * @return object|null
	 */
	private function normalize_order( $order_id, $order ) {
		if ( is_object( $order ) && method_exists( $order, 'get_items' ) ) {
			return $order;
		}

		if ( function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( absint( $order_id ) );

			if ( is_object( $order ) && method_exists( $order, 'get_items' ) ) {
				return $order;
			}
		}

		return null;
	}

	/**
	 * Checks whether an order has already produced revenue events.
	 *
	 * @param object $order WooCommerce order object.
	 * @return bool
	 */
	private function has_recorded_order( $order ) {
		return method_exists( $order, 'get_meta' ) && 'yes' === $order->get_meta( self::ATTRIBUTED_META_KEY, true );
	}

	/**
	 * Marks an order as recorded using the WooCommerce CRUD object.
	 *
	 * @param object $order WooCommerce order object.
	 * @return void
	 */
	private function mark_order_recorded( $order ) {
		if ( ! method_exists( $order, 'update_meta_data' ) || ! method_exists( $order, 'save' ) ) {
			return;
		}

		$order->update_meta_data( self::ATTRIBUTED_META_KEY, 'yes' );
		$order->update_meta_data( '_librefunnels_revenue_attributed_at', $this->get_current_mysql_time() );
		$order->save();
	}

	/**
	 * Collects attributable order lines grouped by funnel.
	 *
	 * @param object $order WooCommerce order object.
	 * @return array<int,array<string,mixed>>
	 */
	private function collect_revenue_groups( $order ) {
		$groups = array();
		$items  = $order->get_items( 'line_item' );

		if ( ! is_array( $items ) ) {
			return array();
		}

		foreach ( $items as $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}

			$source = $this->get_line_source( $item );

			if ( empty( $source ) ) {
				continue;
			}

			$funnel_id = $this->get_line_funnel_id( $item, $source['step_id'] );

			if ( 0 === $funnel_id ) {
				continue;
			}

			if ( ! isset( $groups[ $funnel_id ] ) ) {
				$groups[ $funnel_id ] = array(
					'funnel_id' => $funnel_id,
					'step_id'   => absint( $source['step_id'] ),
					'value'     => 0.0,
					'total_tax' => 0.0,
					'sources'   => array(),
					'lines'     => array(),
				);
			}

			$total     = $this->get_item_float( $item, 'get_total' );
			$total_tax = $this->get_item_float( $item, 'get_total_tax' );

			$groups[ $funnel_id ]['value']     += $total;
			$groups[ $funnel_id ]['total_tax'] += $total_tax;

			if ( ! in_array( $source['source'], $groups[ $funnel_id ]['sources'], true ) ) {
				$groups[ $funnel_id ]['sources'][] = $source['source'];
			}

			$groups[ $funnel_id ]['lines'][] = array(
				'item_id'      => $this->get_item_id( $item ),
				'source'       => $source['source'],
				'step_id'      => absint( $source['step_id'] ),
				'object_id'    => $source['object_id'],
				'product_id'   => $this->get_item_int( $item, 'get_product_id' ),
				'variation_id' => $this->get_item_int( $item, 'get_variation_id' ),
				'quantity'     => $this->get_item_float( $item, 'get_quantity' ),
				'total'        => $total,
				'total_tax'    => $total_tax,
			);
		}

		return array_values( $groups );
	}

	/**
	 * Gets the LibreFunnels source for an order line item.
	 *
	 * @param object $item Order line item.
	 * @return array<string,mixed>
	 */
	private function get_line_source( $item ) {
		if ( 'yes' === $this->get_item_meta_string( $item, '_librefunnels_checkout_product' ) || $this->get_item_meta_int( $item, '_librefunnels_checkout_step_id' ) > 0 ) {
			return array(
				'source'    => 'checkout_product',
				'step_id'   => $this->get_item_meta_int( $item, '_librefunnels_checkout_step_id' ),
				'object_id' => (string) $this->get_item_int( $item, 'get_product_id' ),
			);
		}

		if ( 'yes' === $this->get_item_meta_string( $item, '_librefunnels_order_bump' ) ) {
			return array(
				'source'    => 'order_bump',
				'step_id'   => $this->get_item_meta_int( $item, '_librefunnels_order_bump_step_id' ),
				'object_id' => $this->get_item_meta_string( $item, '_librefunnels_order_bump_id' ),
			);
		}

		if ( 'yes' === $this->get_item_meta_string( $item, '_librefunnels_pre_checkout_offer' ) ) {
			return array(
				'source'    => 'offer',
				'step_id'   => $this->get_item_meta_int( $item, '_librefunnels_offer_step_id' ),
				'object_id' => $this->get_item_meta_string( $item, '_librefunnels_offer_id' ),
			);
		}

		return array();
	}

	/**
	 * Gets the funnel ID for an attributed line item.
	 *
	 * @param object $item    Order line item.
	 * @param int    $step_id Step ID.
	 * @return int
	 */
	private function get_line_funnel_id( $item, $step_id ) {
		$funnel_id = $this->get_item_meta_int( $item, '_librefunnels_funnel_id' );

		if ( $funnel_id > 0 ) {
			return $funnel_id;
		}

		if ( function_exists( 'get_post_meta' ) ) {
			return absint( get_post_meta( absint( $step_id ), LIBREFUNNELS_STEP_FUNNEL_ID_META, true ) );
		}

		return 0;
	}

	/**
	 * Gets string line item metadata.
	 *
	 * @param object $item Order line item.
	 * @param string $key  Metadata key.
	 * @return string
	 */
	private function get_item_meta_string( $item, $key ) {
		if ( method_exists( $item, 'get_meta' ) ) {
			return sanitize_key( (string) $item->get_meta( $key, true ) );
		}

		return '';
	}

	/**
	 * Gets integer line item metadata.
	 *
	 * @param object $item Order line item.
	 * @param string $key  Metadata key.
	 * @return int
	 */
	private function get_item_meta_int( $item, $key ) {
		if ( method_exists( $item, 'get_meta' ) ) {
			return absint( $item->get_meta( $key, true ) );
		}

		return 0;
	}

	/**
	 * Gets a numeric line item property.
	 *
	 * @param object $item   Order line item.
	 * @param string $method Getter method.
	 * @return float
	 */
	private function get_item_float( $item, $method ) {
		return method_exists( $item, $method ) ? (float) $item->{$method}() : 0.0;
	}

	/**
	 * Gets an integer line item property.
	 *
	 * @param object $item   Order line item.
	 * @param string $method Getter method.
	 * @return int
	 */
	private function get_item_int( $item, $method ) {
		return method_exists( $item, $method ) ? absint( $item->{$method}() ) : 0;
	}

	/**
	 * Gets the line item ID.
	 *
	 * @param object $item Order line item.
	 * @return int
	 */
	private function get_item_id( $item ) {
		return method_exists( $item, 'get_id' ) ? absint( $item->get_id() ) : 0;
	}

	/**
	 * Gets the order ID.
	 *
	 * @param object $order WooCommerce order object.
	 * @return int
	 */
	private function get_order_id( $order ) {
		return method_exists( $order, 'get_id' ) ? absint( $order->get_id() ) : 0;
	}

	/**
	 * Gets the order currency.
	 *
	 * @param object $order WooCommerce order object.
	 * @return string
	 */
	private function get_order_currency( $order ) {
		return method_exists( $order, 'get_currency' ) ? sanitize_text_field( (string) $order->get_currency() ) : '';
	}

	/**
	 * Gets the order customer ID.
	 *
	 * @param object $order WooCommerce order object.
	 * @return int
	 */
	private function get_order_customer_id( $order ) {
		return method_exists( $order, 'get_customer_id' ) ? absint( $order->get_customer_id() ) : 0;
	}

	/**
	 * Gets the order status.
	 *
	 * @param object $order WooCommerce order object.
	 * @return string
	 */
	private function get_order_status( $order ) {
		return method_exists( $order, 'get_status' ) ? sanitize_key( (string) $order->get_status() ) : '';
	}

	/**
	 * Gets the order total.
	 *
	 * @param object $order WooCommerce order object.
	 * @return float
	 */
	private function get_order_total( $order ) {
		return method_exists( $order, 'get_total' ) ? (float) $order->get_total() : 0.0;
	}

	/**
	 * Gets the current UTC MySQL datetime.
	 *
	 * @return string
	 */
	private function get_current_mysql_time() {
		if ( function_exists( 'current_time' ) ) {
			return current_time( 'mysql', true );
		}

		return gmdate( 'Y-m-d H:i:s' );
	}
}
