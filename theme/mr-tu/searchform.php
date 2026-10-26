<?php
/**
 * Language-aware search form: submits to the current language home so results stay in the
 * visitor's language (the plugin filters the main query by language).
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_form_id = wp_unique_id( 'mrtu-search-' );
?>
<form role="search" method="get" class="mrtu-search" action="<?php echo esc_url( mr_tu_home_url() ); ?>">
	<label class="mrtu-search__label" for="<?php echo esc_attr( $mr_tu_form_id ); ?>"><?php esc_html_e( 'Search this site', 'mr-tu' ); ?></label>
	<div class="mrtu-search__field">
		<?php mr_tu_the_icon( 'search', 'mrtu-search__icon' ); ?>
		<input class="mrtu-search__input" type="search" id="<?php echo esc_attr( $mr_tu_form_id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Bikes, prices, documents…', 'mr-tu' ); ?>" autocomplete="off" enterkeyhint="search">
		<button class="mrtu-search__submit mrtu-btn mrtu-btn--dark" type="submit"><?php esc_html_e( 'Search', 'mr-tu' ); ?></button>
	</div>
</form>
