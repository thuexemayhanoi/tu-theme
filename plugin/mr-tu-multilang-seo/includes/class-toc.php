<?php
/**
 * Table of contents (engineering contract §2.9).
 *
 * Collects the H2/H3 headings of the rendered post content, gives every heading a stable id and
 * inserts an accessible, collapsible TOC right after the first H2. The same items are exposed to
 * themes through mrtu_toc_items() (e.g. a sticky desktop aside marked `data-mrtu-toc-aside`), and a
 * tiny deferred script adds scroll-spy (`aria-current="true"`) and the `--mrtu-read-progress`
 * custom property on <html>.
 *
 * Parsing deliberately uses a small regex tokenizer instead of DOMDocument: DOMDocument re-encodes
 * UTF-8, entities and block markup, while this approach only touches the heading start tags it
 * needs to change and leaves every other byte of the content untouched.
 *
 * Public hooks:
 * - filter `mrtu_toc_enabled( bool $enabled, WP_Post $post )` final say for a post (settings, post type, `_mrtu_toc`).
 * - filter `mrtu_toc_skip_classes( string[] $classes )` headings inside (or carrying) these classes are ignored.
 * - filter `mrtu_toc_reserved_ids( string[] $ids )`   ids generated headings must never take (theme landmarks).
 * - filter `mrtu_toc_html( string $html, array $items, WP_Post $post )` final TOC markup.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Table of contents module.
 */
final class Toc {

	/**
	 * Script/style handle.
	 */
	const HANDLE = 'mrtu-toc';

	/**
	 * Per-post switch (`''` = follow settings, `'off'` = never show).
	 */
	const META_KEY = '_mrtu_toc';

	/**
	 * Maximum length of a generated id (cut at a word boundary).
	 */
	const SLUG_MAX = 80;

	/**
	 * Allowed insert positions.
	 */
	const POSITIONS = array( 'after_first_h2', 'before_first_h2' );

	/**
	 * Elements without an end tag.
	 */
	const VOID_ELEMENTS = array(
		'area'   => true,
		'base'   => true,
		'br'     => true,
		'col'    => true,
		'embed'  => true,
		'hr'     => true,
		'img'    => true,
		'input'  => true,
		'link'   => true,
		'meta'   => true,
		'param'  => true,
		'source' => true,
		'track'  => true,
		'wbr'    => true,
	);

	/**
	 * HTML tokenizer: comments · CDATA · doctype/PI · raw-text elements (content skipped) · end tags · start tags.
	 * Byte based on purpose (no `u` flag): offsets stay valid for substr() and invalid UTF-8 never breaks parsing.
	 * Groups: 1 raw-text element name, 2 end tag name, 3 start tag name, 4 start tag attributes.
	 */
	const TOKEN_PATTERN = '~<!--.*?-->|<!\[CDATA\[.*?\]\]>|<[!?][^>]*>|<(script|style|textarea|template|noscript|title|xmp|iframe)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>.*?</\1\s*>|</([a-z][a-z0-9:-]*)[^>]*>|<([a-z][a-z0-9:-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>~is';

	/**
	 * Attribute tokenizer (name, then double-quoted / single-quoted / unquoted value).
	 */
	const ATTR_PATTERN = '~([^\s"\'>/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?~';

	/**
	 * Per-request cache: "post_id:content_hash:page" => [ 'items' => array, 'words' => int ].
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Re-entrancy guard while mrtu_toc_items() renders content outside the_content.
	 *
	 * @var bool
	 */
	private static $computing = false;

