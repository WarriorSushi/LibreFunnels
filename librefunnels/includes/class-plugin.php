<?php
/**
 * Main plugin coordinator.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels;

use LibreFunnels\Admin\Admin_Menu;
use LibreFunnels\Blocks\Block_Registry;
use LibreFunnels\Checkout\Checkout_Field_Customizer;
use LibreFunnels\Domain\Funnel_Post_Type;
use LibreFunnels\Domain\Registered_Meta;
use LibreFunnels\Domain\Step_Post_Type;
use LibreFunnels\Frontend\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates bootstrap, dependency checks, and top-level services.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Dependency checker.
	 *
	 * @var Dependencies|null
	 */
	private $dependencies = null;

	/**
	 * Returns the plugin singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( version_compare( PHP_VERSION, LIBREFUNNELS_MINIMUM_PHP, '<' ) ) {
			deactivate_plugins( LIBREFUNNELS_BASENAME );
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version. */
						__( 'LibreFunnels requires PHP %1$s or newer. This site is running PHP %2$s.', 'librefunnels' ),
						LIBREFUNNELS_MINIMUM_PHP,
						PHP_VERSION
					)
				)
			);
		}

		global $wp_version;

		if ( version_compare( $wp_version, LIBREFUNNELS_MINIMUM_WP, '<' ) ) {
			deactivate_plugins( LIBREFUNNELS_BASENAME );
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: required WordPress version, 2: current WordPress version. */
						__( 'LibreFunnels requires WordPress %1$s or newer. This site is running WordPress %2$s.', 'librefunnels' ),
						LIBREFUNNELS_MINIMUM_WP,
						$wp_version
					)
				)
			);
		}

		( new Funnel_Post_Type() )->register_post_type();
		( new Step_Post_Type() )->register_post_type();
		flush_rewrite_rules();

		update_option( 'librefunnels_version', LIBREFUNNELS_VERSION, false );
	}

	/**
	 * Boots plugin services after WordPress and plugins are loaded.
	 *
	 * @return void
	 */
	public function boot() {
		load_plugin_textdomain( 'librefunnels', false, dirname( LIBREFUNNELS_BASENAME ) . '/languages' );

		$this->dependencies = new Dependencies();
		add_action( 'admin_notices', array( $this->dependencies, 'render_admin_notices' ) );

		if ( ! $this->dependencies->satisfied() ) {
			return;
		}

		( new Funnel_Post_Type() )->register();
		( new Step_Post_Type() )->register();
		( new Registered_Meta() )->register();
		( new Shortcodes() )->register();
		( new Block_Registry() )->register();
		( new Checkout_Field_Customizer() )->register();

		if ( is_admin() ) {
			( new Admin_Menu() )->register();
		}
	}

	/**
	 * Keeps construction private for singleton use.
	 */
	private function __construct() {}
}
