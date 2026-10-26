<?php
/**
 * Business data store (contract §2.7): NAP, opening hours, contact channels, and the shared
 * persistence layer for the pricing/vehicle data (normalisation, validation, snapshots,
 * export/import, restore).
 *
 * Options:
 * - `mrtu_business`         NAP record, mirrors config/mr-tu-business.json → business (autoload yes).
 * - `mrtu_pricing`          prices + vehicles, mirrors → pricing (autoload yes). Normalised by Pricing.
 * - `mrtu_business_history` last 20 snapshots {id,time,user_id,type,data} (autoload no).
 *
 * Every field carries a `verified` flag. Public accessors never return unverified data.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * NAP + persistence.
 */
final class Business {

	const OPTION           = 'mrtu_business';
	const PRICING_OPTION   = 'mrtu_pricing';
	const HISTORY_OPTION   = 'mrtu_business_history';
	const HISTORY_LIMIT    = 20;
	const SCHEMA_VERSION   = 1;
	const IMPORT_MAX_BYTES = 204800;
	const E164_PATTERN     = '/^\+84\d{9,10}$/';
	const LANGS            = array( 'vi', 'en', 'de', 'fr', 'es' );
	const DAYS             = array( 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su' );
	const SERVICE_MODELS   = array( 'appointment', 'storefront' );
	const HISTORY_TYPES    = array( 'business', 'pricing', 'import', 'restore' );

	/**
	 * Per-request cache of normalised option values and derived data.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Wires hooks.
	 */
	public static function init() {
		// Self-heal: installs that activated the plugin before this module existed get their seed too.
		add_action( 'admin_init', array( __CLASS__, 'maybe_seed' ) );
		foreach ( array( self::OPTION, self::PRICING_OPTION, 'mrtu_ml_settings' ) as $option ) {
			add_action( 'add_option_' . $option, array( __CLASS__, 'flush_cache' ) );
			add_action( 'update_option_' . $option, array( __CLASS__, 'flush_cache' ) );
			add_action( 'delete_option_' . $option, array( __CLASS__, 'flush_cache' ) );
		}
	}

	/**
	 * Activation: seed the options when they are empty. Never overwrites existing data.
	 */
	public static function activate() {
		self::maybe_seed();
	}

	/**
	 * Clears the per-request caches.
	 */
	public static function flush_cache() {
		self::$cache = array();
	}

	/*
	 * Seeding
	 * ------------------------------------------------------------------
	 */

	/**
	 * Seeds `mrtu_business` / `mrtu_pricing` from the seed file when (and only when) they hold no data.
	 * Without a seed file nothing is written: readers fall back to the empty defaults, and a seed file
	 * that appears later is still picked up.
	 *
	 * @return array{business:bool,pricing:bool} Which options were seeded.
	 */
	public static function maybe_seed() {
		$done          = array(
			'business' => false,
			'pricing'  => false,
		);
		$need_business = ! self::has_content( self::OPTION, self::get_business() );
		$need_pricing  = ! self::has_content( self::PRICING_OPTION, self::get_pricing() );
		if ( ! $need_business && ! $need_pricing ) {
			return $done;
		}
		$file = self::seed_file();
		if ( '' === $file ) {
			return $done;
		}

		$seed = self::load_seed( $file );
		if ( false === get_option( self::HISTORY_OPTION ) ) {
			add_option( self::HISTORY_OPTION, array(), '', false );
		}
		if ( $need_business ) {
			update_option( self::OPTION, $seed['business'], true );
			$done['business'] = true;
		}
		if ( $need_pricing ) {
			update_option( self::PRICING_OPTION, $seed['pricing'], true );
			$done['pricing'] = true;
		}
		self::flush_cache();
		return $done;
	}

	/**
	 * Seed file path: MRTU_BUSINESS_SEED (dev) or the bundled data/mr-tu-business.json; '' when none exists.
	 *
	 * @return string
	 */
	public static function seed_file() {
		$candidates = array();
		if ( defined( 'MRTU_BUSINESS_SEED' ) && is_string( MRTU_BUSINESS_SEED ) && '' !== MRTU_BUSINESS_SEED ) {
			$candidates[] = MRTU_BUSINESS_SEED;
		}
		$candidates[] = MRTU_ML_DIR . 'data/mr-tu-business.json';
		$found        = '';
		foreach ( $candidates as $file ) {
			if ( is_readable( $file ) && is_file( $file ) ) {
				$found = $file;
				break;
			}
		}
		/**
		 * Filters the business seed file ('' = no seed).
		 *
		 * @param string $found Seed file path or ''.
		 */
		$found = (string) apply_filters( 'mrtu_business_seed_file', $found );
		return ( '' !== $found && is_readable( $found ) && is_file( $found ) ) ? $found : '';
	}

	/**
	 * Loads and normalises the seed. Missing/invalid parts fall back to empty defaults.
	 *
	 * @param string|null $file Seed path (null = auto-detect).
	 * @return array{business:array,pricing:array}
	 */
	public static function load_seed( $file = null ) {
		$file = null === $file ? self::seed_file() : (string) $file;
		$out  = array(
			'business' => self::business_defaults(),
			'pricing'  => Pricing::defaults(),
		);
		if ( '' === $file || ! is_readable( $file ) || ! is_file( $file ) ) {
			return $out;
		}
		$raw  = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled JSON file.
		$data = is_string( $raw ) ? json_decode( self::strip_bom( $raw ), true ) : null;
		if ( ! is_array( $data ) ) {
			return $out;
		}
		if ( isset( $data['business'] ) && is_array( $data['business'] ) ) {
			$errors = array();
			$value  = self::normalize_business( $data['business'], $errors );
			// Invalid fields were reset to defaults by the normaliser (drop mode), the rest is kept.
			$out['business'] = $value;
		}
		if ( isset( $data['pricing'] ) && is_array( $data['pricing'] ) ) {
			$errors         = array();
			$out['pricing'] = Pricing::normalize( $data['pricing'], $errors );
		}
		return $out;
	}

	/*
	 * Defaults + normalisation
	 * ------------------------------------------------------------------
	 */

	/**
	 * Empty NAP record (shape mirrors config/mr-tu-business.json → business).
	 *
	 * @return array
	 */
	public static function business_defaults() {
		$simple = array(
			'value'    => '',
			'verified' => false,
			'note'     => '',
		);
		return array(
			'brand'         => $simple,
			'display_name'  => $simple,
			'address'       => array(
				'street'      => '',
				'ward'        => '',
				'city'        => '',
				'postal_code' => '',
				'country'     => 'VN',
				'verified'    => false,
				'note'        => '',
			),
			'phone'         => array(
				'display'  => '',
				'e164'     => '',
				'verified' => false,
				'note'     => '',
			),
			'hours'         => array(
				'days'     => array(),
				'opens'    => '',
				'closes'   => '',
				'verified' => false,
				'note'     => '',
			),
			'service_model' => array(
				'value'    => 'appointment',
				'verified' => false,
				'note'     => '',
			),
			'service_area'  => $simple,
			'maps_url'      => $simple,
			'email'         => $simple,
			'website'       => $simple,
			'channels'      => array(
				'phone'    => array(
					'enabled'  => true,
					'verified' => false,
				),
				'whatsapp' => array(
					'enabled'  => false,
					'verified' => false,
					'value'    => '',
					'note'     => '',
				),
				'zalo'     => array(
					'enabled'  => false,
					'verified' => false,
					'value'    => '',
					'note'     => '',
				),
			),
			'updated_at'    => 0,
			'updated_by'    => 0,
		);
	}

	/**
	 * Normalises + validates a NAP record.
	 *
	 * @param mixed $input        Raw input (form post, JSON, stored option).
	 * @param array $errors       Receives [ 'field.path' => message ].
	 * @param bool  $keep_invalid true = keep sanitised invalid values (form redisplay), false = reset them to defaults.
	 * @return array Normalised record.
	 */
	public static function normalize_business( $input, &$errors = array(), $keep_invalid = false ) {
		$errors = array();
		$in     = is_array( $input ) ? $input : array();
		$out    = self::business_defaults();

		// Simple { value, verified, note } records.
		foreach ( array( 'brand', 'display_name', 'service_area' ) as $key ) {
			$rec                     = self::rec( $in, $key );
			$out[ $key ]['value']    = self::text( self::get( $rec, 'value' ) );
			$out[ $key ]['verified'] = self::flag( self::get( $rec, 'verified', false ) );
			$out[ $key ]['note']     = self::note( self::get( $rec, 'note' ) );
		}

		// Address.
		$rec = self::rec( $in, 'address' );
		foreach ( array( 'street', 'ward', 'city', 'postal_code' ) as $part ) {
			$out['address'][ $part ] = self::text( self::get( $rec, $part ) );
		}
		$country = strtoupper( self::text( self::get( $rec, 'country', 'VN' ), 10 ) );
		if ( '' === $country ) {
			$country = 'VN';
		}
		if ( ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
			$errors['address.country'] = __( 'Use a two-letter country code, for example VN.', 'mr-tu-multilang-seo' );
			$country                   = $keep_invalid ? $country : 'VN';
		}
		$out['address']['country']  = $country;
		$out['address']['verified'] = self::flag( self::get( $rec, 'verified', false ) );
		$out['address']['note']     = self::note( self::get( $rec, 'note' ) );

		// Phone.
		$rec     = self::rec( $in, 'phone' );
		$display = self::text( self::get( $rec, 'display' ), 60 );
		if ( self::strlen( $display ) > 20 ) {
			$errors['phone.display'] = __( 'Keep the displayed number to 20 characters or fewer.', 'mr-tu-multilang-seo' );
			$display                 = $keep_invalid ? $display : '';
		}
		$e164_raw = self::text( self::get( $rec, 'e164' ), 40 );
		$e164     = '' === $e164_raw ? self::derive_e164( $display ) : self::to_e164( $e164_raw );
		if ( '' !== $e164 && ! preg_match( self::E164_PATTERN, $e164 ) ) {
			$errors['phone.e164'] = __( 'Use the international format: +84 followed by 9 or 10 digits, for example +84816659199.', 'mr-tu-multilang-seo' );
			$e164                 = $keep_invalid ? $e164_raw : '';
		}
		$out['phone']['display']  = $display;
		$out['phone']['e164']     = $e164;
		$out['phone']['verified'] = self::flag( self::get( $rec, 'verified', false ) );
		$out['phone']['note']     = self::note( self::get( $rec, 'note' ) );
		if ( $out['phone']['verified'] && '' === $e164 && ! isset( $errors['phone.e164'] ) ) {
			$errors['phone.e164'] = __( 'A verified phone needs a valid number.', 'mr-tu-multilang-seo' );
		}

		// Opening hours.
		$rec     = self::rec( $in, 'hours' );
		$days_in = self::get( $rec, 'days', array() );
		if ( is_string( $days_in ) ) {
			$days_in = '' === trim( $days_in ) ? array() : explode( ',', $days_in );
		}
		$days    = array();
		$bad_day = false;
		foreach ( (array) $days_in as $day ) {
			$day = is_scalar( $day ) ? ucfirst( strtolower( trim( (string) $day ) ) ) : '';
			if ( in_array( $day, self::DAYS, true ) ) {
				$days[ $day ] = true;
			} else {
				$bad_day = true;
			}
		}
		if ( $bad_day ) {
			$errors['hours.days'] = __( 'Choose days from Monday to Sunday only.', 'mr-tu-multilang-seo' );
		}
		$out['hours']['days'] = array_values( array_intersect( self::DAYS, array_keys( $days ) ) );
		foreach ( array( 'opens', 'closes' ) as $part ) {
			$raw  = self::text( self::get( $rec, $part ), 10 );
			$time = self::time( $raw );
			if ( false === $time ) {
				$errors[ 'hours.' . $part ] = __( 'Use the 24-hour format HH:MM, for example 08:00.', 'mr-tu-multilang-seo' );
				$time                       = $keep_invalid ? $raw : '';
			}
			$out['hours'][ $part ] = $time;
		}
		if ( ! isset( $errors['hours.opens'] ) && ! isset( $errors['hours.closes'] )
			&& '' !== $out['hours']['opens'] && '' !== $out['hours']['closes']
			&& strcmp( $out['hours']['closes'], $out['hours']['opens'] ) <= 0 ) {
			$errors['hours.closes'] = __( 'The closing time must be later than the opening time.', 'mr-tu-multilang-seo' );
			if ( ! $keep_invalid ) {
				$out['hours']['closes'] = '';
			}
		}
		$out['hours']['verified'] = self::flag( self::get( $rec, 'verified', false ) );
		$out['hours']['note']     = self::note( self::get( $rec, 'note' ) );
		if ( $out['hours']['verified'] && ( ! $out['hours']['days'] || '' === $out['hours']['opens'] || '' === $out['hours']['closes'] ) ) {
			$errors['hours.verified'] = __( 'Choose the days and both times before marking the hours as verified.', 'mr-tu-multilang-seo' );
		}

		// Service model.
		$rec   = self::rec( $in, 'service_model' );
		$model = sanitize_key( (string) self::text( self::get( $rec, 'value', 'appointment' ) ) );
		if ( '' === $model ) {
			$model = 'appointment';
		}
		if ( ! in_array( $model, self::SERVICE_MODELS, true ) ) {
			$errors['service_model.value'] = __( 'Choose “by appointment” or “staffed shop”.', 'mr-tu-multilang-seo' );
			$model                         = $keep_invalid ? $model : 'appointment';
		}
		$out['service_model']['value']    = $model;
		$out['service_model']['verified'] = self::flag( self::get( $rec, 'verified', false ) );
		$out['service_model']['note']     = self::note( self::get( $rec, 'note' ) );

		// Google Maps link: https only.
		$rec = self::rec( $in, 'maps_url' );
		$url = trim( self::text( self::get( $rec, 'value' ), 2000 ) );
		if ( '' !== $url ) {
			$clean = esc_url_raw( $url, array( 'https' ) );
			if ( '' === $clean || 0 !== strpos( $clean, 'https://' ) ) {
				$errors['maps_url.value'] = __( 'The map link must be a full address starting with https://', 'mr-tu-multilang-seo' );
				$clean                    = $keep_invalid ? $url : '';
			}
			$url = $clean;
		}
		$out['maps_url']['value']    = $url;
		$out['maps_url']['verified'] = self::flag( self::get( $rec, 'verified', false ) );
		$out['maps_url']['note']     = self::note( self::get( $rec, 'note' ) );

		// Email.
		$rec   = self::rec( $in, 'email' );
		$email = trim( self::text( self::get( $rec, 'value' ), 254 ) );
		if ( '' !== $email ) {
			$clean = sanitize_email( $email );
			if ( '' === $clean || ! is_email( $clean ) ) {
				$errors['email.value'] = __( 'Enter a valid email address.', 'mr-tu-multilang-seo' );
				$clean                 = $keep_invalid ? $email : '';
			}
			$email = $clean;
		}
		$out['email']['value']    = $email;
		$out['email']['verified'] = self::flag( self::get( $rec, 'verified', false ) );
		$out['email']['note']     = self::note( self::get( $rec, 'note' ) );

		// Website.
		$rec = self::rec( $in, 'website' );
		$url = trim( self::text( self::get( $rec, 'value' ), 2000 ) );
		if ( '' !== $url ) {
			$clean = esc_url_raw( $url, array( 'http', 'https' ) );
			if ( '' === $clean || ! preg_match( '#^https?://#', $clean ) ) {
				$errors['website.value'] = __( 'Enter a full web address starting with https://', 'mr-tu-multilang-seo' );
				$clean                   = $keep_invalid ? $url : '';
			}
			$url = $clean;
		}
		$out['website']['value']    = $url;
		$out['website']['verified'] = self::flag( self::get( $rec, 'verified', false ) );
		$out['website']['note']     = self::note( self::get( $rec, 'note' ) );

		// A verified flag on an empty value makes no sense.
		foreach ( array( 'brand', 'display_name', 'service_area', 'maps_url', 'email', 'website' ) as $key ) {
			if ( $out[ $key ]['verified'] && '' === $out[ $key ]['value'] && ! isset( $errors[ $key . '.value' ] ) ) {
				$errors[ $key . '.value' ] = __( 'Fill this in before marking it as verified.', 'mr-tu-multilang-seo' );
			}
		}
		if ( $out['address']['verified'] && '' === $out['address']['street'] ) {
			$errors['address.street'] = __( 'Enter the street address before marking the address as verified.', 'mr-tu-multilang-seo' );
		}

		// Contact channels.
		$channels = self::rec( $in, 'channels' );
		$rec      = self::rec( $channels, 'phone' );

		$out['channels']['phone']['enabled']  = self::flag( self::get( $rec, 'enabled', true ) );
		$out['channels']['phone']['verified'] = $out['phone']['verified'];

		foreach ( array( 'whatsapp', 'zalo' ) as $channel ) {
			$rec   = self::rec( $channels, $channel );
			$raw   = self::text( self::get( $rec, 'value' ), 40 );
			$value = '';
			if ( '' !== $raw ) {
				$e164 = self::to_e164( $raw );
				if ( ! preg_match( self::E164_PATTERN, $e164 ) ) {
					$errors[ 'channels.' . $channel . '.value' ] = 'whatsapp' === $channel
						? __( 'Use the international format: +84 followed by 9 or 10 digits.', 'mr-tu-multilang-seo' )
						: __( 'Use a Vietnamese phone number, for example 0816659199.', 'mr-tu-multilang-seo' );
					$value                                       = $keep_invalid ? $raw : '';
				} else {
					$value = 'whatsapp' === $channel ? $e164 : '0' . substr( $e164, 3 );
				}
			}
			$out['channels'][ $channel ] = array(
				'enabled'  => self::flag( self::get( $rec, 'enabled', false ) ),
				'verified' => self::flag( self::get( $rec, 'verified', false ) ),
				'value'    => $value,
				'note'     => self::note( self::get( $rec, 'note' ) ),
			);
			if ( ( $out['channels'][ $channel ]['enabled'] || $out['channels'][ $channel ]['verified'] ) && '' === $value && ! isset( $errors[ 'channels.' . $channel . '.value' ] ) ) {
				$errors[ 'channels.' . $channel . '.value' ] = __( 'Enter the number before switching this channel on or marking it as verified.', 'mr-tu-multilang-seo' );
			}
		}

		$out['updated_at'] = absint( self::get( $in, 'updated_at', 0 ) );
		$out['updated_by'] = absint( self::get( $in, 'updated_by', 0 ) );

		return $out;
	}

	/**
	 * Sets every verified flag of a (raw) NAP input whose value is filled in.
	 *
	 * @param array $input Raw NAP input.
	 * @return array
	 */
	public static function mark_all_verified( $input ) {
		$in = is_array( $input ) ? $input : array();
		foreach ( array( 'brand', 'display_name', 'service_model', 'service_area', 'maps_url', 'email', 'website' ) as $key ) {
			if ( isset( $in[ $key ] ) && is_array( $in[ $key ] ) && '' !== trim( (string) self::scalar( self::get( $in[ $key ], 'value' ) ) ) ) {
				$in[ $key ]['verified'] = '1';
			}
		}
		if ( isset( $in['address'] ) && is_array( $in['address'] ) && '' !== trim( (string) self::scalar( self::get( $in['address'], 'street' ) ) ) ) {
			$in['address']['verified'] = '1';
		}
		if ( isset( $in['phone'] ) && is_array( $in['phone'] ) && ( '' !== trim( (string) self::scalar( self::get( $in['phone'], 'e164' ) ) ) || '' !== trim( (string) self::scalar( self::get( $in['phone'], 'display' ) ) ) ) ) {
			$in['phone']['verified'] = '1';
		}
		if ( isset( $in['hours'] ) && is_array( $in['hours'] ) && ! empty( $in['hours']['days'] )
			&& '' !== trim( (string) self::scalar( self::get( $in['hours'], 'opens' ) ) ) && '' !== trim( (string) self::scalar( self::get( $in['hours'], 'closes' ) ) ) ) {
			$in['hours']['verified'] = '1';
		}
		foreach ( array( 'whatsapp', 'zalo' ) as $channel ) {
			if ( isset( $in['channels'][ $channel ] ) && is_array( $in['channels'][ $channel ] )
				&& self::flag( self::get( $in['channels'][ $channel ], 'enabled', false ) )
				&& '' !== trim( (string) self::scalar( self::get( $in['channels'][ $channel ], 'value' ) ) ) ) {
				$in['channels'][ $channel ]['verified'] = '1';
			}
		}
		return $in;
	}

	/*
	 * Reading
	 * ------------------------------------------------------------------
	 */

	/**
	 * Stored NAP record, normalised (invalid values reset), with verified flags and notes.
	 *
	 * @return array
	 */
	public static function get_business() {
		return self::cached_option( self::OPTION );
	}

	/**
	 * Stored pricing record, normalised (invalid values reset).
	 *
	 * @return array
	 */
	public static function get_pricing() {
		return self::cached_option( self::PRICING_OPTION );
	}

	/**
	 * Reads + normalises an option once per request (re-validated when the raw value changes).
	 *
	 * @param string $option Option name.
	 * @return array
	 */
	private static function cached_option( $option ) {
		$raw = get_option( $option, array() );
		if ( isset( self::$cache[ 'opt_' . $option ] ) && self::$cache[ 'opt_' . $option ]['raw'] === $raw ) {
			return self::$cache[ 'opt_' . $option ]['value'];
		}
		$errors = array();
		$value  = self::PRICING_OPTION === $option ? Pricing::normalize( $raw, $errors ) : self::normalize_business( $raw, $errors );

		self::$cache[ 'opt_' . $option ] = array(
			'raw'   => $raw,
			'value' => $value,
		);
		return $value;
	}

	/**
	 * Public NAP view: only verified facts, simplified shapes.
	 *
	 * @return array {brand,display_name,address|null,phone|null,hours|null,service_model,service_area,maps_url,email,website}
	 */
	public static function public_data() {
		$b = self::get_business();

		$address = null;
		if ( $b['address']['verified'] && '' !== $b['address']['street'] ) {
			$address = array(
				'street'       => $b['address']['street'],
				'ward'         => $b['address']['ward'],
				'city'         => $b['address']['city'],
				'postal_code'  => $b['address']['postal_code'],
				'country'      => $b['address']['country'],
				'country_name' => self::country_name( $b['address']['country'] ),
			);

			$address['formatted'] = self::format_address( $address );
		}

		$phone = null;
		if ( $b['phone']['verified'] && '' !== $b['phone']['e164'] ) {
			$phone = array(
				'display' => '' !== $b['phone']['display'] ? $b['phone']['display'] : self::format_phone_display( $b['phone']['e164'] ),
				'e164'    => $b['phone']['e164'],
				'tel'     => 'tel:' . $b['phone']['e164'],
			);
		}

		$hours = null;
		$h     = $b['hours'];
		if ( $h['verified'] && $h['days'] && '' !== $h['opens'] && '' !== $h['closes'] ) {
			$hours = array(
				'days'   => $h['days'],
				'opens'  => $h['opens'],
				'closes' => $h['closes'],
				'spec'   => self::hours_spec( $h ),
				'text'   => self::hours_text( $h ),
			);
		}

		return array(
			'brand'         => self::public_value( $b['brand'] ),
			'display_name'  => self::public_value( $b['display_name'] ),
			'address'       => $address,
			'phone'         => $phone,
			'hours'         => $hours,
			'service_model' => self::public_value( $b['service_model'] ),
			'service_area'  => self::public_value( $b['service_area'] ),
			'maps_url'      => self::public_value( $b['maps_url'] ),
			'email'         => self::public_value( $b['email'] ),
			'website'       => self::public_value( $b['website'] ),
		);
	}

	/**
	 * Field accessor behind mrtu_business(). Supports dot paths ("phone.display").
	 *
	 * @param string|null $field       Field or dot path; null = everything.
	 * @param bool        $public_only Only verified, public data (default) or the raw record.
	 * @return mixed '' / null when unavailable.
	 */
	public static function field( $field = null, $public_only = true ) {
		$data = $public_only ? self::public_data() : self::get_business();
		if ( null === $field || '' === $field ) {
			return $data;
		}
		$node = $data;
		foreach ( explode( '.', (string) $field ) as $part ) {
			if ( is_array( $node ) && array_key_exists( $part, $node ) ) {
				$node = $node[ $part ];
			} else {
				return null;
			}
		}
		return $node;
	}

	/**
	 * Public phone in a format.
	 *
	 * @param string $format display|e164|tel.
	 * @return string '' when the phone is not verified.
	 */
	public static function phone( $format = 'display' ) {
		$phone = self::field( 'phone' );
		if ( ! is_array( $phone ) ) {
			return '';
		}
		if ( 'e164' === $format ) {
			return $phone['e164'];
		}
		if ( 'tel' === $format ) {
			return $phone['tel'];
		}
		return $phone['display'];
	}

	/**
	 * Every channel with its publication status (used by mrtu_contact_channels and the admin).
	 *
	 * @param string|null $lang Language code (labels + contact URL), null = current.
	 * @return array key => [label,url,value,enabled,verified,public,reason]
	 */
	public static function channel_status( $lang = null ) {
		$lang      = self::lang( $lang );
		$cache_key = 'channels_' . $lang;
		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}
		$switched = self::switch_language( $lang );
		$b        = self::get_business();
		$pub      = self::public_data();
		$out      = array();

		// Phone (call).
		$enabled      = (bool) $b['channels']['phone']['enabled'];
		$verified     = (bool) $b['phone']['verified'];
		$public       = $enabled && null !== $pub['phone'];
		$out['phone'] = self::channel_entry(
			__( 'Call', 'mr-tu-multilang-seo' ),
			$public ? $pub['phone']['tel'] : '',
			$public ? $pub['phone']['display'] : '',
			$enabled,
			$verified,
			$public,
			$public ? '' : ( ! $enabled ? __( 'Call buttons are switched off in Store information.', 'mr-tu-multilang-seo' ) : ( ! $verified ? __( 'The phone number is not verified yet.', 'mr-tu-multilang-seo' ) : __( 'The phone number is missing.', 'mr-tu-multilang-seo' ) ) )
		);

		// WhatsApp / Zalo.
		$names = array(
			'whatsapp' => 'WhatsApp',
			'zalo'     => 'Zalo',
		);
		foreach ( $names as $channel => $name ) {
			$c      = $b['channels'][ $channel ];
			$public = $c['enabled'] && $c['verified'] && '' !== $c['value'];
			$digits = preg_replace( '/\D+/', '', $c['value'] );
			$url    = 'whatsapp' === $channel ? 'https://wa.me/' . $digits : 'https://zalo.me/' . $digits;
			$reason = '';
			if ( ! $public ) {
				if ( ! $c['enabled'] ) {
					/* translators: %s: chat app name (WhatsApp or Zalo). */
					$reason = sprintf( __( '%s is switched off in Store information.', 'mr-tu-multilang-seo' ), $name );
				} elseif ( ! $c['verified'] ) {
					/* translators: %s: chat app name (WhatsApp or Zalo). */
					$reason = sprintf( __( '%s is not verified yet.', 'mr-tu-multilang-seo' ), $name );
				} else {
					/* translators: %s: chat app name (WhatsApp or Zalo). */
					$reason = sprintf( __( 'The %s number is missing.', 'mr-tu-multilang-seo' ), $name );
				}
			}
			$out[ $channel ] = self::channel_entry( $name, $public ? $url : '', $public ? $c['value'] : '', (bool) $c['enabled'], (bool) $c['verified'], $public, $reason );
		}

		// Directions: verified map link AND verified address.
		$maps   = $b['maps_url'];
		$public = '' !== $maps['value'] && $maps['verified'] && null !== $pub['address'];
		$reason = '';
		if ( ! $public ) {
			if ( '' === $maps['value'] ) {
				$reason = __( 'The Google Maps link is missing.', 'mr-tu-multilang-seo' );
			} elseif ( ! $maps['verified'] ) {
				$reason = __( 'The Google Maps link is not verified yet.', 'mr-tu-multilang-seo' );
			} else {
				$reason = __( 'The address is not verified yet.', 'mr-tu-multilang-seo' );
			}
		}
		$out['directions'] = self::channel_entry(
			__( 'Directions', 'mr-tu-multilang-seo' ),
			$public ? $maps['value'] : '',
			$public ? $pub['address']['formatted'] : '',
			'' !== $maps['value'],
			(bool) $maps['verified'],
			$public,
			$reason
		);

		// Contact page in the same language.
		$url            = self::role_url( 'contact', $lang );
		$out['contact'] = self::channel_entry(
			__( 'Contact page', 'mr-tu-multilang-seo' ),
			$url,
			$url,
			true,
			true,
			'' !== $url,
			'' !== $url ? '' : __( 'No published contact page in this language yet.', 'mr-tu-multilang-seo' )
		);

		if ( $switched ) {
			restore_previous_locale();
		}
		self::$cache[ $cache_key ] = $out;
		return $out;
	}

