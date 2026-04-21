<?php
/**
 * Funnel graph validation and route resolution.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Routing;

use LibreFunnels\Rules\Rule_Evaluator;

defined( 'ABSPATH' ) || exit;

/**
 * Validates graph shape and resolves graph routes without mutating data.
 */
final class Graph_Validator {
	/**
	 * Rule evaluator.
	 *
	 * @var Rule_Evaluator
	 */
	private $rule_evaluator;

	/**
	 * Creates the validator.
	 *
	 * @param Rule_Evaluator|null $rule_evaluator Optional rule evaluator.
	 */
	public function __construct( Rule_Evaluator $rule_evaluator = null ) {
		$this->rule_evaluator = $rule_evaluator ? $rule_evaluator : new Rule_Evaluator();
	}

	/**
	 * Returns supported route labels.
	 *
	 * @return string[]
	 */
	public static function get_allowed_routes() {
		return array(
			'next',
			'accept',
			'reject',
			'conditional',
			'fallback',
		);
	}

	/**
	 * Validates the configured start step.
	 *
	 * @param int   $funnel_id       Funnel ID.
	 * @param int   $start_step_id   Start step ID.
	 * @param array $step_funnel_ids Map of step ID to owning funnel ID.
	 * @return Routing_Result
	 */
	public function validate_start_step( $funnel_id, $start_step_id, array $step_funnel_ids ) {
		$funnel_id     = $this->absint( $funnel_id );
		$start_step_id = $this->absint( $start_step_id );

		if ( 0 === $start_step_id ) {
			return Routing_Result::failure( 'missing_start_step', __( 'This funnel does not have a start step configured.', 'librefunnels' ) );
		}

		$ownership = $this->validate_step_ownership( $funnel_id, $start_step_id, $step_funnel_ids );

		if ( ! $ownership->is_success() ) {
			return $ownership;
		}

		return Routing_Result::success( $start_step_id, 'start_step_resolved', __( 'Start step resolved.', 'librefunnels' ) );
	}

	/**
	 * Resolves a route from the current step to the next step.
	 *
	 * @param int    $funnel_id       Funnel ID.
	 * @param array  $graph           Funnel graph.
	 * @param int    $current_step_id Current step ID.
	 * @param string $route           Requested route.
	 * @param array  $step_funnel_ids Map of step ID to owning funnel ID.
	 * @param array  $facts           Optional facts for conditional routes.
	 * @return Routing_Result
	 */
	public function resolve_next_step( $funnel_id, array $graph, $current_step_id, $route, array $step_funnel_ids, array $facts = array() ) {
		$funnel_id       = $this->absint( $funnel_id );
		$current_step_id = $this->absint( $current_step_id );
		$route           = $this->sanitize_key( $route );

		if ( ! in_array( $route, self::get_allowed_routes(), true ) ) {
			return Routing_Result::failure( 'unknown_route', __( 'The requested funnel route is not supported.', 'librefunnels' ) );
		}

		$ownership = $this->validate_step_ownership( $funnel_id, $current_step_id, $step_funnel_ids );

		if ( ! $ownership->is_success() ) {
			return $ownership;
		}

		$graph_validation = $this->validate_graph( $funnel_id, $graph, $step_funnel_ids );

		if ( ! $graph_validation->is_success() ) {
			return $graph_validation;
		}

		$nodes_by_id     = $this->get_nodes_by_id( $graph );
		$node_id_by_step = $this->get_node_id_by_step_id( $graph );

		if ( ! isset( $node_id_by_step[ $current_step_id ] ) ) {
			return Routing_Result::failure( 'current_step_not_in_graph', __( 'The current step is not represented in the funnel graph.', 'librefunnels' ) );
		}

		$source_node_id = $node_id_by_step[ $current_step_id ];
		$edge           = 'conditional' === $route ? $this->find_conditional_edge( $graph, $source_node_id, $facts ) : $this->find_edge( $graph, $source_node_id, $route );

		if ( null === $edge && 'fallback' !== $route ) {
			$edge = $this->find_edge( $graph, $source_node_id, 'fallback' );
		}

		if ( null === $edge ) {
			return Routing_Result::failure( 'route_not_found', __( 'No matching route was found for the current step.', 'librefunnels' ) );
		}

		$target_node_id = $this->sanitize_key( $edge['target'] );
		$target_step_id = $this->absint( $nodes_by_id[ $target_node_id ]['stepId'] );

		return Routing_Result::success( $target_step_id, 'next_step_resolved', __( 'Next step resolved.', 'librefunnels' ) );
	}

