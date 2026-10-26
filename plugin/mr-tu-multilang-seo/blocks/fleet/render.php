<?php
/**
 * Render callback of mrtu/fleet.
 *
 * @package MrTu\Multilang
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

echo \MrTu\Multilang\Blocks::render( 'fleet', isset( $attributes ) ? $attributes : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the renderer.
