<?php
/**
 * Mock implementations of the plugin API used by the theme. TEST FIXTURE ONLY.
 *
 * Business data mirrors config/mr-tu-business.json honestly: phone is verified (public),
 * address/hours/service area are NOT verified, so they return ''.
 *
 * @package MrTu\Theme\Tests
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Mocks the plugin's public API names on purpose.

/**
 * Current language (the demo site is English).
 *
 * @return string
 */
function mrtu_current_language() {
	return 'en';
}

/**
 * Default language.
 *
 * @return string
 */
function mrtu_default_language() {
	return 'vi';
}

/**
 * Language home URL.
 *
 * @param string $path Path.
 * @param string $lang Language.
 * @return string
 */
function mrtu_home_url( $path = '', $lang = null ) {
	return home_url( '/' . ltrim( (string) $path, '/' ) );
}

/**
 * Role registry (contract §2.3).
 *
 * @return array
 */
function mrtu_page_roles() {
	return array(
		'home'          => array( 'label' => 'Home', 'nav' => 'none', 'footer' => 'none', 'order' => 0 ),
		'pillar'        => array( 'label' => 'Motorbike rental', 'nav' => 'primary', 'footer' => 'rent', 'order' => 10 ),
		'fleet'         => array( 'label' => 'Bikes', 'nav' => 'primary', 'footer' => 'info', 'order' => 20 ),
		'pricing'       => array( 'label' => 'Prices', 'nav' => 'primary', 'footer' => 'info', 'order' => 30 ),
		'old-quarter'   => array( 'label' => 'Old Quarter', 'nav' => 'primary', 'footer' => 'rent', 'order' => 40 ),
		'guide-faq'     => array( 'label' => 'Guide & FAQ', 'nav' => 'primary', 'footer' => 'info', 'order' => 50 ),
		'short-term'    => array( 'label' => 'Daily & weekly', 'nav' => 'more', 'footer' => 'rent', 'order' => 60 ),
		'monthly'       => array( 'label' => 'Monthly rental', 'nav' => 'more', 'footer' => 'rent', 'order' => 70 ),
		'about'         => array( 'label' => 'About Mr Tu', 'nav' => 'more', 'footer' => 'brand', 'order' => 80 ),
		'blog'          => array( 'label' => 'Blog', 'nav' => 'more', 'footer' => 'info', 'order' => 90 ),
		'contact'       => array( 'label' => 'Contact', 'nav' => 'primary', 'footer' => 'brand', 'order' => 100 ),
		'privacy'       => array( 'label' => 'Privacy', 'nav' => 'none', 'footer' => 'legal', 'order' => 110 ),
		'terms'         => array( 'label' => 'Terms of use', 'nav' => 'none', 'footer' => 'legal', 'order' => 120 ),
		'rental-policy' => array( 'label' => 'Rental rules', 'nav' => 'none', 'footer' => 'legal', 'order' => 130 ),
	);
}

/**
 * Page ID holding a role.
 *
 * @param string $role Role.
 * @param string $lang Language.
 * @return int
 */
function mrtu_get_page_id_by_role( $role, $lang = null ) {
	static $map = null;
	if ( null === $map ) {
		$map   = array();
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'meta_key'       => '_mrtu_role', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'fields'         => 'ids',
			)
		);
		foreach ( $pages as $id ) {
			$map[ get_post_meta( $id, '_mrtu_role', true ) ] = (int) $id;
		}
	}
	return isset( $map[ $role ] ) ? $map[ $role ] : 0;
}

/**
 * Role permalink.
 *
 * @param string $role Role.
 * @param string $lang Language.
 * @return string
 */
function mrtu_role_url( $role, $lang = null ) {
	$id = mrtu_get_page_id_by_role( $role );
	return $id ? get_permalink( $id ) : '';
}

/**
 * Role of a post.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function mrtu_get_post_role( $post_id ) {
	return (string) get_post_meta( $post_id, '_mrtu_role', true );
}

/**
 * Public phone (verified in the seed file).
 *
 * @param string $format Format.
 * @return string
 */
function mrtu_phone( $format = 'display' ) {
	$formats = array(
		'display' => '0816 659 199',
		'e164'    => '+84816659199',
		'tel'     => 'tel:+84816659199',
	);
	return isset( $formats[ $format ] ) ? $formats[ $format ] : '';
}

