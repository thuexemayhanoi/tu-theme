<?php
/**
 * Inner pages (pillar, pricing, fleet, guides, contact, legal…): hero, 720 px prose with an
 * optional sticky table of contents, a closing call to action and related pages.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main site-main--page" tabindex="-1">
	<?php
	while ( have_posts() ) :
		the_post();
		$mr_tu_has_toc = mr_tu_has_sticky_toc();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>
			<?php
			get_template_part(
				'parts/components/page-hero',
				null,
				array(
					'title'   => get_the_title(),
					'lead'    => mr_tu_lead(),
					'updated' => true,
				)
			);
			?>
			<div class="entry-layout<?php echo $mr_tu_has_toc ? ' has-aside' : ''; ?>">
				<div class="entry-content prose">
					<?php
					the_content();
					wp_link_pages(
						array(
							'before' => '<nav class="page-links" aria-label="' . esc_attr__( 'Page sections', 'mr-tu' ) . '">',
							'after'  => '</nav>',
						)
					);
					?>
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
		get_template_part( 'parts/components/related-pages', null, array( 'post_id' => get_the_ID() ) );
	endwhile;
	?>
</main>
<?php
get_footer();
