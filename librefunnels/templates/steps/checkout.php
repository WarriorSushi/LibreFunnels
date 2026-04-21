<?php
/**
 * Default checkout step template.
 *
 * Available args:
 * - $args['step'] WP_Post
 * - $args['step_id'] int
 * - $args['title'] string
 *
 * @package LibreFunnels
 */

defined( 'ABSPATH' ) || exit;

$librefunnels_title = isset( $args['title'] ) ? (string) $args['title'] : '';
?>
<section class="librefunnels-step librefunnels-step--checkout">
	<div class="librefunnels-step__inner">
		<?php if ( '' !== $librefunnels_title ) : ?>
			<h1 class="librefunnels-step__title"><?php echo esc_html( $librefunnels_title ); ?></h1>
		<?php endif; ?>

		<div class="librefunnels-step__checkout">
			<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce checkout shortcode returns complete trusted checkout markup. ?>
			<?php echo do_shortcode( '[woocommerce_checkout]' ); ?>
		</div>
	</div>
</section>
