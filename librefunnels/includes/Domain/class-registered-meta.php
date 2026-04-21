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

		register_post_meta(
			LIBREFUNNELS_STEP_POST_TYPE,
			LIBREFUNNELS_STEP_PAGE_ID_META,
			array(
				'type'              => 'integer',
				'label'             => __( 'Step page ID', 'librefunnels' ),
				'description'       => __( 'WordPress page that renders this funnel step.', 'librefunnels' ),
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'auth_callback'     => array( self::class, 'user_can_manage_funnels' ),
				'show_in_rest'      => true,
			)
		);

		register_post_meta(
			LIBREFUNNELS_STEP_POST_TYPE,
			LIBREFUNNELS_CHECKOUT_PRODUCTS_META,
			array(
				'type'              => 'array',
				'label'             => __( 'Checkout products', 'librefunnels' ),
				'description'       => __( 'Products assigned to a funnel checkout step.', 'librefunnels' ),
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_checkout_products' ),
				'auth_callback'     => array( self::class, 'user_can_manage_funnels' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'product_id'   => array(
									'type' => 'integer',
								),
								'variation_id' => array(
									'type' => 'integer',
								),
								'quantity'     => array(
									'type' => 'integer',
								),
								'variation'    => array(
									'type'                 => 'object',
									'additionalProperties' => array(
										'type' => 'string',
									),
								),
							),
						),
					),
				),
			)
		);

		register_post_meta(
			LIBREFUNNELS_STEP_POST_TYPE,
			LIBREFUNNELS_CHECKOUT_COUPONS_META,
			array(
				'type'              => 'array',
				'label'             => __( 'Checkout coupons', 'librefunnels' ),
				'description'       => __( 'Coupon codes applied when rendering a funnel checkout step.', 'librefunnels' ),
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_coupon_codes' ),
				'auth_callback'     => array( self::class, 'user_can_manage_funnels' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string',
						),
					),
				),
			)
		);

		register_post_meta(
			LIBREFUNNELS_STEP_POST_TYPE,
			LIBREFUNNELS_CHECKOUT_FIELDS_META,
			array(
				'type'              => 'array',
				'label'             => __( 'Checkout fields', 'librefunnels' ),
				'description'       => __( 'Field customization rules for a funnel checkout step.', 'librefunnels' ),
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_checkout_fields' ),
				'auth_callback'     => array( self::class, 'user_can_manage_funnels' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'section'     => array(
									'type' => 'string',
									'enum' => array( 'billing', 'shipping', 'order', 'account' ),
								),
								'key'         => array(
									'type' => 'string',
								),
								'label'       => array(
									'type' => 'string',
								),
								'placeholder' => array(
									'type' => 'string',
								),
								'required'    => array(
									'type' => 'boolean',
								),
								'hidden'      => array(
									'type' => 'boolean',
								),
							),
						),
					),
				),
			)
		);

		register_post_meta(
			LIBREFUNNELS_STEP_POST_TYPE,
			LIBREFUNNELS_ORDER_BUMPS_META,
			array(
				'type'              => 'array',
				'label'             => __( 'Order bumps', 'librefunnels' ),
				'description'       => __( 'Inline offers shown with a funnel checkout step.', 'librefunnels' ),
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_order_bumps' ),
				'auth_callback'     => array( self::class, 'user_can_manage_funnels' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'id'              => array(
									'type' => 'string',
								),
								'product_id'      => array(
									'type' => 'integer',
								),
								'variation_id'    => array(
									'type' => 'integer',
								),
								'quantity'        => array(
									'type' => 'integer',
								),
								'variation'       => array(
									'type'                 => 'object',
									'additionalProperties' => array(
										'type' => 'string',
									),
								),
								'title'           => array(
									'type' => 'string',
								),
								'description'     => array(
									'type' => 'string',
								),
								'discount_type'   => array(
									'type' => 'string',
									'enum' => self::get_allowed_discount_types(),
								),
								'discount_amount' => array(
									'type' => 'number',
								),
								'enabled'         => array(
									'type' => 'boolean',
								),
							),
						),
					),
				),
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
		unset( $allowed, $meta_key, $object_id, $cap, $caps );

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
	 * Sanitizes checkout product assignments.
	 *
	 * @param mixed $value Raw assignment list.
	 * @return array<int,array<string,int>>
	 */
	public static function sanitize_checkout_products( $value ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$products = array();

		foreach ( $value as $item ) {
			if ( is_object( $item ) ) {
				$item = (array) $item;
			}

			if ( ! is_array( $item ) ) {
				continue;
			}

			$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;

			if ( 0 === $product_id ) {
				continue;
			}

			$products[] = array(
				'product_id'   => $product_id,
				'variation_id' => isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0,
				'quantity'     => isset( $item['quantity'] ) ? max( 1, absint( $item['quantity'] ) ) : 1,
				'variation'    => self::sanitize_variation_attributes( isset( $item['variation'] ) ? $item['variation'] : array() ),
			);
		}

		return $products;
	}

	/**
	 * Sanitizes product variation attributes.
	 *
	 * @param mixed $value Raw variation attributes.
	 * @return array<string,string>
	 */
	private static function sanitize_variation_attributes( $value ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$variation = array();

		foreach ( $value as $attribute => $attribute_value ) {
			$attribute = sanitize_key( (string) $attribute );

			if ( '' === $attribute ) {
				continue;
			}

			$variation[ $attribute ] = sanitize_text_field( (string) $attribute_value );
		}

		return $variation;
	}

	/**
	 * Sanitizes coupon codes.
	 *
	 * @param mixed $value Raw coupon list.
	 * @return string[]
	 */
	public static function sanitize_coupon_codes( $value ) {
		if ( is_string( $value ) ) {
			$value = array( $value );
		}

		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$codes = array();

		foreach ( $value as $code ) {
			$code = self::format_coupon_code( $code );

			if ( '' !== $code ) {
				$codes[] = $code;
			}
		}

		return array_values( array_unique( $codes ) );
	}

	/**
	 * Sanitizes checkout field customization rules.
	 *
	 * @param mixed $value Raw field rules.
	 * @return array<int,array<string,mixed>>
	 */
	public static function sanitize_checkout_fields( $value ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$rules = array();

		foreach ( $value as $rule ) {
			if ( is_object( $rule ) ) {
				$rule = (array) $rule;
			}

			if ( ! is_array( $rule ) ) {
				continue;
			}

			$section = isset( $rule['section'] ) ? sanitize_key( (string) $rule['section'] ) : '';
			$key     = isset( $rule['key'] ) ? sanitize_key( (string) $rule['key'] ) : '';

			if ( ! in_array( $section, self::get_allowed_checkout_field_sections(), true ) || '' === $key ) {
				continue;
			}

			$rules[] = array(
				'section'     => $section,
				'key'         => $key,
				'label'       => isset( $rule['label'] ) ? sanitize_text_field( (string) $rule['label'] ) : '',
				'placeholder' => isset( $rule['placeholder'] ) ? sanitize_text_field( (string) $rule['placeholder'] ) : '',
				'required'    => isset( $rule['required'] ) ? (bool) $rule['required'] : false,
				'hidden'      => isset( $rule['hidden'] ) ? (bool) $rule['hidden'] : false,
			);
		}

		return $rules;
	}

	/**
	 * Sanitizes order bump offer definitions.
	 *
	 * @param mixed $value Raw order bump list.
	 * @return array<int,array<string,mixed>>
	 */
	public static function sanitize_order_bumps( $value ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$bumps = array();

		foreach ( $value as $bump ) {
			if ( is_object( $bump ) ) {
				$bump = (array) $bump;
			}

			if ( ! is_array( $bump ) ) {
				continue;
			}

			$product_id = isset( $bump['product_id'] ) ? absint( $bump['product_id'] ) : 0;

			if ( 0 === $product_id ) {
				continue;
			}

			$bump_id = isset( $bump['id'] ) ? sanitize_key( (string) $bump['id'] ) : '';

			if ( '' === $bump_id ) {
				$bump_id = sanitize_key( 'bump-' . $product_id . '-' . count( $bumps ) );
			}

			$bumps[] = array(
				'id'              => $bump_id,
				'product_id'      => $product_id,
				'variation_id'    => isset( $bump['variation_id'] ) ? absint( $bump['variation_id'] ) : 0,
				'quantity'        => isset( $bump['quantity'] ) ? max( 1, absint( $bump['quantity'] ) ) : 1,
				'variation'       => self::sanitize_variation_attributes( isset( $bump['variation'] ) ? $bump['variation'] : array() ),
				'title'           => isset( $bump['title'] ) ? sanitize_text_field( (string) $bump['title'] ) : '',
				'description'     => isset( $bump['description'] ) ? wp_kses_post( (string) $bump['description'] ) : '',
				'discount_type'   => self::sanitize_discount_type( isset( $bump['discount_type'] ) ? $bump['discount_type'] : 'none' ),
				'discount_amount' => isset( $bump['discount_amount'] ) ? self::sanitize_discount_amount( $bump['discount_amount'] ) : 0.0,
				'enabled'         => isset( $bump['enabled'] ) ? (bool) $bump['enabled'] : true,
			);
		}

		return $bumps;
	}

	/**
	 * Sanitizes an offer discount type.
	 *
	 * @param mixed $value Raw discount type.
	 * @return string
	 */
	public static function sanitize_discount_type( $value ) {
		$value = sanitize_key( (string) $value );

		if ( in_array( $value, self::get_allowed_discount_types(), true ) ) {
			return $value;
		}

		return 'none';
	}

	/**
	 * Returns allowed checkout field sections.
	 *
	 * @return string[]
	 */
	private static function get_allowed_checkout_field_sections() {
		return array( 'billing', 'shipping', 'order', 'account' );
	}

	/**
	 * Returns allowed offer discount types.
	 *
	 * @return string[]
	 */
	private static function get_allowed_discount_types() {
		return array( 'none', 'percentage', 'fixed' );
	}

	/**
	 * Sanitizes a discount amount.
	 *
	 * @param mixed $value Raw discount amount.
	 * @return float
	 */
	private static function sanitize_discount_amount( $value ) {
		$amount = (float) $value;

		if ( 0 > $amount ) {
			return 0.0;
		}

		return $amount;
	}

	/**
	 * Formats a coupon code using WooCommerce when available.
	 *
	 * @param mixed $code Raw coupon code.
	 * @return string
	 */
	private static function format_coupon_code( $code ) {
		if ( function_exists( 'wc_format_coupon_code' ) ) {
			return wc_format_coupon_code( (string) $code );
		}

		return sanitize_text_field( (string) $code );
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
