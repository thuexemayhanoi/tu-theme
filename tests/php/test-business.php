<?php
/**
 * Integration tests — business data module (NAP, pricing, vehicles, blocks, Quick Contact).
 *
 * Run:   wp eval-file tests/php/test-business.php
 * HTTP:  the last group fetches a test page from MRTU_TEST_URL (default: this site's home_url());
 *        it is reported as SKIP when the dev server is not reachable.
 *
 * Self-contained (no shared bootstrap). Every option it touches is backed up and restored.
 * Prints "PASS: …" / "FAIL: …" lines, then "RESULT: <passed>/<total>"; exits 1 on failure.
 *
 * @package MrTu\Multilang
 */

// phpcs:ignoreFile -- test script.

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run with: wp eval-file tests/php/test-business.php\n" );
	exit( 1 );
}

$GLOBALS['mrtu_business_test'] = array(
	'pass' => 0,
	'fail' => 0,
);

function mrtu_business_test_ok( $condition, $label ) {
	if ( $condition ) {
		$GLOBALS['mrtu_business_test']['pass']++;
		echo 'PASS: ' . $label . PHP_EOL;
	} else {
		$GLOBALS['mrtu_business_test']['fail']++;
		echo 'FAIL: ' . $label . PHP_EOL;
	}
}

