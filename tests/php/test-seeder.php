<?php
/**
 * Seeder integration tests: idempotent five-language seeding, roles, translation groups,
 * categories, meta, update/customized handling, dry run, reading settings and missing languages.
 *
 * Works on a fresh env (creates and removes the content) and on an already seeded env (verifies it).
 *
 * Run: wp eval-file tests/php/test-seeder.php   (or tests/run-php.sh <env-dir>)
 *
 * @package MrTu\Multilang\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DB.SlowDBQuery, WordPress.WP.AlternativeFunctions -- CLI test file.

require __DIR__ . '/bootstrap.php';

use MrTu\Multilang\Admin\Admin_Dashboard;
use MrTu\Multilang\Health;
use MrTu\Multilang\Languages;
use MrTu\Multilang\Roles;
use MrTu\Multilang\Router;
use MrTu\Multilang\Seeder;
use MrTu\Multilang\Translations;

if ( ! class_exists( 'MrTu\Multilang\Seeder' ) ) {
	echo "FAIL: plugin mr-tu-multilang-seo is not active\nRESULT: 0 passed, 1 failed\n";
	exit( 1 );
}

MrTu_Test::start( 'Seeder' );
foreach ( array( 'show_on_front', 'page_on_front', 'page_for_posts', 'timezone_string', 'date_format', 'mrtu_roles_map', 'mrtu_term_translations', Seeder::REPORT_OPTION ) as $mrtu_option ) {
	MrTu_Test::keep_option( $mrtu_option );
}
$run = MrTu_Test::$run;
Translations::ensure_language_terms();

/**
 * Ids of every seeded post (any language, any status).
 *
 * @return int[]
 */
function mrtu_seed_test_posts() {
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'        => Translations::post_types(),
				'post_status'      => 'any',
				'meta_key'         => Seeder::META_SEED_ID,
				'fields'           => 'ids',
				'numberposts'      => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
				Router::QUERY_VAR  => 'all',
			)
		)
	);
}

/**
 * Ids of every seeded category.
 *
 * @return int[]
 */
function mrtu_seed_test_terms() {
	$ids = get_terms(
		array(
			'taxonomy'        => 'category',
			'hide_empty'      => false,
			'fields'          => 'ids',
			'meta_key'        => Seeder::META_SEED_ID,
			Router::QUERY_VAR => 'all',
		)
	);
	return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
}

/**
 * Report item of a seed id in a language.
 *
 * @param array  $report  Report.
 * @param string $lang    Language.
 * @param string $seed_id Seed id.
 * @return array|null
 */
function mrtu_seed_test_item( array $report, $lang, $seed_id ) {
	foreach ( $report['languages'][ $lang ]['items'] as $item ) {
		if ( $item['seed_id'] === $seed_id ) {
			return $item;
		}
	}
	return null;
}

$langs      = Languages::codes( true );
$source     = Seeder::source_dir();
$pre_posts  = mrtu_seed_test_posts();
$pre_terms  = mrtu_seed_test_terms();
$pre_seeded = count( $pre_posts ) > 0;
echo $pre_seeded ? "  (env already seeded: " . count( $pre_posts ) . " posts — verifying in place)\n" : "  (fresh env: seeding, then removing the content)\n";

/*
 * Source
 */
MrTu_Test::section( 'Source directory' );
MrTu_Test::same( array( 'vi', 'en', 'de', 'fr', 'es' ), $langs, 'five enabled languages' );
MrTu_Test::ok( is_dir( $source ) && is_file( $source . '/vi/pages.json' ), 'source dir has vi/pages.json', $source );
$available = Seeder::available_languages();
MrTu_Test::same( $langs, array_keys( $available ), 'available_languages() lists every language with content' );
$all_counts = true;
foreach ( $available as $code => $counts ) {
	$all_counts = $all_counts && $counts['pages'] >= 10 && $counts['posts'] >= 1 && $counts['categories'] >= 1;
}
MrTu_Test::ok( $all_counts, 'every language has pages, posts and categories', MrTu_Test::export( $available ) );
$vi_entries = json_decode( file_get_contents( $source . '/vi/pages.json' ), true );
$entry_total = count( $vi_entries ) + count( json_decode( file_get_contents( $source . '/vi/categories.json' ), true ) );
$by_role     = array();
foreach ( $vi_entries as $entry ) {
	if ( ! empty( $entry['role'] ) ) {
		$by_role[ $entry['role'] ] = $entry['seed_id'];
	}
}
MrTu_Test::ok( isset( $by_role['home'], $by_role['pillar'], $by_role['guide-faq'], $by_role['blog'], $by_role['about'], $by_role['contact'] ), 'seed entries carry the core roles' );

