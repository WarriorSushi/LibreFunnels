<?php
/**
 * Discount calculator tests.
 *
 * @package LibreFunnels\Tests
 */

namespace LibreFunnels\Tests\Unit;

use LibreFunnels\Offers\Discount_Calculator;
use PHPUnit\Framework\TestCase;

/**
 * Tests offer discount price calculation.
 */
final class Discount_Calculator_Test extends TestCase {
	/**
	 * @return void
	 */
	public function test_percentage_discount_reduces_price(): void {
		$calculator = new Discount_Calculator();

		$this->assertSame( 80.0, $calculator->calculate_price( 100, 'percentage', 20 ) );
	}

	/**
	 * @return void
	 */
	public function test_percentage_discount_is_capped_at_free(): void {
		$calculator = new Discount_Calculator();

		$this->assertSame( 0.0, $calculator->calculate_price( 100, 'percentage', 200 ) );
	}

	/**
	 * @return void
	 */
	public function test_fixed_discount_never_returns_negative_price(): void {
		$calculator = new Discount_Calculator();

		$this->assertSame( 0.0, $calculator->calculate_price( 25, 'fixed', 40 ) );
	}

	/**
	 * @return void
	 */
	public function test_none_discount_keeps_price(): void {
		$calculator = new Discount_Calculator();

		$this->assertSame( 45.5, $calculator->calculate_price( 45.5, 'none', 100 ) );
	}
}
