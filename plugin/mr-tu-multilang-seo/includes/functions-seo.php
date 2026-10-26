<?php
/**
 * Public SEO API (contract §2.4 / §2.8). Themes and companion plugins call ONLY these functions,
 * always behind function_exists() checks.
 *
 * @package MrTu\Multilang
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mrtu_is_seo_standalone' ) ) {
	/**
	 * Whether the plugin prints the whole SEO head itself (no Yoast, or Yoast switched off by the owner).
	 *
	 * @return bool
	 */
	function mrtu_is_seo_standalone() {
		return \MrTu\Multilang\Seo::is_standalone();
	}
}

if ( ! function_exists( 'mrtu_seo_mode' ) ) {
	/**
	 * Effective SEO mode.
	 *
	 * @return string 'compat' (Yoast prints the head, we add hreflang) or 'standalone'.
	 */
	function mrtu_seo_mode() {
		return \MrTu\Multilang\Seo::mode();
	}
}

if ( ! function_exists( 'mrtu_seo_title' ) ) {
	/**
	 * Document title as raw text (escape on output).
	 *
	 * @param mixed $context null = current request; int|WP_Post = that post; WP_Term = that term.
	 * @return string
	 */
	function mrtu_seo_title( $context = null ) {
		return \MrTu\Multilang\Seo::title( $context );
	}
}

if ( ! function_exists( 'mrtu_seo_description' ) ) {
	/**
	 * Meta description as raw text ('' when none; never print an empty tag).
	 *
	 * @param mixed $context null = current request; int|WP_Post = that post; WP_Term = that term.
	 * @return string
	 */
	function mrtu_seo_description( $context = null ) {
		return \MrTu\Multilang\Seo::description( $context );
	}
}

if ( ! function_exists( 'mrtu_canonical_url' ) ) {
	/**
	 * Canonical URL of the current request ('' on search and 404 pages).
	 *
	 * @param mixed $context null = current request; int|WP_Post = that post; WP_Term = that term.
	 * @return string
	 */
	function mrtu_canonical_url( $context = null ) {
		return \MrTu\Multilang\Seo::canonical( $context );
	}
}

if ( ! function_exists( 'mrtu_sitemap_flush' ) ) {
	/**
	 * Drops the cached sitemaps (they are rebuilt on the next request).
	 */
	function mrtu_sitemap_flush() {
		\MrTu\Multilang\Sitemaps::flush();
	}
}

if ( ! function_exists( 'mrtu_schema_graph' ) ) {
	/**
	 * JSON-LD @graph nodes of the current request (filtered through `mrtu_schema_graph`).
	 *
	 * @return array[]
	 */
	function mrtu_schema_graph() {
		return \MrTu\Multilang\Schema::graph();
	}
}
