<?php
/**
 * Licence-plate label (biển số): white field, thin black frame, inset line, condensed caps.
 * Stays white in dark mode — it is an object, not a surface.
 *
 * Args: `top` (first row), `bottom` (optional second row), `class`, `tag` (span|p|div),
 * `decorative` (bool, default true → aria-hidden).
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_args = wp_parse_args(
	isset( $args ) ? $args : array(),
	array(
		'top'        => '',
		'bottom'     => '',
		'class'      => '',
		'tag'        => 'span',
		'decorative' => true,
	)
);
if ( '' === $mr_tu_args['top'] ) {
	return;
}
$mr_tu_tag = in_array( $mr_tu_args['tag'], array( 'span', 'p', 'div' ), true ) ? $mr_tu_args['tag'] : 'span';
$mr_tu_cls = 'mrtu-plate' . ( '' === $mr_tu_args['bottom'] ? ' mrtu-plate--inline' : '' ) . ( $mr_tu_args['class'] ? ' ' . $mr_tu_args['class'] : '' );
?>
<<?php echo esc_html( $mr_tu_tag ); ?> class="<?php echo esc_attr( $mr_tu_cls ); ?>"<?php echo $mr_tu_args['decorative'] ? ' aria-hidden="true"' : ''; ?>>
	<span class="mrtu-plate__top"><?php echo esc_html( $mr_tu_args['top'] ); ?></span>
	<?php if ( '' !== $mr_tu_args['bottom'] ) : ?>
		<span class="mrtu-plate__bottom"><?php echo esc_html( $mr_tu_args['bottom'] ); ?></span>
	<?php endif; ?>
</<?php echo esc_html( $mr_tu_tag ); ?>>
