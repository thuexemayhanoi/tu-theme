<?php
/**
 * Prices + vehicles (contract §2.7): normalisation/validation of `mrtu_pricing`, the public
 * vehicle list and the price display rules.
 *
 * Display rule for one price (or deposit) of one vehicle:
 * - hidden vehicle, unknown vehicle or no amount for the period → "on_request";
 * - price list confirmed (global switch) AND the vehicle's own price confirmed → "verified";
 * - otherwise, with mrtu_ml_settings['show_reference_prices'] → "reference" (with label);
 * - otherwise → "on_request" ("Price on request" + call link).
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Pricing data + display.
 */
final class Pricing {

	const CATEGORIES   = array( 'manual', 'scooter', '50cc', 'electric' );
	const STATUSES     = array( 'available', 'on_request', 'hidden' );
	const PERIODS      = array( 'day', 'week', 'month' );
	const MAX_VEHICLES = 50;
	const MAX_AMOUNT   = 1000000000;

	/**
	 * Wires hooks (none needed at runtime: data is read lazily through Business).
	 */
	public static function init() {
		add_action( 'mrtu_pricing_updated', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Clears caches after a pricing change.
	 */
	public static function flush() {
		Business::flush_cache();
	}

	/**
	 * Empty pricing record (shape mirrors config/mr-tu-business.json → pricing).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'currency'         => 'VND',
			'verified'         => false,
			'reviewed_at'      => '',
			'reference_source' => '',
			'delivery_fee'     => array(
				'value'    => '',
				'verified' => false,
				'note'     => '',
			),
			'vehicles'         => array(),
			'updated_at'       => 0,
			'updated_by'       => 0,
		);
	}

	/**
	 * Empty vehicle record.
	 *
	 * @return array
	 */
	public static function vehicle_defaults() {
		return array(
			'id'        => '',
			'name'      => '',
			'brand'     => '',
			'category'  => 'scooter',
			'status'    => 'available',
			'verified'  => false,
			'price'     => array(
				'day'   => null,
				'week'  => null,
				'month' => null,
			),
			'deposit'   => null,
			'image_id'  => 0,
			'desc_i18n' => array_fill_keys( Business::LANGS, '' ),
			'note'      => '',
		);
	}

	/**
	 * Normalises + validates a pricing record.
	 *
	 * @param mixed $input        Raw input.
	 * @param array $errors       Receives [ 'field.path' => message ] (vehicles: "vehicles.{index}.{field}").
	 * @param bool  $keep_invalid Keep sanitised invalid values (form redisplay) instead of resetting them.
	 * @return array
	 */
	public static function normalize( $input, &$errors = array(), $keep_invalid = false ) {
		$errors = array();
		$in     = is_array( $input ) ? $input : array();
		$out    = self::defaults();

		$currency = strtoupper( Business::text( Business::get( $in, 'currency', 'VND' ), 10 ) );
		if ( '' !== $currency && 'VND' !== $currency ) {
			$errors['currency'] = __( 'Only prices in VND are supported.', 'mr-tu-multilang-seo' );
		}

		$out['verified'] = Business::flag( Business::get( $in, 'verified', false ) );

		$reviewed = Business::text( Business::get( $in, 'reviewed_at' ), 20 );
		if ( '' !== $reviewed ) {
			$valid = preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $reviewed, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
			if ( ! $valid ) {
				$errors['reviewed_at'] = __( 'Use a valid date (YYYY-MM-DD).', 'mr-tu-multilang-seo' );
				$reviewed              = $keep_invalid ? $reviewed : '';
			}
		}
		$out['reviewed_at']      = $reviewed;
		$out['reference_source'] = Business::note( Business::get( $in, 'reference_source' ), 300 );

		$fee                 = Business::rec( $in, 'delivery_fee' );
		$out['delivery_fee'] = array(
			'value'    => Business::text( Business::get( $fee, 'value' ) ),
			'verified' => Business::flag( Business::get( $fee, 'verified', false ) ),
			'note'     => Business::note( Business::get( $fee, 'note' ) ),
		);
		if ( $out['delivery_fee']['verified'] && '' === $out['delivery_fee']['value'] ) {
			$errors['delivery_fee.value'] = __( 'Describe the delivery fee before marking it as verified.', 'mr-tu-multilang-seo' );
		}

		$list = Business::get( $in, 'vehicles', array() );
		if ( ! is_array( $list ) ) {
			$errors['vehicles'] = __( 'The vehicle list is not valid.', 'mr-tu-multilang-seo' );
			$list               = array();
		}
		$list = array_values( $list );
		if ( count( $list ) > self::MAX_VEHICLES ) {
			/* translators: %d: maximum number of vehicles. */
			$errors['vehicles'] = sprintf( __( 'At most %d vehicles are supported.', 'mr-tu-multilang-seo' ), self::MAX_VEHICLES );
			$list               = array_slice( $list, 0, self::MAX_VEHICLES );
		}

		$seen = array();
		foreach ( $list as $i => $raw ) {
			$p   = 'vehicles.' . $i . '.';
			$raw = is_array( $raw ) ? $raw : array();
			$v   = self::vehicle_defaults();

			$v['name'] = Business::text( Business::get( $raw, 'name' ), 80 );
			if ( '' === $v['name'] ) {
				$errors[ $p . 'name' ] = __( 'Enter the model name.', 'mr-tu-multilang-seo' );
			}

			$id_raw = Business::text( Business::get( $raw, 'id' ), 60 );
			$id     = sanitize_key( $id_raw );
			if ( '' === $id && '' !== $v['name'] ) {
				$id = sanitize_key( sanitize_title( remove_accents( $v['name'] ) ) );
			}
			if ( '' === $id ) {
				$errors[ $p . 'id' ] = __( 'Enter a short ID using letters, numbers and dashes.', 'mr-tu-multilang-seo' );
			} elseif ( isset( $seen[ $id ] ) ) {
				/* translators: %s: vehicle ID. */
				$errors[ $p . 'id' ] = sprintf( __( 'The ID “%s” is used by another vehicle.', 'mr-tu-multilang-seo' ), $id );
			}
			$seen[ $id ] = true;
			$v['id']     = $id;
			$v['brand']  = Business::text( Business::get( $raw, 'brand' ), 40 );

			$category = sanitize_key( Business::text( Business::get( $raw, 'category', 'scooter' ), 20 ) );
			if ( ! in_array( $category, self::CATEGORIES, true ) ) {
				$errors[ $p . 'category' ] = __( 'Choose a valid vehicle type.', 'mr-tu-multilang-seo' );
				$category                  = $keep_invalid ? $category : 'scooter';
			}
			$v['category'] = $category;

			$status = sanitize_key( Business::text( Business::get( $raw, 'status', 'available' ), 20 ) );
			if ( ! in_array( $status, self::STATUSES, true ) ) {
				$errors[ $p . 'status' ] = __( 'Choose a valid status.', 'mr-tu-multilang-seo' );
				$status                  = $keep_invalid ? $status : 'hidden';
			}
			$v['status']   = $status;
			$v['verified'] = Business::flag( Business::get( $raw, 'verified', false ) );

			$prices = Business::rec( $raw, 'price' );
			foreach ( self::PERIODS as $period ) {
				$v['price'][ $period ] = self::range( Business::get( $prices, $period, null ), $p . 'price.' . $period, $errors, $keep_invalid );
			}
			$v['deposit']  = self::range( Business::get( $raw, 'deposit', null ), $p . 'deposit', $errors, $keep_invalid );
			$v['image_id'] = absint( Business::get( $raw, 'image_id', 0 ) );

			$desc = Business::rec( $raw, 'desc_i18n' );
			foreach ( Business::LANGS as $lang ) {
				$v['desc_i18n'][ $lang ] = Business::note( Business::get( $desc, $lang ), 1000 );
			}
			$v['note'] = Business::note( Business::get( $raw, 'note' ) );

			$out['vehicles'][] = $v;
		}

		$out['updated_at'] = absint( Business::get( $in, 'updated_at', 0 ) );
		$out['updated_by'] = absint( Business::get( $in, 'updated_by', 0 ) );

		return $out;
	}

	/**
	 * Normalises a price range: null | [min,max] | {min,max} | scalar → null|[int,int].
	 *
	 * @param mixed  $value        Raw value.
	 * @param string $key          Error key.
	 * @param array  $errors       Errors (by reference).
	 * @param bool   $keep_invalid Keep invalid raw text as {min,max}.
	 * @return array|null
	 */
	private static function range( $value, $key, &$errors, $keep_invalid ) {
		if ( null === $value || '' === $value || false === $value || array() === $value ) {
			return null;
		}
		if ( is_array( $value ) ) {
			if ( array_key_exists( 'min', $value ) || array_key_exists( 'max', $value ) ) {
				$min = Business::get( $value, 'min' );
				$max = Business::get( $value, 'max' );
			} else {
				$values = array_values( $value );
				$min    = isset( $values[0] ) ? $values[0] : '';
				$max    = isset( $values[1] ) ? $values[1] : '';
			}
		} else {
			$min = $value;
			$max = '';
		}
		$min_i = self::parse_amount( $min );
		$max_i = self::parse_amount( $max );
		if ( null === $min_i && null === $max_i ) {
			return null;
		}
		$raw = array(
			'min' => is_scalar( $min ) ? sanitize_text_field( (string) $min ) : '',
			'max' => is_scalar( $max ) ? sanitize_text_field( (string) $max ) : '',
		);
		if ( false === $min_i || false === $max_i ) {
			$errors[ $key ] = __( 'Use whole numbers of 0 or more in VND, for example 150000.', 'mr-tu-multilang-seo' );
			return $keep_invalid ? $raw : null;
		}
		if ( null === $min_i ) {
			$min_i = $max_i;
		}
		if ( null === $max_i ) {
			$max_i = $min_i;
		}
		if ( $min_i > $max_i ) {
			$errors[ $key ] = __( 'The “from” amount must not be higher than the “to” amount.', 'mr-tu-multilang-seo' );
			return $keep_invalid ? $raw : null;
		}
		return array( $min_i, $max_i );
	}

	/**
	 * Parses an amount: int ≥ 0 (≤ 1 billion), null when empty, false when invalid.
	 *
	 * @param mixed $value Value.
	 * @return int|null|false
	 */
	private static function parse_amount( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( is_bool( $value ) || is_array( $value ) || is_object( $value ) ) {
			return false;
		}
		if ( is_int( $value ) ) {
			return ( $value >= 0 && $value <= self::MAX_AMOUNT ) ? $value : false;
		}
		if ( is_float( $value ) ) {
			return ( $value >= 0 && floor( $value ) === $value && $value <= self::MAX_AMOUNT ) ? (int) $value : false;
		}
		$text = preg_replace( '/[\s\x{00A0}\x{202F}]+/u', '', (string) $value );
		if ( '' === $text ) {
			return null;
		}
		if ( ! preg_match( '/^\d{1,10}$/', $text ) ) {
			return false;
		}
		$amount = (int) $text;
		return $amount <= self::MAX_AMOUNT ? $amount : false;
	}

	/*
	 * Public reading
	 * ------------------------------------------------------------------
	 */

	/**
	 * Vehicles behind mrtu_vehicles().
	 *
	 * @param array $args include_hidden (false), category ('' | all | manual | scooter | 50cc | electric),
	 *                    lang (null = current), context ('view' | 'edit': edit keeps admin notes + own verified flag).
	 * @return array[]
	 */
	public static function vehicles( $args = array() ) {
		$args     = wp_parse_args(
			(array) $args,
			array(
				'include_hidden' => false,
				'category'       => '',
				'lang'           => null,
				'context'        => 'view',
			)
		);
		$lang     = Business::lang( $args['lang'] );
		$category = (string) $args['category'];
		$pricing  = Business::get_pricing();
		$out      = array();
		foreach ( $pricing['vehicles'] as $vehicle ) {
			if ( 'hidden' === $vehicle['status'] && ! $args['include_hidden'] ) {
				continue;
			}
			if ( '' !== $category && 'all' !== $category && $category !== $vehicle['category'] ) {
				continue;
			}
			$vehicle['desc'] = isset( $vehicle['desc_i18n'][ $lang ] ) ? $vehicle['desc_i18n'][ $lang ] : '';
			if ( 'edit' !== $args['context'] ) {
				$vehicle['verified'] = self::is_verified( $vehicle );
				$vehicle['note']     = '';
			}
			$out[] = $vehicle;
		}
		return $out;
	}

	/**
	 * Stored vehicle by id.
	 *
	 * @param string $id Vehicle id.
	 * @return array|null
	 */
	public static function find_vehicle( $id ) {
		$id = sanitize_key( (string) $id );
		foreach ( Business::get_pricing()['vehicles'] as $vehicle ) {
			if ( $vehicle['id'] === $id ) {
				return $vehicle;
			}
		}
		return null;
	}

	/**
	 * Resolves a vehicle argument (id or array) to the STORED record (arrays are never trusted).
	 *
	 * @param string|array $vehicle Vehicle id or record.
	 * @return array|null
	 */
	private static function resolve( $vehicle ) {
		if ( is_array( $vehicle ) ) {
			return isset( $vehicle['id'] ) ? self::find_vehicle( $vehicle['id'] ) : null;
		}
		return is_scalar( $vehicle ) ? self::find_vehicle( (string) $vehicle ) : null;
	}

	/**
	 * Whether a stored vehicle's prices count as confirmed (global switch AND own switch).
	 *
	 * @param array $vehicle Stored vehicle.
	 * @return bool
	 */
	public static function is_verified( $vehicle ) {
		$pricing = Business::get_pricing();
		return ! empty( $pricing['verified'] ) && ! empty( $vehicle['verified'] );
	}

	/**
	 * The show_reference_prices setting (lives in mrtu_ml_settings, default false).
	 *
	 * @return bool
	 */
	public static function show_reference() {
		$settings = get_option( 'mrtu_ml_settings', array() );
		return is_array( $settings ) && ! empty( $settings['show_reference_prices'] );
	}

	/**
	 * Price display data behind mrtu_price_display().
	 *
	 * @param string|array $vehicle Vehicle id or record.
	 * @param string       $period  day|week|month|deposit.
	 * @param string|null  $lang    Language code (null = current).
	 * @return array{text:string,state:string,note:string,range:array|null}
	 */
	public static function price_display( $vehicle, $period, $lang = null ) {
		$lang       = Business::lang( $lang );
		$record     = self::resolve( $vehicle );
		$is_deposit = 'deposit' === $period;
		$range      = null;
		if ( $record && 'hidden' !== $record['status'] ) {
			if ( $is_deposit ) {
				$range = $record['deposit'];
			} elseif ( in_array( $period, self::PERIODS, true ) ) {
				$range = $record['price'][ $period ];
			}
		}
		$state = 'on_request';
		if ( is_array( $range ) ) {
			if ( self::is_verified( $record ) ) {
				$state = 'verified';
			} elseif ( self::show_reference() ) {
				$state = 'reference';
			}
		}

		$switched = Business::switch_language( $lang );
		if ( 'on_request' === $state ) {
			$text  = $is_deposit ? __( 'On request', 'mr-tu-multilang-seo' ) : __( 'Price on request', 'mr-tu-multilang-seo' );
			$note  = '';
			$range = null;
		} else {
			$text = self::format_vnd( $range, $lang );
			$note = 'reference' === $state ? __( 'reference price, confirm before booking', 'mr-tu-multilang-seo' ) : '';
		}
		if ( $switched ) {
			restore_previous_locale();
		}
		return array(
			'text'  => $text,
			'state' => $state,
			'note'  => $note,
			'range' => $range,
		);
	}

	/**
	 * Escaped price markup (component-markup.md → Price).
	 *
	 * @param string|array $vehicle Vehicle id or record.
	 * @param string       $period  day|week|month.
	 * @param string       $which   price|deposit.
	 * @return string
	 */
	public static function price_html( $vehicle, $period, $which = 'price' ) {
		$display = self::price_display( $vehicle, 'deposit' === $which ? 'deposit' : $period );
		if ( 'verified' === $display['state'] ) {
			return '<span class="mrtu-price mrtu-price--verified">' . esc_html( $display['text'] ) . '</span>';
		}
		if ( 'reference' === $display['state'] ) {
			return '<span class="mrtu-price mrtu-price--reference">' . esc_html( $display['text'] )
				. '<span class="mrtu-price__flag" aria-hidden="true">*</span>'
				. '<span class="screen-reader-text">(' . esc_html( $display['note'] ) . ')</span></span>';
		}
		$channels = Business::contact_channels( true );
		if ( isset( $channels['phone'] ) ) {
			return '<a class="mrtu-price mrtu-price--on-request" href="' . esc_url( $channels['phone']['url'] ) . '">' . esc_html( $display['text'] ) . '</a>';
		}
		return '<span class="mrtu-price mrtu-price--on-request">' . esc_html( $display['text'] ) . '</span>';
	}

	/**
	 * VND formatting per language. Ranges use an en dash; the currency sign appears once.
	 *
	 * Uses U+00A0 (no-break space) before "₫" and U+202F (narrow no-break space) as the French
	 * thousands separator.
	 *
	 * @param int|string|array $amount Amount or [min,max] / {min,max}.
	 * @param string|null      $lang   Language code (null = current).
	 * @return string
	 */
	public static function format_vnd( $amount, $lang = null ) {
		$lang = Business::lang( $lang );
		if ( is_array( $amount ) ) {
			if ( array_key_exists( 'min', $amount ) || array_key_exists( 'max', $amount ) ) {
				$min = (int) Business::get( $amount, 'min', 0 );
				$max = (int) Business::get( $amount, 'max', $min );
			} else {
				$values = array_values( $amount );
				$min    = isset( $values[0] ) ? (int) $values[0] : 0;
				$max    = isset( $values[1] ) ? (int) $values[1] : $min;
			}
		} else {
			$min = is_numeric( $amount ) ? (int) $amount : 0;
			$max = $min;
		}
		$min = max( 0, $min );
		$max = max( 0, $max );
		if ( $min > $max ) {
			list( $min, $max ) = array( $max, $min );
		}

		$nbsp = "\u{00A0}";
		$dash = "\u{2013}";
		if ( 'en' === $lang ) {
			$number = number_format( $min, 0, '.', ',' );
			if ( $max !== $min ) {
				$number .= $dash . number_format( $max, 0, '.', ',' );
			}
			return "\u{20AB}" . $number;
		}
		$separator = 'fr' === $lang ? "\u{202F}" : '.';
		$number    = number_format( $min, 0, ',', $separator );
		if ( $max !== $min ) {
			$number .= $dash . number_format( $max, 0, ',', $separator );
		}
		return $number . $nbsp . "\u{20AB}";
	}

	/**
	 * Pricing meta behind mrtu_pricing_meta().
	 *
	 * @return array{verified:bool,reviewed_at:string,show_reference:bool,currency:string,delivery_fee:string}
	 */
	public static function meta() {
		$pricing = Business::get_pricing();
		return array(
			'verified'       => (bool) $pricing['verified'],
			'reviewed_at'    => (string) $pricing['reviewed_at'],
			'show_reference' => self::show_reference(),
			'currency'       => 'VND',
			'delivery_fee'   => $pricing['delivery_fee']['verified'] ? (string) $pricing['delivery_fee']['value'] : '',
		);
	}

	/*
	 * Labels
	 * ------------------------------------------------------------------
	 */

	/**
	 * Vehicle type label.
	 *
	 * @param string $category Category.
	 * @param bool   $plural   Plural form (filter chips).
	 * @return string
	 */
	public static function category_label( $category, $plural = false ) {
		if ( $plural ) {
			$labels = array(
				'manual'   => _x( 'Semi-automatic', 'vehicle type filter (plural)', 'mr-tu-multilang-seo' ),
				'scooter'  => _x( 'Scooters', 'vehicle type filter (plural)', 'mr-tu-multilang-seo' ),
				'50cc'     => _x( '50cc', 'vehicle type filter (plural)', 'mr-tu-multilang-seo' ),
				'electric' => _x( 'Electric', 'vehicle type filter (plural)', 'mr-tu-multilang-seo' ),
			);
		} else {
			$labels = array(
				'manual'   => _x( 'Semi-automatic', 'vehicle type', 'mr-tu-multilang-seo' ),
				'scooter'  => _x( 'Scooter', 'vehicle type', 'mr-tu-multilang-seo' ),
				'50cc'     => _x( '50cc scooter', 'vehicle type', 'mr-tu-multilang-seo' ),
				'electric' => _x( 'Electric', 'vehicle type', 'mr-tu-multilang-seo' ),
			);
		}
		return isset( $labels[ $category ] ) ? $labels[ $category ] : '';
	}

	/**
	 * Decorative plate code per type (aria-hidden on the site).
	 *
	 * @param string $category Category.
	 * @return string
	 */
	public static function plate_code( $category ) {
		$labels = array(
			'manual'   => _x( 'SEMI-AUTO', 'decorative licence-plate label, uppercase', 'mr-tu-multilang-seo' ),
			'scooter'  => _x( 'SCOOTER', 'decorative licence-plate label, uppercase', 'mr-tu-multilang-seo' ),
			'50cc'     => _x( '50CC', 'decorative licence-plate label, uppercase', 'mr-tu-multilang-seo' ),
			'electric' => _x( 'ELECTRIC', 'decorative licence-plate label, uppercase', 'mr-tu-multilang-seo' ),
		);
		return isset( $labels[ $category ] ) ? $labels[ $category ] : '';
	}

	/**
	 * Vehicle status label (admin).
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			'available'  => __( 'Available', 'mr-tu-multilang-seo' ),
			'on_request' => __( 'Ask for availability', 'mr-tu-multilang-seo' ),
			'hidden'     => __( 'Hidden from the website', 'mr-tu-multilang-seo' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Period labels: tab, caption, column header, short (fleet card).
	 *
	 * @return array
	 */
	public static function period_labels() {
		return array(
			'day'   => array(
				'tab'     => __( 'Daily', 'mr-tu-multilang-seo' ),
				'caption' => __( 'Daily rental prices', 'mr-tu-multilang-seo' ),
				'column'  => __( 'Price / day', 'mr-tu-multilang-seo' ),
				'short'   => _x( 'Day', 'rental period', 'mr-tu-multilang-seo' ),
			),
			'week'  => array(
				'tab'     => __( 'Weekly', 'mr-tu-multilang-seo' ),
				'caption' => __( 'Weekly rental prices', 'mr-tu-multilang-seo' ),
				'column'  => __( 'Price / week', 'mr-tu-multilang-seo' ),
				'short'   => _x( 'Week', 'rental period', 'mr-tu-multilang-seo' ),
			),
			'month' => array(
				'tab'     => __( 'Monthly', 'mr-tu-multilang-seo' ),
				'caption' => __( 'Monthly rental prices', 'mr-tu-multilang-seo' ),
				'column'  => __( 'Price / month', 'mr-tu-multilang-seo' ),
				'short'   => _x( 'Month', 'rental period', 'mr-tu-multilang-seo' ),
			),
		);
	}
}
