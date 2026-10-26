<?php
/**
 * Core i18n integration tests: languages, translations, routing, links, queries, switcher,
 * alternates, roles, breadcrumbs and the admin-side translation flows.
 *
 * Run: wp eval-file tests/php/test-core.php   (or tests/run-php.sh <env-dir>)
 *
 * @package MrTu\Multilang\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DB.SlowDBQuery -- CLI test file.

require __DIR__ . '/bootstrap.php';

use MrTu\Multilang\Languages;
use MrTu\Multilang\Router;
use MrTu\Multilang\Translations;

if ( ! function_exists( 'mrtu_languages' ) ) {
	echo "FAIL: plugin mr-tu-multilang-seo is not active\nRESULT: 0 passed, 1 failed\n";
	exit( 1 );
}

MrTu_Test::start( 'Core i18n' );
foreach ( array( 'mrtu_ml_settings', 'show_on_front', 'page_on_front', 'page_for_posts', 'mrtu_roles_map', 'mrtu_term_translations' ) as $mrtu_option ) {
	MrTu_Test::keep_option( $mrtu_option );
}
$run = MrTu_Test::$run;
Translations::ensure_language_terms();

/*
 * Language registry & settings
 */
MrTu_Test::section( 'Language registry & settings' );

$langs = mrtu_languages( false );
MrTu_Test::same( array( 'vi', 'en', 'de', 'fr', 'es' ), array_keys( $langs ), 'five languages in registry order' );
MrTu_Test::same( 'vi', $langs['vi']['locale'], 'Vietnamese uses the WordPress.org pack locale "vi"' );
MrTu_Test::same( array( 'vi-VN', 'vi_VN', '', true ), array( $langs['vi']['hreflang'], $langs['vi']['og_locale'], $langs['vi']['prefix'], $langs['vi']['is_default'] ), 'vi: hreflang vi-VN, og vi_VN, no prefix, default' );
MrTu_Test::same( array( 'en_US', 'de_DE', 'fr_FR', 'es_ES' ), array( $langs['en']['locale'], $langs['de']['locale'], $langs['fr']['locale'], $langs['es']['locale'] ), 'WP locales of en/de/fr/es' );
MrTu_Test::same( array( 'en', 'de', 'fr', 'es' ), array( $langs['en']['prefix'], $langs['de']['prefix'], $langs['fr']['prefix'], $langs['es']['prefix'] ), 'default prefixes' );
MrTu_Test::same( array( 'code', 'locale', 'hreflang', 'og_locale', 'prefix', 'name', 'name_vi', 'enabled', 'is_default', 'tagline' ), array_keys( $langs['de'] ), 'language entry keys (contract 2.4)' );
MrTu_Test::same( 'vi', mrtu_default_language(), 'default language is vi' );
MrTu_Test::same( null, mrtu_get_language( 'ru' ), 'unknown language → null' );
MrTu_Test::same( 'Deutsch', mrtu_get_language( 'de' )['name'], 'native name' );

update_option(
	'mrtu_ml_settings',
	array(
		'toc'        => array( 'min_words' => 500 ),
		'languages'  => array(
			'de' => array( 'tagline' => 'Hallo' ),
			'vi' => array(
				'enabled' => false,
				'prefix'  => 'vn',
			),
		),
		'custom_key' => 'kept',
	)
);
$settings = Languages::settings();
MrTu_Test::same( array( 3, 500, array( 'post', 'page' ) ), array( $settings['toc']['min_headings'], $settings['toc']['min_words'], $settings['toc']['post_types'] ), 'settings merge deeply over defaults' );
MrTu_Test::same( array( 'de', 'Hallo', true, 'de' ), array( $settings['languages']['de']['prefix'], $settings['languages']['de']['tagline'], $settings['languages']['de']['enabled'], mrtu_get_language( 'de' )['prefix'] ), 'partial language row keeps its other defaults' );
MrTu_Test::same( array( true, '' ), array( $settings['languages']['vi']['enabled'], $settings['languages']['vi']['prefix'] ), 'default language is always enabled and never prefixed' );
MrTu_Test::same( array( 'kept', 'en', 'auto' ), array( $settings['custom_key'], $settings['x_default'], $settings['seo_mode'] ), 'unknown keys of other modules are kept; defaults filled' );

$validated = Languages::validate( array( 'languages' => array( 'en' => array( 'prefix' => 'EN1' ) ) ) );
MrTu_Test::ok( 1 === count( $validated['errors'] ) && 'en' === $validated['settings']['languages']['en']['prefix'], 'invalid prefix rejected (2–5 a–z), old value kept' );
$validated = Languages::validate( array( 'languages' => array( 'en' => array( 'prefix' => 'Wp' ) ) ) );
MrTu_Test::ok( 1 === count( $validated['errors'] ), 'reserved prefix "wp" rejected' );
$validated = Languages::validate( array( 'languages' => array( 'en' => array( 'prefix' => 'feed' ) ) ) );
MrTu_Test::ok( 1 === count( $validated['errors'] ), 'reserved prefix "feed" rejected' );
$validated = Languages::validate( array( 'languages' => array( 'de' => array( 'prefix' => 'en' ) ) ) );
MrTu_Test::ok( 1 === count( $validated['errors'] ) && 'de' === $validated['settings']['languages']['de']['prefix'], 'duplicate prefix rejected' );
$slug_page = MrTu_Test::post(
	array(
		'post_name'  => 'zqmrt',
		'post_title' => 'ZQ',
	)
);
$validated = Languages::validate( array( 'languages' => array( 'fr' => array( 'prefix' => 'zqmrt' ) ) ) );
MrTu_Test::ok( 1 === count( $validated['errors'] ) && 'fr' === $validated['settings']['languages']['fr']['prefix'], 'prefix equal to an existing top-level page slug rejected' );
$validated = Languages::validate( array( 'languages' => array( 'fr' => array( 'prefix' => 'fra' ) ) ) );
MrTu_Test::ok( ! $validated['errors'] && 'fra' === $validated['settings']['languages']['fr']['prefix'], 'valid new prefix accepted' );
$validated = Languages::validate(
	array(
		'languages' => array(
			'en' => array( 'hreflang' => 'en-gb' ),
			'es' => array( 'hreflang' => 'es-419' ),
		),
	)
);
MrTu_Test::ok( ! $validated['errors'] && 'en-GB' === $validated['settings']['languages']['en']['hreflang'] && 'es-419' === $validated['settings']['languages']['es']['hreflang'], 'hreflang normalized (en-GB, es-419)' );
$validated = Languages::validate( array( 'languages' => array( 'de' => array( 'hreflang' => 'fr' ) ) ) );
MrTu_Test::ok( 1 === count( $validated['errors'] ) && 'de' === $validated['settings']['languages']['de']['hreflang'], 'hreflang must match the language' );
$validated = Languages::validate( array( 'languages' => array( 'de' => array( 'hreflang' => 'de-XX1' ) ) ) );
MrTu_Test::ok( 1 === count( $validated['errors'] ), 'malformed hreflang rejected' );
$validated = Languages::validate( array( 'languages' => array( 'de' => array( 'tagline' => '<b>Roller</b> mieten' ) ) ) );
MrTu_Test::same( 'Roller mieten', $validated['settings']['languages']['de']['tagline'], 'tagline sanitized' );
$validated = Languages::validate(
	array(
		'languages' => array( 'de' => array( 'enabled' => '0' ) ),
		'x_default' => 'de',
	)
);
MrTu_Test::ok( 1 === count( $validated['errors'] ) && 'en' === $validated['settings']['x_default'], 'x-default must be an enabled language' );
$validated = Languages::validate( array( 'languages' => array( 'es' => array( 'enabled' => '0' ) ) ) );
Languages::update_settings( $validated['settings'] );
MrTu_Test::ok( ! mrtu_is_language_enabled( 'es' ) && ! isset( mrtu_languages()['es'] ) && isset( mrtu_languages( false )['es'] ), 'disabling a language removes it from enabled lists only' );
update_option( 'mrtu_ml_settings', Languages::defaults() );
MrTu_Test::ok( mrtu_is_language_enabled( 'es' ), 'settings restored' );

