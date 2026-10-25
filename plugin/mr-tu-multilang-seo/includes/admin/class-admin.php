<?php
/**
 * "Mr Tu" admin menu registry + shared, mobile-first admin UI kit.
 *
 * Modules add their screens with the `mrtu_admin_pages` filter:
 *
 *     add_filter( 'mrtu_admin_pages', function ( $pages ) {
 *         $pages[] = array(
 *             'slug'       => 'mrtu-business',          // submenu slug (the dashboard uses 'mrtu')
 *             'title'      => __( 'Store information', 'mr-tu-multilang-seo' ),
 *             'menu_title' => __( 'Store info', 'mr-tu-multilang-seo' ),
 *             'capability' => 'manage_options',
 *             'callback'   => array( __CLASS__, 'render' ),
 *             'position'   => 10,
 *         );
 *         return $pages;
 *     } );
 *
 * Screens render with the helpers below so every page looks and behaves the same on an iPhone.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin menu + UI helpers.
 */
final class Admin {

	const MENU_SLUG = 'mrtu';

	/**
	 * Hook suffixes of our screens.
	 *
	 * @var string[]
	 */
	private static $hooks = array();

	/**
	 * Wires admin hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Registers the top-level menu and every module screen.
	 */
	public static function register_menu() {
		$pages = apply_filters( 'mrtu_admin_pages', array() );
		usort(
			$pages,
			static function ( $a, $b ) {
				return ( isset( $a['position'] ) ? (int) $a['position'] : 50 ) - ( isset( $b['position'] ) ? (int) $b['position'] : 50 );
			}
		);

		$dashboard = null;
		foreach ( $pages as $page ) {
			if ( self::MENU_SLUG === $page['slug'] ) {
				$dashboard = $page;
				break;
			}
		}

		self::$hooks[] = add_menu_page(
			__( 'Mr Tu', 'mr-tu-multilang-seo' ),
			__( 'Mr Tu', 'mr-tu-multilang-seo' ),
			$dashboard ? $dashboard['capability'] : 'edit_pages',
			self::MENU_SLUG,
			$dashboard ? $dashboard['callback'] : array( __CLASS__, 'render_fallback' ),
			self::menu_icon(),
			3
		);

		foreach ( $pages as $page ) {
			self::$hooks[] = add_submenu_page(
				self::MENU_SLUG,
				$page['title'],
				isset( $page['menu_title'] ) ? $page['menu_title'] : $page['title'],
				isset( $page['capability'] ) ? $page['capability'] : 'manage_options',
				$page['slug'],
				$page['callback']
			);
		}
	}

	/**
	 * Fallback dashboard when the dashboard module is unavailable.
	 */
	public static function render_fallback() {
		self::page_open( __( 'Mr Tu', 'mr-tu-multilang-seo' ), __( 'Control center for languages, SEO and business data.', 'mr-tu-multilang-seo' ) );
		self::page_close();
	}

