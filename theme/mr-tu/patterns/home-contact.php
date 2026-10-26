<?php
/**
 * Title: Home · Contact (dark band)
 * Slug: mr-tu/home-contact
 * Categories: mr-tu
 * Keywords: contact, call, phone, cta
 * Description: Dark closing band with the Mr Tu contact card and call button (both read the verified business data from the plugin).
 * Viewport Width: 1320
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","className":"mrtu-section mrtu-section--contact"} -->
<section class="wp-block-group mrtu-section mrtu-section--contact"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Ready when you are', 'mr-tu' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Call to check availability and arrange the hand-over.', 'mr-tu' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:mrtu/cta {"variant":"call"} /-->

<!-- wp:mrtu/contact-card {"showHours":true,"showAddress":true} /--></section>
<!-- /wp:group -->
