<?php
/**
 * Navigation model: assigned WordPress menus first, otherwise automatic navigation built from
 * the plugin's page roles in the current language, otherwise a plain list of top-level pages.
 *
 * Every source is normalised to the same item shape so the header, the mobile sheet and the
 * footer share one renderer:
 *     [ 'label' => string, 'url' => string, 'current' => bool, 'role' => string, 'children' => item[] ]
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Role navigation spec merged with the plugin registry (registry wins).
 *
 * @return array<string, array{label: string, nav: string, footer: string, order: int}>
 */
function mr_tu_role_nav_spec() {
	$defaults = array(
		'pillar'        => array( 'primary', 'rent', 10 ),
		'fleet'         => array( 'primary', 'info', 20 ),
		'pricing'       => array( 'primary', 'info', 30 ),
		'old-quarter'   => array( 'primary', 'rent', 40 ),
		'guide-faq'     => array( 'primary', 'info', 50 ),
		'short-term'    => array( 'more', 'rent', 60 ),
		'monthly'       => array( 'more', 'rent', 70 ),
		'about'         => array( 'more', 'brand', 80 ),
		'blog'          => array( 'more', 'info', 90 ),
		'contact'       => array( 'primary', 'brand', 100 ),
		'privacy'       => array( 'none', 'legal', 110 ),
		'terms'         => array( 'none', 'legal', 120 ),
		'rental-policy' => array( 'none', 'legal', 130 ),
	);
	$registry = mr_tu_page_roles();
	$spec     = array();

	foreach ( $defaults as $role => $default ) {
		$entry         = isset( $registry[ $role ] ) && is_array( $registry[ $role ] ) ? $registry[ $role ] : array();
		$spec[ $role ] = array(
			'label'  => isset( $entry['label'] ) ? (string) $entry['label'] : '',
			'nav'    => isset( $entry['nav'] ) ? (string) $entry['nav'] : $default[0],
			'footer' => isset( $entry['footer'] ) ? (string) $entry['footer'] : $default[1],
			'order'  => isset( $entry['order'] ) ? (int) $entry['order'] : $default[2],
		);
	}
	foreach ( $registry as $role => $entry ) {
		if ( isset( $spec[ $role ] ) || 'home' === $role || ! is_array( $entry ) ) {
			continue;
		}
		$spec[ $role ] = array(
			'label'  => isset( $entry['label'] ) ? (string) $entry['label'] : '',
			'nav'    => isset( $entry['nav'] ) ? (string) $entry['nav'] : 'none',
			'footer' => isset( $entry['footer'] ) ? (string) $entry['footer'] : 'none',
			'order'  => isset( $entry['order'] ) ? (int) $entry['order'] : 500,
		);
	}
	uasort(
		$spec,
		static function ( $a, $b ) {
			return $a['order'] - $b['order'];
		}
	);
	return $spec;
}

/**
 * Builds a navigation item for a role in the current language.
 *
 * @param string $role  Role key.
 * @param array  $entry Spec entry.
 * @return array|null Null when the role has no published page in this language.
 */
function mr_tu_role_item( $role, $entry ) {
	$url = mr_tu_role_url( $role );
	if ( '' === $url ) {
		return null;
	}
	$page_id = mr_tu_role_page_id( $role );
	$label   = $page_id ? trim( (string) get_post_meta( $page_id, '_mrtu_nav_label', true ) ) : '';
	if ( '' === $label ) {
		$label = '' !== $entry['label'] ? $entry['label'] : ( $page_id ? get_the_title( $page_id ) : $role );
	}
	return array(
		'label'    => $label,
		'url'      => $url,
		'current'  => $page_id && ( is_page( $page_id ) || ( 'blog' === $role && is_home() ) ),
		'role'     => $role,
		'children' => array(),
	);
}

/**
 * Converts an assigned menu into normalised items (two levels).
 *
 * @param string $location Menu location.
 * @return array|null Null when no menu is assigned.
 */