function mrtu_business_test_same( $expected, $actual, $label ) {
	$ok = $expected === $actual;
	mrtu_business_test_ok( $ok, $label . ( $ok ? '' : ' — expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) ) );
}

function mrtu_business_test_contains( $needle, $haystack, $label ) {
	mrtu_business_test_ok( false !== strpos( (string) $haystack, $needle ), $label );
}

function mrtu_business_test_lacks( $needle, $haystack, $label ) {
	mrtu_business_test_ok( false === strpos( (string) $haystack, $needle ), $label );
}

function mrtu_business_test_error_key( $result, $key ) {
	if ( ! is_wp_error( $result ) ) {
		return false;
	}
	$data = $result->get_error_data();
	return is_array( $data ) && isset( $data['errors'][ $key ] );
}

/** Puts the store back into the seeded state (prices unverified, phone verified, no history). */
function mrtu_business_test_reset() {
	$seed = \MrTu\Multilang\Business::load_seed( $GLOBALS['mrtu_business_test_seed'] );
	update_option( 'mrtu_business', $seed['business'], true );
	update_option( 'mrtu_pricing', $seed['pricing'], true );
	update_option( 'mrtu_business_history', array(), false );
	mrtu_business_test_settings( false );
	delete_option( 'mrtu_quick_contact' );
	remove_all_filters( 'mrtu_assistant_active' );
	remove_all_filters( 'mrtu_quick_contact_items' );
	\MrTu\Multilang\Business::flush_cache();
}

function mrtu_business_test_settings( $show_reference ) {
	$settings = get_option( 'mrtu_ml_settings', array() );
	$settings = is_array( $settings ) ? $settings : array();

	$settings['show_reference_prices'] = (bool) $show_reference;
	update_option( 'mrtu_ml_settings', $settings, true );
}

/** Applies $callback( &$business ) and saves (fails the test on error). */
function mrtu_business_test_edit_business( $callback ) {
	$business = \MrTu\Multilang\Business::get_business();
	$callback( $business );
	$result = \MrTu\Multilang\Business::save_business( $business, array( 'snapshot' => false ) );
	if ( is_wp_error( $result ) ) {
		mrtu_business_test_ok( false, 'fixture save_business: ' . wp_json_encode( $result->get_error_data()['errors'] ) );
	}
}

function mrtu_business_test_edit_pricing( $callback ) {
	$pricing = \MrTu\Multilang\Business::get_pricing();
	$callback( $pricing );
	$result = \MrTu\Multilang\Business::save_pricing( $pricing, array( 'snapshot' => false ) );
	if ( is_wp_error( $result ) ) {
		mrtu_business_test_ok( false, 'fixture save_pricing: ' . wp_json_encode( $result->get_error_data()['errors'] ) );
	}
}

function mrtu_business_test_vehicle_index( $pricing, $id ) {
	foreach ( $pricing['vehicles'] as $i => $vehicle ) {
		if ( $id === $vehicle['id'] ) {
			return $i;
		}
	}
	return -1;
}

if ( ! class_exists( '\MrTu\Multilang\Business' ) || ! function_exists( 'mrtu_business' ) ) {
	echo 'FAIL: plugin mr-tu-multilang-seo (business module) is not active' . PHP_EOL . 'RESULT: 0/1' . PHP_EOL;
	exit( 1 );
}

$GLOBALS['mrtu_business_test_seed'] = dirname( __DIR__, 2 ) . '/config/mr-tu-business.json';

$mrtu_bt_keys   = array( 'mrtu_business', 'mrtu_pricing', 'mrtu_business_history', 'mrtu_ml_settings', 'mrtu_quick_contact' );
$mrtu_bt_backup = array();
foreach ( $mrtu_bt_keys as $mrtu_bt_key ) {
	$mrtu_bt_backup[ $mrtu_bt_key ] = get_option( $mrtu_bt_key, '__mrtu_absent__' );
}
$mrtu_bt_page = 0;

$B  = '\MrTu\Multilang\Business';
$P  = '\MrTu\Multilang\Pricing';
$BL = '\MrTu\Multilang\Blocks';
$QC = '\MrTu\Multilang\Quick_Contact';

$nb   = "\u{00A0}";
$nn   = "\u{202F}";
$dash = "\u{2013}";
$dong = "\u{20AB}";

try {
	/* ------------------------------------------------------------ 1. Seeding */
	delete_option( 'mrtu_business' );
	delete_option( 'mrtu_pricing' );
	delete_option( 'mrtu_business_history' );
	$B::flush_cache();
	$seeded = $B::maybe_seed();
	mrtu_business_test_ok( $seeded['business'] && $seeded['pricing'], 'seed: both options seeded when empty' );
	$raw_b = get_option( 'mrtu_business' );
	$raw_p = get_option( 'mrtu_pricing' );
	mrtu_business_test_same( 'Mr Tu', $raw_b['brand']['value'], 'seed: brand from JSON' );
	mrtu_business_test_same( '+84816659199', $raw_b['phone']['e164'], 'seed: phone E.164 from JSON' );
	mrtu_business_test_same( false, $raw_b['address']['verified'], 'seed: address stays unverified' );
	mrtu_business_test_same( 8, count( $raw_p['vehicles'] ), 'seed: 8 vehicles' );
	mrtu_business_test_same( array( 900000, 1200000 ), $raw_p['vehicles'][0]['price']['month'], 'seed: price range [min,max]' );
	mrtu_business_test_same( null, $raw_p['vehicles'][5]['price']['week'], 'seed: null period kept as null' );
	mrtu_business_test_same( array(), get_option( 'mrtu_business_history' ), 'seed: empty history created' );
	global $wpdb;
	$autoload = $wpdb->get_col( "SELECT CONCAT(option_name, '=', autoload) FROM {$wpdb->options} WHERE option_name IN ('mrtu_business','mrtu_pricing','mrtu_business_history') ORDER BY option_name" );
	mrtu_business_test_ok( in_array( 'mrtu_business=on', $autoload, true ) || in_array( 'mrtu_business=yes', $autoload, true ), 'seed: mrtu_business autoloaded' );
	mrtu_business_test_ok( in_array( 'mrtu_business_history=off', $autoload, true ) || in_array( 'mrtu_business_history=no', $autoload, true ), 'seed: mrtu_business_history not autoloaded' );

	$raw_b['brand']['value'] = 'Kept';
	update_option( 'mrtu_business', $raw_b, true );
	$again = $B::maybe_seed();
	mrtu_business_test_ok( ! $again['business'] && ! $again['pricing'], 'seed: skipped when options are not empty' );
	mrtu_business_test_same( 'Kept', get_option( 'mrtu_business' )['brand']['value'], 'seed: existing data never overwritten' );

	$none = $B::load_seed( '/nonexistent/mr-tu-business.json' );
	mrtu_business_test_same( $B::business_defaults(), $none['business'], 'seed: missing file → empty business defaults' );
	mrtu_business_test_same( array(), $none['pricing']['vehicles'], 'seed: missing file → no vehicles' );
	mrtu_business_test_same( 8, count( $B::load_seed( $GLOBALS['mrtu_business_test_seed'] )['pricing']['vehicles'] ), 'seed: explicit seed file loads' );

	add_filter( 'mrtu_business_seed_file', '__return_empty_string' );
	delete_option( 'mrtu_business' );
	delete_option( 'mrtu_pricing' );
	$B::flush_cache();
	$none = $B::maybe_seed();
	mrtu_business_test_ok( ! $none['business'] && ! $none['pricing'] && false === get_option( 'mrtu_business' ), 'seed: no seed file → nothing written' );
	mrtu_business_test_ok( '' === mrtu_business( 'brand' ) && null === mrtu_business( 'phone' ) && array() === mrtu_vehicles(), 'seed: no seed file → empty public defaults' );
	remove_all_filters( 'mrtu_business_seed_file' );
	$later = $B::maybe_seed();
	mrtu_business_test_ok( $later['business'] && $later['pricing'], 'seed: a seed file that appears later is still picked up' );

	/* ------------------------------------------------------------ 2. Validation */
	mrtu_business_test_reset();
	$base = $B::get_business();

	$bad                  = $base;
	$bad['phone']['e164'] = '+8412';
	$result               = $B::save_business( $bad );
	mrtu_business_test_ok( mrtu_business_test_error_key( $result, 'phone.e164' ), 'validation: bad E.164 phone rejected' );
	mrtu_business_test_same( '+84816659199', $B::get_business()['phone']['e164'], 'validation: nothing saved after an error' );
	mrtu_business_test_same( '+8412', $result->get_error_data()['data']['phone']['e164'], 'validation: submitted value kept for redisplay' );

	$bad                  = $base;
	$bad['phone']['e164'] = '+15551234567';
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_business( $bad ), 'phone.e164' ), 'validation: non-Vietnamese E.164 rejected' );

	$local                  = $base;
	$local['phone']['e164'] = '0816 659 199';
	$errors                 = array();
	mrtu_business_test_same( '+84816659199', $B::normalize_business( $local, $errors )['phone']['e164'], 'validation: local number converted to E.164' );

	$derived                     = $base;
	$derived['phone']['e164']    = '';
	$derived['phone']['display'] = '0912 345 678';
	mrtu_business_test_same( '+84912345678', $B::normalize_business( $derived, $errors )['phone']['e164'], 'validation: E.164 derived from display number' );

	$bad                     = $base;
	$bad['phone']['display'] = str_repeat( '9', 21 );
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_business( $bad ), 'phone.display' ), 'validation: display phone > 20 chars rejected' );

	$bad                   = $base;
	$bad['hours']['opens'] = '25:00';
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_business( $bad ), 'hours.opens' ), 'validation: invalid time rejected' );

	$bad                    = $base;
	$bad['hours']['opens']  = '18:00';
	$bad['hours']['closes'] = '17:00';
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_business( $bad ), 'hours.closes' ), 'validation: closing before opening rejected' );

	$fine                   = $base;
	$fine['hours']['opens'] = '8:00';
	mrtu_business_test_same( '08:00', $B::normalize_business( $fine, $errors )['hours']['opens'], 'validation: H:MM normalised to HH:MM' );

	$bad                  = $base;
	$bad['hours']['days'] = array( 'Mo', 'Xx' );
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_business( $bad ), 'hours.days' ), 'validation: unknown weekday rejected' );

	$fine                  = $base;
	$fine['hours']['days'] = array( 'su', 'Mo', 'Mo' );
	mrtu_business_test_same( array( 'Mo', 'Su' ), $B::normalize_business( $fine, $errors )['hours']['days'], 'validation: days de-duplicated and ordered' );

	$bad             = $base;
	$bad['maps_url'] = array(
		'value'    => 'http://maps.google.com/?cid=1',
		'verified' => false,
	);
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_business( $bad ), 'maps_url.value' ), 'validation: non-https map link rejected' );

	$bad                     = $base;
	$bad['email']['value']   = 'not-an-email';
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_business( $bad ), 'email.value' ), 'validation: invalid email rejected' );

	$bad                            = $base;
	$bad['service_model']['value']  = 'franchise';
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_business( $bad ), 'service_model.value' ), 'validation: service model enum enforced' );

	$bad                                   = $base;
	$bad['channels']['whatsapp']['value']  = '12345';
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_business( $bad ), 'channels.whatsapp.value' ), 'validation: invalid WhatsApp number rejected' );

	$bad                             = $base;
	$bad['display_name']['value']    = '';
	$bad['display_name']['verified'] = true;
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_business( $bad ), 'display_name.value' ), 'validation: verified flag on an empty value rejected' );

	$pricing = $B::get_pricing();

	$bad                                  = $pricing;
	$bad['vehicles'][0]['price']['day']   = array( 200000, 100000 );
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_pricing( $bad ), 'vehicles.0.price.day' ), 'validation: min > max rejected' );
	mrtu_business_test_same( array( 150000, 150000 ), $B::get_pricing()['vehicles'][0]['price']['day'], 'validation: pricing unchanged after error' );

	$bad                                = $pricing;
	$bad['vehicles'][0]['price']['week'] = array(
		'min' => '-5',
		'max' => '',
	);
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_pricing( $bad ), 'vehicles.0.price.week' ), 'validation: negative amount rejected' );

	$bad                       = $pricing;
	$bad['vehicles'][0]['deposit'] = array( 'abc', '' );
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_pricing( $bad ), 'vehicles.0.deposit' ), 'validation: non-numeric amount rejected' );

	$bad                       = $pricing;
	$bad['vehicles'][1]['id']  = $bad['vehicles'][0]['id'];
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_pricing( $bad ), 'vehicles.1.id' ), 'validation: duplicate vehicle id rejected' );

	$bad                            = $pricing;
	$bad['vehicles'][0]['category'] = 'truck';
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_pricing( $bad ), 'vehicles.0.category' ), 'validation: category enum enforced' );

	$bad                          = $pricing;
	$bad['vehicles'][0]['status'] = 'sold';
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_pricing( $bad ), 'vehicles.0.status' ), 'validation: status enum enforced' );

	$bad                = $pricing;
	$bad['reviewed_at'] = '2026-02-30';
	mrtu_business_test_ok( mrtu_business_test_error_key( $B::save_pricing( $bad ), 'reviewed_at' ), 'validation: impossible review date rejected' );

	$errors = array();
	$norm   = $P::normalize(
		array(
			'vehicles' => array(
				array(
					'id'    => '',
					'name'  => 'Xe máy điện Mới',
					'price' => array(
						'day' => array(
							'min' => '150000',
							'max' => '',
						),
					),
				),
			),
		),
		$errors
	);
	mrtu_business_test_same( array( 150000, 150000 ), $norm['vehicles'][0]['price']['day'], 'validation: single price stored as [min,min]' );
	mrtu_business_test_same( 'xe-may-dien-moi', $norm['vehicles'][0]['id'], 'validation: id derived from name (sanitize_key)' );
	mrtu_business_test_same( array(), $errors, 'validation: valid vehicle has no errors' );

	/* ------------------------------------------------------------ 3. Snapshot + restore + hooks */
	mrtu_business_test_reset();
	$GLOBALS['mrtu_bt_events'] = array();
	add_action(
		'mrtu_business_updated',
		function ( $old, $new ) {
			$GLOBALS['mrtu_bt_events'][] = array( 'business', $old['brand']['value'], $new['brand']['value'] );
		},
		10,
		2
	);
	add_action(
		'mrtu_pricing_updated',
		function ( $old, $new ) {
			$GLOBALS['mrtu_bt_events'][] = array( 'pricing', count( $old['vehicles'] ), count( $new['vehicles'] ) );
		},
		10,
		2
	);
	$changed                   = $B::get_business();
	$changed['brand']['value'] = 'Mr Tu Test';
	mrtu_business_test_same( 'saved', $B::save_business( $changed ), 'snapshot: save_business returns saved' );
	$history = $B::history();
	mrtu_business_test_same( 1, count( $history ), 'snapshot: one snapshot taken before save' );
	mrtu_business_test_same( 'business', $history[0]['type'], 'snapshot: type business' );
	mrtu_business_test_same( 'Mr Tu', $history[0]['data']['brand']['value'], 'snapshot: holds the OLD value' );
	mrtu_business_test_ok( isset( $history[0]['time'], $history[0]['user_id'], $history[0]['id'] ), 'snapshot: has time, user_id, id' );
	mrtu_business_test_same( array( 'business', 'Mr Tu', 'Mr Tu Test' ), isset( $GLOBALS['mrtu_bt_events'][0] ) ? $GLOBALS['mrtu_bt_events'][0] : null, 'hook: mrtu_business_updated( old, new )' );
	mrtu_business_test_ok( $B::get_business()['updated_at'] > 0, 'save: updated_at stamped' );
	mrtu_business_test_same( 'unchanged', $B::save_business( $B::get_business() ), 'save: identical data → unchanged' );
	mrtu_business_test_same( 1, count( $B::history() ), 'save: no snapshot for an unchanged save' );

	$p = $B::get_pricing();
	array_pop( $p['vehicles'] );
	mrtu_business_test_same( 'saved', $B::save_pricing( $p ), 'snapshot: save_pricing returns saved' );
	mrtu_business_test_same( 'pricing', $B::history()[0]['type'], 'snapshot: type pricing' );
	mrtu_business_test_same( array( 'pricing', 8, 7 ), end( $GLOBALS['mrtu_bt_events'] ), 'hook: mrtu_pricing_updated( old, new )' );

	mrtu_business_test_same( 'saved', $B::restore( $history[0]['id'] ), 'restore: business snapshot restored' );
	mrtu_business_test_same( 'Mr Tu', $B::get_business()['brand']['value'], 'restore: old brand is back' );
	$after = $B::history();
	mrtu_business_test_same( 'restore', $after[0]['type'], 'restore: current data snapshotted first (type restore)' );
	mrtu_business_test_ok( isset( $after[0]['data']['business'], $after[0]['data']['pricing'] ), 'restore: restore snapshot holds both options' );
	mrtu_business_test_same( 'saved', $B::restore( $after[1]['id'] ), 'restore: pricing snapshot restored' );
	mrtu_business_test_same( 8, count( $B::get_pricing()['vehicles'] ), 'restore: vehicle list back to 8' );
	mrtu_business_test_ok( is_wp_error( $B::restore( 'doesnotexist' ) ), 'restore: unknown snapshot id → error' );

	for ( $i = 0; $i < 25; $i++ ) {
		$changed['brand']['value'] = 'Brand ' . $i;
		$B::save_business( $changed );
	}
	mrtu_business_test_same( 20, count( $B::history() ), 'snapshot: history capped at 20' );

	/* ------------------------------------------------------------ 4. Export / import */
	mrtu_business_test_reset();
	$json = $B::export_json();
	$doc  = json_decode( $json, true );
	mrtu_business_test_ok( is_array( $doc ) && isset( $doc['business'], $doc['pricing'], $doc['$schema_version'] ), 'export: JSON with business + pricing + schema version' );
	mrtu_business_test_ok( ! isset( $doc['business']['updated_at'] ), 'export: bookkeeping fields stripped' );
	mrtu_business_test_contains( 'Phúc Tân', $json, 'export: Unicode kept unescaped' );

	mrtu_business_test_edit_business(
		function ( &$b ) {
			$b['brand']['value'] = 'Changed';
		}
	);
	mrtu_business_test_edit_pricing(
		function ( &$p ) {
			$p['vehicles'] = array_slice( $p['vehicles'], 0, 2 );
		}
	);
	mrtu_business_test_same( 'saved', $B::import_json( $json ), 'import: export file imports' );
	$doc2 = json_decode( $B::export_json(), true );
	mrtu_business_test_same( $doc['business'], $doc2['business'], 'import: business round trip identical' );
	mrtu_business_test_same( $doc['pricing'], $doc2['pricing'], 'import: pricing round trip identical' );
	mrtu_business_test_same( 'import', $B::history()[0]['type'], 'import: snapshot taken first (type import)' );
	mrtu_business_test_same( 'Changed', $B::history()[0]['data']['business']['brand']['value'], 'import: snapshot holds the data before import' );

	$result = $B::import_json( '{"business": {bad json' );
	mrtu_business_test_ok( is_wp_error( $result ) && 'mrtu_import_json' === $result->get_error_code(), 'import: invalid JSON rejected' );
	$result = $B::import_json( '{"foo": 1}' );
	mrtu_business_test_ok( is_wp_error( $result ) && 'mrtu_import_schema' === $result->get_error_code(), 'import: JSON without business/pricing rejected' );
	$result = $B::import_json( '{"pricing": {"currency": "VND"}}' );
	mrtu_business_test_ok( is_wp_error( $result ), 'import: pricing without vehicle list rejected' );
	$broken                             = $doc;
	$broken['business']['phone']['e164'] = '+84 12';
	$broken['business']['brand']['value'] = 'Should not land';
	$result                             = $B::import_json( wp_json_encode( $broken ) );
	mrtu_business_test_ok( is_wp_error( $result ) && 'mrtu_payload_invalid' === $result->get_error_code(), 'import: invalid phone rejects the whole file' );
	mrtu_business_test_same( 'Mr Tu', $B::get_business()['brand']['value'], 'import: nothing written after a rejected import' );
	$result = $B::import_json( '{"business":' . str_repeat( ' ', 210000 ) . '{}}' );
	mrtu_business_test_ok( is_wp_error( $result ) && 'mrtu_import_size' === $result->get_error_code(), 'import: > 200 KB rejected' );
	mrtu_business_test_same( 'saved', $B::import_json( "\xEF\xBB\xBF" . file_get_contents( $GLOBALS['mrtu_business_test_seed'] ) ), 'import: repo config file (with BOM) importable' );

	/* ------------------------------------------------------------ 5. Public-only filters */
	mrtu_business_test_reset();
	mrtu_business_test_same( null, mrtu_business( 'address' ), 'public: unverified address hidden' );
	mrtu_business_test_same( null, mrtu_business( 'hours' ), 'public: unverified hours hidden' );
	mrtu_business_test_same( '', mrtu_business( 'display_name' ), 'public: unverified display name hidden' );
	mrtu_business_test_same( 'Mr Tu', mrtu_business( 'brand' ), 'public: verified brand shown' );
	mrtu_business_test_same( '0816 659 199', mrtu_phone(), 'public: verified phone (display)' );
	mrtu_business_test_same( 'tel:+84816659199', mrtu_phone( 'tel' ), 'public: verified phone (tel)' );
	mrtu_business_test_same( '+84816659199', mrtu_phone( 'e164' ), 'public: verified phone (e164)' );
	mrtu_business_test_same( '17 P. Phúc Tân', mrtu_business( 'address.street', false ), 'public: raw access with $public_only=false' );
	$all = mrtu_business();
	mrtu_business_test_ok( is_array( $all ) && null === $all['address'] && null === $all['hours'], 'public: full public array hides unverified parts' );
	$channels = mrtu_contact_channels();
	mrtu_business_test_ok( isset( $channels['phone'] ), 'channels: verified phone listed' );
	mrtu_business_test_ok( ! isset( $channels['whatsapp'] ) && ! isset( $channels['zalo'] ), 'channels: disabled/unverified chat apps hidden' );
	mrtu_business_test_ok( ! isset( $channels['directions'] ), 'channels: directions hidden without verified map + address' );

	mrtu_business_test_edit_business(
		function ( &$b ) {
			$b['address']['verified']             = true;
			$b['hours']['verified']               = true;
			$b['channels']['whatsapp']['enabled'] = true;
		}
	);
	$address = mrtu_business( 'address' );
	mrtu_business_test_same( '17 P. Phúc Tân', is_array( $address ) ? $address['street'] : null, 'public: verified address shown' );
	mrtu_business_test_contains( 'Hồng Hà', is_array( $address ) ? $address['formatted'] : '', 'public: formatted address' );
	mrtu_business_test_same( 'Mo-Su 08:00-17:00', mrtu_business( 'hours.spec' ), 'public: verified hours (schema spec)' );
	mrtu_business_test_same( '08:00', mrtu_business( 'hours.opens' ), 'public: dot-path access' );
	$all_channels = mrtu_contact_channels( false );
	mrtu_business_test_ok( ! isset( mrtu_contact_channels()['whatsapp'] ) && '' === $all_channels['whatsapp']['url'] && '' !== $all_channels['whatsapp']['reason'], 'channels: enabled but unverified WhatsApp hidden (with reason)' );
	mrtu_business_test_edit_business(
		function ( &$b ) {
			$b['channels']['whatsapp']['verified'] = true;
			$b['maps_url']                         = array(
				'value'    => 'https://maps.app.goo.gl/mrtu-test',
				'verified' => true,
				'note'     => '',
			);
		}
	);
	$channels = mrtu_contact_channels();
	mrtu_business_test_same( 'https://wa.me/84816659199', isset( $channels['whatsapp'] ) ? $channels['whatsapp']['url'] : null, 'channels: enabled + verified WhatsApp shown' );
	mrtu_business_test_same( 'https://maps.app.goo.gl/mrtu-test', isset( $channels['directions'] ) ? $channels['directions']['url'] : null, 'channels: directions with verified map + address' );
	mrtu_business_test_edit_business(
		function ( &$b ) {
			$b['address']['verified'] = false;
		}
	);
	mrtu_business_test_ok( ! isset( mrtu_contact_channels()['directions'] ), 'channels: directions hidden again when the address is unverified' );
	mrtu_business_test_edit_business(
		function ( &$b ) {
			$b['phone']['verified'] = false;
		}
	);
	mrtu_business_test_same( '', mrtu_phone(), 'public: unverified phone → empty string' );
	mrtu_business_test_ok( ! isset( mrtu_contact_channels()['phone'] ), 'channels: unverified phone not listed' );
	mrtu_business_test_ok( '' !== mrtu_service_area_label( 'en' ), 'public: service area label available' );

	$marked = $B::normalize_business( $B::mark_all_verified( $B::load_seed( $GLOBALS['mrtu_business_test_seed'] )['business'] ), $errors );
	mrtu_business_test_ok( $marked['display_name']['verified'] && $marked['address']['verified'] && $marked['hours']['verified'], 'mark all verified: filled-in fields verified' );
	mrtu_business_test_ok( ! $marked['email']['verified'] && ! $marked['maps_url']['verified'] && ! $marked['channels']['zalo']['verified'], 'mark all verified: empty/disabled fields left alone' );

	/* ------------------------------------------------------------ 6. Price display states */
	mrtu_business_test_reset();
	$display = mrtu_price_display( 'honda-wave', 'day', 'vi' );
	mrtu_business_test_same( 'on_request', $display['state'], 'price: unverified + reference off → on_request' );
	mrtu_business_test_ok( ! preg_match( '/\d/', $display['text'] ), 'price: on_request text has no amount' );
	mrtu_business_test_same( 'On request', mrtu_price_display( 'honda-wave', 'deposit', 'vi' )['text'], 'price: deposit on request label' );

	mrtu_business_test_settings( true );
	$display = mrtu_price_display( 'honda-wave', 'day', 'vi' );
	mrtu_business_test_same( 'reference', $display['state'], 'price: unverified + reference on → reference' );
	mrtu_business_test_same( '150.000' . $nb . $dong, $display['text'], 'price: reference shows the amount' );
	mrtu_business_test_ok( '' !== $display['note'], 'price: reference carries a note' );
	mrtu_business_test_same( 'reference', mrtu_price_display( 'honda-wave', 'deposit', 'vi' )['state'], 'price: deposit follows the same rule (reference)' );

	mrtu_business_test_edit_pricing(
		function ( &$p ) {
			$p['verified']                        = true;
			$p['vehicles'][0]['verified']         = true;
			$p['vehicles'][0]['price']['week']    = null;
			$p['vehicles'][5]['verified']         = true;
		}
	);
	mrtu_business_test_settings( false );
	$display = mrtu_price_display( 'honda-wave', 'day', 'vi' );
	mrtu_business_test_same( 'verified', $display['state'], 'price: verified list + verified vehicle → verified' );
	mrtu_business_test_same( '150.000' . $nb . $dong, $display['text'], 'price: verified text' );
	mrtu_business_test_same( '900.000' . $dash . '1.200.000' . $nb . $dong, mrtu_price_display( 'honda-wave', 'month', 'vi' )['text'], 'price: verified range with en dash' );
	mrtu_business_test_same( '2.000.000' . $dash . '3.000.000' . $nb . $dong, mrtu_price_display( 'honda-wave', 'deposit', 'vi' )['text'], 'price: verified deposit range' );
	mrtu_business_test_same( 'on_request', mrtu_price_display( 'honda-wave', 'week', 'vi' )['state'], 'price: null period → on_request even when verified' );
	mrtu_business_test_same( 'on_request', mrtu_price_display( 'honda-vision', 'day', 'vi' )['state'], 'price: verified list but unverified vehicle → on_request' );
	mrtu_business_test_same( 'on_request', mrtu_price_display( 'scooter-50cc', 'day', 'vi' )['state'], 'price: hidden vehicle never public' );
	mrtu_business_test_same( 'on_request', mrtu_price_display( 'no-such-model', 'day', 'vi' )['state'], 'price: unknown vehicle → on_request' );
	mrtu_business_test_same( 'on_request', mrtu_price_display( 'yamaha-sirius', 'deposit', 'vi' )['state'], 'price: null deposit → on_request' );
	$forged = array(
		'id'       => 'honda-vision',
		'verified' => true,
		'price'    => array( 'day' => array( 1, 1 ) ),
	);
	mrtu_business_test_same( 'on_request', mrtu_price_display( $forged, 'day', 'vi' )['state'], 'price: vehicle arrays are resolved from storage (forged verified flag ignored)' );
	mrtu_business_test_same( '₫150,000', mrtu_price_display( 'honda-wave', 'day', 'en' )['text'], 'price: formatted in the requested language' );
	$meta = mrtu_pricing_meta();
	mrtu_business_test_same( array( 'verified', 'reviewed_at', 'show_reference', 'currency', 'delivery_fee' ), array_keys( $meta ), 'meta: mrtu_pricing_meta() keys' );
	mrtu_business_test_ok( true === $meta['verified'] && false === $meta['show_reference'] && 'VND' === $meta['currency'], 'meta: values' );

	mrtu_business_test_contains( 'mrtu-price mrtu-price--verified', mrtu_price_html( 'honda-wave', 'day' ), 'price html: verified markup' );
	mrtu_business_test_contains( 'href="tel:+84816659199"', mrtu_price_html( 'honda-vision', 'day' ), 'price html: on request links to the phone' );
	mrtu_business_test_contains( '<a class="mrtu-price mrtu-price--on-request"', mrtu_price_html( 'honda-vision', 'day' ), 'price html: on request is a link when the phone is public' );
	mrtu_business_test_settings( true );
	$html = mrtu_price_html( 'honda-vision', 'day' );
	mrtu_business_test_ok( false !== strpos( $html, 'mrtu-price--reference' ) && false !== strpos( $html, 'mrtu-price__flag' ) && false !== strpos( $html, 'screen-reader-text' ), 'price html: reference markup with flag + screen-reader note' );
	mrtu_business_test_settings( false );
	mrtu_business_test_edit_business(
		function ( &$b ) {
			$b['phone']['verified'] = false;
		}
	);
	mrtu_business_test_contains( '<span class="mrtu-price mrtu-price--on-request"', mrtu_price_html( 'honda-vision', 'day' ), 'price html: on request is a span without a public phone' );

	/* ------------------------------------------------------------ 7. mrtu_format_vnd */
	mrtu_business_test_same( '150.000' . $nb . $dong, mrtu_format_vnd( 150000, 'vi' ), 'vnd: vi' );
	mrtu_business_test_same( $dong . '150,000', mrtu_format_vnd( 150000, 'en' ), 'vnd: en' );
	mrtu_business_test_same( '150.000' . $nb . $dong, mrtu_format_vnd( 150000, 'de' ), 'vnd: de' );
	mrtu_business_test_same( '150' . $nn . '000' . $nb . $dong, mrtu_format_vnd( 150000, 'fr' ), 'vnd: fr (U+202F separator, nbsp before ₫)' );
	mrtu_business_test_same( '150.000' . $nb . $dong, mrtu_format_vnd( 150000, 'es' ), 'vnd: es' );
	mrtu_business_test_same( '900.000' . $dash . '1.200.000' . $nb . $dong, mrtu_format_vnd( array( 900000, 1200000 ), 'vi' ), 'vnd: vi range' );
	mrtu_business_test_same( $dong . '900,000' . $dash . '1,200,000', mrtu_format_vnd( array( 900000, 1200000 ), 'en' ), 'vnd: en range' );
	mrtu_business_test_same( '900' . $nn . '000' . $dash . '1' . $nn . '200' . $nn . '000' . $nb . $dong, mrtu_format_vnd( array( 900000, 1200000 ), 'fr' ), 'vnd: fr range' );
	mrtu_business_test_same( '200.000' . $nb . $dong, mrtu_format_vnd( array( 200000, 200000 ), 'es' ), 'vnd: equal range → single amount' );
	mrtu_business_test_same( '1.500.000' . $nb . $dong, mrtu_format_vnd( '1500000', 'de' ), 'vnd: numeric string' );

	/* ------------------------------------------------------------ 8. Vehicles */
	mrtu_business_test_reset();
	$ids = wp_list_pluck( mrtu_vehicles(), 'id' );
	mrtu_business_test_same( array( 'honda-wave', 'honda-vision', 'honda-air-blade', 'honda-click', 'yamaha-mio' ), $ids, 'vehicles: hidden models excluded, order kept' );
	mrtu_business_test_same( 8, count( mrtu_vehicles( array( 'include_hidden' => true ) ) ), 'vehicles: include_hidden returns all' );
	mrtu_business_test_same( array( 'honda-wave' ), wp_list_pluck( mrtu_vehicles( array( 'category' => 'manual' ) ), 'id' ), 'vehicles: category filter (hidden Sirius excluded)' );
	mrtu_business_test_edit_pricing(
		function ( &$p ) {
			$p['vehicles'][1]['desc_i18n']['en'] = 'Light automatic scooter.';
			$p['vehicles'][1]['note']            = 'Admin only';
		}
	);
	$vision_en = mrtu_vehicles( array( 'lang' => 'en' ) )[1];
	$vision_de = mrtu_vehicles( array( 'lang' => 'de' ) )[1];
	mrtu_business_test_same( 'Light automatic scooter.', $vision_en['desc'], 'vehicles: description in the requested language' );
	mrtu_business_test_same( '', $vision_de['desc'], 'vehicles: no fallback to another language' );
	mrtu_business_test_same( '', $vision_en['note'], 'vehicles: admin note stripped publicly' );
	mrtu_business_test_same( 'Admin only', mrtu_vehicles( array( 'context' => 'edit' ) )[1]['note'], 'vehicles: admin note in edit context' );
	mrtu_business_test_ok( array_key_exists( 'price', $vision_en ) && array_key_exists( 'deposit', $vision_en ) && array_key_exists( 'image_id', $vision_en ), 'vehicles: normalised record shape' );

	/* ------------------------------------------------------------ 9. Blocks */
	mrtu_business_test_reset();
	$html = do_blocks( '<!-- wp:mrtu/pricing-table {"period":"week"} /-->' );
	mrtu_business_test_contains( 'wp-block-mrtu-pricing-table', $html, 'block pricing: wrapper class' );
	mrtu_business_test_contains( 'mrtu-pricing', $html, 'block pricing: .mrtu-pricing' );
	mrtu_business_test_contains( 'data-mrtu-pricing', $html, 'block pricing: data-mrtu-pricing hook' );
	mrtu_business_test_contains( 'role="tablist"', $html, 'block pricing: tablist role' );
	mrtu_business_test_ok( 3 === substr_count( $html, 'role="tab"' ) && 3 === substr_count( $html, 'role="tabpanel"' ), 'block pricing: 3 tabs + 3 panels' );
	mrtu_business_test_ok( (bool) preg_match( '/id="mrtu-pt-\d+-week" aria-controls="mrtu-pp-\d+-week" aria-selected="true" tabindex="0"/', $html ), 'block pricing: default tab from attribute (week) selected' );
	mrtu_business_test_contains( '<caption class="screen-reader-text">', $html, 'block pricing: table caption' );
	mrtu_business_test_contains( 'mrtu-price--on-request', $html, 'block pricing: price on request state' );
	mrtu_business_test_contains( 'mrtu-badge--pending', $html, 'block pricing: pending badge' );
	foreach ( array( '150.000', '150,000', '700.000', '1.200.000', '2.000.000', '4.000.000' ) as $amount ) {
		mrtu_business_test_lacks( $amount, $html, 'block pricing: no unverified price text (' . $amount . ')' );
	}
	mrtu_business_test_lacks( 'Yamaha Sirius', $html, 'block pricing: hidden models not rendered' );
	preg_match( '/mrtu-pt-(\d+)-day/', $html, $first );
	preg_match( '/mrtu-pt-(\d+)-day/', do_blocks( '<!-- wp:mrtu/pricing-table /-->' ), $second );
	mrtu_business_test_ok( isset( $first[1], $second[1] ) && $first[1] !== $second[1], 'block pricing: unique ids per instance' );

	mrtu_business_test_settings( true );
	$html = do_blocks( '<!-- wp:mrtu/pricing-table /-->' );
	mrtu_business_test_ok( false !== strpos( $html, 'mrtu-price--reference' ) && false !== strpos( $html, 'mrtu-pricing__note--reference' ) && false !== strpos( $html, 'mrtu-badge--reference' ), 'block pricing: reference state + legend' );
	mrtu_business_test_settings( false );
	$html = do_blocks( '<!-- wp:mrtu/pricing-table {"showDeposit":false} /-->' );
	mrtu_business_test_lacks( 'data-label="Deposit"', $html, 'block pricing: deposit column optional' );

	$html = do_blocks( '<!-- wp:mrtu/fleet /-->' );
	mrtu_business_test_contains( 'data-mrtu-fleet', $html, 'block fleet: data-mrtu-fleet hook' );
	mrtu_business_test_contains( 'role="group"', $html, 'block fleet: filter group' );
	mrtu_business_test_contains( 'aria-pressed="true" aria-controls=', $html, 'block fleet: "All" chip pressed' );
	mrtu_business_test_ok( false !== strpos( $html, 'data-filter="manual"' ) && false !== strpos( $html, 'data-filter="scooter"' ), 'block fleet: one chip per present category' );
	mrtu_business_test_lacks( 'data-filter="50cc"', $html, 'block fleet: no chip for hidden-only category' );
	mrtu_business_test_same( 5, substr_count( $html, '<li class="mrtu-vehicle"' ), 'block fleet: 5 public vehicles' );
	mrtu_business_test_contains( 'mrtu-plate', $html, 'block fleet: plate fallback without photo' );
	mrtu_business_test_contains( 'aria-live="polite"', $html, 'block fleet: live region for the count' );
	mrtu_business_test_lacks( '150.000', $html, 'block fleet: no unverified prices' );
	$html = do_blocks( '<!-- wp:mrtu/fleet {"category":"manual","showPrices":false} /-->' );
	mrtu_business_test_ok( 1 === substr_count( $html, '<li class="mrtu-vehicle"' ) && false === strpos( $html, 'mrtu-fleet__filters' ) && false === strpos( $html, 'mrtu-vehicle__prices' ), 'block fleet: category attribute, no chips, prices optional' );

	$html = do_blocks( '<!-- wp:mrtu/contact-card /-->' );
	mrtu_business_test_contains( 'data-mrtu-qc-avoid', $html, 'block contact: quick-contact avoid zone' );
	mrtu_business_test_contains( 'href="tel:+84816659199"', $html, 'block contact: verified phone' );
	mrtu_business_test_contains( 'mrtu-contact-card__item--area', $html, 'block contact: area while address unverified' );
	mrtu_business_test_lacks( 'Phúc Tân', $html, 'block contact: unverified address hidden' );
	mrtu_business_test_lacks( 'mrtu-contact-card__item--hours', $html, 'block contact: unverified hours hidden' );
	mrtu_business_test_contains( 'mrtu-contact-card__note', $html, 'block contact: call-ahead note while the service model is unverified' );
	mrtu_business_test_edit_business(
		function ( &$b ) {
			$b['address']['verified'] = true;
			$b['hours']['verified']   = true;
		}
	);
	$html = do_blocks( '<!-- wp:mrtu/contact-card /-->' );
	mrtu_business_test_ok( false !== strpos( $html, 'Phúc Tân' ) && false !== strpos( $html, 'mrtu-contact-card__item--hours' ) && false === strpos( $html, '--area' ), 'block contact: verified address + hours shown' );
	$html = do_blocks( '<!-- wp:mrtu/contact-card {"showHours":false,"showAddress":false} /-->' );
	mrtu_business_test_ok( false === strpos( $html, 'Phúc Tân' ) && false === strpos( $html, 'mrtu-contact-card__item--hours' ), 'block contact: attributes hide address/hours' );

	$html = do_blocks( '<!-- wp:mrtu/cta /-->' );
	mrtu_business_test_ok( false !== strpos( $html, 'mrtu-cta--call' ) && false !== strpos( $html, 'mrtu-button mrtu-button--action' ) && false !== strpos( $html, 'href="tel:+84816659199"' ), 'block cta: call button with verified phone' );
	mrtu_business_test_contains( '<svg class="mrtu-icon', $html, 'block cta: inline SVG icon' );
	mrtu_business_test_edit_business(
		function ( &$b ) {
			$b['phone']['verified'] = false;
		}
	);
	$html = do_blocks( '<!-- wp:mrtu/cta /-->' );
	mrtu_business_test_lacks( 'tel:', $html, 'block cta: no call button without a verified phone' );

	ob_start();
	$BL::print_js_flag();
	mrtu_business_test_contains( "classList.add('mrtu-js')", ob_get_clean(), 'head: mrtu-js flag script' );

	/* ------------------------------------------------------------ 10. Quick Contact */
	mrtu_business_test_reset();
	$markup = $QC::markup();
	mrtu_business_test_contains( 'data-mrtu-qc', $markup, 'quick contact: renders with a verified phone' );
	mrtu_business_test_contains( 'mrtu-qc__item--call', $markup, 'quick contact: call item' );
	mrtu_business_test_contains( 'href="tel:+84816659199"', $markup, 'quick contact: tel link' );
	mrtu_business_test_contains( 'aria-expanded="false" aria-controls="mrtu-qc-panel"', $markup, 'quick contact: toggle ARIA' );
	mrtu_business_test_contains( 'id="mrtu-qc-panel" hidden', $markup, 'quick contact: panel hidden by default' );
	mrtu_business_test_contains( 'data-position="right"', $markup, 'quick contact: default position right' );
	mrtu_business_test_lacks( 'mrtu-qc__item--chat', $markup, 'quick contact: no chat without the assistant' );
	add_filter( 'mrtu_assistant_active', '__return_true' );
	$markup = $QC::markup();
	mrtu_business_test_ok( false !== strpos( $markup, 'mrtu-qc__item--chat' ) && false !== strpos( $markup, 'data-mrtu-assistant-open' ), 'quick contact: chat item when mrtu_assistant_active is true' );
	remove_all_filters( 'mrtu_assistant_active' );

	mrtu_business_test_edit_business(
		function ( &$b ) {
			$b['phone']['verified'] = false;
		}
	);
	mrtu_business_test_same( '', $QC::markup(), 'quick contact: nothing rendered when no item is publishable' );
	add_filter( 'mrtu_assistant_active', '__return_true' );
	$markup = $QC::markup();
	mrtu_business_test_ok( false !== strpos( $markup, 'mrtu-qc__item--chat' ) && false === strpos( $markup, 'mrtu-qc__item--call' ), 'quick contact: chat only when the phone is unverified' );
	remove_all_filters( 'mrtu_assistant_active' );
	$status = $QC::item_status();
	mrtu_business_test_ok( ! $status['call']['publishable'] && '' !== $status['call']['reason'], 'quick contact: admin status explains why call is hidden' );

	mrtu_business_test_reset();
	$QC::save(
		array(
			'enabled'  => true,
			'position' => 'left',
			'items'    => array(
				'call'     => true,
				'whatsapp' => true,
			),
		),
		1
	);
	mrtu_business_test_contains( 'data-position="left"', $QC::markup(), 'quick contact: position setting' );
	mrtu_business_test_lacks( 'wa.me', $QC::markup(), 'quick contact: WhatsApp item hidden while the channel is unverified' );
	mrtu_business_test_edit_business(
		function ( &$b ) {
			$b['channels']['whatsapp']['enabled']  = true;
			$b['channels']['whatsapp']['verified'] = true;
		}
	);
	mrtu_business_test_contains( 'https://wa.me/84816659199', $QC::markup(), 'quick contact: WhatsApp item once enabled + verified' );
	add_filter(
		'mrtu_quick_contact_items',
		function ( $items ) {
			unset( $items['whatsapp'] );
			return $items;
		}
	);
	mrtu_business_test_lacks( 'wa.me', $QC::markup(), 'quick contact: mrtu_quick_contact_items filter applies' );
	remove_all_filters( 'mrtu_quick_contact_items' );
	$QC::save( array( 'enabled' => false ), 1 );
	mrtu_business_test_same( '', $QC::markup(), 'quick contact: disabled setting renders nothing' );

	/* ------------------------------------------------------------ 11. HTTP check (dev server) */
	mrtu_business_test_reset();
	$mrtu_bt_page = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Mr Tu business blocks (test)',
			'post_name'    => 'mrtu-business-blocks-test',
			'post_content' => "<!-- wp:mrtu/pricing-table {\"period\":\"day\"} /-->\n\n<!-- wp:mrtu/fleet /-->\n\n<!-- wp:mrtu/contact-card /-->\n\n<!-- wp:mrtu/cta {\"variant\":\"call\"} /-->",
		)
	);
	// Default to this install's own URL: several local envs run on different ports (8080–8085).
	$base_url = getenv( 'MRTU_TEST_URL' ) ? rtrim( getenv( 'MRTU_TEST_URL' ), '/' ) : rtrim( home_url(), '/' );
	$url      = $base_url . '/?page_id=' . (int) $mrtu_bt_page;
	$response = wp_remote_get( $url, array( 'timeout' => 30 ) );
	if ( is_wp_error( $response ) ) {
		echo 'SKIP: HTTP check — dev server not reachable at ' . $base_url . ' (' . $response->get_error_message() . ')' . PHP_EOL;
	} else {
		$body = wp_remote_retrieve_body( $response );
		$head = (int) strpos( $body, '</head>' );
		mrtu_business_test_same( 200, (int) wp_remote_retrieve_response_code( $response ), 'http: test page 200' );
		foreach ( array( 'mrtu-pricing', 'role="tablist"', 'mrtu-fleet', 'mrtu-vehicle', 'mrtu-contact-card', 'data-mrtu-qc-avoid', 'mrtu-cta', 'class="mrtu-qc"', "classList.add('mrtu-js')" ) as $needle ) {
			mrtu_business_test_contains( $needle, $body, 'http: page contains ' . $needle );
		}
		foreach ( array( '150.000', '1.200.000', 'Phúc Tân' ) as $needle ) {
			mrtu_business_test_lacks( $needle, $body, 'http: no unverified data (' . $needle . ')' );
		}
		$css = strpos( $body, 'assets/css/blocks.css' );
		mrtu_business_test_ok( false !== $css && $css < $head, 'http: blocks.css enqueued in <head> when blocks are present' );
		$js = strpos( $body, 'assets/js/pricing.js' );
		mrtu_business_test_ok( false !== $js && $js > $head, 'http: pricing.js in the footer' );
		mrtu_business_test_ok( false !== strpos( $body, 'assets/js/fleet.js' ) && false !== strpos( $body, 'assets/js/quick-contact.js' ), 'http: fleet.js + quick-contact.js enqueued' );

		$other = wp_remote_get( $base_url . '/?p=1', array( 'timeout' => 30 ) );
		$other = is_wp_error( $other ) ? '' : wp_remote_retrieve_body( $other );
		mrtu_business_test_ok( '' !== $other && false === strpos( $other, 'assets/css/blocks.css' ), 'http: blocks.css not loaded on a page without blocks' );
		mrtu_business_test_contains( 'class="mrtu-qc"', $other, 'http: quick contact dock on every page' );
	}

	/* ------------------------------------------------------------ 12. Core i18n API integration (stubbed when absent) */
	if ( ! function_exists( 'mrtu_role_url' ) && ! function_exists( 'mrtu_current_language' ) ) {
		function mrtu_current_language() {
			return 'en';
		}
		function mrtu_role_url( $role, $lang = null ) {
			$lang = $lang ? $lang : 'en';
			return in_array( $role, array( 'contact', 'pricing' ), true ) ? home_url( '/' . ( 'vi' === $lang ? '' : $lang . '/' ) . $role . '/' ) : '';
		}
		mrtu_business_test_reset();
		mrtu_business_test_same( '₫150,000', mrtu_format_vnd( 150000 ), 'i18n: current language from mrtu_current_language()' );
		$channels = mrtu_contact_channels();
		mrtu_business_test_same( home_url( '/en/contact/' ), isset( $channels['contact'] ) ? $channels['contact']['url'] : null, 'i18n: contact channel uses the same-language role URL' );
		$html = do_blocks( '<!-- wp:mrtu/cta {"variant":"contact"} /-->' );
		mrtu_business_test_ok( (bool) preg_match( '#<a class="mrtu-button mrtu-button--accent" href="' . preg_quote( home_url( '/en/contact/' ), '#' ) . '"#', $html ) && false !== strpos( $html, 'mrtu-button--ghost' ), 'i18n: CTA contact variant → contact page first, call second' );
		mrtu_business_test_contains( 'href="' . home_url( '/en/pricing/' ) . '"', do_blocks( '<!-- wp:mrtu/fleet /-->' ), 'i18n: fleet links to the same-language pricing page' );
		$QC::save(
			array(
				'items' => array(
					'call'    => true,
					'contact' => true,
				),
			),
			1
		);
		mrtu_business_test_contains( 'mrtu-qc__item--contact', $QC::markup(), 'i18n: Quick Contact contact-page item' );
		mrtu_business_test_contains( '/de/contact/', mrtu_contact_channels( true ) ? $B::contact_channels( true, 'de' )['contact']['url'] : '', 'i18n: explicit language for channels' );
	} else {
		echo 'SKIP: i18n stub checks — the core i18n API is loaded' . PHP_EOL;
	}
} catch ( \Throwable $e ) {
	mrtu_business_test_ok( false, 'uncaught ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
} finally {
	if ( $mrtu_bt_page ) {
		wp_delete_post( $mrtu_bt_page, true );
	}
	foreach ( $mrtu_bt_backup as $mrtu_bt_key => $mrtu_bt_value ) {
		if ( '__mrtu_absent__' === $mrtu_bt_value ) {
			delete_option( $mrtu_bt_key );
		} else {
			update_option( $mrtu_bt_key, $mrtu_bt_value, 'mrtu_business_history' !== $mrtu_bt_key );
		}
	}
	\MrTu\Multilang\Business::flush_cache();
}

$mrtu_bt_total = $GLOBALS['mrtu_business_test']['pass'] + $GLOBALS['mrtu_business_test']['fail'];
echo 'RESULT: ' . $GLOBALS['mrtu_business_test']['pass'] . '/' . $mrtu_bt_total . PHP_EOL;
if ( $GLOBALS['mrtu_business_test']['fail'] > 0 ) {
	exit( 1 );
}
