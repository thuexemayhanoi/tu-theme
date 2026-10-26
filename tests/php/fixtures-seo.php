<?php
/**
 * Idempotent SEO fixtures for tests/http/test-seo.sh (persisted in the test env, never production).
 *
 * Creates the VI + EN + DE home / pillar / guide-faq pages from the seed files in content/<lang>/
 * (title, slug, SEO title, description, focus keyword, block content), links them as translations,
 * sets the page roles and makes the VI home the static front page. Prints FX_SEO_* lines.
 *
 * Usage (WP-CLI): wp eval-file tests/php/fixtures-seo.php
 *
 * Every fixture carries post meta `_mrtu_fixture` = key, so re-running refreshes instead of duplicating.
 *
 * @package MrTu\Multilang\Tests
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.DB.SlowDBQuery -- CLI fixture script.

if ( ! defined( 'ABSPATH' ) || ! function_exists( 'mrtu_languages' ) ) {
	echo "ERROR: run with wp eval-file while mr-tu-multilang-seo is active\n";
	exit( 1 );
}

if ( ! function_exists( 'mrtu_fxseo_seed' ) ) {
	/**
	 * Reads one seed page (by role) of a language from content/<lang>/pages.json.
	 *
	 * @param string $lang Language code.
	 * @param string $role Page role.
	 * @return array|null Entry plus 'content'.
	 */
	function mrtu_fxseo_seed( $lang, $role ) {
		$dir   = dirname( __DIR__, 2 ) . '/content/' . $lang . '/';
		$pages = json_decode( (string) file_get_contents( $dir . 'pages.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $pages ) ) {
			return null;
		}
		foreach ( $pages as $page ) {
			if ( is_array( $page ) && isset( $page['role'] ) && $role === $page['role'] ) {
				$page['content'] = file_exists( $dir . $page['file'] ) ? (string) file_get_contents( $dir . $page['file'] ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				return $page;
			}
		}
		return null;
	}

	/**
	 * Finds or creates a fixture page and refreshes its content and SEO meta.
	 *
	 * @param string $key  Fixture key.
	 * @param array  $seed Seed entry (title, slug, content, seo_title, seo_desc, focus_kw).
	 * @param string $slug Slug to use.
	 * @param string $lang Language.
	 * @return int
	 */
	function mrtu_fxseo_post( $key, array $seed, $slug, $lang ) {
		$found = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'any',
				'meta_key'         => '_mrtu_fixture',
				'meta_value'       => $key,
				'fields'           => 'ids',
				'numberposts'      => 1,
				'suppress_filters' => true,
				'mrtu_lang'        => 'all',
			)
		);
		$args  = array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_title'   => $seed['title'],
			'post_name'    => $slug,
			'post_excerpt' => isset( $seed['excerpt'] ) ? $seed['excerpt'] : '',
			'post_content' => $seed['content'],
		);
		if ( $found ) {
			$id         = (int) $found[0];
			$args['ID'] = $id;
			wp_update_post( wp_slash( $args ) );
		} else {
			$args['mrtu_lang']  = $lang;
			$args['meta_input'] = array( '_mrtu_fixture' => $key );
			$id                 = wp_insert_post( wp_slash( $args ), true );
			if ( is_wp_error( $id ) ) {
				echo 'ERROR: ' . $key . ': ' . $id->get_error_message() . "\n";
				exit( 1 );
			}
			$id = (int) $id;
		}
		update_post_meta( $id, '_mrtu_seo_title', isset( $seed['seo_title'] ) ? $seed['seo_title'] : '' );
		update_post_meta( $id, '_mrtu_seo_desc', isset( $seed['seo_desc'] ) ? $seed['seo_desc'] : '' );
		update_post_meta( $id, '_mrtu_focus_kw', isset( $seed['focus_kw'] ) ? $seed['focus_kw'] : '' );
		delete_post_meta( $id, '_mrtu_noindex' );
		return $id;
	}
}

$mrtu_fxseo_home_slugs = array(
	'vi' => 'trang-chu-mr-tu',
	'en' => 'home-mr-tu',
	'de' => 'startseite-mr-tu',
);
$mrtu_fxseo_ids        = array();

foreach ( array( 'home', 'pillar', 'guide-faq' ) as $mrtu_fxseo_role ) {
	$mrtu_fxseo_ids[ $mrtu_fxseo_role ] = array();
	foreach ( array( 'vi', 'en', 'de' ) as $mrtu_fxseo_lang ) {
		$mrtu_fxseo_seed = mrtu_fxseo_seed( $mrtu_fxseo_lang, $mrtu_fxseo_role );
		if ( ! $mrtu_fxseo_seed ) {
			echo 'ERROR: no seed for ' . $mrtu_fxseo_role . ' in ' . $mrtu_fxseo_lang . "\n";
			exit( 1 );
		}
		$mrtu_fxseo_slug = '' !== (string) $mrtu_fxseo_seed['slug'] ? (string) $mrtu_fxseo_seed['slug'] : $mrtu_fxseo_home_slugs[ $mrtu_fxseo_lang ];

		$mrtu_fxseo_ids[ $mrtu_fxseo_role ][ $mrtu_fxseo_lang ] = mrtu_fxseo_post( 'seo-' . $mrtu_fxseo_role . '-' . $mrtu_fxseo_lang, $mrtu_fxseo_seed, $mrtu_fxseo_slug, $mrtu_fxseo_lang );
	}
	mrtu_link_translations( $mrtu_fxseo_ids[ $mrtu_fxseo_role ] );
}

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $mrtu_fxseo_ids['home']['vi'] );
foreach ( array( 'home', 'pillar', 'guide-faq' ) as $mrtu_fxseo_role ) {
	mrtu_set_post_role( $mrtu_fxseo_ids['home' === $mrtu_fxseo_role ? 'home' : $mrtu_fxseo_role ]['vi'], $mrtu_fxseo_role );
}
if ( function_exists( 'mrtu_sitemap_flush' ) ) {
	mrtu_sitemap_flush();
}

foreach ( $mrtu_fxseo_ids as $mrtu_fxseo_role => $mrtu_fxseo_langs ) {
	foreach ( $mrtu_fxseo_langs as $mrtu_fxseo_lang => $mrtu_fxseo_id ) {
		$mrtu_fxseo_name = strtoupper( str_replace( 'guide-faq', 'faq', $mrtu_fxseo_role ) . '_' . $mrtu_fxseo_lang );
		echo 'FX_SEO_' . $mrtu_fxseo_name . '_ID=' . $mrtu_fxseo_id . "\n";
		echo 'FX_SEO_' . $mrtu_fxseo_name . '_PATH=' . wp_make_link_relative( get_permalink( $mrtu_fxseo_id ) ) . "\n";
	}
}