	/**
	 * Wires hooks.
	 */
	public static function init() {
		// Priority 12: after do_blocks (9), wptexturize/wpautop (10), do_shortcode (11) and wp_filter_content_tags (12, added first).
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 12 );
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_style_for_queried_post' ), 20 );
	}

	/*
	 * Settings.
	 */

	/**
	 * Default TOC settings (contract §2.1 → mrtu_ml_settings['toc']).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'      => true,
			'min_headings' => 3,
			'min_words'    => 350,
			'position'     => 'after_first_h2',
			'post_types'   => array( 'post', 'page' ),
		);
	}

	/**
	 * Current settings: mrtu_ml_settings['toc'] sanitized and merged over the defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$all = get_option( 'mrtu_ml_settings', array() );
		$toc = ( is_array( $all ) && isset( $all['toc'] ) && is_array( $all['toc'] ) ) ? $all['toc'] : array();
		return self::sanitize_settings( $toc );
	}

	/**
	 * Sanitizes a TOC settings array (for the SEO screen and for reading the option).
	 * Missing keys fall back to the defaults; pass an empty array/string as `post_types` to disable every type.
	 *
	 * @param mixed $input Raw settings (e.g. $_POST['toc'] after wp_unslash()).
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$out      = $defaults;

		if ( array_key_exists( 'enabled', $input ) ) {
			$out['enabled'] = is_scalar( $input['enabled'] ) && filter_var( $input['enabled'], FILTER_VALIDATE_BOOLEAN );
		}
		if ( array_key_exists( 'min_headings', $input ) && is_scalar( $input['min_headings'] ) ) {
			$out['min_headings'] = max( 1, min( 50, (int) $input['min_headings'] ) );
		}
		if ( array_key_exists( 'min_words', $input ) && is_scalar( $input['min_words'] ) ) {
			$out['min_words'] = max( 0, min( 100000, (int) $input['min_words'] ) );
		}
		if ( isset( $input['position'] ) && is_string( $input['position'] ) && in_array( $input['position'], self::POSITIONS, true ) ) {
			$out['position'] = $input['position'];
		}
		if ( array_key_exists( 'post_types', $input ) ) {
			$types = $input['post_types'];
			if ( is_string( $types ) ) {
				$types = explode( ',', $types );
			}
			$clean = array();
			foreach ( (array) $types as $type ) {
				$type = is_scalar( $type ) ? sanitize_key( (string) $type ) : '';
				if ( '' !== $type && 'attachment' !== $type && ! in_array( $type, $clean, true ) ) {
					$clean[] = $type;
				}
			}
			$out['post_types'] = $clean;
		}
		return $out;
	}

	/*
	 * Eligibility.
	 */

	/**
	 * Whether a post may show a TOC (before the heading/word thresholds are applied).
	 *
	 * Hard rules (not filterable): the post exists, no password is required, it is not a front page.
	 * Soft rules (filterable through `mrtu_toc_enabled`): TOC enabled, post type allowed, `_mrtu_toc` !== 'off'.
	 *
	 * @param int|\WP_Post|null $post Post (default: current post).
	 * @return bool
	 */
	public static function enabled_for( $post = null ) {
		$post = get_post( $post );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		if ( post_password_required( $post ) || self::is_front_page_post( $post ) ) {
			return false;
		}
		$settings = self::settings();
		$enabled  = $settings['enabled']
			&& in_array( $post->post_type, $settings['post_types'], true )
			&& 'off' !== get_post_meta( $post->ID, self::META_KEY, true );

		/**
		 * Filters whether the TOC is enabled for a post.
		 *
		 * @param bool     $enabled Decision from settings, post type and the `_mrtu_toc` meta.
		 * @param \WP_Post $post    Post.
		 */
		return (bool) apply_filters( 'mrtu_toc_enabled', $enabled, $post );
	}

	/**
	 * Whether the post is a front page (static front page or any page with the `home` role).
	 *
	 * @param \WP_Post $post Post.
	 * @return bool
	 */
	private static function is_front_page_post( $post ) {
		if ( 'page' === $post->post_type && 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
			return true;
		}
		return function_exists( 'mrtu_get_post_role' ) && 'home' === mrtu_get_post_role( $post->ID );
	}

	/**
	 * Whether heading/word thresholds are met.
	 *
	 * @param array $data Analysis result with `items` and `words`.
	 * @return bool
	 */
	private static function meets_thresholds( $data ) {
		$settings = self::settings();
		$count    = count( $data['items'] );
		return $count > 0 && $count >= $settings['min_headings'] && $data['words'] >= $settings['min_words'];
	}

	/**
	 * Whether the current request renders a public HTML page (not admin, AJAX, cron, REST, XML-RPC, feeds, embeds).
	 *
	 * @return bool
	 */
	private static function is_frontend_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( function_exists( 'wp_is_serving_rest_request' ) ? wp_is_serving_rest_request() : ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}
		if ( isset( $GLOBALS['wp_query'] ) && ( is_feed() || is_embed() ) ) {
			return false;
		}
		return true;
	}

	/*
	 * the_content.
	 */

	/**
	 * `the_content` filter: inserts the TOC into the main post of a singular request.
	 *
	 * @param string $content Filtered post content.
	 * @return string
	 */
	public static function filter_content( $content ) {
		if ( self::$computing || ! is_string( $content ) || '' === $content ) {
			return $content;
		}
		if ( ! self::is_frontend_request() || doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() || is_front_page() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post || (int) get_queried_object_id() !== (int) $post->ID ) {
			return $content; // A secondary loop rendering full content inside the main loop.
		}
		if ( self::has_toc( $content ) ) {
			return $content; // the_content ran twice over the same output.
		}
		if ( ! self::enabled_for( $post ) ) {
			return $content;
		}

		$data = self::analyze( $content, self::post_language( $post ) );
		$key  = self::cache_key( $post );

		self::$cache[ $key ] = array(
			'items' => $data['items'],
			'words' => $data['words'],
		);
		if ( ! self::meets_thresholds( $data ) ) {
			return $content;
		}

		$settings = self::settings();
		$toc      = self::markup( $data['items'] );

		/**
		 * Filters the TOC markup inserted into the content.
		 *
		 * @param string   $toc   Markup (contract §2.9).
		 * @param array    $items Items: [ 'id' => string, 'text' => string (plain), 'level' => 2|3 ].
		 * @param \WP_Post $post  Post.
		 */
		$toc = (string) apply_filters( 'mrtu_toc_html', $toc, $data['items'], $post );

		self::enqueue_assets();
		return self::inject( $data, $toc, $settings['position'] );
	}

	/**
	 * Whether the HTML already contains a TOC root (`data-mrtu-toc`, not `data-mrtu-toc-aside`).
	 *
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function has_toc( $html ) {
		return (bool) preg_match( '~<[a-z][^>]*\sdata-mrtu-toc(?=[\s=/>])~i', $html );
	}

	/*
	 * Items API (mrtu_toc_items).
	 */

	/**
	 * TOC items of a post — identical to the inline TOC, or an empty array when no TOC is shown.
	 *
	 * Uses the result cached by the first the_content render. When asked before that render (an aside printed
	 * before the content), the content is rendered once through a side-effect-light equivalent of the_content
	 * up to priority 12 (do_blocks, wptexturize, wpautop for classic content, shortcode_unautop, do_shortcode)
	 * and cached for the request.
	 *
	 * @param int|\WP_Post|null $post Post (default: current post).
	 * @return array[] Items: [ 'id' => string, 'text' => string (plain, unescaped), 'level' => 2|3 ].
	 */
	public static function items( $post = null ) {
		$post = get_post( $post );
		if ( self::$computing || ! $post instanceof \WP_Post || ! self::enabled_for( $post ) ) {
			return array();
		}
		$data = self::data_for( $post );
		if ( ! self::meets_thresholds( $data ) ) {
			return array();
		}
		if ( self::is_frontend_request() && (int) get_queried_object_id() === (int) $post->ID && did_action( 'init' ) ) {
			self::enqueue_assets(); // The theme prints these items (aside): scroll-spy must run.
		}
		return $data['items'];
	}

	/**
	 * Cached analysis for a post (computed on demand).
	 *
	 * @param \WP_Post $post Post.
	 * @return array [ 'items' => array, 'words' => int ]
	 */
	private static function data_for( $post ) {
		$key = self::cache_key( $post );
		if ( ! isset( self::$cache[ $key ] ) ) {
			self::$computing = true;
			try {
				$html = self::render_content( $post );
			} finally {
				self::$computing = false;
			}
			$data                = self::analyze( $html, self::post_language( $post ) );
			self::$cache[ $key ] = array(
				'items' => $data['items'],
				'words' => $data['words'],
			);
		}
		return self::$cache[ $key ];
	}

	/**
	 * Renders post content like the_content does up to priority 12, without embeds or media-count side effects.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private static function render_content( $post ) {
		if ( ! is_admin() && (int) get_queried_object_id() === (int) $post->ID ) {
			$content = get_the_content( null, false, $post ); // Current page of a paginated post, as displayed.
		} else {
			$content = $post->post_content;
		}
		$content = do_blocks( $content );
		$content = wptexturize( $content );
		if ( ! has_blocks( $post->post_content ) ) {
			$content = wpautop( $content );
		}
		$content = shortcode_unautop( $content );
		return do_shortcode( $content );
	}

	/**
	 * Cache key: post id + content hash + page number.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private static function cache_key( $post ) {
		$page = isset( $GLOBALS['wp_query'] ) ? max( 1, (int) get_query_var( 'page' ) ) : 1;
		return $post->ID . ':' . md5( (string) $post->post_content ) . ':' . $page;
	}

	/**
	 * Clears the per-request cache.
	 */
	public static function flush_cache() {
		self::$cache = array();
	}

	/**
	 * Language code of a post ('' when the core i18n module is unavailable).
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private static function post_language( $post ) {
		if ( function_exists( 'mrtu_get_post_language' ) ) {
			$lang = mrtu_get_post_language( $post );
			return is_string( $lang ) ? $lang : '';
		}
		return '';
	}

	/*
	 * Parsing.
	 */

	/**
	 * Analyzes HTML: finds eligible H2/H3 headings, assigns missing ids and counts words.
	 *
	 * @param string $html Rendered content.
	 * @param string $lang Content language code (only affects transliteration, e.g. de: ä → ae).
	 * @return array {
	 *     @type string   $html   Content with heading ids added.
	 *     @type array[]  $items  [ 'id', 'text', 'level' ] in document order.
	 *     @type int      $words  Word count of the content.
	 *     @type int|null $before Byte offset (in `html`) of the first eligible H2 (or first heading when there is no H2).
	 *     @type int|null $after  Byte offset right after that heading's end tag.
	 * }
	 */
	public static function analyze( $html, $lang = '' ) {
		$html   = (string) $html;
		$result = array(
			'html'   => $html,
			'items'  => array(),
			'words'  => self::count_words( $html ),
			'before' => null,
			'after'  => null,
		);
		if ( false === stripos( $html, '<h' ) || ! preg_match_all( self::TOKEN_PATTERN, $html, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $result;
		}

		$skip_classes = array_filter( array_map( 'strval', (array) apply_filters( 'mrtu_toc_skip_classes', array( 'mrtu-toc-skip', 'mrtu-fleet' ) ) ) );
		$used         = array();
		foreach ( (array) apply_filters( 'mrtu_toc_reserved_ids', self::reserved_ids() ) as $reserved ) {
			$used[ (string) $reserved ] = true;
		}

		$headings = array();
		$stack    = array();
		$current  = null;
		foreach ( $tokens as $token ) {
			$offset = $token[0][1];
			$length = strlen( $token[0][0] );

			if ( isset( $token[2] ) && '' !== $token[2][0] ) { // End tag.
				$name = strtolower( $token[2][0] );
				if ( null !== $current ) {
					if ( $name === $current['tag'] ) {
						$current['inner_end'] = $offset;
						$current['end']       = $offset + $length;
						$headings[]           = $current;
						$current              = null;
					}
					continue;
				}
				for ( $i = count( $stack ) - 1; $i >= 0; $i-- ) {
					if ( $stack[ $i ]['tag'] === $name ) {
						array_splice( $stack, $i );
						break;
					}
				}
				continue;
			}
			if ( ! isset( $token[3] ) || '' === $token[3][0] ) {
				continue; // Comment, doctype or raw-text element: nothing to track.
			}

			$name  = strtolower( $token[3][0] );
			$attrs = isset( $token[4] ) ? $token[4][0] : '';
			$map   = ( false !== stripos( $attrs, 'id' ) || false !== stripos( $attrs, 'class' ) ) ? self::parse_attributes( $attrs ) : array();
			if ( isset( $map['id'] ) && '' !== trim( $map['id'] ) ) {
				$used[ trim( $map['id'] ) ] = true;
			}
			if ( null !== $current ) {
				if ( ! preg_match( '/^h[1-6]$/', $name ) ) {
					continue; // Inline markup inside the heading.
				}
				$current = null; // A heading cannot contain a heading: drop the unterminated one.
			}

			$skip = ( $stack && $stack[ count( $stack ) - 1 ]['skip'] ) || self::has_class( $map, $skip_classes );
			if ( 'h2' === $name || 'h3' === $name ) {
				$current = array(
					'tag'      => $name,
					'level'    => 'h2' === $name ? 2 : 3,
					'start'    => $offset,
					'open_end' => $offset + $length,
					'attrs'    => $attrs,
					'map'      => $map,
					'skip'     => $skip,
				);
				continue;
			}
			if ( array_key_exists( $name, self::VOID_ELEMENTS ) || '/' === substr( rtrim( $attrs ), -1 ) ) {
				continue;
			}
			$stack[] = array(
				'tag'  => $name,
				'skip' => $skip || 'details' === $name,
			);
		}

		$out       = '';
		$cursor    = 0;
		$first_any = null;
		foreach ( $headings as $heading ) {
			if ( $heading['skip'] ) {
				continue;
			}
			$text = self::heading_text( substr( $html, $heading['open_end'], $heading['inner_end'] - $heading['open_end'] ) );
			if ( '' === $text ) {
				continue;
			}
			$out  .= substr( $html, $cursor, $heading['start'] - $cursor );
			$start = strlen( $out );
			$id    = isset( $heading['map']['id'] ) ? trim( $heading['map']['id'] ) : '';
			if ( '' === $id ) {
				$id   = self::unique_id( self::slugify( $text, $lang ), $used );
				$out .= '<' . $heading['tag'] . ' id="' . esc_attr( $id ) . '"' . self::remove_attribute( $heading['attrs'], 'id' ) . '>';
			} else {
				$out .= substr( $html, $heading['start'], $heading['open_end'] - $heading['start'] );
			}
			$out   .= substr( $html, $heading['open_end'], $heading['end'] - $heading['open_end'] );
			$cursor = $heading['end'];

			if ( null === $first_any ) {
				$first_any = $start;
			}
			if ( 2 === $heading['level'] && null === $result['before'] ) {
				$result['before'] = $start;
				$result['after']  = strlen( $out );
			}
			$result['items'][] = array(
				'id'    => $id,
				'text'  => $text,
				'level' => $heading['level'],
			);
		}
		if ( ! $result['items'] ) {
			return $result;
		}
		$result['html'] = $out . substr( $html, $cursor );
		if ( null === $result['before'] ) {
			$result['before'] = $first_any; // No H2: the TOC goes before the first heading.
			$result['after']  = $first_any;
		}
		return $result;
	}

	/**
	 * Full pipeline on an HTML string, ignoring thresholds and post settings (used by tests and tools).
	 *
	 * @param string $html     Rendered content.
	 * @param string $lang     Content language code.
	 * @param string $position after_first_h2|before_first_h2.
	 * @return string Content with ids and TOC (unchanged when there are no headings).
	 */
	public static function process( $html, $lang = '', $position = 'after_first_h2' ) {
		$data = self::analyze( $html, $lang );
		if ( ! $data['items'] ) {
			return (string) $html;
		}
		return self::inject( $data, self::markup( $data['items'] ), $position );
	}

	/**
	 * Inserts TOC markup at the configured position.
	 *
	 * @param array  $data     Result of analyze().
	 * @param string $toc      TOC markup.
	 * @param string $position after_first_h2|before_first_h2.
	 * @return string
	 */
	private static function inject( $data, $toc, $position ) {
		$at = 'before_first_h2' === $position ? $data['before'] : $data['after'];
		if ( null === $at || '' === $toc ) {
			return $data['html'];
		}
		return substr( $data['html'], 0, $at ) . "\n" . $toc . "\n" . substr( $data['html'], $at );
	}

	/**
	 * Ids generated headings must not take (theme landmarks, skip-link targets, core comment anchors).
	 *
	 * @return string[]
	 */
	private static function reserved_ids() {
		return array( 'main', 'content', 'primary', 'secondary', 'masthead', 'colophon', 'header', 'footer', 'site-header', 'site-footer', 'site-navigation', 'top', 'comments', 'respond', 'wpadminbar', 'mrtu-qc-panel' );
	}

	/**
	 * Parses an attribute string (first occurrence wins, like browsers; values entity-decoded).
	 *
	 * @param string $attrs Attribute string of a start tag.
	 * @return array name => value
	 */
	private static function parse_attributes( $attrs ) {
		$map = array();
		if ( '' === trim( $attrs ) || ! preg_match_all( self::ATTR_PATTERN, $attrs, $matches, PREG_SET_ORDER ) ) {
			return $map;
		}
		foreach ( $matches as $match ) {
			$name = strtolower( $match[1] );
			if ( isset( $map[ $name ] ) ) {
				continue;
			}
			$value = '';
			for ( $i = 2; $i <= 4; $i++ ) {
				if ( isset( $match[ $i ] ) && '' !== $match[ $i ] ) {
					$value = $match[ $i ];
					break;
				}
			}
			$map[ $name ] = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		return $map;
	}

	/**
	 * Removes every occurrence of an attribute from an attribute string (quoted values are never split).
	 *
	 * @param string $attrs Attribute string.
	 * @param string $name  Attribute name.
	 * @return string
	 */
	private static function remove_attribute( $attrs, $name ) {
		if ( false === stripos( $attrs, $name ) || ! preg_match_all( self::ATTR_PATTERN, $attrs, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $attrs;
		}
		foreach ( array_reverse( $matches ) as $match ) {
			if ( strtolower( $match[1][0] ) !== $name ) {
				continue;
			}
			$start = $match[0][1];
			$end   = $start + strlen( $match[0][0] );
			while ( $start > 0 && false !== strpos( " \t\n\r\f", $attrs[ $start - 1 ] ) ) {
				--$start; // Take the separating whitespace with it.
			}
			$attrs = substr( $attrs, 0, $start ) . substr( $attrs, $end );
		}
		return $attrs;
	}

	/**
	 * Whether an attribute map has one of the classes.
	 *
	 * @param array    $map     Attributes.
	 * @param string[] $classes Classes.
	 * @return bool
	 */
	private static function has_class( $map, $classes ) {
		if ( empty( $map['class'] ) || ! $classes ) {
			return false;
		}
		return (bool) array_intersect( preg_split( '/[ \t\n\r\f]+/', trim( $map['class'] ) ), $classes );
	}

	/**
	 * Plain text of a heading's inner HTML (tags stripped, entities decoded, whitespace collapsed).
	 *
	 * @param string $html Inner HTML.
	 * @return string
	 */
	public static function heading_text( $html ) {
		$text = preg_replace( '~<br\s*/?>~i', ' ', (string) $html );
		$text = wp_strip_all_tags( (string) $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = wp_check_invalid_utf8( $text, true );
		$text = preg_replace( '/[ \t\n\r\f\x{0B}]+/u', ' ', $text );
		$text = preg_replace( '/^[\s\x{00A0}\x{202F}]+|[\s\x{00A0}\x{202F}]+$/u', '', (string) $text );
		return (string) $text;
	}

	/**
	 * Stable ASCII id from heading text: remove_accents + sanitize_title, Vietnamese đ → d, ß → ss,
	 * German umlauts → ae/oe/ue for German content. Empty result → 'section'.
	 *
	 * @param string $text Plain heading text.
	 * @param string $lang Content language code.
	 * @return string
	 */
	public static function slugify( $text, $lang = '' ) {
		$text = wp_check_invalid_utf8( (string) $text, true );
		if ( class_exists( 'Normalizer' ) ) {
			$normalized = \Normalizer::normalize( $text, \Normalizer::FORM_C );
			$text       = is_string( $normalized ) ? $normalized : $text;
		}
		$text = str_replace( array( 'Đ', 'đ', 'ẞ', 'ß' ), array( 'D', 'd', 'SS', 'ss' ), $text );
		$text = remove_accents( $text, 'de' === $lang ? 'de_DE' : 'en_US' );
		$text = (string) preg_replace( '/\p{Mn}+/u', '', $text ); // Combining marks left when intl is missing.
		$text = (string) preg_replace( '/[&<>]+|[\p{Z}\s]+/u', ' ', $text ); // sanitize_title would eat "&…;" and "<…>".
		$slug = sanitize_title_with_dashes( $text, '', 'save' ); // Not sanitize_title(): third-party filters must not change ids.
		$slug = preg_replace( '/%[0-9a-f]{2}/i', '', $slug );
		$slug = preg_replace( '/[^a-z0-9_-]+/', '', strtolower( (string) $slug ) );
		$slug = trim( (string) preg_replace( '/-{2,}/', '-', (string) $slug ), '-_' );
		if ( strlen( $slug ) > self::SLUG_MAX ) {
			$cut  = substr( $slug, 0, self::SLUG_MAX );
			$dash = strrpos( $cut, '-' );
			$slug = rtrim( false !== $dash && $dash > 20 ? substr( $cut, 0, $dash ) : $cut, '-_' );
		}
		return '' === $slug ? 'section' : $slug;
	}

	/**
	 * Makes an id unique within the post (-2, -3, …) and reserves it.
	 *
	 * @param string $base Base id.
	 * @param array  $used Used ids (keys), updated.
	 * @return string
	 */
	private static function unique_id( $base, array &$used ) {
		$id     = $base;
		$suffix = 2;
		while ( isset( $used[ $id ] ) ) {
			$id = $base . '-' . $suffix;
			++$suffix;
		}
		$used[ $id ] = true;
		return $id;
	}

	/**
	 * Counts words of rendered HTML (letters/digits runs; Vietnamese syllables count as words).
	 *
	 * @param string $html HTML.
	 * @return int
	 */
	public static function count_words( $html ) {
		$text  = preg_replace( '~<(script|style|template|noscript)\b[^>]*>.*?</\1\s*>~is', ' ', (string) $html );
		$text  = preg_replace( '~<[^>]*>~', ' ', (string) $text );
		$text  = html_entity_decode( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text  = wp_check_invalid_utf8( $text, true );
		$count = preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\p{M}\'’_-]*/u', $text );
		return false === $count ? str_word_count( $text ) : (int) $count;
	}

	/*
	 * Markup.
	 */

	/**
	 * TOC markup (contract §2.9). H3 items nest under the preceding H2; H3 items before any H2 are top-level.
	 *
	 * @param array[] $items Items.
	 * @return string
	 */
	public static function markup( array $items ) {
		$title = __( 'Table of contents', 'mr-tu-multilang-seo' );
		$html  = '<details class="mrtu-toc" data-mrtu-toc>' . "\n"
			. '<summary class="mrtu-toc__summary"><span class="mrtu-toc__title">' . esc_html( $title ) . '</span><span class="mrtu-toc__count">' . esc_html( number_format_i18n( count( $items ) ) ) . '</span></summary>' . "\n"
			. '<nav class="mrtu-toc__nav" aria-label="' . esc_attr( $title ) . '"><ol class="mrtu-toc__list">';

		$h2_open  = false;
		$sub_open = false;
		foreach ( $items as $item ) {
			$level = 3 === (int) $item['level'] ? 3 : 2;
			$link  = '<a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a>';
			if ( 3 === $level && $h2_open ) {
				if ( ! $sub_open ) {
					$html    .= '<ol>';
					$sub_open = true;
				}
				$html .= '<li class="mrtu-toc__item mrtu-toc__item--h3">' . $link . '</li>';
				continue;
			}
			if ( $sub_open ) {
				$html    .= '</ol>';
				$sub_open = false;
			}
			if ( $h2_open ) {
				$html   .= '</li>';
				$h2_open = false;
			}
			if ( 2 === $level ) {
				$html   .= '<li class="mrtu-toc__item mrtu-toc__item--h2">' . $link;
				$h2_open = true;
			} else {
				$html .= '<li class="mrtu-toc__item mrtu-toc__item--h3">' . $link . '</li>';
			}
		}
		if ( $sub_open ) {
			$html .= '</ol>';
		}
		if ( $h2_open ) {
			$html .= '</li>';
		}
		return $html . '</ol></nav>' . "\n" . '</details>';
	}

	/*
	 * Assets + meta.
	 */

	/**
	 * Registers the stylesheet and the deferred footer script.
	 */
	public static function register_assets() {
		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			wp_register_style( self::HANDLE, MRTU_ML_URL . 'assets/css/toc.css', array(), MRTU_ML_VERSION );
			wp_style_add_data( self::HANDLE, 'path', MRTU_ML_DIR . 'assets/css/toc.css' ); // Lets core inline the tiny file.
		}
		if ( ! wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_register_script(
				self::HANDLE,
				MRTU_ML_URL . 'assets/js/toc.js',
				array(),
				MRTU_ML_VERSION,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	}

	/**
	 * Enqueues the TOC script + stylesheet (called when a TOC or its items are printed).
	 */
	public static function enqueue_assets() {
		self::register_assets();
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Classic themes print the content after wp_head, so the (tiny) stylesheet is enqueued in <head> for every
	 * eligible singular post to avoid a late stylesheet. Block themes render the template before wp_head: the
	 * TOC enqueues its assets itself in time. The script is only enqueued once a TOC is actually printed.
	 */
	public static function enqueue_style_for_queried_post() {
		if ( wp_is_block_theme() || ! is_singular() || is_front_page() ) {
			return;
		}
		$post = get_queried_object();
		if ( $post instanceof \WP_Post && self::enabled_for( $post ) ) {
			wp_enqueue_style( self::HANDLE );
		}
	}

	/**
	 * Registers `_mrtu_toc` ('' | 'off') for the TOC post types unless another module already did.
	 */
	public static function register_meta() {
		$types = array_unique( array_merge( array( 'post', 'page' ), self::settings()['post_types'] ) );
		foreach ( $types as $type ) {
			if ( ! post_type_exists( $type ) || registered_meta_key_exists( 'post', self::META_KEY, $type ) ) {
				continue;
			}
			register_post_meta(
				$type,
				self::META_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_meta' ),
					'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
				)
			);
		}
	}

	/**
	 * Sanitizes `_mrtu_toc`.
	 *
	 * @param mixed $value Raw value.
	 * @return string '' or 'off'.
	 */
	public static function sanitize_meta( $value ) {
		return ( is_string( $value ) && 'off' === strtolower( trim( $value ) ) ) ? 'off' : '';
	}

	/**
	 * Meta auth callback.
	 *
	 * @param bool   $allowed   Whether allowed so far.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Post id.
	 * @return bool
	 */
	public static function can_edit_meta( $allowed, $meta_key, $object_id ) {
		return current_user_can( 'edit_post', (int) $object_id );
	}
}
