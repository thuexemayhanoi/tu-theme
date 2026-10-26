<?php
/**
 * Quick Contact dock (contract §2.10, docs/component-markup.md).
 *
 * Option `mrtu_quick_contact`:
 * [ 'enabled' => true, 'items' => [ call, whatsapp, zalo, directions, chat, contact => bool ],
 *   'position' => 'right'|'left', 'collapse_on_scroll' => true, 'updated_at', 'updated_by' ].
 * An item renders only when switched on here AND its channel is publishable (enabled + verified);
 * chat only when `mrtu_assistant_active` is true. Rendered in wp_footer on the frontend.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Quick Contact dock.
 */
final class Quick_Contact {

	const OPTION = 'mrtu_quick_contact';
	const ITEMS  = array( 'call', 'whatsapp', 'zalo', 'directions', 'chat', 'contact' );

	/**
	 * Item → channel key in Business::channel_status().
	 */
	const CHANNELS = array(
		'call'       => 'phone',
		'whatsapp'   => 'whatsapp',
		'zalo'       => 'zalo',
		'directions' => 'directions',
		'contact'    => 'contact',
	);

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_footer' ), 10 ); // Before wp_print_footer_scripts (20).
	}

	/**
	 * Registers front assets.
	 */
	public static function register_assets() {
		wp_register_style( 'mrtu-quick-contact', MRTU_ML_URL . 'assets/css/quick-contact.css', array(), MRTU_ML_VERSION );
		wp_style_add_data( 'mrtu-quick-contact', 'path', MRTU_ML_DIR . 'assets/css/quick-contact.css' ); // Small file: core inlines it.
		wp_register_script(
			'mrtu-quick-contact',
			MRTU_ML_URL . 'assets/js/quick-contact.js',
			array(),
			MRTU_ML_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Default settings (contract §2.10).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'            => true,
			'items'              => array(
				'call'       => true,
				'whatsapp'   => false,
				'zalo'       => false,
				'directions' => false,
				'chat'       => true,
				'contact'    => false,
			),
			'position'           => 'right',
			'collapse_on_scroll' => true,
			'updated_at'         => 0,
			'updated_by'         => 0,
		);
	}

	/**
	 * Normalises settings.
	 *
	 * @param mixed $input Raw settings.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$in       = is_array( $input ) ? $input : array();
		$items    = Business::rec( $in, 'items' );
		$position = Business::get( $in, 'position', 'right' );
		$out      = array(
			'enabled'            => Business::flag( Business::get( $in, 'enabled', $defaults['enabled'] ) ),
			'items'              => array(),
			'position'           => in_array( $position, array( 'left', 'right' ), true ) ? $position : 'right',
			'collapse_on_scroll' => Business::flag( Business::get( $in, 'collapse_on_scroll', $defaults['collapse_on_scroll'] ) ),
			'updated_at'         => absint( Business::get( $in, 'updated_at', 0 ) ),
			'updated_by'         => absint( Business::get( $in, 'updated_by', 0 ) ),
		);
		foreach ( self::ITEMS as $item ) {
			$out['items'][ $item ] = Business::flag( Business::get( $items, $item, $defaults['items'][ $item ] ) );
		}
		return $out;
	}

	/**
	 * Current settings.
	 *
	 * @return array
	 */
	public static function settings() {
		return self::sanitize( get_option( self::OPTION, self::defaults() ) );
	}

	/**
	 * Saves settings (caller checks capability + nonce).
	 *
	 * @param array $input   Raw settings.
	 * @param int   $user_id Editing user.
	 * @return string 'saved'|'unchanged'
	 */
	public static function save( $input, $user_id = 0 ) {
		$old = self::settings();
		$new = self::sanitize( $input );
		if ( Business::strip_meta( $old ) === Business::strip_meta( $new ) ) {
			return 'unchanged';
		}
		$new['updated_at'] = time();
		$new['updated_by'] = (int) $user_id;
		update_option( self::OPTION, $new, true );
		return 'saved';
	}

	/**
	 * Whether the AI assistant plugin is active.
	 *
	 * @return bool
	 */
	public static function assistant_active() {
		return (bool) apply_filters( 'mrtu_assistant_active', false );
	}

	/**
	 * Item labels.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'call'       => __( 'Call', 'mr-tu-multilang-seo' ),
			'whatsapp'   => 'WhatsApp',
			'zalo'       => 'Zalo',
			'directions' => __( 'Directions', 'mr-tu-multilang-seo' ),
			'chat'       => __( 'Ask a question', 'mr-tu-multilang-seo' ),
			'contact'    => __( 'Contact page', 'mr-tu-multilang-seo' ),
		);
	}

	/**
	 * Per-item status for the admin: switched on, publishable, reason.
	 *
	 * @return array item => [ 'on' => bool, 'publishable' => bool, 'reason' => string ]
	 */
	public static function item_status() {
		$settings = self::settings();
		$channels = Business::contact_channels( false );
		$out      = array();
		foreach ( self::ITEMS as $item ) {
			if ( 'chat' === $item ) {
				$publishable = self::assistant_active();
				$reason      = $publishable ? '' : __( 'The Mr Tu assistant plugin is not active.', 'mr-tu-multilang-seo' );
			} else {
				$channel     = $channels[ self::CHANNELS[ $item ] ];
				$publishable = $channel['public'];
				$reason      = $channel['reason'];
			}
			$out[ $item ] = array(
				'on'          => ! empty( $settings['items'][ $item ] ),
				'publishable' => (bool) $publishable,
				'reason'      => $reason,
			);
		}
		return $out;
	}

	/**
	 * Items that render now, in contract order.
	 *
	 * @param string|null $lang Language code (null = current).
	 * @return array key => [ type link|button, label, url, icon ]
	 */
	public static function items( $lang = null ) {
		$settings = self::settings();
		if ( ! $settings['enabled'] ) {
			return array();
		}
		$lang     = Business::lang( $lang );
		$channels = Business::contact_channels( true, $lang );
		$switched = Business::switch_language( $lang );
		$labels   = self::labels();
		$icons    = array(
			'call'       => 'phone',
			'whatsapp'   => 'message',
			'zalo'       => 'message',
			'directions' => 'pin',
			'chat'       => 'chat',
			'contact'    => 'mail',
		);
		$items    = array();
		foreach ( self::ITEMS as $key ) {
			if ( empty( $settings['items'][ $key ] ) ) {
				continue;
			}
			if ( 'chat' === $key ) {
				if ( self::assistant_active() ) {
					$items['chat'] = array(
						'type'  => 'button',
						'label' => $labels['chat'],
						'url'   => '',
						'icon'  => 'chat',
					);
				}
				continue;
			}
			$channel = self::CHANNELS[ $key ];
			if ( ! isset( $channels[ $channel ] ) ) {
				continue;
			}
			$items[ $key ] = array(
				'type'  => 'link',
				'label' => $labels[ $key ],
				'url'   => $channels[ $channel ]['url'],
				'icon'  => $icons[ $key ],
			);
		}
		if ( $switched ) {
			restore_previous_locale();
		}

		/**
		 * Filters the Quick Contact items.
		 *
		 * @param array  $items key => [ type link|button, label, url, icon ].
		 * @param string $lang  Language code.
		 */
		$items = apply_filters( 'mrtu_quick_contact_items', $items, $lang );

		$clean = array();
		foreach ( (array) $items as $key => $item ) {
			if ( ! is_array( $item ) || empty( $item['label'] ) ) {
				continue;
			}
			$type = isset( $item['type'] ) && 'button' === $item['type'] ? 'button' : 'link';
			if ( 'link' === $type && empty( $item['url'] ) ) {
				continue;
			}
			$clean[ sanitize_key( $key ) ] = array(
				'type'  => $type,
				'label' => (string) $item['label'],
				'url'   => isset( $item['url'] ) ? (string) $item['url'] : '',
				'icon'  => isset( $item['icon'] ) ? (string) $item['icon'] : 'message',
			);
		}
		return $clean;
	}

	/**
	 * Dock markup ('' when disabled or nothing is publishable).
	 *
	 * @param string|null $lang Language code.
	 * @return string Escaped HTML.
	 */
	public static function markup( $lang = null ) {
		$items = self::items( $lang );
		if ( ! $items ) {
			return '';
		}
		$settings = self::settings();
		$lang     = Business::lang( $lang );
		$switched = Business::switch_language( $lang );

		$html  = '<div class="mrtu-qc" data-mrtu-qc data-position="' . esc_attr( $settings['position'] ) . '" data-collapse="' . ( $settings['collapse_on_scroll'] ? 'true' : 'false' ) . '">';
		$html .= '<div class="mrtu-qc__panel" id="mrtu-qc-panel" hidden>';
		foreach ( $items as $key => $item ) {
			$inner = Blocks::icon( $item['icon'] ) . '<span class="mrtu-qc__text">' . esc_html( $item['label'] ) . '</span>';
			if ( 'button' === $item['type'] ) {
				$html .= '<button type="button" class="mrtu-qc__item mrtu-qc__item--' . esc_attr( $key ) . '"' . ( 'chat' === $key ? ' data-mrtu-assistant-open' : '' ) . '>' . $inner . '</button>';
			} else {
				$html .= '<a class="mrtu-qc__item mrtu-qc__item--' . esc_attr( $key ) . '" href="' . esc_url( $item['url'] ) . '">' . $inner . '</a>';
			}
		}
		$html .= '</div>';
		$html .= '<button type="button" class="mrtu-qc__toggle" aria-expanded="false" aria-controls="mrtu-qc-panel">';
		$html .= Blocks::icon( isset( $items['call'] ) ? 'phone' : 'message', 'mrtu-qc__icon mrtu-qc__icon--open' );
		$html .= Blocks::icon( 'close', 'mrtu-qc__icon mrtu-qc__icon--close' );
		$html .= '<span class="mrtu-qc__label">' . esc_html__( 'Contact', 'mr-tu-multilang-seo' ) . '</span>';
		$html .= '</button></div>';

		if ( $switched ) {
			restore_previous_locale();
		}
		return $html;
	}

	/**
	 * Whether the dock may render on this request.
	 *
	 * @return bool
	 */
	private static function is_frontend_request() {
		if ( is_admin() || is_feed() || is_embed() ) {
			return false;
		}
		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return false;
		}
		return ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	/**
	 * Enqueues assets when the dock will render.
	 */
	public static function enqueue() {
		if ( ! self::is_frontend_request() || ! self::items() ) {
			return;
		}
		wp_enqueue_style( 'mrtu-quick-contact' );
		wp_enqueue_script( 'mrtu-quick-contact' );
	}

	/**
	 * Prints the dock in wp_footer.
	 */
	public static function render_footer() {
		if ( ! self::is_frontend_request() ) {
			return;
		}
		$html = self::markup();
		if ( '' === $html ) {
			return;
		}
		// Late enqueue (themes that skip wp_enqueue_scripts or when items changed during the request).
		wp_enqueue_style( 'mrtu-quick-contact' );
		wp_enqueue_script( 'mrtu-quick-contact' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in markup().
	}
}
