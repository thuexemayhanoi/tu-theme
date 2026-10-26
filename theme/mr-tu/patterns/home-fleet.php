<?php
/**
 * Title: Home · Fleet (bikes)
 * Slug: mr-tu/home-fleet
 * Categories: mr-tu
 * Keywords: fleet, bikes, models, scooters
 * Description: Section title + intro framing the Mr Tu fleet block (bikes come from the plugin's vehicle list).
 * Viewport Width: 1320
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"tagName":"section","className":"mrtu-section mrtu-section--fleet"} -->
<section class="wp-block-group mrtu-section mrtu-section--fleet"><!-- wp:heading -->
<h2 class="wp-block-heading"><?php esc_html_e( 'Pick your bike', 'mr-tu' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Automatic scooters for the city, semi-automatics for longer rides.', 'mr-tu' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:mrtu/fleet {"category":"all","showPrices":true} /--></section>
<!-- /wp:group -->
