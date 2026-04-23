<?php
/**
 * Bundled funnel templates.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Provides WordPress.org-safe bundled starter templates.
 */
final class Bundled_Template_Library {
	/**
	 * Package validator.
	 *
	 * @var Package_Validator
	 */
	private $validator;

	/**
	 * Creates the template library.
	 *
	 * @param Package_Validator|null $validator Optional package validator.
	 */
	public function __construct( Package_Validator $validator = null ) {
		$this->validator = $validator ? $validator : new Package_Validator();
	}

	/**
	 * Gets template summaries for the admin UI.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_templates() {
		$templates = array();

		foreach ( $this->get_template_packages() as $slug => $template ) {
			$templates[] = array(
				'slug'          => $slug,
				'title'         => $template['title'],
				'description'   => $template['description'],
				'stepSummary'   => $template['stepSummary'],
				'category'      => $template['category'],
				'isRecommended' => ! empty( $template['isRecommended'] ),
				'stepCount'     => count( $template['package']['steps'] ),
			);
		}

		return $templates;
	}

	/**
	 * Gets one normalized template package by slug.
	 *
	 * @param string $slug Template slug.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function get_template_package( $slug ) {
		$slug      = sanitize_key( $slug );
		$templates = $this->get_template_packages();

		if ( ! isset( $templates[ $slug ]['package'] ) ) {
			return new \WP_Error(
				'librefunnels_template_not_found',
				__( 'That LibreFunnels template could not be found.', 'librefunnels' )
			);
		}

		return $this->validator->normalize( $templates[ $slug ]['package'] );
	}

	/**
	 * Gets raw bundled template definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_template_packages() {
		return array(
			'starter_checkout'  => array(
				'title'         => __( 'Starter Checkout Funnel', 'librefunnels' ),
				'description'   => __( 'A calm first funnel with a landing page, checkout, and thank-you step that beginners can finish quickly.', 'librefunnels' ),
				'stepSummary'   => __( 'Landing -> Checkout -> Thank You', 'librefunnels' ),
				'category'      => __( 'Getting started', 'librefunnels' ),
				'isRecommended' => true,
				'package'       => $this->get_starter_checkout_package(),
			),
			'product_launch'    => array(
				'title'         => __( 'Product Launch Funnel', 'librefunnels' ),
				'description'   => __( 'Guide shoppers from a focused landing page into checkout, then present one upgrade before the thank-you page.', 'librefunnels' ),
				'stepSummary'   => __( 'Landing -> Checkout -> Upsell -> Thank You', 'librefunnels' ),
				'category'      => __( 'Sales', 'librefunnels' ),
				'isRecommended' => false,
				'package'       => $this->get_product_launch_package(),
			),
			'lead_offer'        => array(
				'title'         => __( 'Lead Offer Funnel', 'librefunnels' ),
				'description'   => __( 'Capture intent first, then move subscribers into a timed offer and checkout path.', 'librefunnels' ),
				'stepSummary'   => __( 'Opt-in -> Offer -> Checkout -> Thank You', 'librefunnels' ),
				'category'      => __( 'Lead generation', 'librefunnels' ),
				'isRecommended' => false,
				'package'       => $this->get_lead_offer_package(),
			),
			'downsell_recovery' => array(
				'title'         => __( 'Downsell Recovery Funnel', 'librefunnels' ),
				'description'   => __( 'Recover value after an upsell decline with a lower-friction fallback before the final confirmation.', 'librefunnels' ),
				'stepSummary'   => __( 'Checkout -> Upsell -> Downsell -> Thank You', 'librefunnels' ),
				'category'      => __( 'Revenue recovery', 'librefunnels' ),
				'isRecommended' => false,
				'package'       => $this->get_downsell_recovery_package(),
			),
		);
	}

	/**
	 * Gets the starter checkout template package.
	 *
	 * @return array<string,mixed>
	 */
	private function get_starter_checkout_package() {
		return array(
			'format'      => Package_Validator::FORMAT,
			'version'     => Package_Validator::VERSION,
			'generatedBy' => 'LibreFunnels bundled template',
			'funnel'      => array(
				'title'       => __( 'Starter Checkout Funnel', 'librefunnels' ),
				'status'      => 'draft',
				'startStepId' => 101,
				'graph'       => $this->graph(
					array(
						101 => array(
							'type' => 'landing',
							'x'    => 120,
							'y'    => 180,
						),
						102 => array(
							'type' => 'checkout',
							'x'    => 440,
							'y'    => 180,
						),
						103 => array(
							'type' => 'thank_you',
							'x'    => 760,
							'y'    => 180,
						),
					),
					array(
						array(
							'source' => 101,
							'target' => 102,
							'route'  => 'next',
						),
						array(
							'source' => 102,
							'target' => 103,
							'route'  => 'next',
						),
					)
				),
			),
			'steps'       => array(
				$this->content_step( 101, __( 'Landing', 'librefunnels' ), 'landing', 1, $this->heading_and_paragraph( __( 'Tell shoppers what they should do next.', 'librefunnels' ), __( 'Use this landing step to explain the offer, answer objections, and guide visitors into the checkout page.', 'librefunnels' ) ) ),
				$this->checkout_step( 102, __( 'Checkout', 'librefunnels' ), 2 ),
				$this->content_step( 103, __( 'Thank You', 'librefunnels' ), 'thank_you', 3, $this->paragraph( __( 'Your order is confirmed. Use this step to set expectations, share delivery details, and point shoppers to the next useful action.', 'librefunnels' ) ) ),
			),
		);
	}

