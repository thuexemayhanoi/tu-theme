<?php
/**
 * Health check tests: completeness matrix, duplicate SEO titles, keyword cannibalization,
 * orphan detection, caching and severity grouping — with small fixtures on top of whatever the env holds.
 *
 * Run: wp eval-file tests/php/test-health.php   (or tests/run-php.sh <env-dir>)
 *
 * @package MrTu\Multilang\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DB.SlowDBQuery -- CLI test file.

require __DIR__ . '/bootstrap.php';

use MrTu\Multilang\Admin\Admin_Health;
use MrTu\Multilang\Health;
use MrTu\Multilang\Languages;
use MrTu\Multilang\Roles;
use MrTu\Multilang\Seeder;
use MrTu\Multilang\Translations;

if ( ! class_exists( 'MrTu\Multilang\Health' ) ) {
	echo "FAIL: plugin mr-tu-multilang-seo is not active\nRESULT: 0 passed, 1 failed\n";
	exit( 1 );
}

MrTu_Test::start( 'Health' );
$run = MrTu_Test::$run;
Translations::ensure_language_terms();
Health::flush();

/**
 * Finds a check by id.
 *
 * @param array[] $checks Checks.
 * @param string  $id     Check id.
 * @return array
 */
function mrtu_health_find( array $checks, $id ) {
	foreach ( $checks as $check ) {
		if ( $check['id'] === $id ) {
			return $check;
		}
	}
	return array(
		'id'       => $id,
		'severity' => 'missing',
		'label'    => '',
		'detail'   => '',
		'link'     => '',
	);
}

/*
 * Completeness matrix
 */
MrTu_Test::section( 'Completeness matrix' );
$done = Health::completeness();
MrTu_Test::same( Languages::codes( true ), $done['languages'], 'matrix columns = enabled languages' );
MrTu_Test::same( array_keys( Roles::registry() ), array_keys( $done['matrix'] ), 'matrix rows = role registry' );
$published = 0;
$total     = 0;
$shape_ok  = true;
foreach ( $done['matrix'] as $cells ) {
	foreach ( $done['languages'] as $code ) {
		if ( ! isset( $cells[ $code ]['status'], $cells[ $code ]['id'] ) || ! in_array( $cells[ $code ]['status'], array( 'published', 'draft', 'missing' ), true ) ) {
			$shape_ok = false;
			continue;
		}
		++$total;
		if ( 'published' === $cells[ $code ]['status'] ) {
			++$published;
		}
	}
}
MrTu_Test::ok( $shape_ok, 'every cell has a status (published|draft|missing) and an id' );
MrTu_Test::same( array( $published, $total, (int) round( 100 * $published / max( 1, $total ) ) ), array( $done['published'], $done['total'], $done['percent'] ), 'published/total/percent match the matrix' );
MrTu_Test::same( count( Roles::registry() ) * count( $done['languages'] ), $done['total'], 'total = roles × languages' );

$about_es = Roles::get_page_id_by_role( 'about', 'es' );
if ( $about_es && 'publish' === get_post_status( $about_es ) ) {
	wp_update_post(
		array(
			'ID'          => $about_es,
			'post_status' => 'draft',
		)
	);
	$after = Health::completeness();
	MrTu_Test::same( $done['published'] - 1, $after['published'], 'unpublishing ES "about" lowers the published count by one' );
	MrTu_Test::same( 'draft', $after['matrix']['about']['es']['status'], 'cell reports draft' );
	$check = mrtu_health_find( Health::run( array( 'loopback' => false ) ), 'translations' );
	MrTu_Test::same( 'warning', $check['severity'], 'translations check warns' );
	MrTu_Test::ok( false !== strpos( $check['detail'], 'ES' ) && false !== strpos( $check['detail'], 'not published' ), 'translations check names the unpublished ES version', $check['detail'] );
	wp_update_post(
		array(
			'ID'          => $about_es,
			'post_status' => 'publish',
		)
	);
	MrTu_Test::same( $done['published'], Health::completeness()['published'], 'republishing restores the count' );
} else {
	$check = mrtu_health_find( Health::run( array( 'loopback' => false ) ), 'translations' );
	MrTu_Test::ok( in_array( $check['severity'], array( 'warning', 'error' ), true ), 'translations check flags the incomplete matrix on an unseeded env' );
	MrTu_Test::ok( false !== strpos( $check['detail'], 'missing' ), 'translations check lists missing versions', $check['detail'] );
}

/*
 * Fixtures: duplicates, cannibalization, orphans
 */
