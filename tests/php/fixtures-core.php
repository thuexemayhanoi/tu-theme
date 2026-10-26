<?php
/**
 * Idempotent fixtures for tests/http/test-routing.sh (persisted in the test env, never production).
 *
 * Usage (WP-CLI):
 *   wp eval-file tests/php/fixtures-core.php            create/refresh fixtures, print FX_* lines
 *   wp eval-file tests/php/fixtures-core.php disable es disable a language (enable es to restore)
 *   wp eval-file tests/php/fixtures-core.php cookie     print a logged-in cookie for user 1
 *
 * Every fixture carries post/term meta `_mrtu_fixture` = key, so re-running finds instead of duplicating.
 *
 * @package MrTu\Multilang\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DB.SlowDBQuery -- CLI fixture script.

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'mrtu_languages' ) ) {
	echo "ERROR: run with wp eval-file while mr-tu-multilang-seo is active\n";
	exit( 1 );
}

$mrtu_fx_args = isset( $args ) && is_array( $args ) ? array_values( $args ) : array();
$mrtu_fx_cmd  = isset( $mrtu_fx_args[0] ) ? $mrtu_fx_args[0] : 'create';

if ( 'disable' === $mrtu_fx_cmd || 'enable' === $mrtu_fx_cmd ) {
	$mrtu_fx_code   = isset( $mrtu_fx_args[1] ) ? sanitize_key( $mrtu_fx_args[1] ) : '';
	$mrtu_fx_result = \MrTu\Multilang\Languages::validate( array( 'languages' => array( $mrtu_fx_code => array( 'enabled' => 'enable' === $mrtu_fx_cmd ? '1' : '0' ) ) ) );
	\MrTu\Multilang\Languages::update_settings( $mrtu_fx_result['settings'] );
	echo 'FX_LANG_' . strtoupper( $mrtu_fx_code ) . '=' . ( mrtu_is_language_enabled( $mrtu_fx_code ) ? 'on' : 'off' ) . "\n";
	return;
}

if ( 'cookie' === $mrtu_fx_cmd ) {
	$mrtu_fx_expire = time() + HOUR_IN_SECONDS;
	echo 'FX_COOKIE=' . LOGGED_IN_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( 1, $mrtu_fx_expire, 'logged_in' ) ) . "\n";
	return;
}

/**
 * Finds or creates a fixture post.
 *
 * @param string $key  Fixture key.
 * @param array  $args wp_insert_post() args.
 * @param string $lang Language.
 * @return int
 */
function mrtu_fx_post( $key, array $args, $lang ) {
	$found = get_posts(
		array(
			'post_type'        => 'any',
			'post_status'      => 'any',
			'meta_key'         => '_mrtu_fixture',
			'meta_value'       => $key,
			'fields'           => 'ids',
			'numberposts'      => 1,
			'suppress_filters' => true,
			'mrtu_lang'        => 'all',
		)
	);
	if ( $found ) {
		$id = (int) $found[0];
		if ( isset( $args['post_status'] ) && get_post_status( $id ) !== $args['post_status'] ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => $args['post_status'],
				)
			);
		}
		return $id;
	}
	$args = array_merge(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_author' => 1,
			'mrtu_lang'   => $lang,
			'meta_input'  => array( '_mrtu_fixture' => $key ),
		),
		$args
	);
	$id   = wp_insert_post( wp_slash( $args ), true );
	if ( is_wp_error( $id ) ) {
		echo 'ERROR: ' . $key . ': ' . $id->get_error_message() . "\n";
		exit( 1 );
	}
	return (int) $id;
}

/**
 * Finds or creates a fixture category.
 *
 * @param string $key  Fixture key.
 * @param string $name Name.
 * @param string $slug Slug.
 * @param string $lang Language.
 * @return int
 */
function mrtu_fx_category( $key, $name, $slug, $lang ) {
	$term = get_term_by( 'slug', $slug, 'category' );
	if ( ! $term ) {
		$result = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		if ( is_wp_error( $result ) ) {
			echo 'ERROR: ' . $key . ': ' . $result->get_error_message() . "\n";
			exit( 1 );
		}
		$term = get_term( $result['term_id'], 'category' );
		update_term_meta( $term->term_id, '_mrtu_fixture', $key );
	}
	mrtu_set_term_language( $term->term_id, $lang );
	return (int) $term->term_id;
}