/*
 * Dry run
 */
MrTu_Test::section( 'Dry run writes nothing' );
$front_before = array( get_option( 'show_on_front' ), Router::raw_option( 'page_on_front' ), Router::raw_option( 'page_for_posts' ) );
$dry          = Seeder::run(
	array(
		'dry_run'   => true,
		'set_front' => true,
	)
);
MrTu_Test::ok( ! empty( $dry['dry_run'] ), 'report flagged as dry run' );
MrTu_Test::same( 0, $dry['totals']['errors'], 'dry run: no errors' );
MrTu_Test::same( $pre_posts, mrtu_seed_test_posts(), 'dry run: no posts written' );
MrTu_Test::same( $pre_terms, mrtu_seed_test_terms(), 'dry run: no categories written' );
MrTu_Test::same( $front_before, array( get_option( 'show_on_front' ), Router::raw_option( 'page_on_front' ), Router::raw_option( 'page_for_posts' ) ), 'dry run: reading settings untouched' );
MrTu_Test::ok( empty( $dry['front']['applied'] ), 'dry run: front not applied' );
if ( $pre_seeded ) {
	MrTu_Test::same( 0, $dry['totals']['created'], 'dry run on a seeded env creates nothing' );
} else {
	MrTu_Test::same( $entry_total * count( $langs ), $dry['totals']['created'], 'dry run on a fresh env would create every entry of every language' );
}
$last = Seeder::last_report();
MrTu_Test::ok( is_array( $last ) && ! empty( $last['dry_run'] ), 'last_report() stores the dry run, flagged' );

/*
 * Seed twice
 */
MrTu_Test::section( 'Seed all languages twice' );
$created_terms = array();
$r1            = Seeder::run( array( 'set_front' => true ) );
foreach ( $r1['languages'] as $code => $lang_rep ) {
	foreach ( $lang_rep['items'] as $item ) {
		if ( 'created' !== $item['action'] ) {
			continue;
		}
		if ( 'category' === $item['type'] ) {
			$created_terms[] = (int) $item['id'];
		} else {
			MrTu_Test::track_post( (int) $item['id'] );
		}
	}
}
$after1 = mrtu_seed_test_posts();
$terms1 = mrtu_seed_test_terms();
MrTu_Test::same( 0, $r1['totals']['errors'], 'run 1: no errors', MrTu_Test::export( $r1['messages'] ) );
MrTu_Test::same( array_fill_keys( $langs, true ), array_map( static function ( $l ) { return (bool) $l['requested']; }, array_intersect_key( $r1['languages'], array_flip( $langs ) ) ), 'run 1: every language requested' );
MrTu_Test::same( $entry_total * count( $langs ), $r1['totals']['created'] + $r1['totals']['skipped'] + $r1['totals']['updated'] + $r1['totals']['customized'], 'run 1: every entry handled' );
if ( ! $pre_seeded ) {
	MrTu_Test::same( $entry_total * count( $langs ), $r1['totals']['created'], 'run 1 on a fresh env creates every entry' );
	MrTu_Test::same( count( $vi_entries ) * count( $langs ), count( $after1 ), 'run 1: one post per page/post entry per language' );
}
MrTu_Test::ok( ! empty( $r1['front']['applied'] ), 'run 1: reading settings applied' );

