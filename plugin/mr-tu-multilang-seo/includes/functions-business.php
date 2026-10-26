<?php
/**
 * Public business API (contract §2.4 / §2.7). Themes and the assistant plugin call ONLY these
 * functions, always behind function_exists(). Unverified data is never returned as fact.
 *
 * @package MrTu\Multilang
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mrtu_business' ) ) {
	/**
	 * NAP access.
	 *
	 * Public view (default): brand, display_name, service_model, service_area, maps_url, email, website
	 * are strings ('' when unverified); address, phone, hours are arrays or null when unverified.
	 * Dot paths are supported: mrtu_business( 'phone.display' ).
	 *
	 * @param string|null $field       Field / dot path, null for everything.
	 * @param bool        $public_only false returns the stored record with verified flags + admin notes.
	 * @return mixed
	 */
	function mrtu_business( $field = null, $public_only = true ) {
		return \MrTu\Multilang\Business::field( $field, (bool) $public_only );
	}
}

if ( ! function_exists( 'mrtu_phone' ) ) {
	/**
	 * Public phone number.
	 *
	 * @param string $format display ("0816 659 199") | e164 ("+84816659199") | tel ("tel:+84816659199").
	 * @return string '' when the phone is not verified.
	 */
	function mrtu_phone( $format = 'display' ) {
		return \MrTu\Multilang\Business::phone( (string) $format );
	}
}

if ( ! function_exists( 'mrtu_contact_channels' ) ) {
	/**
	 * Contact channels: phone, whatsapp, zalo, directions, contact.
	 *
	 * Each entry: [ label, url, value, enabled, verified, public, reason ]. With $enabled_only (default)
	 * only publishable channels are returned (enabled AND verified; directions = verified map link +
	 * verified address; contact = same-language contact page exists). Non-public entries carry '' url/value.
	 *
	 * @param bool $enabled_only Only publishable channels.
	 * @return array
	 */
	function mrtu_contact_channels( $enabled_only = true ) {
		return \MrTu\Multilang\Business::contact_channels( (bool) $enabled_only );
	}
}

if ( ! function_exists( 'mrtu_vehicles' ) ) {
	/**
	 * Public vehicles (hidden ones excluded unless include_hidden), normalised:
	 * id, name, brand, category, status, verified (effective), price{day,week,month} null|[min,max],
	 * deposit null|[min,max], image_id, desc (requested language), desc_i18n, note ('' unless context=edit).
	 *
	 * Raw prices are data, not display: always render them with mrtu_price_display()/mrtu_price_html().
	 *
	 * @param array $args include_hidden (bool), category (string), lang (string|null), context (view|edit).
	 * @return array[]
	 */
	function mrtu_vehicles( $args = array() ) {
		return \MrTu\Multilang\Pricing::vehicles( $args );
	}
}

if ( ! function_exists( 'mrtu_price_display' ) ) {
	/**
	 * Display data for one price.
	 *
	 * @param string|array $vehicle Vehicle id or record from mrtu_vehicles().
	 * @param string       $period  day|week|month|deposit.
	 * @param string|null  $lang    Language code (null = current).
	 * @return array [ 'text' => '200.000 ₫', 'state' => 'verified'|'reference'|'on_request', 'note' => string, 'range' => array|null ]
	 */
	function mrtu_price_display( $vehicle, $period, $lang = null ) {
		return \MrTu\Multilang\Pricing::price_display( $vehicle, (string) $period, $lang );
	}
}

if ( ! function_exists( 'mrtu_format_vnd' ) ) {
	/**
	 * Formats VND: vi/de/es "150.000 ₫", en "₫150,000", fr "150 000 ₫" (U+202F); ranges with an en dash.
	 *
	 * @param int|array   $amount Amount or [min,max].
	 * @param string|null $lang   Language code (null = current).
	 * @return string
	 */
	function mrtu_format_vnd( $amount, $lang = null ) {
		return \MrTu\Multilang\Pricing::format_vnd( $amount, $lang );
	}
}

if ( ! function_exists( 'mrtu_pricing_meta' ) ) {
	/**
	 * Pricing meta.
	 *
	 * @return array [ 'verified' => bool, 'reviewed_at' => 'Y-m-d'|'', 'show_reference' => bool, 'currency' => 'VND', 'delivery_fee' => string ]
	 */
	function mrtu_pricing_meta() {
		return \MrTu\Multilang\Pricing::meta();
	}
}

if ( ! function_exists( 'mrtu_price_html' ) ) {
	/**
	 * Escaped price markup per docs/component-markup.md.
	 *
	 * @param string|array $vehicle Vehicle id or record.
	 * @param string       $period  day|week|month.
	 * @param string       $which   price|deposit.
	 * @return string
	 */
	function mrtu_price_html( $vehicle, $period, $which = 'price' ) {
		return \MrTu\Multilang\Pricing::price_html( $vehicle, (string) $period, (string) $which );
	}
}

if ( ! function_exists( 'mrtu_service_area_label' ) ) {
	/**
	 * Translated generic service-area label ("Old Quarter, Hanoi"), used while the address is not public.
	 *
	 * @param string|null $lang Language code (null = current).
	 * @return string
	 */
	function mrtu_service_area_label( $lang = null ) {
		return \MrTu\Multilang\Business::service_area_label( $lang );
	}
}
