<?php
/**
 * Integration tests — SEO module next to Yoast SEO (contract §2.8 compat mode).
 *
 * Needs `WPSEO_VERSION` defined before plugins_loaded: tests/run-php.sh installs tests/php/yoast-stub.php
 * as a must-use plugin for this run only. Direct run:
 *   cp tests/php/yoast-stub.php <ENV>/wordpress/wp-content/mu-plugins/zz-mrtu-yoast-stub.php
 *   wp eval-file tests/php/test-seo-yoast.php; rm <ENV>/wordpress/wp-content/mu-plugins/zz-mrtu-yoast-stub.php
 *
 * The stub only defines the constant; the assertions call our Yoast filter callbacks the way Yoast does
 * (apply_filters with an indexable presentation object) and replay `wpseo_head`.
 *
 * @package MrTu\Multilang
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DB.SlowDBQuery -- CLI test file.

require __DIR__ . '/bootstrap.php';

use MrTu\Multilang\Languages;
use MrTu\Multilang\Schema;
use MrTu\Multilang\Seo;
use MrTu\Multilang\Seo_Head;
use MrTu\Multilang\Sitemaps;
use MrTu\Multilang\Translations;
use MrTu\Multilang\Yoast_Compat;

if ( ! function_exists( 'mrtu_languages' ) ) {
	echo "FAIL: plugin mr-tu-multilang-seo is not active\nRESULT: 0 passed, 1 failed\n";
	exit( 1 );
}
if ( ! defined( 'WPSEO_VERSION' ) ) {
	echo "FAIL: Yoast stub not loaded (run through tests/run-php.sh)\nRESULT: 0 passed, 1 failed\n";
	exit( 1 );
}

MrTu_Test::start( 'SEO × Yoast (stub)' );
foreach ( array( 'mrtu_ml_settings', 'show_on_front', 'page_on_front', 'page_for_posts', 'mrtu_roles_map' ) as $mrtu_option ) {
	MrTu_Test::keep_option( $mrtu_option );
}
$run = MrTu_Test::$run;
Translations::ensure_language_terms();
Languages::update_settings(
	array(
		'seo_mode' => 'auto',
		'sitemap'  => 'auto',
	)
);

/**
 * Fake Yoast indexable presentation.
 *
 * @param string $type  post | term | home-page.
 * @param int    $id    Object id.
 * @param array  $model Extra model fields (title, description, is_robots_noindex).
 * @return object
 */
function mrtu_yoast_test_presentation( $type, $id = 0, array $model = array() ) {
	$p        = new stdClass();
	$p->model = (object) array_merge(
		array(
			'object_type'       => $type,
			'object_id'         => $id,
			'title'             => '',
			'description'       => '',
			'is_robots_noindex' => null,
		),
		$model
	);
	return $p;
}

$homes = array(
	'vi' => MrTu_Test::post(
		array(
			'post_name'  => "yhome-vi-{$run}",
			'post_title' => 'Trang chủ',
		),
		'vi'
	),
	'en' => MrTu_Test::post(
		array(
			'post_name'  => "yhome-en-{$run}",
			'post_title' => 'Home',
		),
		'en'
	),
);
mrtu_link_translations( $homes );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $homes['vi'] );
mrtu_set_post_role( $homes['vi'], 'home' );
$pages = array(
	'vi' => MrTu_Test::post(
		array(
			'post_name'  => "ypage-vi-{$run}",
			'post_title' => 'Trang VI',
		),
		'vi'
	),
	'en' => MrTu_Test::post(
		array(
			'post_name'  => "ypage-en-{$run}",
			'post_title' => 'Page EN',
			'meta_input' => array(
				'_mrtu_seo_title' => 'Seeded title | Mr Tu',
				'_mrtu_seo_desc'  => 'Seeded description.',
			),
		),
		'en'
	),
);
mrtu_link_translations( $pages );
$noindex = MrTu_Test::post(
	array(
		'post_name'  => "ynoindex-en-{$run}",
		'post_title' => 'Hidden EN',
		'meta_input' => array( '_mrtu_noindex' => '1' ),
	),
	'en'
);
$term    = MrTu_Test::term( "YTips {$run}", 'category', 'en', array( 'slug' => "ytips-{$run}" ) );
update_term_meta( $term, '_mrtu_seo_title', 'Seeded term title' );
update_term_meta( $term, '_mrtu_seo_desc', 'Seeded term description.' );

/*
 * Mode detection
 */
