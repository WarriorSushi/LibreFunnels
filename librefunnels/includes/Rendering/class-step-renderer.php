<?php
/**
 * Funnel step renderer.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Rendering;

use LibreFunnels\Checkout\Checkout_Field_Customizer;
use LibreFunnels\Checkout\Cart_Preparer;
use LibreFunnels\Offers\Offer_Eligibility;
use LibreFunnels\Offers\Order_Bump_Display;
use LibreFunnels\Offers\Step_Offer_Repository;

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
	 * Step offer repository.
	 *
	 * @var Step_Offer_Repository
	 */
	private $offer_repository;

	/**
	 * Offer eligibility checker.
	 *
	 * @var Offer_Eligibility
	 */
	private $offer_eligibility;

	/**
	 * Creates the renderer.
	 *
	 * @param Template_Loader|null       $template_loader    Optional template loader.
	 * @param Cart_Preparer|null         $cart_preparer      Optional cart preparer.
	 * @param Step_Offer_Repository|null $offer_repository  Optional offer repository.
	 * @param Offer_Eligibility|null     $offer_eligibility  Optional offer eligibility checker.
	 */
	public function __construct( Template_Loader $template_loader = null, Cart_Preparer $cart_preparer = null, Step_Offer_Repository $offer_repository = null, Offer_Eligibility $offer_eligibility = null ) {
		$this->template_loader   = $template_loader ? $template_loader : new Template_Loader();
		$this->cart_preparer     = $cart_preparer ? $cart_preparer : new Cart_Preparer();
		$this->offer_repository  = $offer_repository ? $offer_repository : new Step_Offer_Repository();
		$this->offer_eligibility = $offer_eligibility ? $offer_eligibility : new Offer_Eligibility();
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

		if ( in_array( $step_type, array( 'pre_checkout_offer', 'upsell', 'downsell', 'cross_sell' ), true ) ) {
			return $this->render_offer_step( $step, $step_type );
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
		Order_Bump_Display::set_active_step_id( $step->ID );

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
			Order_Bump_Display::clear_active_step_id();
		}
	}

	/**
	 * Renders a pre-checkout offer step.
	 *
	 * @param \WP_Post $step      Step post.
	 * @param string   $step_type Step type.
	 * @return string
	 */
	private function render_offer_step( $step, $step_type ) {
		$offer = $this->offer_repository->get_offer_for_step( $step->ID );

		if ( empty( $offer['enabled'] ) ) {
			return $this->render_error( __( 'This LibreFunnels offer is disabled.', 'librefunnels' ), 'offer-disabled' );
		}

		$result = $this->offer_eligibility->is_product_offer_purchasable( $offer );

		if ( ! $result->is_success() ) {
			return $this->render_error( $result->get_message(), $result->get_code() );
		}

		$product = wc_get_product( $result->get_step_id() );

		if ( ! $product ) {
			return $this->render_error( __( 'This LibreFunnels offer product could not be found.', 'librefunnels' ), 'offer-product-not-found' );
		}

		$this->enqueue_frontend_assets();
		do_action( 'librefunnels_offer_impression', absint( get_post_meta( $step->ID, LIBREFUNNELS_STEP_FUNNEL_ID_META, true ) ), absint( $step->ID ), $offer );

		return $this->template_loader->render(
			'steps/offer.php',
			array(
				'step'       => $step,
				'step_id'    => absint( $step->ID ),
				'step_type'  => $step_type,
				'title'      => get_the_title( $step ),
				'offer'      => $offer,
				'product'    => $product,
				'price_html' => method_exists( $product, 'get_price_html' ) ? $product->get_price_html() : '',
			)
		);
	}

	/**
	 * Enqueues shared frontend assets.
	 *
	 * @return void
	 */
	private function enqueue_frontend_assets() {
		wp_enqueue_style(
			'librefunnels-frontend',
			LIBREFUNNELS_URL . 'assets/css/frontend.css',
			array(),
			LIBREFUNNELS_VERSION
		);
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