	/**
	 * Validates graph nodes and edges.
	 *
	 * @param int   $funnel_id       Funnel ID.
	 * @param array $graph           Funnel graph.
	 * @param array $step_funnel_ids Map of step ID to owning funnel ID.
	 * @return Routing_Result
	 */
	public function validate_graph( $funnel_id, array $graph, array $step_funnel_ids ) {
		$funnel_id   = $this->absint( $funnel_id );
		$nodes       = $this->get_graph_items( $graph, 'nodes' );
		$edges       = $this->get_graph_items( $graph, 'edges' );
		$nodes_by_id = array();

		foreach ( $nodes as $node ) {
			$node_id = isset( $node['id'] ) ? $this->sanitize_key( $node['id'] ) : '';
			$step_id = isset( $node['stepId'] ) ? $this->absint( $node['stepId'] ) : 0;

			if ( '' === $node_id || 0 === $step_id ) {
				return Routing_Result::failure( 'graph_node_invalid', __( 'A funnel graph node is missing its node ID or step ID.', 'librefunnels' ) );
			}

			$ownership = $this->validate_step_ownership( $funnel_id, $step_id, $step_funnel_ids, 'graph_node_step_missing', 'graph_node_step_wrong_funnel' );

			if ( ! $ownership->is_success() ) {
				return $ownership;
			}

			$nodes_by_id[ $node_id ] = $node;
		}

		foreach ( $edges as $edge ) {
			$source = isset( $edge['source'] ) ? $this->sanitize_key( $edge['source'] ) : '';
			$target = isset( $edge['target'] ) ? $this->sanitize_key( $edge['target'] ) : '';
			$route  = isset( $edge['route'] ) ? $this->sanitize_key( $edge['route'] ) : '';

			if ( '' === $source || '' === $target || '' === $route ) {
				return Routing_Result::failure( 'graph_edge_invalid', __( 'A funnel graph edge is missing its source, target, or route.', 'librefunnels' ) );
			}

			if ( ! isset( $nodes_by_id[ $source ] ) || ! isset( $nodes_by_id[ $target ] ) ) {
				return Routing_Result::failure( 'graph_edge_invalid', __( 'A funnel graph edge references a missing source or target node.', 'librefunnels' ) );
			}

			if ( ! in_array( $route, self::get_allowed_routes(), true ) ) {
				return Routing_Result::failure( 'graph_edge_invalid_route', __( 'A funnel graph edge uses an unsupported route.', 'librefunnels' ) );
			}
		}

		return Routing_Result::success( 0, 'graph_valid', __( 'Funnel graph is valid.', 'librefunnels' ) );
	}

	/**
	 * Validates step ownership.
	 *
	 * @param int    $funnel_id          Funnel ID.
	 * @param int    $step_id            Step ID.
	 * @param array  $step_funnel_ids    Map of step ID to owning funnel ID.
	 * @param string $missing_code       Missing step failure code.
	 * @param string $wrong_funnel_code  Wrong funnel failure code.
	 * @return Routing_Result
	 */
	public function validate_step_ownership( $funnel_id, $step_id, array $step_funnel_ids, $missing_code = 'step_not_found', $wrong_funnel_code = 'step_not_in_funnel' ) {
		$funnel_id = $this->absint( $funnel_id );
		$step_id   = $this->absint( $step_id );

		if ( 0 === $step_id || ! isset( $step_funnel_ids[ $step_id ] ) ) {
			return Routing_Result::failure( $missing_code, __( 'The requested step does not exist.', 'librefunnels' ) );
		}

		if ( $this->absint( $step_funnel_ids[ $step_id ] ) !== $funnel_id ) {
			return Routing_Result::failure( $wrong_funnel_code, __( 'The requested step does not belong to this funnel.', 'librefunnels' ) );
		}

		return Routing_Result::success( $step_id, 'step_valid', __( 'Step belongs to funnel.', 'librefunnels' ) );
	}

