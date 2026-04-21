<?php
/**
 * Order bump checkout display.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Offers;

use LibreFunnels\Rendering\Template_Loader;

defined( 'ABSPATH' ) || exit;

/**
 * Renders eligible order bumps inside the WooCommerce checkout form.
 */
final class Order_Bump_Display {
	/**
	 * Active checkout step ID.
	 *
	 * @var int
	 */
	private static $active_step_id = 0;

	/**
	 * Bump repository.
	 *
	 * @var Order_Bump_Repository
	 */
	private $repository;

	/**
	 * Eligibility checker.
	 *
	 * @var Offer_Eligibility
	 */
	private $eligibility;

	/**
	 * Template loader.
	 *
	 * @var Template_Loader
	 */
	private $template_loader;

	/**
	 * Creates the display service.
	 *
	 * @param Order_Bump_Repository|null $repository      Optional repository.
	 * @param Offer_Eligibility|null     $eligibility     Optional eligibility checker.
	 * @param Template_Loader|null       $template_loader Optional template loader.
	 */
	public function __construct( Order_Bump_Repository $repository = null, Offer_Eligibility $eligibility = null, Template_Loader $template_loader = null ) {
		$this->repository      = $repository ? $repository : new Order_Bump_Repository();
		$this->eligibility     = $eligibility ? $eligibility : new Offer_Eligibility();
		$this->template_loader = $template_loader ? $template_loader : new Template_Loader();
	}

	/**
	 * Registers WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'render_active_order_bumps' ) );
	}

	/**
	 * Sets the active checkout step ID during checkout rendering.
	 *
	 * @param int $step_id Step ID.
	 * @return void
	 */
	public static function set_active_step_id( $step_id ) {
		self::$active_step_id = absint( $step_id );
	}

	/**
	 * Clears the active checkout step ID.
	 *
	 * @return void
	 */
	public static function clear_active_step_id() {
		self::$active_step_id = 0;
	}

	/**
	 * Renders order bumps for the active checkout step.
	 *
	 * @return void
	 */
	public function render_active_order_bumps() {
		$step_id = absint( self::$active_step_id );

		if ( 0 === $step_id ) {
			return;
		}

		$bumps = $this->get_renderable_bumps( $step_id );

		if ( empty( $bumps ) ) {
			return;
		}

		$this->enqueue_assets();

		$markup = $this->template_loader->render(
			'offers/order-bumps.php',
			array(
				'step_id' => $step_id,
				'bumps'   => $bumps,
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template loader returns escaped LibreFunnels template markup.
		echo $markup;
	}

	/**
	 * Gets eligible bump view models for rendering.
	 *
	 * @param int $step_id Step ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_renderable_bumps( $step_id ) {
		$renderable = array();

		foreach ( $this->repository->get_enabled_bumps_for_step( $step_id ) as $bump ) {
			$result = $this->eligibility->is_product_offer_purchasable( $bump );

			if ( ! $result->is_success() ) {
				continue;
			}

			$product = wc_get_product( $result->get_step_id() );

			if ( ! $product ) {
				continue;
			}

			$renderable[] = array(
				'id'              => $bump['id'],
				'title'           => '' !== $bump['title'] ? $bump['title'] : $product->get_name(),
				'description'     => $bump['description'],
				'price_html'      => method_exists( $product, 'get_price_html' ) ? $product->get_price_html() : '',
				'discount_type'   => $bump['discount_type'],
				'discount_amount' => $bump['discount_amount'],
			);
		}

		return $renderable;
	}

	/**
	 * Enqueues frontend assets used by order bump controls.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		wp_enqueue_style(
			'librefunnels-frontend',
			LIBREFUNNELS_URL . 'assets/css/frontend.css',
			array(),
			LIBREFUNNELS_VERSION
		);

		wp_enqueue_script(
			'librefunnels-order-bumps',
			LIBREFUNNELS_URL . 'assets/js/order-bumps.js',
			array( 'jquery' ),
			LIBREFUNNELS_VERSION,
			true
		);
	}
}
