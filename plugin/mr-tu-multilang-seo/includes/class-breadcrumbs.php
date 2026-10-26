<?php
/**
 * Breadcrumb trail for the current request (theme markup + BreadcrumbList schema share it).
 *
 * Home › ancestors › page · Home › Blog page › primary category › post · Home › Blog › term ·
 * Home › search results · Home › page not found.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Breadcrumbs.
 */
final class Breadcrumbs {

	/**
	 * Wires hooks (none needed; kept for the module contract).
	 */
	public static function init() {}

	/**
	 * Trail for the current request (contract `mrtu_breadcrumb_trail()`).
	 *
	 * @return array[] [ [ 'name' => string, 'url' => string ], … ] last item = current page (url may be '').
	 */
	public static function trail() {
		global $wp_query;
		$lang  = Router::effective_language();
		$trail = array( self::item( Languages::ui_string( 'home', $lang ), Links::home_url( '', $lang ) ) );

		if ( ! $wp_query instanceof \WP_Query || $wp_query->is_front_page() ) {
			return self::finish( $trail );
		}

		if ( $wp_query->is_404() ) {
			$trail[] = self::item( Languages::ui_string( 'not_found', $lang ), '' );
		} elseif ( $wp_query->is_search() ) {
			$trail[] = self::item( sprintf( Languages::ui_string( 'search_results', $lang ), get_search_query( false ) ), '' );
		} elseif ( $wp_query->is_home() ) {
			$blog = self::blog_item( $lang );
			if ( $blog ) {
				$trail[] = $blog;
			}
		} elseif ( $wp_query->is_singular() ) {
			$post = $wp_query->get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$trail = array_merge( $trail, self::singular_items( $post, $lang ) );
			}
		} elseif ( $wp_query->is_category() || $wp_query->is_tag() || $wp_query->is_tax() ) {
			$term = $wp_query->get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$blog = in_array( $term->taxonomy, array( 'category', 'post_tag' ), true ) ? self::blog_item( $lang ) : null;
				if ( $blog ) {
					$trail[] = $blog;
				}
				foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
					$ancestor = get_term( $ancestor_id, $term->taxonomy );
					if ( $ancestor instanceof \WP_Term ) {
						$trail[] = self::term_item( $ancestor );
					}
				}
				$trail[] = self::term_item( $term );
			}
		} elseif ( $wp_query->is_post_type_archive() ) {
			$type    = $wp_query->get( 'post_type' );
			$type    = is_array( $type ) ? reset( $type ) : $type;
			$link    = get_post_type_archive_link( (string) $type );
			$trail[] = self::item( post_type_archive_title( '', false ), $link ? $link : '' );
		} elseif ( $wp_query->is_author() || $wp_query->is_date() ) {
			$trail[] = self::item( get_the_archive_title(), '' );
		}

		$paged = (int) $wp_query->get( 'paged' );
		if ( $paged > 1 && ! $wp_query->is_404() && ! $wp_query->is_search() ) {
			$trail[] = self::item( sprintf( Languages::ui_string( 'page_n', $lang ), $paged ), '' );
		}
		return self::finish( $trail );
	}

	/**
	 * Applies the public filter.
	 *
	 * @param array $trail Trail.
	 * @return array
	 */
	private static function finish( array $trail ) {
		/**
		 * Filters the breadcrumb trail.
		 *
		 * @param array $trail [ [ 'name' => string, 'url' => string ], … ].
		 */
		$trail = apply_filters( 'mrtu_breadcrumb_trail', $trail );
		return is_array( $trail ) ? array_values( $trail ) : array();
	}

	/**
	 * One item with plain-text name (entities decoded, tags stripped).
	 *
	 * @param string $name Name.
	 * @param string $url  URL.
	 * @return array
	 */
	private static function item( $name, $url ) {
		$name = html_entity_decode( wp_strip_all_tags( (string) $name ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return array(
			'name' => trim( $name ),
			'url'  => (string) $url,
		);
	}

	/**
	 * Term item.
	 *
	 * @param \WP_Term $term Term.
	 * @return array
	 */
	private static function term_item( \WP_Term $term ) {
		$link = get_term_link( $term );
		return self::item( $term->name, is_wp_error( $link ) ? '' : $link );
	}

	/**
	 * Posts page item in a language (null when there is no published posts page in that language).
	 *
	 * @param string $lang Language.
	 * @return array|null
	 */
	private static function blog_item( $lang ) {
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return null;
		}
		$blog = Router::raw_option( 'page_for_posts' );
		$id   = $blog ? Translations::get_post_translation( $blog, $lang ) : 0;
		if ( ! $id ) {
			return null;
		}
		return self::item( get_the_title( $id ), (string) get_permalink( $id ) );
	}

	/**
	 * Items after "Home" for a singular object.
	 *
	 * @param \WP_Post $post Post.
	 * @param string   $lang Language.
	 * @return array[]
	 */
	private static function singular_items( \WP_Post $post, $lang ) {
		$items = array();
		if ( is_post_type_hierarchical( $post->post_type ) ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
				if ( Links::is_front_page_group( $ancestor_id ) ) {
					continue;
				}
				$items[] = self::item( get_the_title( $ancestor_id ), (string) get_permalink( $ancestor_id ) );
			}
		} elseif ( 'post' === $post->post_type ) {
			$blog = self::blog_item( $lang );
			if ( $blog ) {
				$items[] = $blog;
			}
			$category = self::primary_category( $post );
			if ( $category ) {
				$items[] = self::term_item( $category );
			}
		}
		$items[] = self::item( get_the_title( $post ), (string) get_permalink( $post ) );
		return $items;
	}

	/**
	 * Primary category: an explicit primary term when set (Yoast meta), else the first category of
	 * the post's language; "Uncategorized" is skipped when it is the only choice.
	 *
	 * @param \WP_Post $post Post.
	 * @return \WP_Term|null
	 */
	private static function primary_category( \WP_Post $post ) {
		$categories = get_the_category( $post->ID );
		if ( ! $categories ) {
			return null;
		}
		$primary = (int) get_post_meta( $post->ID, '_yoast_wpseo_primary_category', true );
		$lang    = Translations::get_post_language( $post );
		$default = (int) get_option( 'default_category' );
		$pick    = null;
		foreach ( $categories as $category ) {
			if ( $primary && (int) $category->term_id === $primary ) {
				return $category;
			}
			if ( null === $pick && (int) $category->term_id !== $default && Translations::get_term_language( $category ) === $lang ) {
				$pick = $category;
			}
		}
		return $pick;
	}
}
