<?php
/**
 * Offer eligibility tests.
 *
 * @package LibreFunnels\Tests
 */

namespace LibreFunnels\Tests\Unit;

use LibreFunnels\Offers\Offer_Eligibility;
use PHPUnit\Framework\TestCase;

/**
 * Tests offer product eligibility checks.
 */
final class Offer_Eligibility_Test extends TestCase {
	/**
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['librefunnels_test_products'] = array();

		parent::tearDown();
	}

	/**
	 * @return void
	 */
	public function test_purchasable_product_offer_succeeds(): void {
		$GLOBALS['librefunnels_test_products'] = array(
			123 => new Fake_Product( true ),
		);

		$result = ( new Offer_Eligibility() )->is_product_offer_purchasable(
			array(
				'product_id' => 123,
			)
		);

		$this->assertTrue( $result->is_success() );
		$this->assertSame( 123, $result->get_step_id() );
		$this->assertSame( 'offer_product_purchasable', $result->get_code() );
	}

	/**
	 * @return void
	 */
	public function test_missing_product_offer_fails(): void {
		$result = ( new Offer_Eligibility() )->is_product_offer_purchasable( array() );

		$this->assertFalse( $result->is_success() );
		$this->assertSame( 'offer_product_missing', $result->get_code() );
	}

	/**
	 * @return void
	 */
	public function test_non_purchasable_product_offer_fails(): void {
		$GLOBALS['librefunnels_test_products'] = array(
			123 => new Fake_Product( false ),
		);

		$result = ( new Offer_Eligibility() )->is_product_offer_purchasable(
			array(
				'product_id' => 123,
			)
		);

		$this->assertFalse( $result->is_success() );
		$this->assertSame( 'offer_product_not_purchasable', $result->get_code() );
	}
}

/**
 * Minimal fake product for isolated eligibility tests.
 */
final class Fake_Product {
	/**
	 * Purchasable state.
	 *
	 * @var bool
	 */
	private $purchasable;

	/**
	 * Creates the fake product.
	 *
	 * @param bool $purchasable Purchasable state.
	 */
	public function __construct( $purchasable ) {
		$this->purchasable = (bool) $purchasable;
	}

	/**
	 * Whether the product is purchasable.
	 *
	 * @return bool
	 */
	public function is_purchasable() {
		return $this->purchasable;
	}
}