/*
 * Post language, link / unlink, group integrity
 */
MrTu_Test::section( 'Post language' );

register_post_type( 'mrtu_test_cpt', array( 'public' => true ) );
$post_a = MrTu_Test::post(
	array(
		'post_type'  => 'post',
		'post_title' => "VI post {$run}",
	)
);
MrTu_Test::same( 'vi', mrtu_get_post_language( $post_a ), 'untagged post reports the default language' );
MrTu_Test::ok( ! Translations::has_language_term( $post_a ), 'untagged post really has no language term' );
MrTu_Test::ok( mrtu_set_post_language( $post_a, 'vi' ) && Translations::has_language_term( $post_a ), 'set_post_language tags the post' );
$post_b = MrTu_Test::post(
	array(
		'post_type'  => 'post',
		'post_title' => "EN post {$run}",
	),
	'en'
);
MrTu_Test::same( 'en', mrtu_get_post_language( $post_b ), 'wp_insert_post( mrtu_lang ) assigns the language' );
MrTu_Test::ok( false === mrtu_set_post_language( $post_b, 'xx' ), 'unknown language rejected' );
$cpt = MrTu_Test::post( array( 'post_type' => 'mrtu_test_cpt' ) );
MrTu_Test::ok( false === mrtu_set_post_language( $cpt, 'en' ), 'non-translatable post type rejected' );

MrTu_Test::section( 'Link / unlink translations' );

$post_c     = MrTu_Test::post(
	array(
		'post_type'   => 'post',
		'post_title'  => "DE post {$run}",
		'post_status' => 'draft',
	),
	'de'
);
$linked_map = null;
add_action(
	'mrtu_translations_linked',
	static function ( $map ) use ( &$linked_map ) {
		$linked_map = $map;
	}
);
MrTu_Test::ok(
	mrtu_link_translations(
		array(
			'vi' => $post_a,
			'en' => $post_b,
		)
	),
	'link vi + en'
);
MrTu_Test::same(
	array(
		'vi' => $post_a,
		'en' => $post_b,
	),
	mrtu_get_post_translations( $post_b ),
	'translations map (includes self)'
);
MrTu_Test::ok(
	mrtu_link_translations(
		array(
			'de' => $post_c,
			'vi' => $post_a,
		)
	),
	'linking merges into the existing group'
);
$expected = array(
	'vi' => $post_a,
	'en' => $post_b,
	'de' => $post_c,
);
MrTu_Test::same( $expected, mrtu_get_post_translations( $post_c ), 'merged group has three languages' );
MrTu_Test::same( $expected, $linked_map, 'mrtu_translations_linked fired with the final map' );
MrTu_Test::same( $post_c, mrtu_get_post_translation( $post_a, 'de', false ), 'get_post_translation (any status)' );
MrTu_Test::same( 0, mrtu_get_post_translation( $post_a, 'de' ), 'get_post_translation published-only skips drafts' );
MrTu_Test::same( 0, mrtu_get_post_translation( $post_a, 'es', false ), 'missing language → 0' );
$post_d = MrTu_Test::post( array( 'post_type' => 'post' ), 'en' );
MrTu_Test::ok(
	false === mrtu_link_translations(
		array(
			'en' => $post_d,
			'vi' => $post_a,
		)
	),
	'two posts in one language are refused'
);
MrTu_Test::same( array( 'en' => $post_d ), mrtu_get_post_translations( $post_d ), 'refused link leaves posts untouched' );
MrTu_Test::ok(
	false === mrtu_link_translations(
		array(
			'fr' => $slug_page,
			'vi' => $post_a,
		)
	),
	'mixed post types are refused'
);
MrTu_Test::ok( false === mrtu_set_post_language( $post_c, 'en' ), 'language change into an occupied slot is refused' );
mrtu_unlink_translation( $post_b );
MrTu_Test::same(
	array(
		'vi' => $post_a,
		'de' => $post_c,
	),
	mrtu_get_post_translations( $post_a ),
	'unlink removes the post from the group'
);
MrTu_Test::same( array( 'en' => $post_b ), mrtu_get_post_translations( $post_b ), 'unlinked post keeps its language alone' );
mrtu_link_translations(
	array(
		'en' => $post_b,
		'vi' => $post_a,
	)
);
MrTu_Test::same( $expected, mrtu_get_post_translations( $post_a ), 're-linked' );

MrTu_Test::section( 'Group JSON integrity + rebuild' );

