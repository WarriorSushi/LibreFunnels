<?php
/**
 * Plugin Name: LibreFunnels for WooCommerce
 * Description: Free, open-source WooCommerce funnels, checkout flows, order bumps, upsells, downsells, and routing.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.2
 * WC tested up to: 8.2
 * Author: LibreFunnels Contributors
 * Text Domain: librefunnels
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package LibreFunnels
 */

defined( 'ABSPATH' ) || exit;

define( 'LIBREFUNNELS_VERSION', '0.1.0' );
define( 'LIBREFUNNELS_FILE', __FILE__ );
define( 'LIBREFUNNELS_PATH', plugin_dir_path( __FILE__ ) );
define( 'LIBREFUNNELS_URL', plugin_dir_url( __FILE__ ) );
define( 'LIBREFUNNELS_BASENAME', plugin_basename( __FILE__ ) );
define( 'LIBREFUNNELS_MINIMUM_PHP', '7.4' );
define( 'LIBREFUNNELS_MINIMUM_WP', '6.5' );
define( 'LIBREFUNNELS_MINIMUM_WC', '8.2' );
define( 'LIBREFUNNELS_TESTED_WC', '8.2' );
define( 'LIBREFUNNELS_FUNNEL_POST_TYPE', 'librefunnels_funnel' );
define( 'LIBREFUNNELS_STEP_POST_TYPE', 'librefunnels_step' );
define( 'LIBREFUNNELS_FUNNEL_GRAPH_META', '_librefunnels_graph' );
define( 'LIBREFUNNELS_FUNNEL_START_STEP_META', '_librefunnels_start_step_id' );
define( 'LIBREFUNNELS_STEP_FUNNEL_ID_META', '_librefunnels_funnel_id' );
define( 'LIBREFUNNELS_STEP_TYPE_META', '_librefunnels_step_type' );
define( 'LIBREFUNNELS_STEP_ORDER_META', '_librefunnels_step_order' );
define( 'LIBREFUNNELS_STEP_TEMPLATE_META', '_librefunnels_template_slug' );
define( 'LIBREFUNNELS_STEP_PAGE_ID_META', '_librefunnels_step_page_id' );
define( 'LIBREFUNNELS_CHECKOUT_PRODUCTS_META', '_librefunnels_checkout_products' );
define( 'LIBREFUNNELS_CHECKOUT_COUPONS_META', '_librefunnels_checkout_coupons' );
define( 'LIBREFUNNELS_CHECKOUT_FIELDS_META', '_librefunnels_checkout_fields' );
define( 'LIBREFUNNELS_GLOBAL_CHECKOUT_FUNNEL_ID_OPTION', 'librefunnels_global_checkout_funnel_id' );

require_once LIBREFUNNELS_PATH . 'includes/autoload.php';

add_action(
	'before_woocommerce_init',
	static function () {
		\LibreFunnels\Compatibility\WooCommerce::declare_feature_compatibility( LIBREFUNNELS_FILE );
	}
);

register_activation_hook( __FILE__, array( \LibreFunnels\Plugin::class, 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		\LibreFunnels\Plugin::instance()->boot();
	}
);
