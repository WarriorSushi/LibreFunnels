<?php
/**
 * Funnel router.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Routing;

use LibreFunnels\Domain\Registered_Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves start and next steps from funnel records and graph metadata.
 */
final class Funnel_Router {
	/**
	 * Graph validator.
	 *
	 * @var Graph_Validator
	 */
	private $validator;

	/**
	 * Creates the router.
	 *
	 * @param Graph_Validator|null $validator Optional graph validator.
	 */
	public function __construct( Graph_Validator $validator = null ) {
		$this->validator = $validator ? $validator : new Graph_Validator();
	}

	/**
	 * Resolves a funnel's configured start step.
	 *
	 * @param int $funnel_id Funnel ID.
	 * @return Routing_Result
	 */
	public function get_start_step( $funnel_id ) {
		$funnel_id = absint( $funnel_id );
		$funnel    = $this->get_funnel_post( $funnel_id );

		if ( ! $funnel ) {
			return Routing_Result::failure( 'funnel_not_found', __( 'The requested funnel does not exist.', 'librefunnels' ) );
		}

		$start_step_id  = absint( get_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_START_STEP_META, true ) );
		$step_funnel_ids = $this->get_step_funnel_ids( array(), array( $start_step_id ) );

		return $this->validator->validate_start_step( $funnel_id, $start_step_id, $step_funnel_ids );
	}

	/**
	 * Resolves the next step for a route from the current step.
	 *
	 * @param int    $funnel_id       Funnel ID.
	 * @param int    $current_step_id Current step ID.
	 * @param string $route           Route label.
	 * @return Routing_Result
	 */
	public function get_next_step( $funnel_id, $current_step_id, $route ) {
		$funnel_id       = absint( $funnel_id );
		$current_step_id = absint( $current_step_id );
		$funnel          = $this->get_funnel_post( $funnel_id );

		if ( ! $funnel ) {
			return Routing_Result::failure( 'funnel_not_found', __( 'The requested funnel does not exist.', 'librefunnels' ) );
		}

		$graph           = $this->get_funnel_graph( $funnel_id );
		$step_funnel_ids = $this->get_step_funnel_ids( $graph, array( $current_step_id ) );

		return $this->validator->resolve_next_step( $funnel_id, $graph, $current_step_id, $route, $step_funnel_ids );
	}

	/**
	 * Gets a funnel post if the ID points to a LibreFunnels funnel.
	 *
	 * @param int $funnel_id Funnel ID.
	 * @return \WP_Post|null
	 */
	private function get_funnel_post( $funnel_id ) {
		$post = get_post( absint( $funnel_id ) );

		if ( ! $post || LIBREFUNNELS_FUNNEL_POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Gets the sanitized graph for a funnel.
	 *
	 * @param int $funnel_id Funnel ID.
	 * @return array<string,mixed>
	 */
	private function get_funnel_graph( $funnel_id ) {
		$graph = get_post_meta( absint( $funnel_id ), LIBREFUNNELS_FUNNEL_GRAPH_META, true );

		return Registered_Meta::sanitize_graph( $graph );
	}

	/**
	 * Builds step ownership map for required and graph-referenced steps.
	 *
	 * @param array $graph             Funnel graph.
	 * @param int[] $required_step_ids Required step IDs.
	 * @return array<int,int>
	 */
	private function get_step_funnel_ids( array $graph, array $required_step_ids = array() ) {
		$step_ids = array_map( 'absint', $required_step_ids );

		if ( isset( $graph['nodes'] ) && is_array( $graph['nodes'] ) ) {
			foreach ( $graph['nodes'] as $node ) {
				if ( is_object( $node ) ) {
					$node = (array) $node;
				}

				if ( is_array( $node ) && isset( $node['stepId'] ) ) {
					$step_ids[] = absint( $node['stepId'] );
				}
			}
		}

		$step_ids        = array_unique( array_filter( $step_ids ) );
		$step_funnel_ids = array();

		foreach ( $step_ids as $step_id ) {
			$step = get_post( $step_id );

			if ( ! $step || LIBREFUNNELS_STEP_POST_TYPE !== $step->post_type ) {
				continue;
			}

			$step_funnel_ids[ $step_id ] = absint( get_post_meta( $step_id, LIBREFUNNELS_STEP_FUNNEL_ID_META, true ) );
		}

		return $step_funnel_ids;
	}
}