	/**
	 * Gets the product launch template package.
	 *
	 * @return array<string,mixed>
	 */
	private function get_product_launch_package() {
		return array(
			'format'      => Package_Validator::FORMAT,
			'version'     => Package_Validator::VERSION,
			'generatedBy' => 'LibreFunnels bundled template',
			'funnel'      => array(
				'title'       => __( 'Product Launch Funnel', 'librefunnels' ),
				'status'      => 'draft',
				'startStepId' => 201,
				'graph'       => $this->graph(
					array(
						201 => array(
							'type' => 'landing',
							'x'    => 120,
							'y'    => 180,
						),
						202 => array(
							'type' => 'checkout',
							'x'    => 440,
							'y'    => 180,
						),
						203 => array(
							'type' => 'upsell',
							'x'    => 760,
							'y'    => 120,
						),
						204 => array(
							'type' => 'thank_you',
							'x'    => 1080,
							'y'    => 180,
						),
					),
					array(
						array(
							'source' => 201,
							'target' => 202,
							'route'  => 'next',
						),
						array(
							'source' => 202,
							'target' => 203,
							'route'  => 'next',
						),
						array(
							'source' => 203,
							'target' => 204,
							'route'  => 'accept',
						),
						array(
							'source' => 203,
							'target' => 204,
							'route'  => 'reject',
						),
					)
				),
			),
			'steps'       => array(
				$this->content_step( 201, __( 'Launch Landing', 'librefunnels' ), 'landing', 1, $this->heading_and_paragraph( __( 'Introduce the main product before the checkout asks for payment.', 'librefunnels' ), __( 'Use this step to position the outcome, call out proof, and keep the next action obvious.', 'librefunnels' ) ) ),
				$this->checkout_step( 202, __( 'Launch Checkout', 'librefunnels' ), 2 ),
				$this->offer_step( 203, __( 'Launch Upsell', 'librefunnels' ), 'upsell', 3, __( 'Add the implementation boost', 'librefunnels' ), __( 'Present one focused upgrade after the main checkout choice.', 'librefunnels' ) ),
				$this->content_step( 204, __( 'Launch Thank You', 'librefunnels' ), 'thank_you', 4, $this->paragraph( __( 'Use this page to confirm the purchase and remind customers what happens next.', 'librefunnels' ) ) ),
			),
		);
	}