MrTu_Test::section( 'Duplicate titles, cannibalization and orphans' );
$title_a = "Health A {$run}";
$a       = MrTu_Test::post(
	array(
		'post_title' => $title_a,
		'meta_input' => array(
			Seeder::META_SEO_TITLE => "Same SEO title {$run}",
			Seeder::META_FOCUS_KW  => "Thuê xe máy {$run}",
		),
	),
	'en'
);
$b       = MrTu_Test::post(
	array(
		'post_title' => "Health B {$run}",
		'meta_input' => array(
			Seeder::META_SEO_TITLE => "same seo title {$run}",
			Seeder::META_FOCUS_KW  => "thuê xe máy {$run} ",
		),
	),
	'en'
);
$vi      = MrTu_Test::post(
	array(
		'post_title' => "Health VI {$run}",
		'meta_input' => array(
			Seeder::META_SEO_TITLE => "Same SEO title {$run}",
			Seeder::META_FOCUS_KW  => "thuê xe máy {$run}",
		),
	),
	'vi'
);
$c       = MrTu_Test::post(
	array(
		'post_title'   => "Health C {$run}",
		'post_content' => '<!-- wp:paragraph --><p><a href="' . esc_url( get_permalink( $a ) ) . '">A</a></p><!-- /wp:paragraph -->',
	),
	'en'
);
MrTu_Test::ok( $a && $b && $vi && $c, 'fixtures created' );
MrTu_Test::same( array( 'en', 'en', 'vi', 'en' ), array( Translations::get_post_language( $a ), Translations::get_post_language( $b ), Translations::get_post_language( $vi ), Translations::get_post_language( $c ) ), 'fixture languages' );

$checks = Health::run( array( 'loopback' => false ) );
$ids    = wp_list_pluck( $checks, 'id' );
foreach ( array( 'translations', 'pages_without_role', 'seo_duplicate_titles', 'seo_duplicate_descriptions', 'seo_title_length', 'seo_desc_length', 'seo_focus_keywords', 'orphans', 'broken_links', 'images_alt', 'business', 'quick_contact', 'seo_mode', 'front_pages', 'disabled_languages' ) as $id ) {
	if ( ! in_array( $id, $ids, true ) ) {
		MrTu_Test::ok( false, "check {$id} present" );
	}
}
MrTu_Test::ok( count( $ids ) === count( array_unique( $ids ) ), 'check ids are unique' );
$shape_ok = true;
foreach ( $checks as $check ) {
	if ( ! isset( $check['id'], $check['severity'], $check['label'], $check['detail'], $check['link'] ) || ! in_array( $check['severity'], array( 'ok', 'warning', 'error' ), true ) ) {
		$shape_ok = false;
	}
}
MrTu_Test::ok( $shape_ok, 'every check has id, severity (ok|warning|error), label, detail and link' );

$dup = mrtu_health_find( $checks, 'seo_duplicate_titles' );
MrTu_Test::same( 'warning', $dup['severity'], 'duplicate SEO title within EN detected' );
MrTu_Test::ok( false !== strpos( $dup['detail'], "#{$a}, #{$b}" ) && false !== strpos( $dup['detail'], 'EN:' ), 'duplicate check names both EN pages', $dup['detail'] );
MrTu_Test::ok( false === strpos( $dup['detail'], "#{$vi}" ), 'same title in another language is not a duplicate', $dup['detail'] );
MrTu_Test::ok( '' !== $dup['link'], 'duplicate check links to a screen' );

$kw = mrtu_health_find( $checks, 'seo_focus_keywords' );
MrTu_Test::same( 'warning', $kw['severity'], 'keyword cannibalization within EN detected' );
MrTu_Test::ok( false !== strpos( $kw['detail'], "#{$a}, #{$b}" ) && false !== mb_strpos( $kw['detail'], mb_strtolower( "thuê xe máy {$run}" ) ), 'cannibalization check names the keyword (normalised) and both pages', $kw['detail'] );
MrTu_Test::ok( false === strpos( $kw['detail'], "#{$vi}" ), 'same keyword in another language is not cannibalization', $kw['detail'] );

$orphans = mrtu_health_find( $checks, 'orphans' );
MrTu_Test::same( 'warning', $orphans['severity'], 'orphans detected' );
MrTu_Test::ok( false !== strpos( $orphans['detail'], "Health B {$run} (EN #{$b})" ), 'B (no inbound link) is an orphan', $orphans['detail'] );
MrTu_Test::ok( false === strpos( $orphans['detail'], "(EN #{$a})" ), 'A (linked from C) is not an orphan', $orphans['detail'] );
MrTu_Test::ok( false !== strpos( $orphans['detail'], "(VI #{$vi})" ), 'VI fixture without links is an orphan', $orphans['detail'] );

