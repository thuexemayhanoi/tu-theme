<?php
/**
 * Art for the home "story" section: the owner's story photo when set in Theme settings,
 * otherwise an abstract Old Quarter street grid crossed by the orange road line ending at a
 * Km 0 stone. Decorative — no fake photography.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_photo = mr_tu_story_image_html();
?>
<figure class="mrtu-story-art<?php echo $mr_tu_photo ? ' has-photo' : ''; ?>"<?php echo $mr_tu_photo ? '' : ' aria-hidden="true"'; ?>>
	<?php if ( $mr_tu_photo ) : ?>
		<?php echo $mr_tu_photo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup. ?>
	<?php else : ?>
		<svg class="mrtu-story-art__map" viewBox="0 0 480 400" width="480" height="400" focusable="false">
			<g class="mrtu-story-art__blocks">
				<path d="M24 28h86l-6 64H20zM126 26h92l-4 52h-90zM234 24h70l8 70h-82zM322 22h134v58H330zM20 110h82l-4 70H22zM120 98h92l6 92h-102zM232 112h84l-8 74h-78zM330 100h126v84H322zM24 202h70l4 76H20zM112 210h104l-4 66h-96zM232 204h78l6 84h-90zM330 206h128l-6 78H334zM18 300h84l-4 78H24zM118 296h96l4 82H114zM234 308h82v70h-86zM334 302h120l2 76H330z"/>
			</g>
			<path class="mrtu-story-art__river" d="M470 0c-26 70-18 150 6 220s8 140-10 180"/>
			<path class="mrtu-story-art__road-base" d="M-10 250C70 236 104 192 168 192s84 54 150 54 110-60 176-74"/>
			<path class="mrtu-story-art__road" d="M-10 250C70 236 104 192 168 192s84 54 150 54 110-60 176-74"/>
			<g class="mrtu-story-art__pin" transform="translate(318 246)">
				<circle r="22"/>
				<circle class="mrtu-story-art__pin-dot" r="7"/>
			</g>
		</svg>
		<figcaption class="mrtu-story-art__caption">
			<span class="mrtu-plate">
				<span class="mrtu-plate__top"><?php esc_html_e( 'Km 0', 'mr-tu' ); ?></span>
				<span class="mrtu-plate__bottom"><?php echo esc_html( mr_tu_stone_place() ); ?></span>
			</span>
		</figcaption>
	<?php endif; ?>
</figure>