MrTu_Test::section( 'Mode with Yoast active' );
MrTu_Test::ok( Seo::yoast_active(), 'WPSEO_VERSION seen' );
MrTu_Test::same( 'compat', Seo::mode(), 'auto → compat' );
MrTu_Test::ok( ! Seo::is_standalone() && ! mrtu_is_seo_standalone(), 'not standalone' );
MrTu_Test::ok( Yoast_Compat::setup(), 'Yoast filters registered at plugins_loaded' );
MrTu_Test::ok( Yoast_Compat::is_compat(), 'is_compat()' );
foreach ( array( 'wpseo_title', 'wpseo_metadesc', 'wpseo_robots_array', 'wpseo_canonical', 'wpseo_opengraph_url', 'wpseo_locale', 'wpseo_schema_webpage', 'wpseo_sitemap_entry', 'wpseo_sitemap_urlset', 'wpseo_sitemap_url', 'wpseo_frontend_presenters', 'wpseo_json_ld_output', 'wpseo_dynamic_permalinks_enabled' ) as $filter ) {
	MrTu_Test::ok( false !== has_filter( $filter ), "filter hooked: {$filter}" );
}
MrTu_Test::ok( false !== has_action( 'wpseo_head', array( Yoast_Compat::class, 'print_alternates' ) ), 'wpseo_head action hooked' );
MrTu_Test::ok( apply_filters( 'wpseo_dynamic_permalinks_enabled', false ), 'wpseo_dynamic_permalinks_enabled → true' );
MrTu_Test::ok( ! Seo::sitemap_enabled(), 'our sitemap off in compat (Yoast serves its own)' );
MrTu_Test::ok( Sitemaps::core_sitemaps( true ), 'core sitemap flag untouched in compat' );
MrTu_Test::same( 'X', Sitemaps::robots_txt( 'X', true ), 'no Sitemap line in compat' );

/*
 * Title / description / robots feeding
 */
MrTu_Test::section( 'Feeding Yoast only when its field is empty' );
$p = mrtu_yoast_test_presentation( 'post', $pages['en'] );
MrTu_Test::same( 'Seeded title | Mr Tu', apply_filters( 'wpseo_title', 'Yoast fallback', $p ), 'wpseo_title ← seeded title when Yoast title empty' );
MrTu_Test::same( 'Seeded description.', apply_filters( 'wpseo_metadesc', '', $p ), 'wpseo_metadesc ← seeded description' );
$p_own = mrtu_yoast_test_presentation(
	'post',
	$pages['en'],
	array(
		'title'       => 'Owner title',
		'description' => 'Owner description',
	)
);
MrTu_Test::same( 'Owner title', apply_filters( 'wpseo_title', 'Owner title', $p_own ), 'Yoast per-post title wins' );
MrTu_Test::same( 'Owner description', apply_filters( 'wpseo_metadesc', 'Owner description', $p_own ), 'Yoast per-post description wins' );
$p_vi = mrtu_yoast_test_presentation( 'post', $pages['vi'] );
MrTu_Test::same( 'Yoast fallback', apply_filters( 'wpseo_title', 'Yoast fallback', $p_vi ), 'no seeded title → Yoast value kept' );
$p_term = mrtu_yoast_test_presentation( 'term', $term );
MrTu_Test::same( 'Seeded term title', apply_filters( 'wpseo_title', '', $p_term ), 'term: seeded title' );
MrTu_Test::same( 'Seeded term description.', apply_filters( 'wpseo_metadesc', '', $p_term ), 'term: seeded description' );
MrTu_Test::same( 'Z', apply_filters( 'wpseo_title', 'Z', null ), 'no presentation → untouched' );

$robots = array(
	'index'             => 'index',
	'follow'            => 'follow',
	'max-image-preview' => 'max-image-preview:large',
);
$out    = apply_filters( 'wpseo_robots_array', $robots, mrtu_yoast_test_presentation( 'post', $noindex ) );
MrTu_Test::same( 'noindex', $out['index'], 'wpseo_robots_array: noindex for _mrtu_noindex' );
MrTu_Test::same( 'follow', $out['follow'], 'follow kept' );
MrTu_Test::ok( ! isset( $out['max-image-preview'] ), 'max-* directives dropped on noindex' );
MrTu_Test::same( $robots, apply_filters( 'wpseo_robots_array', $robots, $p ), 'indexable post untouched' );
MrTu_Test::same( $robots, apply_filters( 'wpseo_robots_array', $robots, mrtu_yoast_test_presentation( 'post', $noindex, array( 'is_robots_noindex' => 1 ) ) ), 'Yoast per-post robots decision respected' );

/*
 * Language-correct values on the frontend
 */
