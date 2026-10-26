<?php
/**
 * Blog index (posts page) and last-resort template.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$mr_tu_blog_page = (int) get_option( 'page_for_posts' );
?>
<main id="main" class="site-main site-main--archive" tabindex="-1">
	<?php
	get_template_part(
		'parts/archive/listing',
		null,
		array(
			'title' => is_home() ? mr_tu_blog_title() : wp_strip_all_tags( get_the_archive_title() ),
			'lead'  => ( is_home() && $mr_tu_blog_page ) ? mr_tu_lead( $mr_tu_blog_page ) : '',
		)
	);
	?>
</main>
<?php
get_footer();
