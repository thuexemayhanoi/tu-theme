<?php
/**
 * Related posts (same category, same language when the plugin is active).
 *
 * Args: `post_id` (int).
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : get_the_ID();
$mr_tu_related = mr_tu_related_posts( $mr_tu_post_id, 3 );
if ( ! $mr_tu_related ) {
	return;
}
?>
<section class="related related--posts" aria-labelledby="mrtu-related-title">
	<div class="related__inner">
		<h2 class="related__title" id="mrtu-related-title"><?php esc_html_e( 'More from the road', 'mr-tu' ); ?></h2>
		<div class="card-grid card-grid--3">
			<?php
			foreach ( $mr_tu_related as $mr_tu_related_post ) {
				get_template_part(
					'parts/components/post-card',
					null,
					array(
						'post'    => $mr_tu_related_post,
						'heading' => 'h3',
					)
				);
			}
			?>
		</div>
	</div>
</section>
