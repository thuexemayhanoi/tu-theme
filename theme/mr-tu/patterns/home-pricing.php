<?php
/**
 * Title: Home · Pricing
 * Slug: mr-tu/home-pricing
 * Categories: mr-tu
 * Keywords: prices, pricing, day, week, month, deposit
 * Description: Section title + intro framing the Mr Tu pricing table block (prices come from the plugin, one place for the whole site).
 * Viewport Width: 1320
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","className":"mrtu-section mrtu-section--pricing"} -->
<section class="wp-block-group mrtu-section mrtu-section--pricing"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Prices by day, week or month', 'mr-tu' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Rental and deposit are always shown separately.', 'mr-tu' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:mrtu/pricing-table {"period":"day","showDeposit":true} /--></section>
<!-- /wp:group -->