/**
 * Paragraph block.
 *
 * @param string $text Text.
 * @return string
 */
function mrtu_fx_p( $text ) {
	return '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->';
}

$mrtu_fx_switcher = '<!-- wp:shortcode -->[mrtu_language_switcher display="both"]<!-- /wp:shortcode -->';

// Home pages.
$mrtu_fx_home_slugs = array(
	'vi' => array( 'trang-chu', 'Thuê xe máy Hà Nội – Mr Tu' ),
	'en' => array( 'home', 'Motorbike rental in Hanoi – Mr Tu' ),
	'de' => array( 'startseite', 'Motorrad mieten in Hanoi – Mr Tu' ),
	'fr' => array( 'accueil', 'Location de moto à Hanoï – Mr Tu' ),
	'es' => array( 'inicio', 'Alquiler de motos en Hanói – Mr Tu' ),
);
$mrtu_fx_homes      = array();
$mrtu_fx_blogs      = array();
$mrtu_fx_blog_slugs = array(
	'vi' => array( 'cam-nang', 'Cẩm nang' ),
	'en' => array( 'blog', 'Blog' ),
	'de' => array( 'blog', 'Blog' ),
	'fr' => array( 'blog', 'Blog' ),
	'es' => array( 'blog', 'Blog' ),
);
foreach ( $mrtu_fx_home_slugs as $mrtu_fx_code => $mrtu_fx_home ) {
	$mrtu_fx_homes[ $mrtu_fx_code ] = mrtu_fx_post(
		'home-' . $mrtu_fx_code,
		array(
			'post_name'    => $mrtu_fx_home[0],
			'post_title'   => $mrtu_fx_home[1],
			'post_content' => mrtu_fx_p( 'FX-HOME-' . strtoupper( $mrtu_fx_code ) ) . $mrtu_fx_switcher,
		),
		$mrtu_fx_code
	);
	$mrtu_fx_blogs[ $mrtu_fx_code ] = mrtu_fx_post(
		'blog-' . $mrtu_fx_code,
		array(
			'post_name'  => $mrtu_fx_blog_slugs[ $mrtu_fx_code ][0],
			'post_title' => $mrtu_fx_blog_slugs[ $mrtu_fx_code ][1],
		),
		$mrtu_fx_code
	);
}
mrtu_link_translations( $mrtu_fx_homes );
mrtu_link_translations( $mrtu_fx_blogs );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $mrtu_fx_homes['vi'] );
update_option( 'page_for_posts', $mrtu_fx_blogs['vi'] );
mrtu_set_post_role( $mrtu_fx_homes['vi'], 'home' );
mrtu_set_post_role( $mrtu_fx_blogs['vi'], 'blog' );

// FAQ in four languages with the SAME slug "faq" (VI uses its own slug).
$mrtu_fx_faq = array(
	'vi' => mrtu_fx_post(
		'faq-vi',
		array(
			'post_name'    => 'cau-hoi-thuong-gap',
			'post_title'   => 'Câu hỏi thường gặp',
			'post_content' => mrtu_fx_p( 'FX-FAQ-VI' ) . $mrtu_fx_switcher,
		),
		'vi'
	),
	'en' => mrtu_fx_post(
		'faq-en',
		array(
			'post_name'    => 'faq',
			'post_title'   => 'Frequently asked questions',
			'post_content' => mrtu_fx_p( 'FX-FAQ-EN' ) . $mrtu_fx_switcher,
		),
		'en'
	),
	'de' => mrtu_fx_post(
		'faq-de',
		array(
			'post_name'    => 'faq',
			'post_title'   => 'Häufige Fragen',
			'post_content' => mrtu_fx_p( 'FX-FAQ-DE' ) . $mrtu_fx_switcher,
		),
		'de'
	),
	'fr' => mrtu_fx_post(
		'faq-fr',
		array(
			'post_name'    => 'faq',
			'post_title'   => 'Questions fréquentes',
			'post_content' => mrtu_fx_p( 'FX-FAQ-FR' ) . $mrtu_fx_switcher,
		),
		'fr'
	),
);
mrtu_link_translations( $mrtu_fx_faq );
mrtu_set_post_role( $mrtu_fx_faq['vi'], 'guide-faq' );

