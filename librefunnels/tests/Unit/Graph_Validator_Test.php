<?php
/**
 * Graph validator tests.
 *
 * @package LibreFunnels\Tests
 */

namespace LibreFunnels\Tests\Unit;

use LibreFunnels\Routing\Graph_Validator;
use PHPUnit\Framework\TestCase;

/**
 * Tests pure funnel graph validation and route resolution.
 */
final class Graph_Validator_Test extends TestCase {
	/**
	 * Validator under test.
	 *
	 * @var Graph_Validator
	 */
	private $validator;

	/**
	 * Sets up the test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->validator = new Graph_Validator();
	}

	/**
	 * @return void
	 */
	public function test_valid_start_step_resolves(): void {
		$result = $this->validator->validate_start_step( 99, 10, $this->step_funnel_ids() );

		$this->assertTrue( $result->is_success() );
		$this->assertSame( 10, $result->get_step_id() );
		$this->assertSame( 'start_step_resolved', $result->get_code() );
	}

	/**
	 * @return void
	 */
	public function test_missing_start_step_fails(): void {
		$result = $this->validator->validate_start_step( 99, 0, $this->step_funnel_ids() );

		$this->assertFalse( $result->is_success() );
		$this->assertSame( 'missing_start_step', $result->get_code() );
	}

	/**
	 * @return void
	 */
	public function test_step_owned_by_another_funnel_fails(): void {
		$result = $this->validator->validate_start_step( 99, 40, $this->step_funnel_ids() );

		$this->assertFalse( $result->is_success() );
		$this->assertSame( 'step_not_in_funnel', $result->get_code() );
	}

	/**
	 * @return void
	 */
	public function test_valid_edge_resolves_target_step(): void {
		$result = $this->validator->resolve_next_step( 99, $this->graph(), 10, 'next', $this->step_funnel_ids() );

		$this->assertTrue( $result->is_success() );
		$this->assertSame( 20, $result->get_step_id() );
		$this->assertSame( 'next_step_resolved', $result->get_code() );
	}

	/**
	 * @return void
	 */
	public function test_missing_route_uses_fallback_when_available(): void {
		$result = $this->validator->resolve_next_step( 99, $this->graph(), 20, 'reject', $this->step_funnel_ids() );

		$this->assertTrue( $result->is_success() );
		$this->assertSame( 30, $result->get_step_id() );
	}

	/**
	 * @return void
	 */
	public function test_missing_route_without_fallback_fails(): void {
		$graph = $this->graph();
		array_pop( $graph['edges'] );

		$result = $this->validator->resolve_next_step( 99, $graph, 20, 'reject', $this->step_funnel_ids() );

		$this->assertFalse( $result->is_success() );
		$this->assertSame( 'route_not_found', $result->get_code() );
	}

	/**
	 * @return void
	 */
	public function test_edge_referencing_missing_target_fails_validation(): void {
		$graph = $this->graph();
		$graph['edges'][0]['target'] = 'missing-node';

		$result = $this->validator->validate_graph( 99, $graph, $this->step_funnel_ids() );

		$this->assertFalse( $result->is_success() );
		$this->assertSame( 'graph_edge_invalid', $result->get_code() );
	}

	/**
	 * @return void
	 */
	public function test_unknown_route_fails_without_mutation(): void {
		$result = $this->validator->resolve_next_step( 99, $this->graph(), 10, 'sideways', $this->step_funnel_ids() );

		$this->assertFalse( $result->is_success() );
		$this->assertSame( 'unknown_route', $result->get_code() );
	}

	/**
	 * Gets a valid graph fixture.
	 *
	 * @return array<string,mixed>
	 */
	private function graph(): array {
		return array(
			'version' => 1,
			'nodes'   => array(
				array(
					'id'       => 'landing-node',
					'stepId'   => 10,
					'type'     => 'landing',
					'position' => array(
						'x' => 0,
						'y' => 0,
					),
				),
				array(
					'id'       => 'checkout-node',
					'stepId'   => 20,
					'type'     => 'checkout',
					'position' => array(
						'x' => 320,
						'y' => 0,
					),
				),
				array(
					'id'       => 'thank-you-node',
					'stepId'   => 30,
					'type'     => 'thank_you',
					'position' => array(
						'x' => 640,
						'y' => 0,
					),
				),
			),
			'edges'   => array(
				array(
					'id'     => 'edge-landing-checkout',
					'source' => 'landing-node',
					'target' => 'checkout-node',
					'route'  => 'next',
				),
				array(
					'id'     => 'edge-checkout-thank-you',
					'source' => 'checkout-node',
					'target' => 'thank-you-node',
					'route'  => 'accept',
				),
				array(
					'id'     => 'edge-checkout-fallback',
					'source' => 'checkout-node',
					'target' => 'thank-you-node',
					'route'  => 'fallback',
				),
			),
		);
	}

	/**
	 * Gets step ownership fixture.
	 *
	 * @return array<int,int>
	 */
	private function step_funnel_ids(): array {
		return array(
			10 => 99,
			20 => 99,
			30 => 99,
			40 => 100,
		);
	}
}
