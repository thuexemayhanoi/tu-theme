<?php
/**
 * Kilometre stone (cột cây số): white body, orange cap, condensed stencil type. Pure CSS.
 *
 * Args:
 * - `variant` hero|step|lost|mini (size + layout),
 * - `route`   text on the cap (default "Mr Tu"),
 * - `place`   text on the body,
 * - `km`      the big number (string, may be "?"),
 * - `label`   accessible label; when empty the stone is decorative (aria-hidden).
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_args = wp_parse_args(
	isset( $args ) ? $args : array(),
	array(
		'variant' => 'hero',
		'route'   => __( 'Mr Tu', 'mr-tu' ),
		'place'   => '',
		'km'      => '0',
		'label'   => '',
		'class'   => '',
	)
);
$mr_tu_a11y = '' !== $mr_tu_args['label']
	? ' role="img" aria-label="' . esc_attr( $mr_tu_args['label'] ) . '"'
	: ' aria-hidden="true"';
?>
<div class="mrtu-stone mrtu-stone--<?php echo esc_attr( $mr_tu_args['variant'] ); ?><?php echo $mr_tu_args['class'] ? ' ' . esc_attr( $mr_tu_args['class'] ) : ''; ?>"<?php echo $mr_tu_a11y; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>>
	<span class="mrtu-stone__cap"><span class="mrtu-stone__route"><?php echo esc_html( $mr_tu_args['route'] ); ?></span></span>
	<span class="mrtu-stone__face">
		<?php if ( '' !== $mr_tu_args['place'] ) : ?>
			<span class="mrtu-stone__place"><?php echo esc_html( $mr_tu_args['place'] ); ?></span>
		<?php endif; ?>
		<span class="mrtu-stone__km"><?php echo esc_html( $mr_tu_args['km'] ); ?><span class="mrtu-stone__unit">km</span></span>
	</span>
</div>
