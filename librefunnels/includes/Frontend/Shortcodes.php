<?php
/**
 * Frontend shortcodes.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Frontend;

use LibreFunnels\Rendering\Step_Renderer;
use LibreFunnels\Routing\Funnel_Router;

defined( 'ABSPATH' ) || exit;

/**
 * Registers shortcode entry points for theme and page-builder compatibility.
 */
final class Shortcodes {
	/**
	 * Funnel router.
	 *
	 * @var Funnel_Router
	 */
	private $router;

	/**
	 * Step renderer.
	 *
	 * @var Step_Renderer
	 */
	private $step_renderer;

	/**
	 * Creates the shortcode registry.
	 *
	 * @param Funnel_Router|null $router        Optional funnel router.
	 * @param Step_Renderer|null $step_renderer Optional step renderer.
	 */
	public function __construct( Funnel_Router $router = null, Step_Renderer $step_renderer = null ) {
		$this->router        = $router ? $router : new Funnel_Router();
		$this->step_renderer = $step_renderer ? $step_renderer : new Step_Renderer();
	}

	/**
	 * Registers shortcode tags.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'librefunnels_funnel', array( $this, 'render_funnel_shortcode' ) );
		add_shortcode( 'librefunnels_step', array( $this, 'render_step_shortcode' ) );
	}

	/**
	 * Renders the configured start step for a funnel.
	 *
	 * Usage: [librefunnels_funnel id="123"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_funnel_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			(array) $atts,
			'librefunnels_funnel'
		);

		$funnel_id = absint( $atts['id'] );

		if ( 0 === $funnel_id ) {
			return $this->render_error( __( 'LibreFunnels needs a funnel ID to render this shortcode.', 'librefunnels' ), 'missing-funnel-id' );
		}

		$result = $this->router->get_start_step( $funnel_id );

		if ( ! $result->is_success() ) {
			return $this->render_error( $result->get_message(), $result->get_code() );
		}

		return $this->step_renderer->render_step( $result->get_step_id() );
	}

	/**
	 * Renders a single funnel step.
	 *
	 * Usage: [librefunnels_step id="456"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_step_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			(array) $atts,
			'librefunnels_step'
		);

		$step_id = absint( $atts['id'] );

		if ( 0 === $step_id ) {
			return $this->render_error( __( 'LibreFunnels needs a step ID to render this shortcode.', 'librefunnels' ), 'missing-step-id' );
		}

		return $this->step_renderer->render_step( $step_id );
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
