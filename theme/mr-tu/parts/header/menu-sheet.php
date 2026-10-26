<?php
/**
 * Mobile bottom-sheet menu: a modal <dialog> (focus trap, Esc, backdrop, return focus handled by
 * the browser and theme.js). Primary actions sit at the bottom, inside the thumb zone.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_nav   = mr_tu_header_nav();
$mr_tu_tel   = mr_tu_phone( 'tel' );
$mr_tu_phone = mr_tu_phone( 'display' );
?>
<dialog class="menu-sheet" id="mrtu-menu-sheet" aria-labelledby="mrtu-menu-sheet-title" aria-modal="true" data-mrtu-menu>
	<div class="menu-sheet__frame">
		<div class="menu-sheet__head">
			<span class="menu-sheet__handle" aria-hidden="true"></span>
			<h2 class="menu-sheet__title" id="mrtu-menu-sheet-title"><?php esc_html_e( 'Menu', 'mr-tu' ); ?></h2>
			<button type="button" class="menu-sheet__close" data-mrtu-menu-close>
				<?php mr_tu_the_icon( 'close' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Close menu', 'mr-tu' ); ?></span>
			</button>
		</div>

		<div class="menu-sheet__body">
			<?php if ( ! empty( $mr_tu_nav['primary'] ) ) : ?>
				<nav class="menu-sheet__nav" aria-label="<?php esc_attr_e( 'Primary', 'mr-tu' ); ?>">
					<ul class="menu-sheet__list" role="list">
						<?php foreach ( $mr_tu_nav['primary'] as $mr_tu_item ) : ?>
							<li>
								<a class="menu-sheet__link<?php echo $mr_tu_item['current'] ? ' is-current' : ''; ?>" href="<?php echo esc_url( $mr_tu_item['url'] ); ?>"<?php echo $mr_tu_item['current'] ? ' aria-current="page"' : ''; ?>>
									<span><?php echo esc_html( $mr_tu_item['label'] ); ?></span>
									<?php mr_tu_the_icon( 'arrow' ); ?>
								</a>
								<?php if ( ! empty( $mr_tu_item['children'] ) ) : ?>
									<ul class="menu-sheet__sublist" role="list">
										<?php foreach ( $mr_tu_item['children'] as $mr_tu_child ) : ?>
											<li><?php mr_tu_nav_link( $mr_tu_child, 'menu-sheet__sublink' ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endif; ?>

			<?php if ( ! empty( $mr_tu_nav['more'] ) ) : ?>
				<section class="menu-sheet__group" aria-labelledby="mrtu-menu-sheet-more">
					<h3 class="menu-sheet__label" id="mrtu-menu-sheet-more"><?php esc_html_e( 'More', 'mr-tu' ); ?></h3>
					<ul class="menu-sheet__grid" role="list">
						<?php foreach ( $mr_tu_nav['more'] as $mr_tu_item ) : ?>
							<li><?php mr_tu_nav_link( $mr_tu_item, 'menu-sheet__chip' ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<?php if ( mr_tu_switcher_links() ) : ?>
				<section class="menu-sheet__group" aria-labelledby="mrtu-menu-sheet-lang">
					<h3 class="menu-sheet__label" id="mrtu-menu-sheet-lang"><?php esc_html_e( 'Language', 'mr-tu' ); ?></h3>
					<?php get_template_part( 'parts/components/language-switcher', null, array( 'variant' => 'list' ) ); ?>
				</section>
			<?php endif; ?>

			<section class="menu-sheet__group" aria-labelledby="mrtu-menu-sheet-mode">
				<h3 class="menu-sheet__label" id="mrtu-menu-sheet-mode"><?php esc_html_e( 'Appearance', 'mr-tu' ); ?></h3>
				<?php get_template_part( 'parts/components/theme-toggle', null, array( 'variant' => 'labelled' ) ); ?>
			</section>
		</div>

		<?php if ( '' !== $mr_tu_tel || ! empty( $mr_tu_nav['cta'] ) ) : ?>
			<div class="menu-sheet__actions">
				<?php if ( ! empty( $mr_tu_nav['cta'] ) ) : ?>
					<a class="mrtu-btn mrtu-btn--ghost" href="<?php echo esc_url( $mr_tu_nav['cta']['url'] ); ?>"><?php echo esc_html( $mr_tu_nav['cta']['label'] ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $mr_tu_tel ) : ?>
					<a class="mrtu-btn mrtu-btn--call" href="<?php echo esc_url( $mr_tu_tel, array( 'tel' ) ); ?>">
						<?php mr_tu_the_icon( 'phone' ); ?>
						<span>
							<?php
							/* translators: %s: public phone number. */
							echo esc_html( sprintf( __( 'Call %s', 'mr-tu' ), $mr_tu_phone ) );
							?>
						</span>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</dialog>
