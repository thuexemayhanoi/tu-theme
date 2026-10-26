<?php
/**
 * Template helpers: titles, dates, reading time, related content, contact targets.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Title of the posts page (or a generic "Blog").
 *
 * @return string
 */
function mr_tu_blog_title() {
	$page_id = (int) get_option( 'page_for_posts' );
	if ( $page_id ) {
		return get_the_title( $page_id );
	}
	return __( 'Blog', 'mr-tu' );
}

/**
 * Manual excerpt used as a lead paragraph (auto-generated excerpts are not leads).
 *
 * @param int|WP_Post|null $post Post.
 * @return string Plain text.
 */
function mr_tu_lead( $post = null ) {
	$post = get_post( $post );
	if ( ! $post || ! has_excerpt( $post ) ) {
		return '';
	}
	return trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );
}

/**
 * Estimated reading time in minutes (word count works for Vietnamese, German, French, Spanish).
 *
 * @param int|WP_Post|null $post Post.
 * @return int
 */
function mr_tu_reading_minutes( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return 1;
	}
	$text  = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
	$count = is_array( $words ) ? count( $words ) : 0;
	return max( 1, (int) round( $count / 220 ) );
}

/**
 * Prints a <time> element.
 *
 * @param string           $which published|modified.
 * @param int|WP_Post|null $post  Post.
 */
function mr_tu_the_time( $which = 'published', $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return;
	}
	$modified = 'modified' === $which;
	printf(
		'<time datetime="%1$s">%2$s</time>',
		esc_attr( $modified ? get_the_modified_date( DATE_W3C, $post ) : get_the_date( DATE_W3C, $post ) ),
		esc_html( $modified ? get_the_modified_date( '', $post ) : get_the_date( '', $post ) )
	);
}

/**
 * First category of a post.
 *
 * @param int|WP_Post|null $post Post.
 * @return WP_Term|null
 */
function mr_tu_primary_category( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return null;
	}
	$terms = get_the_category( $post->ID );
	if ( empty( $terms ) ) {
		return null;
	}
	foreach ( $terms as $term ) {
		if ( (int) get_option( 'default_category' ) !== (int) $term->term_id ) {
			return $term;
		}
	}
	return $terms[0];
}

/**
 * Whether the current singular view gets the sticky desktop table of contents.
 *
 * @return bool
 */
function mr_tu_has_sticky_toc() {
	if ( ! is_singular() || is_front_page() ) {
		return false;
	}
	return count( mr_tu_toc_items( get_queried_object_id() ) ) >= 3;
}

/**
 * Primary hero call to action: fleet, then pricing, then the pillar page.
 *
 * @return array{url: string, role: string}
 */
function mr_tu_primary_cta() {
	foreach ( array( 'fleet', 'pricing', 'pillar' ) as $role ) {
		$url = mr_tu_role_url( $role );
		if ( '' !== $url ) {
			return array(
				'url'  => $url,
				'role' => $role,
			);
		}
	}
	return array(
		'url'  => '',
		'role' => '',
	);
}

/**
 * Contact targets for CTA blocks: contact page URL and public phone.
 *
 * @return array{contact: string, tel: string, phone: string}
 */
function mr_tu_contact_targets() {
	return array(
		'contact' => mr_tu_role_url( 'contact' ),
		'tel'     => mr_tu_phone( 'tel' ),
		'phone'   => mr_tu_phone( 'display' ),
	);
}

/**
 * City name in the language of the current page (a proper noun that must never leak Vietnamese
 * diacritics into other languages, independent of translation files).
 *
 * @return string
 */
function mr_tu_city() {
	$lang  = function_exists( 'mrtu_current_language' ) ? (string) mrtu_current_language() : substr( (string) get_locale(), 0, 2 );
	$names = array(
		'vi' => 'Hà Nội',
		'en' => 'Hanoi',
		'de' => 'Hanoi',
		'fr' => 'Hanoï',
		'es' => 'Hanói',
	);
	$names = apply_filters( 'mr_tu_city_names', $names );
	return isset( $names[ $lang ] ) ? $names[ $lang ] : $names['en'];
}

/**
 * Area text shown when the street address is not public.
 *
 * @return string
 */
function mr_tu_area_text() {
	/* translators: 1: "Old Quarter" (translated), 2: city name in the current language. */
	return sprintf( _x( '%1$s, %2$s', 'service area', 'mr-tu' ), __( 'Old Quarter', 'mr-tu' ), mr_tu_city() );
}

/**
 * Public address as one line ('' when not verified).
 *
 * @return string
 */
