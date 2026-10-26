<?php
/**
 * Bridge to the Mr Tu Multilang SEO plugin API.
 *
 * Every call into the plugin goes through these wrappers so templates never fatal when the
 * plugin is inactive, and the fallbacks stay honest: no switcher, no prices, no phone number,
 * plain `home_url()` links. Contract: docs/architecture-contract.md §2.4.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the multilingual plugin API is available.
 *
 * @return bool
 */
function mr_tu_has_plugin() {
	return function_exists( 'mrtu_current_language' );
}

/**
 * Current language code (two letters).
 *
 * Without the plugin the code is derived from the site locale.
 *
 * @return string
 */
function mr_tu_current_language() {
	if ( function_exists( 'mrtu_current_language' ) ) {
		$lang = (string) mrtu_current_language();
		if ( '' !== $lang ) {
			return $lang;
		}
	}
	return strtolower( substr( determine_locale(), 0, 2 ) );
}

/**
 * Language home URL (or a path below it).
 *
 * @param string $path Optional path relative to the language home.
 * @return string
 */
function mr_tu_home_url( $path = '' ) {
	if ( function_exists( 'mrtu_home_url' ) ) {
		$url = (string) mrtu_home_url( $path );
		if ( '' !== $url ) {
			return $url;
		}
	}
	return home_url( '/' . ltrim( (string) $path, '/' ) );
}

/**
 * Page role registry (empty without the plugin).
 *
 * @return array<string, array>
 */
function mr_tu_page_roles() {
	if ( ! function_exists( 'mrtu_page_roles' ) ) {
		return array();
	}
	$roles = mrtu_page_roles();
	return is_array( $roles ) ? $roles : array();
}

/**
 * Permalink of the page holding a role in the current language.
 *
 * @param string $role Role key, e.g. 'pricing'.
 * @return string Empty string when unavailable.
 */
function mr_tu_role_url( $role ) {
	if ( ! function_exists( 'mrtu_role_url' ) ) {
		return '';
	}
	return (string) mrtu_role_url( $role );
}

/**
 * Published page ID holding a role in the current language.
 *
 * @param string $role Role key.
 * @return int 0 when unavailable.
 */
function mr_tu_role_page_id( $role ) {
	if ( ! function_exists( 'mrtu_get_page_id_by_role' ) ) {
		return 0;
	}
	return (int) mrtu_get_page_id_by_role( $role );
}

/**
 * Role of a post ('' when none or without the plugin).
 *
 * @param int $post_id Post ID.
 * @return string
 */
function mr_tu_post_role( $post_id ) {
	if ( ! function_exists( 'mrtu_get_post_role' ) ) {
		return '';
	}
	return (string) mrtu_get_post_role( (int) $post_id );
}

/**
 * Public phone number in a given format ('' when not public or without the plugin).
 *
 * @param string $format display|e164|tel.
 * @return string
 */
function mr_tu_phone( $format = 'display' ) {
	if ( ! function_exists( 'mrtu_phone' ) ) {
		return '';
	}
	return (string) mrtu_phone( $format );
}

/**
 * Public (verified) business field. Never returns unverified data.
 *
 * @param string $field Field key, e.g. 'address', 'hours', 'service_area'.
 * @return mixed '' when not public.
 */
function mr_tu_business( $field ) {
	if ( ! function_exists( 'mrtu_business' ) ) {
		return '';
	}
	$value = mrtu_business( $field, true );
	return null === $value ? '' : $value;
}

/**
 * Language switcher links for the current request.
 *
 * @return array<string, array> Empty without the plugin or with a single language.
 */
function mr_tu_switcher_links() {
	if ( ! function_exists( 'mrtu_switcher_links' ) ) {
		return array();
	}
	$links = mrtu_switcher_links();
	return ( is_array( $links ) && count( $links ) > 1 ) ? $links : array();
}

/**
 * Breadcrumb trail for the current request: list of [ name, url ].
 *
 * Fallback: Home › current title.
 *
 * @return array<int, array{name: string, url: string}>
 */
function mr_tu_breadcrumb_trail() {
	if ( function_exists( 'mrtu_breadcrumb_trail' ) ) {
		$trail = mrtu_breadcrumb_trail();
		if ( is_array( $trail ) && ! empty( $trail ) ) {
			return $trail;
		}
	}

	$trail = array(
		array(
			'name' => __( 'Home', 'mr-tu' ),
			'url'  => mr_tu_home_url(),
		),
	);
	if ( is_singular() ) {
		$trail[] = array(
			'name' => single_post_title( '', false ),
			'url'  => '',
		);
	} elseif ( is_search() ) {
		$trail[] = array(
			'name' => __( 'Search', 'mr-tu' ),
			'url'  => '',
		);
	} elseif ( is_home() ) {
		$trail[] = array(
			'name' => mr_tu_blog_title(),
			'url'  => '',
		);
	} elseif ( is_archive() ) {
		$trail[] = array(
			'name' => wp_strip_all_tags( get_the_archive_title() ),
			'url'  => '',
		);
	} elseif ( is_404() ) {
		$trail[] = array(
			'name' => __( 'Page not found', 'mr-tu' ),
			'url'  => '',
		);
	}
	return $trail;
}

/**
 * Headings of a post for the sticky table of contents.
 *
 * @param int|WP_Post|null $post Post.
 * @return array<int, array{id: string, text: string, level: int}>
 */
function mr_tu_toc_items( $post = null ) {
	if ( ! function_exists( 'mrtu_toc_items' ) ) {
		return array();
	}
	$items = mrtu_toc_items( $post );
	return is_array( $items ) ? $items : array();
}

/**
 * Whether the plugin taxonomy for languages is available for queries.
 *
 * @return bool
 */
function mr_tu_language_tax_query_available() {
	return mr_tu_has_plugin() && taxonomy_exists( 'mrtu_language' );
}

/**
 * Adds a same-language tax query to WP_Query args when the plugin is active.
 *
 * @param array $args WP_Query arguments.
 * @return array
 */
function mr_tu_with_language( $args ) {
	if ( ! mr_tu_language_tax_query_available() ) {
		return $args;
	}
	$tax_query         = isset( $args['tax_query'] ) ? (array) $args['tax_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Small, cached related-content queries.
	$tax_query[]       = array(
		'taxonomy' => 'mrtu_language',
		'field'    => 'slug',
		'terms'    => mr_tu_current_language(),
	);
	$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- See above.
	return $args;
}
