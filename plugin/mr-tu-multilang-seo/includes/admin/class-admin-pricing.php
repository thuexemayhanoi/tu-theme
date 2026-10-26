<?php
/**
 * Admin screen "Prices" (slug mrtu-pricing): price-list status, delivery fee, reference-price setting,
 * per-model day/week/month/deposit ranges with confirmation, and a live preview of the public table.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang\Admin;

use MrTu\Multilang\Blocks;
use MrTu\Multilang\Business;
use MrTu\Multilang\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Prices screen.
 */
final class Admin_Pricing {

	const SLUG = 'mrtu-pricing';

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_filter( 'mrtu_admin_pages', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_head', array( __CLASS__, 'print_js_flag' ) );
		add_action( 'admin_post_mrtu_save_pricing', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Registers the screen.
	 *
	 * @param array $pages Pages.
	 * @return array
	 */
	public static function register_page( $pages ) {
		$pages[] = array(
			'slug'       => self::SLUG,
			'title'      => __( 'Prices', 'mr-tu-multilang-seo' ),
			'menu_title' => __( 'Prices', 'mr-tu-multilang-seo' ),
			'capability' => 'manage_options',
			'callback'   => array( __CLASS__, 'render' ),
			'position'   => 20,
		);
		return $pages;
	}

	/**
	 * Preview assets (public block CSS + tabs script).
	 */
	public static function enqueue() {
		if ( self::SLUG !== Admin_Business::current_page() ) {
			return;
		}
		wp_enqueue_style( 'mrtu-blocks' );
		wp_enqueue_script( 'mrtu-pricing' );
	}

	/**
	 * Progressive-enhancement flag for the preview (same as on the site).
	 */
	public static function print_js_flag() {
		if ( self::SLUG === Admin_Business::current_page() ) {
			wp_print_inline_script_tag( "document.documentElement.classList.add('mrtu-js');" );
		}
	}

	/**
	 * Renders the screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
		}
		$stored   = Business::get_pricing();
		$state    = Admin_Business::recall( 'pricing' );
		$data     = $state && isset( $state['data']['pricing'] ) ? $state['data']['pricing'] : $stored;
		$show_ref = $state && isset( $state['data']['show_reference'] ) ? (bool) $state['data']['show_reference'] : Pricing::show_reference();
		$errors   = $state ? (array) $state['errors'] : array();
		$err      = static function ( $key ) use ( $errors ) {
			return isset( $errors[ $key ] ) ? $errors[ $key ] : '';
		};

		Admin::page_open( __( 'Prices', 'mr-tu-multilang-seo' ), __( 'Rental prices and deposits per model. Unconfirmed prices never appear as fact: visitors see “Price on request” and a call button instead.', 'mr-tu-multilang-seo' ) );
		echo Admin::last_updated( $stored['updated_at'], $stored['updated_by'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by Admin::last_updated().
		Admin_Business::error_summary( $errors );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mrtu-form">';

		// Price list status.
		Admin::card_open( __( 'Price list', 'mr-tu-multilang-seo' ), __( 'Confirm the price list only after checking the real prices at the Mr Tu location.', 'mr-tu-multilang-seo' ) );
		Admin_Business::group_open( __( 'Price list confirmed', 'mr-tu-multilang-seo' ), (bool) $data['verified'] );
		Admin::toggle( 'pricing[verified]', __( 'Prices confirmed for Mr Tu', 'mr-tu-multilang-seo' ), (bool) $data['verified'], __( 'A model’s price is shown on the website only when this switch AND the model’s own switch below are on.', 'mr-tu-multilang-seo' ) );
		Admin_Business::input(
			array(
				'name'  => 'pricing[reviewed_at]',
				'label' => __( 'Date you last checked the prices', 'mr-tu-multilang-seo' ),
				'value' => $data['reviewed_at'],
				'type'  => 'date',
				'help'  => __( 'Shown as “Updated …” above the price table.', 'mr-tu-multilang-seo' ),
				'error' => $err( 'reviewed_at' ),
			)
		);
		Admin_Business::group_close();
		Admin::toggle( 'show_reference_prices', __( 'Show reference prices while not confirmed', 'mr-tu-multilang-seo' ), $show_ref, __( 'On: unconfirmed prices appear with the label “reference price, confirm before booking”. Off: they appear as “Price on request”.', 'mr-tu-multilang-seo' ) );
		Admin::field(
			array(
				'name'  => 'pricing[reference_source]',
				'label' => __( 'Where the reference prices come from (only visible here)', 'mr-tu-multilang-seo' ),
				'value' => $data['reference_source'],
				'type'  => 'textarea',
			)
		);
		Admin::card_close();

		// Delivery fee.
		Admin::card_open( __( 'Delivery fee', 'mr-tu-multilang-seo' ) );
		Admin_Business::group_open( __( 'Delivery / pick-up fee', 'mr-tu-multilang-seo' ), (bool) $data['delivery_fee']['verified'], $data['delivery_fee']['note'] );
		Admin_Business::input(
			array(
				'name'  => 'pricing[delivery_fee][value]',
				'label' => __( 'Delivery / pick-up fee', 'mr-tu-multilang-seo' ),
				'value' => $data['delivery_fee']['value'],
				'help'  => __( 'Short text, for example: free delivery inside the Old Quarter.', 'mr-tu-multilang-seo' ),
				'error' => $err( 'delivery_fee.value' ),
			)
		);
		Admin_Business::verify_toggle( 'pricing[delivery_fee][verified]', (bool) $data['delivery_fee']['verified'] );
		Admin_Business::group_close();
		Admin::card_close();

		// Per model.
		Admin::card_open( __( 'Prices per model', 'mr-tu-multilang-seo' ), __( 'Amounts in VND without dots, for example 150000. Leave “to” empty for a single price, and both empty when there is no price.', 'mr-tu-multilang-seo' ) );
		if ( ! $data['vehicles'] ) {
			echo '<p>' . esc_html__( 'No vehicles yet.', 'mr-tu-multilang-seo' ) . ' <a href="' . esc_url( Admin::url( Admin_Vehicles::SLUG, array( 'edit' => 'new' ) ) ) . '">' . esc_html__( 'Add a vehicle', 'mr-tu-multilang-seo' ) . '</a></p>';
		}
		$periods = array(
			'day'   => __( 'Per day', 'mr-tu-multilang-seo' ),
			'week'  => __( 'Per week', 'mr-tu-multilang-seo' ),
			'month' => __( 'Per month', 'mr-tu-multilang-seo' ),
		);
		foreach ( $data['vehicles'] as $i => $vehicle ) {
			$base = 'pricing[vehicles][' . $i . ']';
			$key  = 'vehicles.' . $i . '.';
			echo '<div class="mrtu-model" data-mrtu-group id="' . esc_attr( 'mrtu-model-' . $vehicle['id'] ) . '">';
			echo '<h3 class="mrtu-model__title">' . esc_html( $vehicle['name'] ) . '</h3>';
			echo '<p class="mrtu-model__meta">' . Admin_Business::live_badge( (bool) $vehicle['verified'] ) . ' ' . Admin::status( 'hidden' === $vehicle['status'] ? 'info' : 'ok', Pricing::status_label( $vehicle['status'] ) ) . ' <span class="mrtu-meta">' . esc_html( self::public_state( $vehicle['id'] ) ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badges escape.
			echo '<input type="hidden" name="' . esc_attr( $base . '[id]' ) . '" value="' . esc_attr( $vehicle['id'] ) . '">';
			echo '<div class="mrtu-grid mrtu-grid--2">';
			foreach ( $periods as $period => $label ) {
				self::range_inputs( $base . '[price][' . $period . ']', $label, $vehicle['price'][ $period ], $err( $key . 'price.' . $period ) );
			}
			self::range_inputs( $base . '[deposit]', __( 'Deposit', 'mr-tu-multilang-seo' ), $vehicle['deposit'], $err( $key . 'deposit' ) );
			echo '</div>';
			Admin::toggle( $base . '[verified]', __( 'Price confirmed for this model', 'mr-tu-multilang-seo' ), (bool) $vehicle['verified'] );
			echo '</div>';
		}
		Admin::card_close();

		Admin::save_bar( 'mrtu_save_pricing' );
		echo '</form>';

		Admin::card_open( __( 'Preview on the website', 'mr-tu-multilang-seo' ), __( 'This is how the saved prices look on the website right now (Vietnamese number format). Save first to see your changes here.', 'mr-tu-multilang-seo' ) );
		$preview = Blocks::render(
			'pricing-table',
			array(
				'period'      => 'day',
				'showDeposit' => true,
			)
		);
		echo '<div class="mrtu-preview">' . $preview . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the renderer.
		Admin::card_close();

		Admin::page_close();
	}

	/**
	 * What visitors see now for a model.
	 *
	 * @param string $id Vehicle id.
	 * @return string
	 */
	private static function public_state( $id ) {
		$vehicle = Pricing::find_vehicle( $id );
		if ( ! $vehicle ) {
			return '';
		}
		if ( 'hidden' === $vehicle['status'] ) {
			return __( 'Not on the website.', 'mr-tu-multilang-seo' );
		}
		$display = Pricing::price_display( $vehicle, 'day', 'vi' );
		if ( 'verified' === $display['state'] ) {
			return __( 'Website: price shown.', 'mr-tu-multilang-seo' );
		}
		if ( 'reference' === $display['state'] ) {
			return __( 'Website: reference price with a “confirm before booking” label.', 'mr-tu-multilang-seo' );
		}
		return __( 'Website: “Price on request”.', 'mr-tu-multilang-seo' );
	}

	/**
	 * From / to inputs of one range.
	 *
	 * @param string     $base  Input base name.
	 * @param string     $label Legend.
	 * @param array|null $value Range ([min,max] or {min,max}).
	 * @param string     $error Error message.
	 */
	private static function range_inputs( $base, $label, $value, $error ) {
		$min = '';
		$max = '';
		if ( is_array( $value ) ) {
			if ( array_key_exists( 'min', $value ) || array_key_exists( 'max', $value ) ) {
				$min = (string) Business::get( $value, 'min' );
				$max = (string) Business::get( $value, 'max' );
			} else {
				$values = array_values( $value );
				$min    = isset( $values[0] ) ? (string) $values[0] : '';
				$max    = ( isset( $values[1] ) && $values[1] !== $values[0] ) ? (string) $values[1] : '';
			}
		}
		$error_id = Admin_Business::field_id( $base ) . '-error';
		$attrs    = array(
			'inputmode' => 'numeric',
			'min'       => '0',
			'step'      => '1',
		);
		if ( '' !== $error ) {
			$attrs['aria-invalid']     = 'true';
			$attrs['aria-describedby'] = $error_id;
		}
		echo '<fieldset class="mrtu-range' . ( '' !== $error ? ' mrtu-has-error' : '' ) . '"><legend class="mrtu-field__label">' . esc_html( $label ) . '</legend><div class="mrtu-range__inputs">';
		Admin::field(
			array(
				'name'  => $base . '[min]',
				'label' => __( 'From (VND)', 'mr-tu-multilang-seo' ),
				'value' => $min,
				'type'  => 'number',
				'attrs' => $attrs,
			)
		);
		Admin::field(
			array(
				'name'  => $base . '[max]',
				'label' => __( 'To (optional)', 'mr-tu-multilang-seo' ),
				'value' => $max,
				'type'  => 'number',
				'attrs' => $attrs,
			)
		);
		echo '</div>';
		if ( '' !== $error ) {
			echo '<p class="mrtu-field__error" id="' . esc_attr( $error_id ) . '">' . esc_html( $error ) . '</p>';
		}
		echo '</fieldset>';
	}

	/**
	 * Saves the form.
	 */
	public static function handle_save() {
		Admin::verify_request( 'mrtu_save_pricing' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce + capability verified by Admin::verify_request() above.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised and validated in Pricing::normalize().
		$submitted = isset( $_POST['pricing'] ) && is_array( $_POST['pricing'] ) ? wp_unslash( $_POST['pricing'] ) : array();
		$show_ref  = isset( $_POST['show_reference_prices'] ) && Business::flag( sanitize_text_field( wp_unslash( $_POST['show_reference_prices'] ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$merged = self::merge( Business::get_pricing(), $submitted );
		$result = Business::save_pricing( $merged );
		$url    = Admin::url( self::SLUG );

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			Admin_Business::remember(
				'pricing',
				array(
					'pricing'        => $data['data'],
					'show_reference' => $show_ref,
				),
				$data['errors']
			);
			Admin::redirect_with_notice( $url, __( 'Nothing was saved. Please fix the highlighted fields.', 'mr-tu-multilang-seo' ), 'error' );
		}
		$settings_changed = self::save_show_reference( $show_ref );
		if ( 'unchanged' === $result && ! $settings_changed ) {
			Admin::redirect_with_notice( $url, __( 'No changes to save.', 'mr-tu-multilang-seo' ), 'info' );
		}
		Admin::redirect_with_notice( $url, __( 'Prices saved. The website is updated in all languages.', 'mr-tu-multilang-seo' ) );
	}

	/**
	 * Merges the submitted price fields into the stored record (vehicle names, photos and texts stay untouched).
	 *
	 * @param array $stored    Stored pricing.
	 * @param array $submitted Submitted fields.
	 * @return array
	 */
	public static function merge( $stored, $submitted ) {
		$merged = $stored;
		foreach ( array( 'verified', 'reviewed_at', 'reference_source' ) as $key ) {
			if ( array_key_exists( $key, $submitted ) ) {
				$merged[ $key ] = $submitted[ $key ];
			}
		}
		$fee = Business::rec( $submitted, 'delivery_fee' );
		foreach ( array( 'value', 'verified' ) as $key ) {
			if ( array_key_exists( $key, $fee ) ) {
				$merged['delivery_fee'][ $key ] = $fee[ $key ];
			}
		}
		$rows = array();
		foreach ( (array) Business::get( $submitted, 'vehicles', array() ) as $row ) {
			if ( is_array( $row ) && isset( $row['id'] ) && is_scalar( $row['id'] ) ) {
				$rows[ sanitize_key( (string) $row['id'] ) ] = $row;
			}
		}
		foreach ( $merged['vehicles'] as $i => $vehicle ) {
			if ( ! isset( $rows[ $vehicle['id'] ] ) ) {
				continue;
			}
			$row    = $rows[ $vehicle['id'] ];
			$prices = Business::rec( $row, 'price' );
			foreach ( Pricing::PERIODS as $period ) {
				$merged['vehicles'][ $i ]['price'][ $period ] = Business::get( $prices, $period, null );
			}
			$merged['vehicles'][ $i ]['deposit'] = Business::get( $row, 'deposit', null );
			if ( array_key_exists( 'verified', $row ) ) {
				$merged['vehicles'][ $i ]['verified'] = $row['verified'];
			}
		}
		return $merged;
	}

	/**
	 * Writes mrtu_ml_settings['show_reference_prices'] (merging the settings array).
	 *
	 * @param bool $value New value.
	 * @return bool Whether it changed.
	 */
	public static function save_show_reference( $value ) {
		$settings = get_option( 'mrtu_ml_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		if ( ! empty( $settings['show_reference_prices'] ) === (bool) $value ) {
			return false;
		}
		$settings['show_reference_prices'] = (bool) $value;
		update_option( 'mrtu_ml_settings', $settings, true );
		return true;
	}
}
