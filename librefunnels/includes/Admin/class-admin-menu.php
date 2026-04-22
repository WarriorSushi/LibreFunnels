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
	 * Menu hook suffix.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Registers WordPress admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Adds the top-level menu page.
	 *
	 * @return void
	 */
	public function add_menu() {
		$this->hook_suffix = add_menu_page(
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
	 * Enqueues admin assets on LibreFunnels screens.
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( $this->hook_suffix !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'librefunnels-admin',
			LIBREFUNNELS_URL . 'assets/css/admin.css',
			array(),
			LIBREFUNNELS_VERSION
		);

		$asset_file = LIBREFUNNELS_PATH . 'build/index.asset.php';
		$script     = LIBREFUNNELS_PATH . 'build/index.js';
		$style      = LIBREFUNNELS_PATH . 'build/style-index.css';

		if ( ! is_readable( $asset_file ) || ! is_readable( $script ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'librefunnels-admin-canvas',
			LIBREFUNNELS_URL . 'build/index.js',
			isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
			isset( $asset['version'] ) ? $asset['version'] : LIBREFUNNELS_VERSION,
			true
		);

		if ( is_readable( $style ) ) {
			wp_enqueue_style(
				'librefunnels-admin-canvas',
				LIBREFUNNELS_URL . 'build/style-index.css',
				array(),
				isset( $asset['version'] ) ? $asset['version'] : LIBREFUNNELS_VERSION
			);
		}

		wp_add_inline_script(
			'librefunnels-admin-canvas',
			'window.libreFunnelsAdmin = ' . wp_json_encode( $this->get_app_settings(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';',
			'before'
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

		$funnel_count = $this->get_post_type_count( LIBREFUNNELS_FUNNEL_POST_TYPE );
		$step_count   = $this->get_post_type_count( LIBREFUNNELS_STEP_POST_TYPE );
		?>
		<div id="librefunnels-admin-app">
			<div class="wrap librefunnels-admin">
				<?php $this->render_fallback_workspace( $funnel_count, $step_count ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the PHP fallback workspace.
	 *
	 * @param int $funnel_count Funnel count.
	 * @param int $step_count   Step count.
	 * @return void
	 */
	private function render_fallback_workspace( $funnel_count, $step_count ) {
		?>
		<section class="librefunnels-admin__hero">
			<div class="librefunnels-admin__hero-copy">
				<p class="librefunnels-admin__eyebrow"><?php esc_html_e( 'LibreFunnels for WooCommerce', 'librefunnels' ); ?></p>
				<h1><?php esc_html_e( 'Build funnels with clarity, not clutter.', 'librefunnels' ); ?></h1>
				<p class="librefunnels-admin__lead">
					<?php esc_html_e( 'A free funnel workspace for checkout flows, order bumps, offers, routing, and conversion experiments.', 'librefunnels' ); ?>
				</p>
			</div>

			<div class="librefunnels-admin__hero-panel" aria-label="<?php echo esc_attr__( 'Workspace summary', 'librefunnels' ); ?>">
				<div>
					<span class="librefunnels-admin__metric"><?php echo esc_html( (string) $funnel_count ); ?></span>
					<span class="librefunnels-admin__metric-label"><?php esc_html_e( 'Funnels', 'librefunnels' ); ?></span>
				</div>
				<div>
					<span class="librefunnels-admin__metric"><?php echo esc_html( (string) $step_count ); ?></span>
					<span class="librefunnels-admin__metric-label"><?php esc_html_e( 'Steps', 'librefunnels' ); ?></span>
				</div>
			</div>
		</section>

		<section class="librefunnels-admin__grid">
			<div class="librefunnels-admin__panel librefunnels-admin__panel--wide">
				<div class="librefunnels-admin__panel-header">
					<h2><?php esc_html_e( 'Builder Readiness', 'librefunnels' ); ?></h2>
					<span><?php esc_html_e( 'Foundation active', 'librefunnels' ); ?></span>
				</div>

				<div class="librefunnels-admin__status-grid">
					<?php foreach ( $this->get_status_items() as $item ) : ?>
						<div class="librefunnels-admin__status">
							<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
							<div>
								<strong><?php echo esc_html( $item['label'] ); ?></strong>
								<p><?php echo esc_html( $item['description'] ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="librefunnels-admin__panel">
				<div class="librefunnels-admin__panel-header">
					<h2><?php esc_html_e( 'Next Build Area', 'librefunnels' ); ?></h2>
				</div>
				<ol class="librefunnels-admin__next">
					<li><?php esc_html_e( 'Canvas editing for funnels and steps', 'librefunnels' ); ?></li>
					<li><?php esc_html_e( 'Inline validation for broken routes', 'librefunnels' ); ?></li>
					<li><?php esc_html_e( 'Offer and rule controls for store owners', 'librefunnels' ); ?></li>
				</ol>
			</div>
		</section>
		<?php
	}

	/**
	 * Gets a post type count.
	 *
	 * @param string $post_type Post type.
	 * @return int
	 */
	private function get_post_type_count( $post_type ) {
		$count = wp_count_posts( $post_type );

		if ( ! $count ) {
			return 0;
		}

		return absint( $count->publish ) + absint( $count->draft ) + absint( $count->private );
	}

	/**
	 * Gets readiness items for the workspace.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_status_items() {
		return array(
			array(
				'icon'        => 'dashicons-randomize',
				'label'       => __( 'Routing core', 'librefunnels' ),
				'description' => __( 'Start, next, accept, reject, fallback, and conditional routes.', 'librefunnels' ),
			),
			array(
				'icon'        => 'dashicons-cart',
				'label'       => __( 'Checkout core', 'librefunnels' ),
				'description' => __( 'Assigned products, coupons, fields, and checkout takeover foundation.', 'librefunnels' ),
			),
			array(
				'icon'        => 'dashicons-tag',
				'label'       => __( 'Offers', 'librefunnels' ),
				'description' => __( 'Order bumps and pre-checkout offers with WooCommerce cart handling.', 'librefunnels' ),
			),
			array(
				'icon'        => 'dashicons-filter',
				'label'       => __( 'Rules', 'librefunnels' ),
				'description' => __( 'Cart and customer facts for conditional funnel decisions.', 'librefunnels' ),
			),
		);
	}

	/**
	 * Gets settings for the admin React app.
	 *
	 * @return array<string,mixed>
	 */
	private function get_app_settings() {
		return array(
			'rootId'    => 'librefunnels-admin-app',
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'rest'      => array(
				'funnels' => '/wp/v2/librefunnels-funnels',
				'steps'   => '/wp/v2/librefunnels-steps',
				'canvas'  => '/' . Canvas_REST_Controller::REST_NAMESPACE . '/canvas',
				'pages'   => '/' . Canvas_REST_Controller::REST_NAMESPACE . '/canvas/pages',
			),
			'metaKeys'  => array(
				'graph'        => LIBREFUNNELS_FUNNEL_GRAPH_META,
				'startStepId'  => LIBREFUNNELS_FUNNEL_START_STEP_META,
				'stepFunnelId' => LIBREFUNNELS_STEP_FUNNEL_ID_META,
				'stepType'     => LIBREFUNNELS_STEP_TYPE_META,
				'stepOrder'    => LIBREFUNNELS_STEP_ORDER_META,
				'stepPageId'   => LIBREFUNNELS_STEP_PAGE_ID_META,
			),
			'stepTypes' => array(
				'landing'            => __( 'Landing', 'librefunnels' ),
				'optin'              => __( 'Opt-in', 'librefunnels' ),
				'checkout'           => __( 'Checkout', 'librefunnels' ),
				'pre_checkout_offer' => __( 'Pre-checkout Offer', 'librefunnels' ),
				'order_bump'         => __( 'Order Bump', 'librefunnels' ),
				'upsell'             => __( 'Upsell', 'librefunnels' ),
				'downsell'           => __( 'Downsell', 'librefunnels' ),
				'cross_sell'         => __( 'Cross-sell', 'librefunnels' ),
				'thank_you'          => __( 'Thank You', 'librefunnels' ),
				'custom'             => __( 'Custom', 'librefunnels' ),
			),
			'routes'    => array(
				'next'        => __( 'Continue', 'librefunnels' ),
				'accept'      => __( 'Accept', 'librefunnels' ),
				'reject'      => __( 'Reject', 'librefunnels' ),
				'conditional' => __( 'Conditional', 'librefunnels' ),
				'fallback'    => __( 'Fallback', 'librefunnels' ),
			),
		);
	}
}