$r2     = Seeder::run( array( 'set_front' => true ) );
$after2 = mrtu_seed_test_posts();
MrTu_Test::same( 0, $r2['totals']['created'], 'run 2 creates nothing' );
MrTu_Test::same( 0, $r2['totals']['errors'], 'run 2: no errors' );
MrTu_Test::same( $entry_total * count( $langs ), $r2['totals']['skipped'], 'run 2 skips every entry' );
MrTu_Test::same( $after1, $after2, 'run 2: identical post set' );
MrTu_Test::same( $terms1, mrtu_seed_test_terms(), 'run 2: identical category set' );
if ( ! $pre_seeded ) {
	MrTu_Test::same( count( $langs ) * $available['vi']['categories'], count( $terms1 ), 'run 1: one category per entry per language' );
}

$keys = array();
$dups = array();
foreach ( $after2 as $id ) {
	$key = get_post_meta( $id, Seeder::META_SEED_ID, true ) . '|' . Translations::get_post_language( $id );
	if ( isset( $keys[ $key ] ) ) {
		$dups[] = $key;
	}
	$keys[ $key ] = $id;
}
MrTu_Test::same( array(), $dups, 'no duplicate (seed id, language) pairs' );
$all_published = true;
$bad           = array();
foreach ( $after2 as $id ) {
	if ( 'publish' !== get_post_status( $id ) ) {
		$all_published = false;
		$bad[]         = $id;
	}
}
MrTu_Test::ok( $all_published, 'every seeded post is published', MrTu_Test::export( $bad ) );

/*
 * Roles and translation groups
 */
MrTu_Test::section( 'Roles and translation groups' );
$missing = array();
foreach ( array_keys( Roles::registry() ) as $role ) {
	foreach ( $langs as $code ) {
		$id = Roles::get_page_id_by_role( $role, $code );
		if ( ! $id || 'publish' !== get_post_status( $id ) || Translations::get_post_language( $id ) !== $code ) {
			$missing[] = "{$role}/{$code}";
		}
	}
}
MrTu_Test::same( array(), $missing, 'every role is published in every language' );
$done = Health::completeness();
MrTu_Test::same( array( 100, $done['total'] ), array( $done['percent'], $done['published'] ), 'completeness 100%' );
foreach ( array( 'pillar', 'home', 'guide-faq' ) as $role ) {
	$vi_id = Roles::get_page_id_by_role( $role, 'vi' );
	$map   = Translations::get_post_translations( $vi_id );
	ksort( $map );
	$sorted = $langs;
	sort( $sorted );
	MrTu_Test::same( $sorted, array_keys( $map ), "{$role}: translation group has 5 entries" );
	MrTu_Test::same( $vi_id, isset( $map['vi'] ) ? $map['vi'] : 0, "{$role}: VI member is the VI page" );
	foreach ( $map as $code => $member ) {
		if ( Roles::get_post_role( $member ) !== $role ) {
			MrTu_Test::ok( false, "{$role}: member {$code} carries the role", Roles::get_post_role( $member ) );
		}
	}
}
$homes = array();
foreach ( $langs as $code ) {
	$homes[ $code ] = Roles::get_page_id_by_role( 'home', $code );
}
MrTu_Test::same( count( $langs ), count( array_unique( array_filter( $homes ) ) ), 'five distinct home pages' );

/*
 * Categories and posts
 */
