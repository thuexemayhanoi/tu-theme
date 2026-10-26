<?php
/**
 * 404: the "KM ?" stone, friendly copy, search and the main roads back.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="site-main site-main--404" tabindex="-1">
	<section class="lost" aria-labelledby="mrtu-lost-title">
		<div class="lost__inner">
			<div class="lost__visual">
				<?php
				get_template_part(
					'parts/components/stone',
					null,
					array(
						'variant' => 'lost',
						'km'      => '?',
						'place'   => __( 'Unknown', 'mr-tu' ),
					)
				);
				?>
				<div class="lost__road" aria-hidden="true"></div>
			</div>
			<div class="lost__copy">
				<p class="lost__eyebrow"><span class="mrtu-plate mrtu-plate--inline"><span class="mrtu-plate__top"><?php esc_html_e( 'Error 404', 'mr-tu' ); ?></span></span></p>
				<h1 class="lost__title" id="mrtu-lost-title"><?php esc_html_e( 'This road isn’t on our map', 'mr-tu' ); ?></h1>
				<p class="lost__text"><?php esc_html_e( 'The page may have moved, or the link has a typo. Every trip starts again from Km 0 — search below or take one of the main roads.', 'mr-tu' ); ?></p>
				<?php get_search_form(); ?>
				<?php get_template_part( 'parts/components/role-links' ); ?>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
