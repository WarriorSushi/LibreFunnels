<?php
/**
 * Registered metadata for funnel records.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Domain;

use LibreFunnels\Routing\Graph_Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Registers typed, REST-visible meta used by the canvas and routing layers.
 */
final class Registered_Meta {
	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
	}

	/**
	 * Registers funnel and step metadata.
	 *
	 * @return void
	 */
	public function register_meta() {
		$this->register_funnel_meta();
		$this->register_step_meta();
	}

	/**
	 * Registers funnel-level metadata.
	 *
	 * @return void
	 */
	private function register_funnel_meta() {
		register_post_meta(
			LIBREFUNNELS_FUNNEL_POST_TYPE,
			LIBREFUNNELS_FUNNEL_GRAPH_META,
			array(
				'type'              => 'object',
				'label'             => __( 'Funnel graph', 'librefunnels' ),
				'description'       => __( 'Canvas nodes and routing edges for a funnel.', 'librefunnels' ),
				'single'            => true,
				'default'           => self::get_empty_graph(),
				'sanitize_callback' => array( self::class, 'sanitize_graph' ),
				'auth_callback'     => array( self::class, 'user_can_manage_funnels' ),
				'show_in_rest'      => array(
					'schema' => $this->get_graph_schema(),
				),
			)
		);

		register_post_meta(
			LIBREFUNNELS_FUNNEL_POST_TYPE,
			LIBREFUNNELS_FUNNEL_START_STEP_META,
			array(
				'type'              => 'integer',
				'label'             => __( 'Start step ID', 'librefunnels' ),
				'description'       => __( 'The first step a visitor enters for this funnel.', 'librefunnels' ),
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( self::class, 'user_can_manage_funnels' ),
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Registers step-level metadata.
	 *
	 * @return void
	 */
	private function register_step_meta() {
		register_post_meta(
			LIBREFUNNELS_STEP_POST_TYPE,
			LIBREFUNNELS_STEP_FUNNEL_ID_META,
			array(
				'type'              => 'integer',
				'label'             => __( 'Parent funnel ID', 'librefunnels' ),
				'description'       => __( 'The funnel that owns this step.', 'librefunnels' ),
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( self::class, 'user_can_manage_funnels' ),
				'show_in_rest'      => true,
			)
		);

		register_post_meta(
			LIBREFUNNELS_STEP_POST_TYPE,
			LIBREFUNNELS_STEP_TYPE_META,
			array(
				'type'              => 'string',
				'label'             => __( 'Step type', 'librefunnels' ),
				'description'       => __( 'The role this step plays in the funnel.', 'librefunnels' ),
				'single'            => true,
				'default'           => 'landing',
				'sanitize_callback' => array( self::class, 'sanitize_step_type' ),
				'auth_callback'     => array( self::class, 'user_can_manage_funnels' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'string',
						'enum' => Step_Post_Type::get_allowed_step_types(),
					),
				),
			)
		);

		register_post_meta(
			LIBREFUNNELS_STEP_POST_TYPE,
			LIBREFUNNELS_STEP_ORDER_META,
			array(
				'type'              => 'integer',
				'label'             => __( 'Step order', 'librefunnels' ),
				'description'       => __( 'Default ordering hint for linear funnel displays.', 'librefunnels' ),
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( self::class, 'user_can_manage_funnels' ),
				'show_in_rest'      => true,
			)
		);

		register_post_meta(
			LIBREFUNNELS_STEP_POST_TYPE,
			LIBREFUNNELS_STEP_TEMPLATE_META,
			array(
				'type'              => 'string',
				'label'             => __( 'Template slug', 'librefunnels' ),
				'description'       => __( 'Optional template identifier used when rendering this step.', 'librefunnels' ),
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
				'auth_callback'     => array( self::class, 'user_can_manage_funnels' ),
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Checks whether a user can access funnel metadata.
	 *
	 * @param bool   $allowed   Whether the user can add the object meta.
	 * @param string $meta_key  The meta key.
	 * @param int    $object_id Object ID.
	 * @param int    $user_id   User ID.
	 * @param string $cap       Capability name.
	 * @param array  $caps      Primitive capabilities.
	 * @return bool
	 */
	public static function user_can_manage_funnels( $allowed, $meta_key, $object_id, $user_id, $cap = '', $caps = array() ) {
		return user_can( $user_id, 'manage_woocommerce' );
	}

	/**
	 * Sanitizes a step type value.
	 *
	 * @param mixed $value Raw step type.
	 * @return string
	 */
	public static function sanitize_step_type( $value ) {
		$value = sanitize_key( (string) $value );

		if ( in_array( $value, Step_Post_Type::get_allowed_step_types(), true ) ) {
			return $value;
		}

		return 'landing';
	}

	/**
	 * Sanitizes the stored funnel graph.
	 *
	 * @param mixed $value Raw graph data.
	 * @return array<string,mixed>
	 */
	public static function sanitize_graph( $value ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( ! is_array( $value ) ) {
			return self::get_empty_graph();
		}

		$nodes = array();
		if ( isset( $value['nodes'] ) && is_array( $value['nodes'] ) ) {
			foreach ( $value['nodes'] as $node ) {
				if ( is_object( $node ) ) {
					$node = (array) $node;
				}

				if ( is_array( $node ) ) {
					$nodes[] = self::sanitize_graph_node( $node );
				}
			}
		}

		$edges = array();
		if ( isset( $value['edges'] ) && is_array( $value['edges'] ) ) {
			foreach ( $value['edges'] as $edge ) {
				if ( is_object( $edge ) ) {
					$edge = (array) $edge;
				}

				if ( is_array( $edge ) ) {
					$edges[] = self::sanitize_graph_edge( $edge );
				}
			}
		}

		return array(
			'version' => 1,
			'nodes'   => $nodes,
			'edges'   => $edges,
		);
	}

	/**
	 * Sanitizes a graph node.
	 *
	 * @param array<string,mixed> $node Raw node data.
	 * @return array<string,mixed>
	 */
	private static function sanitize_graph_node( $node ) {
		if ( isset( $node['position'] ) && is_object( $node['position'] ) ) {
			$node['position'] = (array) $node['position'];
		}

		$position = isset( $node['position'] ) && is_array( $node['position'] ) ? $node['position'] : array();

		return array(
			'id'       => isset( $node['id'] ) ? sanitize_key( (string) $node['id'] ) : '',
			'stepId'   => isset( $node['stepId'] ) ? absint( $node['stepId'] ) : 0,
			'type'     => isset( $node['type'] ) ? self::sanitize_step_type( $node['type'] ) : 'landing',
			'position' => array(
				'x' => isset( $position['x'] ) ? (float) $position['x'] : 0.0,
				'y' => isset( $position['y'] ) ? (float) $position['y'] : 0.0,
			),
		);
	}

	/**
	 * Sanitizes a graph edge.
	 *
	 * @param array<string,mixed> $edge Raw edge data.
	 * @return array<string,string>
	 */
	private static function sanitize_graph_edge( $edge ) {
		$route = isset( $edge['route'] ) ? sanitize_key( (string) $edge['route'] ) : 'next';

		if ( ! in_array( $route, Graph_Validator::get_allowed_routes(), true ) ) {
			$route = 'next';
		}

		return array(
			'id'     => isset( $edge['id'] ) ? sanitize_key( (string) $edge['id'] ) : '',
			'source' => isset( $edge['source'] ) ? sanitize_key( (string) $edge['source'] ) : '',
			'target' => isset( $edge['target'] ) ? sanitize_key( (string) $edge['target'] ) : '',
			'route'  => $route,
		);
	}

	/**
	 * Returns the default empty graph.
	 *
	 * @return array<string,mixed>
	 */
	private static function get_empty_graph() {
		return array(
			'version' => 1,
			'nodes'   => array(),
			'edges'   => array(),
		);
	}

	/**
	 * Returns REST schema for the funnel graph meta value.
	 *
	 * @return array<string,mixed>
	 */
	private function get_graph_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'version' => array(
					'type'    => 'integer',
					'default' => 1,
				),
				'nodes'   => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'       => array(
								'type' => 'string',
							),
							'stepId'   => array(
								'type' => 'integer',
							),
							'type'     => array(
								'type' => 'string',
								'enum' => Step_Post_Type::get_allowed_step_types(),
							),
							'position' => array(
								'type'       => 'object',
								'properties' => array(
									'x' => array(
										'type' => 'number',
									),
									'y' => array(
										'type' => 'number',
									),
								),
							),
						),
					),
				),
				'edges'   => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'     => array(
								'type' => 'string',
							),
							'source' => array(
								'type' => 'string',
							),
							'target' => array(
								'type' => 'string',
							),
							'route'  => array(
								'type' => 'string',
								'enum' => Graph_Validator::get_allowed_routes(),
							),
						),
					),
				),
			),
			'additionalProperties' => false,
		);
	}
}