	/**
	 * Contact channels behind mrtu_contact_channels().
	 *
	 * @param bool        $enabled_only Only publishable channels (default).
	 * @param string|null $lang         Language code.
	 * @return array
	 */
	public static function contact_channels( $enabled_only = true, $lang = null ) {
		$all = self::channel_status( $lang );
		if ( ! $enabled_only ) {
			return $all;
		}
		$out = array();
		foreach ( $all as $key => $channel ) {
			if ( $channel['public'] ) {
				$out[ $key ] = $channel;
			}
		}
		return $out;
	}

	/**
	 * Builds one channel entry.
	 *
	 * @param string $label    Label.
	 * @param string $url      URL ('' when not public).
	 * @param string $value    Value ('' when not public).
	 * @param bool   $enabled  Enabled.
	 * @param bool   $verified Verified.
	 * @param bool   $is_public Publishable.
	 * @param string $reason   Why not public.
	 * @return array
	 */
	private static function channel_entry( $label, $url, $value, $enabled, $verified, $is_public, $reason ) {
		return array(
			'label'    => $label,
			'url'      => $url,
			'value'    => $value,
			'enabled'  => (bool) $enabled,
			'verified' => (bool) $verified,
			'public'   => (bool) $is_public,
			'reason'   => $reason,
		);
	}

