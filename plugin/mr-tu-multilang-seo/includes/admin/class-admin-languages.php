<?php
/**
 * "Languages" screen: enable/disable languages, URL prefixes, hreflang codes, taglines, x-default.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang\Admin;

use MrTu\Multilang\Languages;
use MrTu\Multilang\Translations;

defined( 'ABSPATH' ) || exit;

/**
 * Languages settings screen.
 */
final class Admin_Languages {

	const SLUG = 'mrtu-languages';

	const ACTION = 'mrtu_save_languages';

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_filter( 'mrtu_admin_pages', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'save' ) );
	}

	/**
	 * Registers the screen in the "Mr Tu" menu.
	 *
	 * @param array $pages Pages.
	 * @return array
	 */
	public static function register_page( $pages ) {
		$pages[] = array(
			'slug'       => self::SLUG,
			'title'      => __( 'Languages', 'mr-tu-multilang-seo' ),
			'menu_title' => __( 'Languages', 'mr-tu-multilang-seo' ),
			'capability' => 'manage_options',
			'callback'   => array( __CLASS__, 'render' ),
			'position'   => 40,
		);
		return $pages;
	}

	/**
	 * Renders the screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
		}
		$settings  = Languages::settings();
		$languages = Languages::all( false );
		$stats     = self::stats();

		Admin::page_open(
			__( 'Languages', 'mr-tu-multilang-seo' ),
			__( 'Vietnamese is the default language at “/”. Other languages live under their own prefix, for example “/en/”. Turning a language off hides it everywhere but keeps its content.', 'mr-tu-multilang-seo' )
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>';

		foreach ( $languages as $code => $lang ) {
			/* translators: 1: native language name, 2: language code. */
			$title = sprintf( __( '%1$s (%2$s)', 'mr-tu-multilang-seo' ), $lang['name'], strtoupper( $code ) );
			Admin::card_open( $title, '', 'mrtu-lang-' . $code );
			self::render_status( $code, $lang, $stats );

			if ( $lang['is_default'] ) {
				echo '<p class="mrtu-meta">' . esc_html__( 'Default language: always on, served at the site root without a prefix.', 'mr-tu-multilang-seo' ) . '</p>';
			} else {
				Admin::toggle(
					'languages[' . $code . '][enabled]',
					__( 'Show this language on the website', 'mr-tu-multilang-seo' ),
					$lang['enabled'],
					__( 'When off, its addresses return “not found” and it disappears from the language switcher, hreflang and sitemaps. Nothing is deleted.', 'mr-tu-multilang-seo' )
				);
				Admin::field(
					array(
						'name'  => 'languages[' . $code . '][prefix]',
						'label' => __( 'URL prefix', 'mr-tu-multilang-seo' ),
						'value' => $lang['prefix'],
						/* translators: %s: example address. */
						'help'  => sprintf( __( '2–5 lowercase letters. Addresses look like %s', 'mr-tu-multilang-seo' ), home_url( '/' . $lang['prefix'] . '/' ) ),
						'attrs' => array(
							'maxlength'      => '5',
							'pattern'        => '[a-z]{2,5}',
							'autocapitalize' => 'off',
							'autocomplete'   => 'off',
							'spellcheck'     => 'false',
						),
					)
				);
			}

			Admin::field(
				array(
					'name'  => 'languages[' . $code . '][hreflang]',
					'label' => __( 'hreflang code', 'mr-tu-multilang-seo' ),
					'value' => $lang['hreflang'],
					/* translators: %s: language code. */
					'help'  => sprintf( __( 'Tells search engines the language (and optionally the region) of these pages, e.g. “%s”. Also used for <html lang>.', 'mr-tu-multilang-seo' ), Languages::registry()[ $code ]['hreflang'] ),
					'attrs' => array(
						'maxlength'      => '12',
						'autocapitalize' => 'off',
						'autocomplete'   => 'off',
						'spellcheck'     => 'false',
					),
				)
			);
			Admin::field(
				array(
					'name'  => 'languages[' . $code . '][tagline]',
					'label' => __( 'Tagline', 'mr-tu-multilang-seo' ),
					'value' => $lang['tagline'],
					'help'  => $lang['is_default']
						? __( 'Short slogan in this language. Empty = the WordPress tagline (Settings › General).', 'mr-tu-multilang-seo' )
						: __( 'Short slogan in this language. Leave empty rather than showing a Vietnamese tagline on foreign pages.', 'mr-tu-multilang-seo' ),
					'attrs' => array(
						'maxlength'       => '200',
						'data-mrtu-count' => '120',
						'lang'            => $lang['hreflang'],
					),
				)
			);
			Admin::card_close();
		}

		Admin::card_open(
			__( 'Fallback language for other visitors (x-default)', 'mr-tu-multilang-seo' ),
			__( 'Search engines send visitors whose language is not offered to this version.', 'mr-tu-multilang-seo' ),
			'mrtu-lang-x-default'
		);
		echo '<div class="mrtu-field"><label class="mrtu-field__label" for="mrtu-x-default">' . esc_html__( 'x-default language', 'mr-tu-multilang-seo' ) . '</label>';
		echo '<select id="mrtu-x-default" name="x_default">';
		echo '<option value=""' . selected( '', $settings['x_default'], false ) . '>' . esc_html__( 'None (do not output x-default)', 'mr-tu-multilang-seo' ) . '</option>';
		foreach ( $languages as $code => $lang ) {
			if ( ! $lang['enabled'] ) {
				continue;
			}
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $code, $settings['x_default'], false ) . '>' . esc_html( $lang['name'] . ' (' . strtoupper( $code ) . ')' ) . '</option>';
		}
		echo '</select></div>';
		Admin::card_close();

		Admin::save_bar( self::ACTION );
		echo '</form>';
		Admin::page_close();
	}

	/**
	 * Status line of one language (home page + published content).
	 *
	 * @param string $code  Language code.
	 * @param array  $lang  Language.
	 * @param array  $stats Stats from stats().
	 */
	private static function render_status( $code, array $lang, array $stats ) {
		$badges = array();
		if ( ! $lang['enabled'] ) {
			$badges[] = Admin::status( 'info', __( 'Off', 'mr-tu-multilang-seo' ) );
		}
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$badges[] = ! empty( $stats['home'][ $code ] )
				? Admin::status( 'ok', __( 'Home page published', 'mr-tu-multilang-seo' ) )
				: Admin::status( 'warn', __( 'No published home page: its home address returns “not found”', 'mr-tu-multilang-seo' ) );
		}
		$count = isset( $stats['published'][ $code ] ) ? (int) $stats['published'][ $code ] : 0;
		/* translators: %s: number of published pages and posts. */
		$badges[] = Admin::status( $count ? 'info' : 'warn', sprintf( _n( '%s published page/post', '%s published pages/posts', $count, 'mr-tu-multilang-seo' ), number_format_i18n( $count ) ) );
		echo '<p class="mrtu-actions">' . implode( ' ', $badges ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badges are escaped by Admin::status().
	}

	/**
	 * Published content per language and home page availability.
	 *
	 * @return array [ 'published' => [ code => int ], 'home' => [ code => bool ] ]
	 */
	private static function stats() {
		$published = array();
		foreach ( Translations::post_types() as $type ) {
			foreach ( Admin_Translations::language_counts( $type, array( 'publish' ) ) as $code => $count ) {
				$published[ $code ] = ( isset( $published[ $code ] ) ? $published[ $code ] : 0 ) + $count;
			}
		}
		$home  = array();
		$front = (int) get_option( 'page_on_front' );
		foreach ( Languages::codes( false ) as $code ) {
			$home[ $code ] = $front && Translations::get_post_translation( $front, $code ) > 0;
		}
		return array(
			'published' => $published,
			'home'      => $home,
		);
	}

	/**
	 * Saves the submitted settings (admin-post).
	 */
	public static function save() {
		Admin::verify_request( self::ACTION, 'manage_options' );

		// Each value is sanitized and validated in Languages::validate().
		// Nonce verified by Admin::verify_request() above (WPCS cannot see through the helper).
		$languages = isset( $_POST['languages'] ) && is_array( $_POST['languages'] ) ? wp_unslash( $_POST['languages'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
		$x_default = isset( $_POST['x_default'] ) ? sanitize_key( wp_unslash( $_POST['x_default'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$result = Languages::validate(
			array(
				'languages' => $languages,
				'x_default' => $x_default,
			)
		);
		Languages::update_settings( $result['settings'] );

		$url = Admin::url( self::SLUG );
		if ( $result['errors'] ) {
			/* translators: %s: list of validation errors. */
			Admin::redirect_with_notice( $url, sprintf( __( 'Saved, except: %s', 'mr-tu-multilang-seo' ), implode( ' ', $result['errors'] ) ), 'error' );
		}
		Admin::redirect_with_notice( $url, __( 'Language settings saved.', 'mr-tu-multilang-seo' ) );
	}
}