// One page per language that exists only there.
$mrtu_fx_de_only = mrtu_fx_post(
	'de-only',
	array(
		'post_name'    => 'nur-auf-deutsch',
		'post_title'   => 'Nur auf Deutsch',
		'post_content' => mrtu_fx_p( 'FX-DE-ONLY' ),
	),
	'de'
);
$mrtu_fx_vi_only = mrtu_fx_post(
	'vi-only',
	array(
		'post_name'    => 'chi-tieng-viet',
		'post_title'   => 'Chỉ có tiếng Việt',
		'post_content' => mrtu_fx_p( 'FX-VI-ONLY' ),
	),
	'vi'
);

// Categories and posts (12 EN posts → the EN posts page has a page 2).
$mrtu_fx_cat_en = mrtu_fx_category( 'cat-en', 'Rental tips', 'rental-tips', 'en' );
$mrtu_fx_cat_vi = mrtu_fx_category( 'cat-vi', 'Mẹo thuê xe', 'meo-thue-xe', 'vi' );
mrtu_link_term_translations(
	array(
		'vi' => $mrtu_fx_cat_vi,
		'en' => $mrtu_fx_cat_en,
	)
);
for ( $mrtu_fx_i = 1; $mrtu_fx_i <= 12; $mrtu_fx_i++ ) {
	mrtu_fx_post(
		'en-post-' . $mrtu_fx_i,
		array(
			'post_type'     => 'post',
			'post_name'     => 'fx-en-tip-' . $mrtu_fx_i,
			'post_title'    => 'FX-EN-POST ' . $mrtu_fx_i . ( 1 === $mrtu_fx_i ? ' English test article' : '' ),
			'post_content'  => mrtu_fx_p( 'English riding tip number ' . $mrtu_fx_i . '.' ),
			'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '2026-09-01 08:00:00' ) + $mrtu_fx_i * HOUR_IN_SECONDS ),
			'post_category' => array( $mrtu_fx_cat_en ),
		),
		'en'
	);
}
mrtu_fx_post(
	'vi-post-1',
	array(
		'post_type'     => 'post',
		'post_name'     => 'fx-meo-1',
		'post_title'    => 'FX-VI-POST Bài test tiếng Việt',
		'post_content'  => mrtu_fx_p( 'Mẹo đi xe máy ở Hà Nội.' ),
		// Deliberately also filed under the EN category: it must not leak into /en/category/rental-tips/.
		'post_category' => array( $mrtu_fx_cat_vi, $mrtu_fx_cat_en ),
	),
	'vi'
);
$mrtu_fx_draft = mrtu_fx_post(
	'en-draft',
	array(
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'FX-EN-DRAFT preview me',
		'post_content' => mrtu_fx_p( 'Draft body FX-EN-DRAFT-BODY.' ),
	),
	'en'
);

echo 'FX_EN_DRAFT_ID=' . $mrtu_fx_draft . "\n";
echo 'FX_EN_DRAFT_PREVIEW=' . wp_make_link_relative( get_preview_post_link( $mrtu_fx_draft ) ) . "\n";
echo 'FX_VI_ONLY_SLUG=' . get_post_field( 'post_name', $mrtu_fx_vi_only ) . "\n";
echo 'FX_DE_ONLY_SLUG=' . get_post_field( 'post_name', $mrtu_fx_de_only ) . "\n";
echo 'FX_EN_FAQ_SLUG=' . get_post_field( 'post_name', $mrtu_fx_faq['en'] ) . "\n";
echo 'FX_FR_FAQ_SLUG=' . get_post_field( 'post_name', $mrtu_fx_faq['fr'] ) . "\n";
echo 'FX_DE_FAQ_SLUG=' . get_post_field( 'post_name', $mrtu_fx_faq['de'] ) . "\n";
echo 'FX_EN_BLOG_SLUG=' . get_post_field( 'post_name', $mrtu_fx_blogs['en'] ) . "\n";
echo 'FX_EN_HOME_SLUG=' . get_post_field( 'post_name', $mrtu_fx_homes['en'] ) . "\n";
echo 'FX_VI_POST_ID=' . (int) get_posts(
	array(
		'meta_key'         => '_mrtu_fixture',
		'meta_value'       => 'vi-post-1',
		'fields'           => 'ids',
		'numberposts'      => 1,
		'suppress_filters' => true,
	)
)[0] . "\n";
echo 'FX_EN_CATEGORY=' . get_term_field( 'slug', $mrtu_fx_cat_en, 'category' ) . "\n";