MrTu_Test::section( 'Categories and posts' );
$cats = get_terms(
	array(
		'taxonomy'        => 'category',
		'hide_empty'      => false,
		'meta_key'        => Seeder::META_SEED_ID,
		'meta_value'      => 'cat:tips',
		Router::QUERY_VAR => 'all',
	)
);
$cats = is_array( $cats ) ? $cats : array();
$cat_langs = array();
foreach ( $cats as $term ) {
	$cat_langs[ Translations::get_term_language( $term->term_id ) ] = (int) $term->term_id;
}
ksort( $cat_langs );
$sorted = $langs;
sort( $sorted );
MrTu_Test::same( $sorted, array_keys( $cat_langs ), 'category cat:tips exists once per language' );
if ( $cat_langs ) {
	$term_map = Translations::get_term_translations( reset( $cat_langs ) );
	ksort( $term_map );
	MrTu_Test::same( $cat_langs, $term_map, 'category translations linked across the five languages' );
}
$posts_ok = true;
$detail   = array();
foreach ( $langs as $code ) {
	$ids = get_posts(
		array(
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'meta_key'         => Seeder::META_SEED_ID,
			'fields'           => 'ids',
			'numberposts'      => -1,
			'suppress_filters' => true,
			Router::QUERY_VAR  => $code,
		)
	);
	if ( count( $ids ) !== $available[ $code ]['posts'] ) {
		$posts_ok = false;
		$detail[] = "{$code}: " . count( $ids ) . ' posts';
	}
	foreach ( $ids as $id ) {
		if ( ! isset( $cat_langs[ $code ] ) || ! has_category( $cat_langs[ $code ], $id ) ) {
			$posts_ok = false;
			$detail[] = "{$code}: post {$id} not in its category";
		}
	}
}
MrTu_Test::ok( $posts_ok, 'every seeded post is published in its language and placed in the language category', implode( '; ', $detail ) );

/*
 * Nav labels and SEO meta
 */
MrTu_Test::section( 'Nav labels and SEO meta' );
$meta_ok = true;
$detail  = array();
foreach ( $langs as $code ) {
	$entries = json_decode( file_get_contents( "{$source}/{$code}/pages.json" ), true );
	foreach ( $entries as $entry ) {
		if ( empty( $entry['role'] ) || 'pillar' !== $entry['role'] ) {
			continue;
		}
		$id    = Roles::get_page_id_by_role( 'pillar', $code );
		$meta  = array(
			'nav_label' => get_post_meta( $id, Seeder::META_NAV_LABEL, true ),
			'seo_title' => get_post_meta( $id, Seeder::META_SEO_TITLE, true ),
			'seo_desc'  => get_post_meta( $id, Seeder::META_SEO_DESC, true ),
			'focus_kw'  => get_post_meta( $id, Seeder::META_FOCUS_KW, true ),
		);
		$want  = array(
			'nav_label' => isset( $entry['nav_label'] ) ? $entry['nav_label'] : '',
			'seo_title' => isset( $entry['seo_title'] ) ? $entry['seo_title'] : '',
			'seo_desc'  => isset( $entry['seo_desc'] ) ? $entry['seo_desc'] : '',
			'focus_kw'  => isset( $entry['focus_kw'] ) ? $entry['focus_kw'] : '',
		);
		if ( '' === $meta['nav_label'] || '' === $meta['seo_title'] || '' === $meta['seo_desc'] || '' === $meta['focus_kw'] ) {
			$meta_ok  = false;
			$detail[] = "{$code}: empty meta " . MrTu_Test::export( $meta );
		} elseif ( $meta !== array_map( 'sanitize_text_field', $want ) && $meta !== $want ) {
			$meta_ok  = false;
			$detail[] = "{$code}: meta differs from pages.json";
		}
		if ( get_post_field( 'post_name', $id ) !== $entry['slug'] ) {
			$meta_ok  = false;
			$detail[] = "{$code}: slug " . get_post_field( 'post_name', $id ) . " !== {$entry['slug']}";
		}
		if ( '' === (string) get_post_meta( $id, Seeder::META_SEED_HASH, true ) ) {
			$meta_ok  = false;
			$detail[] = "{$code}: no seed hash";
		}
	}
}
MrTu_Test::ok( $meta_ok, 'pillar pages carry nav label, SEO title/description, focus keyword, slug and seed hash in every language', implode( '; ', $detail ) );

/*
 * Reading settings
 */
