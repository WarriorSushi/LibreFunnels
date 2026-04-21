<?php
/**
 * Funnel step renderer.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Rendering;

use LibreFunnels\Checkout\Checkout_Field_Customizer;
use LibreFunnels\Checkout\Cart_Preparer;

defined( 'ABSPATH' ) || exit;

/**
 * Renders supported LibreFunnels step types.
 */
final class Step_Renderer {
	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Cart preparer.
	 *
	 * @var Cart_Preparer
	 */
	private $cart_preparer;

	/**
	 * Creates the renderer.
	 *
	 * @param Template_Loader|null $template_loader Optional template loader.
	 * @param Cart_Preparer|null   $cart_preparer   Optional cart preparer.
	 */
	public function __construct( Template_Loader $template_loader = null, Cart_Preparer $cart_preparer = null ) {
		$this->template_loader = $template_loader ? $template_loader : new Template_Loader();
		$this->cart_preparer   = $cart_preparer ? $cart_preparer : new Cart_Preparer();
	}

	/**
	 * Renders a funnel step.
	 *
	 * @param int $step_id Step ID.
	 * @return string
	 */
	public function render_step( $step_id ) {
		$step_id = absint( $step_id );
		$step    = get_post( $step_id );

		if ( ! $step || LIBREFUNNELS_STEP_POST_TYPE !== $step->post_type ) {
			return $this->render_error( __( 'This LibreFunnels step could not be found.', 'librefunnels' ), 'step-not-found' );
		}

		$step_type = sanitize_key( get_post_meta( $step_id, LIBREFUNNELS_STEP_TYPE_META, true ) );

		if ( 'checkout' === $step_type ) {
			return $this->render_checkout_step( $step );
		}

		if ( 'thank_you' !== $step_type ) {
			return $this->render_error( __( 'This LibreFunnels step type is not renderable yet.', 'librefunnels' ), 'step-type-not-renderable' );
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Intentionally render step content through WordPress' core content filters.
		$content = apply_filters( 'the_content', $step->post_content );

		return $this->template_loader->render(
			'steps/thank-you.php',
			array(
				'step'      => $step,
				'step_id'   => $step_id,
				'step_type' => $step_type,
				'title'     => get_the_title( $step ),
				'content'   => $content,
			)
		);
	}

	/**
	 * Renders a checkout step.
	 *
	 * @param \WP_Post $step Step post.
	 * @return string
	 */
	private function render_checkout_step( $step ) {
		$prepared = $this->cart_preparer->prepare_for_step( $step->ID );

		if ( is_wp_error( $prepared ) ) {
			return $this->render_error( $prepared->get_error_message(), $prepared->get_error_code() );
		}

		Checkout_Field_Customizer::set_active_step_id( $step->ID );

		try {
			return $this->template_loader->render(
				'steps/checkout.php',
				array(
					'step'    => $step,
					'step_id' => absint( $step->ID ),
					'title'   => get_the_title( $step ),
				)
			);
		} finally {
			Checkout_Field_Customizer::clear_active_step_id();
		}
	}

	/**
	 * Renders a safe frontend error for invalid shortcode state.
	 *
	 * @param string $message Error message.
	 * @param string $code    Error code.
	 * @return string
	 */
	private function render_error( $message, $code ) {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return sprintf(
				'<div class="librefunnels-message librefunnels-message--error" data-librefunnels-code="%1$s">%2$s</div>',
				esc_attr( sanitize_key( $code ) ),
				esc_html( $message )
			);
		}

		return '';
	}
}