MrTu_Test::section( 'Language homes and locale' );
MrTu_Test::go_to( '/en/' );
MrTu_Test::same( home_url( '/en/' ), apply_filters( 'wpseo_canonical', home_url( "/en/yhome-en-{$run}/" ), null ), 'wpseo_canonical on /en/ → /en/' );
MrTu_Test::same( home_url( '/en/' ), apply_filters( 'wpseo_opengraph_url', home_url( '/' ), null ), 'wpseo_opengraph_url on /en/ → /en/' );
MrTu_Test::same( 'en_US', apply_filters( 'wpseo_locale', 'vi_VN' ), 'wpseo_locale on /en/ → en_US' );
$piece = apply_filters( 'wpseo_schema_webpage', array( '@type' => 'WebPage' ), null );
MrTu_Test::same( 'en', isset( $piece['inLanguage'] ) ? $piece['inLanguage'] : null, 'wpseo_schema_webpage inLanguage en' );
$robots_front = apply_filters( 'wpseo_robots_array', $robots, mrtu_yoast_test_presentation( 'home-page' ) );
MrTu_Test::same( 'index', $robots_front['index'], 'home stays index' );
MrTu_Test::go_to( '/' );
MrTu_Test::same( home_url( '/' ), apply_filters( 'wpseo_canonical', home_url( "/yhome-vi-{$run}/" ), null ), 'wpseo_canonical on / → /' );
MrTu_Test::same( 'vi_VN', apply_filters( 'wpseo_locale', 'en_US' ), 'wpseo_locale on / → vi_VN' );
$piece = apply_filters( 'wpseo_schema_webpage', array( '@type' => 'WebPage' ), null );
MrTu_Test::same( 'vi-VN', isset( $piece['inLanguage'] ) ? $piece['inLanguage'] : null, 'wpseo_schema_webpage inLanguage vi-VN' );
MrTu_Test::go_to( "/en/ypage-en-{$run}/" );
$url = get_permalink( $pages['en'] );
MrTu_Test::same( $url, apply_filters( 'wpseo_canonical', $url, $p ), 'singular canonical left to Yoast' );
MrTu_Test::go_to( '/en/?s=xe' );
$robots_search = apply_filters( 'wpseo_robots_array', $robots, null );
MrTu_Test::same( 'noindex', $robots_search['index'], 'search noindex through wpseo_robots_array' );

/*
 * Head in compat mode: only hreflang + og:locale:alternate from us
 */
MrTu_Test::section( 'Head output in compat mode' );
MrTu_Test::go_to( "/en/ypage-en-{$run}/" );
MrTu_Test::ok( ! Seo_Head::is_active(), 'Seo_Head inactive' );
MrTu_Test::ok( false === has_action( 'wp_head', array( Seo_Head::class, 'output' ) ), 'Seo_Head not hooked to wp_head' );
MrTu_Test::ok( false === has_action( 'wp_head', array( Schema::class, 'output' ) ), 'Schema not hooked to wp_head' );
MrTu_Test::same( 'Keep', Seo_Head::document_title( 'Keep' ), 'pre_get_document_title untouched' );
MrTu_Test::same( array( 'index' => 'index' ), Seo_Head::robots( array( 'index' => 'index' ) ), 'wp_robots untouched (Yoast owns robots)' );
MrTu_Test::ok( false !== has_action( 'wp_head', 'rel_canonical' ), 'core rel_canonical not removed by us (Yoast handles it)' );
ob_start();
do_action( 'wpseo_head' );
$printed = ob_get_clean();
MrTu_Test::ok( false !== strpos( $printed, 'hreflang="en"' ) && false !== strpos( $printed, 'hreflang="vi-VN"' ) && false !== strpos( $printed, 'hreflang="x-default"' ), 'wpseo_head prints hreflang links' );
MrTu_Test::ok( false !== strpos( $printed, 'og:locale:alternate" content="vi_VN"' ), 'wpseo_head prints og:locale:alternate' );
MrTu_Test::ok( false === strpos( $printed, '<title' ) && false === strpos( $printed, 'name="description"' ) && false === strpos( $printed, 'rel="canonical"' ) && false === strpos( $printed, 'og:title' ) && false === strpos( $printed, 'ld+json' ), 'no title / description / canonical / og:title / JSON-LD from us' );
MrTu_Test::go_to( "/en/ynoindex-en-{$run}/" );
ob_start();
do_action( 'wpseo_head' );
MrTu_Test::same( '', ob_get_clean(), 'nothing printed on a noindex page' );

/*
 * Yoast sitemap alternates
 */