MrTu_Test::section( 'Reading settings (--set-front)' );
MrTu_Test::same( 'page', get_option( 'show_on_front' ), 'show_on_front = page' );
MrTu_Test::same( $homes['vi'], Router::raw_option( 'page_on_front' ), 'page_on_front = VI home' );
MrTu_Test::same( Roles::get_page_id_by_role( 'blog', 'vi' ), Router::raw_option( 'page_for_posts' ), 'page_for_posts = VI blog page' );
MrTu_Test::same( 'd/m/Y', get_option( 'date_format' ), 'date format d/m/Y' );
MrTu_Test::ok( '' !== (string) get_option( 'timezone_string' ), 'timezone set' );
MrTu_Test::go_to( '/en/' );
MrTu_Test::ok( is_front_page() && is_page(), '/en/ is a front page' );
MrTu_Test::same( array( $homes['en'], 'en' ), array( get_queried_object_id(), mrtu_current_language() ), '/en/ resolves to the EN home through the Router' );
MrTu_Test::go_to( '/' );
MrTu_Test::same( array( $homes['vi'], 'vi' ), array( get_queried_object_id(), mrtu_current_language() ), '/ resolves to the VI home' );
MrTu_Test::go_to( '/de/' );
MrTu_Test::same( $homes['de'], get_queried_object_id(), '/de/ resolves to the DE home' );
MrTu_Test::leave_frontend();

/*
 * Update mode
 */
MrTu_Test::section( 'Update mode respects customized content' );
$about_en = Roles::get_page_id_by_role( 'about', 'en' );
$about_id = $by_role['about'];
$orig     = (string) get_post_field( 'post_content', $about_en, 'raw' );
$r_same   = Seeder::run(
	array(
		'update' => true,
		'lang'   => 'en',
	)
);
$item     = mrtu_seed_test_item( $r_same, 'en', $about_id );
MrTu_Test::same( 'skipped', $item ? $item['action'] : null, 'update: untouched page reported up to date (skipped)' );
MrTu_Test::same( array( 0, 0 ), array( $r_same['totals']['created'], $r_same['totals']['errors'] ), 'update run on EN only: nothing created, no errors' );
MrTu_Test::ok( empty( $r_same['languages']['vi']['requested'] ), '--lang=en: VI not requested' );

wp_update_post(
	array(
		'ID'           => $about_en,
		'post_content' => wp_slash( $orig . "\n<!-- wp:paragraph --><p>Edited {$run}</p><!-- /wp:paragraph -->" ),
	)
);
$r_custom = Seeder::run(
	array(
		'update' => true,
		'lang'   => 'en',
	)
);
$item     = mrtu_seed_test_item( $r_custom, 'en', $about_id );
MrTu_Test::same( 'customized', $item ? $item['action'] : null, 'update: edited page reported as customized' );
MrTu_Test::same( 1, $r_custom['languages']['en']['customized'], 'update: customized counter' );
MrTu_Test::ok( false !== strpos( (string) get_post_field( 'post_content', $about_en, 'raw' ), "Edited {$run}" ), 'update: edited content left unchanged' );
wp_update_post(
	array(
		'ID'           => $about_en,
		'post_content' => wp_slash( $orig ),
	)
);
MrTu_Test::same( $orig, (string) get_post_field( 'post_content', $about_en, 'raw' ), 'content restored' );

$contact_en = Roles::get_page_id_by_role( 'contact', 'en' );
$orig_title = get_post_field( 'post_title', $contact_en, 'raw' );
wp_update_post(
	array(
		'ID'         => $contact_en,
		'post_title' => "Changed {$run}",
	)
);
$r_fix = Seeder::run(
	array(
		'update' => true,
		'lang'   => 'en',
	)
);
$item  = mrtu_seed_test_item( $r_fix, 'en', $by_role['contact'] );
MrTu_Test::same( 'updated', $item ? $item['action'] : null, 'update: unchanged content with a changed title is refreshed' );
MrTu_Test::same( $orig_title, get_post_field( 'post_title', $contact_en, 'raw' ), 'update: title restored from the seed file' );
MrTu_Test::same( $after2, mrtu_seed_test_posts(), 'update runs never create posts' );

/*
 * Missing language folder
 */
