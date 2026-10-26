<?php
/**
 * Render callback of mrtu/cta.
 *
 * @package MrTu\Multilang
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

echo \MrTu\Multilang\Blocks::render( 'cta', isset( $attributes ) ? $attributes : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the renderer.
