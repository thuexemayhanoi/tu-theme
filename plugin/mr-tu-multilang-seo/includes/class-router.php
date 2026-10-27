<?php
/**
 * URL prefix router (contract 2.5).
 *
 * The request language is detected as soon as the plugin loads (frontend only) from the first path
 * segment. The prefix is hidden from WP::parse_request() (REQUEST_URI is stripped in `do_parse_request`
 * and restored in `parse_request`), so WordPress' own rewrite rules keep working unchanged. Slug-based
 * requests are then resolved inside the current language (same slug allowed in every language).
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Language detection, request resolution and language-aware core behaviour.
 */
final class Router {

	const QUERY_VAR = 'mrtu_lang';

	/**
	 * Current request language ('' = not determined, e.g. admin or CLI).
	 *
	 * @var string
	 */
	private static $current = '';

	/**
	 * Whether the frontend routing hooks are active.
	 *
	 * @var bool
	 */
	private static $frontend = false;

	/**
	 * Language whose prefix matched the URL ('' = none, default language).
	 *
	 * @var string
	 */
	private static $prefix_lang = '';

	/**
	 * Whether the URL is exactly "/{prefix}" (needs the trailing slash redirect).
	 *
	 * @var bool
	 */
	private static $needs_slash = false;

	/**
	 * Whether the URL starts with the prefix of a disabled language.
	 *
	 * @var bool
	 */
	private static $disabled_hit = false;

	/**
	 * Original REQUEST_URI while WordPress parses the stripped one.
	 *
	 * @var string|null
	 */
	private static $saved_uri = null;

	/**
	 * Original PATH_INFO while WordPress parses the stripped one.
	 *
	 * @var string|null
	 */
	private static $saved_path_info = null;

	/**
	 * Depth counter: > 0 while reading unfiltered options.
	 *
	 * @var int
	 */
	private static $bypass = 0;

	/**
	 * Whether `mrtu_language_detected` already fired.
	 *
	 * @var bool
	 */
	private static $announced = false;

	/**
	 * Post id the router resolved a slug request to (0 = none).
	 *
	 * @var int
	 */
	private static $resolved_id = 0;

	/**
	 * Frontend hooks registered by start(): [ type, hook, method, priority, args ].
	 *
	 * @var array
	 */
	private static $frontend_hooks = array(
		array( 'filter', 'do_parse_request', 'before_parse_request', 1, 3 ),
		array( 'action', 'parse_request', 'after_parse_request', 0, 1 ),
		array( 'action', 'wp', 'restore_request_uri', -1000, 0 ),
		array( 'filter', 'request', 'resolve_request', 10, 1 ),
		array( 'filter', 'locale', 'filter_locale', 10, 1 ),
		array( 'filter', 'option_page_on_front', 'translate_page_option', 10, 1 ),
		array( 'filter', 'option_page_for_posts', 'translate_page_option', 10, 1 ),
		array( 'filter', 'option_wp_page_for_privacy_policy', 'translate_page_option', 10, 1 ),
		array( 'filter', 'option_blogdescription', 'filter_tagline', 10, 1 ),
		array( 'filter', 'language_attributes', 'filter_language_attributes', 10, 1 ),
		array( 'filter', 'redirect_canonical', 'guard_redirect', 10, 1 ),
		array( 'filter', 'old_slug_redirect_url', 'guard_old_slug_redirect', 10, 1 ),
		array( 'filter', 'get_terms_args', 'scope_terms', 10, 2 ),
		array( 'filter', 'get_search_form', 'localize_search_form', 10, 1 ),
		array( 'filter', 'render_block_core/search', 'localize_search_form', 10, 1 ),
	);

