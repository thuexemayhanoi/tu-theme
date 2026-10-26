<?php
/**
 * Title: Home · FAQ
 * Slug: mr-tu/home-faq
 * Categories: mr-tu
 * Keywords: faq, questions, answers, accordion
 * Description: Section title + intro and an accordion of questions (core Details blocks).
 * Viewport Width: 1320
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","className":"mrtu-section mrtu-section--faq"} -->
<section class="wp-block-group mrtu-section mrtu-section--faq"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Questions before you ride', 'mr-tu' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'The answers travellers ask for most often.', 'mr-tu' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'Which documents do I need?', 'mr-tu' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'Write the verified answer here.', 'mr-tu' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary><?php esc_html_e( 'How does the deposit work?', 'mr-tu' ); ?></summary><!-- wp:paragraph -->
<p><?php esc_html_e( 'Write the verified answer here.', 'mr-tu' ); ?></p>
<!-- /wp:paragraph --></details>
<!-- /wp:details --></section>
<!-- /wp:group -->