/**
 * Business facts: unverified fields are not public.
 *
 * @param string $field       Field.
 * @param bool   $public_only Public only.
 * @return mixed
 */
function mrtu_business( $field = null, $public_only = true ) {
	$verified = array(
		'brand' => 'Mr Tu',
	);
	return isset( $verified[ $field ] ) ? $verified[ $field ] : '';
}

/**
 * Switcher links: English is current, other languages have no translation yet.
 *
 * @return array
 */
function mrtu_switcher_links() {
	$current = home_url( add_query_arg( array() ) );
	$langs   = array(
		'vi' => array( 'Tiếng Việt', 'vi-VN' ),
		'en' => array( 'English', 'en' ),
		'de' => array( 'Deutsch', 'de' ),
		'fr' => array( 'Français', 'fr' ),
		'es' => array( 'Español', 'es' ),
	);
	$links   = array();
	foreach ( $langs as $code => $meta ) {
		$is_current     = 'en' === $code;
		$links[ $code ] = array(
			'code'      => $code,
			'name'      => $meta[0],
			'hreflang'  => $meta[1],
			'url'       => $is_current ? $current : home_url( '/?mrtu_lang=' . $code ),
			'current'   => $is_current,
			'available' => $is_current || 'vi' === $code,
			'fallback'  => ! $is_current && 'vi' !== $code,
		);
	}
	return $links;
}

/**
 * Breadcrumb trail.
 *
 * @return array
 */
function mrtu_breadcrumb_trail() {
	$trail = array(
		array(
			'name' => 'Home',
			'url'  => home_url( '/' ),
		),
	);
	if ( is_singular( 'post' ) ) {
		$blog = (int) get_option( 'page_for_posts' );
		if ( $blog ) {
			$trail[] = array(
				'name' => get_the_title( $blog ),
				'url'  => get_permalink( $blog ),
			);
		}
		$cats = get_the_category();
		if ( $cats ) {
			$trail[] = array(
				'name' => $cats[0]->name,
				'url'  => get_category_link( $cats[0] ),
			);
		}
		$trail[] = array(
			'name' => single_post_title( '', false ),
			'url'  => '',
		);
	} elseif ( is_page() ) {
		foreach ( array_reverse( get_post_ancestors( get_queried_object_id() ) ) as $ancestor ) {
			$trail[] = array(
				'name' => get_the_title( $ancestor ),
				'url'  => get_permalink( $ancestor ),
			);
		}
		$trail[] = array(
			'name' => single_post_title( '', false ),
			'url'  => '',
		);
	} elseif ( is_home() ) {
		$trail[] = array(
			'name' => get_the_title( (int) get_option( 'page_for_posts' ) ),
			'url'  => '',
		);
	} elseif ( is_category() || is_tag() ) {
		$blog = (int) get_option( 'page_for_posts' );
		if ( $blog ) {
			$trail[] = array(
				'name' => get_the_title( $blog ),
				'url'  => get_permalink( $blog ),
			);
		}
		$trail[] = array(
			'name' => single_term_title( '', false ),
			'url'  => '',
		);
	} elseif ( is_search() ) {
		$trail[] = array(
			'name' => 'Search',
			'url'  => '',
		);
	} elseif ( is_404() ) {
		$trail[] = array(
			'name' => 'Page not found',
			'url'  => '',
		);
	}
	return $trail;
}

/**
 * Collects H2/H3 headings with stable ids.
 *
 * @param string $html Content HTML.
 * @return array{0: string, 1: array}
 */
function mrtu_mock_toc_parse( $html ) {
	$items = array();
	$used  = array();
	$html  = preg_replace_callback(
		'#<h([23])([^>]*)>(.*?)</h\1>#s',
		static function ( $m ) use ( &$items, &$used ) {
			$text = trim( wp_strip_all_tags( $m[3] ) );
			if ( preg_match( '/\bid="([^"]+)"/', $m[2], $idm ) ) {
				$id = $idm[1];
			} else {
				$base = sanitize_title( remove_accents( $text ) );
				$id   = $base;
				$n    = 2;
				while ( isset( $used[ $id ] ) ) {
					$id = $base . '-' . $n++;
				}
				$m[2] .= ' id="' . esc_attr( $id ) . '"';
			}
			$used[ $id ] = true;
			$items[]     = array(
				'id'    => $id,
				'text'  => $text,
				'level' => (int) $m[1],
			);
			return '<h' . $m[1] . $m[2] . '>' . $m[3] . '</h' . $m[1] . '>';
		},
		$html
	);
	return array( $html, $items );
}

