<?php
/**
 * Title: Home · Story
 * Slug: mr-tu/home-story
 * Categories: mr-tu
 * Keywords: story, about, editorial
 * Description: Editorial text block; the theme adds the Old Quarter map graphic (or the story photo from Theme settings) beside it.
 * Viewport Width: 1320
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","className":"mrtu-section mrtu-section--story"} -->
<section class="wp-block-group mrtu-section mrtu-section--story"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Hanoi starts at Km 0', 'mr-tu' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'A short, true story about Mr Tu and the Old Quarter.', 'mr-tu' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'A second paragraph with practical detail visitors care about.', 'mr-tu' ); ?></p>
<!-- /wp:paragraph --></section>
<!-- /wp:group -->