$group = Translations::get_post_group( $post_a );
$term  = get_term( $group, Translations::TAX_GROUP );
MrTu_Test::ok( $term instanceof WP_Term && 0 === strpos( $term->slug, 'mrtu-g-' ), 'group term slug mrtu-g-*' );
MrTu_Test::same( $expected, array_map( 'intval', (array) json_decode( $term->description, true ) ), 'term description holds the JSON map' );
wp_update_term( $group, Translations::TAX_GROUP, array( 'description' => wp_slash( '{"vi":999999,"fr":1}' ) ) );
MrTu_Test::same( $expected, Translations::rebuild_group( $group ), 'rebuild_group() restores a corrupted map from relationships' );
MrTu_Test::same( $expected, array_map( 'intval', (array) json_decode( get_term( $group, Translations::TAX_GROUP )->description, true ) ), 'rebuilt map persisted' );
$post_e = MrTu_Test::post( array( 'post_type' => 'post' ), 'vi' );
wp_set_object_terms( $post_e, array( $group ), Translations::TAX_GROUP );
MrTu_Test::same( $expected, Translations::rebuild_group( $group ), 'duplicate language: the published/oldest post wins' );
MrTu_Test::same( 0, Translations::get_post_group( $post_e ), 'duplicate removed from the group' );
wp_trash_post( $post_c );
MrTu_Test::ok( ! isset( mrtu_get_post_translations( $post_a )['de'] ), 'trashing frees the language slot' );
wp_untrash_post( $post_c );
MrTu_Test::same( $post_c, mrtu_get_post_translation( $post_a, 'de', false ), 'untrash rejoins the group' );
wp_delete_post( $post_c, true );
MrTu_Test::ok( ! isset( mrtu_get_post_translations( $post_a )['de'] ), 'deleting updates the map' );
$solo_x = MrTu_Test::post( array( 'post_type' => 'post' ), 'fr' );
$solo_y = MrTu_Test::post( array( 'post_type' => 'post' ), 'es' );
mrtu_link_translations(
	array(
		'fr' => $solo_x,
		'es' => $solo_y,
	)
);
$group_xy = Translations::get_post_group( $solo_x );
wp_delete_post( $solo_x, true );
wp_delete_post( $solo_y, true );
MrTu_Test::ok( ! get_term( $group_xy, Translations::TAX_GROUP ) instanceof WP_Term, 'empty groups are deleted' );

/*
 * Same slug in several languages
 */
MrTu_Test::section( 'Same slug in several languages' );

$slug   = 'faq' . $run;
$faq_en = MrTu_Test::post(
	array(
		'post_name'  => $slug,
		'post_title' => 'FAQ',
	),
	'en'
);
$faq_de = MrTu_Test::post(
	array(
		'post_name'  => $slug,
		'post_title' => 'Häufige Fragen',
	),
	'de'
);
$faq_fr = MrTu_Test::post(
	array(
		'post_name'  => $slug,
		'post_title' => 'Questions fréquentes',
	),
	'fr'
);
MrTu_Test::same( array( $slug, $slug, $slug ), array( get_post_field( 'post_name', $faq_en ), get_post_field( 'post_name', $faq_de ), get_post_field( 'post_name', $faq_fr ) ), 'page slug shared by en/de/fr without suffix' );
$faq_en2 = MrTu_Test::post( array( 'post_name' => $slug ), 'en' );
MrTu_Test::same( $slug . '-2', get_post_field( 'post_name', $faq_en2 ), 'same slug inside one language is still suffixed' );
$faq_vi = MrTu_Test::post(
	array(
		'post_name'   => $slug,
		'post_title'  => 'Câu hỏi thường gặp',
		'post_status' => 'draft',
	)
);
mrtu_set_post_language( $faq_vi, 'vi' );
wp_update_post(
	array(
		'ID'          => $faq_vi,
		'post_status' => 'publish',
	)
);
MrTu_Test::same( $slug, get_post_field( 'post_name', $faq_vi ), 'draft → set language → publish keeps the shared slug' );
$news_slug = 'news' . $run;
$news_en   = MrTu_Test::post(
	array(
		'post_type'  => 'post',
		'post_name'  => $news_slug,
		'post_title' => "News {$run}",
	),
	'en'
);
$news_de   = MrTu_Test::post(
	array(
		'post_type'  => 'post',
		'post_name'  => $news_slug,
		'post_title' => "Neuigkeiten {$run}",
	),
	'de'
);
MrTu_Test::same( $news_slug, get_post_field( 'post_name', $news_de ), 'post slug shared across languages' );
$prefix_page = MrTu_Test::post( array( 'post_name' => 'en' ) );
MrTu_Test::ok( 'en' !== get_post_field( 'post_name', $prefix_page ), 'a top-level page cannot take a language prefix as slug' );
$prefix_post = MrTu_Test::post(
	array(
		'post_type' => 'post',
		'post_name' => 'de',
	)
);
MrTu_Test::ok( 'de' !== get_post_field( 'post_name', $prefix_post ), 'a root-level post cannot take a language prefix as slug' );
$child_en_slug = MrTu_Test::post(
	array(
		'post_name'   => 'en',
		'post_parent' => $faq_vi,
	)
);
MrTu_Test::same( 'en', get_post_field( 'post_name', $child_en_slug ), 'a child page may use a prefix-like slug' );
MrTu_Test::ok(
	mrtu_link_translations(
		array(
			'vi' => $faq_vi,
			'en' => $faq_en,
			'de' => $faq_de,
			'fr' => $faq_fr,
		)
	),
	'faq group linked'
);

/*
 * Front page + posts page per language
 */
MrTu_Test::section( 'Front page and posts page per language' );

$homes = array();
$blogs = array();
foreach ( array( 'vi', 'en', 'de', 'fr', 'es' ) as $code ) {
	$homes[ $code ] = MrTu_Test::post(
		array(
			'post_title' => "Home {$code} {$run}",
			'post_name'  => "home-{$code}-{$run}",
		),
		$code
	);
	$blogs[ $code ] = MrTu_Test::post(
		array(
			'post_title' => "Blog {$code} {$run}",
			'post_name'  => "blog-{$code}-{$run}",
		),
		$code
	);
}
MrTu_Test::ok( mrtu_link_translations( $homes ) && mrtu_link_translations( $blogs ), 'home and blog groups linked' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $homes['vi'] );
update_option( 'page_for_posts', $blogs['vi'] );

MrTu_Test::same( home_url( '/' ), get_permalink( $homes['vi'] ), 'VI home permalink = /' );
MrTu_Test::same( home_url( '/en/' ), get_permalink( $homes['en'] ), 'EN home permalink = /en/' );
MrTu_Test::same( home_url( '/es/' ), get_permalink( $homes['es'] ), 'ES home permalink = /es/' );
MrTu_Test::same( home_url( "/de/blog-de-{$run}/" ), get_permalink( $blogs['de'] ), 'DE posts page permalink' );