MrTu_Test::section( 'Yoast sitemap filters' );
MrTu_Test::leave_frontend();
Yoast_Compat::reset();
$entry = array(
	'loc' => get_permalink( $pages['en'] ),
	'mod' => '',
);
MrTu_Test::same( $entry, apply_filters( 'wpseo_sitemap_entry', $entry, 'post', get_post( $pages['en'] ) ), 'indexable post entry kept' );
MrTu_Test::same( false, apply_filters( 'wpseo_sitemap_entry', array( 'loc' => get_permalink( $noindex ) ), 'post', get_post( $noindex ) ), 'noindex post dropped from the Yoast sitemap' );
$urlset = apply_filters( 'wpseo_sitemap_urlset', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' );
MrTu_Test::ok( false !== strpos( $urlset, 'xmlns:xhtml="http://www.w3.org/1999/xhtml"' ), 'xhtml namespace declared' );
MrTu_Test::same( $urlset, apply_filters( 'wpseo_sitemap_urlset', $urlset ), 'namespace not declared twice' );
$url_xml = apply_filters( 'wpseo_sitemap_url', "<url>\n\t<loc>" . $entry['loc'] . "</loc>\n</url>\n", $entry );
MrTu_Test::ok( false !== strpos( $url_xml, '<xhtml:link rel="alternate" hreflang="en" href="' . $entry['loc'] . '" />' ) && false !== strpos( $url_xml, 'hreflang="vi-VN"' ), 'alternates injected into the Yoast <url>' );
MrTu_Test::ok( preg_match( '~<xhtml:link[^>]+/>\s*</url>~', $url_xml ) === 1, 'alternates placed before </url>' );
$home_xml = apply_filters( 'wpseo_sitemap_url', '<url><loc>' . home_url( '/en/' ) . '</loc></url>', array( 'loc' => home_url( '/en/' ) ) );
MrTu_Test::ok( false !== strpos( $home_xml, 'hreflang="vi-VN" href="' . home_url( '/' ) . '"' ), 'language home gets alternates' );
$plain_xml = apply_filters( 'wpseo_sitemap_url', '<url><loc>' . get_permalink( $noindex ) . '</loc></url>', array( 'loc' => get_permalink( $noindex ) ) );
MrTu_Test::same( '<url><loc>' . get_permalink( $noindex ) . '</loc></url>', $plain_xml, 'unknown / noindex URL untouched' );
$ids = apply_filters( 'wpseo_exclude_from_sitemap_by_post_ids', array( 5 ) );
MrTu_Test::ok( in_array( $noindex, $ids, true ) && in_array( 5, $ids, true ), 'noindex ids added to Yoast exclusions' );
MrTu_Test::ok( ! apply_filters( 'wpseo_json_ld_output', array( 'x' ), '' ) === false, 'JSON-LD left to Yoast in compat' );
MrTu_Test::same( array( 'p' ), apply_filters( 'wpseo_frontend_presenters', array( 'p' ), null ), 'presenters untouched in compat' );

/*
 * Standalone next to Yoast
 */
MrTu_Test::section( 'Standalone mode with Yoast still active' );
Languages::update_settings( array( 'seo_mode' => 'standalone' ) );
MrTu_Test::same( 'standalone', Seo::mode(), 'configured standalone wins' );
MrTu_Test::ok( ! Yoast_Compat::is_compat(), 'compat callbacks idle' );
MrTu_Test::same( array(), apply_filters( 'wpseo_frontend_presenters', array( 'p' ), null ), 'wpseo_frontend_presenters → empty (Yoast head silenced)' );
MrTu_Test::same( false, apply_filters( 'wpseo_json_ld_output', array( 'x' ), '' ), 'wpseo_json_ld_output → false (Yoast schema silenced)' );
MrTu_Test::same( 'Yoast', apply_filters( 'wpseo_title', 'Yoast', $p ), 'no feeding in standalone' );
MrTu_Test::ok( Seo::sitemap_enabled(), 'our sitemap on' );
MrTu_Test::ok( ! Sitemaps::core_sitemaps( true ), 'core sitemap off' );
MrTu_Test::go_to( "/en/ypage-en-{$run}/" );
MrTu_Test::ok( Seo_Head::is_active(), 'Seo_Head active' );
MrTu_Test::ok( false !== has_action( 'wp_head', array( Seo_Head::class, 'output' ) ), 'Seo_Head hooked to wp_head' );
MrTu_Test::ok( false === has_action( 'wp_head', 'rel_canonical' ), 'core rel_canonical replaced' );
ob_start();
do_action( 'wpseo_head' );
MrTu_Test::same( '', ob_get_clean(), 'nothing printed inside wpseo_head in standalone' );
$head = Seo_Head::render();
MrTu_Test::ok( 1 === substr_count( $head, 'rel="canonical"' ) && 1 === substr_count( $head, 'name="description"' ), 'our head prints canonical + description' );

MrTu_Test::finish();
