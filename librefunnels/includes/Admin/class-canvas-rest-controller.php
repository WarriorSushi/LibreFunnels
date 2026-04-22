<?php
/**
 * REST endpoints for the LibreFunnels canvas builder.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Admin;

use LibreFunnels\Domain\Registered_Meta;
use LibreFunnels\Domain\Step_Post_Type;
use LibreFunnels\Routing\Graph_Validator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Provides atomic admin operations for the visual canvas.
 */
final class Canvas_REST_Controller {
	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'librefunnels/v1';

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/canvas',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_workspace' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/canvas/funnels',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_funnel' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'title' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/canvas/funnels/(?P<funnel_id>\d+)/graph',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_graph' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'funnel_id'     => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'graph'         => array(
						'type'     => 'object',
						'required' => true,
					),
					'start_step_id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/canvas/funnels/(?P<funnel_id>\d+)/steps',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_step' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => $this->get_step_args( true ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/canvas/steps/(?P<step_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_step' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => $this->get_step_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_step' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'step_id' => array(
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/canvas/pages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_pages' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'search' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_page' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'title'   => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'step_id' => array(
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/canvas/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_products' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Checks REST permissions.
	 *
	 * @return true|WP_Error
	 */
	public function can_manage() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return new WP_Error(
			'librefunnels_rest_forbidden',
			__( 'You do not have permission to manage LibreFunnels.', 'librefunnels' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Returns the full canvas workspace.
	 *
	 * @return WP_REST_Response
	 */
	public function get_workspace() {
		return rest_ensure_response(
			array(
				'funnels'  => $this->get_funnels(),
				'steps'    => $this->get_steps(),
				'pages'    => $this->get_pages(),
				'products' => $this->get_products( '', $this->get_assigned_product_ids() ),
			)
		);
	}

	/**
	 * Creates a funnel.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_funnel( WP_REST_Request $request ) {
		$title = $request->get_param( 'title' );
		$title = is_string( $title ) && '' !== trim( $title ) ? $title : __( 'New checkout funnel', 'librefunnels' );

		$funnel_id = wp_insert_post(
			array(
				'post_type'   => LIBREFUNNELS_FUNNEL_POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'draft',
			),
			true
		);

		if ( is_wp_error( $funnel_id ) ) {
			return $funnel_id;
		}

		update_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_GRAPH_META, Registered_Meta::sanitize_graph( array() ) );
		update_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_START_STEP_META, 0 );

		return rest_ensure_response( $this->get_workspace_payload( absint( $funnel_id ) ) );
	}

	/**
	 * Saves graph metadata and start step in one request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_graph( WP_REST_Request $request ) {
		$funnel_id = absint( $request['funnel_id'] );
		$funnel    = $this->get_funnel_post( $funnel_id );

		if ( ! $funnel ) {
			return $this->not_found( __( 'This funnel could not be found.', 'librefunnels' ) );
		}

		$graph         = Registered_Meta::sanitize_graph( $request->get_param( 'graph' ) );
		$start_step_id = absint( $request->get_param( 'start_step_id' ) );

		$ownership_error = $this->validate_graph_step_ownership( $funnel_id, $graph, $start_step_id );

		if ( is_wp_error( $ownership_error ) ) {
			return $ownership_error;
		}

		update_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_GRAPH_META, $graph );
		update_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_START_STEP_META, $start_step_id );

		return rest_ensure_response( $this->get_workspace_payload( $funnel_id ) );
	}

	/**
	 * Creates a funnel step and adds it to the graph.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_step( WP_REST_Request $request ) {
		$funnel_id = absint( $request['funnel_id'] );
		$funnel    = $this->get_funnel_post( $funnel_id );

		if ( ! $funnel ) {
			return $this->not_found( __( 'This funnel could not be found.', 'librefunnels' ) );
		}

		$type  = Registered_Meta::sanitize_step_type( $request->get_param( 'type' ) );
		$title = $request->get_param( 'title' );
		$title = is_string( $title ) && '' !== trim( $title ) ? $title : $this->get_step_type_label( $type );
		$order = absint( $request->get_param( 'order' ) );

		$step_id = wp_insert_post(
			array(
				'post_type'   => LIBREFUNNELS_STEP_POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'draft',
			),
			true
		);

		if ( is_wp_error( $step_id ) ) {
			return $step_id;
		}

		update_post_meta( $step_id, LIBREFUNNELS_STEP_FUNNEL_ID_META, $funnel_id );
		update_post_meta( $step_id, LIBREFUNNELS_STEP_TYPE_META, $type );
		update_post_meta( $step_id, LIBREFUNNELS_STEP_ORDER_META, $order );
		update_post_meta( $step_id, LIBREFUNNELS_STEP_PAGE_ID_META, absint( $request->get_param( 'page_id' ) ) );

		$graph            = Registered_Meta::sanitize_graph( get_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_GRAPH_META, true ) );
		$position         = $request->get_param( 'position' );
		$position         = is_array( $position ) ? $position : array();
		$graph['nodes'][] = array(
			'id'       => 'node-' . absint( $step_id ),
			'stepId'   => absint( $step_id ),
			'type'     => $type,
			'position' => array(
				'x' => isset( $position['x'] ) ? (float) $position['x'] : 120 + count( $graph['nodes'] ) * 260,
				'y' => isset( $position['y'] ) ? (float) $position['y'] : 140 + ( count( $graph['nodes'] ) % 2 ) * 150,
			),
		);

		update_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_GRAPH_META, Registered_Meta::sanitize_graph( $graph ) );

		if ( 0 === absint( get_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_START_STEP_META, true ) ) ) {
			update_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_START_STEP_META, absint( $step_id ) );
		}

		return rest_ensure_response( $this->get_workspace_payload( $funnel_id ) );
	}

	/**
	 * Updates basic step settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_step( WP_REST_Request $request ) {
		$step_id = absint( $request['step_id'] );
		$step    = $this->get_step_post( $step_id );

		if ( ! $step ) {
			return $this->not_found( __( 'This step could not be found.', 'librefunnels' ) );
		}

		$funnel_id = absint( get_post_meta( $step_id, LIBREFUNNELS_STEP_FUNNEL_ID_META, true ) );

		if ( $request->has_param( 'title' ) ) {
			$title = sanitize_text_field( (string) $request->get_param( 'title' ) );

			if ( '' !== $title ) {
				$updated = wp_update_post(
					array(
						'ID'         => $step_id,
						'post_title' => $title,
					),
					true
				);

				if ( is_wp_error( $updated ) ) {
					return $updated;
				}
			}
		}

		if ( $request->has_param( 'type' ) ) {
			update_post_meta( $step_id, LIBREFUNNELS_STEP_TYPE_META, Registered_Meta::sanitize_step_type( $request->get_param( 'type' ) ) );
		}

		if ( $request->has_param( 'page_id' ) ) {
			update_post_meta( $step_id, LIBREFUNNELS_STEP_PAGE_ID_META, absint( $request->get_param( 'page_id' ) ) );
		}

		if ( $request->has_param( 'checkout_products' ) ) {
			update_post_meta( $step_id, LIBREFUNNELS_CHECKOUT_PRODUCTS_META, Registered_Meta::sanitize_checkout_products( $request->get_param( 'checkout_products' ) ) );
		}

		if ( $request->has_param( 'order_bumps' ) ) {
			update_post_meta( $step_id, LIBREFUNNELS_ORDER_BUMPS_META, Registered_Meta::sanitize_order_bumps( $request->get_param( 'order_bumps' ) ) );
		}

		if ( $request->has_param( 'offer' ) ) {
			update_post_meta( $step_id, LIBREFUNNELS_STEP_OFFER_META, Registered_Meta::sanitize_step_offer( $request->get_param( 'offer' ) ) );
		}

		return rest_ensure_response( $this->get_workspace_payload( $funnel_id ) );
	}

	/**
	 * Trashes a step and removes its canvas node/routes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_step( WP_REST_Request $request ) {
		$step_id = absint( $request['step_id'] );
		$step    = $this->get_step_post( $step_id );

		if ( ! $step ) {
			return $this->not_found( __( 'This step could not be found.', 'librefunnels' ) );
		}

		$funnel_id = absint( get_post_meta( $step_id, LIBREFUNNELS_STEP_FUNNEL_ID_META, true ) );
		$graph     = Registered_Meta::sanitize_graph( get_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_GRAPH_META, true ) );
		$node_ids  = array();

		foreach ( $graph['nodes'] as $node ) {
			if ( absint( $node['stepId'] ) === $step_id ) {
				$node_ids[] = sanitize_key( $node['id'] );
			}
		}

		$graph['nodes'] = array_values(
			array_filter(
				$graph['nodes'],
				static function ( $node ) use ( $step_id ) {
					return absint( $node['stepId'] ) !== $step_id;
				}
			)
		);
		$graph['edges'] = array_values(
			array_filter(
				$graph['edges'],
				static function ( $edge ) use ( $node_ids ) {
					return ! in_array( sanitize_key( $edge['source'] ), $node_ids, true ) && ! in_array( sanitize_key( $edge['target'] ), $node_ids, true );
				}
			)
		);

		update_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_GRAPH_META, $graph );

		if ( absint( get_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_START_STEP_META, true ) ) === $step_id ) {
			update_post_meta( $funnel_id, LIBREFUNNELS_FUNNEL_START_STEP_META, 0 );
		}

		wp_trash_post( $step_id );

		return rest_ensure_response( $this->get_workspace_payload( $funnel_id ) );
	}

	/**
	 * Searches pages for step assignment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function search_pages( WP_REST_Request $request ) {
		return rest_ensure_response( $this->get_pages( (string) $request->get_param( 'search' ) ) );
	}

	/**
	 * Searches WooCommerce products for assignment controls.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function search_products( WP_REST_Request $request ) {
		return rest_ensure_response( $this->get_products( (string) $request->get_param( 'search' ) ) );
	}

	/**
	 * Creates a draft page for a step and assigns it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_page( WP_REST_Request $request ) {
		$step_id = absint( $request->get_param( 'step_id' ) );
		$step    = $this->get_step_post( $step_id );

		if ( ! $step ) {
			return $this->not_found( __( 'This step could not be found.', 'librefunnels' ) );
		}

		$title = $request->get_param( 'title' );
		$title = is_string( $title ) && '' !== trim( $title ) ? $title : get_the_title( $step );

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_title'   => $title,
				'post_status'  => 'draft',
				'post_content' => '[librefunnels_step id="' . absint( $step_id ) . '"]',
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		update_post_meta( $step_id, LIBREFUNNELS_STEP_PAGE_ID_META, absint( $page_id ) );

		return rest_ensure_response(
			array(
				'page'      => $this->serialize_page( get_post( $page_id ) ),
				'workspace' => $this->get_workspace_payload( absint( get_post_meta( $step_id, LIBREFUNNELS_STEP_FUNNEL_ID_META, true ) ) ),
			)
		);
	}

	/**
	 * Returns reusable step endpoint args.
	 *
	 * @param bool $include_funnel Whether the route includes a funnel ID.
	 * @return array<string,array<string,mixed>>
	 */
	private function get_step_args( $include_funnel ) {
		$args = array(
			'title'             => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'type'              => array(
				'type'              => 'string',
				'enum'              => Step_Post_Type::get_allowed_step_types(),
				'sanitize_callback' => array( Registered_Meta::class, 'sanitize_step_type' ),
			),
			'page_id'           => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'order'             => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'position'          => array(
				'type' => 'object',
			),
			'checkout_products' => array(
				'type' => 'array',
			),
			'order_bumps'       => array(
				'type' => 'array',
			),
			'offer'             => array(
				'type' => 'object',
			),
		);

		if ( $include_funnel ) {
			$args['funnel_id'] = array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			);
		} else {
			$args['step_id'] = array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			);
		}

		return $args;
	}

	/**
	 * Validates graph step ownership.
	 *
	 * @param int   $funnel_id     Funnel ID.
	 * @param array $graph         Graph.
	 * @param int   $start_step_id Start step ID.
	 * @return true|WP_Error
	 */
	private function validate_graph_step_ownership( $funnel_id, array $graph, $start_step_id ) {
		$step_ids = array();

		foreach ( $graph['nodes'] as $node ) {
			$step_ids[] = absint( $node['stepId'] );
		}

		if ( 0 < absint( $start_step_id ) ) {
			$step_ids[] = absint( $start_step_id );
		}

		foreach ( array_unique( array_filter( $step_ids ) ) as $step_id ) {
			$step = $this->get_step_post( $step_id );

			if ( ! $step || absint( get_post_meta( $step_id, LIBREFUNNELS_STEP_FUNNEL_ID_META, true ) ) !== absint( $funnel_id ) ) {
				return new WP_Error(
					'librefunnels_step_not_in_funnel',
					__( 'The graph includes a step that does not belong to this funnel.', 'librefunnels' ),
					array( 'status' => 400 )
				);
			}
		}

		return true;
	}

	/**
	 * Returns the workspace response payload.
	 *
	 * @param int $selected_funnel_id Selected funnel ID.
	 * @return array<string,mixed>
	 */
	private function get_workspace_payload( $selected_funnel_id = 0 ) {
		return array(
			'funnels'          => $this->get_funnels(),
			'steps'            => $this->get_steps( absint( $selected_funnel_id ) ),
			'pages'            => $this->get_pages(),
			'products'         => $this->get_products( '', $this->get_assigned_product_ids() ),
			'selectedFunnelId' => absint( $selected_funnel_id ),
		);
	}

	/**
	 * Gets funnels.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_funnels() {
		$posts = get_posts(
			array(
				'post_type'      => LIBREFUNNELS_FUNNEL_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);

		return array_map( array( $this, 'serialize_funnel' ), $posts );
	}

	/**
	 * Gets steps.
	 *
	 * @param int $selected_funnel_id Selected funnel ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_steps( $selected_funnel_id = 0 ) {
		$selected_funnel_id = absint( $selected_funnel_id );
		$posts              = array();

		if ( 0 < $selected_funnel_id ) {
			$posts = get_posts(
				array(
					'post_type'      => LIBREFUNNELS_STEP_POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
					'posts_per_page' => -1,
					'meta_key'       => LIBREFUNNELS_STEP_FUNNEL_ID_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required to keep the selected funnel workspace complete.
					'meta_value'     => $selected_funnel_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required to keep the selected funnel workspace complete.
					'orderby'        => 'menu_order title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
		}

		$recent_posts = get_posts(
			array(
				'post_type'      => LIBREFUNNELS_STEP_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 100,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$posts_by_id = array();

		foreach ( array_merge( $posts, $recent_posts ) as $post ) {
			$posts_by_id[ $post->ID ] = $post;
		}

		return array_map( array( $this, 'serialize_step' ), array_values( $posts_by_id ) );
	}

	/**
	 * Gets pages for assignment.
	 *
	 * @param string $search Optional search term.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_pages( $search = '' ) {
		$args = array(
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => 20,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		if ( '' !== trim( $search ) ) {
			$args['s'] = sanitize_text_field( $search );
		}

		$posts = get_posts( $args );

		return array_values( array_filter( array_map( array( $this, 'serialize_page' ), $posts ) ) );
	}

	/**
	 * Gets WooCommerce products for assignment controls.
	 *
	 * @param string $search      Optional search term.
	 * @param int[]  $include_ids Product IDs that must be included.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_products( $search = '', array $include_ids = array() ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$args = array(
			'status'  => array( 'publish', 'private' ),
			'limit'   => 20,
			'orderby' => 'name',
			'order'   => 'ASC',
			'return'  => 'objects',
		);

		$search          = sanitize_text_field( $search );
		$product_objects = array();

		if ( '' !== trim( $search ) ) {
			$name_args           = $args;
			$name_args['search'] = '*' . $search . '*';

			$sku_args        = $args;
			$sku_args['sku'] = $search;

			$product_objects = array_merge( wc_get_products( $name_args ), wc_get_products( $sku_args ) );
		} else {
			$product_objects = wc_get_products( $args );
		}

		$seen    = array();
		$results = array();

		foreach ( $product_objects as $product ) {
			$serialized = $this->serialize_product( $product );

			if ( $serialized ) {
				$seen[ $serialized['id'] ] = true;
				$results[]                 = $serialized;
			}
		}

		$include_ids = array_unique( array_filter( array_map( 'absint', $include_ids ) ) );

		foreach ( $include_ids as $product_id ) {
			if ( isset( $seen[ $product_id ] ) ) {
				continue;
			}

			$product = wc_get_product( $product_id );
			$product = $product ? $this->serialize_product( $product ) : null;

			if ( $product ) {
				$results[] = $product;
			}
		}

		return $results;
	}

	/**
	 * Serializes a funnel.
	 *
	 * @param \WP_Post $post Funnel post.
	 * @return array<string,mixed>
	 */
	private function serialize_funnel( $post ) {
		$graph        = Registered_Meta::sanitize_graph( get_post_meta( $post->ID, LIBREFUNNELS_FUNNEL_GRAPH_META, true ) );
		$start_step   = absint( get_post_meta( $post->ID, LIBREFUNNELS_FUNNEL_START_STEP_META, true ) );
		$step_map     = $this->get_step_funnel_map( $graph, $start_step );
		$validator    = new Graph_Validator();
		$graph_result = $validator->validate_graph( $post->ID, $graph, $step_map );
		$warnings     = $this->get_graph_warnings( $post->ID, $graph, $start_step );

		if ( ! $graph_result->is_success() ) {
			$warnings[] = $graph_result->get_message();
		}

		return array(
			'id'          => absint( $post->ID ),
			'title'       => get_the_title( $post ),
			'status'      => $post->post_status,
			'graph'       => $graph,
			'startStepId' => $start_step,
			'warnings'    => array_values( array_unique( $warnings ) ),
		);
	}

	/**
	 * Serializes a step.
	 *
	 * @param \WP_Post $post Step post.
	 * @return array<string,mixed>
	 */
	private function serialize_step( $post ) {
		$page_id = absint( get_post_meta( $post->ID, LIBREFUNNELS_STEP_PAGE_ID_META, true ) );
		$page    = $page_id ? get_post( $page_id ) : null;

		return array(
			'id'               => absint( $post->ID ),
			'title'            => get_the_title( $post ),
			'status'           => $post->post_status,
			'funnelId'         => absint( get_post_meta( $post->ID, LIBREFUNNELS_STEP_FUNNEL_ID_META, true ) ),
			'type'             => Registered_Meta::sanitize_step_type( get_post_meta( $post->ID, LIBREFUNNELS_STEP_TYPE_META, true ) ),
			'order'            => absint( get_post_meta( $post->ID, LIBREFUNNELS_STEP_ORDER_META, true ) ),
			'pageId'           => $page_id,
			'pageTitle'        => $page ? get_the_title( $page ) : '',
			'pageStatus'       => $page ? $page->post_status : '',
			'pageEditUrl'      => $page ? get_edit_post_link( $page_id, 'raw' ) : '',
			'pageUrl'          => $page ? get_permalink( $page ) : '',
			'checkoutProducts' => Registered_Meta::sanitize_checkout_products( get_post_meta( $post->ID, LIBREFUNNELS_CHECKOUT_PRODUCTS_META, true ) ),
			'orderBumps'       => Registered_Meta::sanitize_order_bumps( get_post_meta( $post->ID, LIBREFUNNELS_ORDER_BUMPS_META, true ) ),
			'offer'            => Registered_Meta::sanitize_step_offer( get_post_meta( $post->ID, LIBREFUNNELS_STEP_OFFER_META, true ) ),
		);
	}

	/**
	 * Serializes a WooCommerce product.
	 *
	 * @param \WC_Product|mixed $product Product object.
	 * @return array<string,mixed>|null
	 */
	private function serialize_product( $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return null;
		}

		$image_id  = method_exists( $product, 'get_image_id' ) ? absint( $product->get_image_id() ) : 0;
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

		return array(
			'id'          => absint( $product->get_id() ),
			'name'        => method_exists( $product, 'get_name' ) ? $product->get_name() : '',
			'type'        => method_exists( $product, 'get_type' ) ? $product->get_type() : '',
			'sku'         => method_exists( $product, 'get_sku' ) ? $product->get_sku() : '',
			'price'       => method_exists( $product, 'get_price' ) ? (string) $product->get_price() : '',
			'priceHtml'   => method_exists( $product, 'get_price_html' ) ? wp_strip_all_tags( $product->get_price_html() ) : '',
			'purchasable' => method_exists( $product, 'is_purchasable' ) ? (bool) $product->is_purchasable() : false,
			'imageUrl'    => is_string( $image_url ) ? $image_url : '',
		);
	}

