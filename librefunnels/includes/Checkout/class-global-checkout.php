<?php
/**
 * Global checkout routing.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Checkout;

use LibreFunnels\Routing\Funnel_Router;

defined( 'ABSPATH' ) || exit;

/**
 * Redirects the default WooCommerce checkout page to a configured funnel checkout page.
 */
final class Global_Checkout {
	/**
	 * Funnel router.
	 *
	 * @var Funnel_Router
	 */
	private $router;

	/**
	 * Creates the global checkout controller.
	 *
	 * @param Funnel_Router|null $router Optional router.
	 */
	public function __construct( Funnel_Router $router = null ) {
		$this->router = $router ? $router : new Funnel_Router();
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_settings' ) );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core request lifecycle hook.
		add_action( 'template_redirect', array( $this, 'maybe_redirect_checkout' ) );
	}

	/**
	 * Registers the global checkout option.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'librefunnels',
			LIBREFUNNELS_GLOBAL_CHECKOUT_FUNNEL_ID_OPTION,
			array(
				'type'              => 'integer',
				'description'       => __( 'LibreFunnels funnel used for global WooCommerce checkout takeover.', 'librefunnels' ),
				'sanitize_callback' => 'absint',
				'default'           => 0,
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Redirects the default checkout page when global checkout takeover is configured.
	 *
	 * @return void
	 */
	public function maybe_redirect_checkout() {
		if ( is_admin() || wp_doing_ajax() || ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		if ( $this->is_protected_checkout_endpoint() ) {
			return;
		}

		$target_url = $this->get_global_checkout_url();

		if ( '' === $target_url ) {
			return;
		}

		if ( function_exists( 'get_queried_object_id' ) ) {
			$current_page_id = absint( get_queried_object_id() );
			$target_page_id  = $this->get_global_checkout_page_id();

			if ( $target_page_id && $current_page_id === $target_page_id ) {
				return;
			}
		}

		if ( wp_safe_redirect( $target_url, 302, 'LibreFunnels' ) ) {
			exit;
		}
	}

	/**
	 * Gets the global checkout page URL.
	 *
	 * @return string
	 */
	public function get_global_checkout_url() {
		$page_id = $this->get_global_checkout_page_id();

		if ( 0 === $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? (string) $url : '';
	}

	/**
	 * Gets the global checkout page ID.
	 *
	 * @return int
	 */
	private function get_global_checkout_page_id() {
		$funnel_id = absint( get_option( LIBREFUNNELS_GLOBAL_CHECKOUT_FUNNEL_ID_OPTION, 0 ) );

		if ( 0 === $funnel_id ) {
			return 0;
		}

		$start_step = $this->router->get_start_step( $funnel_id );

		if ( ! $start_step->is_success() ) {
			return 0;
		}

		return absint( get_post_meta( $start_step->get_step_id(), LIBREFUNNELS_STEP_PAGE_ID_META, true ) );
	}

	/**
	 * Checks whether the current checkout request must not be redirected.
	 *
	 * @return bool
	 */
	private function is_protected_checkout_endpoint() {
		if ( ! function_exists( 'is_wc_endpoint_url' ) ) {
			return false;
		}

		$protected_endpoints = array(
			'order-pay',
			'order-received',
			'add-payment-method',
			'delete-payment-method',
			'set-default-payment-method',
		);

		foreach ( $protected_endpoints as $endpoint ) {
			if ( is_wc_endpoint_url( $endpoint ) ) {
				return true;
			}
		}

		return false;
	}
}
