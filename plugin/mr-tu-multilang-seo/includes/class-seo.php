<?php
/**
 * SEO core (contract §2.8): settings access, mode detection, SEO meta registration, title /
 * description resolution, robots decisions, canonical builder and the per-object hreflang
 * alternates shared by the head and the sitemaps.
 *
 * Nothing here prints markup. Seo_Head (standalone mode) and Yoast_Compat (compat mode) decide what
 * reaches the page; Sitemaps and Schema read the same resolvers so every surface agrees.
 *
 * Modes (setting `seo_mode`): `auto` = compat when Yoast is active, otherwise standalone;
 * `compat` needs Yoast (falls back to standalone when Yoast is inactive, otherwise nothing would be
 * printed); `standalone` = we print everything, even next to Yoast (whose presenters are then
 * switched off through Yoast's own filters, see Yoast_Compat).
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * SEO resolvers + settings.
 */
final class Seo {

	const META_TITLE   = '_mrtu_seo_title';
	const META_DESC    = '_mrtu_seo_desc';
	const META_NOINDEX = '_mrtu_noindex';
	const META_KW      = '_mrtu_focus_kw';

	/**
	 * Counter hints for the edit screens (characters).
	 */
	const TITLE_LENGTH = 60;
	const DESC_LENGTH  = 158;

	/**
	 * Generated descriptions are cut here (characters).
	 */
	const DESC_TRIM = 155;

	const SEPARATOR = ' | ';

	const MODES         = array( 'auto', 'compat', 'standalone' );
	const SITEMAP_MODES = array( 'auto', 'on', 'off' );

	/**
	 * Per-request context cache keyed by request signature.
	 *
	 * @var array
	 */
	private static $contexts = array();

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'mrtu_language_detected', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Clears the per-request cache.
	 */
	public static function flush() {
		self::$contexts = array();
	}

	/*
	 * Settings + mode
	 */

	/**
	 * One plugin setting (option `mrtu_ml_settings`).
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Fallback.
	 * @return mixed
	 */
	public static function setting( $key, $fallback = null ) {
		return Languages::setting( $key, $fallback );
	}

	/**
	 * Whether Yoast SEO is loaded.
	 *
	 * @return bool
	 */
	public static function yoast_active() {
		return defined( 'WPSEO_VERSION' );
	}

	/**
	 * Configured mode (auto | compat | standalone).
	 *
	 * @return string
	 */
	public static function configured_mode() {
		$mode = (string) self::setting( 'seo_mode', 'auto' );
		return in_array( $mode, self::MODES, true ) ? $mode : 'auto';
	}

	/**
	 * Effective mode (compat | standalone), see the class description.
	 *
	 * @return string
	 */
	public static function mode() {
		$mode = self::configured_mode();
		if ( 'standalone' === $mode ) {
			return 'standalone';
		}
		return self::yoast_active() ? 'compat' : 'standalone';
	}

	/**
	 * Whether we print the whole head ourselves.
	 *
	 * @return bool
	 */
	public static function is_standalone() {
		return 'standalone' === self::mode();
	}

	/**
	 * Configured sitemap mode (auto | on | off).
	 *
	 * @return string
	 */
	public static function sitemap_mode() {
		$mode = (string) self::setting( 'sitemap', 'auto' );
		return in_array( $mode, self::SITEMAP_MODES, true ) ? $mode : 'auto';
	}

	/**
	 * Whether our sitemaps are served (auto = on in standalone, off in compat).
	 *
	 * @return bool
	 */
	public static function sitemap_enabled() {
		$mode = self::sitemap_mode();
		if ( 'on' === $mode ) {
			return true;
		}
		if ( 'off' === $mode ) {
			return false;
		}
		return self::is_standalone();
	}

	/**
	 * Whether the verified facts needed for AutoRental/LocalBusiness schema exist (address, phone, hours).
	 *
	 * @return bool
	 */
	public static function local_business_allowed() {
		if ( ! function_exists( 'mrtu_business' ) ) {
			return false;
		}
		return is_array( mrtu_business( 'address', true ) ) && is_array( mrtu_business( 'phone', true ) ) && is_array( mrtu_business( 'hours', true ) );
	}

