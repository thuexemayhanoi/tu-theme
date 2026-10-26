<?php
/**
 * XML sitemaps (contract §2.8 + amendment 6): `/mrtu-sitemap-index.xml` → `/mrtu-sitemap-{lang}.xml`,
 * every URL with `<xhtml:link rel="alternate" hreflang>` from the same alternates resolver as the head
 * (Seo::object_alternates()). Only published, indexable, non-password URLs of enabled languages;
 * `lastmod` = `post_modified_gmt`; one transient per language, flushed on content / term / settings changes.
 *
 * Served when Seo::sitemap_enabled() (auto = standalone mode). In standalone mode core's `/wp-sitemap.xml`
 * is switched off; in compat mode Yoast's sitemap stays and Yoast_Compat adds the alternates there.
 * The index path avoids Yoast's `([^/]+?)-sitemap\.xml` rule; the children live at the root like the index.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Sitemap index + per-language sitemaps.
 */
final class Sitemaps {

	const QUERY_VAR = 'mrtu_sitemap';
	const PREFIX    = 'mrtu-sitemap-';
	const INDEX     = 'index';
	const TRANSIENT = 'mrtu_sitemap_';
	const TTL       = 12 * HOUR_IN_SECONDS;

	/**
	 * Google's per-file URL limit.
	 */
	const MAX_URLS = 50000;

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrites' ), 5 );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
		add_action( 'parse_request', array( __CLASS__, 'maybe_render' ), 5 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10, 2 );
		add_filter( 'wp_sitemaps_enabled', array( __CLASS__, 'core_sitemaps' ) );

