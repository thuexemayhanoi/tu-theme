<?php
/**
 * Editorial card for posts and pages. The whole card is clickable through the title link
 * (one link per card, no duplicate tab stops). Without a featured image a designed plate +
 * road-line graphic is shown, never a grey placeholder.
 *
 * Args: `post` (WP_Post|int), `heading` (h2|h3), `variant` (default|feature|page),
 * `eyebrow` (string override for the plate label).
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

$mr_tu_args = wp_parse_args(
	isset( $args ) ? $args : array(),
	array(
		'post'    => null,
		'heading' => 'h3',
		'variant' => 'default',
		'eyebrow' => '',
	)
);
$mr_tu_post = get_post( $mr_tu_args['post'] );
if ( ! $mr_tu_post ) {
	return;
}
$mr_tu_tag     = in_array( $mr_tu_args['heading'], array( 'h2', 'h3' ), true ) ? $mr_tu_args['heading'] : 'h3';
$mr_tu_is_post = 'post' === $mr_tu_post->post_type;
$mr_tu_cat     = $mr_tu_is_post ? mr_tu_primary_category( $mr_tu_post ) : null;
$mr_tu_eyebrow = '' !== $mr_tu_args['eyebrow'] ? $mr_tu_args['eyebrow'] : ( $mr_tu_cat ? $mr_tu_cat->name : '' );
$mr_tu_image   = mr_tu_card_image_html( $mr_tu_post );
$mr_tu_excerpt = has_excerpt( $mr_tu_post ) || $mr_tu_is_post ? wp_strip_all_tags( get_the_excerpt( $mr_tu_post ) ) : '';
$mr_tu_title   = get_the_title( $mr_tu_post );
?>
<article class="post-card post-card--<?php echo esc_attr( $mr_tu_args['variant'] ); ?><?php echo $mr_tu_image ? ' has-image' : ''; ?>" data-reveal>
	<div class="post-card__media">
		<?php if ( $mr_tu_image ) : ?>
			<?php echo $mr_tu_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup. ?>
		<?php else : ?>
			<div class="post-card__art" aria-hidden="true">
				<span class="mrtu-plate">
					<span class="mrtu-plate__top"><?php echo esc_html( '' !== $mr_tu_eyebrow ? $mr_tu_eyebrow : __( 'Mr Tu', 'mr-tu' ) ); ?></span>
					<span class="mrtu-plate__bottom"><?php echo esc_html( $mr_tu_is_post ? get_the_date( 'd.m.y', $mr_tu_post ) : mr_tu_city() ); ?></span>
				</span>
			</div>
		<?php endif; ?>
	</div>
	<div class="post-card__body">
		<?php if ( '' !== $mr_tu_eyebrow ) : ?>
			<p class="post-card__eyebrow"><?php echo esc_html( $mr_tu_eyebrow ); ?></p>
		<?php endif; ?>
		<<?php echo esc_html( $mr_tu_tag ); ?> class="post-card__title">
			<a class="post-card__link" href="<?php echo esc_url( get_permalink( $mr_tu_post ) ); ?>"><?php echo esc_html( '' !== $mr_tu_title ? $mr_tu_title : __( '(no title)', 'mr-tu' ) ); ?></a>
		</<?php echo esc_html( $mr_tu_tag ); ?>>
		<?php if ( '' !== $mr_tu_excerpt ) : ?>
			<p class="post-card__excerpt"><?php echo esc_html( $mr_tu_excerpt ); ?></p>
		<?php endif; ?>
		<?php if ( $mr_tu_is_post ) : ?>
			<p class="post-card__meta">
				<?php mr_tu_the_time( 'published', $mr_tu_post ); ?>
				<span aria-hidden="true">·</span>
				<?php
				$mr_tu_minutes = mr_tu_reading_minutes( $mr_tu_post );
				/* translators: %d: estimated reading time in minutes. */
				echo esc_html( sprintf( _n( '%d min read', '%d min read', $mr_tu_minutes, 'mr-tu' ), $mr_tu_minutes ) );
				?>
			</p>
		<?php else : ?>
			<p class="post-card__more" aria-hidden="true"><?php mr_tu_the_icon( 'arrow' ); ?></p>
		<?php endif; ?>
	</div>
</article>
