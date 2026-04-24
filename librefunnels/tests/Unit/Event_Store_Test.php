<?php
/**
 * Event store tests.
 *
 * @package LibreFunnels\Tests
 */

namespace {
	if ( ! defined( 'DAY_IN_SECONDS' ) ) {
		define( 'DAY_IN_SECONDS', 86400 );
	}

	if ( ! defined( 'ARRAY_A' ) ) {
		define( 'ARRAY_A', 'ARRAY_A' );
	}
}

namespace LibreFunnels\Tests\Unit {
	use LibreFunnels\Analytics\Event_Store;
	use PHPUnit\Framework\TestCase;

	/**
	 * Tests local analytics summary shaping.
	 */
	final class Event_Store_Test extends TestCase {
		/**
		 * Original wpdb object.
		 *
		 * @var mixed
		 */
		private $previous_wpdb;

		/**
		 * @return void
		 */
		protected function setUp(): void {
			parent::setUp();

			$this->previous_wpdb = isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;
		}

		/**
		 * @return void
		 */
		protected function tearDown(): void {
			if ( null === $this->previous_wpdb ) {
				unset( $GLOBALS['wpdb'] );
			} else {
				$GLOBALS['wpdb'] = $this->previous_wpdb;
			}

			parent::tearDown();
		}

		/**
		 * @return void
		 */
		public function test_dashboard_summary_includes_source_and_step_breakdowns(): void {
			$GLOBALS['wpdb'] = new Fake_Analytics_WPDB(
				array(
					array(
						'event_type'  => 'offer_impression',
						'event_count' => 4,
						'event_value' => 0,
					),
					array(
						'event_type'  => 'offer_accept',
						'event_count' => 1,
						'event_value' => 0,
					),
					array(
						'event_type'  => 'offer_reject',
						'event_count' => 1,
						'event_value' => 0,
					),
					array(
						'event_type'  => 'order_revenue',
						'event_count' => 1,
						'event_value' => 165,
					),
				),
				array(
					$this->event_row( 'offer_impression', 13 ),
					$this->event_row( 'offer_impression', 13 ),
					$this->event_row( 'offer_accept', 13 ),
					$this->event_row( 'offer_reject', 13 ),
					$this->event_row(
						'order_revenue',
						12,
						165,
						array(
							'lines' => array(
								array(
									'source'  => 'checkout_product',
									'step_id' => 12,
									'total'   => 100,
								),
								array(
									'source'  => 'order_bump',
									'step_id' => 12,
									'total'   => 25,
								),
								array(
									'source'  => 'offer',
									'step_id' => 13,
									'total'   => 40,
								),
							),
						)
					),
				),
				array(
					array(
						'event_type'  => 'offer_impression',
						'event_count' => 2,
						'event_value' => 0,
					),
					array(
						'event_type'  => 'offer_accept',
						'event_count' => 1,
						'event_value' => 0,
					),
					array(
						'event_type'  => 'order_revenue',
						'event_count' => 1,
						'event_value' => 100,
					),
				)
			);

			$summary = ( new Event_Store() )->get_dashboard_summary(
				array(
					'funnel_id' => '5',
					'days'      => 999,
				)
			);

			$this->assertSame( 365, $summary['period']['days'] );
			$this->assertSame( 5, $summary['funnelId'] );
			$this->assertSame( 165.0, $summary['revenue'] );
			$this->assertSame( 25.0, $summary['offerAcceptRate'] );
			$this->assertSame( 100.0, $summary['comparison']['revenue']['previous'] );
			$this->assertSame( 65.0, $summary['comparison']['revenue']['delta'] );
			$this->assertSame( 65.0, $summary['comparison']['revenue']['deltaPercent'] );
			$this->assertSame( 50.0, $summary['comparison']['offerAcceptRate']['previous'] );
			$this->assertSame( -25.0, $summary['comparison']['offerAcceptRate']['delta'] );
			$this->assertSame( 100.0, $summary['sourceRevenue']['checkout_product'] );
			$this->assertSame( 25.0, $summary['sourceRevenue']['order_bump'] );
			$this->assertSame( 40.0, $summary['sourceRevenue']['offer'] );

			$this->assertSame( 12, $summary['stepBreakdown'][0]['stepId'] );
			$this->assertSame( 125.0, $summary['stepBreakdown'][0]['revenue'] );
			$this->assertSame( 100.0, $summary['stepBreakdown'][0]['checkoutRevenue'] );
			$this->assertSame( 25.0, $summary['stepBreakdown'][0]['bumpRevenue'] );

			$this->assertSame( 13, $summary['stepBreakdown'][1]['stepId'] );
			$this->assertSame( 40.0, $summary['stepBreakdown'][1]['offerRevenue'] );
			$this->assertSame( 2, $summary['stepBreakdown'][1]['offerImpressions'] );
			$this->assertSame( 1, $summary['stepBreakdown'][1]['offerAccepts'] );
			$this->assertSame( 1, $summary['stepBreakdown'][1]['offerRejects'] );
			$this->assertSame( 50.0, $summary['stepBreakdown'][1]['offerAcceptRate'] );
		}

		/**
		 * @param string              $event_type Event type.
		 * @param int                 $step_id    Step ID.
		 * @param float               $value      Event value.
		 * @param array<string,mixed> $context    Event context.
		 * @return array<string,mixed>
		 */
		private function event_row( $event_type, $step_id, $value = 0.0, array $context = array() ): array {
			return array(
				'event_type' => $event_type,
				'step_id'    => $step_id,
				'value'      => $value,
				'context'    => json_encode( $context ),
			);
		}
	}

	/**
	 * Fake wpdb for analytics summary tests.
	 */
	final class Fake_Analytics_WPDB {
		/**
		 * Database prefix.
		 *
		 * @var string
		 */
		public $prefix = 'wp_';

		/**
		 * Count rows.
		 *
		 * @var array<int,array<string,mixed>>
		 */
		private $count_rows;

		/**
		 * Detail rows.
		 *
		 * @var array<int,array<string,mixed>>
		 */
		private $detail_rows;

		/**
		 * Previous period count rows.
		 *
		 * @var array<int,array<string,mixed>>
		 */
		private $previous_count_rows;

		/**
		 * Count query call index.
		 *
		 * @var int
		 */
		private $count_query_index = 0;

		/**
		 * @param array<int,array<string,mixed>> $count_rows          Count rows.
		 * @param array<int,array<string,mixed>> $detail_rows         Detail rows.
		 * @param array<int,array<string,mixed>> $previous_count_rows Previous period count rows.
		 */
		public function __construct( array $count_rows, array $detail_rows, array $previous_count_rows = array() ) {
			$this->count_rows          = $count_rows;
			$this->detail_rows         = $detail_rows;
			$this->previous_count_rows = $previous_count_rows;
		}

		/**
		 * @param string       $query  SQL query.
		 * @param array<mixed> $params Query params.
		 * @return string
		 */
		public function prepare( $query, $params = array() ) {
			unset( $params );

			return $query;
		}

		/**
		 * @param string $query  SQL query.
		 * @param string $output Output type.
		 * @return array<int,array<string,mixed>>
		 */
		public function get_results( $query, $output = ARRAY_A ) {
			unset( $output );

			if ( false !== strpos( $query, 'GROUP BY event_type' ) ) {
				++$this->count_query_index;

				return 1 === $this->count_query_index ? $this->count_rows : $this->previous_count_rows;
			}

			return $this->detail_rows;
		}
	}
}
