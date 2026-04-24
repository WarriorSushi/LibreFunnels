<?php
/**
 * WooCommerce fact collector tests.
 *
 * @package LibreFunnels\Tests
 */

namespace LibreFunnels\Tests\Unit;

use LibreFunnels\Rules\WooCommerce_Fact_Collector;
use PHPUnit\Framework\TestCase;

/**
 * Tests WooCommerce fact collection for rules.
 */
final class WooCommerce_Fact_Collector_Test extends TestCase {
	/**
	 * @return void
	 */
	public function test_collects_cart_facts_from_injected_cart(): void {
		$collector = new WooCommerce_Fact_Collector(
			new Fake_Cart(
				array(
					array(
						'product_id'   => 123,
						'variation_id' => 0,
					),
					array(
						'product_id'   => 456,
						'variation_id' => 789,
					),
				),
				120.5,
				3
			),
			static function () {
				return true;
			}
		);

		$facts = $collector->collect();

		$this->assertSame( array( 123, 456 ), $facts['cart_product_ids'] );
		$this->assertSame( array( 789 ), $facts['cart_variation_ids'] );
		$this->assertSame( 120.5, $facts['cart_subtotal'] );
		$this->assertSame( 3, $facts['cart_item_count'] );
		$this->assertTrue( $facts['customer_logged_in'] );
	}

	/**
	 * @return void
	 */
	public function test_missing_cart_returns_empty_facts(): void {
		$collector = new WooCommerce_Fact_Collector( null, '__return_false' );
		$facts     = $collector->collect();

		$this->assertSame( array(), $facts['cart_product_ids'] );
		$this->assertSame( array(), $facts['cart_variation_ids'] );
		$this->assertSame( 0.0, $facts['cart_subtotal'] );
		$this->assertSame( 0, $facts['cart_item_count'] );
		$this->assertFalse( $facts['customer_logged_in'] );
		$this->assertSame( 0, $facts['order_id'] );
		$this->assertSame( array(), $facts['order_product_ids'] );
		$this->assertSame( 0.0, $facts['order_total'] );
	}

	/**
	 * @return void
	 */
	public function test_collects_order_facts_from_injected_order(): void {
		$collector = new WooCommerce_Fact_Collector(
			null,
			'__return_false',
			new Fake_Fact_Order(
				array(
					new Fake_Fact_Order_Item( 321, 0 ),
					new Fake_Fact_Order_Item( 654, 987 ),
				)
			)
		);
		$facts     = $collector->collect();

		$this->assertSame( 101, $facts['order_id'] );
		$this->assertSame( array( 321, 654 ), $facts['order_product_ids'] );
		$this->assertSame( array( 987 ), $facts['order_variation_ids'] );
		$this->assertSame( 88.25, $facts['order_total'] );
		$this->assertSame( 80.0, $facts['order_subtotal'] );
		$this->assertSame( 2, $facts['order_item_count'] );
		$this->assertSame( 'processing', $facts['order_status'] );
		$this->assertSame( 'stripe', $facts['order_payment_method'] );
		$this->assertSame( 'USD', $facts['order_currency'] );
		$this->assertSame( 55, $facts['customer_id'] );
	}
}

/**
 * Minimal cart fake.
 */
final class Fake_Cart {
	/**
	 * Cart items.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $items;

	/**
	 * Subtotal.
	 *
	 * @var float
	 */
	private $subtotal;

	/**
	 * Item count.
	 *
	 * @var int
	 */
	private $count;

	/**
	 * Creates the fake cart.
	 *
	 * @param array<int,array<string,mixed>> $items    Items.
	 * @param float                         $subtotal Subtotal.
	 * @param int                           $count    Count.
	 */
	public function __construct( array $items, $subtotal, $count ) {
		$this->items    = $items;
		$this->subtotal = (float) $subtotal;
		$this->count    = absint( $count );
	}

	/**
	 * Gets cart items.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_cart() {
		return $this->items;
	}

	/**
	 * Gets subtotal.
	 *
	 * @return float
	 */
	public function get_subtotal() {
		return $this->subtotal;
	}

	/**
	 * Gets item count.
	 *
	 * @return int
	 */
	public function get_cart_contents_count() {
		return $this->count;
	}
}

/**
 * Minimal order fake.
 */
final class Fake_Fact_Order {
	/**
	 * Items.
	 *
	 * @var array<int,Fake_Fact_Order_Item>
	 */
	private $items;

	/**
	 * Creates the fake order.
	 *
	 * @param array<int,Fake_Fact_Order_Item> $items Items.
	 */
	public function __construct( array $items ) {
		$this->items = $items;
	}

	/**
	 * Gets order ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return 101;
	}

	/**
	 * Gets line items.
	 *
	 * @return array<int,Fake_Fact_Order_Item>
	 */
	public function get_items( $type = '' ) {
		unset( $type );

		return $this->items;
	}

	/**
	 * Gets total.
	 *
	 * @return float
	 */
	public function get_total( $context = 'view' ) {
		unset( $context );

		return 88.25;
	}

	/**
	 * Gets subtotal.
	 *
	 * @return float
	 */
	public function get_subtotal() {
		return 80.0;
	}

	/**
	 * Gets item count.
	 *
	 * @return int
	 */
	public function get_item_count() {
		return 2;
	}

	/**
	 * Gets status.
	 *
	 * @return string
	 */
	public function get_status() {
		return 'processing';
	}

	/**
	 * Gets payment method.
	 *
	 * @return string
	 */
	public function get_payment_method() {
		return 'stripe';
	}

	/**
	 * Gets currency.
	 *
	 * @return string
	 */
	public function get_currency() {
		return 'USD';
	}

	/**
	 * Gets customer ID.
	 *
	 * @return int
	 */
	public function get_customer_id() {
		return 55;
	}
}

/**
 * Minimal order item fake.
 */
final class Fake_Fact_Order_Item {
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
	 * Creates the fake item.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 */
	public function __construct( $product_id, $variation_id ) {
		$this->product_id   = absint( $product_id );
		$this->variation_id = absint( $variation_id );
	}

	/**
	 * Gets product ID.
	 *
	 * @return int
	 */
	public function get_product_id() {
		return $this->product_id;
	}

	/**
	 * Gets variation ID.
	 *
	 * @return int
	 */
	public function get_variation_id() {
		return $this->variation_id;
	}
}
