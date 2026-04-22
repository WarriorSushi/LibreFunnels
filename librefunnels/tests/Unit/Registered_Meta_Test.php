<?php
/**
 * Registered metadata tests.
 *
 * @package LibreFunnels\Tests
 */

namespace LibreFunnels\Tests\Unit;

use LibreFunnels\Domain\Registered_Meta;
use PHPUnit\Framework\TestCase;

/**
 * Tests registered metadata sanitizers used by the canvas.
 */
final class Registered_Meta_Test extends TestCase {
	/**
	 * @return void
	 */
	public function test_checkout_products_sanitize_multiple_assignments(): void {
		$products = Registered_Meta::sanitize_checkout_products(
			array(
				array(
					'product_id'   => 123,
					'variation_id' => 456,
					'quantity'     => 2,
					'variation'    => array(
						'attribute_pa_color' => 'Blue',
						''                   => 'ignored',
					),
				),
				array(
					'product_id' => 789,
					'quantity'   => 0,
				),
				array(
					'product_id' => 0,
				),
			)
		);

		$this->assertCount( 2, $products );
		$this->assertSame( 123, $products[0]['product_id'] );
		$this->assertSame( 456, $products[0]['variation_id'] );
		$this->assertSame( 2, $products[0]['quantity'] );
		$this->assertSame( 'Blue', $products[0]['variation']['attribute_pa_color'] );
		$this->assertSame( 789, $products[1]['product_id'] );
		$this->assertSame( 1, $products[1]['quantity'] );
	}

	/**
	 * @return void
	 */
	public function test_order_bumps_sanitize_multiple_bumps(): void {
		$bumps = Registered_Meta::sanitize_order_bumps(
			array(
				array(
					'id'              => 'VIP Upgrade',
					'product_id'      => 321,
					'variation_id'    => 654,
					'quantity'        => 3,
					'variation'       => array(
						'attribute_pa_size' => 'large',
					),
					'title'           => 'VIP setup',
					'description'     => '<strong>Priority setup</strong>',
					'discount_type'   => 'percentage',
					'discount_amount' => 12.5,
					'enabled'         => true,
				),
				array(
					'product_id'      => 987,
					'discount_type'   => 'not-real',
					'discount_amount' => -9,
					'enabled'         => false,
				),
			)
		);

		$this->assertCount( 2, $bumps );
		$this->assertSame( 'vipupgrade', $bumps[0]['id'] );
		$this->assertSame( 321, $bumps[0]['product_id'] );
		$this->assertSame( 654, $bumps[0]['variation_id'] );
		$this->assertSame( 3, $bumps[0]['quantity'] );
		$this->assertSame( 'large', $bumps[0]['variation']['attribute_pa_size'] );
		$this->assertSame( 'percentage', $bumps[0]['discount_type'] );
		$this->assertSame( 12.5, $bumps[0]['discount_amount'] );
		$this->assertSame( 'bump-987-1', $bumps[1]['id'] );
		$this->assertSame( 'none', $bumps[1]['discount_type'] );
		$this->assertSame( 0.0, $bumps[1]['discount_amount'] );
		$this->assertFalse( $bumps[1]['enabled'] );
	}
}
