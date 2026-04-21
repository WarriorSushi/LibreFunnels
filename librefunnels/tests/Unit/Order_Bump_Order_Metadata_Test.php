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
				'librefunnels_discount_type'      => 'percentage',
				'librefunnels_discount_amount'    => 15.5,
				'librefunnels_original_price'     => 100,
			),
			null
		);

		$this->assertSame( 'yes', $item->meta['_librefunnels_order_bump'] );
		$this->assertSame( 'priority-upgrade', $item->meta['_librefunnels_order_bump_id'] );
		$this->assertSame( 123, $item->meta['_librefunnels_order_bump_step_id'] );
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
