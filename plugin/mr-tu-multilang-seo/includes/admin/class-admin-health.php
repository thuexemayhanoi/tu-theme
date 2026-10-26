<?php
/**
 * "Kiểm tra sức khỏe" screen (contract §2.12): the cached health checks with "Run again",
 * the role × language matrix and three maintenance actions.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang\Admin;

use MrTu\Multilang\Health;
use MrTu\Multilang\Languages;
use MrTu\Multilang\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Health Check screen.
 */
final class Admin_Health {

	const SLUG = 'mrtu-health';

	const ACTION_REFRESH = 'mrtu_health_refresh';
	const ACTION_REBUILD = 'mrtu_health_rebuild';
	const ACTION_REWRITE = 'mrtu_health_flush_rewrite';
	const ACTION_SITEMAP = 'mrtu_health_flush_sitemap';
	const ACTION_JSON    = 'mrtu_health_json';

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_filter( 'mrtu_admin_pages', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_post_' . self::ACTION_REFRESH, array( __CLASS__, 'handle_refresh' ) );
		add_action( 'admin_post_' . self::ACTION_REBUILD, array( __CLASS__, 'handle_rebuild' ) );
		add_action( 'admin_post_' . self::ACTION_REWRITE, array( __CLASS__, 'handle_flush_rewrite' ) );
		add_action( 'admin_post_' . self::ACTION_SITEMAP, array( __CLASS__, 'handle_flush_sitemap' ) );
		add_action( 'admin_post_' . self::ACTION_JSON, array( __CLASS__, 'handle_json' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	/**
	 * Registers the screen (last position).
	 *
	 * @param array $pages Pages.
	 * @return array
	 */
	public static function register_page( $pages ) {
		$pages[] = array(
			'slug'       => self::SLUG,
			'title'      => __( 'Health Check', 'mr-tu-multilang-seo' ),
			'menu_title' => __( 'Health Check', 'mr-tu-multilang-seo' ),
			'capability' => 'manage_options',
			'callback'   => array( __CLASS__, 'render' ),
			'position'   => 90,
		);
		return $pages;
	}

	/**
	 * Small inline styles for the check list (attached to the shared admin stylesheet).
	 *
	 * @param string $hook Current admin hook suffix.
	 */
	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) || ! wp_style_is( 'mrtu-admin', 'enqueued' ) ) {
			return;
		}
		wp_add_inline_style(
			'mrtu-admin',
			'.mrtu-health{list-style:none;margin:0;padding:0}.mrtu-health__item{display:grid;grid-template-columns:auto 1fr;gap:6px 12px;padding:12px 0;border-bottom:1px solid var(--mrtu-a-border,#e3e4df)}.mrtu-health__item:last-child{border-bottom:0}.mrtu-health__label{margin:0;font-weight:600}.mrtu-health__detail{grid-column:2;margin:0;color:var(--mrtu-a-muted,#5f6460);font-size:13px;line-height:1.5;overflow-wrap:anywhere}.mrtu-matrix td,.mrtu-matrix th{text-align:center}.mrtu-matrix th:first-child,.mrtu-matrix td:first-child{text-align:left}'
		);
	}

