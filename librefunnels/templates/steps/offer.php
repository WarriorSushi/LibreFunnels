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
 *
 * @package LibreFunnels
 */

defined( 'ABSPATH' ) || exit;

$librefunnels_step_id    = isset( $args['step_id'] ) ? absint( $args['step_id'] ) : 0;
$librefunnels_title      = isset( $args['title'] ) ? (string) $args['title'] : '';
$librefunnels_offer      = isset( $args['offer'] ) && is_array( $args['offer'] ) ? $args['offer'] : array();
$librefunnels_product    = isset( $args['product'] ) && is_object( $args['product'] ) ? $args['product'] : null;
$librefunnels_price_html = isset( $args['price_html'] ) ? (string) $args['price_html'] : '';
$librefunnels_headline   = isset( $librefunnels_offer['title'] ) && '' !== $librefunnels_offer['title'] ? (string) $librefunnels_offer['title'] : $librefunnels_title;
$librefunnels_body       = isset( $librefunnels_offer['description'] ) ? (string) $librefunnels_offer['description'] : '';
$librefunnels_discount   = isset( $librefunnels_offer['discount_type'] ) ? sanitize_key( (string) $librefunnels_offer['discount_type'] ) : 'none';
$librefunnels_amount     = isset( $librefunnels_offer['discount_amount'] ) ? (float) $librefunnels_offer['discount_amount'] : 0.0;

if ( 0 === $librefunnels_step_id || ! $librefunnels_product ) {
	return;
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
			<p class="librefunnels-offer-step__eyebrow"><?php esc_html_e( 'Before you continue', 'librefunnels' ); ?></p>

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

			<form class="librefunnels-offer-step__actions" method="post">
				<input type="hidden" name="librefunnels_offer_step_id" value="<?php echo esc_attr( $librefunnels_step_id ); ?>" />
				<?php wp_nonce_field( 'librefunnels_offer_' . $librefunnels_step_id, 'librefunnels_offer_nonce', false ); ?>

				<button class="librefunnels-offer-step__accept" type="submit" name="librefunnels_offer_action" value="accept">
					<?php esc_html_e( 'Add this to my order', 'librefunnels' ); ?>
				</button>

				<button class="librefunnels-offer-step__reject" type="submit" name="librefunnels_offer_action" value="reject">
					<?php esc_html_e( 'No thanks, continue', 'librefunnels' ); ?>
				</button>
			</form>
		</div>
	</div>
</section>
