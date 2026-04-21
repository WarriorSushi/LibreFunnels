<?php
/**
 * Test bootstrap placeholder.
 *
 * @package LibreFunnels\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'LIBREFUNNELS_FUNNEL_POST_TYPE' ) ) {
	define( 'LIBREFUNNELS_FUNNEL_POST_TYPE', 'librefunnels_funnel' );
}

if ( ! defined( 'LIBREFUNNELS_STEP_POST_TYPE' ) ) {
	define( 'LIBREFUNNELS_STEP_POST_TYPE', 'librefunnels_step' );
}

if ( ! defined( 'LIBREFUNNELS_FUNNEL_GRAPH_META' ) ) {
	define( 'LIBREFUNNELS_FUNNEL_GRAPH_META', '_librefunnels_graph' );
}

if ( ! defined( 'LIBREFUNNELS_FUNNEL_START_STEP_META' ) ) {
	define( 'LIBREFUNNELS_FUNNEL_START_STEP_META', '_librefunnels_start_step_id' );
}

if ( ! defined( 'LIBREFUNNELS_STEP_FUNNEL_ID_META' ) ) {
	define( 'LIBREFUNNELS_STEP_FUNNEL_ID_META', '_librefunnels_funnel_id' );
}

if ( ! defined( 'LIBREFUNNELS_STEP_TYPE_META' ) ) {
	define( 'LIBREFUNNELS_STEP_TYPE_META', '_librefunnels_step_type' );
}

if ( ! defined( 'LIBREFUNNELS_STEP_ORDER_META' ) ) {
	define( 'LIBREFUNNELS_STEP_ORDER_META', '_librefunnels_step_order' );
}

if ( ! defined( 'LIBREFUNNELS_STEP_TEMPLATE_META' ) ) {
	define( 'LIBREFUNNELS_STEP_TEMPLATE_META', '_librefunnels_template_slug' );
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation fallback for isolated unit tests.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * sanitize_key fallback for isolated unit tests.
	 *
	 * @param mixed $key Raw key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * absint fallback for isolated unit tests.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( file_exists( $autoload ) ) {
	require_once $autoload;
} else {
	require_once dirname( __DIR__ ) . '/includes/autoload.php';
}
