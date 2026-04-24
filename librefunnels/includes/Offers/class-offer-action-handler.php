<?php
/**
 * Offer action handling.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Offers;

use LibreFunnels\Rules\WooCommerce_Fact_Collector;
use LibreFunnels\Routing\Funnel_Router;

defined( 'ABSPATH' ) || exit;

/**
 * Processes accept/reject actions for pre-checkout offer steps.
 */
final class Offer_Action_Handler {
	/**
	 * Offer repository.
	 *
	 * @var Step_Offer_Repository
	 */
	private $repository;

	/**
	 * Eligibility checker.
	 *
	 * @var Offer_Eligibility
	 */
	private $eligibility;

	/**
	 * Funnel router.
	 *
	 * @var Funnel_Router
	 */
	private $router;

	/**
	 * Offer state store.
	 *
	 * @var Offer_State
	 */
	private $offer_state;

	/**
	 * WooCommerce fact collector.
	 *
	 * @var WooCommerce_Fact_Collector
	 */
	private $fact_collector;

	/**
	 * Creates the handler.
	 *
	 * @param Step_Offer_Repository|null      $repository     Optional repository.
	 * @param Offer_Eligibility|null          $eligibility    Optional eligibility checker.
	 * @param Funnel_Router|null              $router         Optional router.
	 * @param Offer_State|null                $offer_state    Optional offer state store.
	 * @param WooCommerce_Fact_Collector|null $fact_collector Optional fact collector.
	 */
	public function __construct( Step_Offer_Repository $repository = null, Offer_Eligibility $eligibility = null, Funnel_Router $router = null, Offer_State $offer_state = null, WooCommerce_Fact_Collector $fact_collector = null ) {
		$this->repository     = $repository ? $repository : new Step_Offer_Repository();
		$this->eligibility    = $eligibility ? $eligibility : new Offer_Eligibility();
		$this->router         = $router ? $router : new Funnel_Router();
		$this->offer_state    = $offer_state ? $offer_state : new Offer_State();
		$this->fact_collector = $fact_collector ? $fact_collector : new WooCommerce_Fact_Collector();
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'handle_offer_action' ), 1 );
	}

	/**
	 * Handles an offer accept/reject POST action.
	 *
	 * @return void
	 */
	public function handle_offer_action() {
		$data = $this->get_offer_post_data();

		if ( empty( $data ) ) {
			return;
		}

		$step_id = isset( $data['librefunnels_offer_step_id'] ) ? absint( $data['librefunnels_offer_step_id'] ) : 0;
		$action  = isset( $data['librefunnels_offer_action'] ) ? sanitize_key( (string) $data['librefunnels_offer_action'] ) : '';
		$nonce   = isset( $data['librefunnels_offer_nonce'] ) ? sanitize_text_field( (string) $data['librefunnels_offer_nonce'] ) : '';

		if ( 0 === $step_id || ! in_array( $action, array( 'accept', 'reject' ), true ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'librefunnels_offer_' . $step_id ) ) {
			$this->add_notice( __( 'LibreFunnels could not verify this offer action. Please refresh and try again.', 'librefunnels' ) );
			return;
		}

		$step = get_post( $step_id );

		if ( ! $step || LIBREFUNNELS_STEP_POST_TYPE !== $step->post_type ) {
			$this->add_notice( __( 'This LibreFunnels offer step could not be found.', 'librefunnels' ) );
			return;
		}

		$funnel_id = absint( get_post_meta( $step_id, LIBREFUNNELS_STEP_FUNNEL_ID_META, true ) );

		if ( 0 === $funnel_id ) {
			$this->add_notice( __( 'This LibreFunnels offer is not connected to a funnel.', 'librefunnels' ) );
			return;
		}

		$offer = $this->repository->get_offer_for_step( $step_id );

		if ( 'accept' === $action && ! $this->add_offer_to_cart( $step_id, $offer ) ) {
			return;
		}

		$this->offer_state->record_action( $step_id, isset( $offer['id'] ) ? $offer['id'] : '', $action );
		do_action( 'librefunnels_offer_action_recorded', $funnel_id, $step_id, $action, $offer );
		$this->redirect_to_route( $funnel_id, $step_id, $action );
	}

	/**
	 * Adds an accepted offer product to the cart.
	 *
	 * @param int                 $step_id Step ID.
	 * @param array<string,mixed> $offer   Offer data.
	 * @return bool
	 */
	private function add_offer_to_cart( $step_id, array $offer ) {
		if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_product' ) ) {
			$this->add_notice( __( 'WooCommerce cart services are not available.', 'librefunnels' ) );
			return false;
		}

		$result = $this->eligibility->is_product_offer_purchasable( $offer );

		if ( ! $result->is_success() ) {
			$this->add_notice( $result->get_message() );
			return false;
		}

		$woocommerce = WC();

		if ( ! $woocommerce || ! $woocommerce->cart ) {
			$this->add_notice( __( 'The WooCommerce cart is not available.', 'librefunnels' ) );
			return false;
		}

		$product_id   = absint( $offer['product_id'] );
		$variation_id = absint( $offer['variation_id'] );
		$quantity     = max( 1, absint( $offer['quantity'] ) );
		$variation    = isset( $offer['variation'] ) && is_array( $offer['variation'] ) ? $offer['variation'] : array();
		$product      = wc_get_product( $variation_id ? $variation_id : $product_id );
		$funnel_id    = absint( get_post_meta( absint( $step_id ), LIBREFUNNELS_STEP_FUNNEL_ID_META, true ) );

		if ( $this->cart_contains_offer( $step_id, $offer['id'] ) ) {
			return true;
		}

		$cart_item_key = $woocommerce->cart->add_to_cart(
			$product_id,
			$quantity,
			$variation_id,
			$variation,
			array(
				'librefunnels_pre_checkout_offer' => true,
				'librefunnels_offer_id'           => $offer['id'],
				'librefunnels_offer_step_id'      => absint( $step_id ),
				'librefunnels_funnel_id'          => $funnel_id,
				'librefunnels_discount_type'      => $offer['discount_type'],
				'librefunnels_discount_amount'    => $offer['discount_amount'],
				'librefunnels_original_price'     => $product && method_exists( $product, 'get_price' ) ? (float) $product->get_price( 'edit' ) : 0.0,
			)
		);

		if ( ! $cart_item_key ) {
			$this->add_notice( __( 'LibreFunnels could not add the accepted offer to the cart.', 'librefunnels' ) );
			return false;
		}

		return true;
	}

	/**
	 * Redirects to the next step for a route.
	 *
	 * @param int    $funnel_id Funnel ID.
	 * @param int    $step_id   Step ID.
	 * @param string $route     Route.
	 * @return void
	 */
	private function redirect_to_route( $funnel_id, $step_id, $route ) {
		$result = $this->router->get_next_step_with_facts( $funnel_id, $step_id, $route, $this->fact_collector->collect() );

		if ( ! $result->is_success() ) {
			$this->add_notice( $result->get_message() );
			return;
		}

		$url = $this->get_step_url( $result->get_step_id() );

		if ( '' === $url ) {
			$this->add_notice( __( 'The next LibreFunnels step does not have a page assigned.', 'librefunnels' ) );
			return;
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Gets a step frontend URL from its assigned page.
	 *
	 * @param int $step_id Step ID.
	 * @return string
	 */
	private function get_step_url( $step_id ) {
		$page_id = absint( get_post_meta( absint( $step_id ), LIBREFUNNELS_STEP_PAGE_ID_META, true ) );

		if ( 0 === $page_id ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Checks whether this pre-checkout offer is already in the cart.
	 *
	 * @param int    $step_id  Step ID.
	 * @param string $offer_id Offer ID.
	 * @return bool
	 */
	private function cart_contains_offer( $step_id, $offer_id ) {
		$woocommerce = WC();

		if ( ! $woocommerce || ! $woocommerce->cart ) {
			return false;
		}

		foreach ( $woocommerce->cart->get_cart() as $cart_item ) {
			$item_step_id  = isset( $cart_item['librefunnels_offer_step_id'] ) ? absint( $cart_item['librefunnels_offer_step_id'] ) : 0;
			$item_offer_id = isset( $cart_item['librefunnels_offer_id'] ) ? sanitize_key( (string) $cart_item['librefunnels_offer_id'] ) : '';

			if ( absint( $step_id ) === $item_step_id && sanitize_key( $offer_id ) === $item_offer_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets unslashed offer POST data when an offer action is present.
	 *
	 * @return array<string,mixed>
	 */
	private function get_offer_post_data() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified after the action and step are sanitized.
		if ( empty( $_POST['librefunnels_offer_action'] ) || ! is_array( $_POST ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by handle_offer_action() before data is used.
		return wp_unslash( $_POST );
	}

	/**
	 * Adds an error notice when WooCommerce notices are available.
	 *
	 * @param string $message Notice message.
	 * @return void
	 */
	private function add_notice( $message ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, 'error' );
		}
	}
}