	/**
	 * Generic, translatable service-area label (used while the address is not public).
	 *
	 * @param string|null $lang Language code.
	 * @return string
	 */
	public static function service_area_label( $lang = null ) {
		$lang     = self::lang( $lang );
		$switched = self::switch_language( $lang );
		$label    = __( 'Old Quarter, Hanoi', 'mr-tu-multilang-seo' );
		if ( $switched ) {
			restore_previous_locale();
		}
		/**
		 * Filters the generic service-area label.
		 *
		 * @param string $label Label.
		 * @param string $lang  Language code.
		 */
		return (string) apply_filters( 'mrtu_service_area_label', $label, $lang );
	}

	/*
	 * Writing
	 * ------------------------------------------------------------------
	 */

	/**
	 * Validates and saves the NAP record (snapshot first, then hooks).
	 *
	 * @param array $input Raw input.
	 * @param array $args  snapshot (bool), type (history type), user_id (int), force (bool).
	 * @return string|\WP_Error 'saved' | 'unchanged' | WP_Error( data: errors, data ).
	 */
	public static function save_business( $input, $args = array() ) {
		$args   = self::write_args( $args, 'business' );
		$errors = array();
		$new    = self::normalize_business( $input, $errors, true );
		if ( $errors ) {
			return self::invalid( $errors, $new );
		}
		$old = self::get_business();
		if ( ! $args['force'] && self::strip_meta( $old ) === self::strip_meta( $new ) ) {
			return 'unchanged';
		}
		self::write( self::OPTION, $old, $new, $args );
		return 'saved';
	}

