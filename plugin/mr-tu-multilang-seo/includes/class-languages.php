<?php
/**
 * Language registry + plugin settings (option `mrtu_ml_settings`).
 *
 * The five languages are fixed in code. Settings may toggle `enabled`, change the URL `prefix`
 * (non-default languages only), the `hreflang` code and the per-language tagline.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Languages registry and settings accessor.
 */
final class Languages {

	const OPTION = 'mrtu_ml_settings';

	const DEFAULT_LANGUAGE = 'vi';

	/**
	 * Merged settings cache (null = not loaded).
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Built language lists cache, keyed by 'all' / 'enabled'.
	 *
	 * @var array
	 */
	private static $lists = array();

	/**
	 * Wires hooks.
	 */
	public static function init() {
		foreach ( array( 'add_option_', 'update_option_', 'delete_option_' ) as $prefix ) {
			add_action( $prefix . self::OPTION, array( __CLASS__, 'flush' ) );
		}
	}

	/**
	 * Activation: stores the default settings when the option does not exist yet.
	 */
	public static function activate() {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', true );
		}
		self::flush();
	}

	/**
	 * Clears in-request caches (called when the option changes).
	 */
	public static function flush() {
		self::$settings = null;
		self::$lists    = array();
	}

	/**
	 * Fixed language registry (contract 2.1). Order is the display order everywhere.
	 *
	 * @return array code => [ locale, hreflang, og_locale, prefix, name, name_vi ]
	 */
	public static function registry() {
		return array(
			'vi' => array(
				'locale'    => 'vi',
				'hreflang'  => 'vi-VN',
				'og_locale' => 'vi_VN',
				'prefix'    => '',
				'name'      => 'Tiếng Việt',
				'name_vi'   => 'Tiếng Việt',
			),
			'en' => array(
				'locale'    => 'en_US',
				'hreflang'  => 'en',
				'og_locale' => 'en_US',
				'prefix'    => 'en',
				'name'      => 'English',
				'name_vi'   => 'Tiếng Anh',
			),
			'de' => array(
				'locale'    => 'de_DE',
				'hreflang'  => 'de',
				'og_locale' => 'de_DE',
				'prefix'    => 'de',
				'name'      => 'Deutsch',
				'name_vi'   => 'Tiếng Đức',
			),
			'fr' => array(
				'locale'    => 'fr_FR',
				'hreflang'  => 'fr',
				'og_locale' => 'fr_FR',
				'prefix'    => 'fr',
				'name'      => 'Français',
				'name_vi'   => 'Tiếng Pháp',
			),
			'es' => array(
				'locale'    => 'es_ES',
				'hreflang'  => 'es',
				'og_locale' => 'es_ES',
				'prefix'    => 'es',
				'name'      => 'Español',
				'name_vi'   => 'Tiếng Tây Ban Nha',
			),
		);
	}

	/**
	 * Default value of the whole `mrtu_ml_settings` option (contract 2.1).
	 *
	 * @return array
	 */
	public static function defaults() {
		$languages = array();
		foreach ( self::registry() as $code => $lang ) {
			$languages[ $code ] = array(
				'enabled'  => true,
				'prefix'   => $lang['prefix'],
				'hreflang' => $lang['hreflang'],
				'tagline'  => '',
			);
		}

		return array(
			'languages'                => $languages,
			'default'                  => self::DEFAULT_LANGUAGE,
			'x_default'                => 'en',
			'seo_mode'                 => 'auto',
			'sitemap'                  => 'auto',
			'local_business_schema'    => false,
			'faq_schema'               => true,
			'noindex_author'           => true,
			'noindex_date'             => true,
			'show_reference_prices'    => false,
			'toc'                      => array(
				'enabled'      => true,
				'min_headings' => 3,
				'min_words'    => 350,
				'position'     => 'after_first_h2',
				'post_types'   => array( 'post', 'page' ),
			),
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * Saved settings deep-merged over the defaults. Invariants of v1 are enforced here.
	 *
	 * @return array
	 */
	public static function settings() {
		if ( null !== self::$settings ) {
			return self::$settings;
		}

		$saved    = get_option( self::OPTION, array() );
		$settings = self::merge_deep( self::defaults(), is_array( $saved ) ? $saved : array() );

		// v1 invariants: the default language is fixed, always enabled and never prefixed.
		$settings['default']                                        = self::DEFAULT_LANGUAGE;
		$settings['languages'][ self::DEFAULT_LANGUAGE ]['enabled'] = true;
		$settings['languages'][ self::DEFAULT_LANGUAGE ]['prefix']  = '';

		self::$settings = $settings;
		return $settings;
	}

	/**
	 * One top-level setting.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Returned when the key is unknown.
	 * @return mixed
	 */
	public static function setting( $key, $fallback = null ) {
		$settings = self::settings();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	/**
	 * Replaces the whole option (other modules' keys are kept by merging first).
	 *
	 * @param array $settings Complete or partial settings array.
	 * @return bool Whether the option changed.
	 */
	public static function update_settings( array $settings ) {
		$merged = self::merge_deep( self::settings(), $settings );
		$result = update_option( self::OPTION, $merged, true );
		self::flush();
		return $result;
	}

	/**
	 * Recursively merges $values over $defaults. Associative arrays merge; lists and scalars replace.
	 * A scalar never replaces an associative default array (keeps the structure intact).
	 *
	 * @param array $defaults Defaults.
	 * @param array $values   Values.
	 * @return array
	 */
	public static function merge_deep( array $defaults, array $values ) {
		foreach ( $values as $key => $value ) {
			if ( isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) && self::is_assoc( $defaults[ $key ] ) ) {
				if ( is_array( $value ) ) {
					$defaults[ $key ] = self::merge_deep( $defaults[ $key ], $value );
				}
				continue;
			}
			$defaults[ $key ] = $value;
		}
		return $defaults;
	}

	/**
	 * Whether an array has string keys.
	 *
	 * @param array $values Array.
	 * @return bool
	 */
	private static function is_assoc( array $values ) {
		if ( array() === $values ) {
			return false;
		}
		return array_keys( $values ) !== range( 0, count( $values ) - 1 );
	}

	/**
	 * Language list (contract 2.4 `mrtu_languages()`).
	 *
	 * @param bool $enabled_only Only enabled languages.
	 * @return array code => [ code, locale, hreflang, og_locale, prefix, name, name_vi, enabled, is_default, tagline ]
	 */
	public static function all( $enabled_only = true ) {
		$key = $enabled_only ? 'enabled' : 'all';
		if ( isset( self::$lists[ $key ] ) ) {
			return self::$lists[ $key ];
		}

		$settings = self::settings();
		$list     = array();
		foreach ( self::registry() as $code => $lang ) {
			$saved   = isset( $settings['languages'][ $code ] ) && is_array( $settings['languages'][ $code ] ) ? $settings['languages'][ $code ] : array();
			$enabled = self::DEFAULT_LANGUAGE === $code ? true : ! empty( $saved['enabled'] );
			if ( $enabled_only && ! $enabled ) {
				continue;
			}
			$prefix   = self::DEFAULT_LANGUAGE === $code ? '' : ( isset( $saved['prefix'] ) && self::is_valid_prefix_format( $saved['prefix'] ) ? $saved['prefix'] : $lang['prefix'] );
			$hreflang = isset( $saved['hreflang'] ) && is_string( $saved['hreflang'] ) && '' !== $saved['hreflang'] ? $saved['hreflang'] : $lang['hreflang'];

			$list[ $code ] = array(
				'code'       => $code,
				'locale'     => $lang['locale'],
				'hreflang'   => $hreflang,
				'og_locale'  => $lang['og_locale'],
				'prefix'     => $prefix,
				'name'       => $lang['name'],
				'name_vi'    => $lang['name_vi'],
				'enabled'    => $enabled,
				'is_default' => self::DEFAULT_LANGUAGE === $code,
				'tagline'    => isset( $saved['tagline'] ) && is_string( $saved['tagline'] ) ? $saved['tagline'] : '',
			);
		}

		self::$lists[ $key ] = $list;
		return $list;
	}

	/**
	 * Codes of the (enabled) languages.
	 *
	 * @param bool $enabled_only Only enabled languages.
	 * @return string[]
	 */
	public static function codes( $enabled_only = true ) {
		return array_keys( self::all( $enabled_only ) );
	}

	/**
	 * One language (enabled or not).
	 *
	 * @param string $code Language code.
	 * @return array|null
	 */
	public static function get( $code ) {
		$all = self::all( false );
		return is_string( $code ) && isset( $all[ $code ] ) ? $all[ $code ] : null;
	}

	/**
	 * Whether a code is a registered language.
	 *
	 * @param string $code Language code.
	 * @return bool
	 */
	public static function exists( $code ) {
		return null !== self::get( $code );
	}

	/**
	 * Whether a language is enabled.
	 *
	 * @param string $code Language code.
	 * @return bool
	 */
	public static function is_enabled( $code ) {
		$lang = self::get( $code );
		return $lang ? $lang['enabled'] : false;
	}

	/**
	 * Default language code.
	 *
	 * @return string
	 */
	public static function default_code() {
		return self::DEFAULT_LANGUAGE;
	}

	/**
	 * Language name for admin screens: Vietnamese names for a Vietnamese admin, native names otherwise.
	 *
	 * @param string $code Language code.
	 * @return string
	 */
	public static function admin_name( $code ) {
		$lang = self::get( $code );
		if ( ! $lang ) {
			return strtoupper( (string) $code );
		}
		$user_locale = function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
		return 0 === strpos( $user_locale, 'vi' ) ? $lang['name_vi'] : $lang['name'];
	}

	/*
	 * Validation
	 */

	/**
	 * Whether a prefix has the allowed shape (2–5 lowercase ASCII letters).
	 *
	 * @param mixed $prefix Prefix.
	 * @return bool
	 */
	public static function is_valid_prefix_format( $prefix ) {
		return is_string( $prefix ) && 1 === preg_match( '/^[a-z]{2,5}$/', $prefix );
	}

	/**
	 * URL segments a language prefix may never take (WordPress routes and our own endpoints).
	 *
	 * @return string[]
	 */
	public static function reserved_prefixes() {
		$reserved = array( 'wp', 'feed', 'rss', 'rss2', 'rdf', 'atom', 'page', 'pages', 'category', 'tag', 'search', 'author', 'embed', 'trackback', 'comments', 'attachment', 'type', 'date', 'admin', 'login', 'files', 'blog' );
		/**
		 * Filters the list of URL segments a language prefix may not use.
		 *
		 * @param string[] $reserved Reserved segments.
		 */
		return (array) apply_filters( 'mrtu_reserved_prefixes', $reserved );
	}

	/**
	 * Normalizes an hreflang value (language lower case, script title case, region upper case).
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function normalize_hreflang( $value ) {
		$parts = explode( '-', str_replace( '_', '-', trim( (string) $value ) ) );
		foreach ( $parts as $i => $part ) {
			if ( 0 === $i ) {
				$parts[ $i ] = strtolower( $part );
			} elseif ( 4 === strlen( $part ) && ctype_alpha( $part ) ) {
				$parts[ $i ] = ucfirst( strtolower( $part ) );
			} else {
				$parts[ $i ] = strtoupper( $part );
			}
		}
		return implode( '-', $parts );
	}

	/**
	 * Whether an hreflang value is a valid BCP 47 language[-Script][-REGION] tag for the given language.
	 *
	 * @param string $hreflang Normalized hreflang.
	 * @param string $code     Language code the tag must start with.
	 * @return bool
	 */
	public static function is_valid_hreflang( $hreflang, $code ) {
		if ( 1 !== preg_match( '/^([a-z]{2,3})(-[A-Z][a-z]{3})?(-(?:[A-Z]{2}|[0-9]{3}))?$/', (string) $hreflang, $m ) ) {
			return false;
		}
		return $m[1] === $code;
	}

	/**
	 * Top-level content slugs that would collide with a prefix (`/%postname%/` puts posts at the root too).
	 *
	 * @param string $slug Candidate prefix.
	 * @return bool
	 */
	public static function slug_in_use( $slug ) {
		global $wpdb;
		$types = array_unique( array_merge( array( 'page', 'post' ), Translations::post_types() ) );
		$in    = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- settings validation only (write path); $in holds placeholders only.
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_parent = 0 AND post_type IN ( {$in} ) AND post_status NOT IN ( 'trash', 'auto-draft' ) LIMIT 1", array_merge( array( $slug ), $types ) ) );
		// phpcs:enable
		return ! empty( $found );
	}

	/**
	 * Validates submitted language settings.
	 *
	 * @param array $input [ 'languages' => [ code => [ enabled, prefix, hreflang, tagline ] ], 'x_default' => code|'' ].
	 * @return array [ 'settings' => array (partial settings to merge), 'errors' => string[] ]
	 */
	public static function validate( array $input ) {
		$current   = self::settings();
		$errors    = array();
		$languages = array();
		$prefixes  = array();
		$hreflangs = array();
		$submitted = isset( $input['languages'] ) && is_array( $input['languages'] ) ? $input['languages'] : array();

		foreach ( self::registry() as $code => $lang ) {
			$row     = isset( $submitted[ $code ] ) && is_array( $submitted[ $code ] ) ? $submitted[ $code ] : array();
			$saved   = $current['languages'][ $code ];
			$name    = $lang['name'];
			$enabled = self::DEFAULT_LANGUAGE === $code ? true : ( isset( $row['enabled'] ) ? (bool) $row['enabled'] : (bool) $saved['enabled'] );

			// Prefix.
			if ( self::DEFAULT_LANGUAGE === $code ) {
				$prefix = '';
			} else {
				$prefix = isset( $row['prefix'] ) ? strtolower( trim( sanitize_text_field( (string) $row['prefix'] ) ) ) : $saved['prefix'];
				$prefix = trim( $prefix, '/' );
				if ( ! self::is_valid_prefix_format( $prefix ) ) {
					/* translators: %s: language name. */
					$errors[] = sprintf( __( '%s: the URL prefix must be 2 to 5 lowercase letters (a–z).', 'mr-tu-multilang-seo' ), $name );
					$prefix   = $saved['prefix'];
				} elseif ( in_array( $prefix, self::reserved_prefixes(), true ) || 0 === strpos( $prefix, 'wp' ) ) {
					/* translators: 1: language name, 2: URL prefix. */
					$errors[] = sprintf( __( '%1$s: “%2$s” is reserved by WordPress and cannot be used as a URL prefix.', 'mr-tu-multilang-seo' ), $name, $prefix );
					$prefix   = $saved['prefix'];
				} elseif ( isset( $prefixes[ $prefix ] ) ) {
					/* translators: 1: language name, 2: URL prefix. */
					$errors[] = sprintf( __( '%1$s: the URL prefix “%2$s” is already used by another language.', 'mr-tu-multilang-seo' ), $name, $prefix );
					$prefix   = $saved['prefix'];
				} elseif ( $prefix !== $saved['prefix'] && self::slug_in_use( $prefix ) ) {
					/* translators: 1: language name, 2: URL prefix. */
					$errors[] = sprintf( __( '%1$s: a page or post with the address “/%2$s/” already exists, so it cannot become a language prefix.', 'mr-tu-multilang-seo' ), $name, $prefix );
					$prefix   = $saved['prefix'];
				}
				if ( isset( $prefixes[ $prefix ] ) ) {
					// The fallback collides too: keep the registry default (always unique).
					$prefix = $lang['prefix'];
				}
				$prefixes[ $prefix ] = $code;
			}

			// Hreflang.
			$hreflang = isset( $row['hreflang'] ) ? self::normalize_hreflang( sanitize_text_field( (string) $row['hreflang'] ) ) : $saved['hreflang'];
			if ( '' === $hreflang ) {
				$hreflang = $lang['hreflang'];
			}
			if ( ! self::is_valid_hreflang( $hreflang, $code ) ) {
				/* translators: 1: language name, 2: language code. */
				$errors[] = sprintf( __( '%1$s: the hreflang code must look like “%2$s” or “%2$s-XX” (language, optional region).', 'mr-tu-multilang-seo' ), $name, $code );
				$hreflang = $saved['hreflang'];
			} elseif ( isset( $hreflangs[ $hreflang ] ) ) {
				/* translators: 1: language name, 2: hreflang code. */
				$errors[] = sprintf( __( '%1$s: the hreflang code “%2$s” is already used.', 'mr-tu-multilang-seo' ), $name, $hreflang );
				$hreflang = $lang['hreflang'];
			}
			$hreflangs[ $hreflang ] = $code;

			// Tagline.
			$tagline = isset( $row['tagline'] ) ? sanitize_text_field( (string) $row['tagline'] ) : $saved['tagline'];
			if ( function_exists( 'mb_substr' ) ) {
				$tagline = mb_substr( $tagline, 0, 200 );
			}

			$languages[ $code ] = array(
				'enabled'  => $enabled,
				'prefix'   => $prefix,
				'hreflang' => $hreflang,
				'tagline'  => $tagline,
			);
		}

		$x_default = isset( $input['x_default'] ) ? sanitize_key( (string) $input['x_default'] ) : $current['x_default'];
		if ( '' !== $x_default && ( ! isset( $languages[ $x_default ] ) || ! $languages[ $x_default ]['enabled'] ) ) {
			$errors[]  = __( 'The x-default language must be one of the enabled languages.', 'mr-tu-multilang-seo' );
			$x_default = isset( $languages[ $current['x_default'] ] ) && $languages[ $current['x_default'] ]['enabled'] ? $current['x_default'] : '';
		}

		return array(
			'settings' => array(
				'languages' => $languages,
				'x_default' => $x_default,
			),
			'errors'   => $errors,
		);
	}

	/*
	 * Frontend UI strings with built-in fallbacks
	 */

	/**
	 * A short frontend UI string in the current (or given) language.
	 *
	 * Gettext wins when a translation is installed; otherwise a small built-in dictionary keeps
	 * breadcrumbs and the switcher from mixing English into Vietnamese/German/French/Spanish pages.
	 *
	 * @param string      $key  home|blog|search_results|not_found|language|translation_missing|page_n.
	 * @param string|null $lang Target language (null = current language).
	 * @return string Raw (unescaped) text; may contain a %s / %d placeholder.
	 */
	public static function ui_string( $key, $lang = null ) {
		$current = Router::effective_language();
		$lang    = null === $lang ? $current : $lang;

		switch ( $key ) {
			case 'home':
				$source     = 'Home';
				$translated = __( 'Home', 'mr-tu-multilang-seo' );
				break;
			case 'blog':
				$source     = 'Blog';
				$translated = __( 'Blog', 'mr-tu-multilang-seo' );
				break;
			case 'search_results':
				$source = 'Search results for “%s”';
				/* translators: %s: search query. */
				$translated = __( 'Search results for “%s”', 'mr-tu-multilang-seo' );
				break;
			case 'not_found':
				$source     = 'Page not found';
				$translated = __( 'Page not found', 'mr-tu-multilang-seo' );
				break;
			case 'language':
				$source     = 'Language';
				$translated = __( 'Language', 'mr-tu-multilang-seo' );
				break;
			case 'translation_missing':
				$source     = 'Not translated yet – opens the home page';
				$translated = __( 'Not translated yet – opens the home page', 'mr-tu-multilang-seo' );
				break;
			case 'page_n':
				$source = 'Page %d';
				/* translators: %d: page number. */
				$translated = __( 'Page %d', 'mr-tu-multilang-seo' );
				break;
			default:
				return '';
		}

		// Gettext only knows the current locale; use it when it actually translated the string.
		if ( $lang === $current && $translated !== $source ) {
			return $translated;
		}

		$builtin = self::builtin_strings();
		if ( isset( $builtin[ $key ][ $lang ] ) ) {
			return $builtin[ $key ][ $lang ];
		}
		return $lang === $current ? $translated : $source;
	}

	/**
	 * Built-in translations of the few strings rendered by the core frontend helpers.
	 *
	 * @return array key => [ lang => text ]
	 */
	private static function builtin_strings() {
		return array(
			'home'                => array(
				'vi' => 'Trang chủ',
				'de' => 'Startseite',
				'fr' => 'Accueil',
				'es' => 'Inicio',
			),
			'blog'                => array(
				'vi' => 'Cẩm nang',
				'de' => 'Blog',
				'fr' => 'Blog',
				'es' => 'Blog',
			),
			'search_results'      => array(
				'vi' => 'Kết quả tìm kiếm cho “%s”',
				'de' => 'Suchergebnisse für „%s“',
				'fr' => 'Résultats de recherche pour « %s »',
				'es' => 'Resultados de búsqueda de «%s»',
			),
			'not_found'           => array(
				'vi' => 'Không tìm thấy trang',
				'de' => 'Seite nicht gefunden',
				'fr' => 'Page introuvable',
				'es' => 'Página no encontrada',
			),
			'language'            => array(
				'vi' => 'Ngôn ngữ',
				'de' => 'Sprache',
				'fr' => 'Langue',
				'es' => 'Idioma',
			),
			'translation_missing' => array(
				'vi' => 'Chưa có bản dịch – mở trang chủ',
				'de' => 'Noch nicht übersetzt – öffnet die Startseite',
				'fr' => 'Pas encore traduit – ouvre la page d’accueil',
				'es' => 'Aún sin traducir – abre la página de inicio',
			),
			'page_n'              => array(
				'vi' => 'Trang %d',
				'de' => 'Seite %d',
				'fr' => 'Page %d',
				'es' => 'Página %d',
			),
		);
	}
}
