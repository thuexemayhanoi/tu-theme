<?php
/**
 * Editorial post grid shared by the blog (index.php) and archives (archive.php).
 *
 * Args: `title`, `lead`, `eyebrow`.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_args = wp_parse_args(
	isset( $args ) ? $args : array(),
	array(
		'title'   => '',
		'lead'    => '',
		'eyebrow' => '',
	)
);

get_template_part(
	'parts/components/page-hero',
	null,
	array(
		'title'   => $mr_tu_args['title'],
		'lead'    => $mr_tu_args['lead'],
		'eyebrow' => $mr_tu_args['eyebrow'],
		'variant' => 'archive',
	)
);
?>
<div class="archive-body">
	<?php if ( have_posts() ) : ?>
		<div class="card-grid card-grid--editorial">
			<?php
			$mr_tu_index = 0;
			while ( have_posts() ) {
				the_post();
				get_template_part(
					'parts/components/post-card',
					null,
					array(
						'post'    => get_post(),
						'heading' => 'h2',
						'variant' => ( 0 === $mr_tu_index && ! is_paged() ) ? 'feature' : 'default',
					)
				);
				++$mr_tu_index;
			}
			?>
		</div>
		<?php mr_tu_pagination(); ?>
	<?php else : ?>
		<div class="empty-state">
			<p class="empty-state__text"><?php esc_html_e( 'Nothing has been published here yet.', 'mr-tu' ); ?></p>
			<?php get_search_form(); ?>
		</div>
	<?php endif; ?>
</div>
