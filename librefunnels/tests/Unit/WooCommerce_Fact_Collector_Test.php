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
