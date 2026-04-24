<?php
/**
 * Default pre-checkout offer step template.
 *
 * Available args:
 * - $args['step'] WP_Post
 * - $args['step_id'] int
 * - $args['step_type'] string
 * - $args['title'] string
 * - $args['offer'] array
 * - $args['product'] WC_Product
 * - $args['price_html'] string
 * - $args['payment'] array
 *
 * @package LibreFunnels
 */

defined( 'ABSPATH' ) || exit;

$librefunnels_step_id         = isset( $args['step_id'] ) ? absint( $args['step_id'] ) : 0;
$librefunnels_title           = isset( $args['title'] ) ? (string) $args['title'] : '';
$librefunnels_offer           = isset( $args['offer'] ) && is_array( $args['offer'] ) ? $args['offer'] : array();
$librefunnels_product         = isset( $args['product'] ) && is_object( $args['product'] ) ? $args['product'] : null;
$librefunnels_price_html      = isset( $args['price_html'] ) ? (string) $args['price_html'] : '';
$librefunnels_step_type       = isset( $args['step_type'] ) ? sanitize_key( (string) $args['step_type'] ) : 'pre_checkout_offer';
$librefunnels_headline        = isset( $librefunnels_offer['title'] ) && '' !== $librefunnels_offer['title'] ? (string) $librefunnels_offer['title'] : $librefunnels_title;
$librefunnels_body            = isset( $librefunnels_offer['description'] ) ? (string) $librefunnels_offer['description'] : '';
$librefunnels_discount        = isset( $librefunnels_offer['discount_type'] ) ? sanitize_key( (string) $librefunnels_offer['discount_type'] ) : 'none';
$librefunnels_amount          = isset( $librefunnels_offer['discount_amount'] ) ? (float) $librefunnels_offer['discount_amount'] : 0.0;
$librefunnels_payment         = isset( $args['payment'] ) && is_array( $args['payment'] ) ? $args['payment'] : array();
$librefunnels_payment_mode    = isset( $librefunnels_payment['mode'] ) ? sanitize_key( (string) $librefunnels_payment['mode'] ) : 'cart_before_checkout';
$librefunnels_payment_message = isset( $librefunnels_payment['message'] ) ? (string) $librefunnels_payment['message'] : '';
$librefunnels_order_id        = isset( $librefunnels_payment['orderId'] ) ? absint( $librefunnels_payment['orderId'] ) : 0;
$librefunnels_order_key       = isset( $librefunnels_payment['orderKey'] ) ? (string) $librefunnels_payment['orderKey'] : '';

if ( 0 === $librefunnels_step_id || ! $librefunnels_product ) {
	return;
}

$librefunnels_eyebrow = __( 'Before you continue', 'librefunnels' );
$librefunnels_accept  = __( 'Add this to my order', 'librefunnels' );

if ( in_array( $librefunnels_step_type, array( 'upsell', 'downsell', 'cross_sell' ), true ) ) {
	$librefunnels_eyebrow = 'one_click' === $librefunnels_payment_mode ? __( 'One-click offer', 'librefunnels' ) : __( 'Secure checkout confirmation', 'librefunnels' );
	$librefunnels_accept  = 'one_click' === $librefunnels_payment_mode ? __( 'Add offer to this order', 'librefunnels' ) : __( 'Add offer and confirm', 'librefunnels' );
}
?>
<section class="librefunnels-step librefunnels-step--offer">
	<div class="librefunnels-offer-step">
		<div class="librefunnels-offer-step__media">
			<?php if ( method_exists( $librefunnels_product, 'get_image' ) ) : ?>
				<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce product image HTML is trusted product output. ?>
				<?php echo $librefunnels_product->get_image( 'woocommerce_single' ); ?>
			<?php endif; ?>
		</div>

		<div class="librefunnels-offer-step__content">
			<p class="librefunnels-offer-step__eyebrow"><?php echo esc_html( $librefunnels_eyebrow ); ?></p>

			<?php if ( '' !== $librefunnels_headline ) : ?>
				<h1 class="librefunnels-offer-step__title"><?php echo esc_html( $librefunnels_headline ); ?></h1>
			<?php endif; ?>

			<?php if ( '' !== $librefunnels_body ) : ?>
				<div class="librefunnels-offer-step__description">
					<?php echo wp_kses_post( $librefunnels_body ); ?>
				</div>
			<?php endif; ?>

			<div class="librefunnels-offer-step__summary">
				<?php if ( 'none' !== $librefunnels_discount && 0.0 < $librefunnels_amount ) : ?>
					<span class="librefunnels-offer-step__discount">
						<?php
						if ( 'percentage' === $librefunnels_discount ) {
							echo esc_html(
								sprintf(
									/* translators: %s: Discount percentage. */
									__( '%s%% offer discount', 'librefunnels' ),
									rtrim( rtrim( number_format_i18n( $librefunnels_amount, 2 ), '0' ), '.' )
								)
							);
						} else {
							echo esc_html(
								sprintf(
									/* translators: %s: Discount amount. */
									__( '%s offer discount', 'librefunnels' ),
									wp_strip_all_tags( wc_price( $librefunnels_amount ) )
								)
							);
						}
						?>
					</span>
				<?php endif; ?>

				<?php if ( '' !== $librefunnels_price_html ) : ?>
					<span class="librefunnels-offer-step__price">
						<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce product price HTML is trusted product output. ?>
						<?php echo $librefunnels_price_html; ?>
					</span>
				<?php endif; ?>
			</div>

			<?php if ( in_array( $librefunnels_step_type, array( 'upsell', 'downsell', 'cross_sell' ), true ) && '' !== $librefunnels_payment_message ) : ?>
				<p class="librefunnels-offer-step__payment-note">
					<?php echo esc_html( $librefunnels_payment_message ); ?>
				</p>
			<?php endif; ?>

			<form class="librefunnels-offer-step__actions" method="post">
				<input type="hidden" name="librefunnels_offer_step_id" value="<?php echo esc_attr( $librefunnels_step_id ); ?>" />
				<?php if ( $librefunnels_order_id > 0 && '' !== $librefunnels_order_key ) : ?>
					<input type="hidden" name="librefunnels_order_id" value="<?php echo esc_attr( $librefunnels_order_id ); ?>" />
					<input type="hidden" name="librefunnels_order_key" value="<?php echo esc_attr( $librefunnels_order_key ); ?>" />
				<?php endif; ?>
				<?php wp_nonce_field( 'librefunnels_offer_' . $librefunnels_step_id, 'librefunnels_offer_nonce', false ); ?>

				<button class="librefunnels-offer-step__accept" type="submit" name="librefunnels_offer_action" value="accept">
					<?php echo esc_html( $librefunnels_accept ); ?>
				</button>

				<button class="librefunnels-offer-step__reject" type="submit" name="librefunnels_offer_action" value="reject">
					<?php esc_html_e( 'No thanks, continue', 'librefunnels' ); ?>
				</button>
			</form>
		</div>
	</div>
</section>
