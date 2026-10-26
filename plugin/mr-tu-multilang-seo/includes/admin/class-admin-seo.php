<?php
/**
 * "SEO" screen (contract §2.8 / §2.12): mode, sitemap, schema, robots and TOC settings, the redirect map,
 * a live head audit of every language home; plus the "SEO Mr Tu" meta box on posts/pages and the same
 * fields on category/tag forms (term meta).
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang\Admin;

use MrTu\Multilang\Languages;
use MrTu\Multilang\Links;
use MrTu\Multilang\Redirects;
use MrTu\Multilang\Seo;
use MrTu\Multilang\Sitemaps;
use MrTu\Multilang\Toc;
use MrTu\Multilang\Translations;

defined( 'ABSPATH' ) || exit;

/**
 * SEO settings screen, meta box and term fields.
 */
final class Admin_Seo {

	const SLUG = 'mrtu-seo';

	const ACTION          = 'mrtu_save_seo';
	const REDIRECT_ADD    = 'mrtu_redirect_add';
	const REDIRECT_DELETE = 'mrtu_redirect_delete';
	const AUDIT           = 'mrtu_seo_audit';

	/**
	 * Nonce action prefix of the post meta box (suffixed with the post id).
	 */
	const POST_NONCE = 'mrtu_seo_post_';

