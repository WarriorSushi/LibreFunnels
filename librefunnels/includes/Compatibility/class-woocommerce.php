<?php
/**
 * WooCommerce compatibility declarations.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Compatibility;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Handles WooCommerce feature compatibility.
 */
final class WooCommerce {
	/**
	 * Declares compatibility with WooCommerce features during before_woocommerce_init.
	 *
	 * @param string $plugin_file Main plugin file path.
	 * @return void
	 */
	public static function declare_feature_compatibility( $plugin_file ) {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		FeaturesUtil::declare_compatibility( 'custom_order_tables', $plugin_file, true );
	}
}
