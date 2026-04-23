<?php
/**
 * Funnel importer.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\ImportExport;

use LibreFunnels\Domain\Registered_Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Safely imports funnel packages into local posts and pages.
 */
final class Funnel_Importer {
	/**
	 * Package validator.
	 *
	 * @var Package_Validator
	 */
	private $validator;

	/**
	 * Creates the importer.
	 *
	 * @param Package_Validator|null $validator Optional package validator.
	 */
	public function __construct( Package_Validator $validator = null ) {
		$this->validator = $validator ? $validator : new Package_Validator();
	}

	/**
	 * Imports a funnel package.
	 *
	 * @param array<string,mixed>|string $package Package data or JSON string.
	 * @param array<string,mixed>        $options Import options.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function import( $package, array $options = array() ) {
		$package = $this->validator->normalize( $package );

		if ( is_wp_error( $package ) ) {
			return $package;
		}

		$options = wp_parse_args(
			$options,
			array(
				'title'       => '',
				'createPages' => true,
				'forceDraft'  => true,
			)
		);

		$funnel_title = isset( $options['title'] ) && '' !== trim( (string) $options['title'] )
			? sanitize_text_field( (string) $options['title'] )
			: sanitize_text_field( (string) $package['funnel']['title'] );

		$funnel_id = wp_insert_post(
			array(
				'post_type'   => LIBREFUNNELS_FUNNEL_POST_TYPE,
				'post_title'  => $funnel_title ? $funnel_title : __( 'Imported funnel', 'librefunnels' ),
				'post_status' => ! empty( $options['forceDraft'] ) ? 'draft' : $this->sanitize_post_status( $package['funnel']['status'] ),
			),
			true
		);

		if ( is_wp_error( $funnel_id ) ) {
			return $funnel_id;
		}

		update_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_GRAPH_META, Registered_Meta::sanitize_graph( array() ) );
		update_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_START_STEP_META, 0 );

		$step_map = array();
		$page_map = array();

		foreach ( $package['steps'] as $step_package ) {
			$step_id = wp_insert_post(
				array(
					'post_type'    => LIBREFUNNELS_STEP_POST_TYPE,
					'post_title'   => sanitize_text_field( (string) $step_package['title'] ),
					'post_content' => wp_kses_post( (string) $step_package['content'] ),
					'post_excerpt' => wp_kses_post( (string) $step_package['excerpt'] ),
					'post_status'  => ! empty( $options['forceDraft'] ) ? 'draft' : $this->sanitize_post_status( $step_package['status'] ),
				),
				true
			);

			if ( is_wp_error( $step_id ) ) {
				return $step_id;
			}

			$original_id              = absint( $step_package['originalId'] );
			$step_map[ $original_id ] = absint( $step_id );

			update_post_meta( $step_id, LIBREFUNNELS_STEP_FUNNEL_ID_META, absint( $funnel_id ) );
			update_post_meta( $step_id, LIBREFUNNELS_STEP_TYPE_META, Registered_Meta::sanitize_step_type( $step_package['type'] ) );
			update_post_meta( $step_id, LIBREFUNNELS_STEP_ORDER_META, absint( $step_package['order'] ) );
			update_post_meta( $step_id, LIBREFUNNELS_STEP_TEMPLATE_META, sanitize_key( (string) $step_package['template'] ) );
			update_post_meta( $step_id, LIBREFUNNELS_CHECKOUT_PRODUCTS_META, Registered_Meta::sanitize_checkout_products( $step_package['checkoutProducts'] ) );
			update_post_meta( $step_id, LIBREFUNNELS_CHECKOUT_COUPONS_META, Registered_Meta::sanitize_coupon_codes( $step_package['checkoutCoupons'] ) );
			update_post_meta( $step_id, LIBREFUNNELS_CHECKOUT_FIELDS_META, Registered_Meta::sanitize_checkout_fields( $step_package['checkoutFields'] ) );
			update_post_meta( $step_id, LIBREFUNNELS_ORDER_BUMPS_META, Registered_Meta::sanitize_order_bumps( $step_package['orderBumps'] ) );
			update_post_meta( $step_id, LIBREFUNNELS_STEP_OFFER_META, Registered_Meta::sanitize_step_offer( $step_package['offer'] ) );
			update_post_meta( $step_id, LIBREFUNNELS_STEP_PAGE_ID_META, 0 );

			if ( ! empty( $options['createPages'] ) ) {
				$page_id = $this->create_step_page( $step_id, $step_package, $funnel_title );

				if ( is_wp_error( $page_id ) ) {
					return $page_id;
				}

				$page_map[ $original_id ] = absint( $page_id );
				update_post_meta( $step_id, LIBREFUNNELS_STEP_PAGE_ID_META, absint( $page_id ) );
			}
		}

		$graph = $this->remap_graph( $package['funnel']['graph'], $step_map );
		update_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_GRAPH_META, Registered_Meta::sanitize_graph( $graph ) );
		update_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_START_STEP_META, $this->remap_start_step_id( $package['funnel']['startStepId'], $step_map ) );

		return array(
			'funnelId' => absint( $funnel_id ),
			'stepIds'  => $step_map,
			'pageIds'  => $page_map,
		);
	}

	/**
	 * Creates a draft WordPress page for a step.
	 *
	 * @param int                 $step_id       Step ID.
	 * @param array<string,mixed> $step_package  Step package.
	 * @param string              $funnel_title  Funnel title.
	 * @return int|\WP_Error
	 */
	private function create_step_page( $step_id, array $step_package, $funnel_title ) {
		$page_title = trim(
			sprintf(
				/* translators: 1: funnel title, 2: step title. */
				__( '%1$s - %2$s', 'librefunnels' ),
				$funnel_title,
				sanitize_text_field( (string) $step_package['title'] )
			)
		);

		return wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => $page_title,
				'post_status'  => 'draft',
				'post_content' => '[librefunnels_step id="' . absint( $step_id ) . '"]',
			),
			true
		);
	}

	/**
	 * Remaps graph node and edge IDs after import.
	 *
	 * @param array<string,mixed> $graph    Graph data.
	 * @param array<int,int>      $step_map Original step IDs to new step IDs.
	 * @return array<string,mixed>
	 */
	private function remap_graph( array $graph, array $step_map ) {
		$graph       = Registered_Meta::sanitize_graph( $graph );
		$node_id_map = array();
		$nodes       = array();
		$edges       = array();

		foreach ( $graph['nodes'] as $index => $node ) {
			$original_step_id = absint( $node['stepId'] );
			$new_step_id      = isset( $step_map[ $original_step_id ] ) ? absint( $step_map[ $original_step_id ] ) : 0;

			if ( $new_step_id < 1 ) {
				continue;
			}

			$original_node_id                 = sanitize_key( (string) $node['id'] );
			$new_node_id                      = 'node-' . $new_step_id;
			$node_id_map[ $original_node_id ] = $new_node_id;
			$nodes[]                          = array(
				'id'       => $new_node_id,
				'stepId'   => $new_step_id,
				'type'     => Registered_Meta::sanitize_step_type( $node['type'] ),
				'position' => array(
					'x' => isset( $node['position']['x'] ) ? (float) $node['position']['x'] : 120 + $index * 260,
					'y' => isset( $node['position']['y'] ) ? (float) $node['position']['y'] : 160,
				),
			);
		}

		foreach ( $graph['edges'] as $index => $edge ) {
			$source = sanitize_key( (string) $edge['source'] );
			$target = sanitize_key( (string) $edge['target'] );

			if ( ! isset( $node_id_map[ $source ], $node_id_map[ $target ] ) ) {
				continue;
			}

			$edges[] = array(
				'id'     => sprintf( 'edge-imported-%d', $index + 1 ),
				'source' => $node_id_map[ $source ],
				'target' => $node_id_map[ $target ],
				'route'  => sanitize_key( (string) $edge['route'] ),
				'rule'   => isset( $edge['rule'] ) && is_array( $edge['rule'] ) ? $edge['rule'] : array(),
			);
		}

		return array(
			'version' => 1,
			'nodes'   => $nodes,
			'edges'   => $edges,
		);
	}

	/**
	 * Remaps the start step ID from package IDs to created step IDs.
	 *
	 * @param int            $start_step_id Original start step ID.
	 * @param array<int,int> $step_map Original step IDs to new step IDs.
	 * @return int
	 */
	private function remap_start_step_id( $start_step_id, array $step_map ) {
		$start_step_id = absint( $start_step_id );

		return isset( $step_map[ $start_step_id ] ) ? absint( $step_map[ $start_step_id ] ) : 0;
	}

	/**
	 * Sanitizes import post statuses.
	 *
	 * @param string $status Raw post status.
	 * @return string
	 */
	private function sanitize_post_status( $status ) {
		$status = sanitize_key( (string) $status );

		return in_array( $status, array( 'draft', 'publish', 'private', 'pending' ), true ) ? $status : 'draft';
	}
}
