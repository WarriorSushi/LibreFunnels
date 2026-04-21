<?php
/**
 * Order bump checkout handling.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Offers;

defined( 'ABSPATH' ) || exit;

/**
 * Applies selected order bump products to the WooCommerce cart.
 */
final class Order_Bump_Handler {
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
	 * Discount calculator.
	 *
	 * @var Discount_Calculator
	 */
	private $discount_calculator;

	/**
	 * Creates the handler.
	 *
	 * @param Order_Bump_Repository|null $repository          Optional repository.
	 * @param Offer_Eligibility|null     $eligibility         Optional eligibility checker.
	 * @param Discount_Calculator|null   $discount_calculator Optional discount calculator.
	 */
	public function __construct( Order_Bump_Repository $repository = null, Offer_Eligibility $eligibility = null, Discount_Calculator $discount_calculator = null ) {
		$this->repository          = $repository ? $repository : new Order_Bump_Repository();
		$this->eligibility         = $eligibility ? $eligibility : new Offer_Eligibility();
		$this->discount_calculator = $discount_calculator ? $discount_calculator : new Discount_Calculator();
	}

	/**
	 * Registers WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'sync_from_order_review' ), 5 );
		add_action( 'woocommerce_checkout_process', array( $this, 'sync_from_checkout_post' ), 5 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_bump_discounts' ), 20 );
	}

	/**
	 * Syncs selected bumps during checkout AJAX refreshes.
	 *
	 * @param string $post_data Serialized checkout form data.
	 * @return void
	 */
	public function sync_from_order_review( $post_data ) {
		$data = array();
		wp_parse_str( $post_data, $data );

		$this->sync_selected_bumps( $data, false );
	}

	/**
	 * Syncs selected bumps during final checkout submission.
	 *
	 * @return void
	 */
	public function sync_from_checkout_post() {
		$this->sync_selected_bumps( $this->get_unslashed_post_data(), true );
	}

