<?php
/**
 * Image helpers: hero (eager, high priority) and lazy images with honest `sizes`.
 *
 * @package MrTu\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings image ID when it is a valid image attachment.
 *
 * @param string $key Setting key (hero_image|story_image).
 * @return int
 */
function mr_tu_setting_image_id( $key ) {
	$id = absint( mr_tu_setting( $key ) );
	return ( $id && wp_attachment_is_image( $id ) ) ? $id : 0;
}

/**
 * Home hero photo: never lazy, fetched with high priority.
 *
 * @return string HTML or ''.
 */
function mr_tu_hero_image_html() {
	$id = mr_tu_setting_image_id( 'hero_image' );
	if ( ! $id ) {
		return '';
	}
	return wp_get_attachment_image(
		$id,
		'mr-tu-wide',
		false,
		array(
			'class'         => 'home-hero__photo',
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'decoding'      => 'async',
			'sizes'         => '(min-width: 1024px) 560px, calc(100vw - 32px)',
		)
	);
}

/**
 * Story photo (lazy).
 *
 * @return string HTML or ''.
 */
function mr_tu_story_image_html() {
	$id = mr_tu_setting_image_id( 'story_image' );
	if ( ! $id ) {
		return '';
	}
	return wp_get_attachment_image(
		$id,
		'large',
		false,
		array(
			'class'    => 'mrtu-story-art__photo',
			'loading'  => 'lazy',
			'decoding' => 'async',
			'sizes'    => '(min-width: 1024px) 560px, calc(100vw - 32px)',
		)
	);
}

/**
 * Card thumbnail (lazy).
 *
 * @param int|WP_Post $post Post.
 * @return string HTML or ''.
 */
function mr_tu_card_image_html( $post ) {
	if ( ! has_post_thumbnail( $post ) ) {
		return '';
	}
	return get_the_post_thumbnail(
		$post,
		'mr-tu-card',
		array(
			'class'    => 'post-card__img',
			'loading'  => 'lazy',
			'decoding' => 'async',
			'sizes'    => '(min-width: 1200px) 420px, (min-width: 768px) 45vw, calc(100vw - 32px)',
		)
	);
}

/**
 * Featured image of a single post (eager: it is usually the LCP element).
 *
 * @param int|WP_Post $post Post.
 * @return string HTML or ''.
 */
function mr_tu_featured_image_html( $post ) {
	if ( ! has_post_thumbnail( $post ) ) {
		return '';
	}
	return get_the_post_thumbnail(
		$post,
		'mr-tu-wide',
		array(
			'class'         => 'entry-media__img',
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'decoding'      => 'async',
			'sizes'         => '(min-width: 1200px) 1080px, calc(100vw - 32px)',
		)
	);
}

/**
 * Use translated attachment descriptions when one shared photo serves several languages.
 *
 * @param array   $attrs      Image HTML attributes.
 * @param WP_Post $attachment Attachment object.
 * @return array
 */
function mr_tu_localized_image_alt( $attrs, $attachment ) {
	if ( ! function_exists( 'mrtu_current_language' ) || ! $attachment instanceof WP_Post ) {
		return $attrs;
	}
	$lang = mrtu_current_language();
	$maps = get_option( 'mrtu_image_alt_translations', array() );
	$map  = isset( $maps[ $attachment->ID ] ) ? $maps[ $attachment->ID ] : array();
	if ( is_array( $map ) && isset( $map[ $lang ] ) && is_string( $map[ $lang ] ) ) {
		$attrs['alt'] = wp_strip_all_tags( $map[ $lang ] );
	}
	return $attrs;
}
add_filter( 'wp_get_attachment_image_attributes', 'mr_tu_localized_image_alt', 20, 2 );
