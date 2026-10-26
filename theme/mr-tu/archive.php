<?php
/**
 * Category, tag, date and author archives.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$mr_tu_eyebrow = '';
if ( is_category() ) {
	$mr_tu_eyebrow = __( 'Category', 'mr-tu' );
} elseif ( is_tag() ) {
	$mr_tu_eyebrow = __( 'Tag', 'mr-tu' );
} elseif ( is_date() ) {
	$mr_tu_eyebrow = __( 'Archive', 'mr-tu' );
}
?>
<main id="main" class="site-main site-main--archive" tabindex="-1">
	<?php
	get_template_part(
		'parts/archive/listing',
		null,
		array(
			'title'   => wp_strip_all_tags( get_the_archive_title() ),
			'lead'    => wp_strip_all_tags( get_the_archive_description() ),
			'eyebrow' => $mr_tu_eyebrow,
		)
	);
	?>
</main>
<?php
get_footer();
