<?php
/**
 * Render callback of mrtu/contact-card.
 *
 * @package MrTu\Multilang
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

echo \MrTu\Multilang\Blocks::render( 'contact-card', isset( $attributes ) ? $attributes : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the renderer.
