<?php
/**
 * Deletes local LibreFunnels smoke-test artifacts from the Docker WordPress site.
 *
 * This script is intentionally narrow and is meant for WP-CLI use in local
 * development only:
 * wp eval-file wp-content/plugins/librefunnels/tools/cleanup-test-artifacts.php
 *
 * @package LibreFunnels
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Returns whether a title matches one of the provided regular expressions.
 *
 * @param string $title    Post title.
 * @param array  $patterns Regular expression patterns.
 * @return bool
 */
function librefunnels_cleanup_title_matches( $title, array $patterns ) {
	foreach ( $patterns as $pattern ) {
		if ( 1 === preg_match( $pattern, $title ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Deletes posts by title pattern.
 *
 * @param string|string[] $post_type Post type or post types.
 * @param array           $patterns  Regular expression patterns.
 * @return int[] Deleted post IDs.
 */
function librefunnels_cleanup_delete_posts_by_title( $post_type, array $patterns ) {
	$posts       = get_posts(
		array(
			'post_type'        => $post_type,
			'post_status'      => 'any',
			'posts_per_page'   => -1,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => false,
		)
	);
	$deleted_ids = array();

	foreach ( $posts as $post ) {
		if ( librefunnels_cleanup_title_matches( $post->post_title, $patterns ) ) {
			wp_delete_post( $post->ID, true );
			$deleted_ids[] = (int) $post->ID;
		}
	}

	return $deleted_ids;
}

/**
 * Deletes LibreFunnels steps owned by deleted funnels.
 *
 * @param int[] $funnel_ids Deleted funnel IDs.
 * @return int Number of deleted steps.
 */
function librefunnels_cleanup_delete_owned_steps( array $funnel_ids ) {
	if ( empty( $funnel_ids ) ) {
		return 0;
	}

	$step_post_type = defined( 'LIBREFUNNELS_STEP_POST_TYPE' ) ? LIBREFUNNELS_STEP_POST_TYPE : 'librefunnels_step';
	$funnel_meta    = defined( 'LIBREFUNNELS_STEP_FUNNEL_ID_META' ) ? LIBREFUNNELS_STEP_FUNNEL_ID_META : '_librefunnels_funnel_id';
	$steps          = get_posts(
		array(
			'post_type'      => $step_post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => $funnel_meta,
					'value'   => array_map( 'absint', $funnel_ids ),
					'compare' => 'IN',
				),
			),
		)
	);
	$count          = 0;

	foreach ( $steps as $step_id ) {
		wp_delete_post( absint( $step_id ), true );
		++$count;
	}

	return $count;
}

$librefunnels_cleanup_page_patterns = array(
	'/^Public (Checkout|Thank You|Upsell) \d+ Page$/',
	'/^Playwright checkout \d+$/',
	'/^(Imported starter funnel|Guided starter funnel|Starter Checkout Funnel)\s*-\s*(Landing|Checkout|Thank You)$/i',
	'/^(Landing|Pre-checkout Offer|Checkout) page$/i',
);

$librefunnels_cleanup_funnel_patterns = array(
	'/^Public smoke funnel \d+$/',
	'/^New checkout funnel$/',
	'/^(Guided starter funnel|Imported starter funnel|Starter Checkout Funnel)$/',
	'/^Debug selected funnel$/',
);

$librefunnels_cleanup_product_patterns = array(
	'/^LibreFunnels Revenue Smoke \d+$/',
	'/^LibreFunnels Free Smoke \d+$/',
);

$librefunnels_cleanup_funnel_post_type = defined( 'LIBREFUNNELS_FUNNEL_POST_TYPE' ) ? LIBREFUNNELS_FUNNEL_POST_TYPE : 'librefunnels_funnel';
$librefunnels_cleanup_deleted_pages    = librefunnels_cleanup_delete_posts_by_title( 'page', $librefunnels_cleanup_page_patterns );
$librefunnels_cleanup_deleted_funnels  = librefunnels_cleanup_delete_posts_by_title( $librefunnels_cleanup_funnel_post_type, $librefunnels_cleanup_funnel_patterns );
$librefunnels_cleanup_deleted_products = librefunnels_cleanup_delete_posts_by_title( 'product', $librefunnels_cleanup_product_patterns );
$librefunnels_cleanup_deleted_steps    = librefunnels_cleanup_delete_owned_steps( $librefunnels_cleanup_deleted_funnels );

WP_CLI::success(
	sprintf(
		/* translators: 1: page count, 2: funnel count, 3: step count, 4: product count. */
		__( 'Deleted %1$d page(s), %2$d funnel(s), %3$d step(s), and %4$d product(s).', 'librefunnels' ),
		count( $librefunnels_cleanup_deleted_pages ),
		count( $librefunnels_cleanup_deleted_funnels ),
		$librefunnels_cleanup_deleted_steps,
		count( $librefunnels_cleanup_deleted_products )
	)
);
