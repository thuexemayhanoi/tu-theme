<?php
/**
 * Sticky site header: brand, desktop navigation (≥ 1024 px), language, colour mode, contact and
 * call actions; on mobile the brand, a call icon button and the menu button.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_nav     = mr_tu_header_nav();
$mr_tu_tel     = mr_tu_phone( 'tel' );
$mr_tu_phone   = mr_tu_phone( 'display' );
$mr_tu_logo_id = (int) get_theme_mod( 'custom_logo' );
?>
<header class="site-header" data-mrtu-header>
	<div class="site-header__inner">
		<a class="site-brand" href="<?php echo esc_url( mr_tu_home_url() ); ?>" rel="home"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>>
			<?php
			if ( $mr_tu_logo_id && wp_attachment_is_image( $mr_tu_logo_id ) ) {
				echo wp_get_attachment_image(
					$mr_tu_logo_id,
					'full',
					false,
					array(
						'class'         => 'site-brand__logo',
						'alt'           => get_bloginfo( 'name', 'display' ),
						'loading'       => 'eager',
						'fetchpriority' => 'low',
					)
				);
			} else {
				echo mr_tu_wordmark( 'site-brand__mark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG, label escaped inside.
			}
			?>
		</a>

		<?php if ( ! empty( $mr_tu_nav['primary'] ) || ! empty( $mr_tu_nav['more'] ) ) : ?>
			<nav class="site-nav" aria-label="<?php esc_attr_e( 'Primary', 'mr-tu' ); ?>">
				<?php mr_tu_render_header_nav( $mr_tu_nav ); ?>
			</nav>
		<?php endif; ?>

		<div class="site-header__tools">
			<?php get_template_part( 'parts/components/language-switcher', null, array( 'variant' => 'popover' ) ); ?>
			<?php get_template_part( 'parts/components/theme-toggle', null, array( 'variant' => 'compact' ) ); ?>

			<?php if ( ! empty( $mr_tu_nav['cta'] ) ) : ?>
				<a class="mrtu-btn mrtu-btn--ghost site-header__contact<?php echo $mr_tu_nav['cta']['current'] ? ' is-current' : ''; ?>" href="<?php echo esc_url( $mr_tu_nav['cta']['url'] ); ?>"<?php echo $mr_tu_nav['cta']['current'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $mr_tu_nav['cta']['label'] ); ?></a>
			<?php endif; ?>

			<?php if ( '' !== $mr_tu_tel ) : ?>
				<a class="mrtu-btn mrtu-btn--call site-header__call" href="<?php echo esc_url( $mr_tu_tel, array( 'tel' ) ); ?>">
					<?php mr_tu_the_icon( 'phone' ); ?>
					<span class="site-header__call-label"><?php esc_html_e( 'Call', 'mr-tu' ); ?></span>
					<span class="site-header__call-number"><?php echo esc_html( $mr_tu_phone ); ?></span>
				</a>
			<?php endif; ?>

			<button type="button" class="site-header__menu-btn" data-mrtu-menu-open aria-haspopup="dialog" aria-controls="mrtu-menu-sheet" aria-expanded="false">
				<?php mr_tu_the_icon( 'menu' ); ?>
				<span class="site-header__menu-label"><?php esc_html_e( 'Menu', 'mr-tu' ); ?></span>
			</button>
			<a class="site-header__menu-btn site-header__menu-btn--nojs" href="#site-footer">
				<?php mr_tu_the_icon( 'menu' ); ?>
				<span class="site-header__menu-label"><?php esc_html_e( 'Menu', 'mr-tu' ); ?></span>
			</a>
		</div>
	</div>
	<div class="reading-progress" aria-hidden="true"></div>
</header>
