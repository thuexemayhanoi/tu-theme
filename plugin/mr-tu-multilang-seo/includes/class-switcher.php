<?php
/**
 * Language switcher data, hreflang alternates and the default accessible switcher markup.
 *
 * Both derive from one computation of the "equivalent page in every language" for the current request.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Switcher + alternates.
 */
final class Switcher {

	/**
	 * In-request cache of targets keyed by request signature.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_action( 'mrtu_language_detected', array( __CLASS__, 'flush' ) );
		add_shortcode( 'mrtu_language_switcher', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Clears the cache.
	 */
	public static function flush() {
		self::$cache = array();
	}

	/**
	 * Equivalent of the current request in every enabled language.
	 *
	 * @return array code => [ 'url' => string, 'indexable' => bool ] (only languages that have an equivalent)
	 */
	public static function targets() {
		global $wp_query;
		if ( ! $wp_query instanceof \WP_Query ) {
			return array();
		}
		$current = Router::effective_language();
		$key     = md5( $current . '|' . $wp_query->query_vars_hash . '|' . $wp_query->get_queried_object_id() . '|' . (int) $wp_query->is_404() );
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$languages = Languages::all( true );
		$targets   = array();

		if ( $wp_query->is_404() ) {
			$targets = array();
		} elseif ( $wp_query->is_search() ) {
			$search = get_search_query( false );
			foreach ( $languages as $code => $lang ) {
				$targets[ $code ] = array(
					'url'       => Links::to_language( add_query_arg( 's', rawurlencode( $search ), home_url( '/' ) ), $code ),
					'indexable' => false,
				);
			}
		} elseif ( $wp_query->is_front_page() ) {
			$targets = self::front_page_targets( $languages );
		} elseif ( $wp_query->is_home() ) {
			$targets = self::posts_page_targets( $languages );
		} elseif ( $wp_query->is_singular() ) {
			$targets = self::singular_targets( $wp_query->get_queried_object(), $languages, $current );
		} elseif ( $wp_query->is_category() || $wp_query->is_tag() || $wp_query->is_tax() ) {
			$targets = self::term_targets( $wp_query->get_queried_object(), $languages );
		} elseif ( $wp_query->is_post_type_archive() || $wp_query->is_date() || $wp_query->is_author() ) {
			$indexable = true;
			if ( $wp_query->is_date() ) {
				$indexable = ! Languages::setting( 'noindex_date', true );
			} elseif ( $wp_query->is_author() ) {
				$indexable = ! Languages::setting( 'noindex_author', true );
			}
			$page_one = get_pagenum_link( 1, false );
			foreach ( $languages as $code => $lang ) {
				$targets[ $code ] = array(
					'url'       => Links::to_language( $page_one, $code ),
					'indexable' => $indexable,
				);
			}
		}

		self::$cache[ $key ] = $targets;
		return $targets;
	}

	/**
	 * Front page in every language.
	 *
	 * @param array $languages Enabled languages.
	 * @return array
	 */
	private static function front_page_targets( array $languages ) {
		$targets = array();
		$front   = 'page' === get_option( 'show_on_front' ) ? Router::raw_option( 'page_on_front' ) : 0;
		if ( $front ) {
			$map = Translations::get_post_translations( $front );
			Translations::prime( array_values( $map ) );
		}
		foreach ( $languages as $code => $lang ) {
			if ( $front ) {
				$id = isset( $map[ $code ] ) ? (int) $map[ $code ] : 0;
				if ( ! $id || 'publish' !== get_post_status( $id ) ) {
					continue;
				}
				$targets[ $code ] = array(
					'url'       => Links::language_home( $code ),
					'indexable' => self::post_is_indexable( $id ),
				);
			} else {
				$targets[ $code ] = array(
					'url'       => Links::language_home( $code ),
					'indexable' => true,
				);
			}
		}
		return $targets;
	}

