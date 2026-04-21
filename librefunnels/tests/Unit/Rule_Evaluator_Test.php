<?php
/**
 * Rule evaluator tests.
 *
 * @package LibreFunnels\Tests
 */

namespace LibreFunnels\Tests\Unit;

use LibreFunnels\Rules\Rule_Evaluator;
use PHPUnit\Framework\TestCase;

/**
 * Tests structured rule evaluation.
 */
final class Rule_Evaluator_Test extends TestCase {
	/**
	 * @return void
	 */
	public function test_all_rule_matches_when_every_child_matches(): void {
		$evaluator = new Rule_Evaluator();
		$result    = $evaluator->evaluate(
			array(
				'type'  => 'all',
				'rules' => array(
					array(
						'type'       => 'cart_contains_product',
						'product_id' => 123,
					),
					array(
						'type'   => 'cart_subtotal_gte',
						'amount' => 50,
					),
				),
			),
			array(
				'cart_product_ids' => array( 123, 456 ),
				'cart_subtotal'    => 80,
			)
		);

		$this->assertTrue( $result->is_match() );
		$this->assertSame( 'all_rule_matched', $result->get_code() );
	}

	/**
	 * @return void
	 */
	public function test_any_rule_matches_when_one_child_matches(): void {
		$evaluator = new Rule_Evaluator();
		$result    = $evaluator->evaluate(
			array(
				'type'  => 'any',
				'rules' => array(
					array(
						'type'       => 'cart_contains_product',
						'product_id' => 999,
					),
					array(
						'type'   => 'cart_subtotal_lte',
						'amount' => 100,
					),
				),
			),
			array(
				'cart_product_ids' => array( 123 ),
				'cart_subtotal'    => 80,
			)
		);

		$this->assertTrue( $result->is_match() );
		$this->assertSame( 'any_rule_matched', $result->get_code() );
	}

	/**
	 * @return void
	 */
	public function test_unknown_rule_type_does_not_match(): void {
		$evaluator = new Rule_Evaluator();
		$result    = $evaluator->evaluate(
			array(
				'type' => 'mystery',
			)
		);

		$this->assertFalse( $result->is_match() );
		$this->assertSame( 'unknown_rule_type', $result->get_code() );
	}

	/**
	 * @return void
	 */
	public function test_customer_logged_in_rule_reads_fact(): void {
		$evaluator = new Rule_Evaluator();

		$this->assertTrue(
			$evaluator->evaluate(
				array(
					'type' => 'customer_logged_in',
				),
				array(
					'customer_logged_in' => true,
				)
			)->is_match()
		);
	}
}
