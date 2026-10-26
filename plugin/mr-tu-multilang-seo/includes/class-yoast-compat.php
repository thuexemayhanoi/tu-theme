<?php
/**
 * Yoast SEO coexistence (contract §2.8, research ADR-D). Active only when `WPSEO_VERSION` is defined.
 *
 * Compat mode (Yoast prints the head):
 * - we print ONLY hreflang `<link rel="alternate">` + `og:locale:alternate` inside Yoast's head block (`wpseo_head`);
 * - `_mrtu_seo_title` / `_mrtu_seo_desc` / `_mrtu_noindex` feed `wpseo_title` / `wpseo_metadesc` / `wpseo_robots_array`
 *   only when Yoast's own per-post/term value is empty (the indexable model);
 * - language-correct `wpseo_canonical` + `wpseo_opengraph_url` on language homes (`/en/` with trailing slash);
 * - `wpseo_locale` → og locale of the request language, `wpseo_schema_webpage` (Yoast's dynamic
 *   `'wpseo_schema_' . $identifier` filter for the WebPage piece) → inLanguage;
 * - Yoast's sitemap stays the only sitemap: `wpseo_sitemap_entry` drops noindex / disabled-language URLs,
 *   `wpseo_exclude_from_sitemap_by_post_ids` drops noindex posts, `wpseo_sitemap_urlset` + `wpseo_sitemap_url`
 *   add `xmlns:xhtml` and `<xhtml:link>` alternates. Limits: Yoast caches its sitemaps (alternates refresh with
 *   that cache), author sitemaps are untouched, and image entries keep Yoast's format.
 * - `wpseo_dynamic_permalinks_enabled` → true so canonicals follow our language-prefixed permalinks.
 *
 * Every filter name above exists in Yoast SEO (checked against the wordpress-seo source: `apply_filters( 'wpseo_…' )`).
 * `wpseo_sitemap_exclude_post` does NOT exist in Yoast and is therefore not used.
 *
 * Standalone mode with Yoast still active (owner opted in): Yoast's frontend presenters and JSON-LD output
 * are switched off through Yoast's own filters (`wpseo_frontend_presenters` → empty list, `wpseo_json_ld_output`
 * → false). Limits: Yoast still prints its `<!-- This site is optimized with … -->` comment wrapper and still
 * serves `/sitemap_index.xml` (no filter disables it) — the SEO screen tells the owner to switch Yoast's sitemap
 * off; Yoast's indexables / admin metabox keep working but are not read by our head.
 *
 * Yoast cannot run in the local test env (no network installs): tests/php/yoast-stub.php defines
 * WPSEO_VERSION and replays these filters; real verification happens on staging.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Yoast filter callbacks.
 */
final class Yoast_Compat {

	/**
	 * Whether the filters were registered.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * Sitemap `loc` → [ kind, id ] remembered from `wpseo_sitemap_entry` for `wpseo_sitemap_url`.
	 *
	 * @var array
	 */
	private static $entries = array();

	/**
	 * Wires hooks after every plugin loaded (Yoast may load after us).
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'setup' ), 20 );
	}

	/**
	 * Registers the Yoast filters once (no-op without Yoast).
	 *
	 * @return bool Whether the filters are active.
	 */
	public static function setup() {
		if ( self::$hooked || ! Seo::yoast_active() ) {
			return self::$hooked;
		}
		self::$hooked = true;

		add_filter( 'wpseo_dynamic_permalinks_enabled', '__return_true' );

		// Compat: feed Yoast and add what Yoast lacks.
		add_filter( 'wpseo_title', array( __CLASS__, 'title' ), 10, 2 );
		add_filter( 'wpseo_metadesc', array( __CLASS__, 'metadesc' ), 10, 2 );
		add_filter( 'wpseo_robots_array', array( __CLASS__, 'robots_array' ), 10, 2 );
		add_filter( 'wpseo_canonical', array( __CLASS__, 'canonical' ), 10, 2 );
		add_filter( 'wpseo_opengraph_url', array( __CLASS__, 'canonical' ), 10, 2 );
		add_filter( 'wpseo_locale', array( __CLASS__, 'locale' ) );
		add_filter( 'wpseo_schema_webpage', array( __CLASS__, 'schema_webpage' ), 10, 2 );
		add_action( 'wpseo_head', array( __CLASS__, 'print_alternates' ), 10 );
		add_filter( 'wpseo_sitemap_entry', array( __CLASS__, 'sitemap_entry' ), 10, 3 );
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( __CLASS__, 'exclude_noindex_posts' ) );
		add_filter( 'wpseo_sitemap_urlset', array( __CLASS__, 'sitemap_urlset' ) );
		add_filter( 'wpseo_sitemap_url', array( __CLASS__, 'sitemap_url' ), 10, 2 );

