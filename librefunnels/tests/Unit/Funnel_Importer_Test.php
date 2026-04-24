<?php
/**
 * Funnel importer tests.
 *
 * @package LibreFunnels\Tests
 */

namespace {
	if ( ! function_exists( 'wp_parse_args' ) ) {
		/**
		 * wp_parse_args fallback for isolated importer tests.
		 *
		 * @param mixed $args     Arguments.
		 * @param mixed $defaults Defaults.
		 * @return array<string,mixed>
		 */
		function wp_parse_args( $args, $defaults = array() ) {
			if ( is_object( $args ) ) {
				$args = get_object_vars( $args );
			}

			if ( is_object( $defaults ) ) {
				$defaults = get_object_vars( $defaults );
			}

			$args     = is_array( $args ) ? $args : array();
			$defaults = is_array( $defaults ) ? $defaults : array();

			return array_merge( $defaults, $args );
		}
	}

	if ( ! function_exists( 'wp_insert_post' ) ) {
		/**
		 * wp_insert_post fallback backed by in-memory globals.
		 *
		 * @param array<string,mixed> $postarr  Post data.
		 * @param bool                $wp_error Whether to return WP_Error on failure.
		 * @return int|\WP_Error
		 */
		function wp_insert_post( $postarr, $wp_error = false ) {
			if ( ! empty( $GLOBALS['librefunnels_test_wp_insert_error'] ) ) {
				return $wp_error ? new \WP_Error( 'insert_failed', 'Insert failed.' ) : 0;
			}

			if ( ! isset( $GLOBALS['librefunnels_test_next_post_id'] ) ) {
				$GLOBALS['librefunnels_test_next_post_id'] = 1000;
			}

			$post_id = ++$GLOBALS['librefunnels_test_next_post_id'];

			$GLOBALS['librefunnels_test_posts'][ $post_id ] = (object) array_merge(
				array(
					'ID'           => $post_id,
					'post_type'    => 'post',
					'post_title'   => '',
					'post_content' => '',
					'post_excerpt' => '',
					'post_status'  => 'draft',
				),
				$postarr,
				array(
					'ID' => $post_id,
				)
			);

			return $post_id;
		}
	}

	if ( ! function_exists( 'update_post_meta' ) ) {
		/**
		 * update_post_meta fallback backed by in-memory globals.
		 *
		 * @param int    $post_id Post ID.
		 * @param string $key     Meta key.
		 * @param mixed  $value   Meta value.
		 * @return bool
		 */
		function update_post_meta( $post_id, $key, $value ) {
			$GLOBALS['librefunnels_test_post_meta'][ absint( $post_id ) ][ (string) $key ] = $value;

			return true;
		}
	}
}

namespace LibreFunnels\Tests\Unit {
	use LibreFunnels\ImportExport\Funnel_Importer;
	use LibreFunnels\ImportExport\Package_Validator;
	use PHPUnit\Framework\TestCase;

	/**
	 * Tests safe funnel imports and template option handling.
	 */
	final class Funnel_Importer_Test extends TestCase {
		/**
		 * @return void
		 */
		protected function setUp(): void {
			parent::setUp();

			$GLOBALS['librefunnels_test_next_post_id'] = 1000;
			$GLOBALS['librefunnels_test_posts']        = array();
			$GLOBALS['librefunnels_test_post_meta']    = array();
			$GLOBALS['librefunnels_test_products']     = array(
				101 => (object) array( 'id' => 101 ),
				202 => (object) array( 'id' => 202 ),
			);
			unset( $GLOBALS['librefunnels_test_wp_insert_error'] );
		}

		/**
		 * @return void
		 */
		protected function tearDown(): void {
			unset(
				$GLOBALS['librefunnels_test_next_post_id'],
				$GLOBALS['librefunnels_test_posts'],
				$GLOBALS['librefunnels_test_post_meta'],
				$GLOBALS['librefunnels_test_products'],
				$GLOBALS['librefunnels_test_wp_insert_error']
			);

			parent::tearDown();
		}

