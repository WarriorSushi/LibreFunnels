<?php
/**
 * Template loader.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Rendering;

defined( 'ABSPATH' ) || exit;

/**
 * Loads theme-overridable frontend templates.
 */
final class Template_Loader {
	/**
	 * Renders a template with args.
	 *
	 * @param string              $template Template path relative to plugin templates directory.
	 * @param array<string,mixed> $args     Template args.
	 * @return string
	 */
	public function render( $template, array $args = array() ) {
		$template = ltrim( (string) $template, '/\\' );
		$located  = $this->locate( $template );

		if ( '' === $located ) {
			return '';
		}

		ob_start();
		load_template( $located, false, $args );

		return (string) ob_get_clean();
	}

	/**
	 * Locates a theme override or plugin fallback template.
	 *
	 * @param string $template Template path relative to plugin templates directory.
	 * @return string
	 */
	private function locate( $template ) {
		$theme_template = locate_template(
			array(
				'librefunnels/' . $template,
			)
		);

		if ( $theme_template ) {
			return $theme_template;
		}

		$plugin_template = LIBREFUNNELS_PATH . 'templates/' . $template;

		if ( is_readable( $plugin_template ) ) {
			return $plugin_template;
		}

		return '';
	}
}