	/**
	 * Syncs selected bump IDs to the current cart.
	 *
	 * @param array<string,mixed> $data        Request data.
	 * @param bool                $add_notices Whether to add checkout notices.
	 * @return void
	 */
	private function sync_selected_bumps( array $data, $add_notices ) {
		if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$step_id = isset( $data['librefunnels_step_id'] ) ? absint( $data['librefunnels_step_id'] ) : 0;

		if ( 0 === $step_id ) {
			return;
		}

		$nonce = isset( $data['librefunnels_order_bump_nonce'] ) ? sanitize_text_field( (string) $data['librefunnels_order_bump_nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'librefunnels_order_bumps_' . $step_id ) ) {
			$this->maybe_add_notice(
				__( 'LibreFunnels could not verify the selected order bump. Please refresh checkout and try again.', 'librefunnels' ),
				$add_notices
			);
			return;
		}

		$selected_ids = $this->sanitize_selected_ids( isset( $data['librefunnels_order_bumps'] ) ? $data['librefunnels_order_bumps'] : array() );
		$bumps        = $this->get_enabled_bumps_by_id( $step_id );

		$this->remove_unselected_bumps( $step_id, $selected_ids );

		foreach ( $selected_ids as $bump_id ) {
			if ( ! isset( $bumps[ $bump_id ] ) ) {
				$this->maybe_add_notice(
					__( 'One selected order bump is no longer available.', 'librefunnels' ),
					$add_notices
				);
				continue;
			}

			$this->add_bump_to_cart( $step_id, $bumps[ $bump_id ], $add_notices );
		}
	}

	/**
	 * Adds a bump product to the cart when it is not already present.
	 *
	 * @param int                 $step_id     Step ID.
	 * @param array<string,mixed> $bump        Bump data.
	 * @param bool                $add_notices Whether to add checkout notices.
	 * @return void
	 */
	private function add_bump_to_cart( $step_id, array $bump, $add_notices ) {
		$result = $this->eligibility->is_product_offer_purchasable( $bump );

		if ( ! $result->is_success() ) {
			$this->maybe_add_notice( $result->get_message(), $add_notices );
			return;
		}

		$woocommerce = WC();

		if ( ! $woocommerce || ! $woocommerce->cart ) {
			return;
		}

		if ( $this->cart_contains_bump( $step_id, $bump['id'] ) ) {
			return;
		}

		$product_id   = absint( $bump['product_id'] );
		$variation_id = absint( $bump['variation_id'] );
		$quantity     = max( 1, absint( $bump['quantity'] ) );
		$variation    = isset( $bump['variation'] ) && is_array( $bump['variation'] ) ? $bump['variation'] : array();
		$product      = wc_get_product( $variation_id ? $variation_id : $product_id );

		$cart_item_key = $woocommerce->cart->add_to_cart(
			$product_id,
			$quantity,
			$variation_id,
			$variation,
			array(
				'librefunnels_order_bump'         => true,
				'librefunnels_order_bump_id'      => $bump['id'],
				'librefunnels_order_bump_step_id' => absint( $step_id ),
				'librefunnels_discount_type'      => $bump['discount_type'],
				'librefunnels_discount_amount'    => $bump['discount_amount'],
				'librefunnels_original_price'     => $product && method_exists( $product, 'get_price' ) ? (float) $product->get_price( 'edit' ) : 0.0,
			)
		);

		if ( ! $cart_item_key ) {
			$this->maybe_add_notice(
				__( 'LibreFunnels could not add the selected order bump to the cart.', 'librefunnels' ),
				$add_notices
			);
		}
	}

	/**
	 * Applies order bump discounts during WooCommerce total calculation.
	 *
	 * @param \WC_Cart $cart WooCommerce cart.
	 * @return void
	 */
	public function apply_bump_discounts( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! $cart || ! method_exists( $cart, 'get_cart' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			$is_discountable_offer = ! empty( $cart_item['librefunnels_order_bump'] ) || ! empty( $cart_item['librefunnels_pre_checkout_offer'] );

			if ( ! $is_discountable_offer || empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			if ( ! method_exists( $cart_item['data'], 'set_price' ) ) {
				continue;
			}

			$original_price = isset( $cart_item['librefunnels_original_price'] ) ? (float) $cart_item['librefunnels_original_price'] : $this->get_product_price( $cart_item['data'] );
			$discount_type  = isset( $cart_item['librefunnels_discount_type'] ) ? sanitize_key( (string) $cart_item['librefunnels_discount_type'] ) : 'none';
			$discount       = isset( $cart_item['librefunnels_discount_amount'] ) ? (float) $cart_item['librefunnels_discount_amount'] : 0.0;
			$new_price      = $this->discount_calculator->calculate_price( $original_price, $discount_type, $discount );

			$cart_item['data']->set_price( $this->format_price_for_woocommerce( $new_price ) );
		}
	}

	/**
	 * Removes previously selected bumps that are no longer selected.
	 *
	 * @param int      $step_id      Step ID.
	 * @param string[] $selected_ids Selected bump IDs.
	 * @return void
	 */
	private function remove_unselected_bumps( $step_id, array $selected_ids ) {
		$woocommerce = WC();

		if ( ! $woocommerce || ! $woocommerce->cart ) {
			return;
		}

		foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $cart_item ) {
			$is_bump        = ! empty( $cart_item['librefunnels_order_bump'] );
			$bump_step_id   = isset( $cart_item['librefunnels_order_bump_step_id'] ) ? absint( $cart_item['librefunnels_order_bump_step_id'] ) : 0;
			$bump_id        = isset( $cart_item['librefunnels_order_bump_id'] ) ? sanitize_key( (string) $cart_item['librefunnels_order_bump_id'] ) : '';
			$still_selected = in_array( $bump_id, $selected_ids, true );

			if ( $is_bump && absint( $step_id ) === $bump_step_id && ! $still_selected ) {
				$woocommerce->cart->remove_cart_item( $cart_item_key );
			}
		}
	}

	/**
	 * Checks whether a bump is already in the cart.
	 *
	 * @param int    $step_id Step ID.
	 * @param string $bump_id Bump ID.
	 * @return bool
	 */
	private function cart_contains_bump( $step_id, $bump_id ) {
		$woocommerce = WC();

		if ( ! $woocommerce || ! $woocommerce->cart ) {
			return false;
		}

		foreach ( $woocommerce->cart->get_cart() as $cart_item ) {
			$item_step_id = isset( $cart_item['librefunnels_order_bump_step_id'] ) ? absint( $cart_item['librefunnels_order_bump_step_id'] ) : 0;
			$item_bump_id = isset( $cart_item['librefunnels_order_bump_id'] ) ? sanitize_key( (string) $cart_item['librefunnels_order_bump_id'] ) : '';

			if ( absint( $step_id ) === $item_step_id && sanitize_key( $bump_id ) === $item_bump_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets enabled bumps keyed by bump ID.
	 *
	 * @param int $step_id Step ID.
	 * @return array<string,array<string,mixed>>
	 */
	private function get_enabled_bumps_by_id( $step_id ) {
		$bumps = array();

		foreach ( $this->repository->get_enabled_bumps_for_step( $step_id ) as $bump ) {
			$bumps[ $bump['id'] ] = $bump;
		}

		return $bumps;
	}

	/**
	 * Sanitizes selected bump IDs.
	 *
	 * @param mixed $selected Raw selected IDs.
	 * @return string[]
	 */
	private function sanitize_selected_ids( $selected ) {
		if ( ! is_array( $selected ) ) {
			$selected = array( $selected );
		}

		$ids = array();

		foreach ( $selected as $id ) {
			$id = sanitize_key( (string) $id );

			if ( '' !== $id ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Gets unslashed POST data.
	 *
	 * @return array<string,mixed>
	 */
	private function get_unslashed_post_data() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is checked by sync_selected_bumps().
		return isset( $_POST ) && is_array( $_POST ) ? wp_unslash( $_POST ) : array();
	}

	/**
	 * Adds a checkout notice when notices are requested.
	 *
	 * @param string $message     Notice message.
	 * @param bool   $add_notices Whether to add notices.
	 * @return void
	 */
	private function maybe_add_notice( $message, $add_notices ) {
		if ( $add_notices && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, 'error' );
		}
	}

	/**
	 * Gets an editable product price.
	 *
	 * @param object $product Product object.
	 * @return float
	 */
	private function get_product_price( $product ) {
		if ( method_exists( $product, 'get_price' ) ) {
			return (float) $product->get_price( 'edit' );
		}

		return 0.0;
	}

	/**
	 * Formats a price for WC_Product::set_price().
	 *
	 * @param float $price Price.
	 * @return float|string
	 */
	private function format_price_for_woocommerce( $price ) {
		$price = max( 0.0, (float) $price );

		if ( function_exists( 'wc_format_decimal' ) ) {
			return wc_format_decimal( $price );
		}

		return $price;
	}
}
