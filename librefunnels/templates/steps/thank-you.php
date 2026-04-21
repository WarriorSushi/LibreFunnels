<?php
/**
 * Default thank-you step template.
 *
 * Available args:
 * - $args['step'] WP_Post
 * - $args['step_id'] int
 * - $args['step_type'] string
 * - $args['title'] string
 * - $args['content'] string
 *
 * @package LibreFunnels
 */

defined( 'ABSPATH' ) || exit;

$title   = isset( $args['title'] ) ? (string) $args['title'] : '';
$content = isset( $args['content'] ) ? (string) $args['content'] : '';
?>
<section class="librefunnels-step librefunnels-step--thank-you">
	<div class="librefunnels-step__inner">
		<?php if ( '' !== $title ) : ?>
			<h1 class="librefunnels-step__title"><?php echo esc_html( $title ); ?></h1>
		<?php endif; ?>

		<?php if ( '' !== $content ) : ?>
			<div class="librefunnels-step__content">
				<?php echo wp_kses_post( $content ); ?>
			</div>
		<?php else : ?>
			<p class="librefunnels-step__empty">
				<?php esc_html_e( 'Thank you. Your order is complete.', 'librefunnels' ); ?>
			</p>
		<?php endif; ?>
	</div>
</section>
