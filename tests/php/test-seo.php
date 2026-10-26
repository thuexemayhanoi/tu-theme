<?php
/**
 * Integration tests — SEO module (contract §2.8 + amendments 6–9): title/description resolution,
 * robots, canonical, hreflang alternates, JSON-LD graph, sitemaps and redirects. Standalone mode
 * (no Yoast in this process); the Yoast side lives in test-seo-yoast.php.
 *
 * Run: wp eval-file tests/php/test-seo.php   (or bash tests/run-php.sh <ENV>)
 *
 * @package MrTu\Multilang
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DB.SlowDBQuery -- CLI test file.

require __DIR__ . '/bootstrap.php';

use MrTu\Multilang\Business;
use MrTu\Multilang\Languages;
use MrTu\Multilang\Links;
use MrTu\Multilang\Redirects;
use MrTu\Multilang\Schema;
use MrTu\Multilang\Seo;
use MrTu\Multilang\Seo_Head;
use MrTu\Multilang\Sitemaps;
use MrTu\Multilang\Translations;

if ( ! function_exists( 'mrtu_languages' ) ) {
	echo "FAIL: plugin mr-tu-multilang-seo is not active\nRESULT: 0 passed, 1 failed\n";
	exit( 1 );
}

MrTu_Test::start( 'SEO' );
foreach ( array( 'mrtu_ml_settings', 'show_on_front', 'page_on_front', 'page_for_posts', 'posts_per_page', 'mrtu_roles_map', 'mrtu_term_translations', 'mrtu_redirects', 'mrtu_business', 'mrtu_business_history' ) as $mrtu_option ) {
	MrTu_Test::keep_option( $mrtu_option );
}
$run  = MrTu_Test::$run;
$root = dirname( __DIR__, 2 );
Translations::ensure_language_terms();
Languages::update_settings(
	array(
		'seo_mode'              => 'auto',
		'sitemap'               => 'auto',
		'local_business_schema' => false,
		'faq_schema'            => true,
		'noindex_author'        => true,
		'noindex_date'          => true,
	)
);
// Every language on (ES is switched off later for the "disabled language" cases).
Languages::update_settings( Languages::validate( array( 'languages' => array( 'es' => array( 'enabled' => '1' ) ) ) )['settings'] );

/**
 * Nodes of a given @type in a graph.
 *
 * @param array  $graph Graph.
 * @param string $type  Type.
 * @return array[]
 */
function mrtu_seo_test_nodes( array $graph, $type ) {
	$out = array();
	foreach ( $graph as $node ) {
		$types = isset( $node['@type'] ) ? (array) $node['@type'] : array();
		if ( in_array( $type, $types, true ) ) {
			$out[] = $node;
		}
	}
	return $out;
}

/*
 * Fixtures
 */
$homes = array(
	'vi' => MrTu_Test::post(
		array(
			'post_name'  => "home-vi-{$run}",
			'post_title' => 'Trang chủ test',
			'meta_input' => array(
				'_mrtu_seo_title' => 'Cho thuê xe máy Hà Nội | Mr Tu',
				'_mrtu_seo_desc'  => 'Mô tả trang chủ test.',
			),
		),
		'vi'
	),
	'en' => MrTu_Test::post(
		array(
			'post_name'  => "home-en-{$run}",
			'post_title' => 'Home test',
			'meta_input' => array(
				'_mrtu_seo_title' => 'Motorbike rental in Hanoi | Mr Tu',
				'_mrtu_seo_desc'  => 'Home description test.',
			),
		),
		'en'
	),
	'de' => MrTu_Test::post(
		array(
			'post_name'  => "home-de-{$run}",
			'post_title' => 'Startseite Test',
		),
		'de'
	),
);
mrtu_link_translations( $homes );
$blogs = array(
	'vi' => MrTu_Test::post(
		array(
			'post_name'  => "blog-vi-{$run}",
			'post_title' => 'Cẩm nang',
		),
		'vi'
	),
	'en' => MrTu_Test::post(
		array(
			'post_name'  => "blog-en-{$run}",
			'post_title' => 'Blog',
		),
		'en'
	),
);
mrtu_link_translations( $blogs );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $homes['vi'] );
update_option( 'page_for_posts', $blogs['vi'] );
update_option( 'posts_per_page', 1 );
mrtu_set_post_role( $homes['vi'], 'home' );
mrtu_set_post_role( $blogs['vi'], 'blog' );

$pillar = array(
	'vi' => MrTu_Test::post(
		array(
			'post_name'    => "pillar-vi-{$run}",
			'post_title'   => 'Thuê xe máy Hà Nội',
			'post_excerpt' => 'Trích đoạn tiếng Việt.',
		),
		'vi'
	),
	'en' => MrTu_Test::post(
		array(
			'post_name'  => "pillar-en-{$run}",
			'post_title' => 'Motorbike rental Hanoi',
			'meta_input' => array(
				'_mrtu_seo_title' => 'Rent a motorbike in Hanoi: scooters by the day or month | Mr Tu',
				'_mrtu_seo_desc'  => 'Pillar description test.',
			),
		),
		'en'
	),
	'de' => MrTu_Test::post(
		array(
			'post_name'   => "pillar-de-{$run}",
			'post_title'  => 'Motorrad mieten Hanoi',
			'post_status' => 'draft',
		),
		'de'
	),
	'es' => MrTu_Test::post(
		array(
			'post_name'  => "pillar-es-{$run}",
			'post_title' => 'Alquiler de motos Hanói',
		),
		'es'
	),
);
mrtu_link_translations( $pillar );
mrtu_set_post_role( $pillar['vi'], 'pillar' );

