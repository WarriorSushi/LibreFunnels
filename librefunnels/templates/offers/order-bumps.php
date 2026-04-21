<?php
/**
 * Default checkout order bumps template.
 *
 * Available args:
 * - $args['step_id'] int
 * - $args['bumps'] array
 *
 * @package LibreFunnels
 */

defined( 'ABSPATH' ) || exit;

$librefunnels_step_id = isset( $args['step_id'] ) ? absint( $args['step_id'] ) : 0;
$librefunnels_bumps   = isset( $args['bumps'] ) && is_array( $args['bumps'] ) ? $args['bumps'] : array();

if ( 0 === $librefunnels_step_id || empty( $librefunnels_bumps ) ) {
	return;
}
?>
<section class="librefunnels-order-bumps" aria-label="<?php echo esc_attr__( 'Recommended add-ons', 'librefunnels' ); ?>">
	<input type="hidden" name="librefunnels_step_id" value="<?php echo esc_attr( $librefunnels_step_id ); ?>" />
	<?php wp_nonce_field( 'librefunnels_order_bumps_' . $librefunnels_step_id, 'librefunnels_order_bump_nonce', false ); ?>

	<div class="librefunnels-order-bumps__header">
		<p class="librefunnels-order-bumps__eyebrow"><?php esc_html_e( 'Optional upgrade', 'librefunnels' ); ?></p>
		<h2 class="librefunnels-order-bumps__title"><?php esc_html_e( 'Complete your order with one useful add-on', 'librefunnels' ); ?></h2>
	</div>

	<div class="librefunnels-order-bumps__list">
		<?php foreach ( $librefunnels_bumps as $librefunnels_bump ) : ?>
			<?php
			$librefunnels_bump_id     = isset( $librefunnels_bump['id'] ) ? sanitize_key( (string) $librefunnels_bump['id'] ) : '';
			$librefunnels_input_id    = 'librefunnels-order-bump-' . $librefunnels_step_id . '-' . $librefunnels_bump_id;
			$librefunnels_title       = isset( $librefunnels_bump['title'] ) ? (string) $librefunnels_bump['title'] : '';
			$librefunnels_description = isset( $librefunnels_bump['description'] ) ? (string) $librefunnels_bump['description'] : '';
			$librefunnels_price_html  = isset( $librefunnels_bump['price_html'] ) ? (string) $librefunnels_bump['price_html'] : '';
			$librefunnels_discount    = isset( $librefunnels_bump['discount_type'] ) ? sanitize_key( (string) $librefunnels_bump['discount_type'] ) : 'none';
			$librefunnels_amount      = isset( $librefunnels_bump['discount_amount'] ) ? (float) $librefunnels_bump['discount_amount'] : 0.0;
			?>
			<label class="librefunnels-order-bump" for="<?php echo esc_attr( $librefunnels_input_id ); ?>">
				<span class="librefunnels-order-bump__control">
					<input
						id="<?php echo esc_attr( $librefunnels_input_id ); ?>"
						class="librefunnels-order-bump__input"
						type="checkbox"
						name="librefunnels_order_bumps[]"
						value="<?php echo esc_attr( $librefunnels_bump_id ); ?>"
					/>
					<span class="librefunnels-order-bump__check" aria-hidden="true"></span>
				</span>

				<span class="librefunnels-order-bump__body">
					<span class="librefunnels-order-bump__main">
						<strong class="librefunnels-order-bump__title"><?php echo esc_html( $librefunnels_title ); ?></strong>
						<?php if ( '' !== $librefunnels_description ) : ?>
							<span class="librefunnels-order-bump__description">
								<?php echo wp_kses_post( $librefunnels_description ); ?>
							</span>
						<?php endif; ?>
					</span>

					<span class="librefunnels-order-bump__meta">
						<?php if ( 'none' !== $librefunnels_discount && 0.0 < $librefunnels_amount ) : ?>
							<span class="librefunnels-order-bump__discount">
								<?php
								if ( 'percentage' === $librefunnels_discount ) {
									echo esc_html(
										sprintf(
											/* translators: %s: Discount percentage. */
											__( '%s%% off', 'librefunnels' ),
											rtrim( rtrim( number_format_i18n( $librefunnels_amount, 2 ), '0' ), '.' )
										)
									);
								} else {
									echo esc_html(
										sprintf(
											/* translators: %s: Discount amount. */
											__( '%s off', 'librefunnels' ),
											wp_strip_all_tags( wc_price( $librefunnels_amount ) )
										)
									);
								}
								?>
							</span>
						<?php endif; ?>

						<?php if ( '' !== $librefunnels_price_html ) : ?>
							<span class="librefunnels-order-bump__price">
								<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce product price HTML is trusted product output. ?>
								<?php echo $librefunnels_price_html; ?>
							</span>
						<?php endif; ?>
					</span>
				</span>
			</label>
		<?php endforeach; ?>
	</div>
</section>