	/**
	 * Whether AutoRental schema is printed (setting on AND facts verified).
	 *
	 * @return bool
	 */
	public static function local_business_enabled() {
		return (bool) self::setting( 'local_business_schema', false ) && self::local_business_allowed();
	}

	/**
	 * Sanitizes the SEO part of the settings form.
	 *
	 * @param array $input seo_mode, sitemap, local_business_schema, faq_schema, noindex_author, noindex_date.
	 * @return array Partial settings to merge.
	 */
	public static function sanitize_settings( array $input ) {
		$out  = array();
		$mode = isset( $input['seo_mode'] ) ? sanitize_key( (string) $input['seo_mode'] ) : self::configured_mode();
		if ( in_array( $mode, self::MODES, true ) ) {
			$out['seo_mode'] = $mode;
		}
		$sitemap = isset( $input['sitemap'] ) ? sanitize_key( (string) $input['sitemap'] ) : self::sitemap_mode();
		if ( in_array( $sitemap, self::SITEMAP_MODES, true ) ) {
			$out['sitemap'] = $sitemap;
		}
		foreach ( array( 'local_business_schema', 'faq_schema', 'noindex_author', 'noindex_date' ) as $flag ) {
			if ( array_key_exists( $flag, $input ) ) {
				$out[ $flag ] = is_scalar( $input[ $flag ] ) && in_array( strtolower( trim( (string) $input[ $flag ] ) ), array( '1', 'true', 'on', 'yes' ), true );
			}
		}
		return $out;
	}

	/*
	 * Meta registration
	 */