$faq = array(
	'vi' => MrTu_Test::post(
		array(
			'post_name'    => "faq-vi-{$run}",
			'post_title'   => 'Thủ tục thuê xe máy',
			'post_content' => (string) file_get_contents( $root . '/content/vi/guide-faq.html' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		),
		'vi'
	),
	'en' => MrTu_Test::post(
		array(
			'post_name'    => "faq-en-{$run}",
			'post_title'   => 'How to rent a motorbike in Hanoi',
			'post_content' => (string) file_get_contents( $root . '/content/en/guide-faq.html' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		),
		'en'
	),
	'de' => MrTu_Test::post(
		array(
			'post_name'  => "faq-de-{$run}",
			'post_title' => 'Roller mieten Vietnam',
			'meta_input' => array( '_mrtu_noindex' => '1' ),
		),
		'de'
	),
);
mrtu_link_translations( $faq );
mrtu_set_post_role( $faq['vi'], 'guide-faq' );

$hidden = MrTu_Test::post(
	array(
		'post_name'  => "hidden-en-{$run}",
		'post_title' => 'Hidden page',
		'meta_input' => array( '_mrtu_noindex' => '1' ),
	),
	'en'
);
$secret = MrTu_Test::post(
	array(
		'post_name'     => "secret-en-{$run}",
		'post_title'    => 'Secret page',
		'post_password' => 'pw',
	),
	'en'
);
$multi  = MrTu_Test::post(
	array(
		'post_name'    => "multi-en-{$run}",
		'post_title'   => 'Multi page',
		'post_content' => '<!-- wp:paragraph --><p>One</p><!-- /wp:paragraph --><!--nextpage--><!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->',
	),
	'en'
);
$plain  = MrTu_Test::post(
	array(
		'post_name'    => "plain-en-{$run}",
		'post_title'   => 'Plain page',
		'post_content' => '<!-- wp:paragraph --><p>' . str_repeat( 'Plain words about renting a scooter in Hanoi. ', 12 ) . '</p><!-- /wp:paragraph -->',
	),
	'en'
);
$cat_en = MrTu_Test::term( "Tips {$run}", 'category', 'en', array( 'slug' => "tips-en-{$run}" ) );
$cat_vi = MrTu_Test::term( "Mẹo {$run}", 'category', 'vi', array( 'slug' => "tips-vi-{$run}" ) );
mrtu_link_term_translations(
	array(
		'vi' => $cat_vi,
		'en' => $cat_en,
	)
);
$posts = array();
for ( $i = 1; $i <= 2; $i++ ) {
	$posts[ $i ] = MrTu_Test::post(
		array(
			'post_type'     => 'post',
			'post_name'     => "post-{$i}-en-{$run}",
			'post_title'    => "English post {$i}",
			'post_excerpt'  => "Excerpt of post {$i}.",
			'post_category' => array( $cat_en ),
		),
		'en'
	);
}
MrTu_Test::post(
	array(
		'post_type'     => 'post',
		'post_name'     => "post-vi-{$run}",
		'post_title'    => 'Bài viết',
		'post_category' => array( $cat_vi ),
	),
	'vi'
);
// Spanish off from here on: its pillar must vanish from alternates and sitemaps.
Languages::update_settings( Languages::validate( array( 'languages' => array( 'es' => array( 'enabled' => '0' ) ) ) )['settings'] );
Sitemaps::flush();

$site = Seo::site_name();
$sep  = Seo::SEPARATOR;

/*
 * Mode
 */
MrTu_Test::section( 'Mode' );
MrTu_Test::ok( ! Seo::yoast_active(), 'no Yoast in this process' );
MrTu_Test::same( 'standalone', Seo::mode(), 'mode = standalone without Yoast' );
MrTu_Test::same( 'standalone', mrtu_seo_mode(), 'mrtu_seo_mode()' );
MrTu_Test::ok( mrtu_is_seo_standalone(), 'mrtu_is_seo_standalone()' );
MrTu_Test::ok( Seo::sitemap_enabled(), 'sitemap auto = on in standalone' );
MrTu_Test::ok( '' !== $site, 'site name resolved' );

/*
 * Title / description
 */
MrTu_Test::section( 'Title and description resolution' );
MrTu_Test::same( 'Rent a motorbike in Hanoi: scooters by the day or month | Mr Tu', Seo::title( $pillar['en'] ), 'custom SEO title used as is' );
MrTu_Test::same( 'Pillar description test.', Seo::description( $pillar['en'] ), 'custom description used' );
MrTu_Test::same( 'Thuê xe máy Hà Nội' . $sep . $site, Seo::title( $pillar['vi'] ), 'fallback title = post title | site' );
MrTu_Test::same( 'Trích đoạn tiếng Việt.', Seo::description( $pillar['vi'] ), 'fallback description = excerpt' );
$plain_desc = Seo::description( $plain );
MrTu_Test::ok( '' !== $plain_desc && 0 === strpos( $plain_desc, 'Plain words' ) && mb_strlen( $plain_desc ) <= Seo::DESC_LENGTH, 'fallback description from content, trimmed', $plain_desc );
MrTu_Test::same( 'Motorbike rental in Hanoi | Mr Tu', Seo::title( $homes['en'] ), 'EN home custom title' );
MrTu_Test::same( $site, Seo::brand( '' ), 'brand("") = site name' );
MrTu_Test::same( 'X' . $sep . $site, Seo::brand( 'X' ), 'brand appends site' );
MrTu_Test::same( 'X ' . $site, Seo::brand( 'X ' . $site ), 'brand not doubled' );
MrTu_Test::same( 'Tips ' . $run . $sep . $site, Seo::title( get_term( $cat_en ) ), 'term title fallback' );
update_term_meta( $cat_en, '_mrtu_seo_title', 'Riding tips | Mr Tu' );
update_term_meta( $cat_en, '_mrtu_seo_desc', 'Term description test.' );
MrTu_Test::same( 'Riding tips | Mr Tu', Seo::title( get_term( $cat_en ) ), 'term custom title' );
MrTu_Test::same( 'Term description test.', Seo::description( get_term( $cat_en ) ), 'term custom description' );
MrTu_Test::same( '', Seo::description( get_term( $cat_vi ) ), 'empty term description stays empty (no empty tag)' );

MrTu_Test::go_to( '/en/' );
MrTu_Test::same( 'front', Seo::context()['type'], '/en/ is a front request' );
MrTu_Test::same( 'Motorbike rental in Hanoi | Mr Tu', Seo::title(), '/en/ title from EN home meta' );
MrTu_Test::same( 'Home description test.', Seo::description(), '/en/ description from EN home meta' );
MrTu_Test::go_to( '/de/' );
MrTu_Test::same( 'Startseite Test' . $sep . $site, Seo::title(), '/de/ title falls back to page title | site (no tagline)' );
MrTu_Test::go_to( "/en/blog-en-{$run}/page/2/" );
MrTu_Test::same( 'home', Seo::context()['type'], 'posts page detected' );
MrTu_Test::same( 2, Seo::context()['paged'], 'page 2 detected' );
MrTu_Test::ok( false !== strpos( Seo::title(), '2' ), 'paged title mentions the page number', Seo::title() );

/*
 * Robots
 */
MrTu_Test::section( 'Robots' );
MrTu_Test::go_to( '/en/?s=xe' );
MrTu_Test::same( 'search', Seo::context()['type'], 'search detected' );
MrTu_Test::ok( Seo::is_noindex(), 'search is noindex' );
MrTu_Test::same(
	array(
		'noindex' => true,
		'follow'  => true,
	),
	Seo::robots(),
	'robots() = noindex,follow on search'
);
MrTu_Test::same( '', Seo::canonical(), 'no canonical on search' );
MrTu_Test::same( array(), Seo::head_alternates(), 'no hreflang on search' );
$robots = Seo_Head::robots( array( 'max-image-preview' => 'large' ) );
MrTu_Test::ok( ! empty( $robots['noindex'] ) && ! empty( $robots['follow'] ) && ! isset( $robots['index'] ), 'wp_robots filter adds noindex,follow' );

MrTu_Test::go_to( "/en/does-not-exist-{$run}/" );
MrTu_Test::ok( is_404(), '404 request' );
MrTu_Test::same( '404', Seo::context()['type'], '404 detected' );
MrTu_Test::ok( Seo::is_noindex(), '404 is noindex' );
MrTu_Test::same( '', Seo::canonical(), 'no canonical on 404' );
$head = Seo_Head::render();
MrTu_Test::ok( false === strpos( $head, 'rel="canonical"' ) && false === strpos( $head, 'hreflang=' ), 'head on 404 has no canonical / hreflang' );
MrTu_Test::ok( false !== strpos( $head, 'og:locale" content="en_US"' ), 'head on /en/ 404 still carries og:locale en_US' );

MrTu_Test::go_to( "/en/hidden-en-{$run}/" );
MrTu_Test::ok( is_singular(), 'noindex page is a singular request' );
MrTu_Test::ok( Seo::is_noindex(), '_mrtu_noindex page is noindex' );
MrTu_Test::same( array(), Seo::head_alternates(), 'no hreflang on a noindex page' );
MrTu_Test::ok( ! Seo::post_is_indexable( $hidden ), 'noindex page is not indexable' );
MrTu_Test::ok( ! Seo::post_is_indexable( $secret ), 'password page is not indexable' );
MrTu_Test::ok( ! Seo::post_is_indexable( $pillar['de'] ), 'draft is not indexable' );
MrTu_Test::ok( ! Seo::post_is_indexable( $pillar['es'] ), 'page in a disabled language is not indexable' );
MrTu_Test::ok( Seo::post_is_indexable( $pillar['en'] ), 'published EN page is indexable' );

MrTu_Test::go_to( "/en/pillar-en-{$run}/" );
MrTu_Test::ok( ! Seo::is_noindex(), 'normal page is index' );
MrTu_Test::same( array(), Seo::robots(), 'robots() empty = index' );

$ctx_author = array(
	'type'  => 'author',
	'post'  => null,
	'term'  => null,
	'lang'  => 'en',
	'paged' => 1,
	'page'  => 1,
);
$ctx_date   = array_merge( $ctx_author, array( 'type' => 'date' ) );
MrTu_Test::ok( Seo::is_noindex( $ctx_author ), 'author archive noindex (setting on)' );
MrTu_Test::ok( Seo::is_noindex( $ctx_date ), 'date archive noindex (setting on)' );
Languages::update_settings(
	array(
		'noindex_author' => false,
		'noindex_date'   => false,
	)
);
MrTu_Test::ok( ! Seo::is_noindex( $ctx_author ), 'author archive index when setting off' );
MrTu_Test::ok( ! Seo::is_noindex( $ctx_date ), 'date archive index when setting off' );
Languages::update_settings(
	array(
		'noindex_author' => true,
		'noindex_date'   => true,
	)
);
MrTu_Test::ok( Seo::is_noindex( array_merge( $ctx_author, array( 'type' => 'attachment' ) ) ), 'attachments noindex' );
MrTu_Test::ok(
	Seo::is_noindex(
		array_merge(
			$ctx_author,
			array(
				'type' => 'term',
				'term' => get_term( $cat_vi ),
			)
		)
	) === ( (int) get_term( $cat_vi )->count < 1 ),
	'term archive noindex only when empty'
);

/*
 * Canonical
 */
MrTu_Test::section( 'Canonical' );
MrTu_Test::go_to( '/' );
MrTu_Test::same( home_url( '/' ), Seo::canonical(), 'VI home canonical = /' );
MrTu_Test::go_to( '/en/' );
MrTu_Test::same( home_url( '/en/' ), Seo::canonical(), 'EN home canonical = /en/' );
MrTu_Test::same( home_url( '/en/' ), mrtu_canonical_url(), 'mrtu_canonical_url()' );
MrTu_Test::same( home_url( '/en/' ), Seo::canonical( $homes['en'] ), 'EN home object canonical = language home' );
MrTu_Test::same( home_url( '/en/page/2/' ), Seo::paged_url( home_url( '/en/' ), 2 ), 'paged_url adds /page/2/' );
MrTu_Test::same( home_url( '/en/' ), Seo::paged_url( home_url( '/en/' ), 1 ), 'paged_url leaves page 1' );
MrTu_Test::go_to( "/en/pillar-en-{$run}/" );
MrTu_Test::same( get_permalink( $pillar['en'] ), Seo::canonical(), 'singular canonical = permalink' );
MrTu_Test::ok( 0 === strpos( Seo::canonical(), home_url( '/en/' ) ), 'singular canonical carries the language prefix' );
MrTu_Test::go_to( "/en/multi-en-{$run}/2/" );
MrTu_Test::same( 2, Seo::context()['page'], 'paginated content page 2' );
MrTu_Test::same( trailingslashit( get_permalink( $multi ) ) . '2/', Seo::canonical(), 'paginated content canonical = permalink/2/' );
MrTu_Test::go_to( "/en/blog-en-{$run}/" );
MrTu_Test::same( get_permalink( $blogs['en'] ), Seo::canonical(), 'posts page canonical = its permalink' );
MrTu_Test::go_to( "/en/blog-en-{$run}/page/2/" );
MrTu_Test::same( trailingslashit( get_permalink( $blogs['en'] ) ) . 'page/2/', Seo::canonical(), 'posts page 2 canonical = permalink/page/2/' );
$term_link = get_term_link( $cat_en );
MrTu_Test::go_to( wp_make_link_relative( $term_link ) );
MrTu_Test::same( 'term', Seo::context()['type'], 'term archive detected' );
MrTu_Test::same( $term_link, Seo::canonical(), 'term canonical = term link' );
MrTu_Test::go_to( wp_make_link_relative( $term_link ) . 'page/2/' );
MrTu_Test::same( trailingslashit( $term_link ) . 'page/2/', Seo::canonical(), 'term page 2 canonical' );

/*
 * Alternates
 */
MrTu_Test::section( 'Alternates (hreflang)' );
MrTu_Test::go_to( "/en/pillar-en-{$run}/" );
$alt = Seo::head_alternates();
MrTu_Test::same( get_permalink( $pillar['en'] ), isset( $alt['en'] ) ? $alt['en'] : null, 'self included (en)' );
MrTu_Test::same( get_permalink( $pillar['vi'] ), isset( $alt['vi-VN'] ) ? $alt['vi-VN'] : null, 'vi-VN alternate (default hreflang vi-VN)' );
MrTu_Test::ok( ! isset( $alt['de'] ), 'draft translation excluded' );
MrTu_Test::ok( ! isset( $alt['es'] ), 'disabled language excluded' );
MrTu_Test::same( get_permalink( $pillar['en'] ), isset( $alt['x-default'] ) ? $alt['x-default'] : null, 'x-default = EN alternate' );
MrTu_Test::same( 3, count( $alt ), 'exactly vi-VN + en + x-default' );
MrTu_Test::same( $alt, Seo::usable_alternates( Seo::object_alternates( 'post', $pillar['en'] ) ), 'head and object alternates agree (same function)' );

MrTu_Test::go_to( "/en/faq-en-{$run}/" );
$alt = Seo::head_alternates();
MrTu_Test::ok( isset( $alt['en'], $alt['vi-VN'] ) && ! isset( $alt['de'] ), 'noindex translation excluded from alternates' );
MrTu_Test::same( array(), Seo::object_alternates( 'post', $faq['de'] ), 'noindex object has no alternates' );
MrTu_Test::same( array(), Seo::object_alternates( 'post', $hidden ), 'untranslated noindex page has no alternates' );
MrTu_Test::same( array(), Seo::usable_alternates( array( 'en' => 'x' ) ), 'single language → nothing (needs ≥ 2)' );
MrTu_Test::same(
	array(),
	Seo::usable_alternates(
		array(
			'en'        => 'x',
			'x-default' => 'x',
		)
	),
	'x-default alone does not count as a second language'
);
$plain_alt = Seo::object_alternates( 'post', $plain );
MrTu_Test::same( array( 'en', 'x-default' ), array_keys( $plain_alt ), 'untranslated page resolves to self (+ x-default) only' );
MrTu_Test::same( array(), Seo::usable_alternates( $plain_alt ), 'page without translations prints no hreflang' );

MrTu_Test::go_to( '/en/' );
$alt = Seo::head_alternates();
MrTu_Test::same( home_url( '/' ), isset( $alt['vi-VN'] ) ? $alt['vi-VN'] : null, 'home vi-VN → /' );
MrTu_Test::same( home_url( '/en/' ), isset( $alt['en'] ) ? $alt['en'] : null, 'home en → /en/' );
MrTu_Test::same( home_url( '/de/' ), isset( $alt['de'] ) ? $alt['de'] : null, 'home de → /de/' );
MrTu_Test::ok( ! isset( $alt['es'] ) && ! isset( $alt['fr'] ), 'homes without a published page / disabled language excluded' );
MrTu_Test::same( home_url( '/en/' ), isset( $alt['x-default'] ) ? $alt['x-default'] : null, 'home x-default → /en/' );
MrTu_Test::same( $alt, Seo::usable_alternates( Seo::object_alternates( 'home', 'en' ) ), 'home alternates = object_alternates(home)' );
$head = Seo_Head::render();
MrTu_Test::same( 1, substr_count( $head, 'rel="canonical"' ), 'head: one canonical' );
MrTu_Test::same( 1, substr_count( $head, 'name="description"' ), 'head: one description' );
MrTu_Test::same( 4, substr_count( $head, 'hreflang="' ), 'head: 4 hreflang links' );
MrTu_Test::same( 2, substr_count( $head, 'og:locale:alternate' ), 'head: og:locale:alternate for the other two languages' );
MrTu_Test::ok( false !== strpos( $head, '<meta property="og:locale" content="en_US">' ), 'head: og:locale en_US' );

$term_alt = Seo::object_alternates( 'term', $cat_en );
MrTu_Test::same( $term_link, isset( $term_alt['en'] ) ? $term_alt['en'] : null, 'term alternates: self' );
MrTu_Test::same( get_term_link( $cat_vi ), isset( $term_alt['vi-VN'] ) ? $term_alt['vi-VN'] : null, 'term alternates: VI translation' );

/*
 * Schema
 */
MrTu_Test::section( 'Schema (JSON-LD)' );
MrTu_Test::go_to( '/en/' );
$graph = Schema::graph();
$sites = mrtu_seo_test_nodes( $graph, 'WebSite' );
MrTu_Test::same( 1, count( $sites ), 'exactly one WebSite node' );
MrTu_Test::same( home_url( '/' ), isset( $sites[0]['url'] ) ? $sites[0]['url'] : null, 'WebSite url = root' );
MrTu_Test::same( home_url( '/' ) . '#website', isset( $sites[0]['@id'] ) ? $sites[0]['@id'] : null, 'WebSite @id = root#website' );
MrTu_Test::ok( isset( $sites[0]['inLanguage'] ) && in_array( 'vi-VN', (array) $sites[0]['inLanguage'], true ) && in_array( 'en', (array) $sites[0]['inLanguage'], true ) && ! in_array( 'es', (array) $sites[0]['inLanguage'], true ), 'WebSite inLanguage = enabled languages' );
MrTu_Test::same( 1, count( mrtu_seo_test_nodes( $graph, 'Organization' ) ), 'one Organization node' );
$pages = mrtu_seo_test_nodes( $graph, 'WebPage' );
MrTu_Test::same( 1, count( $pages ), 'one WebPage node' );
MrTu_Test::same( 'en', isset( $pages[0]['inLanguage'] ) ? $pages[0]['inLanguage'] : null, 'WebPage inLanguage en on /en/' );
MrTu_Test::same( home_url( '/en/' ), isset( $pages[0]['url'] ) ? $pages[0]['url'] : null, 'WebPage url = canonical' );
MrTu_Test::same( array(), mrtu_seo_test_nodes( $graph, 'AutoRental' ), 'AutoRental absent (setting off, NAP unverified)' );
MrTu_Test::same( array(), mrtu_seo_test_nodes( $graph, 'FAQPage' ), 'no FAQPage on the home' );
$json = Schema::json();
MrTu_Test::ok( is_array( json_decode( $json, true ) ) && false === strpos( $json, '</script' ), 'JSON-LD is valid JSON with no closing script tag' );
MrTu_Test::same( $graph, mrtu_schema_graph(), 'mrtu_schema_graph()' );

MrTu_Test::go_to( '/' );
$pages = mrtu_seo_test_nodes( Schema::graph(), 'WebPage' );
MrTu_Test::same( 'vi-VN', isset( $pages[0]['inLanguage'] ) ? $pages[0]['inLanguage'] : null, 'WebPage inLanguage vi-VN on /' );
MrTu_Test::go_to( '/de/' );
$pages = mrtu_seo_test_nodes( Schema::graph(), 'WebPage' );
MrTu_Test::same( 'de', isset( $pages[0]['inLanguage'] ) ? $pages[0]['inLanguage'] : null, 'WebPage inLanguage de on /de/' );

MrTu_Test::go_to( "/en/faq-en-{$run}/" );
$graph = Schema::graph();
$faqs  = mrtu_seo_test_nodes( $graph, 'FAQPage' );
MrTu_Test::same( 1, count( $faqs ), 'FAQPage on the guide-faq page' );
MrTu_Test::ok( isset( $faqs[0]['mainEntity'] ) && count( $faqs[0]['mainEntity'] ) >= 3 && 'Question' === $faqs[0]['mainEntity'][0]['@type'], 'FAQPage has Question entities from core/details' );
MrTu_Test::same( 1, count( mrtu_seo_test_nodes( $graph, 'WebSite' ) ), 'still one WebSite node' );
MrTu_Test::ok( isset( $faqs[0]['breadcrumb']['@id'] ) && 1 === count( mrtu_seo_test_nodes( $graph, 'BreadcrumbList' ) ), 'BreadcrumbList linked from the WebPage' );
MrTu_Test::go_to( "/en/pillar-en-{$run}/" );
MrTu_Test::same( array(), mrtu_seo_test_nodes( Schema::graph(), 'FAQPage' ), 'no FAQPage on the pillar (role ≠ guide-faq)' );
Languages::update_settings( array( 'faq_schema' => false ) );
MrTu_Test::go_to( "/en/faq-en-{$run}/" );
MrTu_Test::same( array(), mrtu_seo_test_nodes( Schema::graph(), 'FAQPage' ), 'FAQPage off when the setting is off' );
Languages::update_settings( array( 'faq_schema' => true ) );
MrTu_Test::go_to( "/en/post-1-en-{$run}/" );
$graph = Schema::graph();
MrTu_Test::same( 1, count( mrtu_seo_test_nodes( $graph, 'BlogPosting' ) ), 'BlogPosting on a post' );
MrTu_Test::go_to( "/en/hidden-en-{$run}/" );
MrTu_Test::same( array(), Schema::graph(), 'no graph on a noindex page' );
MrTu_Test::go_to( '/en/?s=xe' );
MrTu_Test::same( array(), Schema::graph(), 'no graph on search' );

// AutoRental: setting on but NAP unverified → absent; verified → present once.
Languages::update_settings( array( 'local_business_schema' => true ) );
MrTu_Test::go_to( '/en/' );
MrTu_Test::ok( ! Seo::local_business_allowed(), 'NAP not verified in the seed store' );
MrTu_Test::same( array(), mrtu_seo_test_nodes( Schema::graph(), 'AutoRental' ), 'AutoRental absent while NAP unverified even with the setting on' );
$business_before = get_option( 'mrtu_business' );
$business        = Business::get_business();
$business['address']['verified'] = true;
$business['phone']['verified']   = true;
$business['hours']               = array(
	'days'     => array( 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su' ),
	'opens'    => '08:00',
	'closes'   => '20:00',
	'verified' => true,
	'note'     => '',
);
$saved = Business::save_business( $business, array( 'snapshot' => false ) );
MrTu_Test::ok( ! is_wp_error( $saved ), 'NAP marked verified for the test', is_wp_error( $saved ) ? wp_json_encode( $saved->get_error_data() ) : '' );
Business::flush_cache();
MrTu_Test::ok( Seo::local_business_allowed(), 'local business allowed once address, phone and hours are verified' );
MrTu_Test::go_to( '/en/' );
$graph = Schema::graph();
$biz   = mrtu_seo_test_nodes( $graph, 'AutoRental' );
MrTu_Test::same( 1, count( $biz ), 'AutoRental present once when verified + setting on' );
MrTu_Test::same( '+84816659199', isset( $biz[0]['telephone'] ) ? $biz[0]['telephone'] : null, 'AutoRental telephone = verified Mr Tu phone' );
MrTu_Test::ok( ! isset( $biz[0]['aggregateRating'] ) && ! isset( $biz[0]['review'] ), 'no ratings / reviews' );
$pages = mrtu_seo_test_nodes( $graph, 'WebPage' );
MrTu_Test::ok( isset( $pages[0]['about']['@id'] ) && $pages[0]['about']['@id'] === $biz[0]['@id'], 'WebPage.about → AutoRental' );
Languages::update_settings( array( 'local_business_schema' => false ) );
MrTu_Test::go_to( '/en/' );
MrTu_Test::same( array(), mrtu_seo_test_nodes( Schema::graph(), 'AutoRental' ), 'AutoRental absent again when the setting is off' );
update_option( 'mrtu_business', $business_before, true );
Business::flush_cache();

/*
 * Sitemaps
 */
MrTu_Test::section( 'Sitemaps' );
MrTu_Test::leave_frontend();
Sitemaps::flush();
MrTu_Test::same( home_url( '/mrtu-sitemap-index.xml' ), Sitemaps::url(), 'index URL' );
MrTu_Test::same( home_url( '/mrtu-sitemap-en.xml' ), Sitemaps::url( 'en' ), 'language URL' );
$index = Sitemaps::index_xml();
$sx    = simplexml_load_string( $index );
MrTu_Test::ok( $sx instanceof SimpleXMLElement && 'sitemapindex' === $sx->getName(), 'index is valid XML' );
$locs = array();
foreach ( $sx->sitemap as $entry ) {
	$locs[] = (string) $entry->loc;
}
MrTu_Test::ok( in_array( Sitemaps::url( 'vi' ), $locs, true ) && in_array( Sitemaps::url( 'en' ), $locs, true ) && in_array( Sitemaps::url( 'de' ), $locs, true ), 'index lists vi, en, de' );
MrTu_Test::ok( ! in_array( Sitemaps::url( 'es' ), $locs, true ), 'index skips the disabled language' );
MrTu_Test::same( '', Sitemaps::language_xml( 'es' ), 'disabled language sitemap empty' );
MrTu_Test::same( '', Sitemaps::language_xml( 'xx' ), 'unknown language sitemap empty' );

$xml = Sitemaps::language_xml( 'en' );
$sx  = simplexml_load_string( $xml );
MrTu_Test::ok( $sx instanceof SimpleXMLElement && 'urlset' === $sx->getName(), 'EN sitemap is valid XML' );
$sx->registerXPathNamespace( 's', 'http://www.sitemaps.org/schemas/sitemap/0.9' );
$sx->registerXPathNamespace( 'xhtml', 'http://www.w3.org/1999/xhtml' );
$locs = array_map( 'strval', $sx->xpath( '//s:url/s:loc' ) );
MrTu_Test::ok( in_array( get_permalink( $pillar['en'] ), $locs, true ), 'EN sitemap has the pillar' );
MrTu_Test::ok( in_array( home_url( '/en/' ), $locs, true ), 'EN sitemap has the language home' );
MrTu_Test::ok( in_array( get_permalink( $posts[1] ), $locs, true ), 'EN sitemap has posts' );
MrTu_Test::ok( in_array( $term_link, $locs, true ), 'EN sitemap has the non-empty category' );
MrTu_Test::ok( ! in_array( get_permalink( $hidden ), $locs, true ), 'noindex page excluded' );
MrTu_Test::ok( ! in_array( get_permalink( $secret ), $locs, true ), 'password page excluded' );
MrTu_Test::ok( ! in_array( get_permalink( $pillar['vi'] ), $locs, true ), 'VI page not in the EN sitemap' );
MrTu_Test::same( count( $locs ), count( array_unique( $locs ) ), 'no duplicate URLs' );
$links = $sx->xpath( '//s:url[s:loc="' . get_permalink( $pillar['en'] ) . '"]/xhtml:link' );
$hrefs = array();
foreach ( (array) $links as $link ) {
	$hrefs[ (string) $link['hreflang'] ] = (string) $link['href'];
}
MrTu_Test::same( Seo::usable_alternates( Seo::object_alternates( 'post', $pillar['en'] ) ), $hrefs, 'sitemap alternates = head alternates' );
MrTu_Test::ok( isset( $hrefs['vi-VN'], $hrefs['en'], $hrefs['x-default'] ) && ! isset( $hrefs['de'] ), 'pillar alternates: vi-VN, en, x-default; draft excluded' );
$mods = $sx->xpath( '//s:url[s:loc="' . get_permalink( $pillar['en'] ) . '"]/s:lastmod' );
MrTu_Test::ok( ! empty( $mods ) && Sitemaps::iso_utc( get_post( $pillar['en'] )->post_modified_gmt ) === (string) $mods[0], 'lastmod = post_modified_gmt (ISO 8601 UTC)' );
MrTu_Test::same( '', Sitemaps::iso_utc( '0000-00-00 00:00:00' ), 'iso_utc of zero date = empty' );

$de = simplexml_load_string( Sitemaps::language_xml( 'de' ) );
$de->registerXPathNamespace( 's', 'http://www.sitemaps.org/schemas/sitemap/0.9' );
$de_locs = array_map( 'strval', $de->xpath( '//s:url/s:loc' ) );
MrTu_Test::ok( ! in_array( get_permalink( $pillar['de'] ), $de_locs, true ), 'draft excluded from the DE sitemap' );
MrTu_Test::ok( ! in_array( get_permalink( $faq['de'] ), $de_locs, true ), 'noindex DE page excluded' );
MrTu_Test::ok( in_array( home_url( '/de/' ), $de_locs, true ), 'DE home present' );

// Cache + filter.
$cached = get_transient( 'mrtu_sitemap_en' );
MrTu_Test::ok( is_array( $cached ) && isset( $cached['xml'] ), 'EN sitemap cached in a transient' );
add_filter(
	'mrtu_sitemap_entries',
	static function ( $entries, $lang ) {
		if ( 'en' === $lang ) {
			$entries[] = array(
				'loc'        => home_url( '/en/extra-entry/' ),
				'lastmod'    => '',
				'alternates' => array(),
			);
			$entries[] = array( 'broken' => true );
		}
		return $entries;
	},
	10,
	2
);
Sitemaps::flush();
MrTu_Test::ok( false === get_transient( 'mrtu_sitemap_en' ), 'flush() drops the transient' );
MrTu_Test::ok( false !== strpos( Sitemaps::language_xml( 'en' ), '<loc>' . home_url( '/en/extra-entry/' ) . '</loc>' ), 'mrtu_sitemap_entries filter adds an entry (malformed one dropped)' );
remove_all_filters( 'mrtu_sitemap_entries' );
Sitemaps::flush();
wp_update_post(
	array(
		'ID'         => $plain,
		'post_title' => 'Plain page edited',
	)
);
MrTu_Test::ok( false === get_transient( 'mrtu_sitemap_en' ), 'save_post flushes the cache' );
Sitemaps::language_data( 'en' );
update_post_meta( $plain, '_mrtu_noindex', '1' );
MrTu_Test::ok( false === get_transient( 'mrtu_sitemap_en' ), 'noindex meta change flushes the cache' );
delete_post_meta( $plain, '_mrtu_noindex' );

MrTu_Test::ok( false !== strpos( Sitemaps::robots_txt( "User-agent: *\nDisallow: /wp-admin/\n", true ), 'Sitemap: ' . Sitemaps::url() ), 'robots.txt gets the Sitemap line' );
MrTu_Test::same( 'X', Sitemaps::robots_txt( 'X', false ), 'no Sitemap line on a private site' );
MrTu_Test::ok( ! Sitemaps::core_sitemaps( true ), 'core sitemaps off in standalone' );
Languages::update_settings( array( 'sitemap' => 'off' ) );
MrTu_Test::ok( ! Seo::sitemap_enabled(), 'sitemap off by setting' );
MrTu_Test::same( 'X', Sitemaps::robots_txt( 'X', true ), 'no Sitemap line when off' );
MrTu_Test::ok( Sitemaps::core_sitemaps( true ), 'core sitemaps untouched when ours are off' );
Languages::update_settings( array( 'sitemap' => 'auto' ) );
$wp_rules = get_option( 'rewrite_rules' );
MrTu_Test::ok( is_array( $wp_rules ) && isset( $wp_rules['^mrtu\-sitemap\-([a-z]{2,5})\.xml$'] ), 'sitemap rewrite rule registered' );
MrTu_Test::ok( in_array( 'mrtu_sitemap', Sitemaps::add_query_var( array() ), true ), 'query var registered' );

/*
 * Redirects
 */
MrTu_Test::section( 'Redirects' );
delete_option( 'mrtu_redirects' );
MrTu_Test::same( array(), Redirects::all(), 'empty map' );
MrTu_Test::same( true, Redirects::add( "/old-a-{$run}/", "/en/pillar-en-{$run}/" ), 'add() with paths' );
$entry = Redirects::match( "/old-a-{$run}" );
MrTu_Test::ok( is_array( $entry ) && get_permalink( $pillar['en'] ) === $entry['to'] && 301 === $entry['code'] && 0 === $entry['hits'], 'match() without trailing slash → absolute same-site target, 301, 0 hits' );
MrTu_Test::same( "/old-a-{$run}/", $entry['from'], 'match() reports the stored key' );
MrTu_Test::ok( Redirects::hit( "/old-a-{$run}/" ) && 1 === Redirects::all()[ "/old-a-{$run}/" ]['hits'], 'hit() increments the counter' );
MrTu_Test::ok( ! Redirects::hit( '/nope/' ), 'hit() on an unknown path = false' );
MrTu_Test::same( true, Redirects::add( "/old-b-{$run}/", "/old-a-{$run}/", 302 ), 'add() chain B → A' );
MrTu_Test::same( get_permalink( $pillar['en'] ), Redirects::all()[ "/old-b-{$run}/" ]['to'], 'chain collapsed: B → pillar directly' );
MrTu_Test::same( 302, Redirects::all()[ "/old-b-{$run}/" ]['code'], '302 kept' );
$loop = Redirects::add( "/en/pillar-en-{$run}/", "/old-b-{$run}/" );
MrTu_Test::ok( is_wp_error( $loop ) && 'mrtu_redirect_loop' === $loop->get_error_code(), 'loop refused (target chain ends at the source)' );
MrTu_Test::ok( is_wp_error( Redirects::add( "/old-c-{$run}/", 'https://evil.example/x' ) ), 'foreign host refused' );
MrTu_Test::ok( is_wp_error( Redirects::add( '/', '/en/' ) ), 'root as source refused' );
MrTu_Test::ok( is_wp_error( Redirects::add( '', '/en/' ) ), 'empty source refused' );
MrTu_Test::same( true, Redirects::add( home_url( "/old-d-{$run}/?x=1" ), home_url( '/en/' ) ), 'add() with full URLs (query dropped)' );
MrTu_Test::ok( isset( Redirects::all()[ "/old-d-{$run}/" ] ), 'URL source normalized to a path' );
MrTu_Test::same( '/a/b/', Redirects::normalize_path( '/a//b' ), 'normalize_path collapses slashes and adds the trailing slash' );
MrTu_Test::same( '', Redirects::normalize_path( '//a/b' ), 'protocol-relative URL on another host rejected' );
MrTu_Test::same( '/a/file.html', Redirects::normalize_path( '/a/file.html' ), 'normalize_path keeps file paths' );
MrTu_Test::same( '', Redirects::normalize_path( 'https://evil.example/x' ), 'normalize_path rejects other hosts' );
// Re-adding A repoints B (which pointed at A's target) safely and keeps hits.
MrTu_Test::same( true, Redirects::add( "/old-a-{$run}/", '/en/' ), 'replace A' );
MrTu_Test::same( 1, Redirects::all()[ "/old-a-{$run}/" ]['hits'], 'hits preserved on replace' );
MrTu_Test::ok( Redirects::remove( "/old-b-{$run}/" ) && ! Redirects::remove( "/old-b-{$run}/" ), 'remove() once' );

// Auto-create on slug change of a published page (same language), children follow.
$parent = MrTu_Test::post(
	array(
		'post_name'  => "parent-en-{$run}",
		'post_title' => 'Parent',
	),
	'en'
);
$child  = MrTu_Test::post(
	array(
		'post_name'   => "child-en-{$run}",
		'post_title'  => 'Child',
		'post_parent' => $parent,
	),
	'en'
);
$old_parent = get_permalink( $parent );
$old_child  = get_permalink( $child );
wp_update_post(
	array(
		'ID'        => $parent,
		'post_name' => "parent-moved-{$run}",
	)
);
$new_parent = get_permalink( $parent );
$new_child  = get_permalink( $child );
MrTu_Test::ok( $old_parent !== $new_parent && false !== strpos( $new_parent, "parent-moved-{$run}" ), 'slug changed' );
$entry = Redirects::match( wp_parse_url( $old_parent, PHP_URL_PATH ) );
MrTu_Test::ok( is_array( $entry ) && $new_parent === $entry['to'] && 301 === $entry['code'], 'auto 301 from the old page URL to the new one' );
$entry = Redirects::match( wp_parse_url( $old_child, PHP_URL_PATH ) );
MrTu_Test::ok( is_array( $entry ) && $new_child === $entry['to'], 'child page redirected too' );
MrTu_Test::ok( null === Redirects::match( wp_parse_url( $new_parent, PHP_URL_PATH ) ), 'new URL itself never redirects' );
wp_update_post(
	array(
		'ID'        => $parent,
		'post_name' => "parent-en-{$run}",
	)
);
MrTu_Test::ok( null === Redirects::match( wp_parse_url( $old_parent, PHP_URL_PATH ) ), 'moving back removes the stale entry for the live URL' );
$entry = Redirects::match( wp_parse_url( $new_parent, PHP_URL_PATH ) );
MrTu_Test::ok( is_array( $entry ) && get_permalink( $parent ) === $entry['to'], 'and redirects the intermediate slug to the restored one' );
$draft_page = MrTu_Test::post(
	array(
		'post_name'   => "draft-en-{$run}",
		'post_title'  => 'Draft',
		'post_status' => 'draft',
	),
	'en'
);
$count      = count( Redirects::all() );
wp_update_post(
	array(
		'ID'        => $draft_page,
		'post_name' => "draft-moved-{$run}",
	)
);
MrTu_Test::same( $count, count( Redirects::all() ), 'no auto redirect for drafts' );

// Cap.
$map = array();
for ( $i = 0; $i < Redirects::MAX + 20; $i++ ) {
	$map[ "/cap-{$i}/" ] = array(
		'to'      => home_url( '/' ),
		'code'    => 301,
		'hits'    => 0,
		'created' => 1000 + $i,
	);
}
update_option( 'mrtu_redirects', $map, true );
Redirects::add( "/cap-new-{$run}/", '/en/' );
MrTu_Test::same( Redirects::MAX, count( Redirects::all() ), 'map capped at 500 entries (oldest dropped)' );
MrTu_Test::ok( isset( Redirects::all()[ "/cap-new-{$run}/" ] ) && ! isset( Redirects::all()['/cap-0/'] ), 'newest kept, oldest dropped' );
delete_option( 'mrtu_redirects' );

MrTu_Test::finish();
