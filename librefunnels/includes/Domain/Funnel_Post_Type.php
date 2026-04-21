<?php
/**
 * Funnel post type registration.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the human-managed funnel container.
 */
final class Funnel_Post_Type {
	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Registers the funnel custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Funnels', 'post type general name', 'librefunnels' ),
			'singular_name'         => _x( 'Funnel', 'post type singular name', 'librefunnels' ),
			'menu_name'             => _x( 'Funnels', 'admin menu', 'librefunnels' ),
			'name_admin_bar'        => _x( 'Funnel', 'add new on admin bar', 'librefunnels' ),
			'add_new'               => _x( 'Add New', 'funnel', 'librefunnels' ),
			'add_new_item'          => __( 'Add New Funnel', 'librefunnels' ),
			'new_item'              => __( 'New Funnel', 'librefunnels' ),
			'edit_item'             => __( 'Edit Funnel', 'librefunnels' ),
			'view_item'             => __( 'View Funnel', 'librefunnels' ),
			'all_items'             => __( 'All Funnels', 'librefunnels' ),
			'search_items'          => __( 'Search Funnels', 'librefunnels' ),
			'parent_item_colon'     => __( 'Parent Funnels:', 'librefunnels' ),
			'not_found'             => __( 'No funnels found.', 'librefunnels' ),
			'not_found_in_trash'    => __( 'No funnels found in Trash.', 'librefunnels' ),
			'archives'              => __( 'Funnel archives', 'librefunnels' ),
			'attributes'            => __( 'Funnel attributes', 'librefunnels' ),
			'insert_into_item'      => __( 'Insert into funnel', 'librefunnels' ),
			'uploaded_to_this_item' => __( 'Uploaded to this funnel', 'librefunnels' ),
			'filter_items_list'     => __( 'Filter funnels list', 'librefunnels' ),
			'items_list_navigation' => __( 'Funnels list navigation', 'librefunnels' ),
			'items_list'            => __( 'Funnels list', 'librefunnels' ),
			'item_published'        => __( 'Funnel published.', 'librefunnels' ),
			'item_updated'          => __( 'Funnel updated.', 'librefunnels' ),
		);

		register_post_type(
			LIBREFUNNELS_FUNNEL_POST_TYPE,
			array(
				'labels'              => $labels,
				'description'         => __( 'LibreFunnels funnel containers for checkout flows, offers, routing, analytics, and templates.', 'librefunnels' ),
				'public'              => false,
				'hierarchical'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => true,
				'rest_base'           => 'librefunnels-funnels',
				'menu_icon'           => 'dashicons-randomize',
				'supports'            => array( 'title', 'revisions' ),
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
	 * Keeps funnel management scoped to WooCommerce managers until custom roles exist.
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
