<?php
/**
 * Front page: hero + the page content, whose `.mrtu-section--*` groups (promise, fleet, pricing,
 * story, steps, trust, faq, contact) get their own layouts from the stylesheet.
 *
 * When the Reading setting shows latest posts, the blog template is used instead.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

if ( 'page' !== get_option( 'show_on_front' ) || ! is_page() ) {
	require get_home_template();
	return;
}

get_header();
?>
<main id="main" class="site-main site-main--home" tabindex="-1">
	<?php
	while ( have_posts() ) :
		the_post();
		get_template_part( 'parts/home/hero' );

		if ( '' !== trim( (string) get_the_content() ) ) :
			?>
			<div class="home-content entry-content">
				<?php the_content(); ?>
			</div>
			<?php
		endif;
	endwhile;
	?>
</main>
<?php
get_footer();