	/**
	 * Finds an edge for a source node and route.
	 *
	 * @param array  $graph          Funnel graph.
	 * @param string $source_node_id Source node ID.
	 * @param string $route          Route label.
	 * @return array<string,mixed>|null
	 */
	private function find_edge( array $graph, $source_node_id, $route ) {
		$source_node_id = $this->sanitize_key( $source_node_id );
		$route          = $this->sanitize_key( $route );

		foreach ( $this->get_graph_items( $graph, 'edges' ) as $edge ) {
			$source     = isset( $edge['source'] ) ? $this->sanitize_key( $edge['source'] ) : '';
			$edge_route = isset( $edge['route'] ) ? $this->sanitize_key( $edge['route'] ) : '';

			if ( $source_node_id === $source && $route === $edge_route ) {
				return $edge;
			}
		}

		return null;
	}

	/**
	 * Finds the first matching conditional edge for supplied facts.
	 *
	 * @param array  $graph          Funnel graph.
	 * @param string $source_node_id Source node ID.
	 * @param array  $facts          Facts.
	 * @return array<string,mixed>|null
	 */
	private function find_conditional_edge( array $graph, $source_node_id, array $facts ) {
		$source_node_id = $this->sanitize_key( $source_node_id );

		foreach ( $this->get_graph_items( $graph, 'edges' ) as $edge ) {
			$source = isset( $edge['source'] ) ? $this->sanitize_key( $edge['source'] ) : '';
			$route  = isset( $edge['route'] ) ? $this->sanitize_key( $edge['route'] ) : '';

			if ( $source_node_id !== $source || 'conditional' !== $route ) {
				continue;
			}

			if ( ! isset( $edge['rule'] ) || ! is_array( $edge['rule'] ) ) {
				continue;
			}

			if ( $this->rule_evaluator->evaluate( $edge['rule'], $facts )->is_match() ) {
				return $edge;
			}
		}

		return null;
	}

	/**
	 * Builds a node lookup by node ID.
	 *
	 * @param array $graph Funnel graph.
	 * @return array<string,array<string,mixed>>
	 */
	private function get_nodes_by_id( array $graph ) {
		$nodes = array();

		foreach ( $this->get_graph_items( $graph, 'nodes' ) as $node ) {
			if ( isset( $node['id'] ) ) {
				$nodes[ $this->sanitize_key( $node['id'] ) ] = $node;
			}
		}

		return $nodes;
	}

	/**
	 * Builds a node lookup by step ID.
	 *
	 * @param array $graph Funnel graph.
	 * @return array<int,string>
	 */
	private function get_node_id_by_step_id( array $graph ) {
		$nodes = array();

		foreach ( $this->get_graph_items( $graph, 'nodes' ) as $node ) {
			if ( isset( $node['id'], $node['stepId'] ) ) {
				$nodes[ $this->absint( $node['stepId'] ) ] = $this->sanitize_key( $node['id'] );
			}
		}

		return $nodes;
	}

	/**
	 * Returns graph items as arrays.
	 *
	 * @param array  $graph Graph.
	 * @param string $key   Item key.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_graph_items( array $graph, $key ) {
		if ( ! isset( $graph[ $key ] ) || ! is_array( $graph[ $key ] ) ) {
			return array();
		}

		$items = array();

		foreach ( $graph[ $key ] as $item ) {
			if ( is_object( $item ) ) {
				$item = (array) $item;
			}

			if ( is_array( $item ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Sanitizes a key with a fallback for isolated unit tests.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_key( $value ) {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
	}

	/**
	 * Converts a value to an absolute integer with a fallback for isolated unit tests.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private function absint( $value ) {
		if ( function_exists( 'absint' ) ) {
			return absint( $value );
		}

		return abs( (int) $value );
	}
}
