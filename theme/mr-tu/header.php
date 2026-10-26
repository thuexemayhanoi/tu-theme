<?php
/**
 * Document head and site header.
 *
 * The <title>, meta description, canonical, hreflang and robots tags are printed through
 * wp_head() by core (title-tag) and the Mr Tu Multilang SEO plugin — never here.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?> class="no-js">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e( 'Skip to content', 'mr-tu' ); ?></a>
<?php get_template_part( 'parts/header/site-header' ); ?>
