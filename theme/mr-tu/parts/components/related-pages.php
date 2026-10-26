<?php
/**
 * "Keep exploring": three other core pages in the same language.
 *
 * Args: `post_id` (int).
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : get_the_ID();
$mr_tu_related = mr_tu_related_pages( $mr_tu_post_id, 3 );
if ( ! $mr_tu_related ) {
	return;
}
$mr_tu_spec = mr_tu_role_nav_spec();
?>
<section class="related related--pages" aria-labelledby="mrtu-related-title">
	<div class="related__inner">
		<h2 class="related__title" id="mrtu-related-title"><?php esc_html_e( 'Keep exploring', 'mr-tu' ); ?></h2>
		<div class="card-grid card-grid--3">
			<?php
			foreach ( $mr_tu_related as $mr_tu_page ) {
				$mr_tu_role    = mr_tu_post_role( $mr_tu_page->ID );
				$mr_tu_eyebrow = trim( (string) get_post_meta( $mr_tu_page->ID, '_mrtu_nav_label', true ) );
				if ( '' === $mr_tu_eyebrow && '' !== $mr_tu_role && ! empty( $mr_tu_spec[ $mr_tu_role ]['label'] ) ) {
					$mr_tu_eyebrow = $mr_tu_spec[ $mr_tu_role ]['label'];
				}
				get_template_part(
					'parts/components/post-card',
					null,
					array(
						'post'    => $mr_tu_page,
						'heading' => 'h3',
						'variant' => 'page',
						'eyebrow' => $mr_tu_eyebrow,
					)
				);
			}
			?>
		</div>
	</div>
</section>