	/**
	 * Gets the lead offer template package.
	 *
	 * @return array<string,mixed>
	 */
	private function get_lead_offer_package() {
		return array(
			'format'      => Package_Validator::FORMAT,
			'version'     => Package_Validator::VERSION,
			'generatedBy' => 'LibreFunnels bundled template',
			'funnel'      => array(
				'title'       => __( 'Lead Offer Funnel', 'librefunnels' ),
				'status'      => 'draft',
				'startStepId' => 301,
				'graph'       => $this->graph(
					array(
						301 => array(
							'type' => 'optin',
							'x'    => 120,
							'y'    => 180,
						),
						302 => array(
							'type' => 'pre_checkout_offer',
							'x'    => 440,
							'y'    => 180,
						),
						303 => array(
							'type' => 'checkout',
							'x'    => 760,
							'y'    => 180,
						),
						304 => array(
							'type' => 'thank_you',
							'x'    => 1080,
							'y'    => 180,
						),
					),
					array(
						array(
							'source' => 301,
							'target' => 302,
							'route'  => 'next',
						),
						array(
							'source' => 302,
							'target' => 303,
							'route'  => 'accept',
						),
						array(
							'source' => 302,
							'target' => 303,
							'route'  => 'reject',
						),
						array(
							'source' => 303,
							'target' => 304,
							'route'  => 'next',
						),
					)
				),
			),
			'steps'       => array(
				$this->content_step( 301, __( 'Lead Opt-in', 'librefunnels' ), 'optin', 1, $this->heading_and_paragraph( __( 'Capture intent before checkout.', 'librefunnels' ), __( 'Use this step to promise the lead magnet or starter resource, then move subscribers into the next offer.', 'librefunnels' ) ) ),
				$this->offer_step( 302, __( 'Lead Offer', 'librefunnels' ), 'pre_checkout_offer', 2, __( 'Continue with the paid offer', 'librefunnels' ), __( 'Place the first paid upgrade between the opt-in and the full checkout.', 'librefunnels' ) ),
				$this->checkout_step( 303, __( 'Lead Checkout', 'librefunnels' ), 3 ),
				$this->content_step( 304, __( 'Lead Thank You', 'librefunnels' ), 'thank_you', 4, $this->paragraph( __( 'Confirm the order and explain how the lead magnet or paid product will be delivered.', 'librefunnels' ) ) ),
			),
		);
	}

	/**
	 * Gets the downsell recovery template package.
	 *
	 * @return array<string,mixed>
	 */
	private function get_downsell_recovery_package() {
		return array(
			'format'      => Package_Validator::FORMAT,
			'version'     => Package_Validator::VERSION,
			'generatedBy' => 'LibreFunnels bundled template',
			'funnel'      => array(
				'title'       => __( 'Downsell Recovery Funnel', 'librefunnels' ),
				'status'      => 'draft',
				'startStepId' => 401,
				'graph'       => $this->graph(
					array(
						401 => array(
							'type' => 'checkout',
							'x'    => 120,
							'y'    => 180,
						),
						402 => array(
							'type' => 'upsell',
							'x'    => 440,
							'y'    => 120,
						),
						403 => array(
							'type' => 'downsell',
							'x'    => 760,
							'y'    => 240,
						),
						404 => array(
							'type' => 'thank_you',
							'x'    => 1080,
							'y'    => 180,
						),
					),
					array(
						array(
							'source' => 401,
							'target' => 402,
							'route'  => 'next',
						),
						array(
							'source' => 402,
							'target' => 404,
							'route'  => 'accept',
						),
						array(
							'source' => 402,
							'target' => 403,
							'route'  => 'reject',
						),
						array(
							'source' => 403,
							'target' => 404,
							'route'  => 'accept',
						),
						array(
							'source' => 403,
							'target' => 404,
							'route'  => 'reject',
						),
					)
				),
			),
			'steps'       => array(
				$this->checkout_step( 401, __( 'Recovery Checkout', 'librefunnels' ), 1 ),
				$this->offer_step( 402, __( 'Primary Upsell', 'librefunnels' ), 'upsell', 2, __( 'Upgrade your order', 'librefunnels' ), __( 'Offer the higher-value add-on right after checkout intent.', 'librefunnels' ) ),
				$this->offer_step( 403, __( 'Downsell Recovery', 'librefunnels' ), 'downsell', 3, __( 'Keep the lighter upgrade', 'librefunnels' ), __( 'Provide a lower-friction fallback if the first upsell is declined.', 'librefunnels' ) ),
				$this->content_step( 404, __( 'Recovery Thank You', 'librefunnels' ), 'thank_you', 4, $this->paragraph( __( 'Wrap up the order and reassure shoppers about fulfillment and support.', 'librefunnels' ) ) ),
			),
		);
	}

