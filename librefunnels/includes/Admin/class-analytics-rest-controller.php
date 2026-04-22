<?php
/**
 * REST endpoints for LibreFunnels analytics.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Admin;

use LibreFunnels\Analytics\Event_Store;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Provides capability-guarded analytics reads for the admin dashboard.
 */
final class Analytics_REST_Controller {
	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'librefunnels/v1';

	/**
	 * Event store.
	 *
	 * @var Event_Store
	 */
	private $event_store;

	/**
	 * Creates the controller.
	 *
	 * @param Event_Store|null $event_store Optional event store.
	 */
	public function __construct( Event_Store $event_store = null ) {
		$this->event_store = $event_store ? $event_store : new Event_Store();
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/analytics/summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_summary' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'funnel_id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'days'      => array(
						'type'              => 'integer',
						'default'           => 30,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Checks REST permissions.
	 *
	 * @return true|WP_Error
	 */
	public function can_manage() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}

		return new WP_Error(
			'librefunnels_rest_forbidden',
			__( 'You do not have permission to view LibreFunnels analytics.', 'librefunnels' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Gets an analytics summary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_summary( WP_REST_Request $request ) {
		return rest_ensure_response(
			$this->event_store->get_dashboard_summary(
				array(
					'funnel_id' => $request->get_param( 'funnel_id' ),
					'days'      => $request->get_param( 'days' ),
				)
			)
		);
	}
}