	/**
	 * Nonce action of the term forms.
	 */
	const TERM_NONCE = 'mrtu_seo_term';

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_filter( 'mrtu_admin_pages', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'save' ) );
		add_action( 'admin_post_' . self::REDIRECT_ADD, array( __CLASS__, 'handle_redirect_add' ) );
		add_action( 'admin_post_' . self::REDIRECT_DELETE, array( __CLASS__, 'handle_redirect_delete' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_screen_hooks' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ), 10, 1 );
		add_action( 'save_post', array( __CLASS__, 'save_post_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
	}

	/**
	 * Registers the screen in the "Mr Tu" menu.
	 *
	 * @param array $pages Pages.
	 * @return array
	 */
	public static function register_page( $pages ) {
		$pages[] = array(
			'slug'       => self::SLUG,
			'title'      => __( 'SEO', 'mr-tu-multilang-seo' ),
			'menu_title' => __( 'SEO', 'mr-tu-multilang-seo' ),
			'capability' => 'manage_options',
			'callback'   => array( __CLASS__, 'render' ),
			'position'   => 50,
		);
		return $pages;
	}

	/**
	 * Term form hooks (need the final taxonomy list).
	 */
	public static function register_screen_hooks() {
		foreach ( Translations::taxonomies() as $taxonomy ) {
			add_action( "{$taxonomy}_add_form_fields", array( __CLASS__, 'term_add_fields' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( __CLASS__, 'term_edit_fields' ), 20, 2 );
			add_action( "created_{$taxonomy}", array( __CLASS__, 'save_term_meta' ) );
			add_action( "edited_{$taxonomy}", array( __CLASS__, 'save_term_meta' ) );
		}
	}

	/**
	 * Snippet preview script on our screens (after the shared admin script).
	 *
	 * @param string $hook Current admin hook suffix.
	 */
	public static function enqueue( $hook ) {
		if ( ! Admin::is_our_screen( $hook ) ) {
			return;
		}
		wp_enqueue_script( 'mrtu-admin-seo', MRTU_ML_URL . 'assets/admin/seo.js', array( 'mrtu-admin' ), MRTU_ML_VERSION, true );
	}

	/*
	 * Settings screen
	 */

	/**
	 * Renders the screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
		}
		Admin::page_open(
			__( 'SEO', 'mr-tu-multilang-seo' ),
			__( 'Titles, descriptions, hreflang, sitemaps and structured data for every language. Nothing unverified is published as a fact.', 'mr-tu-multilang-seo' )
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>';
		self::render_mode_card();
		self::render_sitemap_card();
		self::render_schema_card();
		self::render_robots_card();
		self::render_toc_card();
		Admin::save_bar( self::ACTION );
		echo '</form>';

		self::render_redirects_card();
		self::render_audit_card();
		Admin::page_close();
	}

	/**
	 * Mode card: auto / compat / standalone + what Yoast does right now.
	 */
	private static function render_mode_card() {
		$configured = Seo::configured_mode();
		$effective  = Seo::mode();
		$yoast      = Seo::yoast_active();

		Admin::card_open(
			__( 'Mode', 'mr-tu-multilang-seo' ),
			__( 'Who prints the SEO head: Yoast SEO (compat) or this plugin (standalone).', 'mr-tu-multilang-seo' ),
			'mrtu-seo-mode'
		);

		if ( $yoast ) {
			$badges = array( Admin::status( 'info', __( 'Yoast SEO is active', 'mr-tu-multilang-seo' ) ) );
			if ( 'compat' === $effective ) {
				$badges[] = Admin::status( 'ok', __( 'Yoast prints title, description, canonical, Open Graph, schema and its sitemap. Mr Tu adds hreflang and og:locale:alternate and hands the seeded SEO texts to Yoast wherever Yoast’s own field is empty.', 'mr-tu-multilang-seo' ) );
			} else {
				$badges[] = Admin::status( 'warn', __( 'Yoast’s head output and schema are switched off through Yoast’s own filters; Mr Tu prints everything. Yoast’s XML sitemap cannot be disabled this way: turn it off in Yoast › Settings › APIs › XML sitemaps so only one sitemap is advertised.', 'mr-tu-multilang-seo' ) );
			}
		} else {
			$badges = array(
				Admin::status( 'info', __( 'Yoast SEO is not active', 'mr-tu-multilang-seo' ) ),
				Admin::status( 'ok', __( 'Mr Tu prints the whole head: title, description, robots, canonical, hreflang, Open Graph, Twitter Card, JSON-LD and sitemaps.', 'mr-tu-multilang-seo' ) ),
			);
		}
		echo '<p class="mrtu-actions">' . implode( ' ', $badges ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badges are escaped by Admin::status().

		self::render_radios(
			'seo[seo_mode]',
			$configured,
			array(
				'auto'       => __( 'Automatic: compat while Yoast SEO is active, standalone otherwise (recommended)', 'mr-tu-multilang-seo' ),
				'compat'     => __( 'Compat: let Yoast SEO print the head; Mr Tu only adds the language tags (needs Yoast active)', 'mr-tu-multilang-seo' ),
				'standalone' => __( 'Standalone: Mr Tu prints the whole head even while Yoast SEO is active', 'mr-tu-multilang-seo' ),
			)
		);
		$mode_labels = array(
			'compat'     => __( 'Yoast compatibility', 'mr-tu-multilang-seo' ),
			'standalone' => __( 'Standalone', 'mr-tu-multilang-seo' ),
		);
		/* translators: %s: effective mode name. */
		echo '<p class="mrtu-meta">' . esc_html( sprintf( __( 'Effective mode right now: %s', 'mr-tu-multilang-seo' ), isset( $mode_labels[ $effective ] ) ? $mode_labels[ $effective ] : $effective ) ) . '</p>';
		Admin::card_close();
	}

	/**
	 * Sitemap card: auto / on / off + links.
	 */
	private static function render_sitemap_card() {
		Admin::card_open(
			__( 'XML sitemap', 'mr-tu-multilang-seo' ),
			__( 'One index with a sitemap per language; every address lists its translations. Only published, indexable pages are included.', 'mr-tu-multilang-seo' ),
			'mrtu-seo-sitemap'
		);
		self::render_radios(
			'seo[sitemap]',
			Seo::sitemap_mode(),
			array(
				'auto' => __( 'Automatic: on in standalone mode, off while Yoast prints its own sitemap (recommended)', 'mr-tu-multilang-seo' ),
				'on'   => __( 'Always on', 'mr-tu-multilang-seo' ),
				'off'  => __( 'Always off', 'mr-tu-multilang-seo' ),
			)
		);
		if ( Seo::sitemap_enabled() ) {
			$links = array( '<a href="' . esc_url( Sitemaps::url() ) . '" target="_blank" rel="noopener">' . esc_html( Sitemaps::url() ) . '</a>' );
			foreach ( Languages::codes( true ) as $code ) {
				$links[] = '<a href="' . esc_url( Sitemaps::url( $code ) ) . '" target="_blank" rel="noopener">' . esc_html( strtoupper( $code ) ) . '</a>';
			}
			echo '<p class="mrtu-meta">' . esc_html__( 'Served at:', 'mr-tu-multilang-seo' ) . ' ' . implode( ' · ', $links ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- links escaped above.
			echo '<p class="mrtu-meta">' . esc_html__( 'robots.txt advertises the index; the WordPress core sitemap (/wp-sitemap.xml) is switched off in standalone mode.', 'mr-tu-multilang-seo' ) . '</p>';
		} else {
			echo '<p class="mrtu-meta">' . esc_html( Seo::yoast_active() ? __( 'Off: Yoast SEO serves its sitemap (/sitemap_index.xml) with the language alternates added by Mr Tu.', 'mr-tu-multilang-seo' ) : __( 'Off.', 'mr-tu-multilang-seo' ) ) . '</p>';
		}
		Admin::card_close();
	}

	/**
	 * Schema card: AutoRental (gated by verified NAP) and FAQPage.
	 */
	private static function render_schema_card() {
		Admin::card_open(
			__( 'Structured data (JSON-LD)', 'mr-tu-multilang-seo' ),
			__( 'Organization, WebSite, WebPage and breadcrumbs are always printed in standalone mode. Reviews and ratings are never generated.', 'mr-tu-multilang-seo' ),
			'mrtu-seo-schema'
		);
		$allowed = Seo::local_business_allowed();
		$on      = (bool) Seo::setting( 'local_business_schema', false );
		if ( $allowed ) {
			Admin::toggle(
				'seo[local_business_schema]',
				__( 'Print the AutoRental (local business) node with address, phone and opening hours', 'mr-tu-multilang-seo' ),
				$on,
				__( 'Address, phone and hours are verified in Business data, so the node may be published.', 'mr-tu-multilang-seo' )
			);
		} else {
			echo '<input type="hidden" name="seo[local_business_schema]" value="' . ( $on ? '1' : '0' ) . '">';
			echo '<div class="mrtu-toggle"><input class="mrtu-toggle__input" type="checkbox" role="switch" id="mrtu-seo-local-business" disabled' . checked( $on, true, false ) . '>';
			echo '<label class="mrtu-toggle__label" for="mrtu-seo-local-business">' . esc_html__( 'Print the AutoRental (local business) node with address, phone and opening hours', 'mr-tu-multilang-seo' ) . '</label>';
			echo '<p class="mrtu-field__help">' . Admin::badge( false ) . ' ' . esc_html__( 'Not available: the address, phone number and opening hours must all be marked “verified” on the Business data screen first. Until then no local-business node is printed, whatever this setting says.', 'mr-tu-multilang-seo' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge escaped by Admin::badge().
		}
		Admin::toggle(
			'seo[faq_schema]',
			__( 'Print FAQPage data on the guide/FAQ page', 'mr-tu-multilang-seo' ),
			(bool) Seo::setting( 'faq_schema', true ),
			__( 'Built from the question/answer blocks visible on the page with the “guide-faq” role. Google no longer shows FAQ rich results for businesses; this only describes the page.', 'mr-tu-multilang-seo' )
		);
		Admin::card_close();
	}

	/**
	 * Robots card.
	 */
	private static function render_robots_card() {
		Admin::card_open(
			__( 'Robots', 'mr-tu-multilang-seo' ),
			__( 'Search results, “not found” pages, attachments and empty archives are always noindex. Archives below are thin pages for a small business site.', 'mr-tu-multilang-seo' ),
			'mrtu-seo-robots'
		);
		Admin::toggle( 'seo[noindex_author]', __( 'noindex author archives', 'mr-tu-multilang-seo' ), (bool) Seo::setting( 'noindex_author', true ) );
		Admin::toggle( 'seo[noindex_date]', __( 'noindex date archives', 'mr-tu-multilang-seo' ), (bool) Seo::setting( 'noindex_date', true ) );
		Admin::card_close();
	}

	/**
	 * TOC card (fields from Toc::settings(), saved through Toc::sanitize_settings()).
	 */
	private static function render_toc_card() {
		$toc = Toc::settings();
		Admin::card_open(
			__( 'Table of contents', 'mr-tu-multilang-seo' ),
			__( 'Automatic “On this page” box for long articles; headings get stable anchors.', 'mr-tu-multilang-seo' ),
			'mrtu-seo-toc'
		);
		Admin::toggle( 'toc[enabled]', __( 'Insert the table of contents automatically', 'mr-tu-multilang-seo' ), ! empty( $toc['enabled'] ) );
		Admin::field(
			array(
				'name'  => 'toc[min_headings]',
				'label' => __( 'Minimum number of headings', 'mr-tu-multilang-seo' ),
				'value' => (string) $toc['min_headings'],
				'type'  => 'number',
				'attrs' => array(
					'min'       => '1',
					'max'       => '50',
					'step'      => '1',
					'inputmode' => 'numeric',
				),
			)
		);
		Admin::field(
			array(
				'name'  => 'toc[min_words]',
				'label' => __( 'Minimum number of words', 'mr-tu-multilang-seo' ),
				'value' => (string) $toc['min_words'],
				'type'  => 'number',
				'attrs' => array(
					'min'       => '0',
					'max'       => '100000',
					'step'      => '1',
					'inputmode' => 'numeric',
				),
			)
		);
		echo '<div class="mrtu-field"><label class="mrtu-field__label" for="mrtu-toc-position">' . esc_html__( 'Position', 'mr-tu-multilang-seo' ) . '</label>';
		echo '<select id="mrtu-toc-position" name="toc[position]">';
		$labels = array(
			'after_first_h2'  => __( 'After the first heading', 'mr-tu-multilang-seo' ),
			'before_first_h2' => __( 'Before the first heading', 'mr-tu-multilang-seo' ),
		);
		foreach ( Toc::POSITIONS as $position ) {
			echo '<option value="' . esc_attr( $position ) . '"' . selected( $position, $toc['position'], false ) . '>' . esc_html( isset( $labels[ $position ] ) ? $labels[ $position ] : $position ) . '</option>';
		}
		echo '</select></div>';

		echo '<div class="mrtu-field"><span class="mrtu-field__label">' . esc_html__( 'Content types', 'mr-tu-multilang-seo' ) . '</span>';
		echo '<input type="hidden" name="toc[post_types]" value="">';
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			$id = 'mrtu-toc-type-' . sanitize_html_class( $type->name );
			echo '<label class="mrtu-choice" for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="toc[post_types][]" value="' . esc_attr( $type->name ) . '"' . checked( in_array( $type->name, (array) $toc['post_types'], true ), true, false ) . '> ' . esc_html( $type->labels->name ) . '</label>';
		}
		echo '</div>';
		Admin::card_close();
	}

	/**
	 * Redirect map card: table + add form.
	 */
	private static function render_redirects_card() {
		$map = Redirects::all();
		Admin::card_open(
			__( 'Redirects', 'mr-tu-multilang-seo' ),
			__( 'Old addresses that must keep working. Applied only when the address would otherwise be “not found”. Changing the slug of a published page adds its redirect automatically.', 'mr-tu-multilang-seo' ),
			'mrtu-seo-redirects'
		);
		/* translators: 1: number of redirects, 2: maximum. */
		echo '<p class="mrtu-meta">' . esc_html( sprintf( __( '%1$s of %2$s redirects used.', 'mr-tu-multilang-seo' ), number_format_i18n( count( $map ) ), number_format_i18n( Redirects::MAX ) ) ) . '</p>';

		if ( $map ) {
			uasort(
				$map,
				static function ( $a, $b ) {
					return $b['created'] - $a['created'];
				}
			);
			echo '<div class="mrtu-scroll"><table class="mrtu-table widefat"><thead><tr>';
			echo '<th>' . esc_html__( 'From', 'mr-tu-multilang-seo' ) . '</th><th>' . esc_html__( 'To', 'mr-tu-multilang-seo' ) . '</th><th>' . esc_html__( 'Code', 'mr-tu-multilang-seo' ) . '</th><th>' . esc_html__( 'Hits', 'mr-tu-multilang-seo' ) . '</th><th>' . esc_html__( 'Created', 'mr-tu-multilang-seo' ) . '</th><th></th>';
			echo '</tr></thead><tbody>';
			foreach ( $map as $from => $entry ) {
				echo '<tr><td><code>' . esc_html( $from ) . '</code></td>';
				echo '<td><a href="' . esc_url( $entry['to'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_make_link_relative( $entry['to'] ) ) . '</a></td>';
				echo '<td>' . esc_html( (string) $entry['code'] ) . '</td>';
				echo '<td>' . esc_html( number_format_i18n( $entry['hits'] ) ) . '</td>';
				echo '<td>' . esc_html( $entry['created'] ? wp_date( get_option( 'date_format' ), (int) $entry['created'] ) : '—' ) . '</td>';
				echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( self::REDIRECT_DELETE, '_mrtu_nonce' );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::REDIRECT_DELETE ) . '"><input type="hidden" name="from" value="' . esc_attr( $from ) . '">';
				echo '<button type="submit" class="button-link-delete" data-mrtu-confirm="' . esc_attr__( 'Delete this redirect?', 'mr-tu-multilang-seo' ) . '">' . esc_html__( 'Delete', 'mr-tu-multilang-seo' ) . '</button></form></td></tr>';
			}
			echo '</tbody></table></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>';
		Admin::field(
			array(
				'name'        => 'from',
				'label'       => __( 'Old address (path)', 'mr-tu-multilang-seo' ),
				'placeholder' => '/en/old-page/',
				'attrs'       => array(
					'autocapitalize' => 'off',
					'autocomplete'   => 'off',
					'spellcheck'     => 'false',
				),
			)
		);
		Admin::field(
			array(
				'name'        => 'to',
				'label'       => __( 'New address (path or link on this website)', 'mr-tu-multilang-seo' ),
				'placeholder' => '/en/new-page/',
				'attrs'       => array(
					'autocapitalize' => 'off',
					'autocomplete'   => 'off',
					'spellcheck'     => 'false',
				),
			)
		);
		self::render_radios(
			'code',
			'301',
			array(
				'301' => __( '301 permanent (search engines move the ranking)', 'mr-tu-multilang-seo' ),
				'302' => __( '302 temporary', 'mr-tu-multilang-seo' ),
			)
		);
		Admin::save_bar( self::REDIRECT_ADD, __( 'Add redirect', 'mr-tu-multilang-seo' ) );
		echo '</form>';
		Admin::card_close();
	}

	/**
	 * Live head audit card (loopback request to every language home).
	 */
	private static function render_audit_card() {
		Admin::card_open(
			__( 'Live head audit', 'mr-tu-multilang-seo' ),
			__( 'Fetches every language home page like a visitor and counts the SEO tags. Exactly one canonical, one description and one robots tag is correct; hreflang needs one line per language plus x-default.', 'mr-tu-multilang-seo' ),
			'mrtu-seo-audit'
		);
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only trigger, nonce verified right here.
		$run = isset( $_GET['mrtu_audit'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::AUDIT );
		// phpcs:enable
		if ( $run ) {
			self::render_audit_results( self::audit() );
		}
		$url = wp_nonce_url( Admin::url( self::SLUG, array( 'mrtu_audit' => '1' ) ), self::AUDIT );
		echo '<div class="mrtu-savebar"><a class="button button-primary mrtu-button" href="' . esc_url( $url . '#mrtu-seo-audit' ) . '">' . esc_html( $run ? __( 'Run again', 'mr-tu-multilang-seo' ) : __( 'Run audit', 'mr-tu-multilang-seo' ) ) . '</a></div>';
		Admin::card_close();
	}

	/**
	 * Prints the audit table.
	 *
	 * @param array[] $rows Rows from audit().
	 */
	private static function render_audit_results( array $rows ) {
		echo '<div class="mrtu-scroll"><table class="mrtu-table widefat"><thead><tr>';
		echo '<th>' . esc_html__( 'Language', 'mr-tu-multilang-seo' ) . '</th><th>' . esc_html__( 'Address', 'mr-tu-multilang-seo' ) . '</th><th>HTTP</th><th>canonical</th><th>description</th><th>robots</th><th>hreflang</th><th>' . esc_html__( 'Result', 'mr-tu-multilang-seo' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( strtoupper( $row['lang'] ) ) . '</td>';
			echo '<td><a href="' . esc_url( $row['url'] ) . '" target="_blank" rel="noopener">' . esc_html( wp_make_link_relative( $row['url'] ) ) . '</a></td>';
			if ( '' !== $row['error'] ) {
				echo '<td>—</td><td colspan="4">' . esc_html( $row['error'] ) . '</td><td>' . Admin::status( 'warn', __( 'Could not fetch', 'mr-tu-multilang-seo' ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge escaped by Admin::status().
				continue;
			}
			echo '<td>' . esc_html( (string) $row['status'] ) . '</td>';
			foreach ( array( 'canonical', 'description', 'robots', 'hreflang' ) as $key ) {
				echo '<td>' . esc_html( (string) $row[ $key ] ) . '</td>';
			}
			echo '<td>' . Admin::status( $row['ok'] ? 'ok' : 'error', $row['ok'] ? __( 'OK', 'mr-tu-multilang-seo' ) : __( 'Check', 'mr-tu-multilang-seo' ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge escaped by Admin::status().
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Fetches every enabled language home and counts the head tags.
	 *
	 * @return array[] [ lang, url, status, canonical, description, robots, hreflang, ok, error ]
	 */
	public static function audit() {
		$rows = array();
		foreach ( Languages::codes( true ) as $code ) {
			$url      = Links::language_home( $code );
			$row      = array(
				'lang'        => $code,
				'url'         => $url,
				'status'      => 0,
				'canonical'   => 0,
				'description' => 0,
				'robots'      => 0,
				'hreflang'    => 0,
				'ok'          => false,
				'error'       => '',
			);
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => 10,
					'redirection' => 2,
					'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
					'user-agent'  => 'MrTu-Head-Audit/' . MRTU_ML_VERSION,
				)
			);
			if ( is_wp_error( $response ) ) {
				/* translators: %s: error message. */
				$row['error'] = sprintf( __( 'This server could not call itself (%s). Some hosts block loopback requests; open the address in a browser and view the page source instead.', 'mr-tu-multilang-seo' ), $response->get_error_message() );
				$rows[]       = $row;
				continue;
			}
			$row['status'] = (int) wp_remote_retrieve_response_code( $response );
			$body          = (string) wp_remote_retrieve_body( $response );
			$head          = preg_match( '#<head\b[^>]*>(.*?)</head>#is', $body, $m ) ? $m[1] : $body;
			$counts        = self::count_head_tags( $head );
			$row           = array_merge( $row, $counts );
			$expected      = count( Languages::codes( true ) ) + ( '' !== (string) Languages::setting( 'x_default', '' ) ? 1 : 0 );
			$row['ok']     = 200 === $row['status'] && 1 === $counts['canonical'] && 1 === $counts['description'] && 1 === $counts['robots'] && ( 0 === $counts['hreflang'] || $counts['hreflang'] === $expected );
			$rows[]        = $row;
		}
		return $rows;
	}

	/**
	 * Counts canonical / description / robots / hreflang tags in HTML.
	 *
	 * @param string $html Head HTML.
	 * @return array
	 */
	public static function count_head_tags( $html ) {
		return array(
			'canonical'   => preg_match_all( '#<link\b[^>]*\brel=["\']canonical["\']#i', $html ),
			'description' => preg_match_all( '#<meta\b[^>]*\bname=["\']description["\']#i', $html ),
			'robots'      => preg_match_all( '#<meta\b[^>]*\bname=["\']robots["\']#i', $html ),
			'hreflang'    => preg_match_all( '#<link\b[^>]*\bhreflang=["\']#i', $html ),
		);
	}

	/**
	 * Prints a radio list in the shared field markup.
	 *
	 * @param string $name    Field name.
	 * @param string $current Current value.
	 * @param array  $options value => label.
	 */
	private static function render_radios( $name, $current, array $options ) {
		echo '<div class="mrtu-field">';
		foreach ( $options as $value => $label ) {
			$id = 'mrtu-' . sanitize_html_class( str_replace( array( '[', ']' ), '-', $name ) . '-' . $value );
			echo '<label class="mrtu-choice" for="' . esc_attr( $id ) . '"><input type="radio" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . checked( (string) $value, (string) $current, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>';
	}

	/**
	 * Saves the settings form (admin-post).
	 */
	public static function save() {
		Admin::verify_request( self::ACTION, 'manage_options' );
		// Nonce verified by Admin::verify_request() above; every value is sanitized by Seo/Toc::sanitize_settings().
		$seo = isset( $_POST['seo'] ) && is_array( $_POST['seo'] ) ? wp_unslash( $_POST['seo'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
		$toc = isset( $_POST['toc'] ) && is_array( $_POST['toc'] ) ? wp_unslash( $_POST['toc'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing

		$settings        = Seo::sanitize_settings( $seo );
		$settings['toc'] = Toc::sanitize_settings( $toc );
		Languages::update_settings( $settings );
		Sitemaps::flush();

		Admin::redirect_with_notice( Admin::url( self::SLUG ), __( 'SEO settings saved.', 'mr-tu-multilang-seo' ) );
	}

	/**
	 * Adds a redirect (admin-post).
	 */
	public static function handle_redirect_add() {
		Admin::verify_request( self::REDIRECT_ADD, 'manage_options' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by Admin::verify_request() above.
		$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : '';
		$to   = isset( $_POST['to'] ) ? sanitize_text_field( wp_unslash( $_POST['to'] ) ) : '';
		$code = isset( $_POST['code'] ) ? (int) $_POST['code'] : 301;
		// phpcs:enable
		$result = Redirects::add( $from, $to, $code );
		$url    = Admin::url( self::SLUG ) . '#mrtu-seo-redirects';
		if ( is_wp_error( $result ) ) {
			Admin::redirect_with_notice( $url, $result->get_error_message(), 'error' );
		}
		Admin::redirect_with_notice( $url, __( 'Redirect added.', 'mr-tu-multilang-seo' ) );
	}

	/**
	 * Deletes a redirect (admin-post).
	 */
	public static function handle_redirect_delete() {
		Admin::verify_request( self::REDIRECT_DELETE, 'manage_options' );
		$from = isset( $_POST['from'] ) ? sanitize_text_field( wp_unslash( $_POST['from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by Admin::verify_request() above.
		$url  = Admin::url( self::SLUG ) . '#mrtu-seo-redirects';
		if ( Redirects::remove( $from ) ) {
			Admin::redirect_with_notice( $url, __( 'Redirect deleted.', 'mr-tu-multilang-seo' ) );
		}
		Admin::redirect_with_notice( $url, __( 'That redirect no longer exists.', 'mr-tu-multilang-seo' ), 'warning' );
	}

	/*
	 * Post meta box
	 */

	/**
	 * Registers the "SEO Mr Tu" box on translatable post types.
	 *
	 * @param string $post_type Post type.
	 */
	public static function add_meta_box( $post_type ) {
		if ( ! Translations::is_translatable_post_type( $post_type ) ) {
			return;
		}
		add_meta_box( 'mrtu-seo', __( 'SEO Mr Tu', 'mr-tu-multilang-seo' ), array( __CLASS__, 'render_meta_box' ), $post_type, 'normal', 'default' );
	}

	/**
	 * Prints the meta box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		wp_nonce_field( self::POST_NONCE . $post->ID, '_mrtu_seo_nonce' );
		echo '<div class="mrtu-admin mrtu-metabox">';
		self::render_seo_fields(
			array(
				'title'   => (string) get_post_meta( $post->ID, Seo::META_TITLE, true ),
				'desc'    => (string) get_post_meta( $post->ID, Seo::META_DESC, true ),
				'kw'      => (string) get_post_meta( $post->ID, Seo::META_KW, true ),
				'noindex' => '1' === (string) get_post_meta( $post->ID, Seo::META_NOINDEX, true ),
			),
			array(
				'title' => Seo::title( $post ),
				'desc'  => Seo::description( $post ),
				'url'   => 'publish' === $post->post_status ? (string) get_permalink( $post ) : (string) get_sample_permalink( $post->ID )[0],
			)
		);
		echo '</div>';
	}

	/**
	 * Shared fields (post meta box and term forms).
	 *
	 * @param array $values  [ title, desc, kw, noindex ] stored values.
	 * @param array $preview [ title, desc, url ] resolved fallbacks for the snippet preview.
	 */
	private static function render_seo_fields( array $values, array $preview ) {
		echo '<div class="mrtu-snippet" data-mrtu-snippet>';
		echo '<p class="mrtu-snippet__url mrtu-meta">' . esc_html( str_replace( '%pagename%', '…', $preview['url'] ) ) . '</p>';
		echo '<p class="mrtu-snippet__title" data-mrtu-snippet-title data-mrtu-fallback="' . esc_attr( $preview['title'] ) . '">' . esc_html( '' !== $values['title'] ? Seo::brand( $values['title'] ) : $preview['title'] ) . '</p>';
		echo '<p class="mrtu-snippet__desc" data-mrtu-snippet-desc data-mrtu-fallback="' . esc_attr( $preview['desc'] ) . '">' . esc_html( '' !== $values['desc'] ? $values['desc'] : $preview['desc'] ) . '</p>';
		echo '<p class="mrtu-field__help">' . esc_html__( 'Preview of the search result. Empty fields fall back to the title / excerpt shown here.', 'mr-tu-multilang-seo' ) . '</p>';
		echo '</div>';
		Admin::field(
			array(
				'name'  => 'mrtu_seo[title]',
				'label' => __( 'SEO title', 'mr-tu-multilang-seo' ),
				'value' => $values['title'],
				/* translators: %s: site name appended to the title. */
				'help'  => sprintf( __( 'Up to 60 characters; “| %s” is added automatically.', 'mr-tu-multilang-seo' ), Seo::site_name() ),
				'attrs' => array(
					'maxlength'        => '200',
					'data-mrtu-count'  => (string) Seo::TITLE_LENGTH,
					'data-mrtu-brand'  => Seo::SEPARATOR . Seo::site_name(),
					'data-mrtu-source' => 'title',
				),
			)
		);
		Admin::field(
			array(
				'name'  => 'mrtu_seo[desc]',
				'label' => __( 'Meta description', 'mr-tu-multilang-seo' ),
				'value' => $values['desc'],
				'type'  => 'textarea',
				'help'  => __( 'One or two plain sentences, up to 158 characters. No phone numbers or prices that are not verified.', 'mr-tu-multilang-seo' ),
				'attrs' => array(
					'maxlength'        => '400',
					'data-mrtu-count'  => (string) Seo::DESC_LENGTH,
					'data-mrtu-source' => 'desc',
				),
			)
		);
		Admin::field(
			array(
				'name'  => 'mrtu_seo[kw]',
				'label' => __( 'Focus keyword', 'mr-tu-multilang-seo' ),
				'value' => $values['kw'],
				'help'  => __( 'The one search phrase this page is written for (editorial note; not printed).', 'mr-tu-multilang-seo' ),
				'attrs' => array( 'maxlength' => '200' ),
			)
		);
		Admin::toggle(
			'mrtu_seo[noindex]',
			__( 'Hide from search engines (noindex)', 'mr-tu-multilang-seo' ),
			$values['noindex'],
			__( 'Also removes the page from the sitemap and from hreflang links of its translations.', 'mr-tu-multilang-seo' )
		);
	}

	/**
	 * Saves the meta box (`save_post`).
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 */
	public static function save_post_meta( $post_id, $post ) {
		if ( ! isset( $_POST['_mrtu_seo_nonce'], $_POST['mrtu_seo'] ) || ! is_array( $_POST['mrtu_seo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on the next line.
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_mrtu_seo_nonce'] ) ), self::POST_NONCE . $post_id ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! $post instanceof \WP_Post || ! Translations::is_translatable_post_type( $post->post_type ) ) {
			return;
		}
		$input = wp_unslash( $_POST['mrtu_seo'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field in store_values().
		self::store_values( 'post', $post_id, (array) $input );
	}

	/**
	 * Sanitizes and stores the four fields on a post or term (empty = delete).
	 *
	 * @param string $kind  'post' | 'term'.
	 * @param int    $id    Object id.
	 * @param array  $input Raw [ title, desc, kw, noindex ].
	 */
	private static function store_values( $kind, $id, array $input ) {
		$values = array(
			Seo::META_TITLE   => Seo::sanitize_title_text( isset( $input['title'] ) ? $input['title'] : '' ),
			Seo::META_DESC    => Seo::sanitize_description_text( isset( $input['desc'] ) ? $input['desc'] : '' ),
			Seo::META_KW      => Seo::sanitize_title_text( isset( $input['kw'] ) ? $input['kw'] : '' ),
			Seo::META_NOINDEX => Seo::sanitize_noindex( isset( $input['noindex'] ) ? $input['noindex'] : '' ),
		);
		foreach ( $values as $key => $value ) {
			if ( '' === $value ) {
				call_user_func( 'delete_' . $kind . '_meta', $id, $key );
			} else {
				call_user_func( 'update_' . $kind . '_meta', $id, $key, $value );
			}
		}
		if ( 'post' === $kind ) {
			Sitemaps::flush();
		}
	}

	/*
	 * Term forms
	 */

	/**
	 * Fields on the "add term" form.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public static function term_add_fields( $taxonomy ) {
		wp_nonce_field( self::TERM_NONCE, '_mrtu_seo_term_nonce' );
		echo '<div class="form-field mrtu-admin mrtu-metabox"><h3>' . esc_html__( 'SEO Mr Tu', 'mr-tu-multilang-seo' ) . '</h3>';
		self::render_seo_fields(
			array(
				'title'   => '',
				'desc'    => '',
				'kw'      => '',
				'noindex' => false,
			),
			array(
				'title' => Seo::brand( '' ),
				'desc'  => '',
				'url'   => home_url( '/' ),
			)
		);
		echo '</div>';
		unset( $taxonomy );
	}

	/**
	 * Fields on the "edit term" form.
	 *
	 * @param \WP_Term $term     Term.
	 * @param string   $taxonomy Taxonomy.
	 */
	public static function term_edit_fields( $term, $taxonomy ) {
		if ( ! $term instanceof \WP_Term ) {
			return;
		}
		unset( $taxonomy );
		$link = get_term_link( $term );
		echo '<tr class="form-field"><th scope="row">' . esc_html__( 'SEO Mr Tu', 'mr-tu-multilang-seo' ) . '</th><td><div class="mrtu-admin mrtu-metabox">';
		wp_nonce_field( self::TERM_NONCE, '_mrtu_seo_term_nonce' );
		self::render_seo_fields(
			array(
				'title'   => (string) get_term_meta( $term->term_id, Seo::META_TITLE, true ),
				'desc'    => (string) get_term_meta( $term->term_id, Seo::META_DESC, true ),
				'kw'      => (string) get_term_meta( $term->term_id, Seo::META_KW, true ),
				'noindex' => '1' === (string) get_term_meta( $term->term_id, Seo::META_NOINDEX, true ),
			),
			array(
				'title' => Seo::title( $term ),
				'desc'  => Seo::description( $term ),
				'url'   => is_wp_error( $link ) ? home_url( '/' ) : (string) $link,
			)
		);
		echo '</div></td></tr>';
	}

	/**
	 * Saves the term fields (`created_{taxonomy}` / `edited_{taxonomy}`).
	 *
	 * @param int $term_id Term id.
	 */
	public static function save_term_meta( $term_id ) {
		if ( ! isset( $_POST['_mrtu_seo_term_nonce'], $_POST['mrtu_seo'] ) || ! is_array( $_POST['mrtu_seo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on the next line.
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_mrtu_seo_term_nonce'] ) ), self::TERM_NONCE ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		$input = wp_unslash( $_POST['mrtu_seo'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field in store_values().
		self::store_values( 'term', (int) $term_id, (array) $input );
		Sitemaps::flush();
	}
}