function mr_tu_menu_items( $location ) {
	$locations = get_nav_menu_locations();
	if ( empty( $locations[ $location ] ) ) {
		return null;
	}
	$menu_items = wp_get_nav_menu_items( (int) $locations[ $location ], array( 'update_post_term_cache' => false ) );
	if ( empty( $menu_items ) ) {
		return null;
	}
	$queried = get_queried_object_id();
	$items   = array();
	$by_id   = array();

	foreach ( $menu_items as $menu_item ) {
		$item = array(
			'label'    => $menu_item->title,
			'url'      => $menu_item->url,
			'current'  => ( $queried && (int) $menu_item->object_id === $queried && 'custom' !== $menu_item->type ),
			'role'     => in_array( 'menu-cta', (array) $menu_item->classes, true ) ? 'contact' : '',
			'children' => array(),
		);
		if ( (int) $menu_item->menu_item_parent && isset( $by_id[ (int) $menu_item->menu_item_parent ] ) ) {
			$parent_key                         = $by_id[ (int) $menu_item->menu_item_parent ];
			$items[ $parent_key ]['children'][] = $item;
		} elseif ( ! (int) $menu_item->menu_item_parent ) {
			$items[]                       = $item;
			$by_id[ (int) $menu_item->ID ] = count( $items ) - 1;
		}
	}
	return array_values( $items );
}

/**
 * Fallback list of top-level pages (no plugin, no menu).
 *
 * @param int $limit Max pages.
 * @return array
 */
function mr_tu_page_items( $limit = 12 ) {
	$pages = get_pages(
		array(
			'parent'      => 0,
			'sort_column' => 'menu_order,post_title',
			'number'      => $limit,
			'exclude'     => array_filter( array( (int) get_option( 'page_on_front' ) ) ),
		)
	);
	$items = array();
	foreach ( (array) $pages as $page ) {
		$items[] = array(
			'label'    => get_the_title( $page ),
			'url'      => get_permalink( $page ),
			'current'  => is_page( $page->ID ) || ( (int) get_option( 'page_for_posts' ) === (int) $page->ID && is_home() ),
			'role'     => '',
			'children' => array(),
		);
	}
	return $items;
}

/**
 * Header navigation model.
 *
 * @return array{primary: array, more: array, cta: array|null, source: string}
 */
function mr_tu_header_nav() {
	static $nav = null;
	if ( null !== $nav ) {
		return $nav;
	}
	$nav = array(
		'primary' => array(),
		'more'    => array(),
		'cta'     => null,
		'source'  => 'pages',
	);

	$menu = mr_tu_menu_items( 'primary' );
	if ( null !== $menu ) {
		$nav['source'] = 'menu';
		foreach ( $menu as $item ) {
			if ( 'contact' === $item['role'] && null === $nav['cta'] ) {
				$nav['cta'] = $item;
				continue;
			}
			$nav['primary'][] = $item;
		}
		return $nav;
	}

	if ( mr_tu_has_plugin() ) {
		$nav['source'] = 'roles';
		foreach ( mr_tu_role_nav_spec() as $role => $entry ) {
			if ( ! in_array( $entry['nav'], array( 'primary', 'more' ), true ) && 'contact' !== $role ) {
				continue;
			}
			$item = mr_tu_role_item( $role, $entry );
			if ( null === $item ) {
				continue;
			}
			if ( 'contact' === $role ) {
				$nav['cta'] = $item;
			} elseif ( 'more' === $entry['nav'] ) {
				$nav['more'][] = $item;
			} else {
				$nav['primary'][] = $item;
			}
		}
		return $nav;
	}

	$pages          = mr_tu_page_items();
	$nav['primary'] = array_slice( $pages, 0, 5 );
	$nav['more']    = array_slice( $pages, 5 );
	return $nav;
}

/**
 * Footer columns model.
 *
 * @return array<int, array{title: string, key: string, items: array}>
 */
