<?php
/**
 * Block registration.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Blocks;

use LibreFunnels\Rendering\Step_Renderer;
use LibreFunnels\Routing\Funnel_Router;

defined( 'ABSPATH' ) || exit;

/**
 * Registers dynamic blocks for block themes and Gutenberg.
 */
final class Block_Registry {
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
	 * Creates the registry.
	 *
	 * @param Funnel_Router|null $router        Optional funnel router.
	 * @param Step_Renderer|null $step_renderer Optional step renderer.
	 */
	public function __construct( Funnel_Router $router = null, Step_Renderer $step_renderer = null ) {
		$this->router        = $router ? $router : new Funnel_Router();
		$this->step_renderer = $step_renderer ? $step_renderer : new Step_Renderer();
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers dynamic block types.
	 *
	 * @return void
	 */
	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			LIBREFUNNELS_PATH . 'blocks/funnel',
			array(
				'render_callback' => array( $this, 'render_funnel_block' ),
			)
		);

		register_block_type(
			LIBREFUNNELS_PATH . 'blocks/step',
			array(
				'render_callback' => array( $this, 'render_step_block' ),
			)
		);
	}

	/**
	 * Renders the funnel block.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_funnel_block( $attributes ) {
		$funnel_id = isset( $attributes['funnelId'] ) ? absint( $attributes['funnelId'] ) : 0;

		if ( 0 === $funnel_id ) {
			return $this->render_error( __( 'Choose a LibreFunnels funnel before publishing this block.', 'librefunnels' ), 'missing-funnel-id' );
		}

		$result = $this->router->get_start_step( $funnel_id );

		if ( ! $result->is_success() ) {
			return $this->render_error( $result->get_message(), $result->get_code() );
		}

		return $this->step_renderer->render_step( $result->get_step_id() );
	}

	/**
	 * Renders the step block.
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render_step_block( $attributes ) {
		$step_id = isset( $attributes['stepId'] ) ? absint( $attributes['stepId'] ) : 0;

		if ( 0 === $step_id ) {
			return $this->render_error( __( 'Choose a LibreFunnels step before publishing this block.', 'librefunnels' ), 'missing-step-id' );
		}

		return $this->step_renderer->render_step( $step_id );
	}

	/**
	 * Renders a safe frontend error for invalid block state.
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
