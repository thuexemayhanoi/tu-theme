<?php
/**
 * JSON-LD @graph (contract §2.8 + amendment 7): Organization, one WebSite (root `#website`), WebPage
 * (inLanguage, isPartOf, breadcrumb), BreadcrumbList, BlogPosting on posts, AutoRental only when the
 * owner enabled it AND address, phone and hours are verified, FAQPage (as WebPage type) only on the
 * `guide-faq` page from visible core/details blocks. Never ratings or reviews.
 *
 * Printed in standalone mode only; in compat mode Yoast's graph stays and Yoast_Compat sets inLanguage.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Structured data.
 */
final class Schema {

	/**
	 * Maximum FAQ entries marked up.
	 */
	const FAQ_LIMIT = 50;

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'setup' ), 0 );
	}

	/**
	 * Prints the graph on standalone HTML pages.
	 */
	public static function setup() {
		if ( Seo_Head::is_active() ) {
			add_action( 'wp_head', array( __CLASS__, 'output' ), 5 );
		}
	}

	/**
	 * Prints the <script type="application/ld+json"> block (nothing on noindex pages).
	 */
	public static function output() {
		$json = self::json();
		if ( '' === $json ) {
			return;
		}
		echo '<script type="application/ld+json" class="mrtu-schema">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG (no "</script>" possible).
	}

	/**
	 * JSON of the graph ('' when empty).
	 *
	 * @param mixed $context Context array, post, term or null (current request).
	 * @return string
	 */
	public static function json( $context = null ) {
		$graph = self::graph( $context );
		if ( ! $graph ) {
			return '';
		}
		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => array_values( $graph ),
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
		);
		return is_string( $json ) ? $json : '';
	}

	/**
	 * Graph nodes (contract `mrtu_schema_graph()`), empty on noindex requests (search, 404, attachments …).
	 *
	 * @param mixed $context Context array, post, term or null.
	 * @return array[]
	 */
	public static function graph( $context = null ) {
		$ctx = is_array( $context ) ? $context : Seo::context( $context );
		if ( Seo::is_noindex( $ctx ) || 'other' === $ctx['type'] ) {
			/** This filter is documented below. */
			return (array) apply_filters( 'mrtu_schema_graph', array(), $ctx );
		}
		$url   = Seo::canonical( $ctx );
		$lang  = Languages::get( $ctx['lang'] );
		$bcp47 = $lang ? $lang['hreflang'] : $ctx['lang'];
		$graph = array();

		$graph['organization'] = self::organization();
		$graph['website']      = self::website();

		$business = Seo::local_business_enabled() ? self::business() : null;
		if ( $business ) {
			$graph['business'] = $business;
		}

		$breadcrumb = '' !== $url ? self::breadcrumb( $url ) : null;
		if ( $breadcrumb ) {
			$graph['breadcrumb'] = $breadcrumb;
		}

		if ( '' !== $url ) {
			$page = array(
				'@type'      => 'WebPage',
				'@id'        => $url . '#webpage',
				'url'        => $url,
				'name'       => Seo::title( $ctx ),
				'inLanguage' => $bcp47,
				'isPartOf'   => array( '@id' => self::website_id() ),
			);
			$desc = Seo::description( $ctx );
			if ( '' !== $desc ) {
				$page['description'] = $desc;
			}
			if ( $breadcrumb ) {
				$page['breadcrumb'] = array( '@id' => $breadcrumb['@id'] );
			}
			if ( $business ) {
				$page['about'] = array( '@id' => $business['@id'] );
			}
			if ( $ctx['post'] instanceof \WP_Post ) {
				$page['datePublished'] = Seo_Head::iso_date( $ctx['post'], false );
				$page['dateModified']  = Seo_Head::iso_date( $ctx['post'], true );
				$image                 = Seo_Head::image( $ctx );
				if ( $image ) {
					$page['primaryImageOfPage'] = self::image_object( $image, $url . '#primaryimage' );
				}
				if ( 'singular' === $ctx['type'] && (bool) Seo::setting( 'faq_schema', true ) && 'guide-faq' === Roles::get_post_role( $ctx['post']->ID ) ) {
					$faq = self::faq_items( $ctx['post'] );
					if ( $faq ) {
						$page['@type']      = array( 'WebPage', 'FAQPage' );
						$page['mainEntity'] = $faq;
					}
				}
			}
			$graph['webpage'] = $page;

			if ( 'singular' === $ctx['type'] && $ctx['post'] instanceof \WP_Post && 'post' === $ctx['post']->post_type ) {
				$graph['article'] = self::article( $ctx['post'], $url, $bcp47, $ctx );
			}
		}

		/**
		 * Filters the JSON-LD graph nodes.
		 *
		 * @param array[] $graph   Nodes keyed by role (organization, website, business, breadcrumb, webpage, article).
		 * @param array   $context Request context (see Seo::context()).
		 */
		$graph = apply_filters( 'mrtu_schema_graph', $graph, $ctx );
		return is_array( $graph ) ? array_filter( $graph, 'is_array' ) : array();
	}

	/*
	 * Nodes
	 */

	/**
	 * Root URL of the site (all languages share one WebSite / Organization).
	 *
	 * @return string
	 */
	public static function root() {
		return home_url( '/' );
	}

	/**
	 * `@id` of the Organization node.
	 *
	 * @return string
	 */
	public static function organization_id() {
		return self::root() . '#organization';
	}

	/**
	 * `@id` of the WebSite node.
	 *
	 * @return string
	 */
	public static function website_id() {
		return self::root() . '#website';
	}

	/**
	 * `@id` of the AutoRental node.
	 *
	 * @return string
	 */
	public static function business_id() {
		return self::root() . '#business';
	}

	/**
	 * Organization: name, url, logo (custom logo), verified phone / email / address / map link only.
	 *
	 * @return array
	 */
	public static function organization() {
		$brand = function_exists( 'mrtu_business' ) ? (string) mrtu_business( 'brand' ) : '';
		$node  = array(
			'@type' => 'Organization',
			'@id'   => self::organization_id(),
			'name'  => '' !== $brand ? $brand : Seo::site_name(),
			'url'   => self::root(),
		);
		$logo  = self::logo();
		if ( $logo ) {
			$node['logo']  = $logo;
			$node['image'] = array( '@id' => $logo['@id'] );
		}
		self::add_contact( $node );
		return $node;
	}

	/**
	 * One WebSite node for the whole domain (site names only work at `/`).
	 *
	 * @return array
	 */
	public static function website() {
		$languages = array();
		foreach ( Languages::all( true ) as $lang ) {
			$languages[] = $lang['hreflang'];
		}
		return array(
			'@type'      => 'WebSite',
			'@id'        => self::website_id(),
			'url'        => self::root(),
			'name'       => Seo::site_name(),
			'publisher'  => array( '@id' => self::organization_id() ),
			'inLanguage' => $languages,
		);
	}

	/**
	 * AutoRental (closest schema.org type to a motorbike rental): only with verified NAP + hours.
	 *
	 * @return array|null
	 */
	public static function business() {
		if ( ! function_exists( 'mrtu_business' ) ) {
			return null;
		}
		$address = mrtu_business( 'address' );
		$phone   = mrtu_business( 'phone' );
		$hours   = mrtu_business( 'hours' );
		if ( ! is_array( $address ) || ! is_array( $phone ) || ! is_array( $hours ) ) {
			return null;
		}
		$display = (string) mrtu_business( 'display_name' );
		$brand   = (string) mrtu_business( 'brand' );
		$node    = array(
			'@type'              => 'AutoRental',
			'@id'                => self::business_id(),
			'name'               => '' !== $display ? $display : ( '' !== $brand ? $brand : Seo::site_name() ),
			'url'                => self::root(),
			'telephone'          => $phone['e164'],
			'address'            => self::postal_address( $address ),
			'currenciesAccepted' => 'VND',
			'parentOrganization' => array( '@id' => self::organization_id() ),
		);
		if ( '' !== $brand && $node['name'] !== $brand ) {
			$node['alternateName'] = $brand;
		}
		$spec = self::opening_hours( $hours );
		if ( $spec ) {
			$node['openingHoursSpecification'] = $spec;
		}
		$email = (string) mrtu_business( 'email' );
		if ( '' !== $email ) {
			$node['email'] = $email;
		}
		$area = (string) mrtu_business( 'service_area' );
		if ( '' !== $area ) {
			$node['areaServed'] = $area;
		}
		$maps = (string) mrtu_business( 'maps_url' );
		if ( '' !== $maps ) {
			$node['hasMap'] = $maps;
			$node['sameAs'] = array( $maps );
		}
		$logo = self::logo();
		if ( $logo ) {
			$node['image'] = array( '@id' => $logo['@id'] );
		}
		$offers = self::offers();
		if ( $offers ) {
			$node['makesOffer'] = $offers;
		}
		return $node;
	}

	/**
	 * BreadcrumbList from mrtu_breadcrumb_trail() (two or more items).
	 *
	 * @param string $url Page URL.
	 * @return array|null
	 */
	public static function breadcrumb( $url ) {
		$trail = Breadcrumbs::trail();
		if ( count( $trail ) < 2 ) {
			return null;
		}
		$items = array();
		$last  = count( $trail ) - 1;
		foreach ( $trail as $i => $crumb ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => (string) $crumb['name'],
			);
			if ( '' !== (string) $crumb['url'] && ( $i < $last || (string) $crumb['url'] !== $url ) ) {
				$item['item'] = (string) $crumb['url'];
			}
			$items[] = $item;
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $url . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	/**
	 * BlogPosting for a single post (author = the organisation; no invented persons).
	 *
	 * @param \WP_Post $post  Post.
	 * @param string   $url   Canonical URL.
	 * @param string   $bcp47 Language tag.
	 * @param array    $ctx   Context.
	 * @return array
	 */
	public static function article( \WP_Post $post, $url, $bcp47, array $ctx ) {
		$node = array(
			'@type'            => 'BlogPosting',
			'@id'              => $url . '#article',
			'headline'         => Seo::trim_text( Seo::post_title_text( $post ), 110 ),
			'url'              => $url,
			'datePublished'    => Seo_Head::iso_date( $post, false ),
			'dateModified'     => Seo_Head::iso_date( $post, true ),
			'author'           => array( '@id' => self::organization_id() ),
			'publisher'        => array( '@id' => self::organization_id() ),
			'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
			'isPartOf'         => array( '@id' => $url . '#webpage' ),
			'inLanguage'       => $bcp47,
		);
		$desc = Seo::description( $ctx );
		if ( '' !== $desc ) {
			$node['description'] = $desc;
		}
		$image = Seo_Head::image( $ctx );
		if ( $image ) {
			$node['image'] = array( '@id' => $url . '#primaryimage' );
		}
		$sections = array();
		$default  = (int) get_option( 'default_category' );
		foreach ( (array) get_the_category( $post->ID ) as $category ) {
			if ( $category instanceof \WP_Term && $default !== (int) $category->term_id ) {
				$sections[] = Seo::plain_text( $category->name );
			}
		}
		if ( $sections ) {
			$node['articleSection'] = $sections;
		}
		return $node;
	}

	/*
	 * Pieces
	 */

	/**
	 * ImageObject for the custom logo (theme mod), or null.
	 *
	 * @return array|null
	 */
	public static function logo() {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( ! $logo_id ) {
			return null;
		}
		$src = wp_get_attachment_image_src( $logo_id, 'full' );
		if ( ! $src || empty( $src[0] ) ) {
			return null;
		}
		return self::image_object(
			array(
				'url'    => (string) $src[0],
				'width'  => isset( $src[1] ) ? (int) $src[1] : 0,
				'height' => isset( $src[2] ) ? (int) $src[2] : 0,
				'alt'    => Seo::site_name(),
			),
			self::root() . '#logo'
		);
	}

	/**
	 * ImageObject node.
	 *
	 * @param array  $image [ url, width, height, alt ].
	 * @param string $id    `@id`.
	 * @return array
	 */
	private static function image_object( array $image, $id ) {
		$node = array(
			'@type'      => 'ImageObject',
			'@id'        => $id,
			'url'        => $image['url'],
			'contentUrl' => $image['url'],
		);
		if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ) {
			$node['width']  = (int) $image['width'];
			$node['height'] = (int) $image['height'];
		}
		if ( ! empty( $image['alt'] ) ) {
			$node['caption'] = $image['alt'];
		}
		return $node;
	}

	/**
	 * Adds verified contact facts to an Organization node.
	 *
	 * @param array $node Node (by reference).
	 */
	private static function add_contact( array &$node ) {
		if ( ! function_exists( 'mrtu_business' ) ) {
			return;
		}
		$phone = mrtu_business( 'phone' );
		if ( is_array( $phone ) && ! empty( $phone['e164'] ) ) {
			$node['telephone'] = $phone['e164'];
		}
		$email = (string) mrtu_business( 'email' );
		if ( '' !== $email ) {
			$node['email'] = $email;
		}
		$address = mrtu_business( 'address' );
		if ( is_array( $address ) ) {
			$node['address'] = self::postal_address( $address );
		}
		$maps = (string) mrtu_business( 'maps_url' );
		if ( '' !== $maps ) {
			$node['sameAs'] = array( $maps );
		}
	}

	/**
	 * PostalAddress from the public (verified) address record. No district: Hanoi has two tiers since 2025.
	 *
	 * @param array $address Public address record.
	 * @return array
	 */
	public static function postal_address( array $address ) {
		$street = (string) $address['street'];
		if ( ! empty( $address['ward'] ) ) {
			$street .= ', ' . $address['ward'];
		}
		$node = array(
			'@type'          => 'PostalAddress',
			'streetAddress'  => $street,
			'addressCountry' => ! empty( $address['country'] ) ? $address['country'] : 'VN',
		);
		if ( ! empty( $address['city'] ) ) {
			$node['addressLocality'] = $address['city'];
		}
		if ( ! empty( $address['postal_code'] ) ) {
			$node['postalCode'] = $address['postal_code'];
		}
		return $node;
	}

	/**
	 * OpeningHoursSpecification list from the public hours record.
	 *
	 * @param array $hours [ days => [Mo..Su], opens, closes ].
	 * @return array[]
	 */
	public static function opening_hours( array $hours ) {
		$names = array(
			'Mo' => 'Monday',
			'Tu' => 'Tuesday',
			'We' => 'Wednesday',
			'Th' => 'Thursday',
			'Fr' => 'Friday',
			'Sa' => 'Saturday',
			'Su' => 'Sunday',
		);
		$days  = array();
		foreach ( (array) $hours['days'] as $day ) {
			if ( isset( $names[ $day ] ) ) {
				$days[] = $names[ $day ];
			}
		}
		if ( ! $days || empty( $hours['opens'] ) || empty( $hours['closes'] ) ) {
			return array();
		}
		return array(
			array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $days,
				'opens'     => $hours['opens'],
				'closes'    => $hours['closes'],
			),
		);
	}

	/**
	 * Offers for vehicles whose prices are verified (never reference / unverified prices).
	 *
	 * @return array[]
	 */
	public static function offers() {
		if ( ! function_exists( 'mrtu_vehicles' ) ) {
			return array();
		}
		$units  = array(
			'day'   => 'DAY',
			'week'  => 'WEE',
			'month' => 'MON',
		);
		$offers = array();
		foreach ( (array) mrtu_vehicles() as $vehicle ) {
			if ( empty( $vehicle['verified'] ) || empty( $vehicle['name'] ) || 'available' !== $vehicle['status'] ) {
				continue;
			}
			foreach ( $units as $period => $unit ) {
				$range = isset( $vehicle['price'][ $period ] ) && is_array( $vehicle['price'][ $period ] ) ? $vehicle['price'][ $period ] : null;
				if ( ! $range || empty( $range[0] ) ) {
					continue;
				}
				$spec = array(
					'@type'             => 'UnitPriceSpecification',
					'price'             => (int) $range[0],
					'priceCurrency'     => 'VND',
					'unitCode'          => $unit,
					'referenceQuantity' => array(
						'@type'    => 'QuantitativeValue',
						'value'    => 1,
						'unitCode' => $unit,
					),
				);
				if ( isset( $range[1] ) && (int) $range[1] > (int) $range[0] ) {
					$spec['minPrice'] = (int) $range[0];
					$spec['maxPrice'] = (int) $range[1];
					unset( $spec['price'] );
				}
				$offers[] = array(
					'@type'              => 'Offer',
					'itemOffered'        => array(
						'@type' => 'Service',
						'name'  => (string) $vehicle['name'],
					),
					'priceSpecification' => $spec,
				);
			}
		}
		return $offers;
	}

	/**
	 * Question/Answer pairs from core/details blocks (summary = question, inner blocks = answer).
	 *
	 * @param \WP_Post $post Post.
	 * @return array[]
	 */
	public static function faq_items( \WP_Post $post ) {
		if ( ! has_blocks( $post->post_content ) ) {
			return array();
		}
		$items = array();
		self::collect_details( parse_blocks( $post->post_content ), $items );
		return array_slice( $items, 0, self::FAQ_LIMIT );
	}

	/**
	 * Walks the block tree for core/details blocks.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param array $items  Collected Question nodes (by reference).
	 */
	private static function collect_details( array $blocks, array &$items ) {
		foreach ( $blocks as $block ) {
			if ( count( $items ) >= self::FAQ_LIMIT ) {
				return;
			}
			if ( empty( $block['blockName'] ) ) {
				continue;
			}
			if ( 'core/details' === $block['blockName'] ) {
				$html = render_block( $block );
				if ( ! preg_match( '~<summary[^>]*>(.*?)</summary>~is', $html, $match ) ) {
					continue;
				}
				$question = Seo::plain_text( $match[1] );
				$answer   = Seo::plain_text( preg_replace( '~<summary[^>]*>.*?</summary>~is', '', $html ) );
				if ( '' === $question || '' === $answer ) {
					continue;
				}
				$items[] = array(
					'@type'          => 'Question',
					'name'           => $question,
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $answer,
					),
				);
				continue;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::collect_details( $block['innerBlocks'], $items );
			}
		}
	}
}
