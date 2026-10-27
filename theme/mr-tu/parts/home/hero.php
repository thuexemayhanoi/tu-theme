<?php
/**
 * Home hero: eyebrow plate, H1 from the page title, lead from the excerpt, primary CTA to the
 * fleet/pricing page, call CTA when a public phone exists, three honest fact chips and the
 * Km 0 composition (kilometre stone + licence plate over an Old Quarter street grid, or the
 * owner's hero photo when set).
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_cta   = mr_tu_primary_cta();
$mr_tu_tel   = mr_tu_phone( 'tel' );
$mr_tu_phone = mr_tu_phone( 'display' );
$mr_tu_lead  = mr_tu_lead();
$mr_tu_photo = mr_tu_hero_image_html();
$mr_tu_facts = array(
	array( 'calendar', __( 'Daily · Weekly · Monthly', 'mr-tu' ) ),
	array( 'scooter', __( 'Scooters & semi-automatics', 'mr-tu' ) ),
);
?>
<section class="home-hero<?php echo $mr_tu_photo ? ' has-photo' : ''; ?>" aria-labelledby="mrtu-home-title">
	<div class="home-hero__inner">
		<div class="home-hero__copy">
			<p class="home-hero__eyebrow">
				<span class="mrtu-plate mrtu-plate--inline"><span class="mrtu-plate__top"><?php echo esc_html( 'Mr Tú · ' . mr_tu_city() ); ?></span></span>
			</p>
			<h1 class="home-hero__title" id="mrtu-home-title"><?php echo esc_html( get_the_title() ); ?></h1>
			<?php if ( '' !== $mr_tu_lead ) : ?>
				<p class="home-hero__lead"><?php echo esc_html( $mr_tu_lead ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $mr_tu_cta['url'] || '' !== $mr_tu_tel ) : ?>
				<div class="home-hero__actions">
					<?php if ( '' !== $mr_tu_cta['url'] ) : ?>
						<a class="mrtu-btn mrtu-btn--primary mrtu-btn--lg" href="<?php echo esc_url( $mr_tu_cta['url'] ); ?>">
							<span><?php echo esc_html( 'pricing' === $mr_tu_cta['role'] ? __( 'See prices', 'mr-tu' ) : __( 'See bikes & prices', 'mr-tu' ) ); ?></span>
							<?php mr_tu_the_icon( 'arrow' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( '' !== $mr_tu_tel ) : ?>
						<a class="mrtu-btn mrtu-btn--ghost mrtu-btn--lg home-hero__call" href="<?php echo esc_url( $mr_tu_tel, array( 'tel' ) ); ?>">
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

			<ul class="home-hero__facts" role="list" aria-label="<?php esc_attr_e( 'Key facts', 'mr-tu' ); ?>">
				<?php foreach ( $mr_tu_facts as $mr_tu_fact ) : ?>
					<li class="home-hero__fact"><?php mr_tu_the_icon( $mr_tu_fact[0] ); ?><span><?php echo esc_html( $mr_tu_fact[1] ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="home-hero__visual">
			<div class="home-hero__stage">
				<?php if ( $mr_tu_photo ) : ?>
					<?php echo $mr_tu_photo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup. ?>
				<?php else : ?>
					<svg class="home-hero__grid" viewBox="0 0 480 400" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
						<path d="M24 28h86l-6 64H20zM126 26h92l-4 52h-90zM234 24h70l8 70h-82zM322 22h134v58H330zM20 110h82l-4 70H22zM120 98h92l6 92h-102zM232 112h84l-8 74h-78zM330 100h126v84H322zM24 202h70l4 76H20zM112 210h104l-4 66h-96zM232 204h78l6 84h-90zM330 206h128l-6 78H334zM18 300h84l-4 78H24zM118 296h96l4 82H114zM234 308h82v70h-86zM334 302h120l2 76H330z"/>
					</svg>
					<div class="home-hero__glow" aria-hidden="true"></div>
				<?php endif; ?>
				<svg class="home-hero__road" viewBox="0 0 560 200" preserveAspectRatio="none" aria-hidden="true" focusable="false">
					<path d="M-20 168C120 160 210 120 300 92S470 40 580 30"/>
				</svg>
			</div>
			<?php
			get_template_part(
				'parts/components/stone',
				null,
				array(
					'variant' => 'hero',
					'place'   => mr_tu_stone_place(),
					'km'      => '0',
				)
			);
			?>
			<span class="mrtu-plate home-hero__plate" aria-hidden="true">
				<span class="mrtu-plate__top">29-MT</span>
				<span class="mrtu-plate__bottom"><?php echo esc_html( mr_tu_city() ); ?></span>
			</span>
		</div>
	</div>
</section>
