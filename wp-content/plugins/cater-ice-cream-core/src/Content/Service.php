<?php
/**
 * Wires treats (WooCommerce products), events, pairings, structured fields, seeding and patterns.
 *
 * @package CIC
 */

namespace CIC\Content;

defined( 'ABSPATH' ) || exit;

final class Service {

	public function register(): void {
		( new EventType() )->register();
		( new Catalog() )->register();
		( new Pairings() )->register();
		( new Fields() )->register();
		( new EventsOverview() )->register();
		( new Admin\PairingsBox() )->register();
		add_action( 'init', array( $this, 'register_patterns' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'cic seed-catalog', array( Cli::class, 'seed' ) );
			\WP_CLI::add_command( 'cic import-media', array( Cli::class, 'import_media' ) );
		}
	}

	/**
	 * Core-block patterns for optional repeatable editorial content in the main editor area.
	 */
	public function register_patterns(): void {
		register_block_pattern_category( 'cater-ice-cream', array( 'label' => __( 'Cater Ice Cream', 'cater-ice-cream-core' ) ) );
		register_block_pattern(
			'cater-ice-cream/faq-list',
			array(
				'title'       => __( 'FAQ list (accordion)', 'cater-ice-cream-core' ),
				'description' => __( 'Native details blocks; add or remove questions freely.', 'cater-ice-cream-core' ),
				'categories'  => array( 'cater-ice-cream' ),
				'content'     => '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Good to know.', 'cater-ice-cream-core' ) . '</h2><!-- /wp:heading --><!-- wp:details {"className":"cic-accordion__item"} --><details class="wp-block-details cic-accordion__item"><summary>' . esc_html__( 'Question', 'cater-ice-cream-core' ) . '</summary><!-- wp:paragraph --><p>' . esc_html__( 'Answer.', 'cater-ice-cream-core' ) . '</p><!-- /wp:paragraph --></details><!-- /wp:details -->',
			)
		);
		register_block_pattern(
			'cater-ice-cream/story-split',
			array(
				'title'      => __( 'Story with photo', 'cater-ice-cream-core' ),
				'categories' => array( 'cater-ice-cream' ),
				'content'    => '<!-- wp:columns {"verticalAlignment":"center"} --><div class="wp-block-columns are-vertically-aligned-center"><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img alt=""/></figure><!-- /wp:image --></div><!-- /wp:column --><!-- wp:column {"verticalAlignment":"center"} --><div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Story heading', 'cater-ice-cream-core' ) . '</h2><!-- /wp:heading --><!-- wp:paragraph --><p>' . esc_html__( 'Story text.', 'cater-ice-cream-core' ) . '</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->',
			)
		);
	}
}
