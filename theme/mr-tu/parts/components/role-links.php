<?php
/**
 * Quick links to the core pages (404 and empty search): roles in the current language, or the
 * top-level pages without the plugin.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_items = array();
if ( mr_tu_has_plugin() ) {
	$mr_tu_spec = mr_tu_role_nav_spec();
	foreach ( array( 'pillar', 'fleet', 'pricing', 'guide-faq', 'contact' ) as $mr_tu_role ) {
		if ( isset( $mr_tu_spec[ $mr_tu_role ] ) ) {
			$mr_tu_item = mr_tu_role_item( $mr_tu_role, $mr_tu_spec[ $mr_tu_role ] );
			if ( null !== $mr_tu_item ) {
				$mr_tu_items[] = $mr_tu_item;
			}
		}
	}
} else {
	$mr_tu_items = mr_tu_page_items( 5 );
}
?>
<nav class="role-links" aria-label="<?php esc_attr_e( 'Popular pages', 'mr-tu' ); ?>">
	<ul class="role-links__list" role="list">
		<li>
			<a class="role-links__link role-links__link--home" href="<?php echo esc_url( mr_tu_home_url() ); ?>">
				<span><?php esc_html_e( 'Home', 'mr-tu' ); ?></span><?php mr_tu_the_icon( 'arrow' ); ?>
			</a>
		</li>
		<?php foreach ( $mr_tu_items as $mr_tu_item ) : ?>
			<li>
				<a class="role-links__link" href="<?php echo esc_url( $mr_tu_item['url'] ); ?>">
					<span><?php echo esc_html( $mr_tu_item['label'] ); ?></span><?php mr_tu_the_icon( 'arrow' ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