function mr_tu_public_address() {
	$address = mr_tu_business( 'address' );
	if ( is_array( $address ) ) {
		$parts = array();
		foreach ( array( 'street', 'ward', 'city' ) as $key ) {
			if ( ! empty( $address[ $key ] ) && is_string( $address[ $key ] ) ) {
				$parts[] = $address[ $key ];
			}
		}
		return implode( ', ', $parts );
	}
	return is_string( $address ) ? $address : '';
}

/**
 * Public opening hours as one line ('' when not verified).
 *
 * @return string
 */
function mr_tu_public_hours() {
	$hours = mr_tu_business( 'hours' );
	if ( is_string( $hours ) ) {
		return $hours;
	}
	if ( ! is_array( $hours ) || empty( $hours['opens'] ) || empty( $hours['closes'] ) ) {
		return '';
	}
	$days = isset( $hours['days'] ) ? (array) $hours['days'] : array();
	if ( 7 === count( $days ) ) {
		/* translators: 1: opening time (HH:MM), 2: closing time (HH:MM). */
		return sprintf( __( 'Every day, %1$s–%2$s', 'mr-tu' ), $hours['opens'], $hours['closes'] );
	}
	/* translators: 1: opening time (HH:MM), 2: closing time (HH:MM). */
	return sprintf( __( 'Open %1$s–%2$s', 'mr-tu' ), $hours['opens'], $hours['closes'] );
}

/**
 * Related core pages: other roles in the same language, else other top-level pages.
 *
 * @param int $post_id Current post ID.
 * @param int $count   Number of pages.
 * @return WP_Post[]
 */
function mr_tu_related_pages( $post_id, $count = 3 ) {
	$current = mr_tu_post_role( $post_id );
	$pages   = array();

	if ( mr_tu_has_plugin() ) {
		$roles = apply_filters( 'mr_tu_related_roles', array( 'pillar', 'fleet', 'pricing', 'old-quarter', 'monthly', 'short-term', 'guide-faq', 'about' ) );
		foreach ( $roles as $role ) {
			if ( $role === $current ) {
				continue;
			}
			$id = mr_tu_role_page_id( $role );
			if ( $id && $id !== (int) $post_id && 'publish' === get_post_status( $id ) ) {
				$pages[] = get_post( $id );
			}
			if ( count( $pages ) >= $count ) {
				break;
			}
		}
		return $pages;
	}

	$exclude = array_filter( array( (int) $post_id, (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ), (int) get_option( 'wp_page_for_privacy_policy' ) ) );
	$query   = new WP_Query(
		array(
			'post_type'              => 'page',
			'post_status'            => 'publish',
			'post_parent'            => 0,
			'posts_per_page'         => $count,
			'post__not_in'           => $exclude, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- At most four IDs.
			'orderby'                => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);
	return $query->posts;
}

/**
 * Related posts: same category first, same language when the plugin is active.
 *
 * @param int $post_id Current post ID.
 * @param int $count   Number of posts.
 * @return WP_Post[]
 */
function mr_tu_related_posts( $post_id, $count = 3 ) {
	$args     = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'post__not_in'        => array( (int) $post_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- One ID.
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	$category = mr_tu_primary_category( $post_id );
	if ( $category ) {
		$args['cat'] = (int) $category->term_id;
	}
	$query = new WP_Query( mr_tu_with_language( $args ) );
	$posts = $query->posts;

	if ( count( $posts ) < $count && $category ) {
		unset( $args['cat'] );
		$args['posts_per_page'] = $count - count( $posts );
		$args['post__not_in']   = array_merge( array( (int) $post_id ), wp_list_pluck( $posts, 'ID' ) ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- A handful of IDs.
		$more                   = new WP_Query( mr_tu_with_language( $args ) );
		$posts                  = array_merge( $posts, $more->posts );
	}
	return $posts;
}

/**
 * Prints archive pagination with plate-styled numbers.
 */
function mr_tu_pagination() {
	the_posts_pagination(
		array(
			'mid_size'           => 1,
			'prev_text'          => '<span class="screen-reader-text">' . esc_html__( 'Previous page', 'mr-tu' ) . '</span>' . mr_tu_icon( 'arrow', 'mrtu-icon--flip' ),
			'next_text'          => '<span class="screen-reader-text">' . esc_html__( 'Next page', 'mr-tu' ) . '</span>' . mr_tu_icon( 'arrow' ),
			'before_page_number' => '<span class="screen-reader-text">' . esc_html__( 'Page', 'mr-tu' ) . ' </span>',
			'class'              => 'mrtu-pagination',
			'aria_label'         => __( 'Posts pages', 'mr-tu' ),
		)
	);
}

/**
 * Localised kilometre-stone place label for the hero object.
 *
 * @return string
 */
function mr_tu_stone_place() {
	/* translators: Short place name printed on the hero kilometre stone (uppercase in the design, max ~12 characters). */
	return __( 'Old Quarter', 'mr-tu' );
}