	/**
	 * Inline SVG menu icon (kilometre stone) as a data URI.
	 *
	 * @return string
	 */
	private static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M10 1.5c-3.6 0-6.5 2.9-6.5 6.5v10.5h13V8c0-3.6-2.9-6.5-6.5-6.5zm-4.5 7h9v8.5h-9z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- static SVG icon.
	}

	/**
	 * Whether the current screen belongs to the plugin (or is a post editor/list we decorate).
	 *
	 * @param string $hook Current admin hook suffix.
	 * @return bool
	 */
	public static function is_our_screen( $hook ) {
		if ( in_array( $hook, self::$hooks, true ) ) {
			return true;
		}
		return in_array( $hook, array( 'edit.php', 'post.php', 'post-new.php', 'edit-tags.php', 'term.php' ), true );
	}

	/**
	 * Enqueues the shared admin stylesheet and script.
	 *
	 * @param string $hook Current admin hook suffix.
	 */
	public static function enqueue( $hook ) {
		if ( ! self::is_our_screen( $hook ) ) {
			return;
		}
		wp_enqueue_style( 'mrtu-admin', MRTU_ML_URL . 'assets/admin/admin.css', array(), MRTU_ML_VERSION );
		wp_enqueue_script( 'mrtu-admin', MRTU_ML_URL . 'assets/admin/admin.js', array(), MRTU_ML_VERSION, true );
	}

	/**
	 * Adds a body class on our screens for scoped styling.
	 *
	 * @param string $classes Space separated classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, self::$hooks, true ) ) {
			$classes .= ' mrtu-admin-screen';
		}
		return $classes;
	}

	/* ------------------------------------------------------------------
	 * UI kit — every helper echoes escaped markup.
	 * ---------------------------------------------------------------- */

	/**
	 * Opens a screen wrapper with title + short Vietnamese-friendly intro.
	 *
	 * @param string $title Screen title.
	 * @param string $intro One-sentence explanation.
	 */
	public static function page_open( $title, $intro = '' ) {
		echo '<div class="wrap mrtu-admin">';
		echo '<header class="mrtu-admin__header"><span class="mrtu-admin__mark" aria-hidden="true"></span><div>';
		echo '<h1 class="mrtu-admin__title">' . esc_html( $title ) . '</h1>';
		if ( '' !== $intro ) {
			echo '<p class="mrtu-admin__intro">' . esc_html( $intro ) . '</p>';
		}
		echo '</div></header>';
		self::flash();
	}

	/**
	 * Closes the screen wrapper.
	 */
	public static function page_close() {
		echo '</div>';
	}

	/**
	 * Opens a card section.
	 *
	 * @param string $title Card title.
	 * @param string $help  Optional help text.
	 * @param string $id    Optional element id.
	 */
	public static function card_open( $title, $help = '', $id = '' ) {
		echo '<section class="mrtu-card"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>';
		echo '<h2 class="mrtu-card__title">' . esc_html( $title ) . '</h2>';
		if ( '' !== $help ) {
			echo '<p class="mrtu-card__help">' . esc_html( $help ) . '</p>';
		}
	}

	/**
	 * Closes a card section.
	 */
	public static function card_close() {
		echo '</section>';
	}

	/**
	 * Verification badge.
	 *
	 * @param bool   $verified Whether the value is verified.
	 * @param string $label    Optional custom label.
	 * @return string Escaped HTML.
	 */
	public static function badge( $verified, $label = '' ) {
		if ( $verified ) {
			$text = '' !== $label ? $label : __( 'Verified', 'mr-tu-multilang-seo' );
			return '<span class="mrtu-badge mrtu-badge--ok">' . esc_html( $text ) . '</span>';
		}
		$text = '' !== $label ? $label : __( 'PENDING VERIFICATION', 'mr-tu-multilang-seo' );
		return '<span class="mrtu-badge mrtu-badge--pending">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Generic status badge.
	 *
	 * @param string $state ok|warn|error|info.
	 * @param string $label Text.
	 * @return string Escaped HTML.
	 */
	public static function status( $state, $label ) {
		$state = in_array( $state, array( 'ok', 'warn', 'error', 'info' ), true ) ? $state : 'info';
		return '<span class="mrtu-badge mrtu-badge--' . esc_attr( $state ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Text-like input row.
	 *
	 * @param array $args name, label, value, type, help, placeholder, attrs (array), required (bool).
	 */
	public static function field( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'name'        => '',
				'label'       => '',
				'value'       => '',
				'type'        => 'text',
				'help'        => '',
				'placeholder' => '',
				'attrs'       => array(),
				'required'    => false,
			)
		);
		$id = 'mrtu-' . sanitize_html_class( str_replace( array( '[', ']' ), '-', $args['name'] ) );
		echo '<div class="mrtu-field">';
		echo '<label class="mrtu-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $args['label'] ) . '</label>';
		$attrs = '';
		foreach ( (array) $args['attrs'] as $key => $val ) {
			$attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $val ) . '"';
		}
		if ( 'textarea' === $args['type'] ) {
			echo '<textarea class="mrtu-input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $args['name'] ) . '" rows="3"' . $attrs . ( $args['required'] ? ' required' : '' ) . ' placeholder="' . esc_attr( $args['placeholder'] ) . '">' . esc_textarea( $args['value'] ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs escaped above.
		} else {
			echo '<input class="mrtu-input" type="' . esc_attr( $args['type'] ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $args['name'] ) . '" value="' . esc_attr( $args['value'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '"' . $attrs . ( $args['required'] ? ' required' : '' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs escaped above.
		}
		if ( '' !== $args['help'] ) {
			echo '<p class="mrtu-field__help">' . esc_html( $args['help'] ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Toggle switch row (checkbox).
	 *
	 * @param string $name    Input name.
	 * @param string $label   Label.
	 * @param bool   $checked State.
	 * @param string $help    Optional help.
	 */
	public static function toggle( $name, $label, $checked, $help = '' ) {
		$id = 'mrtu-' . sanitize_html_class( str_replace( array( '[', ']' ), '-', $name ) );
		echo '<div class="mrtu-toggle">';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
		echo '<input class="mrtu-toggle__input" type="checkbox" role="switch" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked, true, false ) . '>';
		echo '<label class="mrtu-toggle__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( '' !== $help ) {
			echo '<p class="mrtu-field__help">' . esc_html( $help ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Sticky save bar with nonce + action for admin-post.php forms.
	 *
	 * @param string $action admin-post action / nonce action.
	 * @param string $label  Button label.
	 */
	public static function save_bar( $action, $label = '' ) {
		wp_nonce_field( $action, '_mrtu_nonce' );
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		echo '<div class="mrtu-savebar"><button type="submit" class="button button-primary mrtu-button">' . esc_html( '' !== $label ? $label : __( 'Save changes', 'mr-tu-multilang-seo' ) ) . '</button></div>';
	}

	/**
	 * Verifies an admin-post submission (nonce + capability) or dies.
	 *
	 * @param string $action     Nonce action.
	 * @param string $capability Required capability.
	 */
	public static function verify_request( $action, $capability = 'manage_options' ) {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
		}
		check_admin_referer( $action, '_mrtu_nonce' );
	}

	/**
	 * Stores a one-time notice for the current user and redirects back.
	 *
	 * @param string $url     Destination.
	 * @param string $message Message.
	 * @param string $type    success|error|warning|info.
	 */
	public static function redirect_with_notice( $url, $message, $type = 'success' ) {
		set_transient( 'mrtu_flash_' . get_current_user_id(), array( 'message' => $message, 'type' => $type ), 60 );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Prints (and clears) the pending one-time notice.
	 */
	public static function flash() {
		$key   = 'mrtu_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( ! $flash || empty( $flash['message'] ) ) {
			return;
		}
		delete_transient( $key );
		$type = in_array( $flash['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $flash['type'] : 'info';
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible mrtu-notice"><p>' . esc_html( $flash['message'] ) . '</p></div>';
	}

	/**
	 * URL of one of our screens.
	 *
	 * @param string $slug Page slug.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $slug, $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Human readable "last updated" line.
	 *
	 * @param int $timestamp Unix time.
	 * @param int $user_id   User id.
	 * @return string Escaped HTML.
	 */
	public static function last_updated( $timestamp, $user_id ) {
		if ( ! $timestamp ) {
			return '<p class="mrtu-meta">' . esc_html__( 'Not edited yet.', 'mr-tu-multilang-seo' ) . '</p>';
		}
		$user = $user_id ? get_userdata( $user_id ) : null;
		/* translators: 1: date and time, 2: user display name. */
		$text = sprintf( __( 'Last updated %1$s by %2$s', 'mr-tu-multilang-seo' ), wp_date( get_option( 'date_format' ) . ' H:i', (int) $timestamp ), $user ? $user->display_name : '—' );
		return '<p class="mrtu-meta">' . esc_html( $text ) . '</p>';
	}
}
