<?php
/**
 * Admin menu shell.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the LibreFunnels admin entry point.
 */
final class Admin_Menu {
	/**
	 * Registers WordPress admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Adds the top-level menu page.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'LibreFunnels', 'librefunnels' ),
			__( 'LibreFunnels', 'librefunnels' ),
			'manage_woocommerce',
			'librefunnels',
			array( $this, 'render_page' ),
			'dashicons-randomize',
			56
		);
	}

	/**
	 * Renders the initial admin shell.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access LibreFunnels.', 'librefunnels' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LibreFunnels', 'librefunnels' ); ?></h1>
			<p>
				<?php esc_html_e( 'Free WooCommerce funnels, checkout flows, order bumps, upsells, downsells, and smart routing.', 'librefunnels' ); ?>
			</p>

			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e( 'Phase 0 is active. The visual canvas, funnel data model, and checkout routing will be introduced in later foundation slices.', 'librefunnels' ); ?>
				</p>
			</div>
		</div>
		<?php
	}
}