	/**
	 * Registers the SEO post/term meta (REST visible, author-gated).
	 */
	public static function register_meta() {
		$post_keys = array(
			self::META_TITLE   => 'sanitize_title_text',
			self::META_DESC    => 'sanitize_description_text',
			self::META_NOINDEX => 'sanitize_noindex',
			self::META_KW      => 'sanitize_title_text',
		);
		foreach ( Translations::post_types() as $type ) {
			if ( ! post_type_exists( $type ) ) {
				continue;
			}
			foreach ( $post_keys as $key => $sanitizer ) {
				if ( registered_meta_key_exists( 'post', $key, $type ) ) {
					continue;
				}
				register_post_meta(
					$type,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'default'           => '',
						'show_in_rest'      => true,
						'sanitize_callback' => array( __CLASS__, $sanitizer ),
						'auth_callback'     => array( __CLASS__, 'can_edit_post_meta' ),
					)
				);
			}
		}
		$term_keys = array(
			self::META_TITLE   => 'sanitize_title_text',
			self::META_DESC    => 'sanitize_description_text',
			self::META_NOINDEX => 'sanitize_noindex',
		);
		foreach ( Translations::taxonomies() as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			foreach ( $term_keys as $key => $sanitizer ) {
				if ( registered_meta_key_exists( 'term', $key, $taxonomy ) ) {
					continue;
				}
				register_term_meta(
					$taxonomy,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'default'           => '',
						'show_in_rest'      => true,
						'sanitize_callback' => array( __CLASS__, $sanitizer ),
						'auth_callback'     => array( __CLASS__, 'can_edit_term_meta' ),
					)
				);
			}
		}
	}

	/**
	 * Single-line text (SEO title, focus keyword), max 200 characters.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_title_text( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = sanitize_text_field( (string) $value );
		return mb_strlen( $text ) > 200 ? mb_substr( $text, 0, 200 ) : $text;
	}

	/**
	 * Meta description text (one paragraph), max 400 characters.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_description_text( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = preg_replace( '/\s+/u', ' ', sanitize_textarea_field( (string) $value ) );
		$text = trim( (string) $text );
		return mb_strlen( $text ) > 400 ? mb_substr( $text, 0, 400 ) : $text;
	}

	/**
	 * `_mrtu_noindex`: '1' or ''.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_noindex( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}
		return is_scalar( $value ) && in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'on', 'yes', 'noindex' ), true ) ? '1' : '';
	}

	/**
	 * Post meta auth callback.
	 *
	 * @param bool   $allowed   Allowed so far.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Post id.
	 * @return bool
	 */
	public static function can_edit_post_meta( $allowed, $meta_key, $object_id ) {
		return current_user_can( 'edit_post', (int) $object_id );
	}

	/**
	 * Term meta auth callback.
	 *
	 * @param bool   $allowed   Allowed so far.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Term id.
	 * @return bool
	 */
	public static function can_edit_term_meta( $allowed, $meta_key, $object_id ) {
		return current_user_can( 'edit_term', (int) $object_id );
	}

	/*
	 * Request context
	 */

	/**
	 * Describes what is being rendered.
	 *
	 * @param mixed $subject null = the current main query; int|WP_Post = that post; WP_Term = that term.
	 * @return array {
	 *     @type string        $type   front|home|singular|attachment|term|author|date|post_type_archive|search|404|other.
	 *     @type \WP_Post|null $post   Post (front/home/singular/attachment).
	 *     @type \WP_Term|null $term   Term (term archives).
	 *     @type string        $lang   Language code.
	 *     @type int           $paged  Archive page number (1 = first).
	 *     @type int           $page   Page of a paginated post (1 = first).
	 * }
	 */
	public static function context( $subject = null ) {
		if ( null !== $subject ) {
			return self::subject_context( $subject );
		}
		global $wp_query;
		$base = array(
			'type'  => 'other',
			'post'  => null,
			'term'  => null,
			'lang'  => Router::effective_language(),
			'paged' => 1,
			'page'  => 1,
		);
		if ( ! $wp_query instanceof \WP_Query ) {
			return $base;
		}
		$key = md5( $base['lang'] . '|' . $wp_query->query_vars_hash . '|' . $wp_query->get_queried_object_id() . '|' . (int) $wp_query->is_404() );
		if ( isset( self::$contexts[ $key ] ) ) {
			return self::$contexts[ $key ];
		}

		$ctx          = $base;
		$ctx['paged'] = max( 1, (int) $wp_query->get( 'paged' ) );
		$ctx['page']  = max( 1, (int) $wp_query->get( 'page' ) );
		$object       = $wp_query->get_queried_object();

		if ( $wp_query->is_404() ) {
			$ctx['type'] = '404';
		} elseif ( $wp_query->is_search() ) {
			$ctx['type'] = 'search';
		} elseif ( $wp_query->is_front_page() ) {
			$ctx['type'] = 'front';
			$ctx['post'] = $object instanceof \WP_Post ? $object : null;
		} elseif ( $wp_query->is_home() ) {
			$ctx['type'] = 'home';
			$ctx['post'] = $object instanceof \WP_Post ? $object : null;
		} elseif ( $wp_query->is_attachment() ) {
			$ctx['type'] = 'attachment';
			$ctx['post'] = $object instanceof \WP_Post ? $object : null;
		} elseif ( $wp_query->is_singular() ) {
			$ctx['type'] = 'singular';
			$ctx['post'] = $object instanceof \WP_Post ? $object : null;
		} elseif ( $wp_query->is_category() || $wp_query->is_tag() || $wp_query->is_tax() ) {
			$ctx['type'] = 'term';
			$ctx['term'] = $object instanceof \WP_Term ? $object : null;
		} elseif ( $wp_query->is_author() ) {
			$ctx['type'] = 'author';
		} elseif ( $wp_query->is_date() ) {
			$ctx['type'] = 'date';
		} elseif ( $wp_query->is_post_type_archive() ) {
			$ctx['type'] = 'post_type_archive';
		}

		self::$contexts[ $key ] = $ctx;
		return $ctx;
	}

	/**
	 * Context for an explicit post or term (edit screens, sitemaps, tests).
	 *
	 * @param mixed $subject int|WP_Post|WP_Term.
	 * @return array
	 */
	private static function subject_context( $subject ) {
		$ctx = array(
			'type'  => 'other',
			'post'  => null,
			'term'  => null,
			'lang'  => Router::effective_language(),
			'paged' => 1,
			'page'  => 1,
		);
		if ( $subject instanceof \WP_Term ) {
			$ctx['type'] = 'term';
			$ctx['term'] = $subject;
			$ctx['lang'] = Translations::is_translatable_taxonomy( $subject->taxonomy ) ? Translations::get_term_language( $subject ) : $ctx['lang'];
			return $ctx;
		}
		$post = get_post( $subject );
		if ( ! $post instanceof \WP_Post ) {
			return $ctx;
		}
		$ctx['post'] = $post;
		if ( Translations::is_translatable_post_type( $post->post_type ) ) {
			$ctx['lang'] = Translations::get_post_language( $post );
		}
		if ( 'attachment' === $post->post_type ) {
			$ctx['type'] = 'attachment';
		} elseif ( Links::is_front_page_group( $post->ID ) ) {
			$ctx['type'] = 'front';
		} elseif ( 'page' === get_option( 'show_on_front' ) && Router::raw_option( 'page_for_posts' ) && Translations::in_same_group( $post->ID, Router::raw_option( 'page_for_posts' ) ) ) {
			$ctx['type'] = 'home';
		} else {
			$ctx['type'] = 'singular';
		}
		return $ctx;
	}

	/*
	 * Title
	 */

	/**
	 * Document title (contract `mrtu_seo_title()`), raw text — escape on output.
	 *
	 * Rules: `_mrtu_seo_title` → else "{title} | {site name}". Home: `_mrtu_seo_title` of the home page →
	 * language tagline ("{tagline} | {site name}") → home page title. Archives/search/404 use the built-in
	 * per-language strings. Paginated requests get a localized "Page N".
	 *
	 * @param mixed $context Context array, post, term or null (current request).
	 * @return string
	 */
	public static function title( $context = null ) {
		$ctx   = is_array( $context ) ? $context : self::context( $context );
		$lang  = $ctx['lang'];
		$site  = self::site_name();
		$title = '';

		switch ( $ctx['type'] ) {
			case 'front':
			case 'home':
				$title = self::home_title( $ctx );
				break;
			case 'singular':
			case 'attachment':
				if ( $ctx['post'] instanceof \WP_Post ) {
					$custom = (string) get_post_meta( $ctx['post']->ID, self::META_TITLE, true );
					$title  = '' !== trim( $custom ) ? $custom : self::brand( self::post_title_text( $ctx['post'] ), $site );
				}
				break;
			case 'term':
				if ( $ctx['term'] instanceof \WP_Term ) {
					$custom = (string) get_term_meta( $ctx['term']->term_id, self::META_TITLE, true );
					$title  = '' !== trim( $custom ) ? $custom : self::brand( self::plain_text( $ctx['term']->name ), $site );
				}
				break;
			case 'search':
				$title = self::brand( sprintf( Languages::ui_string( 'search_results', $lang ), get_search_query( false ) ), $site );
				break;
			case '404':
				$title = self::brand( Languages::ui_string( 'not_found', $lang ), $site );
				break;
			case 'author':
			case 'date':
			case 'post_type_archive':
				$title = self::brand( self::archive_label( $ctx ), $site );
				break;
		}
		if ( '' === $title ) {
			$title = $site;
		}

		$paged = max( $ctx['paged'], $ctx['page'] );
		if ( $paged > 1 && ! in_array( $ctx['type'], array( 'search', '404' ), true ) ) {
			$title = self::with_page( $title, $paged, $lang, $site );
		}

		/**
		 * Filters the document title.
		 *
		 * @param string $title   Title (raw text).
		 * @param array  $context Request context (see Seo::context()).
		 */
		$title = apply_filters( 'mrtu_seo_title', $title, $ctx );
		return self::plain_text( (string) $title );
	}

	/**
	 * Home title: custom meta → "{tagline} | {site}" → home page title (branded unless it names the site).
	 *
	 * @param array $ctx Context.
	 * @return string
	 */
	private static function home_title( array $ctx ) {
		$site = self::site_name();
		if ( $ctx['post'] instanceof \WP_Post ) {
			$custom = (string) get_post_meta( $ctx['post']->ID, self::META_TITLE, true );
			if ( '' !== trim( $custom ) ) {
				return $custom;
			}
		}
		$language = Languages::get( $ctx['lang'] );
		$tagline  = $language && '' !== trim( $language['tagline'] ) ? trim( $language['tagline'] ) : '';
		if ( '' !== $tagline ) {
			return self::brand( $tagline, $site );
		}
		if ( $ctx['post'] instanceof \WP_Post ) {
			return self::brand( self::post_title_text( $ctx['post'] ), $site );
		}
		return $site;
	}

	/**
	 * Appends the site name unless the text already contains it.
	 *
	 * @param string $text Text.
	 * @param string $site Site name.
	 * @return string
	 */
	public static function brand( $text, $site = null ) {
		$site = null === $site ? self::site_name() : (string) $site;
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return $site;
		}
		if ( '' === $site || false !== mb_stripos( $text, $site ) ) {
			return $text;
		}
		return $text . self::SEPARATOR . $site;
	}

	/**
	 * Inserts a localized "Page N" before the brand suffix.
	 *
	 * @param string $title Title.
	 * @param int    $paged Page number.
	 * @param string $lang  Language.
	 * @param string $site  Site name.
	 * @return string
	 */
	private static function with_page( $title, $paged, $lang, $site ) {
		$page   = sprintf( Languages::ui_string( 'page_n', $lang ), (int) $paged );
		$suffix = self::SEPARATOR . $site;
		if ( '' !== $site && substr( $title, -strlen( $suffix ) ) === $suffix ) {
			return substr( $title, 0, -strlen( $suffix ) ) . ' – ' . $page . $suffix;
		}
		return $title . ' – ' . $page;
	}

	/**
	 * Label of an author / date / post type archive in the request language.
	 *
	 * @param array $ctx Context.
	 * @return string
	 */
	private static function archive_label( array $ctx ) {
		global $wp_query;
		if ( 'author' === $ctx['type'] ) {
			$author = $wp_query instanceof \WP_Query ? $wp_query->get_queried_object() : null;
			return $author instanceof \WP_User ? $author->display_name : '';
		}
		if ( 'post_type_archive' === $ctx['type'] ) {
			return post_type_archive_title( '', false );
		}
		$year  = $wp_query instanceof \WP_Query ? (int) $wp_query->get( 'year' ) : 0;
		$month = $wp_query instanceof \WP_Query ? (int) $wp_query->get( 'monthnum' ) : 0;
		$day   = $wp_query instanceof \WP_Query ? (int) $wp_query->get( 'day' ) : 0;
		$m     = $wp_query instanceof \WP_Query ? (string) $wp_query->get( 'm' ) : '';
		if ( '' !== $m && ! $year ) {
			$year  = (int) substr( $m, 0, 4 );
			$month = (int) substr( $m, 4, 2 );
			$day   = (int) substr( $m, 6, 2 );
		}
		if ( ! $year ) {
			return get_the_archive_title();
		}
		$timestamp = mktime( 0, 0, 0, $month ? $month : 1, $day ? $day : 1, $year );
		if ( $day ) {
			return wp_date( get_option( 'date_format' ), $timestamp );
		}
		if ( $month ) {
			return wp_date( 'F Y', $timestamp );
		}
		return (string) $year;
	}

	/*
	 * Description
	 */

	/**
	 * Meta description (contract `mrtu_seo_description()`), raw text, '' when nothing sensible exists.
	 *
	 * Order: `_mrtu_seo_desc` → excerpt (trimmed to 155 characters) → ''. Terms: term meta → term description.
	 *
	 * @param mixed $context Context array, post, term or null.
	 * @return string
	 */
	public static function description( $context = null ) {
		$ctx  = is_array( $context ) ? $context : self::context( $context );
		$desc = '';
		if ( in_array( $ctx['type'], array( 'front', 'home', 'singular', 'attachment' ), true ) && $ctx['post'] instanceof \WP_Post ) {
			$desc = (string) get_post_meta( $ctx['post']->ID, self::META_DESC, true );
			if ( '' === trim( $desc ) ) {
				$desc = self::trim_text( self::post_excerpt_text( $ctx['post'] ), self::DESC_TRIM );
			}
		} elseif ( 'term' === $ctx['type'] && $ctx['term'] instanceof \WP_Term ) {
			$desc = (string) get_term_meta( $ctx['term']->term_id, self::META_DESC, true );
			if ( '' === trim( $desc ) ) {
				$desc = self::trim_text( self::plain_text( $ctx['term']->description ), self::DESC_TRIM );
			}
		}

		/**
		 * Filters the meta description.
		 *
		 * @param string $desc    Description (raw text, may be '').
		 * @param array  $context Request context.
		 */
		$desc = apply_filters( 'mrtu_seo_description', $desc, $ctx );
		return self::plain_text( (string) $desc );
	}

	/*
	 * Robots
	 */

	/**
	 * Robots directives we add for the request: array( 'noindex' => true, 'follow' => true ) or array().
	 *
	 * Noindex applies to search, 404, attachments, date/author archives (settings), `_mrtu_noindex` and empty term archives.
	 *
	 * @param mixed $context Context array, post, term or null.
	 * @return array
	 */
	public static function robots( $context = null ) {
		return self::is_noindex( $context ) ? array(
			'noindex' => true,
			'follow'  => true,
		) : array();
	}

	/**
	 * Whether the request is noindex.
	 *
	 * @param mixed $context Context array, post, term or null.
	 * @return bool
	 */
	public static function is_noindex( $context = null ) {
		$ctx = is_array( $context ) ? $context : self::context( $context );
		switch ( $ctx['type'] ) {
			case 'search':
			case '404':
			case 'attachment':
				return true;
			case 'author':
				return (bool) self::setting( 'noindex_author', true );
			case 'date':
				return (bool) self::setting( 'noindex_date', true );
			case 'term':
				return $ctx['term'] instanceof \WP_Term && ( (int) $ctx['term']->count < 1 || self::term_noindex( $ctx['term'] ) );
			case 'front':
			case 'home':
			case 'singular':
				return $ctx['post'] instanceof \WP_Post && self::post_noindex( $ctx['post'] );
		}
		return false;
	}

	/**
	 * Whether a post carries `_mrtu_noindex`.
	 *
	 * @param int|\WP_Post $post Post.
	 * @return bool
	 */
	public static function post_noindex( $post ) {
		$post = get_post( $post );
		return $post instanceof \WP_Post && '1' === (string) get_post_meta( $post->ID, self::META_NOINDEX, true );
	}

	/**
	 * Whether a term carries `_mrtu_noindex`.
	 *
	 * @param int|\WP_Term $term Term.
	 * @return bool
	 */
	public static function term_noindex( $term ) {
		$term = get_term( $term );
		return $term instanceof \WP_Term && '1' === (string) get_term_meta( $term->term_id, self::META_NOINDEX, true );
	}

	/*
	 * Canonical
	 */

	/**
	 * Canonical URL (contract `mrtu_canonical_url()`); '' on search and 404.
	 *
	 * Singular → permalink (+ /N/ for paginated posts); front → language home; posts page / term and other
	 * archives → their link (+ /page/N/).
	 *
	 * @param mixed $context Context array, post, term or null.
	 * @return string
	 */
	public static function canonical( $context = null ) {
		$ctx = is_array( $context ) ? $context : self::context( $context );
		switch ( $ctx['type'] ) {
			case 'search':
			case '404':
			case 'other':
				return '';
			case 'front':
				return self::paged_url( Links::language_home( $ctx['lang'] ), $ctx['paged'] );
			case 'home':
				$base = $ctx['post'] instanceof \WP_Post ? (string) get_permalink( $ctx['post'] ) : Links::language_home( $ctx['lang'] );
				return self::paged_url( $base, $ctx['paged'] );
			case 'singular':
			case 'attachment':
				if ( ! $ctx['post'] instanceof \WP_Post ) {
					return '';
				}
				$url = wp_get_canonical_url( $ctx['post'] );
				return $url ? (string) $url : (string) get_permalink( $ctx['post'] );
			case 'term':
				if ( ! $ctx['term'] instanceof \WP_Term ) {
					return '';
				}
				$link = get_term_link( $ctx['term'] );
				return is_wp_error( $link ) ? '' : self::paged_url( $link, $ctx['paged'] );
			case 'author':
				$author = isset( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query']->get_queried_object() : null;
				return $author instanceof \WP_User ? self::paged_url( get_author_posts_url( $author->ID, $author->user_nicename ), $ctx['paged'] ) : '';
			case 'date':
				return self::paged_url( self::date_archive_url(), $ctx['paged'] );
			case 'post_type_archive':
				$type = isset( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query']->get( 'post_type' ) : '';
				$type = is_array( $type ) ? reset( $type ) : $type;
				$link = get_post_type_archive_link( (string) $type );
				return $link ? self::paged_url( $link, $ctx['paged'] ) : '';
		}
		return '';
	}

	/**
	 * Link of the current date archive (year / month / day).
	 *
	 * @return string
	 */
	private static function date_archive_url() {
		$query = isset( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query'] : null;
		if ( ! $query instanceof \WP_Query ) {
			return '';
		}
		$year  = (int) $query->get( 'year' );
		$month = (int) $query->get( 'monthnum' );
		$day   = (int) $query->get( 'day' );
		$m     = (string) $query->get( 'm' );
		if ( '' !== $m && ! $year ) {
			$year  = (int) substr( $m, 0, 4 );
			$month = (int) substr( $m, 4, 2 );
			$day   = (int) substr( $m, 6, 2 );
		}
		if ( $day ) {
			return get_day_link( $year, $month, $day );
		}
		if ( $month ) {
			return get_month_link( $year, $month );
		}
		return $year ? get_year_link( $year ) : '';
	}

	/**
	 * Adds the pagination segment to an archive URL.
	 *
	 * @param string $url   Base URL.
	 * @param int    $paged Page number.
	 * @return string
	 */
	public static function paged_url( $url, $paged ) {
		$url   = (string) $url;
		$paged = (int) $paged;
		if ( '' === $url || $paged < 2 ) {
			return $url;
		}
		global $wp_rewrite;
		if ( $wp_rewrite instanceof \WP_Rewrite && $wp_rewrite->using_permalinks() ) {
			return trailingslashit( $url ) . $wp_rewrite->pagination_base . '/' . $paged . '/';
		}
		return add_query_arg( 'paged', $paged, $url );
	}

	/*
	 * Alternates (shared by head, sitemaps and Yoast compat)
	 */

	/**
	 * Whether a post may appear in hreflang / sitemaps: published, no password, not noindex, language enabled.
	 *
	 * @param int|\WP_Post $post Post.
	 * @return bool
	 */
	public static function post_is_indexable( $post ) {
		$post = get_post( $post );
		return $post instanceof \WP_Post
			&& 'publish' === $post->post_status
			&& '' === $post->post_password
			&& ! self::post_noindex( $post )
			&& ( ! Translations::is_translatable_post_type( $post->post_type ) || Languages::is_enabled( Translations::get_post_language( $post ) ) );
	}

	/**
	 * Whether a term archive may appear in hreflang / sitemaps: has posts, not noindex, language enabled.
	 *
	 * @param int|\WP_Term $term Term.
	 * @return bool
	 */
	public static function term_is_indexable( $term ) {
		$term = get_term( $term );
		return $term instanceof \WP_Term
			&& (int) $term->count > 0
			&& ! self::term_noindex( $term )
			&& Languages::is_enabled( Translations::get_term_language( $term ) );
	}

	/**
	 * Alternates of one object, same rules as Switcher::alternates() for the live request.
	 *
	 * @param string $kind    post | term | home.
	 * @param mixed  $subject Post (int|WP_Post), term (int|WP_Term) or language code for `home`.
	 * @return array hreflang => url (+ 'x-default'); empty when the object itself is not indexable.
	 */
	public static function object_alternates( $kind, $subject ) {
		$languages = Languages::all( true );
		$urls      = array();

		if ( 'post' === $kind ) {
			$post = get_post( $subject );
			if ( ! $post instanceof \WP_Post || ! Translations::is_translatable_post_type( $post->post_type ) || ! self::post_is_indexable( $post ) ) {
				return array();
			}
			$map = Translations::get_post_translations( $post );
			Translations::prime( array_values( $map ) );
			foreach ( $languages as $code => $lang ) {
				if ( empty( $map[ $code ] ) || ! self::post_is_indexable( (int) $map[ $code ] ) ) {
					continue;
				}
				$urls[ $code ] = (string) get_permalink( (int) $map[ $code ] );
			}
		} elseif ( 'term' === $kind ) {
			$term = get_term( $subject );
			if ( ! $term instanceof \WP_Term || ! Translations::is_translatable_taxonomy( $term->taxonomy ) || ! self::term_is_indexable( $term ) ) {
				return array();
			}
			$map = Translations::get_term_translations( $term );
			foreach ( $languages as $code => $lang ) {
				if ( empty( $map[ $code ] ) ) {
					continue;
				}
				$translation = get_term( (int) $map[ $code ], $term->taxonomy );
				if ( ! $translation instanceof \WP_Term || ! self::term_is_indexable( $translation ) ) {
					continue;
				}
				$link = get_term_link( $translation );
				if ( ! is_wp_error( $link ) ) {
					$urls[ $code ] = $link;
				}
			}
		} elseif ( 'home' === $kind ) {
			$self  = (string) $subject;
			$front = 'page' === get_option( 'show_on_front' ) ? Router::raw_option( 'page_on_front' ) : 0;
			$map   = $front ? Translations::get_post_translations( $front ) : array();
			foreach ( $languages as $code => $lang ) {
				if ( $front && ( empty( $map[ $code ] ) || ! self::post_is_indexable( (int) $map[ $code ] ) ) ) {
					continue;
				}
				$urls[ $code ] = Links::language_home( $code );
			}
			if ( ! isset( $urls[ $self ] ) ) {
				return array();
			}
		} else {
			return array();
		}

		$out = array();
		foreach ( $urls as $code => $url ) {
			if ( '' !== $url ) {
				$out[ $languages[ $code ]['hreflang'] ] = $url;
			}
		}
		$x_default = (string) self::setting( 'x_default', '' );
		if ( '' !== $x_default && isset( $urls[ $x_default ] ) && '' !== $urls[ $x_default ] ) {
			$out['x-default'] = $urls[ $x_default ];
		}
		return $out;
	}

	/**
	 * Alternates worth printing: at least two language versions (x-default does not count).
	 *
	 * @param array $alternates hreflang => url.
	 * @return array
	 */
	public static function usable_alternates( array $alternates ) {
		$languages = array_diff_key( $alternates, array( 'x-default' => true ) );
		return count( $languages ) >= 2 ? $alternates : array();
	}

	/**
	 * Alternates for the live request (what the head prints): mrtu_alternate_urls() with the ≥ 2 rule.
	 *
	 * @return array hreflang => url
	 */
	public static function head_alternates() {
		return self::usable_alternates( Switcher::alternates() );
	}

	/*
	 * Text helpers
	 */

	/**
	 * Site name (General Settings).
	 *
	 * @return string
	 */
	public static function site_name() {
		return self::plain_text( get_bloginfo( 'name', 'display' ) );
	}

	/**
	 * Plain text: tags stripped, entities decoded, whitespace collapsed.
	 *
	 * @param string $html Text or HTML.
	 * @return string
	 */
	public static function plain_text( $html ) {
		$text = wp_strip_all_tags( (string) $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/[\s\x{00A0}]+/u', ' ', $text );
		return trim( (string) $text );
	}

	/**
	 * Cuts text at a word boundary and appends an ellipsis when it was longer.
	 *
	 * @param string $text Plain text.
	 * @param int    $max  Maximum characters.
	 * @return string
	 */
	public static function trim_text( $text, $max ) {
		$text = self::plain_text( $text );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		$cut   = mb_substr( $text, 0, max( 1, (int) $max - 1 ) );
		$space = mb_strrpos( $cut, ' ' );
		if ( false !== $space && $space > $max / 2 ) {
			$cut = mb_substr( $cut, 0, $space );
		}
		return rtrim( $cut, " \t\n\r\0\x0B,;:.-–" ) . '…';
	}

	/**
	 * Title of a post as plain text.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public static function post_title_text( \WP_Post $post ) {
		return self::plain_text( get_the_title( $post ) );
	}

	/**
	 * Excerpt text of a post: the manual excerpt, else the first text blocks of the content.
	 *
	 * @param \WP_Post $post Post.
	 * @return string Plain text (untrimmed).
	 */
	public static function post_excerpt_text( \WP_Post $post ) {
		if ( '' !== trim( (string) $post->post_excerpt ) ) {
			return self::plain_text( strip_shortcodes( $post->post_excerpt ) );
		}
		if ( ! empty( $post->post_password ) ) {
			return '';
		}
		$content = (string) $post->post_content;
		if ( has_blocks( $content ) ) {
			$content = excerpt_remove_blocks( $content );
		}
		$content = strip_shortcodes( $content );
		return self::plain_text( $content );
	}
}