	/**
	 * Builds a graph array for a package.
	 *
	 * @param array<int,array<string,mixed>> $nodes Node definitions keyed by original step ID.
	 * @param array<int,array<string,mixed>> $edges Edge definitions.
	 * @return array<string,mixed>
	 */
	private function graph( array $nodes, array $edges ) {
		$graph_nodes = array();
		$graph_edges = array();

		foreach ( $nodes as $step_id => $node ) {
			$graph_nodes[] = array(
				'id'       => 'node-' . absint( $step_id ),
				'stepId'   => absint( $step_id ),
				'type'     => sanitize_key( $node['type'] ),
				'position' => array(
					'x' => isset( $node['x'] ) ? (float) $node['x'] : 120,
					'y' => isset( $node['y'] ) ? (float) $node['y'] : 160,
				),
			);
		}

		foreach ( $edges as $index => $edge ) {
			$graph_edges[] = array(
				'id'     => sprintf( 'edge-%1$d-%2$d-%3$d', $index + 1, absint( $edge['source'] ), absint( $edge['target'] ) ),
				'source' => 'node-' . absint( $edge['source'] ),
				'target' => 'node-' . absint( $edge['target'] ),
				'route'  => sanitize_key( $edge['route'] ),
				'rule'   => array(),
			);
		}

		return array(
			'version' => 1,
			'nodes'   => $graph_nodes,
			'edges'   => $graph_edges,
		);
	}

	/**
	 * Builds a content step definition.
	 *
	 * @param int    $original_id Original step ID.
	 * @param string $title       Step title.
	 * @param string $type        Step type.
	 * @param int    $order       Step order.
	 * @param string $content     Step content.
	 * @return array<string,mixed>
	 */
	private function content_step( $original_id, $title, $type, $order, $content ) {
		return array(
			'originalId'       => absint( $original_id ),
			'title'            => $title,
			'content'          => $content,
			'excerpt'          => '',
			'status'           => 'draft',
			'type'             => $type,
			'order'            => absint( $order ),
			'template'         => 'bundled',
			'pageId'           => 0,
			'checkoutProducts' => array(),
			'checkoutCoupons'  => array(),
			'checkoutFields'   => array(),
			'orderBumps'       => array(),
			'offer'            => array(),
		);
	}

	/**
	 * Builds a checkout step definition.
	 *
	 * @param int    $original_id Original step ID.
	 * @param string $title       Step title.
	 * @param int    $order       Step order.
	 * @return array<string,mixed>
	 */
	private function checkout_step( $original_id, $title, $order ) {
		return $this->content_step( $original_id, $title, 'checkout', $order, '' );
	}

	/**
	 * Builds an offer step definition.
	 *
	 * @param int    $original_id Original step ID.
	 * @param string $title       Step title.
	 * @param string $type        Step type.
	 * @param int    $order       Step order.
	 * @param string $offer_title Offer title.
	 * @param string $description Offer description.
	 * @return array<string,mixed>
	 */
	private function offer_step( $original_id, $title, $type, $order, $offer_title, $description ) {
		$step          = $this->content_step( $original_id, $title, $type, $order, '' );
		$step['offer'] = array(
			'id'              => sanitize_key( sprintf( 'offer-%d', absint( $original_id ) ) ),
			'product_id'      => 0,
			'variation_id'    => 0,
			'quantity'        => 1,
			'variation'       => array(),
			'title'           => $offer_title,
			'description'     => $description,
			'discount_type'   => 'none',
			'discount_amount' => 0,
			'enabled'         => true,
		);

		return $step;
	}

	/**
	 * Wraps a translated sentence in a paragraph tag.
	 *
	 * @param string $paragraph Paragraph content.
	 * @return string
	 */
	private function paragraph( $paragraph ) {
		return sprintf( '<p>%s</p>', $paragraph );
	}

	/**
	 * Wraps translated content in a heading and paragraph pair.
	 *
	 * @param string $heading   Heading content.
	 * @param string $paragraph Paragraph content.
	 * @return string
	 */
	private function heading_and_paragraph( $heading, $paragraph ) {
		return sprintf(
			'<h2>%1$s</h2><p>%2$s</p>',
			$heading,
			$paragraph
		);
	}
}
