<?php
/**
 * Order bump order metadata tests.
 *
 * @package LibreFunnels\Tests
 */

namespace LibreFunnels\Tests\Unit;

use LibreFunnels\Offers\Order_Bump_Order_Metadata;
use PHPUnit\Framework\TestCase;

/**
 * Tests copying order bump cart item data to order line item meta.
 */
final class Order_Bump_Order_Metadata_Test extends TestCase {
	/**
	 * @return void
	 */
	public function test_order_bump_cart_item_adds_line_item_metadata(): void {
		$item = new Fake_Order_Item();

		( new Order_Bump_Order_Metadata() )->add_line_item_metadata(
			$item,
			'cart-key',
			array(
				'librefunnels_order_bump'         => true,
				'librefunnels_order_bump_id'      => 'priority-upgrade',
				'librefunnels_order_bump_step_id' => 123,
				'librefunnels_funnel_id'          => 77,
				'librefunnels_discount_type'      => 'percentage',
				'librefunnels_discount_amount'    => 15.5,
				'librefunnels_original_price'     => 100,
			),
			null
		);

		$this->assertSame( 'yes', $item->meta['_librefunnels_order_bump'] );
		$this->assertSame( 'priority-upgrade', $item->meta['_librefunnels_order_bump_id'] );
		$this->assertSame( 123, $item->meta['_librefunnels_order_bump_step_id'] );
		$this->assertSame( 77, $item->meta['_librefunnels_funnel_id'] );
		$this->assertSame( 'percentage', $item->meta['_librefunnels_order_bump_discount_type'] );
		$this->assertSame( 15.5, $item->meta['_librefunnels_order_bump_discount_amount'] );
		$this->assertSame( 100.0, $item->meta['_librefunnels_order_bump_original_price'] );
	}

	/**
	 * @return void
	 */
	public function test_normal_cart_item_does_not_add_metadata(): void {
		$item = new Fake_Order_Item();

		( new Order_Bump_Order_Metadata() )->add_line_item_metadata( $item, 'cart-key', array(), null );

		$this->assertSame( array(), $item->meta );
	}

	/**
	 * @return void
	 */
	public function test_pre_checkout_offer_cart_item_adds_line_item_metadata(): void {
		$item = new Fake_Order_Item();

		( new Order_Bump_Order_Metadata() )->add_line_item_metadata(
			$item,
			'cart-key',
			array(
				'librefunnels_pre_checkout_offer' => true,
				'librefunnels_offer_id'           => 'starter-kit',
				'librefunnels_offer_step_id'      => 456,
				'librefunnels_funnel_id'          => 88,
				'librefunnels_discount_type'      => 'fixed',
				'librefunnels_discount_amount'    => 20,
				'librefunnels_original_price'     => 80,
			),
			null
		);

		$this->assertSame( 'yes', $item->meta['_librefunnels_pre_checkout_offer'] );
		$this->assertSame( 'starter-kit', $item->meta['_librefunnels_offer_id'] );
		$this->assertSame( 456, $item->meta['_librefunnels_offer_step_id'] );
		$this->assertSame( 88, $item->meta['_librefunnels_funnel_id'] );
		$this->assertSame( 'fixed', $item->meta['_librefunnels_offer_discount_type'] );
		$this->assertSame( 20.0, $item->meta['_librefunnels_offer_discount_amount'] );
		$this->assertSame( 80.0, $item->meta['_librefunnels_offer_original_price'] );
	}

	/**
	 * @return void
	 */
	public function test_checkout_product_cart_item_adds_line_item_metadata(): void {
		$item = new Fake_Order_Item();

		( new Order_Bump_Order_Metadata() )->add_line_item_metadata(
			$item,
			'cart-key',
			array(
				'librefunnels_checkout_product'  => true,
				'librefunnels_checkout_step_id' => 321,
				'librefunnels_funnel_id'        => 99,
			),
			null
		);

		$this->assertSame( 'yes', $item->meta['_librefunnels_checkout_product'] );
		$this->assertSame( 321, $item->meta['_librefunnels_checkout_step_id'] );
		$this->assertSame( 99, $item->meta['_librefunnels_funnel_id'] );
	}
}

/**
 * Minimal fake order item for metadata tests.
 */
final class Fake_Order_Item {
	/**
	 * Stored meta.
	 *
	 * @var array<string,mixed>
	 */
	public $meta = array();

	/**
	 * Adds item metadata.
	 *
	 * @param string $key    Meta key.
	 * @param mixed  $value  Meta value.
	 * @param bool   $unique Whether meta should be unique.
	 * @return void
	 */
	public function add_meta_data( $key, $value, $unique = false ) {
		unset( $unique );

		$this->meta[ $key ] = $value;
	}
}
