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

if ( ! defined( 'LIBREFUNNELS_PATH' ) ) {
	define( 'LIBREFUNNELS_PATH', dirname( __DIR__ ) . '/' );
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

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error fallback for isolated unit tests.
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		private $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		private $message;

		/**
		 * Creates an error.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}

		/**
		 * Gets error code.
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * Gets error message.
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * is_wp_error fallback for isolated unit tests.
	 *
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
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

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * sanitize_text_field fallback for isolated unit tests.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return trim( wp_strip_all_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * wp_strip_all_tags fallback for isolated unit tests.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function wp_strip_all_tags( $text ) {
		return strip_tags( $text );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * wp_kses_post fallback for isolated unit tests.
	 *
	 * @param string $data Data.
	 * @return string
	 */
	function wp_kses_post( $data ) {
		return (string) $data;
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
}

require_once dirname( __DIR__ ) . '/includes/autoload.php';