function mr_tu_footer_columns() {
	$menu = mr_tu_menu_items( 'footer' );
	if ( null !== $menu ) {
		$columns = array();
		$loose   = array();
		foreach ( $menu as $item ) {
			if ( ! empty( $item['children'] ) ) {
				$columns[] = array(
					'title' => $item['label'],
					'key'   => sanitize_html_class( sanitize_title( $item['label'] ) ),
					'items' => $item['children'],
				);
			} else {
				$loose[] = $item;
			}
		}
		if ( $loose ) {
			$columns[] = array(
				'title' => __( 'Links', 'mr-tu' ),
				'key'   => 'links',
				'items' => $loose,
			);
		}
		return $columns;
	}

	if ( mr_tu_has_plugin() ) {
		$titles  = array(
			'rent'  => __( 'Rent a bike', 'mr-tu' ),
			'info'  => __( 'Good to know', 'mr-tu' ),
			'brand' => __( 'Mr Tu', 'mr-tu' ),
			'legal' => __( 'Legal', 'mr-tu' ),
		);
		$columns = array();
		foreach ( $titles as $key => $title ) {
			$columns[ $key ] = array(
				'title' => $title,
				'key'   => $key,
				'items' => array(),
			);
		}
		foreach ( mr_tu_role_nav_spec() as $role => $entry ) {
			if ( ! isset( $columns[ $entry['footer'] ] ) ) {
				continue;
			}
			$item = mr_tu_role_item( $role, $entry );
			if ( null !== $item ) {
				$columns[ $entry['footer'] ]['items'][] = $item;
			}
		}
		return array_values(
			array_filter(
				$columns,
				static function ( $column ) {
					return ! empty( $column['items'] );
				}
			)
		);
	}

	$items   = mr_tu_page_items( 8 );
	$privacy = get_privacy_policy_url();
	if ( $privacy ) {
		$items[] = array(
			'label'    => __( 'Privacy policy', 'mr-tu' ),
			'url'      => $privacy,
			'current'  => false,
			'role'     => 'privacy',
			'children' => array(),
		);
	}
	if ( ! $items ) {
		return array();
	}
	return array(
		array(
			'title' => __( 'Pages', 'mr-tu' ),
			'key'   => 'pages',
			'items' => $items,
		),
	);
}

/**
 * Prints a navigation link.
 *
 * @param array  $item  Item.
 * @param string $class Link class.
 */
function mr_tu_nav_link( $item, $class ) {
	printf(
		'<a class="%1$s" href="%2$s"%3$s>%4$s</a>',
		esc_attr( $class . ( $item['current'] ? ' is-current' : '' ) ),
		esc_url( $item['url'] ),
		$item['current'] ? ' aria-current="page"' : '',
		esc_html( $item['label'] )
	);
}

/**
 * Prints the header navigation list (desktop).
 *
 * @param array $nav Header nav model.
 */
function mr_tu_render_header_nav( $nav ) {
	if ( empty( $nav['primary'] ) && empty( $nav['more'] ) ) {
		return;
	}
	echo '<ul class="site-nav__list" role="list">';
	foreach ( $nav['primary'] as $item ) {
		if ( ! empty( $item['children'] ) ) {
			mr_tu_render_nav_group( $item['label'], $item['children'], $item['current'] );
			continue;
		}
		echo '<li class="site-nav__item">';
		mr_tu_nav_link( $item, 'site-nav__link' );
		echo '</li>';
	}
	if ( ! empty( $nav['more'] ) ) {
		mr_tu_render_nav_group( __( 'More', 'mr-tu' ), $nav['more'], false );
	}
	echo '</ul>';
}

/**
 * Prints a dropdown group (`details` works without JavaScript; theme.js adds outside-click/Esc).
 *
 * @param string $label    Group label.
 * @param array  $children Items.
 * @param bool   $current  Whether the parent is current.
 */
function mr_tu_render_nav_group( $label, $children, $current ) {
	$has_current = $current;
	foreach ( $children as $child ) {
		$has_current = $has_current || $child['current'];
	}
	echo '<li class="site-nav__item site-nav__item--group">';
	echo '<details class="site-nav__group" data-mrtu-popover>';
	echo '<summary class="site-nav__link site-nav__summary' . ( $has_current ? ' is-current-parent' : '' ) . '">' . esc_html( $label ) . mr_tu_icon( 'chevron' ) . '</summary>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
	echo '<ul class="site-nav__panel" role="list">';
	foreach ( $children as $child ) {
		echo '<li>';
		mr_tu_nav_link( $child, 'site-nav__panel-link' );
		echo '</li>';
	}
	echo '</ul></details></li>';
}
