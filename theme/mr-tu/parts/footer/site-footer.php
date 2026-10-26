<?php
/**
 * Site footer: brand line, public contact details only (the block is marked
 * `data-mrtu-qc-avoid` so the Quick Contact dock steps aside), navigation columns, languages,
 * colour mode and copyright.
 *
 * Unverified NAP is never printed: without a public address the footer shows the service area
 * as a translatable string.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_columns = mr_tu_footer_columns();
$mr_tu_tel     = mr_tu_phone( 'tel' );
$mr_tu_phone   = mr_tu_phone( 'display' );
$mr_tu_address = mr_tu_public_address();
$mr_tu_hours   = mr_tu_public_hours();
?>
<footer class="site-footer" id="site-footer">
	<div class="site-footer__inner">
		<div class="site-footer__top">
			<p class="site-footer__tagline">
				<?php
				/* translators: Brand line. "Km 0" refers to Hanoi's kilometre zero. */
				esc_html_e( 'Km 0 — Hanoi starts here.', 'mr-tu' );
				?>
			</p>
		</div>

		<div class="site-footer__grid">
			<div class="site-footer__brand">
				<a class="site-footer__logo" href="<?php echo esc_url( mr_tu_home_url() ); ?>" rel="home">
					<?php echo mr_tu_wordmark( 'site-footer__mark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG, label escaped inside. ?>
				</a>
				<p class="site-footer__blurb"><?php esc_html_e( 'Motorbike and scooter rental near Hanoi’s Old Quarter — by the day, the week or the month.', 'mr-tu' ); ?></p>

				<div class="site-footer__contact" data-mrtu-qc-avoid>
					<h2 class="site-footer__heading"><?php esc_html_e( 'Contact', 'mr-tu' ); ?></h2>
					<ul class="site-footer__nap" role="list">
						<?php if ( '' !== $mr_tu_tel ) : ?>
							<li>
								<?php mr_tu_the_icon( 'phone' ); ?>
								<a href="<?php echo esc_url( $mr_tu_tel, array( 'tel' ) ); ?>"><?php echo esc_html( $mr_tu_phone ); ?></a>
							</li>
						<?php endif; ?>
						<li>
							<?php mr_tu_the_icon( 'pin' ); ?>
							<span><?php echo esc_html( '' !== $mr_tu_address ? $mr_tu_address : mr_tu_area_text() ); ?></span>
						</li>
						<?php if ( '' !== $mr_tu_hours ) : ?>
							<li>
								<?php mr_tu_the_icon( 'clock' ); ?>
								<span><?php echo esc_html( $mr_tu_hours ); ?></span>
							</li>
						<?php endif; ?>
					</ul>
				</div>
			</div>

			<?php if ( $mr_tu_columns ) : ?>
				<nav class="site-footer__nav" id="site-footer-nav" aria-label="<?php esc_attr_e( 'Footer', 'mr-tu' ); ?>">
					<?php foreach ( $mr_tu_columns as $mr_tu_column ) : ?>
						<div class="site-footer__col site-footer__col--<?php echo esc_attr( $mr_tu_column['key'] ); ?>">
							<h2 class="site-footer__heading"><?php echo esc_html( $mr_tu_column['title'] ); ?></h2>
							<ul class="site-footer__links" role="list">
								<?php foreach ( $mr_tu_column['items'] as $mr_tu_item ) : ?>
									<li><?php mr_tu_nav_link( $mr_tu_item, 'site-footer__link' ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>

		<div class="site-footer__bottom">
			<?php if ( mr_tu_switcher_links() ) : ?>
				<div class="site-footer__langs">
					<h2 class="screen-reader-text"><?php esc_html_e( 'Language', 'mr-tu' ); ?></h2>
					<?php get_template_part( 'parts/components/language-switcher', null, array( 'variant' => 'list' ) ); ?>
				</div>
			<?php endif; ?>
			<?php get_template_part( 'parts/components/theme-toggle', null, array( 'variant' => 'compact' ) ); ?>
			<p class="site-footer__copy">
				<?php
				/* translators: %s: current year. */
				echo esc_html( sprintf( __( '© %s Mr Tu', 'mr-tu' ), wp_date( 'Y' ) ) );
				?>
			</p>
		</div>
	</div>
</footer>
