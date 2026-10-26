<?php
/**
 * Admin screen "Quick Call" (slug mrtu-quick-contact): the floating contact dock — on/off, which
 * buttons it may show, side, collapse on scroll — and why each button is (not) shown right now.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang\Admin;

use MrTu\Multilang\Quick_Contact;

defined( 'ABSPATH' ) || exit;

/**
 * Quick Call screen.
 */
final class Admin_Quick_Contact {

	const SLUG = 'mrtu-quick-contact';

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_filter( 'mrtu_admin_pages', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_post_mrtu_save_quick_contact', array( __CLASS__, 'handle_save' ) );
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
			'title'      => __( 'Quick Call', 'mr-tu-multilang-seo' ),
			'menu_title' => __( 'Quick Call', 'mr-tu-multilang-seo' ),
			'capability' => 'manage_options',
			'callback'   => array( __CLASS__, 'render' ),
			'position'   => 60,
		);
		return $pages;
	}

	/**
	 * Item labels for the owner.
	 *
	 * @return array
	 */
	private static function item_labels() {
		return array(
			'call'       => __( 'Call', 'mr-tu-multilang-seo' ),
			'whatsapp'   => 'WhatsApp',
			'zalo'       => 'Zalo',
			'directions' => __( 'Directions (Google Maps)', 'mr-tu-multilang-seo' ),
			'chat'       => __( 'Ask a question (Mr Tu assistant)', 'mr-tu-multilang-seo' ),
			'contact'    => __( 'Contact page', 'mr-tu-multilang-seo' ),
		);
	}

	/**
	 * Renders the screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
		}
		$settings = Quick_Contact::settings();
		$status   = Quick_Contact::item_status();
		$labels   = self::item_labels();
		$fix_url  = Admin::url( Admin_Business::SLUG );

		Admin::page_open( __( 'Quick Call', 'mr-tu-multilang-seo' ), __( 'The round contact button in the bottom corner of every page. Each button inside appears only when its channel is verified in Store information.', 'mr-tu-multilang-seo' ) );
		echo Admin::last_updated( $settings['updated_at'], $settings['updated_by'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by Admin::last_updated().

		// What visitors see now.
		$live = array();
		if ( $settings['enabled'] ) {
			foreach ( $status as $item => $row ) {
				if ( $row['on'] && $row['publishable'] ) {
					$live[] = $labels[ $item ];
				}
			}
		}
		Admin::card_open( __( 'On the website now', 'mr-tu-multilang-seo' ) );
		if ( ! $settings['enabled'] ) {
			echo '<p>' . Admin::status( 'info', __( 'Switched off', 'mr-tu-multilang-seo' ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge escapes.
		} elseif ( ! $live ) {
			echo '<p>' . Admin::status( 'warn', __( 'Hidden', 'mr-tu-multilang-seo' ) ) . ' ' . esc_html__( 'No button is ready yet, so the dock is not shown. Verify the phone number in Store information.', 'mr-tu-multilang-seo' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge escapes.
		} else {
			/* translators: %s: comma-separated list of buttons. */
			echo '<p>' . Admin::status( 'ok', __( 'Shown', 'mr-tu-multilang-seo' ) ) . ' ' . esc_html( sprintf( __( 'Buttons: %s', 'mr-tu-multilang-seo' ), implode( ', ', $live ) ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge escapes.
		}
		Admin::card_close();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mrtu-form">';

		Admin::card_open( __( 'Dock', 'mr-tu-multilang-seo' ) );
		Admin::toggle( 'qc[enabled]', __( 'Show the Quick Call button', 'mr-tu-multilang-seo' ), $settings['enabled'] );
		echo '<fieldset class="mrtu-choices"><legend class="mrtu-field__label">' . esc_html__( 'Side of the screen', 'mr-tu-multilang-seo' ) . '</legend>';
		foreach ( array(
			'right' => __( 'Right', 'mr-tu-multilang-seo' ),
			'left'  => __( 'Left', 'mr-tu-multilang-seo' ),
		) as $value => $label ) {
			echo '<label class="mrtu-choice"><input type="radio" name="qc[position]" value="' . esc_attr( $value ) . '"' . checked( $settings['position'], $value, false ) . '> <span>' . esc_html( $label ) . '</span></label>';
		}
		echo '</fieldset>';
		Admin::toggle( 'qc[collapse_on_scroll]', __( 'Make it smaller while scrolling', 'mr-tu-multilang-seo' ), $settings['collapse_on_scroll'], __( 'After scrolling down, only the icon stays visible.', 'mr-tu-multilang-seo' ) );
		Admin::card_close();

		Admin::card_open( __( 'Buttons inside', 'mr-tu-multilang-seo' ), __( 'Switch on the buttons you want. A button switched on but not ready stays hidden until its channel is verified.', 'mr-tu-multilang-seo' ) );
		foreach ( $status as $item => $row ) {
			echo '<div class="mrtu-qc-item">';
			Admin::toggle( 'qc[items][' . $item . ']', $labels[ $item ], $row['on'] );
			echo '<p class="mrtu-qc-item__status">';
			if ( $row['publishable'] ) {
				echo Admin::status( $row['on'] ? 'ok' : 'info', $row['on'] ? __( 'Ready — shown on the website', 'mr-tu-multilang-seo' ) : __( 'Ready, but switched off', 'mr-tu-multilang-seo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge escapes.
			} else {
				echo Admin::status( 'warn', __( 'Not shown', 'mr-tu-multilang-seo' ) ) . ' <span>' . esc_html( $row['reason'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge escapes.
				if ( 'chat' !== $item && 'contact' !== $item ) {
					echo ' <a href="' . esc_url( $fix_url ) . '">' . esc_html__( 'Open Store information', 'mr-tu-multilang-seo' ) . '</a>';
				}
			}
			echo '</p></div>';
		}
		Admin::card_close();

		Admin::save_bar( 'mrtu_save_quick_contact' );
		echo '</form>';
		Admin::page_close();
	}

	/**
	 * Saves the settings.
	 */
	public static function handle_save() {
		Admin::verify_request( 'mrtu_save_quick_contact' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- sanitised in Quick_Contact::sanitize(); nonce verified by Admin::verify_request().
		$input  = isset( $_POST['qc'] ) && is_array( $_POST['qc'] ) ? wp_unslash( $_POST['qc'] ) : array();
		$result = Quick_Contact::save( $input, get_current_user_id() );
		$url    = Admin::url( self::SLUG );
		if ( 'unchanged' === $result ) {
			Admin::redirect_with_notice( $url, __( 'No changes to save.', 'mr-tu-multilang-seo' ), 'info' );
		}
		Admin::redirect_with_notice( $url, __( 'Quick Call settings saved.', 'mr-tu-multilang-seo' ) );
	}
}
