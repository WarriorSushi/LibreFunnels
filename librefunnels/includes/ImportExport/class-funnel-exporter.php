<?php
/**
 * Funnel exporter.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\ImportExport;

use LibreFunnels\Domain\Registered_Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Builds portable JSON-ready funnel packages.
 */
final class Funnel_Exporter {
	/**
	 * Package validator.
	 *
	 * @var Package_Validator
	 */
	private $validator;

	/**
	 * Creates the exporter.
	 *
	 * @param Package_Validator|null $validator Optional package validator.
	 */
	public function __construct( Package_Validator $validator = null ) {
		$this->validator = $validator ? $validator : new Package_Validator();
	}

	/**
	 * Exports a funnel as a normalized package array.
	 *
	 * @param int $funnel_id Funnel ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function export( $funnel_id ) {
		$funnel_id = absint( $funnel_id );
		$funnel    = get_post( $funnel_id );

		if ( ! $funnel || LIBREFUNNELS_FUNNEL_POST_TYPE !== $funnel->post_type ) {
			return new \WP_Error( 'funnel_not_found', __( 'The requested funnel could not be exported because it does not exist.', 'librefunnels' ) );
		}

		return $this->validator->normalize(
			array(
				'format'      => Package_Validator::FORMAT,
				'version'     => Package_Validator::VERSION,
				'generatedBy' => sprintf(
					/* translators: %s: LibreFunnels version. */
					__( 'LibreFunnels %s', 'librefunnels' ),
					LIBREFUNNELS_VERSION
				),
				'funnel'      => $this->export_funnel( $funnel ),
				'steps'       => $this->export_steps( $funnel_id ),
			)
		);
	}

	/**
	 * Exports a funnel as JSON.
	 *
	 * @param int $funnel_id Funnel ID.
	 * @return string|\WP_Error
	 */
	public function export_json( $funnel_id ) {
		$package = $this->export( $funnel_id );

		if ( is_wp_error( $package ) ) {
			return $package;
		}

		$json = wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( ! is_string( $json ) ) {
			return new \WP_Error( 'export_json_failed', __( 'LibreFunnels could not encode the funnel export package.', 'librefunnels' ) );
		}

		return $json;
	}

	/**
	 * Exports funnel-level data.
	 *
	 * @param \WP_Post $funnel Funnel post.
	 * @return array<string,mixed>
	 */
	private function export_funnel( $funnel ) {
		return array(
			'title'       => $funnel->post_title,
			'status'      => $funnel->post_status,
			'startStepId' => absint( get_post_meta( $funnel->ID, LIBREFUNNELS_FUNNEL_START_STEP_META, true ) ),
			'graph'       => Registered_Meta::sanitize_graph( get_post_meta( $funnel->ID, LIBREFUNNELS_FUNNEL_GRAPH_META, true ) ),
		);
	}

	/**
	 * Exports all steps belonging to a funnel.
	 *
	 * @param int $funnel_id Funnel ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function export_steps( $funnel_id ) {
		$steps = get_posts(
			array(
				'post_type'      => LIBREFUNNELS_STEP_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
				'meta_key'       => LIBREFUNNELS_STEP_ORDER_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Export intentionally orders by the step order meta.
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Export intentionally queries steps by parent funnel meta.
					array(
						'key'     => LIBREFUNNELS_STEP_FUNNEL_ID_META,
						'value'   => absint( $funnel_id ),
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		$exported = array();

		foreach ( $steps as $step ) {
			$exported[] = array(
				'originalId' => absint( $step->ID ),
				'title'      => $step->post_title,
				'content'    => $step->post_content,
				'excerpt'    => $step->post_excerpt,
				'status'     => $step->post_status,
				'type'       => get_post_meta( $step->ID, LIBREFUNNELS_STEP_TYPE_META, true ),
				'order'      => get_post_meta( $step->ID, LIBREFUNNELS_STEP_ORDER_META, true ),
				'template'   => get_post_meta( $step->ID, LIBREFUNNELS_STEP_TEMPLATE_META, true ),
			);
		}

		return $exported;
	}
}
