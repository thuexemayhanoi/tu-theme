<?php
/**
 * Single post: article header (category, date, reading time), featured image, prose, tags,
 * call to action, related posts in the same language and comments.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main site-main--single" tabindex="-1">
	<?php
	while ( have_posts() ) :
		the_post();
		$mr_tu_has_toc = mr_tu_has_sticky_toc();
		$mr_tu_media   = mr_tu_featured_image_html( get_post() );
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry entry--post' ); ?>>
			<?php
			get_template_part(
				'parts/components/page-hero',
				null,
				array(
					'title'   => get_the_title(),
					'lead'    => mr_tu_lead(),
					'variant' => 'post',
				)
			);
			?>
			<?php if ( $mr_tu_media ) : ?>
				<figure class="entry-media">
					<?php echo $mr_tu_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup. ?>
					<?php if ( get_the_post_thumbnail_caption() ) : ?>
						<figcaption class="entry-media__caption"><?php echo wp_kses_post( get_the_post_thumbnail_caption() ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endif; ?>

			<div class="entry-layout<?php echo $mr_tu_has_toc ? ' has-aside' : ''; ?>">
				<div class="entry-content prose">
					<?php
					the_content();
					wp_link_pages(
						array(
							'before' => '<nav class="page-links" aria-label="' . esc_attr__( 'Post sections', 'mr-tu' ) . '">',
							'after'  => '</nav>',
						)
					);
					?>
					<?php
					$mr_tu_tags = get_the_tags();
					if ( $mr_tu_tags ) :
						?>
						<ul class="entry-tags" role="list" aria-label="<?php esc_attr_e( 'Tags', 'mr-tu' ); ?>">
							<?php foreach ( $mr_tu_tags as $mr_tu_tag ) : ?>
								<li><a class="entry-tags__link" href="<?php echo esc_url( get_tag_link( $mr_tu_tag ) ); ?>">#<?php echo esc_html( $mr_tu_tag->name ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
				<?php
				if ( $mr_tu_has_toc ) {
					get_template_part( 'parts/components/toc-aside' );
				}
				?>
			</div>
		</article>
		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		get_template_part( 'parts/components/cta-band' );
		get_template_part( 'parts/components/related-posts', null, array( 'post_id' => get_the_ID() ) );
	endwhile;
	?>
</main>
<?php
get_footer();