MrTu_Test::go_to( '/en/' );
MrTu_Test::ok( is_front_page() && is_page(), '/en/ is the front page' );
MrTu_Test::same( array( $homes['en'], 'en' ), array( get_queried_object_id(), mrtu_current_language() ), '/en/ shows the EN home in English' );
MrTu_Test::same( array( $homes['en'], $homes['vi'] ), array( (int) get_option( 'page_on_front' ), Router::raw_option( 'page_on_front' ) ), 'page_on_front filtered on the frontend; raw accessor unfiltered' );
MrTu_Test::same( 'en_US', get_locale(), 'frontend locale follows the URL language' );
MrTu_Test::go_to( '/' );
MrTu_Test::same( array( $homes['vi'], 'vi' ), array( get_queried_object_id(), mrtu_current_language() ), '/ shows the VI home' );
MrTu_Test::go_to( '/de/' );
MrTu_Test::same( $homes['de'], get_queried_object_id(), '/de/ shows the DE home' );
MrTu_Test::go_to( "/en/blog-en-{$run}/" );
MrTu_Test::ok( is_home() && $GLOBALS['wp_query']->is_posts_page && $blogs['en'] === get_queried_object_id(), 'EN posts page is the blog home in English' );
MrTu_Test::go_to( "/en/blog-en-{$run}/page/2/" );
MrTu_Test::same( array( $blogs['en'], 2 ), array( (int) $GLOBALS['wp']->query_vars['page_id'], (int) $GLOBALS['wp']->query_vars['paged'] ), 'posts page pagination is routed to the EN posts page' );
MrTu_Test::go_to( "/en/home-en-{$run}/" );
MrTu_Test::same( home_url( '/en/' ), Router::guard_redirect( home_url( '/' ), home_url( "/en/home-en-{$run}/" ) ), 'front page canonical redirect stays in its language' );
MrTu_Test::go_to( '/en/' );
MrTu_Test::ok( false === Router::guard_redirect( home_url( "/{$slug}/" ), home_url( '/en/x/' ) ), 'canonical redirects never cross languages' );
MrTu_Test::leave_frontend();
wp_update_post(
	array(
		'ID'          => $homes['es'],
		'post_status' => 'draft',
	)
);
MrTu_Test::go_to( '/es/' );
MrTu_Test::ok( is_404(), 'language without a published home → 404' );
MrTu_Test::leave_frontend();
wp_update_post(
	array(
		'ID'          => $homes['es'],
		'post_status' => 'publish',
	)
);

/*
 * Roles
 */
MrTu_Test::section( 'Page roles' );

$roles = mrtu_page_roles();
MrTu_Test::same( array( 'home', 'pillar', 'old-quarter', 'pricing', 'fleet', 'short-term', 'monthly', 'guide-faq', 'about', 'contact', 'blog', 'privacy', 'terms', 'rental-policy' ), array_keys( $roles ), 'role registry (contract 2.3) in order' );
MrTu_Test::same( array( 'primary', 'rent', 'more', 'legal' ), array( $roles['pillar']['nav'], $roles['pillar']['footer'], $roles['monthly']['nav'], $roles['terms']['footer'] ), 'nav/footer placement' );
MrTu_Test::ok( mrtu_set_post_role( $homes['en'], 'home' ), 'set role on one member' );
MrTu_Test::same( array( 'home', 'home' ), array( mrtu_get_post_role( $homes['vi'] ), get_post_meta( $homes['de'], '_mrtu_role', true ) ), 'role applies to the whole group (meta synced)' );
MrTu_Test::same( $homes['de'], mrtu_get_page_id_by_role( 'home', 'de' ), 'page id by role in a language' );
MrTu_Test::same( Translations::get_post_group( $homes['vi'] ), get_option( 'mrtu_roles_map' )['home'], 'roles map stores role → group id' );
mrtu_set_post_role( $blogs['vi'], 'blog' );
MrTu_Test::same( get_permalink( $blogs['fr'] ), mrtu_role_url( 'blog', 'fr' ), 'role URL in a language' );
wp_update_post(
	array(
		'ID'          => $blogs['es'],
		'post_status' => 'draft',
	)
);
MrTu_Test::same( array( 0, '' ), array( mrtu_get_page_id_by_role( 'blog', 'es' ), mrtu_role_url( 'blog', 'es' ) ), 'unpublished translation → 0 / empty URL' );
wp_update_post(
	array(
		'ID'          => $blogs['es'],
		'post_status' => 'publish',
	)
);
MrTu_Test::go_to( '/fr/' );
MrTu_Test::same( $homes['fr'], mrtu_get_page_id_by_role( 'home' ), 'role lookup defaults to the current language' );
MrTu_Test::leave_frontend();
MrTu_Test::ok( false === mrtu_set_post_role( $homes['vi'], 'not-a-role' ), 'unknown role rejected' );
$other_home = MrTu_Test::post( array( 'post_title' => 'Other' ), 'vi' );
mrtu_set_post_role( $other_home, 'home' );
MrTu_Test::same( array( 'home', '', '' ), array( mrtu_get_post_role( $other_home ), mrtu_get_post_role( $homes['en'] ), get_post_meta( $homes['en'], '_mrtu_role', true ) ), 'moving a role clears it from the previous group' );
mrtu_set_post_role( $homes['vi'], 'home' );
mrtu_set_post_role( $other_home, '' );
MrTu_Test::same( array( '', 'home' ), array( mrtu_get_post_role( $other_home ), mrtu_get_post_role( $homes['es'] ) ), 'clearing / reassigning roles' );

/*
 * URLs
 */
MrTu_Test::section( 'mrtu_home_url + permalinks' );

MrTu_Test::same( home_url( '/' ), mrtu_home_url( '', 'vi' ), 'mrtu_home_url vi' );
MrTu_Test::same( home_url( '/en/lien-he/' ), mrtu_home_url( '/lien-he/', 'en' ), 'mrtu_home_url with path' );
MrTu_Test::same( home_url( '/' ), mrtu_home_url(), 'mrtu_home_url outside the frontend → default language' );
MrTu_Test::go_to( '/de/' );
MrTu_Test::same( home_url( '/de/kontakt/' ), mrtu_home_url( 'kontakt/' ), 'mrtu_home_url uses the current language' );
MrTu_Test::leave_frontend();
$validated = Languages::validate( array( 'languages' => array( 'fr' => array( 'enabled' => '0' ) ) ) );
Languages::update_settings( $validated['settings'] );
MrTu_Test::same( home_url( '/x/' ), mrtu_home_url( 'x/', 'fr' ), 'disabled language falls back to the default home' );
update_option( 'mrtu_ml_settings', Languages::defaults() );