	/**
	 * Wires hooks; starts routing immediately on frontend requests.
	 */
	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
		add_filter( 'wp_unique_post_slug', array( __CLASS__, 'unique_post_slug' ), 10, 6 );
		add_filter( 'wp_unique_post_slug_is_bad_hierarchical_slug', array( __CLASS__, 'is_bad_hierarchical_slug' ), 10, 4 );
		add_filter( 'wp_unique_post_slug_is_bad_flat_slug', array( __CLASS__, 'is_bad_flat_slug' ), 10, 2 );
		add_filter( 'wp_unique_post_slug_is_bad_attachment_slug', array( __CLASS__, 'is_bad_flat_slug' ), 10, 2 );
		add_filter( 'get_previous_post_where', array( __CLASS__, 'adjacent_post_where' ), 10, 5 );
		add_filter( 'get_next_post_where', array( __CLASS__, 'adjacent_post_where' ), 10, 5 );
		add_action( 'init', array( __CLASS__, 'register_rest_hooks' ), 20 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'rest_language' ), 10, 3 );

		if ( self::is_frontend_request() ) {
			// Parsed only (never output or stored); stripping/restoring happens around WP::parse_request().
			$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			self::start( (string) $uri );
		}
	}

	/*
	 * State
	 */

	/**
	 * Whether this request is a frontend page request routed by the plugin.
	 *
	 * @return bool
	 */
	public static function is_frontend_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		foreach ( array( 'XMLRPC_REQUEST', 'REST_REQUEST', 'WP_CLI', 'WP_INSTALLING' ) as $constant ) {
			if ( defined( $constant ) && constant( $constant ) ) {
				return false;
			}
		}
		global $pagenow;
		if ( ! empty( $pagenow ) && 'index.php' !== $pagenow ) {
			return false; // Login, sign-up, comment posting and other entry scripts.
		}
		if ( empty( $_SERVER['REQUEST_URI'] ) || isset( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing detection only.
			return false;
		}
		$relative = self::relative_path( wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed only.
		if ( null === $relative ) {
			return false;
		}
		$rest = trim( rest_get_url_prefix(), '/' );
		return ! ( $relative === $rest || 0 === strpos( $relative, $rest . '/' ) );
	}

	/**
	 * Starts frontend routing for a request URI (also used by the test harness).
	 *
	 * @param string $request_uri Request URI (path + query).
	 */
	public static function start( $request_uri ) {
		self::detect( $request_uri );
		if ( self::$frontend ) {
			return;
		}
		self::$frontend = true;
		foreach ( self::$frontend_hooks as $hook ) {
			$fn = 'filter' === $hook[0] ? 'add_filter' : 'add_action';
			$fn( $hook[1], array( __CLASS__, $hook[2] ), $hook[3], $hook[4] );
		}
	}

	/**
	 * Stops frontend routing and resets the state (test harness / CLI only).
	 */
	public static function stop() {
		foreach ( self::$frontend_hooks as $hook ) {
			remove_filter( $hook[1], array( __CLASS__, $hook[2] ), $hook[3] );
		}
		self::$frontend     = false;
		self::$current      = '';
		self::$prefix_lang  = '';
		self::$needs_slash  = false;
		self::$disabled_hit = false;
		self::$announced    = false;
		self::$saved_uri    = null;
	}

	/**
	 * Whether frontend routing is active.
	 *
	 * @return bool
	 */
	public static function is_frontend() {
		return self::$frontend;
	}

	/**
	 * Current language (contract `mrtu_current_language()`).
	 *
	 * Frontend: from the URL (or the queried object for ID-based / preview requests). REST: the `lang`
	 * parameter. Admin: '' unless a post is being edited.
	 *
	 * @return string
	 */
	public static function current_language() {
		if ( '' !== self::$current ) {
			return self::$current;
		}
		if ( is_admin() ) {
			return self::admin_language();
		}
		return '';
	}

	/**
	 * Current language, falling back to the default language.
	 *
	 * @return string
	 */
	public static function effective_language() {
		$lang = self::current_language();
		return '' !== $lang ? $lang : Languages::default_code();
	}

	/**
	 * Forces the current language (REST `lang`, tests). Does not switch the locale.
	 *
	 * @param string $code Language code ('' to reset).
	 */
	public static function set_current_language( $code ) {
		self::$current = '' === $code || Languages::exists( $code ) ? (string) $code : self::$current;
	}

	/**
	 * Language of the post being edited in wp-admin.
	 *
	 * @return string
	 */
	private static function admin_language() {
		global $pagenow;
		// phpcs:disable WordPress.Security.NonceVerification -- read-only context detection; nothing is saved here.
		$post_id = 0;
		if ( 'post.php' === $pagenow ) {
			if ( isset( $_GET['post'] ) ) {
				$post_id = absint( wp_unslash( $_GET['post'] ) );
			} elseif ( isset( $_POST['post_ID'] ) ) {
				$post_id = absint( wp_unslash( $_POST['post_ID'] ) );
			}
		}
		if ( $post_id ) {
			$post = get_post( $post_id );
			return $post && Translations::is_translatable_post_type( $post->post_type ) ? Translations::get_post_language( $post ) : '';
		}
		if ( 'post-new.php' === $pagenow ) {
			$lang = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : '';
			return Languages::exists( $lang ) ? $lang : Languages::default_code();
		}
		// phpcs:enable
		return '';
	}

	/**
	 * Unfiltered value of a Reading/Privacy page option.
	 *
	 * @param string $option page_on_front | page_for_posts | wp_page_for_privacy_policy.
	 * @return int
	 */
	public static function raw_option( $option ) {
		++self::$bypass;
		$value = (int) get_option( $option );
		--self::$bypass;
		return $value;
	}

	/*
	 * Detection
	 */

	/**
	 * Home path without slashes ('' for a root install).
	 *
	 * @return string
	 */
	public static function home_path() {
		$path = wp_parse_url( home_url(), PHP_URL_PATH );
		return is_string( $path ) ? trim( $path, '/' ) : '';
	}

	/**
	 * Path of a request URI relative to the home path, without leading slash.
	 *
	 * @param string $request_uri Request URI.
	 * @return string|null Null when the URI is outside the home path.
	 */
	public static function relative_path( $request_uri ) {
		$path = wp_parse_url( '/' . ltrim( (string) $request_uri, '/' ), PHP_URL_PATH );
		$path = is_string( $path ) ? ltrim( $path, '/' ) : '';
		$home = self::home_path();
		if ( '' === $home ) {
			return $path;
		}
		if ( $path === $home ) {
			return '';
		}
		if ( 0 !== strpos( $path, $home . '/' ) ) {
			return null;
		}
		return (string) substr( $path, strlen( $home ) + 1 );
	}

	/**
	 * Detects the language from the first path segment.
	 *
	 * @param string $request_uri Request URI.
	 */
	private static function detect( $request_uri ) {
		self::$current      = Languages::default_code();
		self::$prefix_lang  = '';
		self::$needs_slash  = false;
		self::$disabled_hit = false;
		self::$announced    = false;
		self::$resolved_id  = 0;

		$relative = self::relative_path( $request_uri );
		if ( null === $relative || '' === $relative ) {
			return;
		}
		$parts = explode( '/', $relative, 2 );
		$rest  = isset( $parts[1] ) ? $parts[1] : null;
		foreach ( Languages::all( false ) as $code => $lang ) {
			if ( '' === $lang['prefix'] || $parts[0] !== $lang['prefix'] ) {
				continue;
			}
			if ( null !== $rest && preg_match( '#^(wp-json|wp-admin|wp-content|wp-includes|wp-login\.php|xmlrpc\.php)(/|$)#', $rest ) ) {
				return;
			}
			if ( ! $lang['enabled'] ) {
				self::$disabled_hit = true;
				return;
			}
			self::$current     = $code;
			self::$prefix_lang = $code;
			self::$needs_slash = null === $rest;
			return;
		}
	}

	/**
	 * Fires `mrtu_language_detected` once the request language is final.
	 */
	private static function announce() {
		if ( self::$announced ) {
			return;
		}
		self::$announced = true;
		/**
		 * Fires once per request when the language of the request is known.
		 *
		 * @param string $lang Language code.
		 */
		do_action( 'mrtu_language_detected', self::$current );
	}

	/**
	 * Switches the request language after detection (ID-based requests and previews).
	 *
	 * @param string $code Language code.
	 */
	private static function switch_language( $code ) {
		if ( $code === self::$current || ! Languages::exists( $code ) ) {
			return;
		}
		$lang = Languages::get( $code );
		if ( did_action( 'init' ) && function_exists( 'switch_to_locale' ) && determine_locale() !== $lang['locale'] ) {
			// Must run while the old language is current so the switcher sees a change.
			switch_to_locale( $lang['locale'] );
		}
		self::$current = $code;
	}

	/*
	 * Parse request
	 */

	/**
	 * Redirects "/{prefix}" to "/{prefix}/" and hides the prefix from WP::parse_request().
	 *
	 * @param bool $do_parse Whether to parse the request.
	 * @return bool
	 */
	public static function before_parse_request( $do_parse ) {
		if ( self::$needs_slash ) {
			$query  = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- re-appended to a same-site URL via wp_safe_redirect().
			$target = Links::language_home( self::$prefix_lang ) . ( $query ? '?' . $query : '' );
			wp_safe_redirect( $target, 301, 'Mr Tu Multilang' );
			exit;
		}
		if ( ! $do_parse || '' === self::$prefix_lang || null !== self::$saved_uri || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return $do_parse;
		}
		$prefix                 = Languages::get( self::$prefix_lang )['prefix'];
		self::$saved_uri        = $_SERVER['REQUEST_URI']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- restored verbatim in after_parse_request().
		$_SERVER['REQUEST_URI'] = self::strip_prefix( self::$saved_uri, $prefix );
		if ( ! empty( $_SERVER['PATH_INFO'] ) ) {
			self::$saved_path_info = $_SERVER['PATH_INFO']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- restored verbatim.
			$_SERVER['PATH_INFO']  = self::strip_prefix( self::$saved_path_info, $prefix );
		}
		return $do_parse;
	}

	/**
	 * Removes "/{prefix}" right after the home path of a URI.
	 *
	 * @param string $uri    URI (path + query).
	 * @param string $prefix Language prefix.
	 * @return string
	 */
	private static function strip_prefix( $uri, $prefix ) {
		$home  = self::home_path();
		$base  = '' === $home ? '/' : '/' . $home . '/';
		$regex = '#^' . preg_quote( $base . $prefix, '#' ) . '(?=/|\?|$)/?#';
		$out   = preg_replace( $regex, $base, (string) $uri, 1 );
		return is_string( $out ) ? $out : (string) $uri;
	}

	/**
	 * Restores REQUEST_URI and adds the prefix back to $wp->request.
	 *
	 * @param \WP $wp WordPress environment.
	 */
	public static function after_parse_request( $wp ) {
		if ( null !== self::$saved_uri ) {
			self::restore_request_uri();
			if ( '' !== self::$prefix_lang && $wp instanceof \WP ) {
				$wp->request = trim( Languages::get( self::$prefix_lang )['prefix'] . '/' . $wp->request, '/' );
			}
		}
		self::announce();
	}

	/**
	 * Restores the original REQUEST_URI / PATH_INFO (also a safety net when parsing was aborted).
	 */
	public static function restore_request_uri() {
		if ( null === self::$saved_uri ) {
			return;
		}
		$_SERVER['REQUEST_URI'] = self::$saved_uri;
		self::$saved_uri        = null;
		if ( null !== self::$saved_path_info ) {
			$_SERVER['PATH_INFO']  = self::$saved_path_info;
			self::$saved_path_info = null;
		}
	}

	/**
	 * Resolves slug-based requests inside the current language (filter `request`).
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public static function resolve_request( $vars ) {
		$vars = is_array( $vars ) ? $vars : array();
		unset( $vars[ self::QUERY_VAR ] ); // On the frontend the URL is the only language source.

		if ( self::$disabled_hit ) {
			return array( 'error' => '404' );
		}
		if ( ! empty( $vars['error'] ) ) {
			return $vars;
		}
		$lang = self::$current;

		// ID-based requests (?p=, ?page_id=, previews): the queried object decides the language.
		foreach ( array( 'p', 'page_id', 'attachment_id' ) as $key ) {
			if ( ! empty( $vars[ $key ] ) && is_scalar( $vars[ $key ] ) ) {
				$post = get_post( absint( $vars[ $key ] ) );
				if ( $post && Translations::is_translatable_post_type( $post->post_type ) ) {
					self::switch_language( Translations::get_post_language( $post ) );
				}
				return $vars;
			}
		}

		if ( self::is_home_request( $vars ) ) {
			$front = self::raw_option( 'page_on_front' );
			if ( Languages::default_code() !== $lang && 'page' === get_option( 'show_on_front' ) && $front && ! Translations::get_post_translation( $front, $lang ) ) {
				return array( 'error' => '404' ); // No published home in this language.
			}
			return $vars;
		}

		if ( isset( $vars['pagename'] ) && is_string( $vars['pagename'] ) && '' !== trim( $vars['pagename'], '/' ) ) {
			return self::resolve_pagename( $vars, $lang );
		}
		if ( isset( $vars['name'] ) && is_string( $vars['name'] ) && '' !== $vars['name'] ) {
			return self::resolve_name( $vars, $lang );
		}
		return self::check_term_archive( $vars, $lang );
	}

	/**
	 * Whether the query vars describe the home page (optionally paged / preview).
	 *
	 * @param array $vars Query vars.
	 * @return bool
	 */
	private static function is_home_request( array $vars ) {
		foreach ( $vars as $key => $value ) {
			if ( in_array( $key, array( 'preview', 'page', 'paged', 'cpage' ), true ) ) {
				continue;
			}
			if ( '' !== $value && null !== $value && array() !== $value ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Resolves `pagename` (hierarchical path) inside a language; falls back to a flat post slug.
	 *
	 * @param array  $vars Query vars.
	 * @param string $lang Language.
	 * @return array
	 */
	private static function resolve_pagename( array $vars, $lang ) {
		$path  = $vars['pagename'];
		$types = array_values( array_filter( Translations::post_types(), 'is_post_type_hierarchical' ) );
		if ( ! empty( $vars['post_type'] ) && is_string( $vars['post_type'] ) ) {
			$types = array_values( array_intersect( $types, array( $vars['post_type'] ) ) );
		}

		$id = $types ? Translations::find_post_by_path( $path, $lang, $types ) : 0;
		if ( $id ) {
			self::$resolved_id = $id;
			$type              = get_post_type( $id );
			unset( $vars['pagename'] );
			if ( 'page' === $type ) {
				$vars['page_id'] = $id;
			} else {
				$vars['p']         = $id;
				$vars['post_type'] = $type;
			}
			return $vars;
		}

		// "/%postname%/" puts posts at the root too: a single segment may be a post of this language.
		$slug = trim( $path, '/' );
		if ( false === strpos( $slug, '/' ) ) {
			$flat = array_values( array_diff( Translations::post_types(), array_filter( Translations::post_types(), 'is_post_type_hierarchical' ) ) );
			$id   = $flat ? Translations::find_post_by_name( $slug, $lang, $flat ) : 0;
			if ( $id ) {
				self::$resolved_id = $id;
				unset( $vars['pagename'] );
				$vars['name']      = $slug;
				$vars['p']         = $id;
				$vars['post_type'] = get_post_type( $id );
				return $vars;
			}
		}

		// Untranslatable content (attachments, other hierarchical types) stays with WordPress.
		$core = get_page_by_path( $path );
		if ( $core && ! Translations::is_translatable_post_type( $core->post_type ) ) {
			return $vars;
		}
		return array( 'error' => '404' );
	}

	/**
	 * Resolves `name` (+ optional post_type) inside a language.
	 *
	 * @param array  $vars Query vars.
	 * @param string $lang Language.
	 * @return array
	 */
	private static function resolve_name( array $vars, $lang ) {
		$types        = ! empty( $vars['post_type'] ) ? (array) $vars['post_type'] : array( 'post' );
		$translatable = array_values( array_filter( $types, array( Translations::class, 'is_translatable_post_type' ) ) );
		if ( ! $translatable ) {
			return $vars;
		}
		$hierarchical = array_values( array_filter( $translatable, 'is_post_type_hierarchical' ) );
		$id           = $hierarchical
			? Translations::find_post_by_path( $vars['name'], $lang, $hierarchical )
			: Translations::find_post_by_name( $vars['name'], $lang, $translatable );
		if ( $id ) {
			self::$resolved_id = $id;
			$vars['p']         = $id;
			if ( empty( $vars['post_type'] ) && 'post' !== get_post_type( $id ) ) {
				$vars['post_type'] = get_post_type( $id );
			}
			return $vars;
		}
		if ( count( $translatable ) < count( $types ) ) {
			return $vars;
		}
		return array( 'error' => '404' );
	}

	/**
	 * Term archives of translatable taxonomies only exist in the term's own language.
	 *
	 * @param array  $vars Query vars.
	 * @param string $lang Language.
	 * @return array
	 */
	private static function check_term_archive( array $vars, $lang ) {
		foreach ( Translations::taxonomies() as $taxonomy ) {
			$tax = get_taxonomy( $taxonomy );
			if ( ! $tax || ! $tax->query_var || empty( $vars[ $tax->query_var ] ) || ! is_string( $vars[ $tax->query_var ] ) ) {
				continue;
			}
			$value = $vars[ $tax->query_var ];
			if ( preg_match( '/[,+]/', $value ) ) {
				continue; // Multi-term archives are left to WordPress.
			}
			$segments = explode( '/', trim( $value, '/' ) );
			$slug     = sanitize_title_for_query( (string) end( $segments ) );
			$term     = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term && Translations::get_term_language( $term ) !== $lang ) {
				return array( 'error' => '404' );
			}
		}
		return $vars;
	}

	/*
	 * Locale, options, head
	 */

	/**
	 * Frontend locale = locale of the request language.
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function filter_locale( $locale ) {
		if ( '' === self::$current ) {
			return $locale;
		}
		$lang = Languages::get( self::$current );
		return $lang ? $lang['locale'] : $locale;
	}

	/**
	 * Front page / posts page / privacy page resolve to their published translation.
	 *
	 * @param mixed $value Option value.
	 * @return mixed
	 */
	public static function translate_page_option( $value ) {
		if ( self::$bypass > 0 || ! $value || '' === self::$current || ! did_action( 'init' ) ) {
			return $value;
		}
		$id = Translations::get_post_translation( (int) $value, self::$current, true );
		return $id ? $id : $value;
	}

	/**
	 * Tagline per language (the default language falls back to the WordPress tagline).
	 *
	 * @param string $value Tagline.
	 * @return string
	 */
	public static function filter_tagline( $value ) {
		if ( self::$bypass > 0 || '' === self::$current ) {
			return $value;
		}
		$lang = Languages::get( self::$current );
		if ( ! $lang ) {
			return $value;
		}
		if ( '' !== $lang['tagline'] ) {
			return $lang['tagline'];
		}
		return $lang['is_default'] ? $value : '';
	}

	/**
	 * `<html lang>` uses the hreflang code of the request language (vi-VN, en, de, fr, es).
	 *
	 * @param string $output Attributes.
	 * @return string
	 */
	public static function filter_language_attributes( $output ) {
		$lang = Languages::get( self::$current );
		if ( ! $lang ) {
			return $output;
		}
		$replaced = preg_replace( '/\b(xml:)?lang="[^"]*"/', '$1lang="' . esc_attr( $lang['hreflang'] ) . '"', (string) $output );
		if ( is_string( $replaced ) && false === strpos( $replaced, 'lang="' ) ) {
			$replaced = trim( $replaced . ' lang="' . esc_attr( $lang['hreflang'] ) . '"' );
		}
		return is_string( $replaced ) ? $replaced : $output;
	}

	/**
	 * Search forms submit to the language home so results stay in the current language.
	 *
	 * @param string $html Form markup.
	 * @return string
	 */
	public static function localize_search_form( $html ) {
		if ( '' === self::$current || Languages::default_code() === self::$current || ! is_string( $html ) ) {
			return $html;
		}
		$from = 'action="' . esc_url( home_url( '/' ) ) . '"';
		$to   = 'action="' . esc_url( Links::language_home( self::$current ) ) . '"';
		return str_replace( $from, $to, $html );
	}

	/*
	 * Redirect guards
	 */

	/**
	 * Canonical redirects never cross languages. The front page keeps its language home.
	 *
	 * @param string|false $redirect_url Redirect target.
	 * @return string|false
	 */
	public static function guard_redirect( $redirect_url ) {
		if ( ! $redirect_url || '' === self::$current ) {
			return $redirect_url;
		}
		// Core redirects 404s carrying ?p= / ?page_id= to that post. Ids injected by the slug resolver
		// are not user input: such a 404 (e.g. posts page beyond its last page) stays a 404, like core.
		if ( self::$resolved_id && function_exists( 'is_404' ) && is_404() && ! get_query_var( 'page' ) ) {
			return false;
		}
		$target = Links::language_of_url( $redirect_url );
		if ( null === $target || $target === self::$current ) {
			return $redirect_url;
		}
		// Core canonicalises the static front page to home_url( '/' ): use this language's home instead.
		$path = wp_parse_url( $redirect_url, PHP_URL_PATH );
		$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( function_exists( 'is_front_page' ) && is_front_page() && trailingslashit( (string) $path ) === trailingslashit( (string) $home ) ) {
			$query = wp_parse_url( $redirect_url, PHP_URL_QUERY );
			return Links::language_home( self::$current ) . ( $query ? '?' . $query : '' );
		}
		return false;
	}

	/**
	 * Old-slug redirects never cross languages.
	 *
	 * @param string $link Redirect target.
	 * @return string|false
	 */
	public static function guard_old_slug_redirect( $link ) {
		if ( ! $link || '' === self::$current ) {
			return $link;
		}
		$target = Links::language_of_url( $link );
		return null === $target || $target === self::$current ? $link : false;
	}

	/*
	 * Terms on the frontend
	 */

	/**
	 * Scopes term listings (category lists, tag clouds …) of translatable taxonomies to the current language.
	 *
	 * Lookups by id/slug/name/object and internal 'get' => 'all' queries (term hierarchy cache) are untouched.
	 * Pass 'mrtu_lang' => 'all' to opt out, or a language code to pick one.
	 *
	 * @param array    $args       get_terms() arguments.
	 * @param string[] $taxonomies Taxonomies.
	 * @return array
	 */
	public static function scope_terms( $args, $taxonomies ) {
		// Yoast's single sitemap covers every language, unlike frontend category lists.
		if ( get_query_var( 'sitemap' ) ) {
			return $args;
		}
		if ( '' === self::$current || ! is_array( $args ) || empty( $taxonomies ) ) {
			return $args;
		}
		$lang = isset( $args[ self::QUERY_VAR ] ) && is_string( $args[ self::QUERY_VAR ] ) ? $args[ self::QUERY_VAR ] : self::$current;
		if ( 'all' === $lang || ! Languages::exists( $lang ) ) {
			return $args;
		}
		foreach ( (array) $taxonomies as $taxonomy ) {
			if ( ! Translations::is_translatable_taxonomy( $taxonomy ) ) {
				return $args;
			}
		}
		foreach ( array( 'object_ids', 'include', 'slug', 'name', 'term_taxonomy_id' ) as $key ) {
			if ( ! empty( $args[ $key ] ) ) {
				return $args;
			}
		}
		if ( ( isset( $args['get'] ) && 'all' === $args['get'] ) || ( isset( $args['fields'] ) && 'id=>parent' === $args['fields'] ) ) {
			return $args;
		}

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
		$existing = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) && $args['meta_query'] ? $args['meta_query'] : null;
		$scoped   = $existing ? array(
			'relation' => 'AND',
			$existing,
			$clause,
		) : array( $clause );
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- term tables are small; this is the language scoping itself.
		$args['meta_query'] = $scoped;
		return $args;
	}

	/*
	 * Slugs
	 */

	/**
	 * Whether a slug equals a language prefix (enabled or not).
	 *
	 * @param string $slug Slug.
	 * @return bool
	 */
	public static function is_language_prefix( $slug ) {
		foreach ( Languages::all( false ) as $lang ) {
			if ( '' !== $lang['prefix'] && $slug === $lang['prefix'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Top-level pages may not take a language prefix as slug.
	 *
	 * @param bool   $bad         Whether the slug is bad.
	 * @param string $slug        Slug.
	 * @param string $post_type   Post type.
	 * @param int    $post_parent Parent.
	 * @return bool
	 */
	public static function is_bad_hierarchical_slug( $bad, $slug, $post_type, $post_parent ) {
		return $bad || ( 0 === (int) $post_parent && self::is_language_prefix( $slug ) );
	}

	/**
	 * Root-level posts/attachments may not take a language prefix as slug.
	 *
	 * @param bool   $bad  Whether the slug is bad.
	 * @param string $slug Slug.
	 * @return bool
	 */
	public static function is_bad_flat_slug( $bad, $slug ) {
		return $bad || self::is_language_prefix( $slug );
	}

	/**
	 * Same slug allowed across languages: only suffix when it collides inside the same language.
	 *
	 * @param string $slug          Slug computed by core.
	 * @param int    $post_id       Post id.
	 * @param string $post_status   Status.
	 * @param string $post_type     Post type.
	 * @param int    $post_parent   Parent.
	 * @param string $original_slug Desired slug.
	 * @return string
	 */
	public static function unique_post_slug( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
		if ( $slug === $original_slug || ! Translations::is_translatable_post_type( $post_type ) ) {
			return $slug;
		}
		$hierarchical = is_post_type_hierarchical( $post_type );
		$owners       = self::slug_owners( $original_slug, $post_id, $post_type, $post_parent, $hierarchical );
		if ( ! $owners || self::is_reserved_slug( $original_slug, $post_type, $post_parent, $hierarchical, $post_id ) ) {
			return $slug; // Suffixed for another reason (feeds, numbers, reserved words …).
		}
		$lang = Translations::language_for_slug( $post_id );
		if ( ! self::collides_in_language( $owners, $lang ) ) {
			return $original_slug;
		}
		$suffix = 2;
		do {
			$alt    = _truncate_post_slug( $original_slug, 200 - ( strlen( (string) $suffix ) + 1 ) ) . "-$suffix";
			$owners = self::slug_owners( $alt, $post_id, $post_type, $post_parent, $hierarchical );
			++$suffix;
		} while ( self::collides_in_language( $owners, $lang ) );
		return $alt;
	}

	/**
	 * Posts that already own a slug (mirrors core's uniqueness scope).
	 *
	 * @param string $slug         Slug.
	 * @param int    $post_id      Post id to exclude.
	 * @param string $post_type    Post type.
	 * @param int    $post_parent  Parent.
	 * @param bool   $hierarchical Whether the type is hierarchical.
	 * @return array Rows (ID, post_type).
	 */
	private static function slug_owners( $slug, $post_id, $post_type, $post_parent, $hierarchical ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- mirrors wp_unique_post_slug(); write path only.
		if ( $hierarchical ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ( %s, 'attachment' ) AND ID != %d AND post_parent = %d", $slug, $post_type, $post_id, $post_parent ) );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND ID != %d", $slug, $post_type, $post_id ) );
		}
		// phpcs:enable
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Whether any owner is in the given language (attachments are language-neutral and always collide).
	 *
	 * @param array  $owners Rows (ID, post_type).
	 * @param string $lang   Language.
	 * @return bool
	 */
	private static function collides_in_language( array $owners, $lang ) {
		if ( ! $owners ) {
			return false;
		}
		$ids = array();
		foreach ( $owners as $row ) {
			if ( 'attachment' === $row->post_type ) {
				return true;
			}
			$ids[] = (int) $row->ID;
		}
		update_object_term_cache( $ids, Translations::post_types() );
		foreach ( $ids as $id ) {
			if ( Translations::get_post_language( $id ) === $lang ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Slugs core always suffixes (feeds, embed, pagination-like numbers, date clashes, filtered bad slugs).
	 *
	 * @param string $slug         Slug.
	 * @param string $post_type    Post type.
	 * @param int    $post_parent  Parent.
	 * @param bool   $hierarchical Hierarchical type.
	 * @param int    $post_id      Post id.
	 * @return bool
	 */
	private static function is_reserved_slug( $slug, $post_type, $post_parent, $hierarchical, $post_id ) {
		global $wp_rewrite;
		$feeds = $wp_rewrite && is_array( $wp_rewrite->feeds ) ? $wp_rewrite->feeds : array();
		if ( in_array( $slug, $feeds, true ) || 'embed' === $slug ) {
			return true;
		}
		if ( $hierarchical ) {
			$base = $wp_rewrite ? $wp_rewrite->pagination_base : 'page';
			if ( preg_match( "@^($base)?\d+$@", $slug ) ) {
				return true;
			}
			/** This filter is documented in wp-includes/post.php */
			return (bool) apply_filters( 'wp_unique_post_slug_is_bad_hierarchical_slug', false, $slug, $post_type, $post_parent ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter re-applied on purpose (per-language slug uniqueness mirrors wp_unique_post_slug()).
		}
		if ( 'post' === $post_type && preg_match( '/^[0-9]+$/', $slug ) ) {
			$post = get_post( $post_id );
			if ( ! $post || $post->post_name !== $slug ) {
				return true; // Possible date-archive clash: keep core's decision.
			}
		}
		/** This filter is documented in wp-includes/post.php */
		return (bool) apply_filters( 'wp_unique_post_slug_is_bad_flat_slug', false, $slug, $post_type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter re-applied on purpose.
	}

	/*
	 * Adjacent posts, REST, query var
	 */

	/**
	 * Previous/next post links stay inside the language of the current post.
	 *
	 * @param string   $where          WHERE clause.
	 * @param bool     $in_same_term   Same term.
	 * @param mixed    $excluded_terms Excluded terms.
	 * @param string   $taxonomy       Taxonomy.
	 * @param \WP_Post $post           Current post.
	 * @return string
	 */
	public static function adjacent_post_where( $where, $in_same_term = false, $excluded_terms = '', $taxonomy = '', $post = null ) {
		global $wpdb;
		if ( ! $post instanceof \WP_Post || ! Translations::is_translatable_post_type( $post->post_type ) ) {
			return $where;
		}
		$lang = Translations::get_post_language( $post );
		if ( Languages::default_code() === $lang ) {
			$others = array();
			foreach ( Languages::codes( false ) as $code ) {
				$tt = Languages::default_code() === $code ? 0 : Translations::language_tt_id( $code );
				if ( $tt ) {
					$others[] = $tt;
				}
			}
			if ( ! $others ) {
				return $where;
			}
			return $where . " AND p.ID NOT IN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ( " . implode( ',', array_map( 'intval', $others ) ) . ' ) )';
		}
		return $where . $wpdb->prepare( " AND p.ID IN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d )", Translations::language_tt_id( $lang ) );
	}

	/**
	 * Registers the public `mrtu_lang` query var (admin list filter, WP_Query argument).
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	public static function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Adds the `lang` collection parameter to translatable post type REST endpoints.
	 */
	public static function register_rest_hooks() {
		foreach ( Translations::post_types() as $type ) {
			add_filter( "rest_{$type}_collection_params", array( __CLASS__, 'rest_collection_params' ) );
			add_filter( "rest_{$type}_query", array( __CLASS__, 'rest_query' ), 10, 2 );
		}
	}

	/**
	 * REST collection params.
	 *
	 * @param array $params Params.
	 * @return array
	 */
	public static function rest_collection_params( $params ) {
		$params['lang'] = array(
			'description' => __( 'Limit results to one language code, or "all".', 'mr-tu-multilang-seo' ),
			'type'        => 'string',
			'enum'        => array_merge( Languages::codes( false ), array( 'all' ) ),
		);
		return $params;
	}

	/**
	 * REST collection query: `lang` → `mrtu_lang`.
	 *
	 * @param array            $args    WP_Query args.
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	public static function rest_query( $args, $request ) {
		$lang = $request instanceof \WP_REST_Request ? $request->get_param( 'lang' ) : null;
		if ( is_string( $lang ) && Languages::exists( $lang ) ) {
			$args[ self::QUERY_VAR ] = $lang;
		}
		return $args;
	}

	/**
	 * REST requests with a valid `lang` parameter set the current language.
	 *
	 * @param mixed            $result  Pre-dispatch result.
	 * @param \WP_REST_Server  $server  Server.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function rest_language( $result, $server, $request ) {
		$lang = $request instanceof \WP_REST_Request ? $request->get_param( 'lang' ) : null;
		if ( is_string( $lang ) && Languages::exists( $lang ) ) {
			self::$current = $lang;
			self::announce();
		}
		return $result;
	}
}