	/**
	 * Posts page (blog) in every language.
	 *
	 * @param array $languages Enabled languages.
	 * @return array
	 */
	private static function posts_page_targets( array $languages ) {
		$blog = Router::raw_option( 'page_for_posts' );
		if ( ! $blog ) {
			return array();
		}
		$map = Translations::get_post_translations( $blog );
		Translations::prime( array_values( $map ) );
		$targets = array();
		foreach ( $languages as $code => $lang ) {
			$id = isset( $map[ $code ] ) ? (int) $map[ $code ] : 0;
			if ( ! $id || 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$targets[ $code ] = array(
				'url'       => (string) get_permalink( $id ),
				'indexable' => self::post_is_indexable( $id ),
			);
		}
		return $targets;
	}

	/**
	 * A singular object in every language.
	 *
	 * @param mixed  $queried   Queried object.
	 * @param array  $languages Enabled languages.
	 * @param string $current   Current language.
	 * @return array
	 */
	private static function singular_targets( $queried, array $languages, $current ) {
		if ( ! $queried instanceof \WP_Post ) {
			return array();
		}
		if ( ! Translations::is_translatable_post_type( $queried->post_type ) ) {
			return array(
				$current => array(
					'url'       => (string) get_permalink( $queried ),
					'indexable' => false,
				),
			);
		}
		$map = Translations::get_post_translations( $queried );
		Translations::prime( array_values( $map ) );
		$targets = array();
		foreach ( $languages as $code => $lang ) {
			$id = isset( $map[ $code ] ) ? (int) $map[ $code ] : 0;
			if ( ! $id ) {
				continue;
			}
			$is_self = $id === (int) $queried->ID;
			if ( ! $is_self && 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$targets[ $code ] = array(
				'url'       => (string) get_permalink( $id ),
				'indexable' => self::post_is_indexable( $id ),
			);
		}
		return $targets;
	}

	/**
	 * A term archive in every language.
	 *
	 * @param mixed $term      Queried term.
	 * @param array $languages Enabled languages.
	 * @return array
	 */
	private static function term_targets( $term, array $languages ) {
		if ( ! $term instanceof \WP_Term || ! Translations::is_translatable_taxonomy( $term->taxonomy ) ) {
			return array();
		}
		$map     = Translations::get_term_translations( $term );
		$targets = array();
		if ( $map ) {
			_prime_term_caches( array_values( $map ) );
		}
		foreach ( $languages as $code => $lang ) {
			if ( empty( $map[ $code ] ) ) {
				continue;
			}
			$translation = get_term( (int) $map[ $code ], $term->taxonomy );
			if ( ! $translation instanceof \WP_Term ) {
				continue;
			}
			$url = get_term_link( $translation );
			if ( is_wp_error( $url ) ) {
				continue;
			}
			$targets[ $code ] = array(
				'url'       => $url,
				'indexable' => $translation->count > 0 && '1' !== (string) get_term_meta( $translation->term_id, '_mrtu_noindex', true ),
			);
		}
		return $targets;
	}

	/**
	 * Whether a post may appear in hreflang (published, not noindex, not password protected).
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	private static function post_is_indexable( $post_id ) {
		$post = get_post( $post_id );
		return $post
			&& 'publish' === $post->post_status
			&& '' === $post->post_password
			&& '1' !== (string) get_post_meta( $post->ID, '_mrtu_noindex', true );
	}

	/**
	 * Fallback destination when a language has no equivalent: its home page, if it exists.
	 *
	 * @param string $code Language code.
	 * @return string '' when the language has no reachable home.
	 */
	private static function fallback_url( $code ) {
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$front = Router::raw_option( 'page_on_front' );
			if ( $front && ! Translations::get_post_translation( $front, $code ) ) {
				return '';
			}
		}
		return Links::language_home( $code );
	}

	/**
	 * Switcher entries (contract `mrtu_switcher_links()`).
	 *
	 * @return array lang => [ code, name, hreflang, url, current, available, fallback ]
	 */
	public static function links() {
		$targets = self::targets();
		$current = Router::effective_language();
		$links   = array();
		foreach ( Languages::all( true ) as $code => $lang ) {
			$available = isset( $targets[ $code ] ) && '' !== $targets[ $code ]['url'];
			if ( $available ) {
				$url = $targets[ $code ]['url'];
			} else {
				// The current language without an equivalent (404) is not a destination.
				$url = $code === $current ? '' : self::fallback_url( $code );
			}
			$links[ $code ] = array(
				'code'      => $code,
				'name'      => $lang['name'],
				'hreflang'  => $lang['hreflang'],
				'url'       => $url,
				'current'   => $code === $current,
				'available' => $available,
				'fallback'  => ! $available && '' !== $url,
			);
		}
		return $links;
	}

	/**
	 * Indexable alternates of the current request (contract `mrtu_alternate_urls()`).
	 *
	 * Empty on search, 404, paginated archives and when the current page itself is not indexable.
	 *
	 * @return array hreflang => url, plus 'x-default'.
	 */
	public static function alternates() {
		global $wp_query;
		if ( ! $wp_query instanceof \WP_Query || $wp_query->is_search() || $wp_query->is_404() || $wp_query->is_paged() || $wp_query->is_attachment() ) {
			return array();
		}
		$targets = self::targets();
		$current = Router::effective_language();
		if ( empty( $targets[ $current ]['indexable'] ) ) {
			return array();
		}
		$out = array();
		foreach ( Languages::all( true ) as $code => $lang ) {
			if ( ! empty( $targets[ $code ]['indexable'] ) && '' !== $targets[ $code ]['url'] ) {
				$out[ $lang['hreflang'] ] = $targets[ $code ]['url'];
			}
		}
		$x_default = (string) Languages::setting( 'x_default', '' );
		if ( '' !== $x_default && Languages::is_enabled( $x_default ) && ! empty( $targets[ $x_default ]['indexable'] ) ) {
			$out['x-default'] = $targets[ $x_default ]['url'];
		}
		return $out;
	}

	/**
	 * Default accessible switcher markup (contract `mrtu_language_switcher()`).
	 *
	 * @param array $args {
	 *     Optional. Display options.
	 *
	 *     @type bool   $echo         Print the markup (default true).
	 *     @type string $display      'name' | 'code' | 'both' (default 'name').
	 *     @type bool   $hide_current Omit the current language (default false).
	 *     @type bool   $hide_missing Omit languages without an equivalent page (default false).
	 *     @type string $class        Extra class on the nav element.
	 * }
	 * @return string Escaped markup.
	 */
	public static function render( $args = array() ) {
		$args  = wp_parse_args(
			$args,
			array(
				'echo'         => true,
				'display'      => 'name',
				'hide_current' => false,
				'hide_missing' => false,
				'class'        => '',
			)
		);
		$items = '';
		foreach ( self::links() as $link ) {
			if ( ( $args['hide_current'] && $link['current'] ) || ( $args['hide_missing'] && ! $link['available'] && ! $link['current'] ) ) {
				continue;
			}
			if ( '' === $link['url'] && ! $link['current'] ) {
				continue;
			}
			$classes = array( 'mrtu-switcher__item' );
			if ( $link['current'] ) {
				$classes[] = 'mrtu-switcher__item--current';
			}
			if ( $link['fallback'] ) {
				$classes[] = 'mrtu-switcher__item--fallback';
			}

			$label = $link['name'];
			if ( 'code' === $args['display'] ) {
				$text = '<span aria-hidden="true">' . esc_html( strtoupper( $link['code'] ) ) . '</span><span class="screen-reader-text">' . esc_html( $label ) . '</span>';
			} elseif ( 'both' === $args['display'] ) {
				$text = '<span class="mrtu-switcher__code" aria-hidden="true">' . esc_html( strtoupper( $link['code'] ) ) . '</span> <span class="mrtu-switcher__name">' . esc_html( $label ) . '</span>';
			} else {
				$text = '<span class="mrtu-switcher__name">' . esc_html( $label ) . '</span>';
			}
			if ( $link['fallback'] ) {
				$text .= '<span class="screen-reader-text"> (' . esc_html( Languages::ui_string( 'translation_missing', $link['code'] ) ) . ')</span>';
			}

			$attrs = ' lang="' . esc_attr( $link['hreflang'] ) . '"';
			if ( $link['current'] ) {
				$attrs .= ' aria-current="page"';
			}
			if ( '' !== $link['url'] ) {
				$title = $link['fallback'] ? ' title="' . esc_attr( Languages::ui_string( 'translation_missing', $link['code'] ) ) . '"' : '';
				$item  = '<a class="mrtu-switcher__link" href="' . esc_url( $link['url'] ) . '" hreflang="' . esc_attr( $link['hreflang'] ) . '"' . $attrs . $title . '>' . $text . '</a>';
			} else {
				$item = '<span class="mrtu-switcher__link"' . $attrs . '>' . $text . '</span>';
			}
			$items .= '<li class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $item . '</li>';
		}

		$class = trim( 'mrtu-switcher ' . sanitize_html_class( (string) $args['class'] ) );
		$html  = '<nav class="' . esc_attr( $class ) . '" aria-label="' . esc_attr( Languages::ui_string( 'language' ) ) . '"><ul class="mrtu-switcher__list">' . $items . '</ul></nav>';
		if ( $args['echo'] ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part escaped above.
		}
		return $html;
	}

	/**
	 * `[mrtu_language_switcher display="code"]`.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts         = shortcode_atts(
			array(
				'display'      => 'name',
				'hide_current' => '',
				'hide_missing' => '',
				'class'        => '',
			),
			$atts,
			'mrtu_language_switcher'
		);
		$atts['echo'] = false;
		foreach ( array( 'hide_current', 'hide_missing' ) as $flag ) {
			$atts[ $flag ] = in_array( strtolower( (string) $atts[ $flag ] ), array( '1', 'true', 'yes' ), true );
		}
		$atts['display'] = in_array( $atts['display'], array( 'name', 'code', 'both' ), true ) ? $atts['display'] : 'name';
		return self::render( $atts );
	}
}
