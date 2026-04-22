<?php
/**
 * Revenue attribution tests.
 *
 * @package LibreFunnels\Tests
 */

namespace LibreFunnels\Tests\Unit;

use LibreFunnels\Analytics\Revenue_Attribution;
use PHPUnit\Framework\TestCase;

/**
 * Tests WooCommerce order revenue attribution.
 */
final class Revenue_Attribution_Test extends TestCase {
	/**
	 * @return void
	 */
	public function test_records_funnel_revenue_from_attributed_order_lines(): void {
		$store = new Fake_Revenue_Store();
		$order = new Fake_Revenue_Order(
			array(
				new Fake_Revenue_Order_Item(
					1001,
					array(
						'_librefunnels_checkout_product'  => 'yes',
						'_librefunnels_checkout_step_id' => 10,
						'_librefunnels_funnel_id'        => 5,
					),
					100,
					8,
					2,
					44,
					0
				),
				new Fake_Revenue_Order_Item(
					1002,
					array(
						'_librefunnels_order_bump'         => 'yes',
						'_librefunnels_order_bump_id'      => 'priority-upgrade',
						'_librefunnels_order_bump_step_id' => 10,
						'_librefunnels_funnel_id'          => 5,
					),
					25,
					2,
					1,
					45,
					0
				),
				new Fake_Revenue_Order_Item(
					1003,
					array(
						'_librefunnels_pre_checkout_offer' => 'yes',
						'_librefunnels_offer_id'           => 'starter-kit',
						'_librefunnels_offer_step_id'      => 12,
						'_librefunnels_funnel_id'          => 5,
					),
					40,
					3,
					1,
					46,
					0
				),
			)
		);

		( new Revenue_Attribution( $store ) )->record_checkout_order( 9001, array(), $order );

		$this->assertCount( 1, $store->events );

		$event = $store->events[0];

		$this->assertSame( 'order_revenue', $event['event_type'] );
		$this->assertSame( 5, $event['funnel_id'] );
		$this->assertSame( 10, $event['step_id'] );
		$this->assertSame( 'order', $event['object_type'] );
		$this->assertSame( '9001', $event['object_id'] );
		$this->assertSame( 165.0, $event['value'] );
		$this->assertSame( 'USD', $event['currency'] );
		$this->assertSame( 123, $event['customer_id'] );
		$this->assertSame( 13.0, $event['context']['total_tax'] );
		$this->assertSame( array( 'checkout_product', 'order_bump', 'offer' ), $event['context']['line_sources'] );
		$this->assertSame( 'priority-upgrade', $event['context']['lines'][1]['object_id'] );
		$this->assertSame( 'yes', $order->meta[ Revenue_Attribution::ATTRIBUTED_META_KEY ] );
		$this->assertTrue( $order->saved );
	}

	/**
	 * @return void
	 */
	public function test_skips_orders_without_attributed_lines(): void {
		$store = new Fake_Revenue_Store();
		$order = new Fake_Revenue_Order(
			array(
				new Fake_Revenue_Order_Item( 1001, array(), 100, 8, 1, 44, 0 ),
			)
		);

		( new Revenue_Attribution( $store ) )->record_checkout_order( 9001, array(), $order );

		$this->assertSame( array(), $store->events );
		$this->assertFalse( $order->saved );
	}

	/**
	 * @return void
	 */
	public function test_skips_orders_that_were_already_recorded(): void {
		$store = new Fake_Revenue_Store();
		$order = new Fake_Revenue_Order(
			array(
				new Fake_Revenue_Order_Item(
					1001,
					array(
						'_librefunnels_checkout_product'  => 'yes',
						'_librefunnels_checkout_step_id' => 10,
						'_librefunnels_funnel_id'        => 5,
					),
					100,
					8,
					1,
					44,
					0
				),
			)
		);
		$order->meta[ Revenue_Attribution::ATTRIBUTED_META_KEY ] = 'yes';

		( new Revenue_Attribution( $store ) )->record_checkout_order( 9001, array(), $order );

		$this->assertSame( array(), $store->events );
	}
}

