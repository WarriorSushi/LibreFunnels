<?php
/**
 * Minimal project autoloader.
 *
 * Composer can replace this during packaged builds, but keeping a local
 * autoloader makes the plugin usable in early development without a vendor dir.
 *
 * @package LibreFunnels
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'LibreFunnels\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $prefix ) );
		$file           = LIBREFUNNELS_PATH . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
