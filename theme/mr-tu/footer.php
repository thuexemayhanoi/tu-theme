<?php
/**
 * Site footer, the mobile menu sheet (outside the header so it can stack above everything)
 * and wp_footer().
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

get_template_part( 'parts/footer/site-footer' );
get_template_part( 'parts/header/menu-sheet' );
wp_footer();
?>
</body>
</html>