/**
 * Fake analytics store.
 */
final class Fake_Revenue_Store {
	/**
	 * Recorded events.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public $events = array();

	/**
	 * Records an event.
	 *
	 * @param array<string,mixed> $event Event.
	 * @return bool
	 */
	public function record( array $event ) {
		$this->events[] = $event;

		return true;
	}
}

/**
 * Fake WooCommerce order.
 */
final class Fake_Revenue_Order {
	/**
	 * Order items.
	 *
	 * @var array<int,Fake_Revenue_Order_Item>
	 */
	private $items;

	/**
	 * Order meta.
	 *
	 * @var array<string,mixed>
	 */
	public $meta = array();

	/**
	 * Whether the order was saved.
	 *
	 * @var bool
	 */
	public $saved = false;

	/**
	 * @param array<int,Fake_Revenue_Order_Item> $items Order items.
	 */
	public function __construct( array $items ) {
		$this->items = $items;
	}

	/**
	 * @param string $type Item type.
	 * @return array<int,Fake_Revenue_Order_Item>
	 */
	public function get_items( $type = '' ) {
		unset( $type );

		return $this->items;
	}

	/**
	 * @return int
	 */
	public function get_id() {
		return 9001;
	}

	/**
	 * @return string
	 */
	public function get_currency() {
		return 'USD';
	}

	/**
	 * @return int
	 */
	public function get_customer_id() {
		return 123;
	}

	/**
	 * @return string
	 */
	public function get_status() {
		return 'processing';
	}

	/**
	 * @return float
	 */
	public function get_total() {
		return 180.0;
	}

	/**
	 * @param string $key    Meta key.
	 * @param bool   $single Whether to get a single value.
	 * @return mixed
	 */
	public function get_meta( $key, $single = true ) {
		unset( $single );

		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
	}

	/**
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 * @return void
	 */
	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	/**
	 * @return void
	 */
	public function save() {
		$this->saved = true;
	}
}

/**
 * Fake WooCommerce order item.
 */
final class Fake_Revenue_Order_Item {
	/**
	 * Item ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Item meta.
	 *
	 * @var array<string,mixed>
	 */
	private $meta;

	/**
	 * Line total.
	 *
	 * @var float
	 */
	private $total;

	/**
	 * Line tax.
	 *
	 * @var float
	 */
	private $total_tax;

	/**
	 * Quantity.
	 *
	 * @var float
	 */
	private $quantity;

	/**
	 * Product ID.
	 *
	 * @var int
	 */
	private $product_id;

	/**
	 * Variation ID.
	 *
	 * @var int
	 */
	private $variation_id;

	/**
	 * @param int                 $id           Item ID.
	 * @param array<string,mixed> $meta         Item meta.
	 * @param float               $total        Line total.
	 * @param float               $total_tax    Line tax.
	 * @param float               $quantity     Quantity.
	 * @param int                 $product_id   Product ID.
	 * @param int                 $variation_id Variation ID.
	 */
	public function __construct( $id, array $meta, $total, $total_tax, $quantity, $product_id, $variation_id ) {
		$this->id           = $id;
		$this->meta         = $meta;
		$this->total        = $total;
		$this->total_tax    = $total_tax;
		$this->quantity     = $quantity;
		$this->product_id   = $product_id;
		$this->variation_id = $variation_id;
	}

	/**
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * @param string $key    Meta key.
	 * @param bool   $single Whether to get a single value.
	 * @return mixed
	 */
	public function get_meta( $key, $single = true ) {
		unset( $single );

		return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : '';
	}

	/**
	 * @return float
	 */
	public function get_total() {
		return $this->total;
	}

	/**
	 * @return float
	 */
	public function get_total_tax() {
		return $this->total_tax;
	}

	/**
	 * @return float
	 */
	public function get_quantity() {
		return $this->quantity;
	}

	/**
	 * @return int
	 */
	public function get_product_id() {
		return $this->product_id;
	}

	/**
	 * @return int
	 */
	public function get_variation_id() {
		return $this->variation_id;
	}
}
