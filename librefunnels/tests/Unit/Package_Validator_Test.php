<?php
/**
 * Package validator tests.
 *
 * @package LibreFunnels\Tests
 */

namespace LibreFunnels\Tests\Unit;

use LibreFunnels\ImportExport\Package_Validator;
use PHPUnit\Framework\TestCase;

/**
 * Tests import/export package normalization.
 */
final class Package_Validator_Test extends TestCase {
	/**
	 * @return void
	 */
	public function test_valid_package_is_normalized(): void {
		$validator = new Package_Validator();
		$package   = $validator->normalize( $this->package() );

		$this->assertIsArray( $package );
		$this->assertSame( Package_Validator::FORMAT, $package['format'] );
		$this->assertSame( Package_Validator::VERSION, $package['version'] );
		$this->assertSame( 'Checkout Flow', $package['funnel']['title'] );
		$this->assertSame( 10, $package['funnel']['startStepId'] );
		$this->assertSame( 'thank_you', $package['steps'][0]['type'] );
		$this->assertSame( 777, $package['steps'][0]['pageId'] );
		$this->assertSame( 123, $package['steps'][0]['checkoutProducts'][0]['product_id'] );
		$this->assertSame( 2, $package['steps'][0]['checkoutProducts'][0]['quantity'] );
		$this->assertSame( 'blue', $package['steps'][0]['checkoutProducts'][0]['variation']['attribute_pa_color'] );
		$this->assertSame( array( 'SAVE10' ), $package['steps'][0]['checkoutCoupons'] );
		$this->assertSame( 'billing', $package['steps'][0]['checkoutFields'][0]['section'] );
		$this->assertFalse( $package['steps'][0]['checkoutFields'][0]['required'] );
		$this->assertSame( 'priority-upgrade', $package['steps'][0]['orderBumps'][0]['id'] );
		$this->assertSame( 456, $package['steps'][0]['orderBumps'][0]['product_id'] );
		$this->assertSame( 'percentage', $package['steps'][0]['orderBumps'][0]['discount_type'] );
		$this->assertSame( 15.5, $package['steps'][0]['orderBumps'][0]['discount_amount'] );
		$this->assertTrue( $package['steps'][0]['orderBumps'][0]['enabled'] );
		$this->assertSame( 'pre-checkout-upgrade', $package['steps'][0]['offer']['id'] );
		$this->assertSame( 789, $package['steps'][0]['offer']['product_id'] );
		$this->assertSame( 'fixed', $package['steps'][0]['offer']['discount_type'] );
	}

	/**
	 * @return void
	 */
	public function test_invalid_format_returns_error(): void {
		$validator = new Package_Validator();
		$package   = $this->package();
		$package['format'] = 'not-librefunnels';
		$result = $validator->normalize( $package );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_package_format', $result->get_error_code() );
	}

	/**
	 * @return void
	 */
	public function test_invalid_step_type_falls_back_to_landing(): void {
		$validator = new Package_Validator();
		$package   = $this->package();
		$package['steps'][0]['type'] = 'dangerous<script>';

		$result = $validator->normalize( $package );

		$this->assertIsArray( $result );
		$this->assertSame( 'landing', $result['steps'][0]['type'] );
	}

	/**
	 * Gets a valid package fixture.
	 *
	 * @return array<string,mixed>
	 */
	private function package(): array {
		return array(
			'format'      => Package_Validator::FORMAT,
			'version'     => Package_Validator::VERSION,
			'generatedBy' => 'LibreFunnels test',
			'funnel'      => array(
				'title'       => 'Checkout Flow',
				'status'      => 'publish',
				'startStepId' => 10,
				'graph'       => array(
					'version' => 1,
					'nodes'   => array(
						array(
							'id'       => 'thank-you-node',
							'stepId'   => 10,
							'type'     => 'thank_you',
							'position' => array(
								'x' => 0,
								'y' => 0,
							),
						),
					),
					'edges'   => array(),
				),
			),
			'steps'       => array(
				array(
					'originalId' => 10,
					'title'      => 'Thanks',
					'content'    => '<p>Order received.</p>',
					'excerpt'    => '',
					'status'     => 'publish',
					'type'       => 'thank_you',
					'order'      => 1,
					'template'   => 'clean',
					'pageId'     => 777,
					'checkoutProducts' => array(
						array(
							'product_id'   => 123,
							'variation_id' => 0,
							'quantity'     => 2,
							'variation'    => array(
								'attribute_pa_color' => 'blue',
							),
						),
					),
					'checkoutCoupons'  => array( 'SAVE10', 'SAVE10', '' ),
					'checkoutFields'   => array(
						array(
							'section'     => 'billing',
							'key'         => 'billing_phone',
							'label'       => 'Phone number',
							'placeholder' => 'Best phone',
							'required'    => false,
							'hidden'      => false,
						),
					),
					'orderBumps'       => array(
						array(
							'id'              => 'priority-upgrade',
							'product_id'      => 456,
							'variation_id'    => 0,
							'quantity'        => 1,
							'variation'       => array(),
							'title'           => 'Add priority setup',
							'description'     => '<strong>Setup help</strong> within one business day.',
							'discount_type'   => 'percentage',
							'discount_amount' => 15.5,
							'enabled'         => true,
						),
					),
					'offer'            => array(
						'id'              => 'pre-checkout-upgrade',
						'product_id'      => 789,
						'variation_id'    => 0,
						'quantity'        => 1,
						'variation'       => array(),
						'title'           => 'Add the starter kit',
						'description'     => '<p>Everything needed before checkout.</p>',
						'discount_type'   => 'fixed',
						'discount_amount' => 10,
						'enabled'         => true,
					),
				),
			),
		);
	}
}