		// Cache invalidation.
		add_action( 'save_post', array( __CLASS__, 'on_post_change' ), 99, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 99, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'on_post_change' ), 99, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'flush' ) );
		add_action( 'created_term', array( __CLASS__, 'on_term_change' ), 99, 3 );
		add_action( 'edited_term', array( __CLASS__, 'on_term_change' ), 99, 3 );
		add_action( 'delete_term', array( __CLASS__, 'on_term_change' ), 99, 3 );
		add_action( 'set_object_terms', array( __CLASS__, 'flush' ) );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta' ), 10, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'on_meta' ), 10, 3 );
		add_action( 'update_option_' . Languages::OPTION, array( __CLASS__, 'flush' ) );
		add_action( 'update_option_show_on_front', array( __CLASS__, 'flush' ) );
		add_action( 'update_option_page_on_front', array( __CLASS__, 'flush' ) );
		add_action( 'update_option_permalink_structure', array( __CLASS__, 'flush' ) );
		add_action( 'mrtu_translations_linked', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Activation: rules exist → flush once.
	 */
	public static function activate() {
		self::register_rewrites();
		flush_rewrite_rules();
		self::flush();
	}

	/**
	 * Deactivation: drop caches and our rules.
	 */
	public static function deactivate() {
		self::flush();
		flush_rewrite_rules();
	}

	/*
	 * Routing
	 */

	/**
	 * Registers `^mrtu-sitemap-(index|<lang>)\.xml$`.
	 */
	public static function register_rewrites() {
		add_rewrite_rule( '^' . preg_quote( self::PREFIX, '#' ) . '([a-z]{2,5})\.xml$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Registers the query var.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public static function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * URL of the index or of a language sitemap.
	 *
	 * @param string $which 'index' or a language code.
	 * @return string
	 */
	public static function url( $which = self::INDEX ) {
		return home_url( '/' . self::PREFIX . sanitize_key( (string) $which ) . '.xml' );
	}

	/**
	 * Serves the sitemap before the main query runs (`parse_request`).
	 *
	 * The query var only counts on its own path at the site root (never `/?mrtu_sitemap=…` or `/en/mrtu-…`).
	 *
	 * @param \WP $wp WordPress environment.
	 */
	public static function maybe_render( $wp ) {
		if ( ! $wp instanceof \WP || empty( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			return;
		}
		$which = sanitize_key( (string) $wp->query_vars[ self::QUERY_VAR ] );
		$path  = isset( $_SERVER['REQUEST_URI'] ) ? Router::relative_path( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared, never output.
		if ( $path !== self::PREFIX . $which . '.xml' ) {
			unset( $wp->query_vars[ self::QUERY_VAR ] ); // Not a sitemap URL: the var is noise.
			return;
		}
		if ( ! Seo::sitemap_enabled() || ( self::INDEX !== $which && ! Languages::is_enabled( $which ) ) ) {
			$wp->query_vars = array( 'error' => '404' );
			return;
		}
		$xml = self::INDEX === $which ? self::index_xml() : self::language_xml( $which );
		if ( '' === $xml ) {
			$wp->query_vars = array( 'error' => '404' );
			return;
		}
		self::send( $xml );
	}

	/**
	 * Sends XML and stops.
	 *
	 * @param string $xml Document.
	 */
	private static function send( $xml ) {
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow', true );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML escaped while building.
		exit;
	}

	/*
	 * Documents
	 */

	/**
	 * Sitemap index XML ('' when no language has content).
	 *
	 * @return string
	 */
	public static function index_xml() {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		$any = false;
		foreach ( Languages::codes( true ) as $code ) {
			$data = self::language_data( $code );
			if ( empty( $data['count'] ) ) {
				continue;
			}
			$any  = true;
			$xml .= "\t<sitemap>\n\t\t<loc>" . esc_xml( self::url( $code ) ) . "</loc>\n";
			if ( '' !== $data['lastmod'] ) {
				$xml .= "\t\t<lastmod>" . esc_xml( $data['lastmod'] ) . "</lastmod>\n";
			}
			$xml .= "\t</sitemap>\n";
		}
		return $any ? $xml . '</sitemapindex>' . "\n" : '';
	}

	/**
	 * One language's urlset XML ('' when empty).
	 *
	 * @param string $lang Language code.
	 * @return string
	 */
	public static function language_xml( $lang ) {
		$data = self::language_data( $lang );
		return empty( $data['count'] ) ? '' : $data['xml'];
	}

	/**
	 * Cached [ xml, lastmod, count ] of a language.
	 *
	 * @param string $lang Language code.
	 * @return array
	 */
	public static function language_data( $lang ) {
		$lang = sanitize_key( (string) $lang );
		$data = get_transient( self::TRANSIENT . $lang );
		if ( is_array( $data ) && isset( $data['xml'], $data['lastmod'], $data['count'] ) ) {
			return $data;
		}
		$entries = Languages::is_enabled( $lang ) ? self::entries( $lang ) : array();
		$lastmod = '';
		foreach ( $entries as $entry ) {
			if ( '' !== $entry['lastmod'] && strcmp( $entry['lastmod'], $lastmod ) > 0 ) {
				$lastmod = $entry['lastmod'];
			}
		}
		$data = array(
			'xml'     => $entries ? self::urlset_xml( $entries ) : '',
			'lastmod' => $lastmod,
			'count'   => count( $entries ),
		);
		set_transient( self::TRANSIENT . $lang, $data, self::TTL );
		return $data;
	}

	/**
	 * Builds a urlset document.
	 *
	 * @param array[] $entries Entries from entries().
	 * @return string
	 */
	public static function urlset_xml( array $entries ) {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
		foreach ( array_slice( $entries, 0, self::MAX_URLS ) as $entry ) {
			$xml .= "\t<url>\n\t\t<loc>" . esc_xml( $entry['loc'] ) . "</loc>\n";
			if ( '' !== $entry['lastmod'] ) {
				$xml .= "\t\t<lastmod>" . esc_xml( $entry['lastmod'] ) . "</lastmod>\n";
			}
			$xml .= self::alternate_links_xml( $entry['alternates'] );
			$xml .= "\t</url>\n";
		}
		return $xml . '</urlset>' . "\n";
	}

	/**
	 * `<xhtml:link>` lines for one URL (also used by Yoast_Compat).
	 *
	 * @param array  $alternates hreflang => url.
	 * @param string $indent     Leading whitespace.
	 * @return string
	 */
	public static function alternate_links_xml( array $alternates, $indent = "\t\t" ) {
		$xml = '';
		foreach ( $alternates as $hreflang => $url ) {
			$xml .= $indent . '<xhtml:link rel="alternate" hreflang="' . esc_xml( $hreflang ) . '" href="' . esc_xml( $url ) . '" />' . "\n";
		}
		return $xml;
	}

	/*
	 * Entries
	 */

	/**
	 * Sitemap entries of a language (filter `mrtu_sitemap_entries`).
	 *
	 * @param string $lang Language code.
	 * @return array[] [ 'loc' => string, 'lastmod' => string (ISO 8601 UTC or ''), 'alternates' => array ]
	 */
	public static function entries( $lang ) {
		$entries = array();
		$seen    = array();

		if ( 'page' !== get_option( 'show_on_front' ) ) {
			$home = Links::language_home( $lang );
			self::push( $entries, $seen, $home, '', Seo::object_alternates( 'home', $lang ) );
		}

		foreach ( self::posts( $lang ) as $post ) {
			if ( ! Seo::post_is_indexable( $post ) ) {
				continue;
			}
			$loc = (string) get_permalink( $post );
			if ( '' === $loc ) {
				continue;
			}
			self::push( $entries, $seen, $loc, self::iso_utc( $post->post_modified_gmt ), Seo::object_alternates( 'post', $post ) );
		}

		foreach ( self::terms( $lang ) as $term ) {
			if ( ! Seo::term_is_indexable( $term ) ) {
				continue;
			}
			$loc = get_term_link( $term );
			if ( is_wp_error( $loc ) ) {
				continue;
			}
			self::push( $entries, $seen, (string) $loc, '', Seo::object_alternates( 'term', $term ) );
		}

		/**
		 * Filters the sitemap entries of a language.
		 *
		 * @param array[] $entries [ loc, lastmod, alternates ].
		 * @param string  $lang    Language code.
		 */
		$entries = apply_filters( 'mrtu_sitemap_entries', $entries, $lang );
		return is_array( $entries ) ? array_values( array_filter( $entries, array( __CLASS__, 'is_entry' ) ) ) : array();
	}

	/**
	 * Adds an entry once per URL.
	 *
	 * @param array  $entries    Entries (by reference).
	 * @param array  $seen       Seen URLs (by reference).
	 * @param string $loc        URL.
	 * @param string $lastmod    Last modified (ISO 8601) or ''.
	 * @param array  $alternates hreflang => url.
	 */
	private static function push( array &$entries, array &$seen, $loc, $lastmod, array $alternates ) {
		if ( '' === $loc || isset( $seen[ $loc ] ) ) {
			return;
		}
		$seen[ $loc ] = true;
		$entries[]    = array(
			'loc'        => $loc,
			'lastmod'    => (string) $lastmod,
			'alternates' => Seo::usable_alternates( $alternates ),
		);
	}

	/**
	 * Entry shape check for filtered entries.
	 *
	 * @param mixed $entry Entry.
	 * @return bool
	 */
	public static function is_entry( $entry ) {
		return is_array( $entry ) && ! empty( $entry['loc'] ) && is_string( $entry['loc'] ) && isset( $entry['lastmod'], $entry['alternates'] ) && is_array( $entry['alternates'] );
	}

	/**
	 * Published, non-password posts of translatable types in one language (noindex excluded by meta query).
	 *
	 * @param string $lang Language code.
	 * @return \WP_Post[]
	 */
	private static function posts( $lang ) {
		$query = new \WP_Query(
			array(
				'post_type'              => Translations::post_types(),
				'post_status'            => 'publish',
				'has_password'           => false,
				'posts_per_page'         => self::MAX_URLS,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'suppress_filters'       => false,
				'update_post_term_cache' => true,
				'update_post_meta_cache' => true,
				Router::QUERY_VAR        => $lang,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- noindex exclusion; cached per language.
					'relation' => 'OR',
					array(
						'key'     => Seo::META_NOINDEX,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => Seo::META_NOINDEX,
						'value'   => '1',
						'compare' => '!=',
					),
				),
			)
		);
		$posts = $query->posts;
		if ( $posts ) {
			Translations::prime( wp_list_pluck( $posts, 'ID' ) );
		}
		return $posts;
	}

	/**
	 * Non-empty terms of translatable taxonomies in one language.
	 *
	 * @param string $lang Language code.
	 * @return \WP_Term[]
	 */
	private static function terms( $lang ) {
		if ( Languages::default_code() === $lang ) {
			$clause = array(
				'relation' => 'OR',
				array(
					'key'     => Translations::META_TERM_LANG,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => Translations::META_TERM_LANG,
					'value' => $lang,
				),
			);
		} else {
			$clause = array(
				'key'   => Translations::META_TERM_LANG,
				'value' => $lang,
			);
		}
		$out = array();
		foreach ( Translations::taxonomies() as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_terms(
				array(
					'taxonomy'        => $taxonomy,
					'hide_empty'      => true,
					Router::QUERY_VAR => 'all',
					'meta_query'      => array( $clause ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- language scoping; term tables are small.
				)
			);
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( $term instanceof \WP_Term ) {
						$out[] = $term;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * MySQL GMT datetime → ISO 8601 UTC.
	 *
	 * @param string $mysql_gmt e.g. "2026-10-25 09:00:00".
	 * @return string '' when unknown.
	 */
	public static function iso_utc( $mysql_gmt ) {
		$mysql_gmt = (string) $mysql_gmt;
		if ( '' === $mysql_gmt || '0000-00-00 00:00:00' === $mysql_gmt ) {
			return '';
		}
		$time = strtotime( $mysql_gmt . ' UTC' );
		return $time ? gmdate( 'Y-m-d\TH:i:s+00:00', $time ) : '';
	}

	/*
	 * robots.txt + core sitemaps
	 */

	/**
	 * Adds the `Sitemap:` line.
	 *
	 * @param string $output robots.txt text.
	 * @param bool   $is_public Whether the site is public.
	 * @return string
	 */
	public static function robots_txt( $output, $is_public ) {
		if ( ! $is_public || ! Seo::sitemap_enabled() ) {
			return $output;
		}
		$line = 'Sitemap: ' . self::url( self::INDEX );
		if ( false !== strpos( (string) $output, $line ) ) {
			return $output;
		}
		return rtrim( (string) $output, "\n" ) . "\n\n" . $line . "\n";
	}

	/**
	 * Core sitemaps are switched off while ours are served in standalone mode (never in compat mode).
	 *
	 * @param bool $enabled Core flag.
	 * @return bool
	 */
	public static function core_sitemaps( $enabled ) {
		if ( Seo::is_standalone() && Seo::sitemap_enabled() ) {
			return false;
		}
		return $enabled;
	}

	/*
	 * Cache
	 */

	/**
	 * Drops every cached sitemap (contract `mrtu_sitemap_flush()`).
	 */
	public static function flush() {
		foreach ( Languages::codes( false ) as $code ) {
			delete_transient( self::TRANSIENT . $code );
		}
		delete_transient( self::TRANSIENT . self::INDEX );
	}

	/**
	 * Flushes for translatable posts.
	 *
	 * @param int           $post_id Post id.
	 * @param \WP_Post|null $post    Post.
	 */
	public static function on_post_change( $post_id, $post = null ) {
		$post = $post instanceof \WP_Post ? $post : get_post( $post_id );
		if ( $post instanceof \WP_Post && Translations::is_translatable_post_type( $post->post_type ) ) {
			self::flush();
		}
	}

	/**
	 * Flushes when a translatable post changes status.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public static function on_transition( $new_status, $old_status, $post ) {
		if ( $new_status !== $old_status && $post instanceof \WP_Post && Translations::is_translatable_post_type( $post->post_type ) ) {
			self::flush();
		}
	}

	/**
	 * Flushes for translatable taxonomies.
	 *
	 * @param int    $term_id  Term id.
	 * @param int    $tt_id    Term taxonomy id.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function on_term_change( $term_id, $tt_id, $taxonomy ) {
		if ( Translations::is_translatable_taxonomy( $taxonomy ) ) {
			self::flush();
		}
	}

	/**
	 * Flushes when SEO meta changes outside save_post (REST, CLI).
	 *
	 * @param int    $meta_id  Meta id.
	 * @param int    $post_id  Post id.
	 * @param string $meta_key Meta key.
	 */
	public static function on_meta( $meta_id, $post_id, $meta_key ) {
		if ( in_array( $meta_key, array( Seo::META_NOINDEX, Roles::META ), true ) ) {
			self::flush();
		}
	}
}
