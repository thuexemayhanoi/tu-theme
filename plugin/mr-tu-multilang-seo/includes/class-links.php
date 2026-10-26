<?php
/**
 * Language-aware links (contract 2.6).
 *
 * Objects with a language get the prefix of THEIR language; context links (search, dates, authors, feeds,
 * pagination, archives) get the prefix of the current language. The core `home_url` is never filtered.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Permalink filters + URL helpers.
 */
final class Links {

	/**
	 * Wires hooks (all contexts: permalinks are the same in admin, REST, feeds and sitemaps).
	 */
	public static function init() {
		add_filter( 'post_link', array( __CLASS__, 'post_link' ), 10, 2 );
		add_filter( 'post_type_link', array( __CLASS__, 'post_link' ), 10, 2 );
		add_filter( 'page_link', array( __CLASS__, 'page_link' ), 10, 3 );
		add_filter( 'attachment_link', array( __CLASS__, 'attachment_link' ), 10, 1 );
		add_filter( 'term_link', array( __CLASS__, 'term_link' ), 10, 3 );
		add_filter( 'post_type_archive_link', array( __CLASS__, 'post_type_archive_link' ), 10, 2 );
		foreach ( array( 'search_link', 'get_pagenum_link', 'feed_link', 'author_link', 'year_link', 'month_link', 'day_link' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'context_link' ), 10, 1 );
		}
	}

	/*
	 * URL helpers
	 */

	/**
	 * Home URL of a language ("/" or "/{prefix}/").
	 *
	 * @param string $lang Language code.
	 * @return string
	 */
	public static function language_home( $lang ) {
		$language = Languages::get( $lang );
		$home     = home_url( '/' );
		return $language && '' !== $language['prefix'] ? $home . $language['prefix'] . '/' : $home;
	}

	/**
	 * Splits a URL into the home base (scheme://host/home-path/) and the rest.
	 *
	 * @param string $url URL.
	 * @return array|null [ base, rest ] or null when the URL is not under home.
	 */
	public static function split( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}
		$home = home_url( '/' );
		$bare = preg_replace( '#^https?:#i', '', $url );
		$hbar = preg_replace( '#^https?:#i', '', $home );
		if ( 0 === stripos( (string) $bare, (string) $hbar ) ) {
			$len = strlen( $url ) - strlen( (string) $bare ) + strlen( (string) $hbar );
			return array( substr( $url, 0, $len ), (string) substr( $url, $len ) );
		}
		if ( $bare . '/' === $hbar ) {
			return array( $url . '/', '' );
		}
		return null;
	}

	/**
	 * Removes a leading language prefix from the part of a URL after the home base.
	 *
	 * @param string $rest URL rest.
	 * @return string
	 */
	public static function strip_language_prefix( $rest ) {
		foreach ( Languages::all( false ) as $lang ) {
			$prefix = $lang['prefix'];
			if ( '' === $prefix ) {
				continue;
			}
			if ( $rest === $prefix ) {
				return '';
			}
			if ( 0 === strpos( $rest, $prefix . '/' ) ) {
				return (string) substr( $rest, strlen( $prefix ) + 1 );
			}
			if ( 0 === strpos( $rest, $prefix . '?' ) || 0 === strpos( $rest, $prefix . '#' ) ) {
				return (string) substr( $rest, strlen( $prefix ) );
			}
		}
		return $rest;
	}

	/**
	 * Rewrites a site URL into a language (idempotent: an existing prefix is replaced).
	 *
	 * @param string $url  URL.
	 * @param string $lang Language code.
	 * @return string
	 */
	public static function to_language( $url, $lang ) {
		$parts = self::split( $url );
		if ( null === $parts ) {
			return $url;
		}
		$rest     = self::strip_language_prefix( $parts[1] );
		$language = Languages::get( $lang );
		if ( $language && '' !== $language['prefix'] ) {
			$rest = $language['prefix'] . '/' . ltrim( $rest, '/' );
		}
		return $parts[0] . $rest;
	}

	/**
	 * Language of a site URL (by prefix).
	 *
	 * @param string $url URL.
	 * @return string|null Null when the URL is not under home.
	 */
	public static function language_of_url( $url ) {
		$parts = self::split( $url );
		if ( null === $parts ) {
			return null;
		}
		$segment = strtok( $parts[1], '/?#' );
		foreach ( Languages::all( false ) as $code => $lang ) {
			if ( '' !== $lang['prefix'] && $segment === $lang['prefix'] ) {
				return $code;
			}
		}
		return Languages::default_code();
	}

	/**
	 * Language home with an optional path (contract `mrtu_home_url()`); disabled languages fall back to default.
	 *
	 * @param string      $path Path relative to the language home.
	 * @param string|null $lang Language (null = current).
	 * @return string
	 */
	public static function home_url( $path = '', $lang = null ) {
		$lang = null === $lang ? Router::effective_language() : (string) $lang;
		if ( ! Languages::is_enabled( $lang ) ) {
			$lang = Languages::default_code();
		}
		return self::language_home( $lang ) . ltrim( (string) $path, '/' );
	}

	/**
	 * Whether a post belongs to the translation group of the (raw) static front page.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	public static function is_front_page_group( $post_id ) {
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return false;
		}
		$front = Router::raw_option( 'page_on_front' );
		return $front && Translations::in_same_group( (int) $post_id, $front );
	}

	/*
	 * Filters
	 */

	/**
	 * Posts and custom post types: prefix of the post's language.
	 *
	 * @param string       $url  Permalink.
	 * @param \WP_Post|int $post Post.
	 * @return string
	 */
	public static function post_link( $url, $post ) {
		$post = get_post( $post );
		if ( ! $post || ! Translations::is_translatable_post_type( $post->post_type ) ) {
			return $url;
		}
		return self::to_language( $url, Translations::get_post_language( $post ) );
	}

	/**
	 * Pages: prefix of the page's language; any published page of the front page group → its language home.
	 *
	 * @param string $url     Permalink.
	 * @param int    $post_id Page id.
	 * @param bool   $sample  Sample permalink.
	 * @return string
	 */
	public static function page_link( $url, $post_id, $sample = false ) {
		$post = get_post( $post_id );
		if ( ! $post || ! Translations::is_translatable_post_type( $post->post_type ) ) {
			return $url;
		}
		$lang = Translations::get_post_language( $post );
		if ( ( 'publish' === $post->post_status || $sample ) && self::is_front_page_group( $post->ID ) ) {
			return self::language_home( $lang );
		}
		return self::to_language( $url, $lang );
	}

	/**
	 * Attachments are language-neutral: never prefixed.
	 *
	 * @param string $url Attachment URL.
	 * @return string
	 */
	public static function attachment_link( $url ) {
		return self::to_language( $url, Languages::default_code() );
	}

	/**
	 * Terms of translatable taxonomies: prefix of the term's language.
	 *
	 * @param string   $url      Term link.
	 * @param \WP_Term $term     Term.
	 * @param string   $taxonomy Taxonomy.
	 * @return string
	 */
	public static function term_link( $url, $term, $taxonomy ) {
		if ( ! Translations::is_translatable_taxonomy( $taxonomy ) ) {
			return $url;
		}
		return self::to_language( $url, Translations::get_term_language( $term ) );
	}

	/**
	 * Post type archives: current language; the posts archive uses the translated posts page.
	 *
	 * @param string $url       Archive URL.
	 * @param string $post_type Post type.
	 * @return string
	 */
	public static function post_type_archive_link( $url, $post_type ) {
		$lang = Router::current_language();
		if ( '' === $lang ) {
			return $url;
		}
		if ( 'post' === $post_type && 'page' === get_option( 'show_on_front' ) ) {
			$blog = Router::raw_option( 'page_for_posts' );
			if ( $blog ) {
				$id = Translations::get_post_translation( $blog, $lang );
				return $id ? (string) get_permalink( $id ) : self::language_home( $lang );
			}
		}
		return self::to_language( $url, $lang );
	}

	/**
	 * Context links (search, pagination, feeds, author, date): current language.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function context_link( $url ) {
		$lang = Router::current_language();
		return '' === $lang ? $url : self::to_language( $url, $lang );
	}
}
