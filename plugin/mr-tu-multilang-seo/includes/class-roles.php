<?php
/**
 * Page roles (contract 2.3): a role belongs to a translation group, so every language version of
 * e.g. the "pricing" page is found in O(1): option `mrtu_roles_map` [ role => group term id ] + the
 * group's JSON map. Post meta `_mrtu_role` mirrors the role on every member for convenient reads.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Page role registry and lookups.
 */
final class Roles {

	const META = '_mrtu_role';

	const OPTION = 'mrtu_roles_map';

	/**
	 * Roles map cache (null = not loaded).
	 *
	 * @var array|null
	 */
	private static $map = null;

	/**
	 * Wires hooks.
	 */
	public static function init() {
		foreach ( array( 'add_option_', 'update_option_', 'delete_option_' ) as $prefix ) {
			add_action( $prefix . self::OPTION, array( __CLASS__, 'flush' ) );
		}
	}

	/**
	 * Clears the in-request cache.
	 */
	public static function flush() {
		self::$map = null;
	}

	/**
	 * Role registry (filter `mrtu_page_roles`).
	 *
	 * @return array role => [ label, nav (primary|more|none), footer (rent|info|brand|legal|none), order ]
	 */
	public static function registry() {
		$roles = array(
			'home'          => array(
				'label'  => __( 'Home page', 'mr-tu-multilang-seo' ),
				'nav'    => 'none',
				'footer' => 'none',
				'order'  => 0,
			),
			'pillar'        => array(
				'label'  => __( 'Motorbike rental Hanoi', 'mr-tu-multilang-seo' ),
				'nav'    => 'primary',
				'footer' => 'rent',
				'order'  => 10,
			),
			'old-quarter'   => array(
				'label'  => __( 'Old Quarter', 'mr-tu-multilang-seo' ),
				'nav'    => 'primary',
				'footer' => 'rent',
				'order'  => 20,
			),
			'pricing'       => array(
				'label'  => __( 'Prices', 'mr-tu-multilang-seo' ),
				'nav'    => 'primary',
				'footer' => 'info',
				'order'  => 30,
			),
			'fleet'         => array(
				'label'  => __( 'Motorbikes', 'mr-tu-multilang-seo' ),
				'nav'    => 'primary',
				'footer' => 'info',
				'order'  => 40,
			),
			'short-term'    => array(
				'label'  => __( 'Daily & weekly rental', 'mr-tu-multilang-seo' ),
				'nav'    => 'more',
				'footer' => 'rent',
				'order'  => 50,
			),
			'monthly'       => array(
				'label'  => __( 'Monthly rental', 'mr-tu-multilang-seo' ),
				'nav'    => 'more',
				'footer' => 'rent',
				'order'  => 60,
			),
			'guide-faq'     => array(
				'label'  => __( 'How to rent & FAQ', 'mr-tu-multilang-seo' ),
				'nav'    => 'primary',
				'footer' => 'info',
				'order'  => 70,
			),
			'delivery'      => array(
				'label'  => __( 'Delivery & collection', 'mr-tu-multilang-seo' ),
				'nav'    => 'more',
				'footer' => 'info',
				'order'  => 75,
			),
			'about'         => array(
				'label'  => __( 'About Mr Tu', 'mr-tu-multilang-seo' ),
				'nav'    => 'more',
				'footer' => 'brand',
				'order'  => 80,
			),
			'contact'       => array(
				'label'  => __( 'Contact', 'mr-tu-multilang-seo' ),
				'nav'    => 'primary',
				'footer' => 'brand',
				'order'  => 90,
			),
			'blog'          => array(
				'label'  => __( 'Blog', 'mr-tu-multilang-seo' ),
				'nav'    => 'more',
				'footer' => 'info',
				'order'  => 100,
			),
			'privacy'       => array(
				'label'  => __( 'Privacy policy', 'mr-tu-multilang-seo' ),
				'nav'    => 'none',
				'footer' => 'legal',
				'order'  => 110,
			),
			'terms'         => array(
				'label'  => __( 'Terms of use', 'mr-tu-multilang-seo' ),
				'nav'    => 'none',
				'footer' => 'legal',
				'order'  => 120,
			),
			'rental-policy' => array(
				'label'  => __( 'Rental rules', 'mr-tu-multilang-seo' ),
				'nav'    => 'none',
				'footer' => 'legal',
				'order'  => 130,
			),
		);

		/**
		 * Filters the page role registry.
		 *
		 * @param array $roles role => [ label, nav, footer, order ].
		 */
		$roles = (array) apply_filters( 'mrtu_page_roles', $roles );
		uasort(
			$roles,
			static function ( $a, $b ) {
				return ( isset( $a['order'] ) ? (int) $a['order'] : 0 ) - ( isset( $b['order'] ) ? (int) $b['order'] : 0 );
			}
		);
		return $roles;
	}

	/**
	 * Whether a role is registered.
	 *
	 * @param string $role Role key.
	 * @return bool
	 */
	public static function exists( $role ) {
		$roles = self::registry();
		return is_string( $role ) && isset( $roles[ $role ] );
	}

	/**
	 * Roles map: role => translation group term id.
	 *
	 * @return array
	 */
	public static function map() {
		if ( null === self::$map ) {
			$map       = get_option( self::OPTION, array() );
			self::$map = is_array( $map ) ? array_map( 'intval', $map ) : array();
		}
		return self::$map;
	}