$post_vi = MrTu_Test::post(
	array(
		'post_type'  => 'post',
		'post_name'  => "xin-chao-{$run}",
		'post_title' => "Xin chào {$run}",
	),
	'vi'
);
MrTu_Test::same( home_url( "/xin-chao-{$run}/" ), get_permalink( $post_vi ), 'VI post has no prefix' );
MrTu_Test::same( home_url( "/en/{$news_slug}/" ), get_permalink( $news_en ), 'EN post prefixed' );
MrTu_Test::same( home_url( "/fr/{$slug}/" ), get_permalink( $faq_fr ), 'FR page prefixed' );
$guides   = MrTu_Test::post(
	array(
		'post_name'  => "guides-{$run}",
		'post_title' => "Guides {$run}",
	),
	'en'
);
$document = MrTu_Test::post(
	array(
		'post_name'   => 'documents',
		'post_title'  => 'Documents',
		'post_parent' => $guides,
	),
	'en'
);
MrTu_Test::same( home_url( "/en/guides-{$run}/documents/" ), get_permalink( $document ), 'hierarchical EN page' );
$cat_en = MrTu_Test::term( "Rental tips {$run}", 'category', 'en', array( 'slug' => "rental-tips-{$run}" ) );
$cat_vi = MrTu_Test::term( "Mẹo thuê xe {$run}", 'category', 'vi', array( 'slug' => "meo-thue-xe-{$run}" ) );
$tag_vi = MrTu_Test::term( "meo {$run}", 'post_tag', 'vi', array( 'slug' => "meo-{$run}" ) );
MrTu_Test::same( home_url( "/en/category/rental-tips-{$run}/" ), get_term_link( $cat_en ), 'EN category prefixed' );
MrTu_Test::same( home_url( "/tag/meo-{$run}/" ), get_term_link( $tag_vi ), 'VI tag not prefixed' );
$draft_en = MrTu_Test::post(
	array(
		'post_type'   => 'post',
		'post_status' => 'draft',
		'post_title'  => "EN draft {$run}",
	),
	'en'
);
MrTu_Test::same( home_url( "/en/?p={$draft_en}" ), get_permalink( $draft_en ), 'plain draft link carries the language prefix' );
$attachment = wp_insert_attachment(
	array(
		'post_title'     => "att-{$run}",
		'post_mime_type' => 'image/png',
		'post_status'    => 'inherit',
	),
	false,
	$news_en
);
MrTu_Test::track_post( $attachment );
MrTu_Test::ok( false === strpos( get_attachment_link( $attachment ), '/en/' ), 'attachment links are never prefixed' );

MrTu_Test::go_to( '/en/' );
MrTu_Test::same(
	array( home_url( '/en/search/abc/' ), home_url( '/en/feed/' ), home_url( '/en/author/admin/' ), home_url( '/en/2026/' ), home_url( '/en/2026/10/' ), home_url( '/en/2026/10/25/' ) ),
	array( get_search_link( 'abc' ), get_feed_link(), get_author_posts_url( 1, 'admin' ), get_year_link( 2026 ), get_month_link( 2026, 10 ), get_day_link( 2026, 10, 25 ) ),
	'context links (search, feed, author, dates) use the current language'
);
MrTu_Test::same( get_permalink( $blogs['en'] ), get_post_type_archive_link( 'post' ), 'posts archive link = EN posts page' );
MrTu_Test::same( home_url( "/en/category/rental-tips-{$run}/feed/" ), get_category_feed_link( $cat_en ), 'term feed inherits the term language' );
MrTu_Test::go_to( '/' );
MrTu_Test::same( home_url( '/feed/' ), get_feed_link(), 'VI feed link without prefix' );
MrTu_Test::leave_frontend();
MrTu_Test::same( home_url( '/feed/' ), get_feed_link(), 'admin/CLI context links are not prefixed' );

/*
 * Routing (simulated requests)
 */
MrTu_Test::section( 'Routing (simulated requests)' );

MrTu_Test::go_to( "/en/{$slug}/" );
MrTu_Test::same( array( $faq_en, 'en' ), array( get_queried_object_id(), mrtu_current_language() ), '/en/{faq}/ → EN page' );
MrTu_Test::go_to( "/de/{$slug}/" );
MrTu_Test::same( $faq_de, get_queried_object_id(), '/de/{faq}/ → DE page' );
MrTu_Test::go_to( "/fr/{$slug}/" );
MrTu_Test::same( $faq_fr, get_queried_object_id(), '/fr/{faq}/ → FR page' );
MrTu_Test::go_to( "/{$slug}/" );
MrTu_Test::same( $faq_vi, get_queried_object_id(), '/{faq}/ → VI page' );
MrTu_Test::go_to( "/es/{$slug}/" );
MrTu_Test::ok( is_404(), 'slug without an ES version → 404 under /es/' );
MrTu_Test::go_to( "/en/xin-chao-{$run}/" );
MrTu_Test::ok( is_404(), 'VI-only slug under /en/ → 404' );
MrTu_Test::go_to( "/xin-chao-{$run}/" );
MrTu_Test::ok( is_single() && $post_vi === get_queried_object_id(), 'VI post at the root' );
MrTu_Test::go_to( "/{$news_slug}/" );
MrTu_Test::ok( is_404(), 'EN/DE-only post slug without prefix → 404' );
MrTu_Test::go_to( "/de/{$news_slug}/" );
MrTu_Test::same( $news_de, get_queried_object_id(), 'same post slug resolves per language' );
MrTu_Test::go_to( "/en/guides-{$run}/documents/" );
MrTu_Test::same( $document, get_queried_object_id(), 'hierarchical path resolved in its language' );
MrTu_Test::go_to( "/en/category/rental-tips-{$run}/" );
MrTu_Test::ok( is_category() && ! is_404(), 'EN category archive under /en/' );
MrTu_Test::go_to( "/category/rental-tips-{$run}/" );
MrTu_Test::ok( is_404(), 'EN category archive without prefix → 404' );
MrTu_Test::go_to( "/en/?p={$post_vi}" );
MrTu_Test::same( 'vi', mrtu_current_language(), 'ID-based request takes the language of the object' );
MrTu_Test::go_to( "/?p={$draft_en}&preview=true" );
MrTu_Test::ok( is_preview() && $draft_en === get_queried_object_id() && 'en' === mrtu_current_language(), 'preview of an EN draft renders in English' );
$validated = Languages::validate( array( 'languages' => array( 'es' => array( 'enabled' => '0' ) ) ) );
Languages::update_settings( $validated['settings'] );
MrTu_Test::go_to( '/es/' );
MrTu_Test::ok( is_404() && 'vi' === mrtu_current_language(), 'disabled language prefix is not routed (404)' );
update_option( 'mrtu_ml_settings', Languages::defaults() );
$fired_lang = '';
add_action(
	'mrtu_language_detected',
	static function ( $lang ) use ( &$fired_lang ) {
		$fired_lang = $lang;
	}
);
MrTu_Test::go_to( '/fr/' );
MrTu_Test::same( 'fr', $fired_lang, 'mrtu_language_detected fired' );
MrTu_Test::same( '/fr/', $_SERVER['REQUEST_URI'], 'REQUEST_URI restored after parsing' );
MrTu_Test::same( 'fr', $GLOBALS['wp']->request, '$wp->request keeps the prefix' );
MrTu_Test::leave_frontend();

