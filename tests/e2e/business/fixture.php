<?php
/**
 * E2E fixture for the business module (local dev env only).
 *
 *   wp eval-file tests/e2e/business/fixture.php setup      → backs up the business options, sets a known
 *                                                             state, creates a test page, prints {"url","page_id"}
 *   wp eval-file tests/e2e/business/fixture.php state [no-reference] → only sets the known state (no page)
 *   wp eval-file tests/e2e/business/fixture.php teardown   → deletes the test pages and restores the options
 *
 * Known state: seed data (phone verified; address, hours and prices unverified), reference prices ON
 * (OFF with the "no-reference" argument),
 * Quick Contact defaults (call item, collapse on scroll).
 *
 * @package MrTu\Multilang
 */

// phpcs:ignoreFile -- test fixture.

if ( ! defined( 'ABSPATH' ) || ! class_exists( '\MrTu\Multilang\Business' ) ) {
	fwrite( STDERR, "Run with wp eval-file while mr-tu-multilang-seo is active.\n" );
	exit( 1 );
}

$mrtu_e2e_mode   = isset( $args[0] ) ? (string) $args[0] : 'setup';
$mrtu_e2e_keys   = array( 'mrtu_business', 'mrtu_pricing', 'mrtu_business_history', 'mrtu_ml_settings', 'mrtu_quick_contact' );
$mrtu_e2e_backup = 'mrtu_e2e_business_backup';

if ( 'teardown' === $mrtu_e2e_mode ) {
	foreach ( get_posts(
		array(
			'post_type'   => 'page',
			'post_status' => 'any',
			'meta_key'    => '_mrtu_e2e',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	) as $mrtu_e2e_id ) {
		wp_delete_post( $mrtu_e2e_id, true );
	}
	$mrtu_e2e_saved = get_option( $mrtu_e2e_backup );
	if ( is_array( $mrtu_e2e_saved ) ) {
		foreach ( $mrtu_e2e_saved as $mrtu_e2e_key => $mrtu_e2e_value ) {
			if ( '__mrtu_absent__' === $mrtu_e2e_value ) {
				delete_option( $mrtu_e2e_key );
			} else {
				update_option( $mrtu_e2e_key, $mrtu_e2e_value, 'mrtu_business_history' !== $mrtu_e2e_key );
			}
		}
		delete_option( $mrtu_e2e_backup );
	}
	echo wp_json_encode( array( 'teardown' => true ) ) . PHP_EOL;
	return;
}

if ( false === get_option( $mrtu_e2e_backup ) ) {
	$mrtu_e2e_saved = array();
	foreach ( $mrtu_e2e_keys as $mrtu_e2e_key ) {
		$mrtu_e2e_saved[ $mrtu_e2e_key ] = get_option( $mrtu_e2e_key, '__mrtu_absent__' );
	}
	add_option( $mrtu_e2e_backup, $mrtu_e2e_saved, '', false );
}

$mrtu_e2e_seed = \MrTu\Multilang\Business::load_seed( dirname( __DIR__, 3 ) . '/config/mr-tu-business.json' );
update_option( 'mrtu_business', $mrtu_e2e_seed['business'], true );
update_option( 'mrtu_pricing', $mrtu_e2e_seed['pricing'], true );
$mrtu_e2e_settings = get_option( 'mrtu_ml_settings', array() );
$mrtu_e2e_settings = is_array( $mrtu_e2e_settings ) ? $mrtu_e2e_settings : array();

$mrtu_e2e_settings['show_reference_prices'] = ! ( isset( $args[1] ) && 'no-reference' === $args[1] );
update_option( 'mrtu_ml_settings', $mrtu_e2e_settings, true );
delete_option( 'mrtu_quick_contact' );

if ( 'state' === $mrtu_e2e_mode ) {
	echo wp_json_encode( array( 'state' => true ) ) . PHP_EOL;
	return;
}

$mrtu_e2e_filler = '';
for ( $mrtu_e2e_i = 1; $mrtu_e2e_i <= 24; $mrtu_e2e_i++ ) {
	$mrtu_e2e_filler .= "<!-- wp:paragraph -->\n<p>Filler paragraph {$mrtu_e2e_i} so the contact card starts below the fold. Hanoi Old Quarter streets are busy; ride slowly and wear a helmet.</p>\n<!-- /wp:paragraph -->\n\n";
}
$mrtu_e2e_page = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Mr Tu business e2e',
		'post_name'    => 'mrtu-business-e2e',
		'post_content' => "<!-- wp:mrtu/pricing-table {\"period\":\"day\"} /-->\n\n<!-- wp:mrtu/fleet /-->\n\n" . $mrtu_e2e_filler . "<!-- wp:mrtu/contact-card /-->\n\n<!-- wp:mrtu/cta {\"variant\":\"call\"} /-->",
		'meta_input'   => array( '_mrtu_e2e' => '1' ),
	)
);
echo wp_json_encode(
	array(
		'url'     => get_permalink( $mrtu_e2e_page ),
		'page_id' => $mrtu_e2e_page,
	)
) . PHP_EOL;