/**
 * TOC items for a post.
 *
 * @param int|WP_Post|null $post Post.
 * @return array
 */
function mrtu_toc_items( $post = null ) {
	$post = get_post( $post );
	if ( ! $post || (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
		return array();
	}
	$parsed = mrtu_mock_toc_parse( do_blocks( $post->post_content ) );
	return $parsed[1];
}

/**
 * Inline TOC after the first H2 (contract §2.9 markup).
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! is_singular() || is_front_page() || ! in_the_loop() ) {
			return $content;
		}
		list( $content, $items ) = mrtu_mock_toc_parse( $content );
		if ( count( $items ) < 3 ) {
			return $content;
		}
		$list  = '';
		$open  = false;
		$count = 0;
		foreach ( $items as $item ) {
			if ( 2 === $item['level'] ) {
				if ( $open ) {
					$list .= '</ol></li>';
					$open  = false;
				} elseif ( $count ) {
					$list .= '</li>';
				}
				$list .= '<li class="mrtu-toc__item mrtu-toc__item--h2"><a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a>';
				++$count;
			} else {
				if ( ! $open ) {
					$list .= '<ol>';
					$open  = true;
				}
				$list .= '<li class="mrtu-toc__item mrtu-toc__item--h3"><a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a></li>';
			}
		}
		$list .= $open ? '</ol></li>' : '</li>';
		$toc   = '<details class="mrtu-toc" data-mrtu-toc><summary class="mrtu-toc__summary"><span class="mrtu-toc__title">On this page</span><span class="mrtu-toc__count">' . count( $items ) . '</span></summary><nav class="mrtu-toc__nav" aria-label="On this page"><ol class="mrtu-toc__list">' . $list . '</ol></nav></details>';
		$pos   = strpos( $content, '</h2>' );
		if ( false === $pos ) {
			return $toc . $content;
		}
		$after = strpos( $content, '</p>', $pos );
		$at    = false !== $after ? $after + 4 : $pos + 5;
		return substr( $content, 0, $at ) . $toc . substr( $content, $at );
	},
	12
);

/**
 * Dynamic blocks rendering the reference markup.
 */
add_action(
	'init',
	static function () {
		$blocks = array(
			'mrtu/fleet'         => 'fleet.html',
			'mrtu/pricing-table' => 'pricing.html',
			'mrtu/contact-card'  => 'contact-card.html',
			'mrtu/cta'           => 'cta.html',
		);
		foreach ( $blocks as $name => $file ) {
			register_block_type(
				$name,
				array(
					'render_callback' => static function () use ( $file ) {
						return (string) file_get_contents( __DIR__ . '/markup/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					},
				)
			);
		}
	}
);

/**
 * Quick Contact dock + minimal behaviour (the real plugin ships its own script).
 */
add_action(
	'wp_footer',
	static function () {
		echo file_get_contents( __DIR__ . '/markup/quick-contact.html' ); // phpcs:ignore
		echo "<script>(function(){var q=document.querySelector('[data-mrtu-qc]');if(!q)return;var t=q.querySelector('.mrtu-qc__toggle'),p=q.querySelector('.mrtu-qc__panel');t.addEventListener('click',function(){var o=t.getAttribute('aria-expanded')==='true';t.setAttribute('aria-expanded',o?'false':'true');p.hidden=o;});var av=document.querySelectorAll('[data-mrtu-qc-avoid]');if('IntersectionObserver'in window&&av.length){var seen=new Set();var io=new IntersectionObserver(function(es){es.forEach(function(e){e.isIntersecting?seen.add(e.target):seen.delete(e.target)});q.classList.toggle('is-hidden',seen.size>0)});av.forEach(function(a){io.observe(a)})}})();</script>"; // phpcs:ignore
	}
);