		/**
		 * @return void
		 */
		public function test_import_creates_draft_pages_and_applies_existing_template_products(): void {
			$importer = new Funnel_Importer();

			$result = $importer->import(
				$this->package(),
				array(
					'title'             => '<strong>Guided Starter</strong>',
					'createPages'       => true,
					'forceDraft'        => true,
					'checkoutProductId' => 101,
					'offerProductId'    => 202,
				)
			);

			$this->assertIsArray( $result );
			$this->assertCount( 4, $result['stepIds'] );
			$this->assertCount( 4, $result['pageIds'] );

			$funnel_id        = $result['funnelId'];
			$landing_step_id  = $result['stepIds'][11];
			$checkout_step_id = $result['stepIds'][12];
			$upsell_step_id   = $result['stepIds'][13];
			$checkout_page_id = $result['pageIds'][12];

			$this->assertSame( 'Guided Starter', $GLOBALS['librefunnels_test_posts'][ $funnel_id ]->post_title );
			$this->assertSame( 'draft', $GLOBALS['librefunnels_test_posts'][ $funnel_id ]->post_status );
			$this->assertSame( 'page', $GLOBALS['librefunnels_test_posts'][ $checkout_page_id ]->post_type );
			$this->assertSame( '[librefunnels_step id="' . $checkout_step_id . '"]', $GLOBALS['librefunnels_test_posts'][ $checkout_page_id ]->post_content );

			$this->assertSame( $landing_step_id, $GLOBALS['librefunnels_test_post_meta'][ $funnel_id ][ LIBREFUNNELS_FUNNEL_START_STEP_META ] );
			$this->assertSame( $checkout_page_id, $GLOBALS['librefunnels_test_post_meta'][ $checkout_step_id ][ LIBREFUNNELS_STEP_PAGE_ID_META ] );

			$graph = $GLOBALS['librefunnels_test_post_meta'][ $funnel_id ][ LIBREFUNNELS_FUNNEL_GRAPH_META ];
			$this->assertSame( 'node-' . $landing_step_id, $graph['edges'][0]['source'] );
			$this->assertSame( 'node-' . $checkout_step_id, $graph['edges'][0]['target'] );

			$checkout_products = $GLOBALS['librefunnels_test_post_meta'][ $checkout_step_id ][ LIBREFUNNELS_CHECKOUT_PRODUCTS_META ];
			$this->assertSame( 101, $checkout_products[0]['product_id'] );
			$this->assertSame( 1, $checkout_products[0]['quantity'] );

			$offer = $GLOBALS['librefunnels_test_post_meta'][ $upsell_step_id ][ LIBREFUNNELS_STEP_OFFER_META ];
			$this->assertSame( 202, $offer['product_id'] );
			$this->assertSame( 3, $offer['quantity'] );
			$this->assertTrue( $offer['enabled'] );
		}

		/**
		 * @return void
		 */
		public function test_missing_template_products_do_not_override_package_products(): void {
			$importer = new Funnel_Importer();

			$result = $importer->import(
				$this->package(),
				array(
					'checkoutProductId' => 909,
					'offerProductId'    => 919,
				)
			);

			$this->assertIsArray( $result );

			$checkout_step_id = $result['stepIds'][12];
			$upsell_step_id   = $result['stepIds'][13];

			$checkout_products = $GLOBALS['librefunnels_test_post_meta'][ $checkout_step_id ][ LIBREFUNNELS_CHECKOUT_PRODUCTS_META ];
			$offer             = $GLOBALS['librefunnels_test_post_meta'][ $upsell_step_id ][ LIBREFUNNELS_STEP_OFFER_META ];

			$this->assertSame( 999, $checkout_products[0]['product_id'] );
			$this->assertSame( 888, $offer['product_id'] );
		}

		/**
		 * @return void
		 */
		public function test_create_pages_option_can_disable_page_side_effects(): void {
			$importer = new Funnel_Importer();

			$result = $importer->import(
				$this->package(),
				array(
					'createPages' => false,
				)
			);

			$this->assertIsArray( $result );
			$this->assertSame( array(), $result['pageIds'] );

			$checkout_step_id = $result['stepIds'][12];
			$this->assertSame( 0, $GLOBALS['librefunnels_test_post_meta'][ $checkout_step_id ][ LIBREFUNNELS_STEP_PAGE_ID_META ] );
		}

