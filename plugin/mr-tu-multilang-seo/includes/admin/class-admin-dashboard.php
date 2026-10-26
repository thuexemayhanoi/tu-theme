<?php
/**
 * "Tổng quan" dashboard (contract §2.12): stat cards, business verification summary, SEO mode,
 * Quick Contact status, the content seeder (admin-post action) and the configuration export.
 *
 * Registered with the menu slug `mrtu`, which the Admin class uses as the top-level page.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang\Admin;

use MrTu\Multilang\Business;
use MrTu\Multilang\Health;
use MrTu\Multilang\Languages;
use MrTu\Multilang\Quick_Contact;
use MrTu\Multilang\Seeder;
use MrTu\Multilang\Translations;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard screen.
 */
final class Admin_Dashboard {

	const SLUG = Admin::MENU_SLUG;

	const ACTION_SEED = 'mrtu_seed_content';

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_filter( 'mrtu_admin_pages', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_post_' . self::ACTION_SEED, array( __CLASS__, 'handle_seed' ) );
	}

	/**
	 * Registers the dashboard (position 0 = first; read access for editors, actions need manage_options).
	 *
	 * @param array $pages Pages.
	 * @return array
	 */
	public static function register_page( $pages ) {
		$pages[] = array(
			'slug'       => self::SLUG,
			'title'      => __( 'Overview', 'mr-tu-multilang-seo' ),
			'menu_title' => __( 'Overview', 'mr-tu-multilang-seo' ),
			'capability' => 'edit_pages',
			'callback'   => array( __CLASS__, 'render' ),
			'position'   => 0,
		);
		return $pages;
	}

	/**
	 * Renders the screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
		}
		Admin::page_open(
			__( 'Mr Tu — Overview', 'mr-tu-multilang-seo' ),
			__( 'One place to see the state of the five language versions, the business data and the SEO setup. Nothing here changes the website until you press a button.', 'mr-tu-multilang-seo' )
		);
		self::render_stats();
		echo '<div class="mrtu-grid mrtu-grid--2">';
		self::render_business_card();
		self::render_seo_card();
		echo '</div>';
		self::render_quick_contact_card();
		if ( current_user_can( 'manage_options' ) ) {
			self::render_seed_card();
			self::render_export_card();
		}
		Admin::page_close();
	}

	/*
	 * Cards
	 * ------------------------------------------------------------------
	 */

	/**
	 * Stat cards: published pages per language, completeness, posts, health counts.
	 */
	private static function render_stats() {
		$pages = Admin_Translations::language_counts( 'page', array( 'publish' ) );
		$posts = Admin_Translations::language_counts( 'post', array( 'publish' ) );
		$done  = Health::completeness();
		$cache = Health::cached();

		echo '<div class="mrtu-stats">';
		$bits = array();
		foreach ( Languages::all( true ) as $code => $lang ) {
			$bits[] = strtoupper( $code ) . ' ' . number_format_i18n( isset( $pages[ $code ] ) ? $pages[ $code ] : 0 );
		}
		self::stat( implode( ' · ', $bits ), __( 'Published pages per language', 'mr-tu-multilang-seo' ), Admin::url( Admin_Translations::SLUG ) );
		/* translators: 1: published count, 2: total count. */
		self::stat( $done['percent'] . '%', sprintf( __( 'Core pages translated (%1$d of %2$d published)', 'mr-tu-multilang-seo' ), $done['published'], $done['total'] ), Admin::url( Admin_Translations::SLUG ) );
		self::stat( number_format_i18n( array_sum( $posts ) ), __( 'Published blog posts (all languages)', 'mr-tu-multilang-seo' ), admin_url( 'edit.php' ) );
		if ( $cache ) {
			/* translators: 1: number of warnings, 2: number of errors. */
			self::stat( number_format_i18n( $cache['summary']['warning'] ) . ' / ' . number_format_i18n( $cache['summary']['error'] ), sprintf( __( 'Health warnings / errors (checked %s)', 'mr-tu-multilang-seo' ), wp_date( 'H:i', (int) $cache['time'] ) ), Admin::url( Admin_Health::SLUG ) );
		} else {
			self::stat( '—', __( 'Health check not run yet — open Health Check', 'mr-tu-multilang-seo' ), Admin::url( Admin_Health::SLUG ) );
		}
		echo '</div>';
	}