	/**
	 * Renders the screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
		}
		$report  = Health::get();
		$summary = $report['summary'];

		Admin::page_open(
			__( 'Health Check', 'mr-tu-multilang-seo' ),
			__( 'Checks the five language versions, SEO metadata, internal links, business data and the live pages. Results are kept for 10 minutes; press “Run again” after changes.', 'mr-tu-multilang-seo' )
		);

		echo '<div class="mrtu-stats">';
		self::stat( number_format_i18n( $summary['error'] ), __( 'Errors', 'mr-tu-multilang-seo' ) );
		self::stat( number_format_i18n( $summary['warning'] ), __( 'Warnings', 'mr-tu-multilang-seo' ) );
		self::stat( number_format_i18n( $summary['ok'] ), __( 'OK', 'mr-tu-multilang-seo' ) );
		self::stat( wp_date( 'H:i', (int) $report['time'] ), __( 'Last run', 'mr-tu-multilang-seo' ) . ( empty( $report['loopback'] ) ? ' · ' . __( 'without live page fetch', 'mr-tu-multilang-seo' ) : '' ) );
		echo '</div>';

		echo '<div class="mrtu-actions" style="margin:0 0 16px">';
		self::action_form( self::ACTION_REFRESH, __( 'Run again', 'mr-tu-multilang-seo' ), 'button-primary mrtu-button' );
		self::action_form( self::ACTION_REBUILD, __( 'Rebuild translation maps', 'mr-tu-multilang-seo' ), 'button', __( 'Rebuild every translation map from the stored relationships?', 'mr-tu-multilang-seo' ) );
		self::action_form( self::ACTION_REWRITE, __( 'Flush rewrite rules', 'mr-tu-multilang-seo' ), 'button' );
		if ( function_exists( 'mrtu_sitemap_flush' ) ) {
			self::action_form( self::ACTION_SITEMAP, __( 'Flush sitemap cache', 'mr-tu-multilang-seo' ), 'button' );
		}
		self::action_form( self::ACTION_JSON, __( 'Download JSON', 'mr-tu-multilang-seo' ), 'button' );
		echo '</div>';

		$groups = array(
			'error'   => __( 'Errors', 'mr-tu-multilang-seo' ),
			'warning' => __( 'Warnings', 'mr-tu-multilang-seo' ),
			'ok'      => __( 'OK', 'mr-tu-multilang-seo' ),
		);
		foreach ( self::grouped( $report['checks'] ) as $severity => $checks ) {
			if ( ! $checks ) {
				continue;
			}
			/* translators: 1: severity label, 2: number of checks. */
			Admin::card_open( sprintf( __( '%1$s (%2$d)', 'mr-tu-multilang-seo' ), isset( $groups[ $severity ] ) ? $groups[ $severity ] : $severity, count( $checks ) ), '', 'mrtu-health-' . $severity );
			echo '<ul class="mrtu-health">';
			foreach ( $checks as $check ) {
				self::render_check( $check );
			}
			echo '</ul>';
			Admin::card_close();
		}

		self::render_matrix();
		Admin::page_close();
	}

	/**
	 * Groups checks by severity, errors first.
	 *
	 * @param array[] $checks Checks.
	 * @return array severity => checks
	 */
	public static function grouped( array $checks ) {
		$out = array(
			'error'   => array(),
			'warning' => array(),
			'ok'      => array(),
		);
		foreach ( $checks as $check ) {
			$severity           = isset( $check['severity'], $out[ $check['severity'] ] ) ? $check['severity'] : 'warning';
			$out[ $severity ][] = $check;
		}
		return $out;
	}

	/**
	 * One stat card.
	 *
	 * @param string $value Value.
	 * @param string $label Label.
	 */
	private static function stat( $value, $label ) {
		echo '<div class="mrtu-stat"><span class="mrtu-stat__value">' . esc_html( $value ) . '</span><span class="mrtu-stat__label">' . esc_html( $label ) . '</span></div>';
	}

	/**
	 * One-button admin-post form.
	 *
	 * @param string $action  Action / nonce action.
	 * @param string $label   Button label.
	 * @param string $classes Button classes.
	 * @param string $confirm Optional confirmation text.
	 */
	private static function action_form( $action, $label, $classes = 'button', $confirm = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		wp_nonce_field( $action, '_mrtu_nonce' );
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		echo '<button type="submit" class="' . esc_attr( 'button ' . $classes ) . '"' . ( '' !== $confirm ? ' data-mrtu-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>' . esc_html( $label ) . '</button>';
		echo '</form>';
	}

	/**
	 * One check row.
	 *
	 * @param array $check Check.
	 */
	private static function render_check( array $check ) {
		$labels = array(
			'ok'      => __( 'OK', 'mr-tu-multilang-seo' ),
			'warning' => __( 'Warning', 'mr-tu-multilang-seo' ),
			'error'   => __( 'Error', 'mr-tu-multilang-seo' ),
		);
		$state  = 'warning' === $check['severity'] ? 'warn' : $check['severity'];
		echo '<li class="mrtu-health__item" id="mrtu-check-' . esc_attr( $check['id'] ) . '">';
		echo Admin::status( $state, isset( $labels[ $check['severity'] ] ) ? $labels[ $check['severity'] ] : $check['severity'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status() escapes.
		echo '<p class="mrtu-health__label">' . esc_html( $check['label'] );
		if ( ! empty( $check['link'] ) ) {
			echo ' <a href="' . esc_url( $check['link'] ) . '">' . esc_html__( 'Open', 'mr-tu-multilang-seo' ) . '</a>';
		}
		echo '</p>';
		if ( '' !== $check['detail'] ) {
			echo '<p class="mrtu-health__detail">' . esc_html( $check['detail'] ) . '</p>';
		}
		echo '</li>';
	}

	/**
	 * Role × language matrix with links to edit/create.
	 */
	private static function render_matrix() {
		$data  = Health::completeness();
		$roles = Roles::registry();
		Admin::card_open(
			__( 'Core pages × languages', 'mr-tu-multilang-seo' ),
			__( '✅ published · ✏️ draft or other status · ➕ missing. Create missing versions from the Translations screen or by seeding.', 'mr-tu-multilang-seo' ),
			'mrtu-health-matrix'
		);
		echo '<div class="mrtu-scroll"><table class="mrtu-table mrtu-matrix"><thead><tr><th scope="col">' . esc_html__( 'Role', 'mr-tu-multilang-seo' ) . '</th>';
		foreach ( $data['languages'] as $code ) {
			echo '<th scope="col"><abbr title="' . esc_attr( Languages::admin_name( $code ) ) . '">' . esc_html( strtoupper( $code ) ) . '</abbr></th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $data['matrix'] as $role => $cells ) {
			echo '<tr><th scope="row">' . esc_html( isset( $roles[ $role ]['label'] ) ? $roles[ $role ]['label'] : $role ) . '</th>';
			foreach ( $data['languages'] as $code ) {
				$cell = $cells[ $code ];
				if ( 'published' === $cell['status'] ) {
					$html = '<a class="mrtu-lang-cell" href="' . esc_url( (string) get_edit_post_link( $cell['id'], 'raw' ) ) . '" title="' . esc_attr__( 'Published — edit', 'mr-tu-multilang-seo' ) . '">✅</a>';
				} elseif ( 'draft' === $cell['status'] ) {
					$html = '<a class="mrtu-lang-cell" href="' . esc_url( (string) get_edit_post_link( $cell['id'], 'raw' ) ) . '" title="' . esc_attr__( 'Not published — edit', 'mr-tu-multilang-seo' ) . '">✏️</a>';
				} else {
					$html = '<a class="mrtu-lang-cell" href="' . esc_url( Admin::url( Admin_Translations::SLUG ) ) . '" title="' . esc_attr__( 'Missing — create', 'mr-tu-multilang-seo' ) . '">➕</a>';
				}
				echo '<td>' . $html . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		Admin::card_close();
	}

	/*
	 * Actions
	 * ------------------------------------------------------------------
	 */

	/**
	 * "Run again": drop the cache (the screen recomputes on load).
	 */
	public static function handle_refresh() {
		Admin::verify_request( self::ACTION_REFRESH, 'manage_options' );
		Health::flush();
		Admin::redirect_with_notice( Admin::url( self::SLUG ), __( 'Health checks refreshed.', 'mr-tu-multilang-seo' ) );
	}

	/**
	 * Rebuilds every translation map.
	 */
	public static function handle_rebuild() {
		Admin::verify_request( self::ACTION_REBUILD, 'manage_options' );
		$count = Health::rebuild_translation_maps();
		/* translators: %s: number of translation groups. */
		Admin::redirect_with_notice( Admin::url( self::SLUG ), sprintf( _n( '%s translation map rebuilt.', '%s translation maps rebuilt.', $count, 'mr-tu-multilang-seo' ), number_format_i18n( $count ) ) );
	}

	/**
	 * Flushes the rewrite rules.
	 */
	public static function handle_flush_rewrite() {
		Admin::verify_request( self::ACTION_REWRITE, 'manage_options' );
		Health::flush_rewrite();
		Admin::redirect_with_notice( Admin::url( self::SLUG ), __( 'Rewrite rules flushed.', 'mr-tu-multilang-seo' ) );
	}

	/**
	 * Flushes the sitemap cache.
	 */
	public static function handle_flush_sitemap() {
		Admin::verify_request( self::ACTION_SITEMAP, 'manage_options' );
		if ( ! Health::flush_sitemap() ) {
			Admin::redirect_with_notice( Admin::url( self::SLUG ), __( 'The sitemap module is not available; nothing to flush.', 'mr-tu-multilang-seo' ), 'warning' );
		}
		Admin::redirect_with_notice( Admin::url( self::SLUG ), __( 'Sitemap cache flushed.', 'mr-tu-multilang-seo' ) );
	}

	/**
	 * "Download JSON": the current (cached or freshly computed) report as a file.
	 */
	public static function handle_json() {
		Admin::verify_request( self::ACTION_JSON, 'manage_options' );
		$report              = Health::get();
		$report['site']      = home_url( '/' );
		$report['generated'] = gmdate( 'c', (int) $report['time'] );
		$report['grouped']   = self::grouped( $report['checks'] );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mrtu-health-' . gmdate( 'Ymd-His', (int) $report['time'] ) . '.json"' );
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}
}
