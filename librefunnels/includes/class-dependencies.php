<?php
/**
 * Runtime dependency checks.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels;

defined( 'ABSPATH' ) || exit;

/**
 * Checks whether the plugin can safely load its WooCommerce-dependent features.
 */
final class Dependencies {
	/**
	 * Determines whether all runtime requirements are met.
	 *
	 * @return bool
	 */
	public function satisfied() {
		return $this->meets_php_requirement()
			&& $this->meets_wordpress_requirement()
			&& $this->woocommerce_is_available()
			&& $this->meets_woocommerce_requirement();
	}

	/**
	 * Renders admin notices for unmet dependencies.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		foreach ( $this->get_messages() as $message ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( $message )
			);
		}
	}

	/**
	 * Builds dependency messages for administrators.
	 *
	 * @return string[]
	 */
	private function get_messages() {
		$messages = array();

		if ( ! $this->meets_php_requirement() ) {
			$messages[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'LibreFunnels requires PHP %1$s or newer. This site is running PHP %2$s.', 'librefunnels' ),
				LIBREFUNNELS_MINIMUM_PHP,
				PHP_VERSION
			);
		}

		if ( ! $this->meets_wordpress_requirement() ) {
			global $wp_version;

			$messages[] = sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version. */
				__( 'LibreFunnels requires WordPress %1$s or newer. This site is running WordPress %2$s.', 'librefunnels' ),
				LIBREFUNNELS_MINIMUM_WP,
				$wp_version
			);
		}

		if ( ! $this->woocommerce_is_available() ) {
			$messages[] = __( 'LibreFunnels requires WooCommerce to be installed and active.', 'librefunnels' );
		} elseif ( ! $this->meets_woocommerce_requirement() ) {
			$messages[] = sprintf(
				/* translators: 1: required WooCommerce version, 2: current WooCommerce version. */
				__( 'LibreFunnels requires WooCommerce %1$s or newer. This site is running WooCommerce %2$s.', 'librefunnels' ),
				LIBREFUNNELS_MINIMUM_WC,
				$this->get_woocommerce_version()
			);
		}

		return $messages;
	}

	/**
	 * Checks the PHP runtime.
	 *
	 * @return bool
	 */
	private function meets_php_requirement() {
		return version_compare( PHP_VERSION, LIBREFUNNELS_MINIMUM_PHP, '>=' );
	}

	/**
	 * Checks the WordPress runtime.
	 *
	 * @return bool
	 */
	private function meets_wordpress_requirement() {
		global $wp_version;

		return version_compare( $wp_version, LIBREFUNNELS_MINIMUM_WP, '>=' );
	}

	/**
	 * Checks whether WooCommerce is active enough for runtime use.
	 *
	 * @return bool
	 */
	private function woocommerce_is_available() {
		return class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' );
	}

	/**
	 * Checks the WooCommerce version.
	 *
	 * @return bool
	 */
	private function meets_woocommerce_requirement() {
		if ( ! $this->woocommerce_is_available() ) {
			return false;
		}

		return version_compare( $this->get_woocommerce_version(), LIBREFUNNELS_MINIMUM_WC, '>=' );
	}

	/**
	 * Gets the active WooCommerce version.
	 *
	 * @return string
	 */
	private function get_woocommerce_version() {
		return defined( 'WC_VERSION' ) ? WC_VERSION : '0.0.0';
	}
}
