<?php
/**
 * Standalone SEO head (contract §2.8): <title>, meta description, robots directives, canonical,
 * hreflang, Open Graph and Twitter Card. Active only when Seo::is_standalone(); in compat mode
 * Yoast prints the head and Yoast_Compat adds hreflang / og:locale:alternate.
 *
 * Exactly one canonical, one description and one robots tag per page: core's `rel_canonical` is removed,
 * robots directives go through the core `wp_robots` filter (core prints the single meta tag), the title
 * through `pre_get_document_title` (the theme keeps `title-tag` support and never prints a <title>).
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Head output for standalone mode.
 */
final class Seo_Head {

	/**
	 * Wires hooks. Mode is checked lazily: Yoast may load after this plugin.
	 */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'setup' ), 0 );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), PHP_INT_MAX );
	}

	/**
	 * Replaces core's canonical/shortlink with our head block (standalone, HTML frontend only).
	 */
	public static function setup() {
		if ( ! self::is_active() ) {
			return;
		}
		remove_action( 'wp_head', 'rel_canonical' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		add_action( 'wp_head', array( __CLASS__, 'output' ), 1 );
	}

	/**
	 * Whether this request renders our head (standalone mode on a public HTML page).
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( ! Seo::is_standalone() || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( function_exists( 'wp_is_serving_rest_request' ) ? wp_is_serving_rest_request() : ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}
		if ( isset( $GLOBALS['wp_query'] ) && ( is_feed() || is_embed() || is_robots() || ( function_exists( 'is_sitemap' ) && is_sitemap() ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * `pre_get_document_title`: our title (escaped, core echoes it verbatim).
	 *
	 * @param string $title Title so far.
	 * @return string
	 */
	public static function document_title( $title ) {
		if ( ! self::is_active() ) {
			return $title;
		}
		$ours = Seo::title();
		return '' !== $ours ? esc_html( $ours ) : $title;
	}

	/**
	 * `wp_robots`: adds our noindex decisions (runs after Yoast's PHP_INT_MAX - 10 when Yoast is still active).
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( ! Seo::is_standalone() || ! is_array( $robots ) ) {
			return $robots;
		}
		if ( Seo::is_noindex() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}
		return $robots;
	}

	/**
	 * Prints the head block.
	 */
	public static function output() {
		echo self::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped in render().
	}

	/**
	 * Builds the head block (one tag per line; escaped).
	 *
	 * @return string
	 */
	public static function render() {
		$context     = Seo::context();
		$lang        = Languages::get( $context['lang'] );
		$title       = Seo::title( $context );
		$description = Seo::description( $context );
		$canonical   = Seo::canonical( $context );
		$noindex     = Seo::is_noindex( $context );
		$alternates  = $noindex ? array() : Seo::head_alternates();
		$lines       = array();

		if ( '' !== $description ) {
			$lines[] = '<meta name="description" content="' . esc_attr( $description ) . '">';
		}
		if ( '' !== $canonical ) {
			$lines[] = '<link rel="canonical" href="' . esc_url( $canonical ) . '">';
		}
		foreach ( $alternates as $hreflang => $url ) {
			$lines[] = '<link rel="alternate" hreflang="' . esc_attr( $hreflang ) . '" href="' . esc_url( $url ) . '">';
		}

		// Open Graph.
		$lines[] = '<meta property="og:locale" content="' . esc_attr( $lang ? $lang['og_locale'] : 'vi_VN' ) . '">';
		foreach ( self::alternate_og_locales( $alternates, $context['lang'] ) as $og_locale ) {
			$lines[] = '<meta property="og:locale:alternate" content="' . esc_attr( $og_locale ) . '">';
		}
		$is_article = 'singular' === $context['type'] && $context['post'] instanceof \WP_Post && 'post' === $context['post']->post_type;
		$lines[]    = '<meta property="og:type" content="' . ( $is_article ? 'article' : 'website' ) . '">';
		$lines[]    = '<meta property="og:title" content="' . esc_attr( $title ) . '">';
		if ( '' !== $description ) {
			$lines[] = '<meta property="og:description" content="' . esc_attr( $description ) . '">';
		}
		if ( '' !== $canonical ) {
			$lines[] = '<meta property="og:url" content="' . esc_url( $canonical ) . '">';
		}
		$lines[] = '<meta property="og:site_name" content="' . esc_attr( Seo::site_name() ) . '">';
		$image   = self::image( $context );
		if ( $image ) {
			$lines[] = '<meta property="og:image" content="' . esc_url( $image['url'] ) . '">';
			if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ) {
				$lines[] = '<meta property="og:image:width" content="' . esc_attr( (string) $image['width'] ) . '">';
				$lines[] = '<meta property="og:image:height" content="' . esc_attr( (string) $image['height'] ) . '">';
			}
			if ( '' !== $image['alt'] ) {
				$lines[] = '<meta property="og:image:alt" content="' . esc_attr( $image['alt'] ) . '">';
			}
		}
		if ( $is_article ) {
			$lines[] = '<meta property="article:published_time" content="' . esc_attr( self::iso_date( $context['post'], false ) ) . '">';
			$lines[] = '<meta property="article:modified_time" content="' . esc_attr( self::iso_date( $context['post'], true ) ) . '">';
		}

		// Twitter Card.
		$lines[] = '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">';
		$lines[] = '<meta name="twitter:title" content="' . esc_attr( $title ) . '">';
		if ( '' !== $description ) {
			$lines[] = '<meta name="twitter:description" content="' . esc_attr( $description ) . '">';
		}
		if ( $image ) {
			$lines[] = '<meta name="twitter:image" content="' . esc_url( $image['url'] ) . '">';
		}

		return "<!-- Mr Tu Multilang SEO -->\n" . implode( "\n", $lines ) . "\n<!-- / Mr Tu Multilang SEO -->\n";
	}

	/**
	 * Hreflang <link> tags only (used by Yoast_Compat inside Yoast's head block).
	 *
	 * @return string
	 */
	public static function render_alternates() {
		if ( Seo::is_noindex() ) {
			return '';
		}
		$lines = array();
		foreach ( Seo::head_alternates() as $hreflang => $url ) {
			$lines[] = '<link rel="alternate" hreflang="' . esc_attr( $hreflang ) . '" href="' . esc_url( $url ) . '">';
		}
		foreach ( self::alternate_og_locales( Seo::head_alternates(), Router::effective_language() ) as $og_locale ) {
			$lines[] = '<meta property="og:locale:alternate" content="' . esc_attr( $og_locale ) . '">';
		}
		return $lines ? implode( "\n", $lines ) . "\n" : '';
	}

	/**
	 * Open Graph locale values of the other languages present in the alternates.
	 *
	 * @param array  $alternates hreflang => url.
	 * @param string $current    Current language code.
	 * @return string[]
	 */
	public static function alternate_og_locales( array $alternates, $current ) {
		$out = array();
		foreach ( Languages::all( true ) as $code => $lang ) {
			if ( $code !== $current && isset( $alternates[ $lang['hreflang'] ] ) ) {
				$out[] = $lang['og_locale'];
			}
		}
		return $out;
	}

	/**
	 * Share image of the request: the featured image of a singular post (full size). No invented photos.
	 *
	 * @param array $context Context.
	 * @return array|null [ url, width, height, alt ]
	 */
	public static function image( array $context ) {
		if ( ! in_array( $context['type'], array( 'front', 'home', 'singular' ), true ) || ! $context['post'] instanceof \WP_Post ) {
			return null;
		}
		$thumbnail = get_post_thumbnail_id( $context['post'] );
		if ( ! $thumbnail ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $thumbnail, 'full' );
		if ( ! $src || empty( $src[0] ) ) {
			return null;
		}
		return array(
			'url'    => (string) $src[0],
			'width'  => isset( $src[1] ) ? (int) $src[1] : 0,
			'height' => isset( $src[2] ) ? (int) $src[2] : 0,
			'alt'    => Seo::plain_text( (string) get_post_meta( $thumbnail, '_wp_attachment_image_alt', true ) ),
		);
	}

	/**
	 * ISO 8601 date with the site timezone offset (e.g. +07:00).
	 *
	 * @param \WP_Post $post     Post.
	 * @param bool     $modified Modified date instead of publish date.
	 * @return string
	 */
	public static function iso_date( \WP_Post $post, $modified = false ) {
		$timestamp = get_post_timestamp( $post, $modified ? 'modified' : 'date' );
		return $timestamp ? (string) wp_date( 'c', $timestamp ) : '';
	}
}