		// Standalone next to Yoast: silence Yoast's head and schema through its own filters.
		add_filter( 'wpseo_frontend_presenters', array( __CLASS__, 'presenters' ), PHP_INT_MAX );
		add_filter( 'wpseo_json_ld_output', array( __CLASS__, 'json_ld_output' ), PHP_INT_MAX );
		return true;
	}

	/**
	 * Whether the compat callbacks should act (Yoast active and mode = compat).
	 *
	 * @return bool
	 */
	public static function is_compat() {
		return Seo::yoast_active() && 'compat' === Seo::mode();
	}

	/**
	 * Resets the remembered sitemap entries (tests).
	 */
	public static function reset() {
		self::$entries = array();
	}

	/*
	 * Helpers around Yoast's presentation object
	 */

	/**
	 * Object described by a Yoast presentation: [ 'post', id ] | [ 'term', id ] | [ 'home' ] | null.
	 *
	 * @param mixed $presentation Yoast indexable presentation.
	 * @return array|null
	 */
	private static function presented_object( $presentation ) {
		if ( ! is_object( $presentation ) || ! isset( $presentation->model ) || ! is_object( $presentation->model ) ) {
			return null;
		}
		$model = $presentation->model;
		$type  = isset( $model->object_type ) ? (string) $model->object_type : '';
		$id    = isset( $model->object_id ) ? (int) $model->object_id : 0;
		if ( 'post' === $type && $id ) {
			return array( 'post', $id );
		}
		if ( 'term' === $type && $id ) {
			return array( 'term', $id );
		}
		if ( 'home-page' === $type ) {
			return array( 'home' );
		}
		return null;
	}

	/**
	 * Whether Yoast's own per-object field is empty (no presentation at all counts as empty).
	 *
	 * @param mixed  $presentation Presentation.
	 * @param string $field        Model property (title, description, is_robots_noindex).
	 * @return bool
	 */
	private static function model_empty( $presentation, $field ) {
		if ( ! is_object( $presentation ) || ! isset( $presentation->model ) || ! is_object( $presentation->model ) ) {
			return true;
		}
		return empty( $presentation->model->{$field} );
	}

	/*
	 * Title / description / robots
	 */

	/**
	 * `wpseo_title`: our seeded title when Yoast's own field is empty.
	 *
	 * @param string $title        Yoast title.
	 * @param mixed  $presentation Presentation.
	 * @return string
	 */
	public static function title( $title, $presentation = null ) {
		if ( ! self::is_compat() || ! self::model_empty( $presentation, 'title' ) ) {
			return $title;
		}
		$object = self::presented_object( $presentation );
		if ( ! $object ) {
			return $title;
		}
		if ( 'post' === $object[0] ) {
			$ours = (string) get_post_meta( $object[1], Seo::META_TITLE, true );
		} elseif ( 'term' === $object[0] ) {
			$ours = (string) get_term_meta( $object[1], Seo::META_TITLE, true );
		} else {
			$ours = '';
		}
		return '' !== trim( $ours ) ? $ours : $title;
	}

	/**
	 * `wpseo_metadesc`: our seeded description when Yoast's own field is empty.
	 *
	 * @param string $description  Yoast description.
	 * @param mixed  $presentation Presentation.
	 * @return string
	 */
	public static function metadesc( $description, $presentation = null ) {
		if ( ! self::is_compat() || ! self::model_empty( $presentation, 'description' ) ) {
			return $description;
		}
		$object = self::presented_object( $presentation );
		if ( ! $object ) {
			return $description;
		}
		if ( 'post' === $object[0] ) {
			$ours = (string) get_post_meta( $object[1], Seo::META_DESC, true );
		} elseif ( 'term' === $object[0] ) {
			$ours = (string) get_term_meta( $object[1], Seo::META_DESC, true );
		} else {
			$ours = '';
		}
		return '' !== trim( $ours ) ? $ours : $description;
	}

	/**
	 * `wpseo_robots_array`: adds noindex when our rules say so and Yoast's per-object flag is unset.
	 *
	 * @param array $robots       Yoast robots (index, follow, max-*).
	 * @param mixed $presentation Presentation.
	 * @return array
	 */
	public static function robots_array( $robots, $presentation = null ) {
		if ( ! self::is_compat() || ! is_array( $robots ) ) {
			return $robots;
		}
		if ( ! self::model_empty( $presentation, 'is_robots_noindex' ) ) {
			return $robots; // The owner decided in Yoast.
		}
		$object  = self::presented_object( $presentation );
		$noindex = false;
		if ( $object && 'post' === $object[0] ) {
			$noindex = Seo::post_noindex( $object[1] );
		} elseif ( $object && 'term' === $object[0] ) {
			$noindex = Seo::term_noindex( $object[1] );
		}
		if ( ! $noindex && Router::is_frontend() ) {
			$noindex = Seo::is_noindex();
		}
		if ( ! $noindex ) {
			return $robots;
		}
		$robots['index']  = 'noindex';
		$robots['follow'] = isset( $robots['follow'] ) ? $robots['follow'] : 'follow';
		unset( $robots['max-snippet'], $robots['max-image-preview'], $robots['max-video-preview'] );
		return $robots;
	}

	/**
	 * `wpseo_canonical` / `wpseo_opengraph_url`: language homes always point at `/{prefix}/`.
	 *
	 * @param string $url          Yoast URL.
	 * @param mixed  $presentation Presentation.
	 * @return string
	 */
	public static function canonical( $url, $presentation = null ) {
		if ( ! self::is_compat() || ! Router::is_frontend() ) {
			return $url;
		}
		$context = Seo::context();
		if ( 'front' === $context['type'] && 1 === $context['paged'] ) {
			return Links::language_home( $context['lang'] );
		}
		return $url;
	}

	/**
	 * `wpseo_locale`: og:locale of the request language.
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function locale( $locale ) {
		if ( ! self::is_compat() ) {
			return $locale;
		}
		$lang = Languages::get( Router::effective_language() );
		return $lang ? $lang['og_locale'] : $locale;
	}

	/**
	 * `wpseo_schema_webpage`: inLanguage of the request language.
	 *
	 * @param array $piece   WebPage piece.
	 * @param mixed $context Yoast meta tags context.
	 * @return array
	 */
	public static function schema_webpage( $piece, $context = null ) {
		if ( ! self::is_compat() || ! is_array( $piece ) ) {
			return $piece;
		}
		$lang = Languages::get( Router::effective_language() );
		if ( $lang ) {
			$piece['inLanguage'] = $lang['hreflang'];
		}
		return $piece;
	}

	/**
	 * `wpseo_head`: hreflang + og:locale:alternate inside Yoast's block (compat only).
	 */
	public static function print_alternates() {
		if ( ! self::is_compat() || ! Router::is_frontend() ) {
			return;
		}
		echo Seo_Head::render_alternates(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render_alternates().
	}

	/*
	 * Standalone next to Yoast
	 */

	/**
	 * `wpseo_frontend_presenters`: none in standalone mode (we print the head).
	 *
	 * @param array $presenters Presenter instances.
	 * @return array
	 */
	public static function presenters( $presenters ) {
		return Seo::is_standalone() ? array() : $presenters;
	}

	/**
	 * `wpseo_json_ld_output`: false in standalone mode (we print the graph).
	 *
	 * @param mixed $output Deprecated data / flag.
	 * @return mixed
	 */
	public static function json_ld_output( $output ) {
		return Seo::is_standalone() ? false : $output;
	}

	/*
	 * Yoast sitemap
	 */

	/**
	 * `wpseo_sitemap_entry`: drops non-indexable posts/terms and remembers the rest for the alternates.
	 *
	 * @param array|false $url  Entry [ loc, mod, … ].
	 * @param string      $type post | term | user.
	 * @param mixed       $item Post / term / user object.
	 * @return array|false
	 */
	public static function sitemap_entry( $url, $type, $item = null ) {
		if ( ! self::is_compat() || ! is_array( $url ) || empty( $url['loc'] ) ) {
			return $url;
		}
		if ( 'post' === $type && $item instanceof \WP_Post && Translations::is_translatable_post_type( $item->post_type ) ) {
			if ( ! Seo::post_is_indexable( $item ) ) {
				return false;
			}
			self::$entries[ (string) $url['loc'] ] = array( 'post', (int) $item->ID );
		} elseif ( 'term' === $type && $item instanceof \WP_Term && Translations::is_translatable_taxonomy( $item->taxonomy ) ) {
			if ( ! Seo::term_is_indexable( $item ) ) {
				return false;
			}
			self::$entries[ (string) $url['loc'] ] = array( 'term', (int) $item->term_id );
		}
		return $url;
	}

	/**
	 * `wpseo_exclude_from_sitemap_by_post_ids`: posts flagged noindex.
	 *
	 * @param array $ids Excluded ids.
	 * @return array
	 */
	public static function exclude_noindex_posts( $ids ) {
		$ids = is_array( $ids ) ? $ids : array();
		if ( ! self::is_compat() ) {
			return $ids;
		}
		$noindex = get_posts(
			array(
				'post_type'        => Translations::post_types(),
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'no_found_rows'    => true,
				Router::QUERY_VAR  => 'all',
				'meta_key'         => Seo::META_NOINDEX, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- noindex flag lookup when Yoast builds its sitemap.
				'meta_value'       => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return array_values( array_unique( array_merge( array_map( 'intval', $ids ), array_map( 'intval', (array) $noindex ) ) ) );
	}

	/**
	 * `wpseo_sitemap_urlset`: declares the xhtml namespace.
	 *
	 * @param string $urlset Opening `<urlset …>` tag.
	 * @return string
	 */
	public static function sitemap_urlset( $urlset ) {
		if ( ! self::is_compat() || ! is_string( $urlset ) || false !== strpos( $urlset, 'xmlns:xhtml=' ) ) {
			return $urlset;
		}
		return preg_replace( '/<urlset\b/', '<urlset xmlns:xhtml="http://www.w3.org/1999/xhtml"', $urlset, 1 );
	}

	/**
	 * `wpseo_sitemap_url`: injects `<xhtml:link>` alternates before `</url>`.
	 *
	 * @param string $output `<url>…</url>` XML.
	 * @param array  $url    Entry [ loc, … ].
	 * @return string
	 */
	public static function sitemap_url( $output, $url ) {
		if ( ! self::is_compat() || ! is_string( $output ) || ! is_array( $url ) || empty( $url['loc'] ) ) {
			return $output;
		}
		$loc        = (string) $url['loc'];
		$alternates = array();
		if ( isset( self::$entries[ $loc ] ) ) {
			$alternates = Seo::object_alternates( self::$entries[ $loc ][0], self::$entries[ $loc ][1] );
		} else {
			foreach ( Languages::codes( true ) as $code ) {
				if ( untrailingslashit( Links::language_home( $code ) ) === untrailingslashit( $loc ) ) {
					$alternates = Seo::object_alternates( 'home', $code );
					break;
				}
			}
		}
		$alternates = Seo::usable_alternates( $alternates );
		if ( ! $alternates ) {
			return $output;
		}
		$links = Sitemaps::alternate_links_xml( $alternates );
		$pos   = strrpos( $output, '</url>' );
		if ( false === $pos ) {
			return $output;
		}
		return substr( $output, 0, $pos ) . $links . substr( $output, $pos );
	}
}