	/**
	 * Validates and saves the pricing record (snapshot first, then hooks).
	 *
	 * @param array $input Raw input.
	 * @param array $args  See save_business().
	 * @return string|\WP_Error
	 */
	public static function save_pricing( $input, $args = array() ) {
		$args   = self::write_args( $args, 'pricing' );
		$errors = array();
		$new    = Pricing::normalize( $input, $errors, true );
		if ( $errors ) {
			return self::invalid( $errors, $new );
		}
		$old = self::get_pricing();
		if ( ! $args['force'] && self::strip_meta( $old ) === self::strip_meta( $new ) ) {
			return 'unchanged';
		}
		self::write( self::PRICING_OPTION, $old, $new, $args );
		return 'saved';
	}

	/**
	 * Default write arguments.
	 *
	 * @param array  $args Args.
	 * @param string $type Default history type.
	 * @return array
	 */
	private static function write_args( $args, $type ) {
		$args = wp_parse_args(
			(array) $args,
			array(
				'snapshot' => true,
				'type'     => $type,
				'user_id'  => get_current_user_id(),
				'force'    => false,
			)
		);

		$args['type'] = in_array( $args['type'], self::HISTORY_TYPES, true ) ? $args['type'] : $type;
		return $args;
	}

	/**
	 * Validation error object.
	 *
	 * @param array $errors Errors.
	 * @param array $data   Submitted (sanitised) data for redisplay.
	 * @return \WP_Error
	 */
	private static function invalid( $errors, $data ) {
		return new \WP_Error(
			'mrtu_invalid',
			__( 'Some fields need attention. Nothing was saved.', 'mr-tu-multilang-seo' ),
			array(
				'errors' => $errors,
				'data'   => $data,
			)
		);
	}