		/**
		 * @return void
		 */
		public function test_invalid_package_returns_error_without_creating_posts(): void {
			$importer = new Funnel_Importer();

			$result = $importer->import( '{"format":"not-librefunnels"}' );

			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'invalid_package_format', $result->get_error_code() );
			$this->assertSame( array(), $GLOBALS['librefunnels_test_posts'] );
			$this->assertSame( array(), $GLOBALS['librefunnels_test_post_meta'] );
		}

		/**
		 * Gets a portable package fixture.
		 *
		 * @return array<string,mixed>
		 */
		private function package(): array {
			return array(
				'format'      => Package_Validator::FORMAT,
				'version'     => Package_Validator::VERSION,
				'generatedBy' => 'LibreFunnels test',
				'funnel'      => array(
					'title'       => 'Imported Starter',
					'status'      => 'publish',
					'startStepId' => 11,
					'graph'       => array(
						'version' => 1,
						'nodes'   => array(
							$this->node( 'landing-node', 11, 'landing', 80, 120 ),
							$this->node( 'checkout-node', 12, 'checkout', 360, 120 ),
							$this->node( 'upsell-node', 13, 'upsell', 640, 120 ),
							$this->node( 'thanks-node', 14, 'thank_you', 920, 120 ),
						),
						'edges'   => array(
							$this->edge( 'edge-1', 'landing-node', 'checkout-node', 'next' ),
							$this->edge( 'edge-2', 'checkout-node', 'upsell-node', 'accept' ),
							$this->edge( 'edge-3', 'upsell-node', 'thanks-node', 'next' ),
						),
					),
				),
				'steps'       => array(
					$this->step( 11, 'Landing', 'landing', 1 ),
					array_merge(
						$this->step( 12, 'Checkout', 'checkout', 2 ),
						array(
							'checkoutProducts' => array(
								array(
									'product_id' => 999,
									'quantity'   => 2,
								),
							),
						)
					),
					array_merge(
						$this->step( 13, 'Upsell', 'upsell', 3 ),
						array(
							'offer' => array(
								'id'         => 'main-upgrade',
								'product_id' => 888,
								'quantity'   => 3,
								'title'      => 'Add the upgrade',
								'enabled'    => false,
							),
						)
					),
					$this->step( 14, 'Thank You', 'thank_you', 4 ),
				),
			);
		}

		/**
		 * @param int    $id    Original ID.
		 * @param string $title Step title.
		 * @param string $type  Step type.
		 * @param int    $order Step order.
		 * @return array<string,mixed>
		 */
		private function step( $id, $title, $type, $order ): array {
			return array(
				'originalId'       => $id,
				'title'            => $title,
				'content'          => '<!-- wp:paragraph --><p>' . $title . '</p><!-- /wp:paragraph -->',
				'excerpt'          => '',
				'status'           => 'publish',
				'type'             => $type,
				'order'            => $order,
				'template'         => 'starter',
				'checkoutProducts' => array(),
				'checkoutCoupons'  => array(),
				'checkoutFields'   => array(),
				'orderBumps'       => array(),
				'offer'            => array(),
			);
		}

		/**
		 * @param string $id      Node ID.
		 * @param int    $step_id Step ID.
		 * @param string $type    Step type.
		 * @param int    $x       X coordinate.
		 * @param int    $y       Y coordinate.
		 * @return array<string,mixed>
		 */
		private function node( $id, $step_id, $type, $x, $y ): array {
			return array(
				'id'       => $id,
				'stepId'   => $step_id,
				'type'     => $type,
				'position' => array(
					'x' => $x,
					'y' => $y,
				),
			);
		}

		/**
		 * @param string $id     Edge ID.
		 * @param string $source Source node ID.
		 * @param string $target Target node ID.
		 * @param string $route  Route type.
		 * @return array<string,string>
		 */
		private function edge( $id, $source, $target, $route ): array {
			return array(
				'id'     => $id,
				'source' => $source,
				'target' => $target,
				'route'  => $route,
			);
		}
	}
}