	/**
	 * Gets product IDs currently assigned in funnel step commerce metadata.
	 *
	 * @return int[]
	 */
	private function get_assigned_product_ids() {
		$ids = array();

		foreach ( $this->get_steps() as $step ) {
			foreach ( $step['checkoutProducts'] as $item ) {
				$ids[] = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
			}

			foreach ( $step['orderBumps'] as $item ) {
				$ids[] = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
			}

			if ( ! empty( $step['offer']['product_id'] ) ) {
				$ids[] = absint( $step['offer']['product_id'] );
			}
		}

		return $ids;
	}

	/**
	 * Serializes a page.
	 *
	 * @param \WP_Post|null $post Page post.
	 * @return array<string,mixed>|null
	 */
	private function serialize_page( $post ) {
		if ( ! $post || 'page' !== $post->post_type ) {
			return null;
		}

		return array(
			'id'      => absint( $post->ID ),
			'title'   => get_the_title( $post ),
			'status'  => $post->post_status,
			'editUrl' => get_edit_post_link( $post->ID, 'raw' ),
			'url'     => get_permalink( $post ),
		);
	}

	/**
	 * Gets non-blocking graph warnings for the UI.
	 *
	 * @param int   $funnel_id     Funnel ID.
	 * @param array $graph         Graph.
	 * @param int   $start_step_id Start step ID.
	 * @return string[]
	 */
	private function get_graph_warnings( $funnel_id, array $graph, $start_step_id ) {
		$warnings = array();

		if ( 0 === absint( $start_step_id ) ) {
			$warnings[] = __( 'Choose a start step so shoppers know where to enter.', 'librefunnels' );
		}

		foreach ( $graph['nodes'] as $node ) {
			$step_id = absint( $node['stepId'] );
			$step    = $this->get_step_post( $step_id );

			if ( ! $step ) {
				$warnings[] = __( 'A canvas node points to a step that no longer exists.', 'librefunnels' );
				continue;
			}

			if ( absint( get_post_meta( $step_id, LIBREFUNNELS_STEP_FUNNEL_ID_META, true ) ) !== absint( $funnel_id ) ) {
				$warnings[] = __( 'A canvas node points to a step from another funnel.', 'librefunnels' );
			}

			if ( 0 === absint( get_post_meta( $step_id, LIBREFUNNELS_STEP_PAGE_ID_META, true ) ) ) {
				$warnings[] = __( 'One or more steps need an assigned page.', 'librefunnels' );
			}
		}

		$node_ids = array();
		foreach ( $graph['nodes'] as $node ) {
			$node_ids[] = sanitize_key( $node['id'] );
		}

		foreach ( $graph['edges'] as $edge ) {
			if ( ! in_array( sanitize_key( $edge['source'] ), $node_ids, true ) || ! in_array( sanitize_key( $edge['target'] ), $node_ids, true ) ) {
				$warnings[] = __( 'One or more routes point to missing steps.', 'librefunnels' );
			}

			if ( 'conditional' === sanitize_key( $edge['route'] ) && empty( $edge['rule']['type'] ) ) {
				$warnings[] = __( 'One or more conditional routes need a rule.', 'librefunnels' );
			}
		}

		return $warnings;
	}

