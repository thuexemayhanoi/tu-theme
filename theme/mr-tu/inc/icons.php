<?php
/**
 * Inline SVG icon set (24 × 24, stroke icons drawn for this theme).
 *
 * Icons are decorative (`aria-hidden`); the accessible name always comes from text.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Icon geometry. Static, trusted markup.
 *
 * @return array<string, string>
 */
function mr_tu_icon_paths() {
	return array(
		'phone'    => '<path d="M6.6 3h2.9l1.6 4.4-2.1 1.5a12.2 12.2 0 0 0 6.1 6.1l1.5-2.1 4.4 1.6v2.9a2 2 0 0 1-2.1 2A17.6 17.6 0 0 1 4.6 5.1a2 2 0 0 1 2-2.1z"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h10"/>',
		'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
		'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.4 2.6 3.7 5.6 3.7 9s-1.3 6.4-3.7 9c-2.4-2.6-3.7-5.6-3.7-9S9.6 5.6 12 3z"/>',
		'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M2.5 12h2M19.5 12h2M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/>',
		'moon'     => '<path d="M20 14.6A8.2 8.2 0 0 1 9.4 4a8.3 8.3 0 1 0 10.6 10.6z"/>',
		'system'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 3.5a8.5 8.5 0 0 1 0 17z" fill="currentColor" stroke="none"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'chevron'  => '<path d="m6 9 6 6 6-6"/>',
		'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/>',
		'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
		'scooter'  => '<circle cx="6" cy="17" r="2.8"/><circle cx="18" cy="17" r="2.8"/><path d="M8.8 17h5.6l2.4-6.5h-4.1L10.4 6H8M14.6 6h2.6"/>',
		'chat'     => '<path d="M4.5 5.5h15v10.5H10l-5.5 4z"/><path d="M8.5 10.5h7"/>',
		'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
		'pin'      => '<path d="M12 21s-6.8-6-6.8-11a6.8 6.8 0 0 1 13.6 0c0 5-6.8 11-6.8 11z"/><circle cx="12" cy="10" r="2.4"/>',
		'plus'     => '<path d="M12 5v14M5 12h14"/>',
		'external' => '<path d="M7 17 17 7M9 7h8v8"/>',
	);
}

/**
 * Returns an icon's SVG markup.
 *
 * @param string $name  Icon name.
 * @param string $class Extra class.
 * @return string
 */
function mr_tu_icon( $name, $class = '' ) {
	$paths = mr_tu_icon_paths();
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="mrtu-icon mrtu-icon--' . esc_attr( $name ) . ( $class ? ' ' . esc_attr( $class ) : '' ) . '" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Echoes an icon.
 *
 * @param string $name  Icon name.
 * @param string $class Extra class.
 */
function mr_tu_the_icon( $name, $class = '' ) {
	echo mr_tu_icon( $name, $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG; attributes escaped in mr_tu_icon().
}

/**
 * Kilometre-stone brand mark + "Mr Tu" wordmark as one inline SVG.
 *
 * The wordmark is drawn from Be Vietnam Pro ExtraBold outlines so it never depends on font
 * loading; colours follow the theme (text = currentColor, cap = accent token).
 *
 * @param string $class Extra class.
 * @return string
 */
function mr_tu_wordmark( $class = '' ) {
	$word = 'M64 1000V260H298L451 620L610 260H836V1000H665V527L530 832H370L235 525V1000ZM934 1000V470H1103V550H1109Q1117 529 1132.5 508.5Q1148 488 1176 474Q1204 460 1250 460Q1272 460 1285 463Q1298 466 1298 466V635Q1298 635 1284 632Q1270 629 1245 629Q1174 629 1139.5 667Q1105 705 1105 791V1000ZM1773 1000V410H1548V260H2172V410H1947V1000ZM2406 1010Q2348 1010 2312.5 985.5Q2277 961 2260 920.5Q2243 880 2243 831V470H2412V793Q2412 827 2430 849Q2448 871 2484 871Q2525 871 2542.5 843.5Q2560 816 2560 781V470H2731V1000H2560V925H2554Q2534 960 2498 985Q2462 1010 2406 1010Z';

	return '<svg class="mrtu-wordmark' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '" viewBox="0 180 3420 840" width="122" height="30" role="img" aria-label="' . esc_attr__( 'Mr Tu', 'mr-tu' ) . '" focusable="false">'
		. '<path class="mrtu-wordmark__stone" d="M0 1000V470a290 290 0 0 1 580 0v530z" fill="currentColor"/>'
		. '<path class="mrtu-wordmark__body" d="M56 944V470a234 234 0 0 1 468 0v474z" fill="#fff"/>'
		. '<path class="mrtu-wordmark__cap" d="M56 520v-50a234 234 0 0 1 468 0v50z" fill="var(--color-accent,#F56A2A)"/>'
		. '<path class="mrtu-wordmark__road" d="M196 760h188" stroke="currentColor" stroke-width="56" stroke-linecap="round"/>'
		. '<path class="mrtu-wordmark__word" transform="translate(689 0)" d="' . $word . '" fill="currentColor"/>'
		. '</svg>';
}
