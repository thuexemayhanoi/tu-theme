<?php
/**
 * Search results in the current language.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

global $wp_query;
$mr_tu_query = get_search_query();
$mr_tu_found = (int) $wp_query->found_posts;
?>
<main id="main" class="site-main site-main--search" tabindex="-1">
	<header class="page-hero page-hero--search">
		<div class="page-hero__inner">
			<?php get_template_part( 'parts/components/breadcrumbs' ); ?>
			<h1 class="page-hero__title">
				<?php
				if ( '' !== $mr_tu_query ) {
					/* translators: %s: search query. */
					echo esc_html( sprintf( __( 'Results for “%s”', 'mr-tu' ), $mr_tu_query ) );
				} else {
					esc_html_e( 'Search', 'mr-tu' );
				}
				?>
			</h1>
			<?php if ( '' !== $mr_tu_query ) : ?>
				<p class="page-hero__lead" role="status">
					<?php
					/* translators: %d: number of search results. */
					echo esc_html( sprintf( _n( '%d result', '%d results', $mr_tu_found, 'mr-tu' ), $mr_tu_found ) );
					?>
				</p>
			<?php endif; ?>
			<div class="page-hero__search"><?php get_search_form(); ?></div>
		</div>
		<div class="page-hero__road" aria-hidden="true"></div>
	</header>

	<div class="search-body">
		<?php if ( have_posts() ) : ?>
			<ol class="search-results" role="list">
				<?php
				while ( have_posts() ) :
					the_post();
					$mr_tu_type = get_post_type_object( get_post_type() );
					?>
					<li class="search-result" data-reveal>
						<p class="search-result__type"><?php echo esc_html( $mr_tu_type ? $mr_tu_type->labels->singular_name : '' ); ?></p>
						<h2 class="search-result__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p class="search-result__excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
						<p class="search-result__url" aria-hidden="true"><?php echo esc_html( wp_parse_url( get_permalink(), PHP_URL_PATH ) ); ?></p>
					</li>
				<?php endwhile; ?>
			</ol>
			<?php mr_tu_pagination(); ?>
		<?php else : ?>
			<div class="empty-state">
				<?php
				get_template_part(
					'parts/components/stone',
					null,
					array(
						'variant' => 'lost',
						'km'      => '?',
					)
				);
				?>
				<h2 class="empty-state__title"><?php esc_html_e( 'No road leads there yet', 'mr-tu' ); ?></h2>
				<p class="empty-state__text"><?php esc_html_e( 'Try a shorter word, a bike model or a topic like “deposit” or “monthly”.', 'mr-tu' ); ?></p>
				<?php get_template_part( 'parts/components/role-links' ); ?>
			</div>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
