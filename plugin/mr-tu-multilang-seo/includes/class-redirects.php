<?php
/**
 * Managed redirects (contract §2.8): option `mrtu_redirects`
 * `[ '/old-path/' => [ 'to' => url, 'code' => 301|302, 'hits' => int, 'created' => ts ] ]`,
 * matched only on 404 at `template_redirect` priority 0, auto-created when a published page/post
 * changes its slug or parent (same-language target only), chain/loop safe, at most 500 entries.
 *
 * Pages never get core's old-slug redirect, so this map is what keeps old page URLs alive.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Redirect map.
 */
final class Redirects {

	const OPTION = 'mrtu_redirects';
	const MAX    = 500;
	const CODES  = array( 301, 302 );

	/**
	 * Maximum hops followed when collapsing chains.
	 */
	const MAX_HOPS = 10;

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 0 );
		add_action( 'post_updated', array( __CLASS__, 'on_post_updated' ), 10, 3 );
	}

	/*
	 * Storage
	 */

	/**
	 * All entries, normalized.
	 *
	 * @return array path => [ to, code, hits, created ]
	 */
	public static function all() {
		$raw = get_option( self::OPTION, array() );
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $raw as $from => $entry ) {
			$path = self::normalize_path( (string) $from );
			if ( '' === $path || ! is_array( $entry ) || empty( $entry['to'] ) ) {
				continue;
			}
			$out[ $path ] = array(
				'to'      => (string) $entry['to'],
				'code'    => isset( $entry['code'] ) && in_array( (int) $entry['code'], self::CODES, true ) ? (int) $entry['code'] : 301,
				'hits'    => isset( $entry['hits'] ) ? max( 0, (int) $entry['hits'] ) : 0,
				'created' => isset( $entry['created'] ) ? (int) $entry['created'] : 0,
			);
		}
		return $out;
	}

	/**
	 * Saves the map (autoloaded: looked up on every 404).
	 *
	 * @param array $map Map.
	 */
	private static function save( array $map ) {
		if ( count( $map ) > self::MAX ) {
			uasort(
				$map,
				static function ( $a, $b ) {
					return $b['created'] - $a['created']; // Newest first.
				}
			);
			$map = array_slice( $map, 0, self::MAX, true );
		}
		update_option( self::OPTION, $map, true );
	}

	/**
	 * Adds (or replaces) a redirect. Chains are collapsed, loops refused, entries pointing at $from are repointed.
	 *
	 * @param string $from Site-relative path (or a same-site URL).
	 * @param string $to   Target URL or site-relative path.
	 * @param int    $code 301 | 302.
	 * @return true|\WP_Error
	 */
	public static function add( $from, $to, $code = 301 ) {
		$path = self::normalize_path( (string) $from );
		if ( '' === $path || '/' === $path ) {
			return new \WP_Error( 'mrtu_redirect_from', __( 'Enter the old address as a path such as /old-page/.', 'mr-tu-multilang-seo' ) );
		}
		$target = self::normalize_target( (string) $to );
		if ( '' === $target ) {
			return new \WP_Error( 'mrtu_redirect_to', __( 'Enter the new address as a path or a full https:// link on this website.', 'mr-tu-multilang-seo' ) );
		}
		$code = in_array( (int) $code, self::CODES, true ) ? (int) $code : 301;
		$map  = self::all();

		// Collapse chains: A → B while B → C becomes A → C.
		$hops = 0;
		while ( $hops < self::MAX_HOPS ) {
			$target_path = self::normalize_path( $target );
			if ( '' === $target_path || ! isset( $map[ $target_path ] ) || $target_path === $path ) {
				break;
			}
			$target = $map[ $target_path ]['to'];
			++$hops;
		}
		if ( self::normalize_path( $target ) === $path ) {
			return new \WP_Error( 'mrtu_redirect_loop', __( 'That redirect would point to itself.', 'mr-tu-multilang-seo' ) );
		}

		// The old address is dead: anything that still redirected to it goes to the new target.
		foreach ( $map as $other_from => $other ) {
			if ( self::normalize_path( $other['to'] ) === $path ) {
				if ( $other_from === self::normalize_path( $target ) ) {
					unset( $map[ $other_from ] ); // Would loop back.
				} else {
					$map[ $other_from ]['to'] = $target;
				}
			}
		}

		$existing     = isset( $map[ $path ] ) ? $map[ $path ] : null;
		$map[ $path ] = array(
			'to'      => $target,
			'code'    => $code,
			'hits'    => $existing ? $existing['hits'] : 0,
			'created' => $existing ? $existing['created'] : time(),
		);
		self::save( $map );
		return true;
	}

	/**
	 * Removes a redirect.
	 *
	 * @param string $from Path.
	 * @return bool Whether it existed.
	 */
	public static function remove( $from ) {
		$path = self::normalize_path( (string) $from );
		$map  = self::all();
		if ( '' === $path || ! isset( $map[ $path ] ) ) {
			return false;
		}
		unset( $map[ $path ] );
		self::save( $map );
		return true;
	}

	/**
	 * Finds the entry for a request path (with or without trailing slash).
	 *
	 * @param string $path Request path.
	 * @return array|null Entry plus 'from'.
	 */
	public static function match( $path ) {
		$map  = self::all();
		$norm = self::normalize_path( (string) $path );
		foreach ( array_unique( array( $norm, rtrim( $norm, '/' ), $norm . '/' ) ) as $candidate ) {
			if ( '' !== $candidate && isset( $map[ $candidate ] ) ) {
				return $map[ $candidate ] + array( 'from' => $candidate );
			}
		}
		return null;
	}

	/*
	 * Normalization
	 */

	/**
	 * Site-relative path with leading slash and trailing slash (unless the last segment looks like a file).
	 *
	 * Accepts a path or a URL of this site. Query string and fragment are dropped. '' when outside the site.
	 *
	 * @param string $value Path or URL.
	 * @return string
	 */
	public static function normalize_path( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '#^(https?:)?//#i', $value ) ) {
			$parts = Links::split( $value );
			if ( null === $parts ) {
				return '';
			}
			$value = '/' . $parts[1];
		}
		$path = (string) wp_parse_url( $value, PHP_URL_PATH );
		if ( '' === $path ) {
			$path = '/';
		}
		$path = '/' . ltrim( str_replace( '\\', '/', $path ), '/' );
		$path = preg_replace( '#/{2,}#', '/', $path );
		$home = Router::home_path();
		if ( '' !== $home && 0 === strpos( $path, '/' . $home . '/' ) ) {
			$path = (string) substr( $path, strlen( $home ) + 1 );
		}
		$last = basename( $path );
		if ( '/' !== $path && false === strpos( $last, '.' ) ) {
			$path = trailingslashit( $path );
		}
		return $path;
	}

	/**
	 * Absolute same-site target URL ('' when invalid / another host).
	 *
	 * @param string $to Target.
	 * @return string
	 */
	public static function normalize_target( $to ) {
		$to = trim( (string) $to );
		if ( '' === $to ) {
			return '';
		}
		if ( ! preg_match( '#^(https?:)?//#i', $to ) ) {
			$to = home_url( '/' . ltrim( $to, '/' ) );
		}
		$clean = esc_url_raw( $to, array( 'http', 'https' ) );
		if ( '' === $clean || null === Links::split( $clean ) ) {
			return '';
		}
		return $clean;
	}

	/*
	 * Frontend
	 */

	/**
	 * Redirects 404 requests found in the map (`template_redirect`, priority 0).
	 */
	public static function maybe_redirect() {
		if ( ! is_404() || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$uri   = wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed, compared and only used through wp_safe_redirect().
		$path  = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );
		$entry = self::match( $path );
		if ( ! $entry ) {
			return;
		}
		$target = $entry['to'];
		if ( self::normalize_path( $target ) === self::normalize_path( $path ) ) {
			return; // Would loop.
		}
		$query = (string) wp_parse_url( (string) $uri, PHP_URL_QUERY );
		if ( '' !== $query && false === strpos( $target, '?' ) ) {
			$target .= '?' . $query;
		}
		self::count_hit( $entry['from'] );
		wp_safe_redirect( $target, $entry['code'], 'Mr Tu Multilang' );
		exit;
	}

	/**
	 * Increments the hit counter of an entry.
	 *
	 * @param string $from Path.
	 */
	private static function count_hit( $from ) {
		$map = self::all();
		if ( isset( $map[ $from ] ) ) {
			++$map[ $from ]['hits'];
			update_option( self::OPTION, $map, true );
		}
	}

	/*
	 * Auto-create on slug / parent changes
	 */

	/**
	 * A published page/post that changes slug or parent gets a 301 from its old URL (and so do its children).
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $after   Post after the update.
	 * @param \WP_Post $before  Post before the update.
	 */
	public static function on_post_updated( $post_id, $after, $before ) {
		if ( ! $after instanceof \WP_Post || ! $before instanceof \WP_Post || ! Translations::is_translatable_post_type( $after->post_type ) ) {
			return;
		}
		if ( 'publish' !== $before->post_status || 'publish' !== $after->post_status ) {
			return;
		}
		if ( $before->post_name === $after->post_name && (int) $before->post_parent === (int) $after->post_parent ) {
			return;
		}
		$old = (string) get_permalink( $before );
		$new = (string) get_permalink( $after );
		if ( '' === $old || '' === $new || $old === $new || Links::language_of_url( $old ) !== Links::language_of_url( $new ) ) {
			return;
		}
		$old_path = self::normalize_path( $old );
		$new_path = self::normalize_path( $new );
		if ( '' === $old_path || '/' === $old_path || $old_path === $new_path ) {
			return;
		}
		self::remove( $new_path ); // The new address is live again: it must not redirect anywhere.
		self::add( $old_path, $new, 301 );

		if ( is_post_type_hierarchical( $after->post_type ) ) {
			$children = get_pages(
				array(
					'child_of'        => $after->ID,
					'post_type'       => $after->post_type,
					'post_status'     => 'publish',
					Router::QUERY_VAR => 'all',
				)
			);
			foreach ( (array) $children as $child ) {
				$child_new = (string) get_permalink( $child );
				if ( '' === $child_new || 0 !== strpos( $child_new, $new ) ) {
					continue;
				}
				$child_old = $old . substr( $child_new, strlen( $new ) );
				self::remove( self::normalize_path( $child_new ) );
				self::add( $child_old, $child_new, 301 );
			}
		}
	}
}
