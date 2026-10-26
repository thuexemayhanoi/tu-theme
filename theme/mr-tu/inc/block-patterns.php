<?php
/**
 * Block styles and the "Mr Tu" pattern category.
 *
 * Patterns live in /patterns (auto-registered by core) and use only the blocks allowed by the
 * content markup contract (docs/architecture-contract.md §3).
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the pattern category and block style variations.
 */
function mr_tu_register_block_styles() {
	register_block_pattern_category(
		'mr-tu',
		array(
			'label'       => __( 'Mr Tu', 'mr-tu' ),
			'description' => __( 'Home page sections and inner-page components in the Mr Tu design.', 'mr-tu' ),
		)
	);

	$styles = array(
		'core/group'     => array(
			'name'  => 'callout',
			'label' => __( 'Callout', 'mr-tu' ),
		),
		'core/paragraph' => array(
			'name'  => 'lead',
			'label' => __( 'Lead', 'mr-tu' ),
		),
		'core/list'      => array(
			'name'  => 'checklist',
			'label' => __( 'Checklist', 'mr-tu' ),
		),
		'core/separator' => array(
			'name'  => 'road',
			'label' => __( 'Road line', 'mr-tu' ),
		),
		'core/button'    => array(
			'name'  => 'call',
			'label' => __( 'Call (blue)', 'mr-tu' ),
		),
	);
	foreach ( $styles as $block => $style ) {
		register_block_style( $block, $style );
	}
}
add_action( 'init', 'mr_tu_register_block_styles' );

add_filter( 'should_load_remote_block_patterns', '__return_false' );
