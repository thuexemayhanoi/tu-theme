<?php
/**
 * Server-rendered business blocks (contract §2.11, docs/component-markup.md):
 * mrtu/pricing-table, mrtu/fleet, mrtu/contact-card, mrtu/cta.
 *
 * Each block lives in blocks/<name>/ (block.json apiVersion 3 + render.php). One shared
 * blocks/editor.js registers the editor UI without a build step. Front assets are enqueued only
 * when a block is present (has_block() on singular content + enqueue from the render callback).
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Block registration + renderers.
 */
final class Blocks {

	const NAMES = array( 'pricing-table', 'fleet', 'contact-card', 'cta' );

	/**
	 * Per-request instance counter (unique ids).
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_for_content' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_js_flag' ), 0 );
	}

	/**
	 * Registers assets and block types.
	 */
	public static function register() {
		$url = MRTU_ML_URL;
		$ver = MRTU_ML_VERSION;
		wp_register_style( 'mrtu-blocks', $url . 'assets/css/blocks.css', array(), $ver );
		wp_style_add_data( 'mrtu-blocks', 'path', MRTU_ML_DIR . 'assets/css/blocks.css' ); // Small file: core inlines it (one render-blocking request less).
		wp_register_script(
			'mrtu-pricing',
			$url . 'assets/js/pricing.js',
			array(),
			$ver,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_register_script(
			'mrtu-fleet',
			$url . 'assets/js/fleet.js',
			array(),
			$ver,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_register_script(
			'mrtu-blocks-editor',
			$url . 'blocks/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			$ver,
			true
		);
		wp_set_script_translations( 'mrtu-blocks-editor', 'mr-tu-multilang-seo', MRTU_ML_DIR . 'languages' );

		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		foreach ( self::NAMES as $name ) {
			register_block_type( dirname( __DIR__ ) . '/blocks/' . $name );
		}
	}

	/**
	 * Adds the "Mr Tu" block category.
	 *
	 * @param array $categories Categories.
	 * @return array
	 */
	public static function register_category( $categories ) {
		foreach ( (array) $categories as $category ) {
			if ( isset( $category['slug'] ) && 'mrtu' === $category['slug'] ) {
				return $categories;
			}
		}
		array_unshift(
			$categories,
			array(
				'slug'  => 'mrtu',
				'title' => __( 'Mr Tu', 'mr-tu-multilang-seo' ),
				'icon'  => null,
			)
		);
		return $categories;
	}

	/**
	 * Enqueues block assets in <head> when the singular content contains our blocks.
	 */
	public static function enqueue_for_content() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_post( get_queried_object_id() );
		if ( ! $post ) {
			return;
		}
		foreach ( self::NAMES as $name ) {
			if ( has_block( 'mrtu/' . $name, $post ) ) {
				self::enqueue_assets( $name );
			}
		}
	}

	/**
	 * Enqueues the assets of one block (styles print late in the footer when called after wp_head).
	 *
	 * @param string $name Block name without namespace.
	 */
	public static function enqueue_assets( $name ) {
		wp_enqueue_style( 'mrtu-blocks' );
		if ( 'pricing-table' === $name ) {
			wp_enqueue_script( 'mrtu-pricing' );
		} elseif ( 'fleet' === $name ) {
			wp_enqueue_script( 'mrtu-fleet' );
		}
	}

	/**
	 * Prints the one-line progressive-enhancement flag (`html.mrtu-js`).
	 */
	public static function print_js_flag() {
		if ( is_admin() ) {
			return;
		}
		wp_print_inline_script_tag( "document.documentElement.classList.add('mrtu-js');" );
	}

	/**
	 * Next per-request instance number.
	 *
	 * @return int
	 */
	public static function next_id() {
		return ++self::$instance;
	}

	/**
	 * Renders a block by name (used by render.php files and the admin preview).
	 *
	 * @param string $name       Block name without namespace.
	 * @param array  $attributes Attributes.
	 * @return string Escaped HTML.
	 */
	public static function render( $name, $attributes = array() ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		if ( ! in_array( $name, self::NAMES, true ) ) {
			return '';
		}
		self::enqueue_assets( $name );
		switch ( $name ) {
			case 'pricing-table':
				return self::render_pricing_table( $attributes );
			case 'fleet':
				return self::render_fleet( $attributes );
			case 'contact-card':
				return self::render_contact_card( $attributes );
			default:
				return self::render_cta( $attributes );
		}
	}

	/**
	 * Root element attributes: block supports (align, className) inside a block render, plain otherwise.
	 *
	 * @param array $extra Attributes (unescaped).
	 * @return string Escaped attribute string.
	 */
	private static function wrapper( $extra ) {
		if ( function_exists( 'get_block_wrapper_attributes' ) ) {
			return get_block_wrapper_attributes( $extra );
		}
		$parts = array();
		foreach ( $extra as $key => $value ) {
			$parts[] = esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
		return implode( ' ', $parts );
	}

	/*
	 * mrtu/pricing-table
	 * ------------------------------------------------------------------
	 */

	/**
	 * Price table with day/week/month tabs.
	 *
	 * @param array $attributes period (day|week|month), showDeposit (bool).
	 * @return string
	 */
	public static function render_pricing_table( $attributes ) {
		$attributes   = wp_parse_args(
			$attributes,
			array(
				'period'      => 'day',
				'showDeposit' => true,
			)
		);
		$default      = in_array( $attributes['period'], Pricing::PERIODS, true ) ? $attributes['period'] : 'day';
		$show_deposit = Business::flag( $attributes['showDeposit'] );
		$n            = self::next_id();
		$lang         = Business::lang();
		$vehicles     = Pricing::vehicles( array( 'lang' => $lang ) );
		$meta         = Pricing::meta();
		$labels       = Pricing::period_labels();

		$html  = '<section ' . self::wrapper(
			array(
				'class'             => 'mrtu-pricing',
				'data-mrtu-pricing' => '',
				'data-default'      => $default,
				'aria-label'        => __( 'Rental prices', 'mr-tu-multilang-seo' ),
			)
		) . '>';
		$html .= '<div class="mrtu-pricing__head">';
		$html .= '<p class="mrtu-pricing__meta">' . self::pricing_badge( $meta ) . self::pricing_updated( $meta, $lang ) . '</p>';

		if ( ! $vehicles ) {
			$html .= '</div><p class="mrtu-pricing__note mrtu-pricing__note--empty">' . esc_html( self::confirmation_notice( $lang, 'price' ) ) . '</p></section>';
			return $html;
		}

		$html .= '<div class="mrtu-pricing__tabs" role="tablist" aria-label="' . esc_attr__( 'Rental period', 'mr-tu-multilang-seo' ) . '">';
		foreach ( Pricing::PERIODS as $period ) {
			$selected = $period === $default;
			$html    .= sprintf(
				'<button type="button" class="mrtu-pricing__tab%1$s" role="tab" id="%2$s" aria-controls="%3$s" aria-selected="%4$s" tabindex="%5$s">%6$s</button>',
				$selected ? ' is-active' : '',
				esc_attr( 'mrtu-pt-' . $n . '-' . $period ),
				esc_attr( 'mrtu-pp-' . $n . '-' . $period ),
				$selected ? 'true' : 'false',
				$selected ? '0' : '-1',
				esc_html( $labels[ $period ]['tab'] )
			);
		}
		$html .= '</div></div>';

		$any_reference = false;
		$deposit_label = __( 'Deposit', 'mr-tu-multilang-seo' );
		foreach ( Pricing::PERIODS as $period ) {
			$selected = $period === $default;
			$html    .= sprintf(
				'<div class="mrtu-pricing__panel%1$s" role="tabpanel" id="%2$s" aria-labelledby="%3$s" data-period="%4$s" tabindex="0">',
				$selected ? ' is-active' : '',
				esc_attr( 'mrtu-pp-' . $n . '-' . $period ),
				esc_attr( 'mrtu-pt-' . $n . '-' . $period ),
				esc_attr( $period )
			);
			$html    .= '<table class="mrtu-pricing__table"><caption class="screen-reader-text">' . esc_html( $labels[ $period ]['caption'] ) . '</caption>';
			$html    .= '<thead><tr><th scope="col">' . esc_html__( 'Model', 'mr-tu-multilang-seo' ) . '</th><th scope="col">' . esc_html( $labels[ $period ]['column'] ) . '</th>';
			if ( $show_deposit ) {
				$html .= '<th scope="col">' . esc_html( $deposit_label ) . '</th>';
			}
			$html .= '</tr></thead><tbody>';
			foreach ( $vehicles as $vehicle ) {
				$state = Pricing::price_display( $vehicle, $period, $lang );
				if ( 'reference' === $state['state'] ) {
					$any_reference = true;
				}
				$html .= '<tr class="mrtu-pricing__row" data-category="' . esc_attr( $vehicle['category'] ) . '">';
				$html .= '<th scope="row"><span class="mrtu-pricing__model">' . esc_html( $vehicle['name'] ) . '</span> <span class="mrtu-pricing__type">' . esc_html( Pricing::category_label( $vehicle['category'] ) ) . '</span></th>';
				$html .= '<td data-label="' . esc_attr( $labels[ $period ]['column'] ) . '">' . Pricing::price_html( $vehicle, $period ) . '</td>';
				if ( $show_deposit ) {
					$deposit = Pricing::price_display( $vehicle, 'deposit', $lang );
					if ( 'reference' === $deposit['state'] ) {
						$any_reference = true;
					}
					$html .= '<td data-label="' . esc_attr( $deposit_label ) . '">' . Pricing::price_html( $vehicle, $period, 'deposit' ) . '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody></table></div>';
		}

		if ( $any_reference ) {
			$html .= '<p class="mrtu-pricing__note mrtu-pricing__note--reference"><span aria-hidden="true">* </span>' . esc_html__( 'Reference price: please confirm it with Mr Tu before booking.', 'mr-tu-multilang-seo' ) . '</p>';
		}
		if ( '' !== $meta['delivery_fee'] ) {
			/* translators: %s: delivery fee description entered by the owner. */
			$html .= '<p class="mrtu-pricing__note mrtu-pricing__note--delivery">' . esc_html( sprintf( __( 'Delivery: %s', 'mr-tu-multilang-seo' ), $meta['delivery_fee'] ) ) . '</p>';
		}
		$note  = $show_deposit
			? __( 'The deposit is separate from the rental price. Please confirm the price, deposit and documents with Mr Tu before you rent.', 'mr-tu-multilang-seo' )
			: __( 'Please confirm the price and documents with Mr Tu before you rent.', 'mr-tu-multilang-seo' );
		$html .= '<p class="mrtu-pricing__note">' . esc_html( $note ) . '</p>';
		$html .= '</section>';
		return $html;
	}

	/**
	 * Price-list status badge.
	 *
	 * @param array $meta Pricing meta.
	 * @return string
	 */
	private static function pricing_badge( $meta ) {
		if ( $meta['verified'] ) {
			return '<span class="mrtu-badge mrtu-badge--verified">' . esc_html__( 'Confirmed prices', 'mr-tu-multilang-seo' ) . '</span>';
		}
		if ( $meta['show_reference'] ) {
			return '<span class="mrtu-badge mrtu-badge--reference">' . esc_html__( 'Reference prices', 'mr-tu-multilang-seo' ) . '</span>';
		}
		return '<span class="mrtu-badge mrtu-badge--pending">' . esc_html__( 'Prices on request', 'mr-tu-multilang-seo' ) . '</span>';
	}

	/**
	 * "Updated dd/mm/yyyy" (only for a confirmed list with a review date).
	 *
	 * @param array  $meta Pricing meta.
	 * @param string $lang Language.
	 * @return string
	 */
	private static function pricing_updated( $meta, $lang ) {
		if ( ! $meta['verified'] || '' === $meta['reviewed_at'] ) {
			return '';
		}
		$timestamp = strtotime( $meta['reviewed_at'] . ' 12:00:00 UTC' );
		if ( ! $timestamp ) {
			return '';
		}
		$formats = array(
			'en' => 'j M Y',
			'de' => 'd.m.Y',
		);
		$date    = wp_date( isset( $formats[ $lang ] ) ? $formats[ $lang ] : 'd/m/Y', $timestamp, new \DateTimeZone( 'UTC' ) );
		/* translators: %s: date the prices were last checked. */
		return ' <span class="mrtu-pricing__updated">' . esc_html( sprintf( __( 'Updated %s', 'mr-tu-multilang-seo' ), $date ) ) . '</span>';
	}

	/*
	 * mrtu/fleet
	 * ------------------------------------------------------------------
	 */

	/**
	 * Vehicle cards with filter chips.
	 *
	 * @param array $attributes category (all|manual|scooter|50cc|electric), showPrices (bool).
	 * @return string
	 */
	public static function render_fleet( $attributes ) {
		$attributes  = wp_parse_args(
			$attributes,
			array(
				'category'   => 'all',
				'showPrices' => true,
			)
		);
		$category    = in_array( $attributes['category'], Pricing::CATEGORIES, true ) ? $attributes['category'] : 'all';
		$show_prices = Business::flag( $attributes['showPrices'] );
		$n           = self::next_id();
		$lang        = Business::lang();
		$vehicles    = Pricing::vehicles(
			array(
				'category' => 'all' === $category ? '' : $category,
				'lang'     => $lang,
			)
		);
		$present     = array();
		foreach ( $vehicles as $vehicle ) {
			$present[ $vehicle['category'] ] = true;
		}
		$categories = array_values( array_intersect( Pricing::CATEGORIES, array_keys( $present ) ) );

		/* translators: %d: number of vehicle models shown after filtering. */
		$count_template = __( 'Models shown: %d', 'mr-tu-multilang-seo' );

		$html = '<div ' . self::wrapper(
			array(
				'class'               => 'mrtu-fleet',
				'data-mrtu-fleet'     => '',
				'data-count-template' => $count_template,
			)
		) . '>';

		if ( ! $vehicles ) {
			return $html . '<p class="mrtu-fleet__empty">' . esc_html( self::confirmation_notice( $lang, 'fleet' ) ) . '</p></div>';
		}

		if ( 'all' === $category && count( $categories ) >= 2 ) {
			$list_id = 'mrtu-fl-' . $n;
			$html   .= '<div class="mrtu-fleet__filters" role="group" aria-label="' . esc_attr__( 'Vehicle type', 'mr-tu-multilang-seo' ) . '">';
			$html   .= '<button type="button" class="mrtu-fleet__filter" aria-pressed="true" aria-controls="' . esc_attr( $list_id ) . '" data-filter="all">' . esc_html__( 'All', 'mr-tu-multilang-seo' ) . '</button>';
			foreach ( $categories as $cat ) {
				$html .= '<button type="button" class="mrtu-fleet__filter" aria-pressed="false" aria-controls="' . esc_attr( $list_id ) . '" data-filter="' . esc_attr( $cat ) . '">' . esc_html( Pricing::category_label( $cat, true ) ) . '</button>';
			}
			$html .= '</div>';
			$html .= '<p class="mrtu-fleet__status screen-reader-text" aria-live="polite" aria-atomic="true"></p>';
		}

		$html .= '<ul class="mrtu-fleet__list" role="list" id="' . esc_attr( 'mrtu-fl-' . $n ) . '">';
		foreach ( $vehicles as $vehicle ) {
			$html .= self::vehicle_card( $vehicle, $show_prices, $lang );
		}
		$html       .= '</ul>';
		$pricing_url = $show_prices ? Business::role_url( 'pricing', $lang ) : '';
		if ( '' !== $pricing_url && ! self::is_current_url( $pricing_url ) ) {
			$html .= '<p class="mrtu-fleet__more"><a href="' . esc_url( $pricing_url ) . '">' . esc_html__( 'All prices and deposits', 'mr-tu-multilang-seo' ) . '</a></p>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * One vehicle card.
	 *
	 * @param array  $vehicle     Public vehicle record.
	 * @param bool   $show_prices Show prices.
	 * @param string $lang        Language.
	 * @return string
	 */
	private static function vehicle_card( $vehicle, $show_prices, $lang ) {
		$media = '';
		if ( $vehicle['image_id'] ) {
			$media = wp_get_attachment_image(
				$vehicle['image_id'],
				'medium_large',
				false,
				array(
					'class'    => 'mrtu-vehicle__img',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);
		}
		if ( '' === $media ) {
			$name = $vehicle['name'];
			if ( '' !== $vehicle['brand'] && 0 === stripos( $name, $vehicle['brand'] . ' ' ) ) {
				$name = substr( $name, strlen( $vehicle['brand'] ) + 1 );
			}
			$media = '<span class="mrtu-plate" aria-hidden="true"><span class="mrtu-plate__code">' . esc_html( Pricing::plate_code( $vehicle['category'] ) ) . '</span><span class="mrtu-plate__name">' . esc_html( $name ) . '</span></span>';
		}

		$html  = '<li class="mrtu-vehicle" data-category="' . esc_attr( $vehicle['category'] ) . '"><article class="mrtu-vehicle__card">';
		$html .= '<div class="mrtu-vehicle__media">' . $media . '</div>';
		$html .= '<div class="mrtu-vehicle__body">';
		$html .= '<h3 class="mrtu-vehicle__name">' . esc_html( $vehicle['name'] ) . '</h3>';
		$html .= '<p class="mrtu-vehicle__type">' . esc_html( Pricing::category_label( $vehicle['category'] ) ) . '</p>';
		if ( 'on_request' === $vehicle['status'] ) {
			$html .= '<p class="mrtu-vehicle__status"><span class="mrtu-badge mrtu-badge--reference">' . esc_html__( 'Ask for availability', 'mr-tu-multilang-seo' ) . '</span></p>';
		}
		if ( '' !== $vehicle['desc'] ) {
			$html .= '<p class="mrtu-vehicle__desc">' . esc_html( $vehicle['desc'] ) . '</p>';
		}
		if ( $show_prices ) {
			$labels   = Pricing::period_labels();
			$all_none = true;
			foreach ( Pricing::PERIODS as $period ) {
				$display = Pricing::price_display( $vehicle, $period, $lang );
				if ( 'on_request' !== $display['state'] ) {
					$all_none = false;
				}
			}
			$html .= '<dl class="mrtu-vehicle__prices">';
			if ( $all_none ) {
				$html .= '<div class="mrtu-vehicle__price mrtu-vehicle__price--all"><dt>' . esc_html__( 'Price', 'mr-tu-multilang-seo' ) . '</dt><dd>' . Pricing::price_html( $vehicle, 'day' ) . '</dd></div>';
			} else {
				foreach ( Pricing::PERIODS as $period ) {
					$html .= '<div class="mrtu-vehicle__price"><dt>' . esc_html( $labels[ $period ]['short'] ) . '</dt><dd>' . Pricing::price_html( $vehicle, $period ) . '</dd></div>';
				}
			}
			$html .= '</dl>';
		}
		$html .= '</div></article></li>';
		return $html;
	}

	/*
	 * mrtu/contact-card
	 * ------------------------------------------------------------------
	 */

	/**
	 * Contact card (verified facts only).
	 *
	 * @param array $attributes showHours (bool), showAddress (bool).
	 * @return string
	 */
	public static function render_contact_card( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			array(
				'showHours'   => true,
				'showAddress' => true,
			)
		);
		$lang       = Business::lang();
		$public     = Business::public_data();
		$channels   = Business::contact_channels( true, $lang );
		$business   = Business::get_business();
		$items      = array();

		if ( $public['phone'] ) {
			$items['phone'] = array( __( 'Phone', 'mr-tu-multilang-seo' ), '<a href="' . esc_url( $public['phone']['tel'] ) . '">' . esc_html( $public['phone']['display'] ) . '</a>' );
		}
		foreach ( array( 'whatsapp', 'zalo' ) as $channel ) {
			if ( isset( $channels[ $channel ] ) ) {
				$items[ $channel ] = array( $channels[ $channel ]['label'], '<a href="' . esc_url( $channels[ $channel ]['url'] ) . '">' . esc_html( $channels[ $channel ]['value'] ) . '</a>' );
			}
		}
		if ( '' !== $public['email'] ) {
			$items['email'] = array( __( 'Email', 'mr-tu-multilang-seo' ), '<a href="' . esc_url( 'mailto:' . $public['email'] ) . '">' . esc_html( $public['email'] ) . '</a>' );
		}
		if ( $public['address'] && Business::flag( $attributes['showAddress'] ) ) {
			$value = esc_html( $public['address']['formatted'] );
			if ( isset( $channels['directions'] ) ) {
				$value .= ' <a class="mrtu-contact-card__directions" href="' . esc_url( $channels['directions']['url'] ) . '">' . esc_html( $channels['directions']['label'] ) . '</a>';
			}
			$items['address'] = array( __( 'Address', 'mr-tu-multilang-seo' ), $value );
		}
		if ( ! $public['address'] ) {
			$items['area'] = array( __( 'Area', 'mr-tu-multilang-seo' ), esc_html( Business::service_area_label( $lang ) ) );
		}
		if ( $public['hours'] && Business::flag( $attributes['showHours'] ) ) {
			$items['hours'] = array( __( 'Hours', 'mr-tu-multilang-seo' ), esc_html( $public['hours']['text'] ) );
		}

		$html = '<div ' . self::wrapper(
			array(
				'class'              => 'mrtu-contact-card',
				'data-mrtu-qc-avoid' => '',
			)
		) . '><dl class="mrtu-contact-card__list">';
		foreach ( $items as $key => $item ) {
			$html .= '<div class="mrtu-contact-card__item mrtu-contact-card__item--' . esc_attr( $key ) . '"><dt>' . esc_html( $item[0] ) . '</dt><dd>' . $item[1] . '</dd></div>';
		}
		$html .= '</dl>';
		if ( ! $business['service_model']['verified'] || 'appointment' === $business['service_model']['value'] ) {
			$html .= '<p class="mrtu-contact-card__note">' . esc_html__( 'Please call ahead to arrange the hand-over time and place.', 'mr-tu-multilang-seo' ) . '</p>';
		}
		$html .= '</div>';
		return $html;
	}

	/*
	 * mrtu/cta
	 * ------------------------------------------------------------------
	 */

	/**
	 * Call / contact call-to-action ('' when no channel is publishable).
	 *
	 * @param array $attributes variant (call|contact).
	 * @return string
	 */
	public static function render_cta( $attributes ) {
		$attributes = wp_parse_args( $attributes, array( 'variant' => 'call' ) );
		$variant    = 'contact' === $attributes['variant'] ? 'contact' : 'call';
		$lang       = Business::lang();
		$channels   = Business::contact_channels( true, $lang );
		$buttons    = array();

		if ( isset( $channels['phone'] ) ) {
			$buttons['call'] = array(
				'url'   => $channels['phone']['url'],
				'icon'  => 'phone',
				/* translators: %s: phone number. */
				'label' => sprintf( __( 'Call %s', 'mr-tu-multilang-seo' ), $channels['phone']['value'] ),
			);
		}
		if ( isset( $channels['contact'] ) && ! self::is_current_url( $channels['contact']['url'] ) ) {
			$buttons['contact'] = array(
				'url'   => $channels['contact']['url'],
				'icon'  => 'arrow',
				'label' => __( 'Contact page', 'mr-tu-multilang-seo' ),
			);
		}
		if ( ! $buttons ) {
			return '';
		}
		if ( 'contact' === $variant && isset( $buttons['contact'] ) ) {
			$buttons = array_merge( array( 'contact' => $buttons['contact'] ), $buttons );
		}

		$html  = '<div ' . self::wrapper( array( 'class' => 'mrtu-cta mrtu-cta--' . $variant ) ) . '>';
		$first = true;
		foreach ( $buttons as $key => $button ) {
			if ( $first ) {
				$class = 'call' === $key ? 'mrtu-button--action' : 'mrtu-button--accent';
			} else {
				$class = 'mrtu-button--ghost';
			}
			$first = false;
			$html .= '<a class="mrtu-button ' . esc_attr( $class ) . '" href="' . esc_url( $button['url'] ) . '">' . self::icon( $button['icon'] ) . '<span>' . esc_html( $button['label'] ) . '</span></a>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * Whether a URL is the current singular page (avoid self-links).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_current_url( $url ) {
		if ( ! is_singular() ) {
			return false;
		}
		$current = get_permalink( get_queried_object_id() );
		return $current && untrailingslashit( $current ) === untrailingslashit( $url );
	}

	/*
	 * Icons (simple consistent stroke icons; no brand logos)
	 * ------------------------------------------------------------------
	 */

	/**
	 * Inline SVG icon (static markup, safe to print).
	 *
	 * @param string $name  phone|message|chat|pin|mail|arrow|close.
	 * @param string $extra Extra class.
	 * @return string
	 */
	public static function icon( $name, $extra = '' ) {
		$paths = array(
			'phone'   => '<path d="M6.6 3.5h2.8l1.5 4.2-2 1.3a11.5 11.5 0 0 0 6.1 6.1l1.3-2 4.2 1.5v2.8a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.7a2 2 0 0 1 2-2.2z"/>',
			'message' => '<path d="M20.5 11.5a8.5 8.5 0 0 1-12.4 7.6L3.5 20.5l1.4-4.4a8.5 8.5 0 1 1 15.6-4.6z"/>',
			'chat'    => '<path d="M4 4.5h16v11H9.5L4 20z"/><path d="M8 8.5h8M8 11.5h5"/>',
			'pin'     => '<path d="M12 21s-7-6.2-7-11.2a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.8" r="2.6"/>',
			'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
			'arrow'   => '<path d="M4.5 12h15M13.5 6l6 6-6 6"/>',
			'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		$class = 'mrtu-icon mrtu-icon--' . $name . ( '' !== $extra ? ' ' . $extra : '' );
		return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}

	/**
	 * Honest localized contact messages when no verified fleet or price is published.
	 *
	 * @param string $lang Language code.
	 * @param string $kind Message kind: price or fleet.
	 * @return string
	 */
	private static function confirmation_notice( $lang, $kind ) {
		$messages = array(
			'vi' => array(
				'price' => 'Liên hệ Mr Tú để xác nhận báo giá theo xe và thời gian thuê của bạn.',
				'fleet' => 'Liên hệ Mr Tú để xác nhận xe có sẵn cho ngày nhận và trả dự kiến.',
			),
			'en' => array(
				'price' => 'Contact Mr Tú to confirm a quote for your vehicle and rental dates.',
				'fleet' => 'Contact Mr Tú to confirm which vehicles are available for your dates.',
			),
			'de' => array(
				'price' => 'Kontaktiere Mr Tú, um ein Angebot für Fahrzeug und Mietzeitraum zu bestätigen.',
				'fleet' => 'Kontaktiere Mr Tú, um verfügbare Fahrzeuge für deinen Mietzeitraum zu erfragen.',
			),
			'fr' => array(
				'price' => 'Contactez Mr Tú pour confirmer un devis selon le véhicule et vos dates.',
				'fleet' => 'Contactez Mr Tú pour confirmer les véhicules disponibles à vos dates.',
			),
			'es' => array(
				'price' => 'Contacta con Mr Tú para confirmar un presupuesto según la moto y las fechas.',
				'fleet' => 'Contacta con Mr Tú para confirmar las motos disponibles en tus fechas.',
			),
		);
		$lang = isset( $messages[ $lang ] ) ? $lang : 'vi';
		return $messages[ $lang ][ $kind ];
	}
}
