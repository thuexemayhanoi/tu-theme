<?php
/**
 * Admin screen "Vehicles" (slug mrtu-vehicles): list with add / edit / delete / move up-down,
 * edit form with photo (media library), type, status and a description per language.
 * Prices are edited on the Prices screen; both screens write the same `mrtu_pricing` option.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang\Admin;

use MrTu\Multilang\Business;
use MrTu\Multilang\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Vehicles screen.
 */
final class Admin_Vehicles {

	const SLUG = 'mrtu-vehicles';

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_filter( 'mrtu_admin_pages', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_mrtu_save_vehicle', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_mrtu_vehicles_op', array( __CLASS__, 'handle_op' ) );
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
			'title'      => __( 'Vehicles', 'mr-tu-multilang-seo' ),
			'menu_title' => __( 'Vehicles', 'mr-tu-multilang-seo' ),
			'capability' => 'manage_options',
			'callback'   => array( __CLASS__, 'render' ),
			'position'   => 25,
		);
		return $pages;
	}

	/**
	 * Media library only on this screen.
	 */
	public static function enqueue() {
		if ( self::SLUG === Admin_Business::current_page() ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Native language names for the description tabs.
	 *
	 * @return array code => name
	 */
	private static function language_names() {
		$names = array(
			'vi' => 'Tiếng Việt',
			'en' => 'English',
			'de' => 'Deutsch',
			'fr' => 'Français',
			'es' => 'Español',
		);
		if ( function_exists( 'mrtu_languages' ) ) {
			foreach ( (array) mrtu_languages( false ) as $code => $language ) {
				if ( isset( $names[ $code ] ) && is_array( $language ) && ! empty( $language['name'] ) ) {
					$names[ $code ] = (string) $language['name'];
				}
			}
		}
		return $names;
	}

	/**
	 * Renders the list or the edit form.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen routing.
		$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
		if ( '' !== $edit ) {
			self::render_edit( $edit );
			return;
		}
		self::render_list();
	}

	/**
	 * Vehicle list.
	 */
	private static function render_list() {
		$pricing  = Business::get_pricing();
		$vehicles = $pricing['vehicles'];
		$last     = count( $vehicles ) - 1;

		Admin::page_open( __( 'Vehicles', 'mr-tu-multilang-seo' ), __( 'The motorbike models Mr Tu rents out. Hidden models never appear on the website. Prices are set on the Prices screen.', 'mr-tu-multilang-seo' ) );
		echo Admin::last_updated( $pricing['updated_at'], $pricing['updated_by'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by Admin::last_updated().

		Admin::card_open( __( 'Models', 'mr-tu-multilang-seo' ), __( 'The order here is the order on the website.', 'mr-tu-multilang-seo' ) );
		if ( ! $vehicles ) {
			echo '<p>' . esc_html__( 'No vehicles yet.', 'mr-tu-multilang-seo' ) . '</p>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'mrtu_vehicles_op', '_mrtu_nonce' );
			echo '<input type="hidden" name="action" value="mrtu_vehicles_op"><ul class="mrtu-list">';
			foreach ( $vehicles as $i => $vehicle ) {
				$thumb = $vehicle['image_id'] ? wp_get_attachment_image( $vehicle['image_id'], array( 64, 64 ), false, array( 'class' => 'mrtu-list__thumb' ) ) : '';
				echo '<li class="mrtu-list__item"><div class="mrtu-list__main">';
				echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
				echo '<div><strong class="mrtu-list__title">' . esc_html( $vehicle['name'] ) . '</strong>';
				echo '<span class="mrtu-list__meta">' . esc_html( Pricing::category_label( $vehicle['category'] ) ) . ' · ' . esc_html( $vehicle['id'] ) . '</span>';
				$confirmed = Pricing::is_verified( $vehicle );
				echo '<span class="mrtu-list__badges">' . Admin::status( 'hidden' === $vehicle['status'] ? 'info' : 'ok', Pricing::status_label( $vehicle['status'] ) ) . ' ' . Admin::badge( $confirmed, $confirmed ? __( 'Price confirmed', 'mr-tu-multilang-seo' ) : __( 'Price PENDING VERIFICATION', 'mr-tu-multilang-seo' ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badges escape.
				echo '</div></div><div class="mrtu-actions">';
				echo '<a class="button" href="' . esc_url( Admin::url( self::SLUG, array( 'edit' => $vehicle['id'] ) ) ) . '">' . esc_html__( 'Edit', 'mr-tu-multilang-seo' ) . '</a>';
				/* translators: %s: vehicle name. */
				echo '<button type="submit" class="button" name="mrtu_op" value="' . esc_attr( 'up:' . $vehicle['id'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Move %s up', 'mr-tu-multilang-seo' ), $vehicle['name'] ) ) . '"' . disabled( 0 === $i, true, false ) . '>&uarr;</button>';
				/* translators: %s: vehicle name. */
				echo '<button type="submit" class="button" name="mrtu_op" value="' . esc_attr( 'down:' . $vehicle['id'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Move %s down', 'mr-tu-multilang-seo' ), $vehicle['name'] ) ) . '"' . disabled( $last === $i, true, false ) . '>&darr;</button>';
				/* translators: %s: vehicle name. */
				echo '<button type="submit" class="button button-link-delete mrtu-delete" name="mrtu_op" value="' . esc_attr( 'delete:' . $vehicle['id'] ) . '" data-mrtu-confirm="' . esc_attr( sprintf( __( 'Delete %s? You can undo this in Store information → Backup & restore.', 'mr-tu-multilang-seo' ), $vehicle['name'] ) ) . '">' . esc_html__( 'Delete', 'mr-tu-multilang-seo' ) . '</button>';
				echo '</div></li>';
			}
			echo '</ul></form>';
		}
		echo '<p><a class="button button-primary" href="' . esc_url( Admin::url( self::SLUG, array( 'edit' => 'new' ) ) ) . '">' . esc_html__( 'Add vehicle', 'mr-tu-multilang-seo' ) . '</a></p>';
		Admin::card_close();
		Admin::page_close();
	}

	/**
	 * Edit / add form.
	 *
	 * @param string $edit Vehicle id or "new".
	 */
	private static function render_edit( $edit ) {
		$is_new  = 'new' === $edit;
		$vehicle = $is_new ? Pricing::vehicle_defaults() : null;
		if ( ! $is_new ) {
			foreach ( Business::get_pricing()['vehicles'] as $item ) {
				if ( $item['id'] === $edit ) {
					$vehicle = $item;
					break;
				}
			}
		}
		$original = $is_new ? '' : $edit;
		$errors   = array();
		$state    = Admin_Business::recall( 'vehicle' );
		if ( $state && isset( $state['data']['original_id'], $state['data']['vehicle'] ) && $state['data']['original_id'] === $original ) {
			$vehicle = array_merge( Pricing::vehicle_defaults(), (array) $state['data']['vehicle'] );
			$errors  = (array) $state['errors'];
		}

		$back = '<p><a href="' . esc_url( Admin::url( self::SLUG ) ) . '">&larr; ' . esc_html__( 'All vehicles', 'mr-tu-multilang-seo' ) . '</a></p>';
		if ( ! $vehicle ) {
			Admin::page_open( __( 'Vehicles', 'mr-tu-multilang-seo' ) );
			echo '<div class="notice notice-error"><p>' . esc_html__( 'This vehicle no longer exists.', 'mr-tu-multilang-seo' ) . '</p></div>' . $back; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			Admin::page_close();
			return;
		}
		$err = static function ( $key ) use ( $errors ) {
			return isset( $errors[ $key ] ) ? $errors[ $key ] : '';
		};

		/* translators: %s: vehicle name. */
		Admin::page_open( $is_new ? __( 'Add vehicle', 'mr-tu-multilang-seo' ) : sprintf( __( 'Edit %s', 'mr-tu-multilang-seo' ), $vehicle['name'] ) );
		$pricing = Business::get_pricing();
		echo Admin::last_updated( $pricing['updated_at'], $pricing['updated_by'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by Admin::last_updated().
		echo $back; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		Admin_Business::error_summary( $errors );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mrtu-form">';
		echo '<input type="hidden" name="original_id" value="' . esc_attr( $original ) . '">';

		Admin::card_open( __( 'Model', 'mr-tu-multilang-seo' ) );
		echo '<div class="mrtu-grid mrtu-grid--2">';
		Admin_Business::input(
			array(
				'name'     => 'vehicle[name]',
				'label'    => __( 'Model name', 'mr-tu-multilang-seo' ),
				'value'    => $vehicle['name'],
				'help'     => __( 'For example: Honda Vision', 'mr-tu-multilang-seo' ),
				'required' => true,
				'error'    => $err( 'name' ),
			)
		);
		Admin_Business::input(
			array(
				'name'  => 'vehicle[brand]',
				'label' => __( 'Brand', 'mr-tu-multilang-seo' ),
				'value' => $vehicle['brand'],
				'error' => $err( 'brand' ),
			)
		);
		self::select( 'vehicle[category]', __( 'Type', 'mr-tu-multilang-seo' ), $vehicle['category'], self::category_options(), $err( 'category' ) );
		self::select( 'vehicle[status]', __( 'Status', 'mr-tu-multilang-seo' ), $vehicle['status'], self::status_options(), $err( 'status' ), __( 'Hidden: kept here but never shown on the website.', 'mr-tu-multilang-seo' ) );
		Admin_Business::input(
			array(
				'name'  => 'vehicle[id]',
				'label' => __( 'Short ID', 'mr-tu-multilang-seo' ),
				'value' => $vehicle['id'],
				'help'  => __( 'Used internally, for example honda-vision. Lowercase letters, numbers and dashes. Leave empty to create it from the name.', 'mr-tu-multilang-seo' ),
				'error' => $err( 'id' ),
				'attrs' => array(
					'autocapitalize' => 'none',
					'spellcheck'     => 'false',
				),
			)
		);
		echo '</div>';
		Admin::card_close();

		Admin::card_open( __( 'Photo', 'mr-tu-multilang-seo' ), __( 'A real photo of this model. Without a photo the website shows a licence-plate style label.', 'mr-tu-multilang-seo' ) );
		$image_id = absint( $vehicle['image_id'] );
		echo '<div class="mrtu-media" data-mrtu-media-field>';
		echo '<input type="hidden" name="vehicle[image_id]" value="' . esc_attr( (string) $image_id ) . '">';
		echo '<div class="mrtu-media__preview" data-mrtu-media-preview>' . ( $image_id ? wp_get_attachment_image( $image_id, 'medium' ) : '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image markup.
		echo '<div class="mrtu-actions">';
		echo '<button type="button" class="button" data-mrtu-media="select" data-title="' . esc_attr__( 'Choose a photo', 'mr-tu-multilang-seo' ) . '" data-button="' . esc_attr__( 'Use this photo', 'mr-tu-multilang-seo' ) . '">' . esc_html__( 'Choose photo', 'mr-tu-multilang-seo' ) . '</button>';
		echo '<button type="button" class="button" data-mrtu-media="remove"' . ( $image_id ? '' : ' hidden' ) . '>' . esc_html__( 'Remove photo', 'mr-tu-multilang-seo' ) . '</button>';
		echo '</div></div>';
		Admin::card_close();

		Admin::card_open( __( 'Description', 'mr-tu-multilang-seo' ), __( 'One or two sentences per language (optional). Leave a language empty instead of pasting another language.', 'mr-tu-multilang-seo' ) );
		$first = true;
		foreach ( self::language_names() as $code => $language ) {
			$text = isset( $vehicle['desc_i18n'][ $code ] ) ? (string) $vehicle['desc_i18n'][ $code ] : '';
			echo '<details class="mrtu-lang"' . ( $first ? ' open' : '' ) . '><summary class="mrtu-lang__summary"><span>' . esc_html( $language . ' (' . strtoupper( $code ) . ')' ) . '</span> ';
			echo '' !== $text ? Admin::status( 'ok', __( 'Written', 'mr-tu-multilang-seo' ) ) : Admin::status( 'info', __( 'Empty', 'mr-tu-multilang-seo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge escapes.
			echo '</summary>';
			Admin::field(
				array(
					'name'  => 'vehicle[desc_i18n][' . $code . ']',
					/* translators: %s: language name. */
					'label' => sprintf( __( 'Description in %s', 'mr-tu-multilang-seo' ), $language ),
					'value' => $text,
					'type'  => 'textarea',
					'attrs' => array(
						'lang'      => $code,
						'maxlength' => '1000',
					),
				)
			);
			echo '</details>';
			$first = false;
		}
		Admin::card_close();

		Admin::card_open( __( 'Admin note', 'mr-tu-multilang-seo' ) );
		Admin::field(
			array(
				'name'  => 'vehicle[note]',
				'label' => __( 'Note (only visible here)', 'mr-tu-multilang-seo' ),
				'value' => $vehicle['note'],
				'type'  => 'textarea',
			)
		);
		Admin::card_close();

		Admin::save_bar( 'mrtu_save_vehicle', __( 'Save vehicle', 'mr-tu-multilang-seo' ) );
		echo '</form>';
		Admin::page_close();
	}

	/**
	 * Select field with label, help and inline error.
	 *
	 * @param string $name    Name.
	 * @param string $label   Label.
	 * @param string $value   Current value.
	 * @param array  $options value => label.
	 * @param string $error   Error.
	 * @param string $help    Help.
	 */
	private static function select( $name, $label, $value, $options, $error = '', $help = '' ) {
		$id = Admin_Business::field_id( $name );
		echo '<div class="mrtu-field' . ( '' !== $error ? ' mrtu-has-error' : '' ) . '"><label class="mrtu-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		echo '<select class="mrtu-input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( '' !== $error ? ' aria-invalid="true" aria-describedby="' . esc_attr( $id . '-error' ) . '"' : '' ) . '>';
		foreach ( $options as $option => $text ) {
			echo '<option value="' . esc_attr( $option ) . '"' . selected( $value, $option, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select>';
		if ( '' !== $help ) {
			echo '<p class="mrtu-field__help">' . esc_html( $help ) . '</p>';
		}
		if ( '' !== $error ) {
			echo '<p class="mrtu-field__error" id="' . esc_attr( $id . '-error' ) . '">' . esc_html( $error ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Type options.
	 *
	 * @return array
	 */
	private static function category_options() {
		$out = array();
		foreach ( Pricing::CATEGORIES as $category ) {
			$out[ $category ] = Pricing::category_label( $category );
		}
		return $out;
	}

	/**
	 * Status options.
	 *
	 * @return array
	 */
	private static function status_options() {
		$out = array();
		foreach ( Pricing::STATUSES as $status ) {
			$out[ $status ] = Pricing::status_label( $status );
		}
		return $out;
	}

	/**
	 * Saves one vehicle (add or edit).
	 */
	public static function handle_save() {
		Admin::verify_request( 'mrtu_save_vehicle' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce + capability verified by Admin::verify_request() above.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised and validated in Pricing::normalize().
		$submitted = isset( $_POST['vehicle'] ) && is_array( $_POST['vehicle'] ) ? wp_unslash( $_POST['vehicle'] ) : array();
		$original  = isset( $_POST['original_id'] ) ? sanitize_key( wp_unslash( $_POST['original_id'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$pricing = Business::get_pricing();
		$index   = null;
		foreach ( $pricing['vehicles'] as $i => $vehicle ) {
			if ( '' !== $original && $vehicle['id'] === $original ) {
				$index = $i;
				break;
			}
		}
		if ( '' !== $original && null === $index ) {
			Admin::redirect_with_notice( Admin::url( self::SLUG ), __( 'This vehicle no longer exists.', 'mr-tu-multilang-seo' ), 'error' );
		}

		$record = null === $index ? Pricing::vehicle_defaults() : $pricing['vehicles'][ $index ];
		foreach ( array( 'id', 'name', 'brand', 'category', 'status', 'image_id', 'note' ) as $field ) {
			if ( array_key_exists( $field, $submitted ) ) {
				$record[ $field ] = $submitted[ $field ];
			}
		}
		$desc = Business::rec( $submitted, 'desc_i18n' );
		foreach ( Business::LANGS as $lang ) {
			if ( array_key_exists( $lang, $desc ) ) {
				$record['desc_i18n'][ $lang ] = $desc[ $lang ];
			}
		}
		$image_id = absint( $record['image_id'] );
		if ( $image_id && ! wp_attachment_is_image( $image_id ) ) {
			$record['image_id'] = 0;
		}

		if ( null === $index ) {
			$pricing['vehicles'][] = $record;
			$index                 = count( $pricing['vehicles'] ) - 1;
		} else {
			$pricing['vehicles'][ $index ] = $record;
		}

		$result = Business::save_pricing( $pricing );
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$prefix = 'vehicles.' . $index . '.';
			$errors = array();
			foreach ( $data['errors'] as $key => $message ) {
				$is_own = 0 === strpos( $key, $prefix );
				$errors[ $is_own ? substr( $key, strlen( $prefix ) ) : $key ] = $message;
			}
			$redisplay = isset( $data['data']['vehicles'][ $index ] ) ? $data['data']['vehicles'][ $index ] : $record;
			Admin_Business::remember(
				'vehicle',
				array(
					'vehicle'     => $redisplay,
					'original_id' => $original,
				),
				$errors
			);
			Admin::redirect_with_notice( Admin::url( self::SLUG, array( 'edit' => '' === $original ? 'new' : $original ) ), __( 'Nothing was saved. Please fix the highlighted fields.', 'mr-tu-multilang-seo' ), 'error' );
		}
		if ( 'unchanged' === $result ) {
			Admin::redirect_with_notice( Admin::url( self::SLUG ), __( 'No changes to save.', 'mr-tu-multilang-seo' ), 'info' );
		}
		Admin::redirect_with_notice( Admin::url( self::SLUG ), __( 'Vehicle saved. Set or check its prices on the Prices screen.', 'mr-tu-multilang-seo' ) );
	}

	/**
	 * Move up / move down / delete.
	 */
	public static function handle_op() {
		Admin::verify_request( 'mrtu_vehicles_op' );
		$op      = isset( $_POST['mrtu_op'] ) ? sanitize_text_field( wp_unslash( $_POST['mrtu_op'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by Admin::verify_request().
		$parts   = explode( ':', $op, 2 );
		$action  = $parts[0];
		$id      = isset( $parts[1] ) ? sanitize_key( $parts[1] ) : '';
		$pricing = Business::get_pricing();
		$url     = Admin::url( self::SLUG );
		$index   = null;
		foreach ( $pricing['vehicles'] as $i => $vehicle ) {
			if ( $vehicle['id'] === $id ) {
				$index = $i;
				break;
			}
		}
		if ( null === $index || ! in_array( $action, array( 'up', 'down', 'delete' ), true ) ) {
			Admin::redirect_with_notice( $url, __( 'This vehicle no longer exists.', 'mr-tu-multilang-seo' ), 'error' );
		}
		$list = $pricing['vehicles'];
		if ( 'delete' === $action ) {
			array_splice( $list, $index, 1 );
			$message = __( 'Vehicle deleted. You can undo this in Store information → Backup & restore.', 'mr-tu-multilang-seo' );
		} else {
			$target = 'up' === $action ? $index - 1 : $index + 1;
			if ( $target < 0 || $target >= count( $list ) ) {
				Admin::redirect_with_notice( $url, __( 'No changes to save.', 'mr-tu-multilang-seo' ), 'info' );
			}
			$moved           = $list[ $index ];
			$list[ $index ]  = $list[ $target ];
			$list[ $target ] = $moved;
			$message         = __( 'Order updated.', 'mr-tu-multilang-seo' );
		}
		$pricing['vehicles'] = array_values( $list );
		$result              = Business::save_pricing( $pricing );
		if ( is_wp_error( $result ) ) {
			Admin::redirect_with_notice( $url, $result->get_error_message(), 'error' );
		}
		Admin::redirect_with_notice( $url, $message );
	}
}
