<?php
/**
 * Closing call-to-action band: contact page + call button. Renders nothing when neither the
 * contact role page nor a public phone number exists (no dead buttons).
 *
 * Args: `title`, `text` (both optional, translatable defaults).
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_targets    = mr_tu_contact_targets();
$mr_tu_on_contact = '' !== $mr_tu_targets['contact'] && is_page( mr_tu_role_page_id( 'contact' ) );
if ( $mr_tu_on_contact ) {
	$mr_tu_targets['contact'] = '';
}
if ( '' === $mr_tu_targets['contact'] && '' === $mr_tu_targets['tel'] ) {
	return;
}
$mr_tu_args          = wp_parse_args(
	isset( $args ) ? $args : array(),
	array(
		'title' => __( 'Ready to ride from Km 0?', 'mr-tu' ),
		'text'  => __( 'Tell us your dates and the bike you have in mind. Price, deposit and hand-over are confirmed with you before anything is booked.', 'mr-tu' ),
	)
);
$mr_tu_contact_label = __( 'Contact', 'mr-tu' );
$mr_tu_contact_id    = mr_tu_role_page_id( 'contact' );
if ( $mr_tu_contact_id ) {
	$mr_tu_nav_label = trim( (string) get_post_meta( $mr_tu_contact_id, '_mrtu_nav_label', true ) );
	if ( '' !== $mr_tu_nav_label ) {
		$mr_tu_contact_label = $mr_tu_nav_label;
	}
}
?>
<section class="cta-band" aria-labelledby="mrtu-cta-band-title" data-reveal>
	<div class="cta-band__inner">
		<div class="cta-band__copy">
			<h2 class="cta-band__title" id="mrtu-cta-band-title"><?php echo esc_html( $mr_tu_args['title'] ); ?></h2>
			<p class="cta-band__text"><?php echo esc_html( $mr_tu_args['text'] ); ?></p>
		</div>
		<div class="cta-band__actions">
			<?php if ( '' !== $mr_tu_targets['tel'] ) : ?>
				<a class="mrtu-btn mrtu-btn--call mrtu-btn--lg" href="<?php echo esc_url( $mr_tu_targets['tel'], array( 'tel' ) ); ?>">
					<?php mr_tu_the_icon( 'phone' ); ?>
					<span>
						<?php
						/* translators: %s: public phone number. */
						echo esc_html( sprintf( __( 'Call %s', 'mr-tu' ), $mr_tu_targets['phone'] ) );
						?>
					</span>
				</a>
			<?php endif; ?>
			<?php if ( '' !== $mr_tu_targets['contact'] ) : ?>
				<a class="mrtu-btn mrtu-btn--ghost mrtu-btn--lg" href="<?php echo esc_url( $mr_tu_targets['contact'] ); ?>">
					<span><?php echo esc_html( $mr_tu_contact_label ); ?></span>
					<?php mr_tu_the_icon( 'arrow' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		get_template_part(
			'parts/components/stone',
			null,
			array(
				'variant' => 'band',
				'place'   => __( 'Hà Nội', 'mr-tu' ),
				'km'      => '0',
			)
		);
		?>
	</div>
</section>