$no_role = mrtu_health_find( $checks, 'pages_without_role' );
MrTu_Test::same( 'warning', $no_role['severity'], 'top-level pages without a role reported' );
MrTu_Test::ok( false !== strpos( $no_role['detail'], "(EN #{$a})" ), 'A listed as a page without role', $no_role['detail'] );

$broken = mrtu_health_find( $checks, 'broken_links' );
MrTu_Test::ok( false === strpos( $broken['detail'], "#{$c}" ), 'the EN → EN link from C is not broken or cross-language', $broken['detail'] );

// Fix the fixtures: unique meta + an inbound link for B → the warnings about them disappear.
update_post_meta( $b, Seeder::META_SEO_TITLE, "Unique title {$run}" );
update_post_meta( $b, Seeder::META_FOCUS_KW, "unique keyword {$run}" );
wp_update_post(
	array(
		'ID'           => $c,
		'post_content' => '<!-- wp:paragraph --><p><a href="' . esc_url( get_permalink( $a ) ) . '">A</a> <a href="' . esc_url( get_permalink( $b ) ) . '">B</a></p><!-- /wp:paragraph -->',
	)
);
$checks  = Health::run( array( 'loopback' => false ) );
$dup     = mrtu_health_find( $checks, 'seo_duplicate_titles' );
$kw      = mrtu_health_find( $checks, 'seo_focus_keywords' );
$orphans = mrtu_health_find( $checks, 'orphans' );
MrTu_Test::ok( false === strpos( $dup['detail'], "#{$a}" ), 'after fixing the title, A/B no longer duplicate', $dup['detail'] );
MrTu_Test::ok( false === strpos( $kw['detail'], "#{$a}" ), 'after fixing the keyword, A/B no longer compete', $kw['detail'] );
MrTu_Test::ok( false === strpos( $orphans['detail'], "(EN #{$b})" ), 'after linking B, it is no longer an orphan', $orphans['detail'] );

/*
 * Summary, cache and grouping
 */
MrTu_Test::section( 'Summary, cache and grouping' );
$sample = array(
	array( 'severity' => 'ok' ),
	array( 'severity' => 'warning' ),
	array( 'severity' => 'error' ),
	array( 'severity' => 'warning' ),
	array( 'severity' => 'bogus' ),
);
MrTu_Test::same(
	array(
		'ok'      => 1,
		'warning' => 3,
		'error'   => 1,
	),
	Health::summary( $sample ),
	'summary() counts per severity (unknown → warning)'
);
$grouped = Admin_Health::grouped(
	array(
		array(
			'id'       => 'x',
			'severity' => 'ok',
		),
		array(
			'id'       => 'y',
			'severity' => 'error',
		),
		array(
			'id'       => 'z',
			'severity' => 'warning',
		),
	)
);
MrTu_Test::same( array( 'error', 'warning', 'ok' ), array_keys( $grouped ), 'grouped(): errors, warnings, ok' );
MrTu_Test::same( array( 'y', 'z', 'x' ), array( $grouped['error'][0]['id'], $grouped['warning'][0]['id'], $grouped['ok'][0]['id'] ), 'grouped(): checks land in their severity' );

Health::flush();
MrTu_Test::same( null, Health::cached(), 'flush(): nothing cached' );
$first = Health::get( array( 'loopback' => false ) );
MrTu_Test::ok( isset( $first['time'], $first['checks'], $first['summary'] ) && false === $first['loopback'], 'get() computes and reports loopback off' );
$cached = Health::cached();
MrTu_Test::same( $first['time'], $cached ? $cached['time'] : null, 'get() stores the report in the transient' );
MrTu_Test::same( $first['time'], Health::get()['time'], 'second get() is served from the cache' );
MrTu_Test::same( Health::summary( $first['checks'] ), $first['summary'], 'summary matches the checks' );
$fresh = Health::get(
	array(
		'fresh'    => true,
		'loopback' => false,
	)
);
MrTu_Test::ok( $fresh['time'] >= $first['time'], 'get( fresh ) recomputes' );
Health::flush();
MrTu_Test::same( null, Health::cached(), 'flush() after get() clears the cache' );

MrTu_Test::finish();
