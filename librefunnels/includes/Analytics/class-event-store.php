<?php
/**
 * First-party analytics event storage.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Stores funnel events in a local WordPress database table.
 */
final class Event_Store {
	/**
	 * Events table schema version.
	 */
	const SCHEMA_VERSION = '1';

	/**
	 * Installs the events table when needed.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( self::SCHEMA_VERSION === get_option( 'librefunnels_events_schema_version' ) ) {
			return;
		}

		self::install();
	}

	/**
	 * Creates or updates the events table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table_name} (
				event_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_type varchar(64) NOT NULL,
				funnel_id bigint(20) unsigned NOT NULL DEFAULT 0,
				step_id bigint(20) unsigned NOT NULL DEFAULT 0,
				route varchar(32) NOT NULL DEFAULT '',
				object_type varchar(32) NOT NULL DEFAULT '',
				object_id varchar(100) NOT NULL DEFAULT '',
				value decimal(26,8) NOT NULL DEFAULT 0,
				currency varchar(10) NOT NULL DEFAULT '',
				customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
				session_hash varchar(64) NOT NULL DEFAULT '',
				context longtext NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (event_id),
				KEY event_type (event_type),
				KEY funnel_step (funnel_id, step_id),
				KEY created_at (created_at)
			) {$charset_collate};"
		);

		update_option( 'librefunnels_events_schema_version', self::SCHEMA_VERSION, false );
	}

	/**
	 * Records an analytics event.
	 *
	 * @param array<string,mixed> $event Event data.
	 * @return bool
	 */
	public function record( array $event ) {
		global $wpdb;

		$event_type = isset( $event['event_type'] ) ? sanitize_key( (string) $event['event_type'] ) : '';

		if ( '' === $event_type ) {
			return false;
		}

		$context = isset( $event['context'] ) && is_array( $event['context'] ) ? $event['context'] : array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- LibreFunnels analytics events are stored in a purpose-built local table.
		$insert = $wpdb->insert(
			self::get_table_name(),
			array(
				'event_type'   => $event_type,
				'funnel_id'    => isset( $event['funnel_id'] ) ? absint( $event['funnel_id'] ) : 0,
				'step_id'      => isset( $event['step_id'] ) ? absint( $event['step_id'] ) : 0,
				'route'        => isset( $event['route'] ) ? sanitize_key( (string) $event['route'] ) : '',
				'object_type'  => isset( $event['object_type'] ) ? sanitize_key( (string) $event['object_type'] ) : '',
				'object_id'    => isset( $event['object_id'] ) ? sanitize_text_field( (string) $event['object_id'] ) : '',
				'value'        => isset( $event['value'] ) ? (float) $event['value'] : 0.0,
				'currency'     => isset( $event['currency'] ) ? sanitize_text_field( (string) $event['currency'] ) : '',
				'customer_id'  => get_current_user_id(),
				'session_hash' => $this->get_session_hash(),
				'context'      => wp_json_encode( $context ),
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%d', '%s', '%s', '%s' )
		);

		return false !== $insert;
	}

	/**
	 * Gets the events table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'librefunnels_events';
	}

	/**
	 * Gets a privacy-preserving session hash.
	 *
	 * @return string
	 */
	private function get_session_hash() {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}

		$woocommerce = WC();

		if ( ! $woocommerce || empty( $woocommerce->session ) || ! method_exists( $woocommerce->session, 'get_customer_id' ) ) {
			return '';
		}

		return hash( 'sha256', (string) $woocommerce->session->get_customer_id() );
	}
}