	/**
	 * Persists a normalised record: snapshot old value, stamp, update, fire the hook.
	 *
	 * @param string $option Option name.
	 * @param array  $old    Old normalised value.
	 * @param array  $value  New normalised value.
	 * @param array  $args   Write args.
	 */
	private static function write( $option, $old, $value, $args ) {
		if ( $args['snapshot'] && self::has_content( $option, $old ) ) {
			self::snapshot( $args['type'], $old, $args['user_id'] );
		}
		$value['updated_at'] = time();
		$value['updated_by'] = (int) $args['user_id'];
		update_option( $option, $value, true );
		self::flush_cache();

		if ( self::OPTION === $option ) {
			/**
			 * Fires after the NAP record changed.
			 *
			 * @param array $old Previous record.
			 * @param array $new New record.
			 */
			do_action( 'mrtu_business_updated', $old, $value );
		} else {
			/**
			 * Fires after prices/vehicles changed.
			 *
			 * @param array $old Previous record.
			 * @param array $new New record.
			 */
			do_action( 'mrtu_pricing_updated', $old, $value );
		}
	}

	/*
	 * History (snapshots)
	 * ------------------------------------------------------------------
	 */

	/**
	 * Adds a snapshot (newest first, max 20).
	 *
	 * @param string   $type    business|pricing|import|restore.
	 * @param array    $data    Data (import/restore: [business, pricing]).
	 * @param int|null $user_id User (null = current).
	 * @return string Snapshot id.
	 */
	public static function snapshot( $type, $data, $user_id = null ) {
		$history = self::history();
		$id      = substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 16 );
		array_unshift(
			$history,
			array(
				'id'      => $id,
				'time'    => time(),
				'user_id' => null === $user_id ? get_current_user_id() : (int) $user_id,
				'type'    => in_array( $type, self::HISTORY_TYPES, true ) ? $type : 'business',
				'data'    => $data,
			)
		);
		$history = array_slice( $history, 0, self::HISTORY_LIMIT );
		if ( false === get_option( self::HISTORY_OPTION ) ) {
			add_option( self::HISTORY_OPTION, $history, '', false );
		} else {
			update_option( self::HISTORY_OPTION, $history, false );
		}
		return $id;
	}

	/**
	 * Snapshot list, newest first.
	 *
	 * @return array[]
	 */
	public static function history() {
		$history = get_option( self::HISTORY_OPTION, array() );
		if ( ! is_array( $history ) ) {
			return array();
		}
		$out = array();
		foreach ( $history as $entry ) {
			if ( is_array( $entry ) && isset( $entry['id'], $entry['type'], $entry['data'] ) ) {
				$out[] = $entry;
			}
		}
		return $out;
	}

	/**
	 * Restores a snapshot (current data is snapshotted first as type "restore").
	 *
	 * @param string $id Snapshot id.
	 * @return string|\WP_Error 'saved' or error.
	 */
	public static function restore( $id ) {
		$entry = null;
		foreach ( self::history() as $item ) {
			if ( (string) $item['id'] === (string) $id ) {
				$entry = $item;
				break;
			}
		}
		if ( ! $entry ) {
			return new \WP_Error( 'mrtu_snapshot_missing', __( 'That backup no longer exists.', 'mr-tu-multilang-seo' ) );
		}
		if ( 'business' === $entry['type'] ) {
			$payload = array( 'business' => $entry['data'] );
		} elseif ( 'pricing' === $entry['type'] ) {
			$payload = array( 'pricing' => $entry['data'] );
		} else {
			$payload = is_array( $entry['data'] ) ? $entry['data'] : array();
		}
		$result = self::apply_payload( $payload, 'restore' );
		return $result;
	}

	/**
	 * Validates a {business?, pricing?} payload, snapshots the current data, then writes.
	 *
	 * @param array  $payload Payload.
	 * @param string $type    History type (import|restore).
	 * @return string|\WP_Error
	 */
	private static function apply_payload( $payload, $type ) {
		$errors = array();
		$new    = array();
		if ( isset( $payload['business'] ) ) {
			if ( ! is_array( $payload['business'] ) ) {
				$errors['business'] = __( 'The store information block is not valid.', 'mr-tu-multilang-seo' );
			} else {
				$e               = array();
				$new['business'] = self::normalize_business( $payload['business'], $e, true );
				foreach ( $e as $key => $message ) {
					$errors[ 'business.' . $key ] = $message;
				}
			}
		}
		if ( isset( $payload['pricing'] ) ) {
			if ( ! is_array( $payload['pricing'] ) ) {
				$errors['pricing'] = __( 'The price block is not valid.', 'mr-tu-multilang-seo' );
			} else {
				$e              = array();
				$new['pricing'] = Pricing::normalize( $payload['pricing'], $e, true );
				foreach ( $e as $key => $message ) {
					$errors[ 'pricing.' . $key ] = $message;
				}
			}
		}
		if ( ! $new && ! $errors ) {
			$errors['file'] = __( 'There is no store information or price data in it.', 'mr-tu-multilang-seo' );
		}
		if ( $errors ) {
			return new \WP_Error(
				'mrtu_payload_invalid',
				'restore' === $type
					? __( 'This backup contains invalid data and was not restored.', 'mr-tu-multilang-seo' )
					: __( 'The file contains invalid data. Nothing was imported.', 'mr-tu-multilang-seo' ),
				array( 'errors' => $errors )
			);
		}

		$current = array(
			'business' => self::get_business(),
			'pricing'  => self::get_pricing(),
		);
		self::snapshot( $type, $current );

		$args = self::write_args(
			array(
				'snapshot' => false,
				'type'     => $type,
			),
			$type
		);
		if ( isset( $new['business'] ) ) {
			self::write( self::OPTION, $current['business'], $new['business'], $args );
		}
		if ( isset( $new['pricing'] ) ) {
			self::write( self::PRICING_OPTION, $current['pricing'], $new['pricing'], $args );
		}
		return 'saved';
	}

	/*
	 * Export / import
	 * ------------------------------------------------------------------
	 */

	/**
	 * Export payload (both options, same shape as config/mr-tu-business.json).
	 *
	 * @return array
	 */
	public static function export_data() {
		return array(
			'$schema_version' => self::SCHEMA_VERSION,
			'generator'       => 'mr-tu-multilang-seo',
			'plugin_version'  => defined( 'MRTU_ML_VERSION' ) ? MRTU_ML_VERSION : '',
			'exported_at'     => gmdate( 'c' ),
			'site'            => home_url( '/' ),
			'business'        => self::strip_meta( self::get_business() ),
			'pricing'         => self::strip_meta( self::get_pricing() ),
		);
	}

	/**
	 * Export as pretty JSON.
	 *
	 * @return string
	 */
	public static function export_json() {
		return (string) wp_json_encode( self::export_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Imports a JSON document (≤ 200 KB, strict decode, schema validation, automatic snapshot).
	 *
	 * @param string $json JSON text.
	 * @return string|\WP_Error 'saved' or error (data: errors).
	 */
	public static function import_json( $json ) {
		if ( ! is_string( $json ) || '' === trim( $json ) ) {
			return new \WP_Error( 'mrtu_import_empty', __( 'The file is empty.', 'mr-tu-multilang-seo' ) );
		}
		if ( strlen( $json ) > self::IMPORT_MAX_BYTES ) {
			return new \WP_Error( 'mrtu_import_size', __( 'The file is larger than 200 KB.', 'mr-tu-multilang-seo' ) );
		}
		$data = json_decode( self::strip_bom( $json ), true, 64 );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			/* translators: %s: technical JSON error message. */
			return new \WP_Error( 'mrtu_import_json', sprintf( __( 'The file is not valid JSON (%s).', 'mr-tu-multilang-seo' ), json_last_error_msg() ) );
		}
		return self::import_data( $data );
	}

	/**
	 * Imports decoded data.
	 *
	 * @param array $data Decoded document.
	 * @return string|\WP_Error
	 */
	public static function import_data( $data ) {
		if ( ! is_array( $data ) || ( ! isset( $data['business'] ) && ! isset( $data['pricing'] ) ) ) {
			return new \WP_Error( 'mrtu_import_schema', __( 'This file does not contain Mr Tu store information or prices.', 'mr-tu-multilang-seo' ) );
		}
		if ( isset( $data['$schema_version'] ) && (int) $data['$schema_version'] > self::SCHEMA_VERSION ) {
			return new \WP_Error( 'mrtu_import_version', __( 'This file was made by a newer version of the plugin.', 'mr-tu-multilang-seo' ) );
		}
		$payload = array();
		$errors  = array();
		if ( isset( $data['business'] ) ) {
			$known = is_array( $data['business'] ) ? array_intersect( array_keys( $data['business'] ), array( 'brand', 'display_name', 'address', 'phone', 'hours' ) ) : array();
			if ( ! $known ) {
				$errors['business'] = __( 'The store information block has none of the expected fields.', 'mr-tu-multilang-seo' );
			}
			$payload['business'] = $data['business'];
		}
		if ( isset( $data['pricing'] ) ) {
			if ( ! is_array( $data['pricing'] ) || ! isset( $data['pricing']['vehicles'] ) || ! is_array( $data['pricing']['vehicles'] ) ) {
				$errors['pricing'] = __( 'The price block must contain a list of vehicles.', 'mr-tu-multilang-seo' );
			}
			$payload['pricing'] = $data['pricing'];
		}
		if ( $errors ) {
			return new \WP_Error( 'mrtu_import_schema', __( 'The file contains invalid data. Nothing was imported.', 'mr-tu-multilang-seo' ), array( 'errors' => $errors ) );
		}
		return self::apply_payload( $payload, 'import' );
	}

	/*
	 * Language helpers (core i18n module integration, with fallbacks)
	 * ------------------------------------------------------------------
	 */

	/**
	 * Resolves a language code: explicit valid code, else current language, else 'vi'.
	 *
	 * @param string|null $lang Code or null.
	 * @return string
	 */
	public static function lang( $lang = null ) {
		if ( is_string( $lang ) && in_array( $lang, self::LANGS, true ) ) {
			return $lang;
		}
		if ( function_exists( 'mrtu_current_language' ) ) {
			$current = (string) mrtu_current_language();
			if ( in_array( $current, self::LANGS, true ) ) {
				return $current;
			}
		}
		return 'vi';
	}

	/**
	 * WordPress locale of a language code.
	 *
	 * @param string $lang Code.
	 * @return string
	 */
	public static function locale_for( $lang ) {
		if ( function_exists( 'mrtu_get_language' ) ) {
			$language = mrtu_get_language( $lang );
			if ( is_array( $language ) && ! empty( $language['locale'] ) ) {
				return (string) $language['locale'];
			}
		}
		$map = array(
			'vi' => 'vi',
			'en' => 'en_US',
			'de' => 'de_DE',
			'fr' => 'fr_FR',
			'es' => 'es_ES',
		);
		return isset( $map[ $lang ] ) ? $map[ $lang ] : '';
	}

	/**
	 * Switches translations to another language when it differs from the current one.
	 * Callers must restore_previous_locale() when this returns true.
	 *
	 * @param string $lang Code.
	 * @return bool Whether the locale was switched.
	 */
	public static function switch_language( $lang ) {
		if ( ! function_exists( 'switch_to_locale' ) || self::lang( null ) === $lang ) {
			return false;
		}
		$locale = self::locale_for( $lang );
		if ( '' === $locale || determine_locale() === $locale ) {
			return false;
		}
		return (bool) switch_to_locale( $locale );
	}

	/**
	 * Same-language URL of a page role via the core API ('' when unavailable).
	 *
	 * @param string      $role Role.
	 * @param string|null $lang Code.
	 * @return string
	 */
	public static function role_url( $role, $lang = null ) {
		if ( ! function_exists( 'mrtu_role_url' ) ) {
			return '';
		}
		$url = mrtu_role_url( $role, $lang );
		return is_string( $url ) ? $url : '';
	}

	/*
	 * Formatting helpers
	 * ------------------------------------------------------------------
	 */

	/**
	 * Converts a phone string to +84 form (no validation). "0816 659 199" → "+84816659199".
	 *
	 * @param string $text Phone text.
	 * @return string
	 */
	public static function to_e164( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}
		$plus   = 0 === strpos( $text, '+' );
		$digits = preg_replace( '/\D+/', '', $text );
		if ( '' === $digits ) {
			return $text;
		}
		if ( $plus ) {
			return '+' . $digits;
		}
		if ( 0 === strpos( $digits, '0084' ) ) {
			return '+' . substr( $digits, 2 );
		}
		if ( 0 === strpos( $digits, '84' ) && strlen( $digits ) >= 11 ) {
			return '+' . $digits;
		}
		if ( 0 === strpos( $digits, '0' ) ) {
			return '+84' . substr( $digits, 1 );
		}
		return $digits;
	}

	/**
	 * Suggests an E.164 number from a display number ('' when it cannot be derived).
	 *
	 * @param string $display Display number.
	 * @return string
	 */
	public static function derive_e164( $display ) {
		$e164 = self::to_e164( $display );
		return preg_match( self::E164_PATTERN, $e164 ) ? $e164 : '';
	}

	/**
	 * Vietnamese display format of an E.164 number: +84816659199 → "0816 659 199".
	 *
	 * @param string $e164 E.164 number.
	 * @return string
	 */
	public static function format_phone_display( $e164 ) {
		if ( ! preg_match( self::E164_PATTERN, (string) $e164 ) ) {
			return (string) $e164;
		}
		$local = '0' . substr( $e164, 3 );
		if ( 10 === strlen( $local ) ) {
			return substr( $local, 0, 4 ) . ' ' . substr( $local, 4, 3 ) . ' ' . substr( $local, 7 );
		}
		return substr( $local, 0, 4 ) . ' ' . substr( $local, 4, 3 ) . ' ' . substr( $local, 7, 4 );
	}

	/**
	 * One-line address.
	 *
	 * @param array $a Address parts.
	 * @return string
	 */
	public static function format_address( $a ) {
		$city = trim( $a['city'] . ( '' !== $a['postal_code'] ? ' ' . $a['postal_code'] : '' ) );
		$bits = array_filter( array( $a['street'], $a['ward'], $city, isset( $a['country_name'] ) ? $a['country_name'] : '' ), 'strlen' );
		return implode( ', ', $bits );
	}

	/**
	 * Country name.
	 *
	 * @param string $code ISO code.
	 * @return string
	 */
	public static function country_name( $code ) {
		if ( 'VN' === $code ) {
			return __( 'Vietnam', 'mr-tu-multilang-seo' );
		}
		return (string) $code;
	}

	/**
	 * Translated short weekday labels.
	 *
	 * @param bool $long Full names.
	 * @return array code => label
	 */
	public static function day_labels( $long = false ) {
		if ( $long ) {
			return array(
				'Mo' => __( 'Monday', 'mr-tu-multilang-seo' ),
				'Tu' => __( 'Tuesday', 'mr-tu-multilang-seo' ),
				'We' => __( 'Wednesday', 'mr-tu-multilang-seo' ),
				'Th' => __( 'Thursday', 'mr-tu-multilang-seo' ),
				'Fr' => __( 'Friday', 'mr-tu-multilang-seo' ),
				'Sa' => __( 'Saturday', 'mr-tu-multilang-seo' ),
				'Su' => __( 'Sunday', 'mr-tu-multilang-seo' ),
			);
		}
		return array(
			'Mo' => _x( 'Mon', 'weekday abbreviation', 'mr-tu-multilang-seo' ),
			'Tu' => _x( 'Tue', 'weekday abbreviation', 'mr-tu-multilang-seo' ),
			'We' => _x( 'Wed', 'weekday abbreviation', 'mr-tu-multilang-seo' ),
			'Th' => _x( 'Thu', 'weekday abbreviation', 'mr-tu-multilang-seo' ),
			'Fr' => _x( 'Fri', 'weekday abbreviation', 'mr-tu-multilang-seo' ),
			'Sa' => _x( 'Sat', 'weekday abbreviation', 'mr-tu-multilang-seo' ),
			'Su' => _x( 'Sun', 'weekday abbreviation', 'mr-tu-multilang-seo' ),
		);
	}

	/**
	 * Groups day codes into consecutive runs: [Mo,Tu,We,Fr] → [[Mo,We],[Fr,Fr]].
	 *
	 * @param string[] $days Day codes.
	 * @return array[] [first, last] pairs.
	 */
	private static function day_runs( $days ) {
		$index = array_flip( self::DAYS );
		$runs  = array();
		$prev  = -2;
		foreach ( self::DAYS as $day ) {
			if ( ! in_array( $day, $days, true ) ) {
				continue;
			}
			if ( $index[ $day ] === $prev + 1 && $runs ) {
				$runs[ count( $runs ) - 1 ][1] = $day;
			} else {
				$runs[] = array( $day, $day );
			}
			$prev = $index[ $day ];
		}
		return $runs;
	}

	/**
	 * Schema.org openingHours string, e.g. "Mo-Su 08:00-17:00".
	 *
	 * @param array $hours Hours record.
	 * @return string
	 */
	public static function hours_spec( $hours ) {
		$parts = array();
		foreach ( self::day_runs( $hours['days'] ) as $run ) {
			$parts[] = $run[0] === $run[1] ? $run[0] : $run[0] . '-' . $run[1];
		}
		return implode( ',', $parts ) . ' ' . $hours['opens'] . '-' . $hours['closes'];
	}

	/**
	 * Human, translated opening hours, e.g. "Every day, 08:00–17:00".
	 *
	 * @param array $hours Hours record.
	 * @return string
	 */
	public static function hours_text( $hours ) {
		$labels = self::day_labels();
		if ( 7 === count( $hours['days'] ) ) {
			$days = __( 'Every day', 'mr-tu-multilang-seo' );
		} else {
			$parts = array();
			foreach ( self::day_runs( $hours['days'] ) as $run ) {
				$parts[] = $run[0] === $run[1] ? $labels[ $run[0] ] : $labels[ $run[0] ] . "\u{2013}" . $labels[ $run[1] ];
			}
			$days = implode( ', ', $parts );
		}
		/* translators: 1: days, e.g. "Every day" or "Mon–Fri", 2: time range, e.g. "08:00–17:00". */
		return sprintf( __( '%1$s, %2$s', 'mr-tu-multilang-seo' ), $days, $hours['opens'] . "\u{2013}" . $hours['closes'] );
	}

	/*
	 * Low-level sanitising helpers (shared with Pricing)
	 * ------------------------------------------------------------------
	 */

	/**
	 * Array value or default.
	 *
	 * @param mixed  $arr     Array.
	 * @param string $key     Key.
	 * @param mixed  $fallback Default.
	 * @return mixed
	 */
	public static function get( $arr, $key, $fallback = '' ) {
		return ( is_array( $arr ) && array_key_exists( $key, $arr ) && null !== $arr[ $key ] ) ? $arr[ $key ] : $fallback;
	}

	/**
	 * Sub-array or empty array.
	 *
	 * @param mixed  $arr Array.
	 * @param string $key Key.
	 * @return array
	 */
	public static function rec( $arr, $key ) {
		return ( is_array( $arr ) && isset( $arr[ $key ] ) && is_array( $arr[ $key ] ) ) ? $arr[ $key ] : array();
	}

	/**
	 * Scalar or ''.
	 *
	 * @param mixed $value Value.
	 * @return string|int|float|bool
	 */
	private static function scalar( $value ) {
		return is_scalar( $value ) ? $value : '';
	}

	/**
	 * Single-line text, capped.
	 *
	 * @param mixed $value Value.
	 * @param int   $max   Max characters.
	 * @return string
	 */
	public static function text( $value, $max = 200 ) {
		if ( ! is_scalar( $value ) || is_bool( $value ) ) {
			return '';
		}
		$text = sanitize_text_field( (string) $value );
		return self::strlen( $text ) > $max ? mb_substr( $text, 0, $max ) : $text;
	}

	/**
	 * Multi-line admin note / description, capped.
	 *
	 * @param mixed $value Value.
	 * @param int   $max   Max characters.
	 * @return string
	 */
	public static function note( $value, $max = 500 ) {
		if ( ! is_scalar( $value ) || is_bool( $value ) ) {
			return '';
		}
		$text = sanitize_textarea_field( (string) $value );
		return self::strlen( $text ) > $max ? mb_substr( $text, 0, $max ) : $text;
	}

	/**
	 * Boolean from form/JSON values.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function flag( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return 0.0 !== (float) $value;
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'on', 'yes' ), true );
		}
		return false;
	}

	/**
	 * Multibyte length.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	public static function strlen( $text ) {
		return (int) mb_strlen( (string) $text );
	}

	/**
	 * Normalises "8:00" / "08:00" / "08:00:00" to "08:00"; '' stays ''; false when invalid.
	 *
	 * @param string $value Time text.
	 * @return string|false
	 */
	private static function time( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $m ) ) {
			return false;
		}
		$h = (int) $m[1];
		$i = (int) $m[2];
		if ( $h > 23 || $i > 59 ) {
			return false;
		}
		return sprintf( '%02d:%02d', $h, $i );
	}

	/**
	 * Value of a { value, verified } record when verified, else ''.
	 *
	 * @param array $rec Record.
	 * @return string
	 */
	private static function public_value( $rec ) {
		return ( ! empty( $rec['verified'] ) && isset( $rec['value'] ) ) ? (string) $rec['value'] : '';
	}

	/**
	 * Removes bookkeeping keys (updated_at/updated_by) for comparison/export.
	 *
	 * @param array $data Record.
	 * @return array
	 */
	public static function strip_meta( $data ) {
		unset( $data['updated_at'], $data['updated_by'] );
		return $data;
	}

	/**
	 * Whether a normalised record differs from the empty defaults (worth a snapshot).
	 *
	 * @param string $option Option name.
	 * @param array  $value  Normalised value.
	 * @return bool
	 */
	private static function has_content( $option, $value ) {
		$defaults = self::PRICING_OPTION === $option ? Pricing::defaults() : self::business_defaults();
		return self::strip_meta( $value ) !== self::strip_meta( $defaults );
	}

	/**
	 * Removes a UTF-8 byte order mark.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function strip_bom( $text ) {
		return 0 === strpos( $text, "\xEF\xBB\xBF" ) ? substr( $text, 3 ) : $text;
	}
}