	/**
	 * Saves the roles map (autoloaded: menus/footers read it on most requests).
	 *
	 * @param array $map role => group id.
	 */
	private static function save_map( array $map ) {
		update_option( self::OPTION, $map, true );
		self::$map = $map;
	}

	/**
	 * Role carried by a group.
	 *
	 * @param int $group_id Group term id.
	 * @return string '' when none.
	 */
	public static function group_role( $group_id ) {
		$role = array_search( (int) $group_id, self::map(), true );
		return false === $role ? '' : (string) $role;
	}

	/**
	 * Assigns a role to a group (one role per group, one group per role).
	 *
	 * @param string $role     Role.
	 * @param int    $group_id Group term id.
	 */
	public static function assign_group( $role, $group_id ) {
		$group_id = (int) $group_id;
		$map      = self::map();
		$previous = isset( $map[ $role ] ) ? (int) $map[ $role ] : 0;
		foreach ( $map as $other_role => $other_group ) {
			if ( $other_group === $group_id && $other_role !== $role ) {
				unset( $map[ $other_role ] );
			}
		}
		$map[ $role ] = $group_id;
		self::save_map( $map );

		if ( $previous && $previous !== $group_id ) {
			self::sync_group( $previous, Translations::group_map( $previous ) );
		}
		self::sync_group( $group_id, Translations::group_map( $group_id ) );
	}

	/**
	 * Drops every role pointing at a (deleted) group.
	 *
	 * @param int $group_id Group term id.
	 */
	public static function forget_group( $group_id ) {
		$map     = self::map();
		$changed = false;
		foreach ( $map as $role => $group ) {
			if ( (int) $group === (int) $group_id ) {
				unset( $map[ $role ] );
				$changed = true;
			}
		}
		if ( $changed ) {
			self::save_map( $map );
		}
	}

	/**
	 * Mirrors the group role into `_mrtu_role` on every member.
	 *
	 * @param int   $group_id Group term id.
	 * @param array $members  lang => post_id.
	 */
	public static function sync_group( $group_id, array $members ) {
		$role = self::group_role( $group_id );
		foreach ( $members as $post_id ) {
			$current = get_post_meta( (int) $post_id, self::META, true );
			if ( '' === $role ) {
				if ( '' !== $current ) {
					delete_post_meta( (int) $post_id, self::META );
				}
			} elseif ( $current !== $role ) {
				update_post_meta( (int) $post_id, self::META, $role );
			}
		}
	}

	/**
	 * Role of a post.
	 *
	 * @param int $post_id Post id.
	 * @return string '' when none.
	 */
	public static function get_post_role( $post_id ) {
		$group = Translations::get_post_group( $post_id );
		if ( $group ) {
			return self::group_role( $group );
		}
		return '';
	}

	/**
	 * Sets (or clears with '') the role of a post's whole translation group.
	 *
	 * @param int    $post_id Post id.
	 * @param string $role    Role key or ''.
	 * @return bool
	 */
	public static function set_post_role( $post_id, $role ) {
		$post = get_post( $post_id );
		if ( ! $post || ! Translations::is_translatable_post_type( $post->post_type ) ) {
			return false;
		}
		$role = (string) $role;
		if ( '' === $role ) {
			$group = Translations::get_post_group( $post->ID );
			if ( $group ) {
				self::forget_group( $group );
				self::sync_group( $group, Translations::group_map( $group ) );
			}
			delete_post_meta( $post->ID, self::META );
			return true;
		}
		if ( ! self::exists( $role ) ) {
			return false;
		}
		$group = Translations::ensure_group( $post->ID );
		if ( ! $group ) {
			return false;
		}
		self::assign_group( $role, $group );
		return true;
	}

	/**
	 * Published post id of a role in a language.
	 *
	 * Falls back to the Reading settings for home / blog / privacy when no group carries the role.
	 *
	 * @param string      $role Role.
	 * @param string|null $lang Language (null = current language).
	 * @return int 0 when unavailable.
	 */
	public static function get_page_id_by_role( $role, $lang = null ) {
		$lang = null === $lang ? Router::effective_language() : (string) $lang;
		if ( ! Languages::exists( $lang ) ) {
			return 0;
		}
		$map = self::map();
		$id  = 0;
		if ( isset( $map[ $role ] ) ) {
			$members = Translations::group_map( $map[ $role ] );
			$id      = isset( $members[ $lang ] ) ? (int) $members[ $lang ] : 0;
		} else {
			$fallback = 0;
			if ( 'home' === $role && 'page' === get_option( 'show_on_front' ) ) {
				$fallback = Router::raw_option( 'page_on_front' );
			} elseif ( 'blog' === $role && 'page' === get_option( 'show_on_front' ) ) {
				$fallback = Router::raw_option( 'page_for_posts' );
			} elseif ( 'privacy' === $role ) {
				$fallback = Router::raw_option( 'wp_page_for_privacy_policy' );
			}
			if ( $fallback ) {
				$id = Translations::get_post_translation( $fallback, $lang, false );
			}
		}
		return $id && 'publish' === get_post_status( $id ) ? $id : 0;
	}

	/**
	 * Permalink of a role in a language.
	 *
	 * @param string      $role Role.
	 * @param string|null $lang Language (null = current language).
	 * @return string '' when unavailable.
	 */
	public static function role_url( $role, $lang = null ) {
		$id = self::get_page_id_by_role( $role, $lang );
		if ( ! $id ) {
			return '';
		}
		$url = get_permalink( $id );
		return $url ? $url : '';
	}
}
