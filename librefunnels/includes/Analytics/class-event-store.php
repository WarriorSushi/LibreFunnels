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
				'customer_id'  => isset( $event['customer_id'] ) ? absint( $event['customer_id'] ) : get_current_user_id(),
				'session_hash' => $this->get_session_hash(),
				'context'      => wp_json_encode( $context ),
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%d', '%s', '%s', '%s' )
		);

		return false !== $insert;
	}

	/**
	 * Gets a compact analytics summary for the admin dashboard.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array<string,mixed>
	 */
	public function get_dashboard_summary( array $args = array() ) {
		global $wpdb;

		$days      = isset( $args['days'] ) ? max( 1, min( 365, absint( $args['days'] ) ) ) : 30;
		$funnel_id = isset( $args['funnel_id'] ) ? absint( $args['funnel_id'] ) : 0;
		$since     = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$where     = array( 'created_at >= %s' );
		$params    = array( $since );

		if ( $funnel_id > 0 ) {
			$where[]  = 'funnel_id = %d';
			$params[] = $funnel_id;
		}

		$where_sql  = implode( ' AND ', $where );
		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from $wpdb->prefix and static suffix.
		$counts_sql = "SELECT event_type, COUNT(*) AS event_count, COALESCE(SUM(value), 0) AS event_value FROM {$table_name} WHERE {$where_sql} GROUP BY event_type";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin analytics reads from LibreFunnels' local events table.
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is assembled from fixed SQL fragments and prepared placeholders.
			$wpdb->prepare( $counts_sql, $params ),
			ARRAY_A
		);

		$events = array(
			'offer_impression' => array(
				'count' => 0,
				'value' => 0.0,
			),
			'offer_accept'     => array(
				'count' => 0,
				'value' => 0.0,
			),
			'offer_reject'     => array(
				'count' => 0,
				'value' => 0.0,
			),
			'order_revenue'    => array(
				'count' => 0,
				'value' => 0.0,
			),
		);

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$event_type = isset( $row['event_type'] ) ? sanitize_key( (string) $row['event_type'] ) : '';

			if ( ! isset( $events[ $event_type ] ) ) {
				$events[ $event_type ] = array(
					'count' => 0,
					'value' => 0.0,
				);
			}

			$events[ $event_type ] = array(
				'count' => isset( $row['event_count'] ) ? absint( $row['event_count'] ) : 0,
				'value' => isset( $row['event_value'] ) ? (float) $row['event_value'] : 0.0,
			);
		}

		$accept_count     = $events['offer_accept']['count'];
		$impression_count = $events['offer_impression']['count'];
		$breakdowns       = $this->get_dashboard_breakdowns( $table_name, $where_sql, $params );

		return array_merge(
			array(
				'period'          => array(
					'days'  => $days,
					'since' => $since,
				),
				'funnelId'        => $funnel_id,
				'events'          => $events,
				'revenue'         => $events['order_revenue']['value'],
				'currency'        => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
				'orders'          => $events['order_revenue']['count'],
				'offerAcceptRate' => $impression_count > 0 ? round( ( $accept_count / $impression_count ) * 100, 2 ) : 0.0,
			),
			$breakdowns
		);
	}

	/**
	 * Gets bounded detail rows and converts them into dashboard breakdowns.
	 *
	 * @param string       $table_name Events table name.
	 * @param string       $where_sql  Prepared WHERE SQL fragment.
	 * @param array<mixed> $params     Prepared query params.
	 * @return array<string,mixed>
	 */
	private function get_dashboard_breakdowns( $table_name, $where_sql, array $params ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from $wpdb->prefix and static suffix.
		$detail_sql = "SELECT event_type, step_id, value, context FROM {$table_name} WHERE {$where_sql} ORDER BY created_at DESC LIMIT 500";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin analytics reads from LibreFunnels' local events table.
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is assembled from fixed SQL fragments and prepared placeholders.
			$wpdb->prepare( $detail_sql, $params ),
			ARRAY_A
		);

		return $this->build_dashboard_breakdowns( is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Builds local analytics breakdowns from raw event rows.
	 *
	 * @param array<int,array<string,mixed>> $rows Event rows.
	 * @return array<string,mixed>
	 */
	private function build_dashboard_breakdowns( array $rows ) {
		$steps          = array();
		$source_revenue = array(
			'checkout_product' => 0.0,
			'order_bump'       => 0.0,
			'offer'            => 0.0,
		);

		foreach ( $rows as $row ) {
			$event_type = isset( $row['event_type'] ) ? sanitize_key( (string) $row['event_type'] ) : '';
			$step_id    = isset( $row['step_id'] ) ? absint( $row['step_id'] ) : 0;
			$value      = isset( $row['value'] ) ? (float) $row['value'] : 0.0;
			$context    = $this->decode_event_context( isset( $row['context'] ) ? $row['context'] : '' );

			if ( $step_id > 0 ) {
				$this->ensure_step_breakdown( $steps, $step_id );
				$this->add_step_event( $steps[ $step_id ], $event_type );
			}

			if ( 'order_revenue' === $event_type ) {
				$this->add_revenue_lines_to_breakdown( $steps, $source_revenue, $context, $step_id, $value );
			}
		}

		$step_breakdown = array_values( $steps );

		usort(
			$step_breakdown,
			static function ( $first, $second ) {
				if ( $first['revenue'] === $second['revenue'] ) {
					return $second['activity'] <=> $first['activity'];
				}

				return $second['revenue'] <=> $first['revenue'];
			}
		);

		return array(
			'sourceRevenue' => $source_revenue,
			'stepBreakdown' => array_slice( $step_breakdown, 0, 20 ),
		);
	}

	/**
	 * Adds event-level counters to a step breakdown.
	 *
	 * @param array<string,mixed> $step       Step breakdown.
	 * @param string              $event_type Event type.
	 * @return void
	 */
	private function add_step_event( array &$step, $event_type ) {
		++$step['activity'];

		if ( 'offer_impression' === $event_type ) {
			++$step['offerImpressions'];
		} elseif ( 'offer_accept' === $event_type ) {
			++$step['offerAccepts'];
		} elseif ( 'offer_reject' === $event_type ) {
			++$step['offerRejects'];
		} elseif ( 'order_revenue' === $event_type ) {
			++$step['orders'];
		}

		$this->refresh_step_rates( $step );
	}

	/**
	 * Adds line-level revenue attribution to source and step breakdowns.
	 *
	 * @param array<int,array<string,mixed>> $steps          Step breakdowns.
	 * @param array<string,float>            $source_revenue Source revenue totals.
	 * @param array<string,mixed>            $context        Event context.
	 * @param int                            $fallback_step  Fallback step ID.
	 * @param float                          $fallback_value Fallback event value.
	 * @return void
	 */
	private function add_revenue_lines_to_breakdown( array &$steps, array &$source_revenue, array $context, $fallback_step, $fallback_value ) {
		$lines = isset( $context['lines'] ) && is_array( $context['lines'] ) ? $context['lines'] : array();

		if ( empty( $lines ) ) {
			if ( $fallback_step > 0 ) {
				$this->ensure_step_breakdown( $steps, $fallback_step );
				$steps[ $fallback_step ]['revenue']         += $fallback_value;
				$steps[ $fallback_step ]['checkoutRevenue'] += $fallback_value;
				$source_revenue['checkout_product']         += $fallback_value;
			}

			return;
		}

		foreach ( $lines as $line ) {
			if ( is_object( $line ) ) {
				$line = (array) $line;
			}

			if ( ! is_array( $line ) ) {
				continue;
			}

			$source  = isset( $line['source'] ) ? sanitize_key( (string) $line['source'] ) : '';
			$step_id = isset( $line['step_id'] ) ? absint( $line['step_id'] ) : $fallback_step;
			$total   = isset( $line['total'] ) ? (float) $line['total'] : 0.0;

			if ( ! isset( $source_revenue[ $source ] ) || $step_id < 1 ) {
				continue;
			}

			$this->ensure_step_breakdown( $steps, $step_id );
			$steps[ $step_id ]['revenue'] += $total;
			$source_revenue[ $source ]    += $total;

			if ( 'checkout_product' === $source ) {
				$steps[ $step_id ]['checkoutRevenue'] += $total;
			} elseif ( 'order_bump' === $source ) {
				$steps[ $step_id ]['bumpRevenue'] += $total;
			} elseif ( 'offer' === $source ) {
				$steps[ $step_id ]['offerRevenue'] += $total;
			}
		}
	}

	/**
	 * Ensures a step breakdown exists.
	 *
	 * @param array<int,array<string,mixed>> $steps   Step breakdowns.
	 * @param int                            $step_id Step ID.
	 * @return void
	 */
	private function ensure_step_breakdown( array &$steps, $step_id ) {
		if ( isset( $steps[ $step_id ] ) ) {
			return;
		}

		$steps[ $step_id ] = array(
			'stepId'           => absint( $step_id ),
			'revenue'          => 0.0,
			'checkoutRevenue'  => 0.0,
			'bumpRevenue'      => 0.0,
			'offerRevenue'     => 0.0,
			'orders'           => 0,
			'offerImpressions' => 0,
			'offerAccepts'     => 0,
			'offerRejects'     => 0,
			'offerAcceptRate'  => 0.0,
			'activity'         => 0,
		);
	}

	/**
	 * Refreshes derived step rates.
	 *
	 * @param array<string,mixed> $step Step breakdown.
	 * @return void
	 */
	private function refresh_step_rates( array &$step ) {
		$step['offerAcceptRate'] = $step['offerImpressions'] > 0
			? round( ( $step['offerAccepts'] / $step['offerImpressions'] ) * 100, 2 )
			: 0.0;
	}

	/**
	 * Decodes stored event context.
	 *
	 * @param mixed $context Raw context JSON.
	 * @return array<string,mixed>
	 */
	private function decode_event_context( $context ) {
		if ( is_array( $context ) ) {
			return $context;
		}

		if ( ! is_string( $context ) || '' === $context ) {
			return array();
		}

		$decoded = json_decode( $context, true );

		return is_array( $decoded ) ? $decoded : array();
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
