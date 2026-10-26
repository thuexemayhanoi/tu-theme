<?php
/**
 * Three-state colour mode control (System · Light · Dark) with aria-pressed semantics.
 *
 * The theme.js script keeps every instance in sync and stores the choice in localStorage['mrtu-theme'].
 * Hidden without JavaScript (it could not work), via the `no-js` class.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_variant = isset( $args['variant'] ) && 'labelled' === $args['variant'] ? 'labelled' : 'compact';
$mr_tu_default = (string) mr_tu_setting( 'color_mode' );
$mr_tu_modes   = array(
	'system' => array( 'system', __( 'System', 'mr-tu' ) ),
	'light'  => array( 'sun', __( 'Light', 'mr-tu' ) ),
	'dark'   => array( 'moon', __( 'Dark', 'mr-tu' ) ),
);
?>
<div class="mrtu-mode mrtu-mode--<?php echo esc_attr( $mr_tu_variant ); ?>" role="group" aria-label="<?php esc_attr_e( 'Colour mode', 'mr-tu' ); ?>" data-mrtu-mode>
	<?php foreach ( $mr_tu_modes as $mr_tu_mode => $mr_tu_meta ) : ?>
		<button type="button" class="mrtu-mode__btn" data-mrtu-mode-set="<?php echo esc_attr( $mr_tu_mode ); ?>" aria-pressed="<?php echo $mr_tu_mode === $mr_tu_default ? 'true' : 'false'; ?>">
			<?php mr_tu_the_icon( $mr_tu_meta[0] ); ?>
			<span class="<?php echo 'labelled' === $mr_tu_variant ? 'mrtu-mode__label' : 'screen-reader-text'; ?>"><?php echo esc_html( $mr_tu_meta[1] ); ?></span>
		</button>
	<?php endforeach; ?>
</div>