	/**
	 * Builds a step ownership map for a graph.
	 *
	 * @param array $graph         Graph.
	 * @param int   $start_step_id Start step ID.
	 * @return array<int,int>
	 */
	private function get_step_funnel_map( array $graph, $start_step_id ) {
		$step_ids = array( absint( $start_step_id ) );

		foreach ( $graph['nodes'] as $node ) {
			$step_ids[] = absint( $node['stepId'] );
		}

		$map = array();

		foreach ( array_unique( array_filter( $step_ids ) ) as $step_id ) {
			$map[ $step_id ] = absint( get_post_meta( $step_id, LIBREFUNNELS_STEP_FUNNEL_ID_META, true ) );
		}

		return $map;
	}

	/**
	 * Gets a funnel post.
	 *
	 * @param int $funnel_id Funnel ID.
	 * @return \WP_Post|null
	 */
	private function get_funnel_post( $funnel_id ) {
		$post = get_post( absint( $funnel_id ) );

		return $post && LIBREFUNNELS_FUNNEL_POST_TYPE === $post->post_type ? $post : null;
	}

	/**
	 * Gets a step post.
	 *
	 * @param int $step_id Step ID.
	 * @return \WP_Post|null
	 */
	private function get_step_post( $step_id ) {
		$post = get_post( absint( $step_id ) );

		return $post && LIBREFUNNELS_STEP_POST_TYPE === $post->post_type ? $post : null;
	}

	/**
	 * Returns a not found error.
	 *
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function not_found( $message ) {
		return new WP_Error( 'librefunnels_not_found', $message, array( 'status' => 404 ) );
	}

	/**
	 * Gets a human step type label.
	 *
	 * @param string $type Step type.
	 * @return string
	 */
	private function get_step_type_label( $type ) {
		$labels = array(
			'landing'            => __( 'Landing', 'librefunnels' ),
			'optin'              => __( 'Opt-in', 'librefunnels' ),
			'checkout'           => __( 'Checkout', 'librefunnels' ),
			'pre_checkout_offer' => __( 'Pre-checkout Offer', 'librefunnels' ),
			'order_bump'         => __( 'Order Bump', 'librefunnels' ),
			'upsell'             => __( 'Upsell', 'librefunnels' ),
			'downsell'           => __( 'Downsell', 'librefunnels' ),
			'cross_sell'         => __( 'Cross-sell', 'librefunnels' ),
			'thank_you'          => __( 'Thank You', 'librefunnels' ),
			'custom'             => __( 'Custom', 'librefunnels' ),
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : __( 'New step', 'librefunnels' );
	}
}
