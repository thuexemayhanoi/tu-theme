<?php
/**
 * Breadcrumbs from mrtu_breadcrumb_trail() (fallback: Home › current title).
 *
 * The plugin outputs the BreadcrumbList schema from the same trail; this is the visible copy.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_trail = mr_tu_breadcrumb_trail();
if ( count( $mr_tu_trail ) < 2 ) {
	return;
}
$mr_tu_last = count( $mr_tu_trail ) - 1;
?>
<nav class="mrtu-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'mr-tu' ); ?>">
	<ol class="mrtu-breadcrumbs__list" role="list">
		<?php foreach ( $mr_tu_trail as $mr_tu_index => $mr_tu_crumb ) : ?>
			<?php
			$mr_tu_name = isset( $mr_tu_crumb['name'] ) ? wp_strip_all_tags( (string) $mr_tu_crumb['name'] ) : '';
			$mr_tu_url  = isset( $mr_tu_crumb['url'] ) ? (string) $mr_tu_crumb['url'] : '';
			if ( '' === $mr_tu_name ) {
				continue;
			}
			?>
			<li class="mrtu-breadcrumbs__item">
				<?php if ( $mr_tu_index === $mr_tu_last || '' === $mr_tu_url ) : ?>
					<span<?php echo $mr_tu_index === $mr_tu_last ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $mr_tu_name ); ?></span>
				<?php else : ?>
					<a href="<?php echo esc_url( $mr_tu_url ); ?>"><?php echo esc_html( $mr_tu_name ); ?></a>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</nav>