/*
 * Queries
 */
MrTu_Test::section( 'Language-scoped queries' );

$word     = 'zz' . $run;
$q_en     = MrTu_Test::post(
	array(
		'post_type'     => 'post',
		'post_title'    => "English {$word}",
		'post_date'     => '2026-01-01 10:00:00',
		'post_category' => array( $cat_en ),
	),
	'en'
);
$q_vi     = MrTu_Test::post(
	array(
		'post_type'     => 'post',
		'post_title'    => "Tiếng Việt {$word}",
		'post_date'     => '2026-01-02 10:00:00',
		'post_category' => array( $cat_vi, $cat_en ),
	),
	'vi'
);
$q_en2    = MrTu_Test::post(
	array(
		'post_type'     => 'post',
		'post_title'    => "English two {$word}",
		'post_date'     => '2026-01-03 10:00:00',
		'post_category' => array( $cat_en ),
	),
	'en'
);
$q_de     = MrTu_Test::post(
	array(
		'post_type'  => 'post',
		'post_title' => "Deutsch {$word}",
	),
	'de'
);
$q_untag  = MrTu_Test::post(
	array(
		'post_type'  => 'post',
		'post_title' => "Untagged {$word}",
	)
);
$langs_of = static function () {
	$out = array();
	foreach ( $GLOBALS['wp_query']->posts as $found ) {
		$out[] = mrtu_get_post_language( $found );
	}
	return array_values( array_unique( $out ) );
};
MrTu_Test::go_to( "/en/?s={$word}" );
MrTu_Test::ok( is_search() && 2 === count( $GLOBALS['wp_query']->posts ) && array( 'en' ) === $langs_of(), 'EN search returns only EN posts' );
MrTu_Test::go_to( "/?s={$word}" );
$ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
MrTu_Test::ok( in_array( $q_vi, $ids, true ) && in_array( $q_untag, $ids, true ) && 2 === count( $ids ), 'VI search includes untagged posts (default language)' );
MrTu_Test::go_to( "/de/?s={$word}" );
MrTu_Test::same( array( $q_de ), wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ), 'DE search returns only DE posts' );
MrTu_Test::go_to( "/en/category/rental-tips-{$run}/" );
MrTu_Test::ok( array( 'en' ) === $langs_of() && ! in_array( $q_vi, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ), true ), 'category archive lists only its language' );
MrTu_Test::go_to( "/en/blog-en-{$run}/" );
MrTu_Test::ok( $GLOBALS['wp_query']->posts && array( 'en' ) === $langs_of(), 'EN posts page lists only EN posts' );
MrTu_Test::go_to( '/en/feed/' );
MrTu_Test::ok( is_feed() && array( 'en' ) === $langs_of(), 'EN feed lists only EN posts' );
MrTu_Test::go_to( '/en/' );
$category_langs = array();
foreach ( get_terms(
	array(
		'taxonomy'   => 'category',
		'hide_empty' => false,
	)
) as $found_term ) {
	$category_langs[ mrtu_get_term_language( $found_term ) ] = true;
}
MrTu_Test::same( array( 'en' ), array_keys( $category_langs ), 'frontend get_terms() is scoped to the current language' );
MrTu_Test::go_to( "/en/{$news_slug}/" );
$previous = get_adjacent_post( false, '', true );
$next     = get_adjacent_post( false, '', false );
MrTu_Test::ok( ( ! $previous || 'en' === mrtu_get_post_language( $previous ) ) && ( ! $next || 'en' === mrtu_get_post_language( $next ) ), 'adjacent posts stay in the language' );
MrTu_Test::leave_frontend();
$GLOBALS['post'] = get_post( $q_en2 ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- simulate the loop.
$previous        = get_adjacent_post( false, '', true );
MrTu_Test::same( $q_en, $previous ? $previous->ID : 0, 'previous post skips the VI post in between' );
unset( $GLOBALS['post'] );
$secondary = new WP_Query(
	array(
		's'         => $word,
		'mrtu_lang' => 'de',
		'fields'    => 'ids',
	)
);
MrTu_Test::same( array( $q_de ), $secondary->posts, 'WP_Query( mrtu_lang ) scopes secondary queries' );
$secondary = new WP_Query(
	array(
		's'         => $word,
		'mrtu_lang' => 'all',
		'fields'    => 'ids',
	)
);
MrTu_Test::same( 5, count( $secondary->posts ), "mrtu_lang 'all' disables scoping" );
$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
$request->set_param( 'lang', 'en' );
$request->set_param( 'search', $word );
$response = rest_do_request( $request );
$rest_ids = wp_list_pluck( (array) $response->get_data(), 'id' );
sort( $rest_ids );
MrTu_Test::same( array( $q_en, $q_en2 ), $rest_ids, 'REST collection param lang=en' );
Router::set_current_language( '' );

/*
 * Switcher
 */
MrTu_Test::section( 'Language switcher' );

MrTu_Test::go_to( "/en/{$slug}/" );
$links = mrtu_switcher_links();
MrTu_Test::same( array( 'vi', 'en', 'de', 'fr', 'es' ), array_keys( $links ), 'one entry per enabled language' );
MrTu_Test::same( array( 'code', 'name', 'hreflang', 'url', 'current', 'available', 'fallback' ), array_keys( $links['de'] ), 'entry keys (contract 2.4)' );
MrTu_Test::ok( $links['en']['current'] && ! $links['de']['current'], 'current flag' );
MrTu_Test::same( array( get_permalink( $faq_de ), true, false ), array( $links['de']['url'], $links['de']['available'], $links['de']['fallback'] ), 'singular: translation available' );
MrTu_Test::same( array( home_url( '/es/' ), false, true ), array( $links['es']['url'], $links['es']['available'], $links['es']['fallback'] ), 'singular: missing translation falls back to the language home' );
$html = mrtu_language_switcher( array( 'echo' => false ) );
MrTu_Test::ok(
	false !== strpos( $html, '<nav class="mrtu-switcher" aria-label="Language">' ) && false !== strpos( $html, '<ul' ) && false !== strpos( $html, 'aria-current="page"' ) && false !== strpos( $html, 'hreflang="de" lang="de"' ) && false !== strpos( $html, 'hreflang="vi-VN" lang="vi-VN"' ),
	'accessible switcher markup (nav aria-label, list, lang/hreflang, aria-current)',
	$html
);
MrTu_Test::go_to( '/de/' );
$links = mrtu_switcher_links();
MrTu_Test::same( array( home_url( '/' ), home_url( '/en/' ), true ), array( $links['vi']['url'], $links['en']['url'], $links['de']['current'] ), 'front page: language homes' );
mrtu_link_term_translations(
	array(
		'vi' => $cat_vi,
		'en' => $cat_en,
	)
);
MrTu_Test::go_to( "/en/category/rental-tips-{$run}/" );
$links = mrtu_switcher_links();
MrTu_Test::same( array( get_term_link( $cat_vi ), true ), array( $links['vi']['url'], $links['vi']['available'] ), 'term archive: term translation' );
MrTu_Test::ok( ! $links['de']['available'] && home_url( '/de/' ) === $links['de']['url'], 'term archive: missing translation → DE home' );
MrTu_Test::go_to( "/en/?s={$word}" );
$links = mrtu_switcher_links();
MrTu_Test::same( home_url( "/de/?s={$word}" ), $links['de']['url'], 'search: same query in the other language' );
MrTu_Test::go_to( "/en/no-such-page-{$run}/" );
$links = mrtu_switcher_links();
MrTu_Test::ok( is_404() && '' === $links['en']['url'] && home_url( '/fr/' ) === $links['fr']['url'] && $links['fr']['fallback'], '404: current has no URL, others go home' );
MrTu_Test::ok( false === strpos( mrtu_language_switcher( array( 'echo' => false ) ), 'href=""' ), '404 markup has no empty links' );

/*
 * Alternates
 */
MrTu_Test::section( 'hreflang alternates' );

MrTu_Test::go_to( "/en/{$slug}/" );
$alternates = mrtu_alternate_urls();
MrTu_Test::same(
	array(
		'vi-VN'     => get_permalink( $faq_vi ),
		'en'        => get_permalink( $faq_en ),
		'de'        => get_permalink( $faq_de ),
		'fr'        => get_permalink( $faq_fr ),
		'x-default' => get_permalink( $faq_en ),
	),
	$alternates,
	'alternates: published translations incl. self + x-default'
);
wp_update_post(
	array(
		'ID'          => $faq_fr,
		'post_status' => 'draft',
	)
);
update_post_meta( $faq_de, '_mrtu_noindex', '1' );
MrTu_Test::go_to( "/en/{$slug}/" );
$alternates = mrtu_alternate_urls();
MrTu_Test::ok( ! isset( $alternates['fr'] ) && ! isset( $alternates['de'] ) && isset( $alternates['vi-VN'] ), 'drafts and noindex translations excluded' );
wp_update_post(
	array(
		'ID'          => $faq_fr,
		'post_status' => 'publish',
	)
);
delete_post_meta( $faq_de, '_mrtu_noindex' );
$validated = Languages::validate( array( 'languages' => array( 'fr' => array( 'enabled' => '0' ) ) ) );
Languages::update_settings( $validated['settings'] );
MrTu_Test::go_to( "/en/{$slug}/" );
$alternates = mrtu_alternate_urls();
MrTu_Test::ok( ! isset( $alternates['fr'] ) && isset( $alternates['de'] ), 'disabled languages excluded' );
update_option( 'mrtu_ml_settings', Languages::defaults() );
update_post_meta( $faq_en, '_mrtu_noindex', '1' );
MrTu_Test::go_to( "/en/{$slug}/" );
MrTu_Test::same( array(), mrtu_alternate_urls(), 'noindex page → no alternates' );
delete_post_meta( $faq_en, '_mrtu_noindex' );
MrTu_Test::go_to( "/en/?s={$word}" );
MrTu_Test::same( array(), mrtu_alternate_urls(), 'search → no alternates' );
MrTu_Test::go_to( "/en/no-such-page-{$run}/" );
MrTu_Test::same( array(), mrtu_alternate_urls(), '404 → no alternates' );
MrTu_Test::go_to( '/' );
$alternates = mrtu_alternate_urls();
MrTu_Test::same( array( home_url( '/' ), home_url( '/en/' ), home_url( '/es/' ), home_url( '/en/' ) ), array( $alternates['vi-VN'], $alternates['en'], $alternates['es'], $alternates['x-default'] ), 'front page alternates are the language homes' );

/*
 * Breadcrumbs
 */
MrTu_Test::section( 'Breadcrumbs' );

MrTu_Test::go_to( "/en/guides-{$run}/documents/" );
$trail = mrtu_breadcrumb_trail();
MrTu_Test::same(
	array(
		array(
			'name' => 'Home',
			'url'  => home_url( '/en/' ),
		),
		array(
			'name' => "Guides {$run}",
			'url'  => get_permalink( $guides ),
		),
		array(
			'name' => 'Documents',
			'url'  => get_permalink( $document ),
		),
	),
	$trail,
	'page: Home › ancestors › page'
);
MrTu_Test::go_to( "/en/{$news_slug}/" );
wp_set_post_categories( $news_en, array( $cat_en ) );
MrTu_Test::go_to( "/en/{$news_slug}/" );
$trail = mrtu_breadcrumb_trail();
MrTu_Test::same( array( 'Home', "Blog en {$run}", "Rental tips {$run}", "News {$run}" ), wp_list_pluck( $trail, 'name' ), 'post: Home › posts page › category › post' );
MrTu_Test::go_to( "/xin-chao-{$run}/" );
$trail = mrtu_breadcrumb_trail();
MrTu_Test::same( array( 'Trang chủ', home_url( '/' ) ), array( $trail[0]['name'], $trail[0]['url'] ), 'VI trail starts with “Trang chủ”' );
MrTu_Test::go_to( "/en/category/rental-tips-{$run}/" );
MrTu_Test::same( array( 'Home', "Blog en {$run}", "Rental tips {$run}" ), wp_list_pluck( mrtu_breadcrumb_trail(), 'name' ), 'category: Home › posts page › term' );
MrTu_Test::go_to( "/de/?s={$word}" );
$trail = mrtu_breadcrumb_trail();
MrTu_Test::same( array( 'Startseite', "Suchergebnisse für „{$word}“", '' ), array( $trail[0]['name'], $trail[1]['name'], $trail[1]['url'] ), 'search trail in German' );
MrTu_Test::go_to( "/fr/no-such-page-{$run}/" );
MrTu_Test::same( array( 'Accueil', 'Page introuvable' ), wp_list_pluck( mrtu_breadcrumb_trail(), 'name' ), '404 trail in French' );
MrTu_Test::go_to( '/es/' );
MrTu_Test::same( array( 'Inicio' ), wp_list_pluck( mrtu_breadcrumb_trail(), 'name' ), 'front page: only Home' );
MrTu_Test::leave_frontend();

/*
 * Creating translations + duplicate guard
 */
MrTu_Test::section( 'Create translation + duplicate-content guard' );

$parent_vi = MrTu_Test::post( array( 'post_title' => "Cha {$run}" ), 'vi' );
$parent_en = MrTu_Test::post( array( 'post_title' => "Parent {$run}" ), 'en' );
mrtu_link_translations(
	array(
		'vi' => $parent_vi,
		'en' => $parent_en,
	)
);
$source = MrTu_Test::post(
	array(
		'post_title'   => "Con {$run}",
		'post_parent'  => $parent_vi,
		'post_content' => '<!-- wp:paragraph --><p>Nội dung tiếng Việt đầy đủ.</p><!-- /wp:paragraph -->',
	),
	'vi'
);
mrtu_set_post_role( $source, 'pricing' );
$copy = Translations::create_translation( $source, 'en', true );
MrTu_Test::track_post( is_int( $copy ) ? $copy : 0 );
MrTu_Test::ok( is_int( $copy ) && 'draft' === get_post_status( $copy ) && 'en' === mrtu_get_post_language( $copy ), 'translation created as an EN draft' );
MrTu_Test::same( array( $parent_en, get_post_field( 'post_content', $source ), 'pricing' ), array( (int) get_post_field( 'post_parent', $copy ), get_post_field( 'post_content', $copy ), mrtu_get_post_role( $copy ) ), 'parent mapped, content copied, role shared' );
MrTu_Test::same( $copy, mrtu_get_post_translation( $source, 'en', false ), 'linked to the group' );
wp_update_post(
	array(
		'ID'          => $copy,
		'post_status' => 'publish',
	)
);
MrTu_Test::same( 'draft', get_post_status( $copy ), 'an identical copy cannot be published' );
$flash = get_transient( 'mrtu_flash_1' );
MrTu_Test::ok( is_array( $flash ) && 'warning' === $flash['type'], 'flash notice explains why' );
delete_transient( 'mrtu_flash_1' );
wp_update_post(
	array(
		'ID'          => $source,
		'post_status' => 'publish',
		'post_title'  => "Con {$run} (updated)",
	)
);
MrTu_Test::same( 'publish', get_post_status( $source ), 'the original stays publishable' );
wp_update_post(
	array(
		'ID'           => $copy,
		'post_status'  => 'publish',
		'post_content' => '<!-- wp:paragraph --><p>Complete English text.</p><!-- /wp:paragraph -->',
	)
);
MrTu_Test::same( 'publish', get_post_status( $copy ), 'a translated version publishes' );
$copy_de = Translations::create_translation( $source, 'de', true );
MrTu_Test::track_post( is_int( $copy_de ) ? $copy_de : 0 );
wp_publish_post( $copy_de );
MrTu_Test::same( 'draft', get_post_status( $copy_de ), 'wp_publish_post() path is guarded too' );
$empty_vi = MrTu_Test::post(
	array(
		'post_title'   => "Empty {$run}",
		'post_content' => '',
	),
	'vi'
);
$empty_fr = Translations::create_translation( $empty_vi, 'fr', true );
MrTu_Test::track_post( is_int( $empty_fr ) ? $empty_fr : 0 );
wp_update_post(
	array(
		'ID'          => $empty_fr,
		'post_status' => 'publish',
	)
);
MrTu_Test::same( 'publish', get_post_status( $empty_fr ), 'empty (template-driven) pages are not blocked' );
$again = Translations::create_translation( $source, 'en', true );
MrTu_Test::ok( is_wp_error( $again ) && 'mrtu_translation_exists' === $again->get_error_code(), 'existing language version is not duplicated' );
delete_transient( 'mrtu_flash_1' );

/*
 * Term translations
 */
MrTu_Test::section( 'Term translations' );

MrTu_Test::same( 'en', mrtu_get_term_language( $cat_en ), 'term language' );
MrTu_Test::same(
	array(
		'vi' => $cat_vi,
		'en' => $cat_en,
	),
	mrtu_get_term_translations( $cat_en ),
	'term translations map'
);
MrTu_Test::same( $cat_vi, mrtu_get_term_translation( $cat_en, 'vi' ), 'term translation lookup' );
MrTu_Test::ok( false === mrtu_set_term_language( $cat_vi, 'en' ), 'term language change into an occupied slot refused' );
$tag_de = MrTu_Test::term( "Tipp {$run}", 'post_tag', 'de' );
MrTu_Test::ok(
	false === mrtu_link_term_translations(
		array(
			'vi' => $cat_vi,
			'de' => $tag_de,
		)
	),
	'mixed taxonomies refused'
);
$cat_de = MrTu_Test::term( "Tipps {$run}", 'category', 'de' );
MrTu_Test::ok(
	mrtu_link_term_translations(
		array(
			'de' => $cat_de,
			'en' => $cat_en,
		)
	),
	'term groups merge'
);
MrTu_Test::same( array( 'vi', 'en', 'de' ), array_keys( mrtu_get_term_translations( $cat_vi ) ), 'merged term group' );
wp_delete_term( $cat_de, 'category' );
MrTu_Test::same( array( 'vi', 'en' ), array_keys( mrtu_get_term_translations( $cat_vi ) ), 'deleted term leaves the group' );

MrTu_Test::finish();