MrTu_Test::section( 'Missing language folder' );
$tmp = untrailingslashit( get_temp_dir() ) . "/mrtu-seed-{$run}";
mkdir( $tmp );
symlink( $source . '/vi', $tmp . '/vi' );
$r_missing = Seeder::run(
	array(
		'dry_run' => true,
		'source'  => $tmp,
	)
);
MrTu_Test::same( $tmp, $r_missing['source'], 'source override reported' );
MrTu_Test::ok( empty( $r_missing['languages']['vi']['missing'] ), 'vi folder found' );
$missing_codes = array();
foreach ( $r_missing['languages'] as $code => $lang_rep ) {
	if ( ! empty( $lang_rep['missing'] ) ) {
		$missing_codes[] = $code;
	}
}
MrTu_Test::same( array( 'en', 'de', 'fr', 'es' ), $missing_codes, 'en/de/fr/es reported missing, not fatal' );
MrTu_Test::same( 0, $r_missing['totals']['errors'], 'missing folders are not counted as errors' );
MrTu_Test::ok( ! empty( $r_missing['languages']['en']['message'] ) && 4 === count( $r_missing['messages'] ), 'one message per missing language', MrTu_Test::export( $r_missing['messages'] ) );
unlink( $tmp . '/vi' );
rmdir( $tmp );
$r_none = Seeder::run(
	array(
		'dry_run' => true,
		'source'  => $tmp,
	)
);
MrTu_Test::same( 0, $r_none['totals']['created'] + $r_none['totals']['errors'], 'empty source: nothing created, no fatal' );

/*
 * Admin screens
 */
MrTu_Test::section( 'Admin screens' );
MrTu_Test::ok( Admin_Dashboard::is_seeded(), 'dashboard detects seeded content' );
ob_start();
Admin_Dashboard::render();
$html = ob_get_clean();
MrTu_Test::ok( false !== strpos( $html, 'name="action" value="' . Admin_Dashboard::ACTION_SEED . '"' ) && false !== strpos( $html, 'data-mrtu-confirm=' ), 'dashboard: seed form with admin-post action and confirm' );
MrTu_Test::ok( false !== strpos( $html, '_mrtu_nonce' ) && false !== strpos( $html, 'name="dry_run"' ) && false !== strpos( $html, 'name="set_front"' ), 'dashboard: nonce, dry run and set-front controls' );
MrTu_Test::ok( false !== strpos( $html, 'mrtu-stat__value">100%' ), 'dashboard: completeness stat 100%' );
MrTu_Test::ok( false !== strpos( $html, 'mrtu_business_export' ), 'dashboard: export action' );
$pages = \MrTu\Multilang\Admin\Admin_Health::register_page( Admin_Dashboard::register_page( array() ) );
MrTu_Test::same( array( 'mrtu', 'mrtu-health' ), wp_list_pluck( $pages, 'slug' ), 'dashboard (mrtu) and health (mrtu-health) register through the mrtu_admin_pages callback' );
MrTu_Test::same( array( 0, 90, 'manage_options' ), array( $pages[0]['position'], $pages[1]['position'], $pages[1]['capability'] ), 'dashboard first, health last and admin-only' );
ob_start();
\MrTu\Multilang\Admin\Admin_Health::render();
$html = ob_get_clean();
MrTu_Test::ok( false !== strpos( $html, 'mrtu_health_json' ) && false !== strpos( $html, 'mrtu_health_refresh' ) && false !== strpos( $html, 'mrtu_health_rebuild' ) && false !== strpos( $html, 'mrtu_health_flush_rewrite' ), 'health screen: refresh, rebuild, rewrite and JSON actions' );
MrTu_Test::ok( false !== strpos( $html, 'id="mrtu-health-matrix"' ) && substr_count( $html, '✅' ) >= count( $langs ) * count( Roles::registry() ), 'health screen: role × language matrix fully published' );

/*
 * Cleanup of categories created by this run (posts are tracked by the bootstrap).
 */
foreach ( $created_terms as $term_id ) {
	wp_delete_term( $term_id, 'category' );
}
if ( ! $pre_seeded ) {
	delete_option( Seeder::REPORT_OPTION );
}
Health::flush();
MrTu_Test::finish();