	/**
	 * One stat card.
	 *
	 * @param string $value Value.
	 * @param string $label Label.
	 * @param string $url   Link.
	 */
	private static function stat( $value, $label, $url ) {
		echo '<a class="mrtu-stat" href="' . esc_url( $url ) . '"><span class="mrtu-stat__value">' . esc_html( $value ) . '</span><span class="mrtu-stat__label">' . esc_html( $label ) . '</span></a>';
	}

	/**
	 * Business verification summary.
	 */
	private static function render_business_card() {
		$business = Business::get_business();
		$pricing  = Business::get_pricing();
		Admin::card_open(
			__( 'Store information', 'mr-tu-multilang-seo' ),
			__( 'Only verified facts appear on the website. Everything else shows “PENDING VERIFICATION” here and stays hidden from visitors.', 'mr-tu-multilang-seo' )
		);
		$rows = array(
			__( 'Brand', 'mr-tu-multilang-seo' )         => $business['brand'],
			__( 'Listing name', 'mr-tu-multilang-seo' )  => $business['display_name'],
			__( 'Address', 'mr-tu-multilang-seo' )       => $business['address'],
			__( 'Phone', 'mr-tu-multilang-seo' )         => $business['phone'],
			__( 'Opening hours', 'mr-tu-multilang-seo' ) => $business['hours'],
			__( 'Service model', 'mr-tu-multilang-seo' ) => $business['service_model'],
			__( 'Google Maps link', 'mr-tu-multilang-seo' ) => $business['maps_url'],
		);
		echo '<table class="mrtu-table"><tbody>';
		foreach ( $rows as $label => $record ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . Admin::badge( ! empty( $record['verified'] ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		}
		$public   = 0;
		$verified = 0;
		foreach ( $pricing['vehicles'] as $vehicle ) {
			if ( 'hidden' === $vehicle['status'] ) {
				continue;
			}
			++$public;
			if ( ! empty( $vehicle['verified'] ) ) {
				++$verified;
			}
		}
		/* translators: 1: verified vehicles, 2: public vehicles. */
		$price_label = empty( $pricing['verified'] ) ? __( 'Not confirmed — prices show “on request”', 'mr-tu-multilang-seo' ) : sprintf( __( 'Confirmed (%1$d of %2$d vehicles)', 'mr-tu-multilang-seo' ), $verified, $public );
		echo '<tr><th scope="row">' . esc_html__( 'Price list', 'mr-tu-multilang-seo' ) . '</th><td>' . Admin::badge( ! empty( $pricing['verified'] ), $price_label ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		echo '</tbody></table>';
		echo '<p class="mrtu-actions"><a class="button" href="' . esc_url( Admin::url( Admin_Business::SLUG ) ) . '">' . esc_html__( 'Store info', 'mr-tu-multilang-seo' ) . '</a> <a class="button" href="' . esc_url( Admin::url( Admin_Pricing::SLUG ) ) . '">' . esc_html__( 'Prices', 'mr-tu-multilang-seo' ) . '</a></p>';
		Admin::card_close();
	}

	/**
	 * Current SEO mode.
	 */
	private static function render_seo_card() {
		$mode  = Health::seo_mode();
		$names = array(
			'auto'       => __( 'Automatic', 'mr-tu-multilang-seo' ),
			'compat'     => __( 'Yoast compatibility', 'mr-tu-multilang-seo' ),
			'standalone' => __( 'Standalone', 'mr-tu-multilang-seo' ),
		);
		Admin::card_open(
			__( 'SEO mode', 'mr-tu-multilang-seo' ),
			__( 'Standalone: the plugin prints title, description, canonical, hreflang, Open Graph, schema and sitemaps. Yoast compatibility: Yoast keeps its output and the plugin only adds hreflang.', 'mr-tu-multilang-seo' )
		);
		echo '<table class="mrtu-table"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Setting', 'mr-tu-multilang-seo' ) . '</th><td>' . esc_html( isset( $names[ $mode['setting'] ] ) ? $names[ $mode['setting'] ] : $mode['setting'] ) . '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Running now', 'mr-tu-multilang-seo' ) . '</th><td>' . Admin::status( 'info', $names[ $mode['effective'] ] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status() escapes.
		echo '<tr><th scope="row">' . esc_html__( 'Yoast SEO', 'mr-tu-multilang-seo' ) . '</th><td>' . esc_html( $mode['yoast'] ? __( 'Active', 'mr-tu-multilang-seo' ) : __( 'Not active', 'mr-tu-multilang-seo' ) ) . '</td></tr>';
		if ( ! $mode['module'] ) {
			echo '<tr><th scope="row">' . esc_html__( 'SEO module', 'mr-tu-multilang-seo' ) . '</th><td>' . Admin::status( 'warn', __( 'Not loaded', 'mr-tu-multilang-seo' ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status() escapes.
		}
		echo '</tbody></table>';
		echo '<p class="mrtu-actions"><a class="button" href="' . esc_url( Admin::url( 'mrtu-seo' ) ) . '">' . esc_html__( 'SEO settings', 'mr-tu-multilang-seo' ) . '</a></p>';
		Admin::card_close();
	}

	/**
	 * Quick Contact status.
	 */
	private static function render_quick_contact_card() {
		Admin::card_open(
			__( 'Quick Contact', 'mr-tu-multilang-seo' ),
			__( 'The floating contact button on the website. An item only appears when it is switched on and its data is verified.', 'mr-tu-multilang-seo' )
		);
		if ( ! class_exists( '\MrTu\Multilang\Quick_Contact' ) ) {
			echo '<p class="mrtu-meta">' . esc_html__( 'The Quick Contact module is not available.', 'mr-tu-multilang-seo' ) . '</p>';
			Admin::card_close();
			return;
		}
		$settings = Quick_Contact::settings();
		$labels   = Quick_Contact::labels();
		echo '<p class="mrtu-actions">' . Admin::status( ! empty( $settings['enabled'] ) ? 'ok' : 'info', ! empty( $settings['enabled'] ) ? __( 'Switched on', 'mr-tu-multilang-seo' ) : __( 'Switched off', 'mr-tu-multilang-seo' ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status() escapes.
		echo '<table class="mrtu-table"><tbody>';
		foreach ( Quick_Contact::item_status() as $item => $status ) {
			$name = isset( $labels[ $item ] ) ? $labels[ $item ] : $item;
			if ( ! $status['on'] ) {
				$badge = Admin::status( 'info', __( 'Off', 'mr-tu-multilang-seo' ) );
			} elseif ( $status['publishable'] ) {
				$badge = Admin::status( 'ok', __( 'Showing', 'mr-tu-multilang-seo' ) );
			} else {
				$badge = Admin::status( 'warn', __( 'Hidden', 'mr-tu-multilang-seo' ) );
			}
			echo '<tr><th scope="row">' . esc_html( $name ) . '</th><td>' . $badge . ( $status['on'] && ! $status['publishable'] && '' !== $status['reason'] ? ' <span class="mrtu-meta">' . esc_html( $status['reason'] ) . '</span>' : '' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status() escapes.
		}
		echo '</tbody></table>';
		echo '<p class="mrtu-actions"><a class="button" href="' . esc_url( Admin::url( Admin_Quick_Contact::SLUG ) ) . '">' . esc_html__( 'Quick Contact settings', 'mr-tu-multilang-seo' ) . '</a></p>';
		Admin::card_close();
	}

	/**
	 * Seed content card: explanation, source, languages, form, last report.
	 */
	private static function render_seed_card() {
		$dir       = Seeder::source_dir();
		$available = Seeder::available_languages( $dir );
		Admin::card_open(
			__( 'Seed content', 'mr-tu-multilang-seo' ),
			__( 'Creates the core pages, blog posts and categories of every language from the content bundled with the plugin, links them as translations and assigns their roles. Safe to run again: pages that already exist are skipped, and pages you edited are never overwritten.', 'mr-tu-multilang-seo' ),
			'mrtu-seed'
		);
		echo '<p class="mrtu-meta">' . esc_html__( 'Source folder:', 'mr-tu-multilang-seo' ) . ' <code>' . esc_html( $dir ) . '</code></p>';
		if ( ! $available ) {
			echo '<p>' . Admin::status( 'error', __( 'No seed content found', 'mr-tu-multilang-seo' ) ) . ' <span class="mrtu-meta">' . esc_html__( 'The folder has no {lang}/pages.json files. Install the plugin ZIP built with scripts/build.sh.', 'mr-tu-multilang-seo' ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status() escapes.
		} else {
			$bits = array();
			foreach ( $available as $code => $counts ) {
				/* translators: 1: language code, 2: number of pages, 3: number of posts. */
				$bits[] = sprintf( __( '%1$s: %2$d pages, %3$d posts', 'mr-tu-multilang-seo' ), strtoupper( $code ), $counts['pages'], $counts['posts'] );
			}
			echo '<p class="mrtu-meta">' . esc_html__( 'Available:', 'mr-tu-multilang-seo' ) . ' ' . esc_html( implode( ' · ', $bits ) ) . '</p>';

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			Admin::toggle( 'set_front', __( 'Set reading settings (front page / posts page)', 'mr-tu-multilang-seo' ), 'page' !== get_option( 'show_on_front' ), __( 'Makes the Vietnamese home page the front page and the Vietnamese blog page the posts page; the other languages follow through their translations. Also sets the timezone (if empty) and the date format d/m/Y.', 'mr-tu-multilang-seo' ) );
			Admin::toggle( 'update', __( 'Refresh unchanged pages from the files', 'mr-tu-multilang-seo' ), false, __( 'Only pages whose content is still exactly as seeded are rewritten. Pages edited on the website are reported as “customized” and kept.', 'mr-tu-multilang-seo' ) );
			Admin::toggle( 'dry_run', __( 'Dry run (report only, change nothing)', 'mr-tu-multilang-seo' ), false );
			wp_nonce_field( self::ACTION_SEED, '_mrtu_nonce' );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SEED ) . '">';
			echo '<p class="mrtu-actions"><button type="submit" class="button button-primary mrtu-button" data-mrtu-confirm="' . esc_attr__( 'Seed the content now? Existing pages are kept; new pages are created in every language that has content.', 'mr-tu-multilang-seo' ) . '">' . esc_html__( 'Seed now', 'mr-tu-multilang-seo' ) . '</button></p>';
			echo '</form>';
		}
		self::render_last_report();
		Admin::card_close();
	}

	/**
	 * Last seeding report.
	 */
	private static function render_last_report() {
		$report = Seeder::last_report();
		if ( ! $report ) {
			echo '<p class="mrtu-meta">' . esc_html__( 'Not seeded yet.', 'mr-tu-multilang-seo' ) . '</p>';
			return;
		}
		$user = ! empty( $report['user_id'] ) ? get_userdata( (int) $report['user_id'] ) : null;
		$when = sprintf(
			/* translators: 1: date and time, 2: user display name. */
			__( 'Last run %1$s by %2$s', 'mr-tu-multilang-seo' ),
			wp_date( get_option( 'date_format' ) . ' H:i', (int) $report['finished'] ),
			$user ? $user->display_name : 'WP-CLI'
		);
		echo '<h3 class="mrtu-card__title">' . esc_html__( 'Last report', 'mr-tu-multilang-seo' ) . '</h3>';
		echo '<p class="mrtu-meta">' . esc_html( $when ) . ( ! empty( $report['dry_run'] ) ? ' ' . Admin::status( 'info', __( 'Dry run', 'mr-tu-multilang-seo' ) ) : '' ) . ( ! empty( $report['update'] ) ? ' ' . Admin::status( 'info', __( 'Update mode', 'mr-tu-multilang-seo' ) ) : '' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status() escapes.
		echo '<div class="mrtu-scroll"><table class="mrtu-table"><thead><tr>';
		foreach ( array( __( 'Language', 'mr-tu-multilang-seo' ), __( 'Created', 'mr-tu-multilang-seo' ), __( 'Updated', 'mr-tu-multilang-seo' ), __( 'Skipped', 'mr-tu-multilang-seo' ), __( 'Customized', 'mr-tu-multilang-seo' ), __( 'Errors', 'mr-tu-multilang-seo' ) ) as $head ) {
			echo '<th scope="col">' . esc_html( $head ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $report['languages'] as $code => $lang ) {
			if ( empty( $lang['requested'] ) ) {
				continue;
			}
			echo '<tr><th scope="row">' . esc_html( Languages::admin_name( $code ) ) . ( ! empty( $lang['missing'] ) ? ' ' . Admin::status( 'warn', __( 'No content', 'mr-tu-multilang-seo' ) ) : '' ) . '</th>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status() escapes.
			foreach ( array( 'created', 'updated', 'skipped', 'customized', 'errors' ) as $key ) {
				echo '<td>' . esc_html( number_format_i18n( (int) $lang[ $key ] ) ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';

		$notes = array();
		foreach ( $report['languages'] as $code => $lang ) {
			foreach ( $lang['items'] as $item ) {
				if ( in_array( $item['action'], array( 'errors', 'customized' ), true ) ) {
					$notes[] = strtoupper( $code ) . ' · ' . $item['seed_id'] . ': ' . $item['message'];
				}
			}
		}
		$notes = array_merge( $notes, (array) $report['front']['messages'], (array) $report['messages'] );
		if ( $notes ) {
			echo '<ul class="mrtu-meta">';
			foreach ( array_slice( $notes, 0, 30 ) as $note ) {
				echo '<li>' . esc_html( $note ) . '</li>';
			}
			echo '</ul>';
		}
	}

	/**
	 * Export link (Business export action, same nonce as the Store info screen).
	 */
	private static function render_export_card() {
		Admin::card_open(
			__( 'Export configuration', 'mr-tu-multilang-seo' ),
			__( 'Downloads the store information and prices as a JSON backup. Import and restore live under Store info → Backup & restore; WP-CLI: wp mrtu export / wp mrtu import.', 'mr-tu-multilang-seo' )
		);
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mrtu-actions">';
		wp_nonce_field( 'mrtu_business_export', '_mrtu_nonce' );
		echo '<input type="hidden" name="action" value="mrtu_business_export">';
		echo '<button type="submit" class="button">' . esc_html__( 'Download backup (JSON)', 'mr-tu-multilang-seo' ) . '</button>';
		echo ' <a class="button" href="' . esc_url( Admin::url( Admin_Business::SLUG ) . '#mrtu-backup' ) . '">' . esc_html__( 'Backup & restore', 'mr-tu-multilang-seo' ) . '</a>';
		echo '</form>';
		Admin::card_close();
	}

	/*
	 * Actions
	 * ------------------------------------------------------------------
	 */

	/**
	 * "Seed now" (admin-post): nonce + manage_options, runs the seeder and reports back.
	 */
	public static function handle_seed() {
		Admin::verify_request( self::ACTION_SEED, 'manage_options' );
		// Nonce verified by Admin::verify_request() above.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$args = array(
			'dry_run'   => ! empty( $_POST['dry_run'] ) && '1' === sanitize_key( wp_unslash( $_POST['dry_run'] ) ),
			'update'    => ! empty( $_POST['update'] ) && '1' === sanitize_key( wp_unslash( $_POST['update'] ) ),
			'set_front' => ! empty( $_POST['set_front'] ) && '1' === sanitize_key( wp_unslash( $_POST['set_front'] ) ),
		);
		// phpcs:enable
		if ( ! Seeder::available_languages() ) {
			Admin::redirect_with_notice( Admin::url( self::SLUG ) . '#mrtu-seed', __( 'No seed content found in the source folder.', 'mr-tu-multilang-seo' ), 'error' );
		}
		$report = Seeder::run( $args );
		$totals = $report['totals'];
		$text   = sprintf(
			/* translators: 1: created, 2: updated, 3: skipped, 4: customized, 5: errors. */
			__( '%1$d created, %2$d updated, %3$d skipped, %4$d customized, %5$d errors.', 'mr-tu-multilang-seo' ),
			$totals['created'],
			$totals['updated'],
			$totals['skipped'],
			$totals['customized'],
			$totals['errors']
		);
		if ( $report['dry_run'] ) {
			/* translators: %s: counts summary. */
			Admin::redirect_with_notice( Admin::url( self::SLUG ) . '#mrtu-seed', sprintf( __( 'Dry run finished (nothing changed): %s', 'mr-tu-multilang-seo' ), $text ), 'info' );
		}
		if ( $report['front']['applied'] ) {
			$text .= ' ' . __( 'Reading settings applied.', 'mr-tu-multilang-seo' );
		}
		if ( $totals['errors'] > 0 ) {
			/* translators: %s: counts summary. */
			Admin::redirect_with_notice( Admin::url( self::SLUG ) . '#mrtu-seed', sprintf( __( 'Seeding finished with errors: %s See the report below.', 'mr-tu-multilang-seo' ), $text ), 'error' );
		}
		/* translators: %s: counts summary. */
		Admin::redirect_with_notice( Admin::url( self::SLUG ) . '#mrtu-seed', sprintf( __( 'Content seeded: %s', 'mr-tu-multilang-seo' ), $text ) );
	}

	/**
	 * Whether any content was ever seeded (used by other screens).
	 *
	 * @return bool
	 */
	public static function is_seeded() {
		return (bool) get_posts(
			array(
				'post_type'        => Translations::post_types(),
				'post_status'      => 'any',
				'meta_key'         => Seeder::META_SEED_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- existence check.
				'fields'           => 'ids',
				'numberposts'      => 1,
				'suppress_filters' => true,
				'mrtu_lang'        => 'all',
			)
		);
	}
}
