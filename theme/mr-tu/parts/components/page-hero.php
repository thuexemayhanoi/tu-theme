<?php
/**
 * Inner page hero: breadcrumbs, the page's only H1, lead (manual excerpt) and meta line.
 *
 * Args: `title` (string, required), `lead` (string), `eyebrow` (string, plate label),
 * `updated` (bool: "Updated <date>" of the queried post), `variant` (page|archive|search|post).
 * The `post` variant prints the category plate, publish date, reading time and update date.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_args     = wp_parse_args(
	isset( $args ) ? $args : array(),
	array(
		'title'   => '',
		'lead'    => '',
		'eyebrow' => '',
		'updated' => false,
		'variant' => 'page',
	)
);
$mr_tu_is_post  = 'post' === $mr_tu_args['variant'];
$mr_tu_category = $mr_tu_is_post ? mr_tu_primary_category() : null;
$mr_tu_modified = $mr_tu_is_post && ( get_the_modified_time( 'U' ) - get_the_time( 'U' ) > DAY_IN_SECONDS );
// Singular heroes share the reading column of the content below (and its TOC offset).
$mr_tu_inner = 'page-hero__inner';
if ( in_array( $mr_tu_args['variant'], array( 'page', 'post' ), true ) ) {
	$mr_tu_inner .= ' page-hero__inner--prose' . ( mr_tu_has_sticky_toc() ? ' has-aside' : '' );
}
?>
<header class="page-hero page-hero--<?php echo esc_attr( $mr_tu_args['variant'] ); ?>">
	<div class="<?php echo esc_attr( $mr_tu_inner ); ?>">
		<?php get_template_part( 'parts/components/breadcrumbs' ); ?>

		<?php if ( $mr_tu_category ) : ?>
			<p class="page-hero__eyebrow"><a class="mrtu-plate mrtu-plate--inline mrtu-plate--link" href="<?php echo esc_url( get_category_link( $mr_tu_category ) ); ?>"><span class="mrtu-plate__top"><?php echo esc_html( $mr_tu_category->name ); ?></span></a></p>
		<?php elseif ( '' !== $mr_tu_args['eyebrow'] ) : ?>
			<p class="page-hero__eyebrow"><span class="mrtu-plate mrtu-plate--inline"><span class="mrtu-plate__top"><?php echo esc_html( $mr_tu_args['eyebrow'] ); ?></span></span></p>
		<?php endif; ?>

		<h1 class="page-hero__title"><?php echo esc_html( $mr_tu_args['title'] ); ?></h1>

		<?php if ( '' !== $mr_tu_args['lead'] ) : ?>
			<p class="page-hero__lead"><?php echo esc_html( $mr_tu_args['lead'] ); ?></p>
		<?php endif; ?>

		<?php if ( $mr_tu_is_post ) : ?>
			<p class="page-hero__meta">
				<span class="page-hero__meta-item"><?php mr_tu_the_icon( 'calendar' ); ?><?php mr_tu_the_time( 'published' ); ?></span>
				<span class="page-hero__meta-item">
					<?php mr_tu_the_icon( 'clock' ); ?>
					<span>
						<?php
						$mr_tu_minutes = mr_tu_reading_minutes();
						/* translators: %d: estimated reading time in minutes. */
						echo esc_html( sprintf( _n( '%d min read', '%d min read', $mr_tu_minutes, 'mr-tu' ), $mr_tu_minutes ) );
						?>
					</span>
				</span>
				<?php if ( $mr_tu_modified ) : ?>
					<span class="page-hero__meta-item"><span><?php esc_html_e( 'Updated', 'mr-tu' ); ?></span> <?php mr_tu_the_time( 'modified' ); ?></span>
				<?php endif; ?>
			</p>
		<?php elseif ( $mr_tu_args['updated'] ) : ?>
			<p class="page-hero__meta">
				<span class="page-hero__meta-item">
					<?php mr_tu_the_icon( 'calendar' ); ?>
					<span><?php esc_html_e( 'Updated', 'mr-tu' ); ?></span>
					<?php mr_tu_the_time( 'modified' ); ?>
				</span>
			</p>
		<?php endif; ?>
	</div>
	<div class="page-hero__road" aria-hidden="true"></div>
</header>
