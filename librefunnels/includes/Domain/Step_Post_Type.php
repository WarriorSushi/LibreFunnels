<?php
/**
 * Funnel step post type registration.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the private funnel step records used by routing and rendering.
 */
final class Step_Post_Type {
	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Registers the funnel step custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Funnel Steps', 'post type general name', 'librefunnels' ),
			'singular_name'         => _x( 'Funnel Step', 'post type singular name', 'librefunnels' ),
			'menu_name'             => _x( 'Funnel Steps', 'admin menu', 'librefunnels' ),
			'name_admin_bar'        => _x( 'Funnel Step', 'add new on admin bar', 'librefunnels' ),
			'add_new'               => _x( 'Add New', 'funnel step', 'librefunnels' ),
			'add_new_item'          => __( 'Add New Funnel Step', 'librefunnels' ),
			'new_item'              => __( 'New Funnel Step', 'librefunnels' ),
			'edit_item'             => __( 'Edit Funnel Step', 'librefunnels' ),
			'view_item'             => __( 'View Funnel Step', 'librefunnels' ),
			'all_items'             => __( 'All Funnel Steps', 'librefunnels' ),
			'search_items'          => __( 'Search Funnel Steps', 'librefunnels' ),
			'parent_item_colon'     => __( 'Parent Funnel Steps:', 'librefunnels' ),
			'not_found'             => __( 'No funnel steps found.', 'librefunnels' ),
			'not_found_in_trash'    => __( 'No funnel steps found in Trash.', 'librefunnels' ),
			'archives'              => __( 'Funnel step archives', 'librefunnels' ),
			'attributes'            => __( 'Funnel step attributes', 'librefunnels' ),
			'insert_into_item'      => __( 'Insert into funnel step', 'librefunnels' ),
			'uploaded_to_this_item' => __( 'Uploaded to this funnel step', 'librefunnels' ),
			'filter_items_list'     => __( 'Filter funnel steps list', 'librefunnels' ),
			'items_list_navigation' => __( 'Funnel steps list navigation', 'librefunnels' ),
			'items_list'            => __( 'Funnel steps list', 'librefunnels' ),
			'item_published'        => __( 'Funnel step published.', 'librefunnels' ),
			'item_updated'          => __( 'Funnel step updated.', 'librefunnels' ),
		);

		register_post_type(
			LIBREFUNNELS_STEP_POST_TYPE,
			array(
				'labels'              => $labels,
				'description'         => __( 'LibreFunnels step records for landing pages, checkouts, offers, downsells, and thank-you pages.', 'librefunnels' ),
				'public'              => false,
				'hierarchical'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => true,
				'rest_base'           => 'librefunnels-steps',
				'menu_icon'           => 'dashicons-networking',
				'supports'            => array( 'title', 'editor', 'excerpt', 'revisions' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
				'delete_with_user'    => false,
				'capabilities'        => $this->get_capabilities(),
				'map_meta_cap'        => false,
			)
		);
	}

	/**
	 * Returns the supported step types for the initial funnel model.
	 *
	 * @return string[]
	 */
	public static function get_allowed_step_types() {
		return array(
			'landing',
			'optin',
			'checkout',
			'pre_checkout_offer',
			'order_bump',
			'upsell',
			'downsell',
			'cross_sell',
			'thank_you',
			'custom',
		);
	}

	/**
	 * Keeps step management scoped to WooCommerce managers until custom roles exist.
	 *
	 * @return array<string,string>
	 */
	private function get_capabilities() {
		return array(
			'edit_post'              => 'manage_woocommerce',
			'read_post'              => 'manage_woocommerce',
			'delete_post'            => 'manage_woocommerce',
			'edit_posts'             => 'manage_woocommerce',
			'edit_others_posts'      => 'manage_woocommerce',
			'delete_posts'           => 'manage_woocommerce',
			'publish_posts'          => 'manage_woocommerce',
			'read_private_posts'     => 'manage_woocommerce',
			'delete_private_posts'   => 'manage_woocommerce',
			'delete_published_posts' => 'manage_woocommerce',
			'delete_others_posts'    => 'manage_woocommerce',
			'edit_private_posts'     => 'manage_woocommerce',
			'edit_published_posts'   => 'manage_woocommerce',
			'create_posts'           => 'manage_woocommerce',
		);
	}
}
