<?php
/**
 * Title: Callout (inner pages)
 * Slug: mr-tu/inner-callout
 * Categories: mr-tu
 * Keywords: callout, note, tip, warning, box
 * Description: Highlighted note for guides and service pages: short heading, one or two sentences and an optional checklist.
 * Block Types: core/group
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"mrtu-callout"} -->
<div class="wp-block-group mrtu-callout"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading"><?php esc_html_e( 'Good to know', 'mr-tu' ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'One practical tip that saves the reader time or money.', 'mr-tu' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li><?php esc_html_e( 'A short, checkable point', 'mr-tu' ); ?></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><?php esc_html_e( 'Another short point', 'mr-tu' ); ?></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->
