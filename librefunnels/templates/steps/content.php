<?php
/**
 * Generic content step template.
 *
 * @var string $title
 * @var string $content
 * @var string $step_type
 *
 * @package LibreFunnels
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="librefunnels-step librefunnels-step--content librefunnels-step--<?php echo esc_attr( sanitize_html_class( $step_type ) ); ?>">
	<header class="librefunnels-step__header">
		<h1 class="librefunnels-step__title"><?php echo esc_html( $title ); ?></h1>
	</header>

	<div class="librefunnels-step__content">
		<?php echo wp_kses_post( $content ); ?>
	</div>
</section>
