<?php
/**
 * Health Check (contract §2.12 "Kiểm tra sức khỏe"): a list of checks over translations, roles, SEO
 * metadata, internal links, images, business data, Quick Contact, SEO mode, front pages, languages,
 * the sitemap and the live `<head>` of every language home.
 *
 * Each check is `[ id, severity (ok|warning|error), label, detail, link ]`. Results are cached in a
 * transient for ten minutes; the admin screen offers "Run again" and three maintenance actions
 * (rebuild translation maps, flush rewrite rules, flush the sitemap cache).
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Health checks.
 */
final class Health {

	const TRANSIENT = 'mrtu_health_report';

	const TTL = 600;

	const MAX_POSTS = 500;

	const MAX_EXAMPLES = 8;

	const TITLE_MAX = 60;

	const DESC_MIN = 120;

	const DESC_MAX = 160;

	/**
	 * Wires hooks: anything that changes content, settings or business data invalidates the cache.
	 */
	public static function init() {
		$hooks = array(
			'save_post',
			'deleted_post',
			'trashed_post',
			'untrashed_post',
			'created_term',
			'edited_term',
			'delete_term',
			'switch_theme',
			'activated_plugin',
			'deactivated_plugin',
			'mrtu_business_updated',
			'mrtu_pricing_updated',
			'mrtu_translations_linked',
			'mrtu_seeded',
			'update_option_' . Languages::OPTION,
			'update_option_' . Roles::OPTION,
			'update_option_mrtu_quick_contact',
			'update_option_show_on_front',
			'update_option_page_on_front',
			'update_option_page_for_posts',
		);
		foreach ( $hooks as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush' ) );
		}
	}

	/**
	 * Deactivation: drop the cached report.
	 */
	public static function deactivate() {
		self::flush();
	}

	/**
	 * Clears the cached report.
	 */
	public static function flush() {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * Cached report (runs the checks when nothing is cached).
	 *
	 * @param array $args fresh (bool, ignore the cache), loopback (bool, default true).
	 * @return array [ 'time' => int, 'checks' => array[], 'summary' => [ ok, warning, error ], 'loopback' => bool ]
	 */
	public static function get( array $args = array() ) {
		$fresh = ! empty( $args['fresh'] );
		if ( ! $fresh ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) && isset( $cached['checks'], $cached['time'] ) ) {
				return $cached;
			}
		}
		$loopback = ! array_key_exists( 'loopback', $args ) || ! empty( $args['loopback'] );
		$checks   = self::run( array( 'loopback' => $loopback ) );
		$report   = array(
			'time'     => time(),
			'checks'   => $checks,
			'summary'  => self::summary( $checks ),
			'loopback' => $loopback,
		);
		set_transient( self::TRANSIENT, $report, self::TTL );
		return $report;
	}

	/**
	 * Cached report without computing: null when nothing is cached.
	 *
	 * @return array|null
	 */
	public static function cached() {
		$cached = get_transient( self::TRANSIENT );
		return is_array( $cached ) && isset( $cached['checks'], $cached['time'] ) ? $cached : null;
	}

	/**
	 * Counts checks per severity.
	 *
	 * @param array[] $checks Checks.
	 * @return array [ 'ok' => int, 'warning' => int, 'error' => int ]
	 */
	public static function summary( array $checks ) {
		$out = array(
			'ok'      => 0,
			'warning' => 0,
			'error'   => 0,
		);
		foreach ( $checks as $check ) {
			$severity = isset( $check['severity'] ) && isset( $out[ $check['severity'] ] ) ? $check['severity'] : 'warning';
			++$out[ $severity ];
		}
		return $out;
	}

	/**
	 * Runs every check (uncached).
	 *
	 * @param array $args loopback (bool, default true): fetch the sitemap and language homes over HTTP.
	 * @return array[] Checks [ id, severity, label, detail, link ].
	 */
	public static function run( array $args = array() ) {
		$loopback = ! array_key_exists( 'loopback', $args ) || ! empty( $args['loopback'] );
		$content  = self::collect_content();

		$checks   = array();
		$checks[] = self::check_translations();
		$checks[] = self::check_pages_without_role( $content );
		$checks[] = self::check_duplicate_meta( $content, 'title' );
		$checks[] = self::check_duplicate_meta( $content, 'desc' );
		$checks[] = self::check_title_length( $content );
		$checks[] = self::check_desc_length( $content );
		$checks[] = self::check_focus_keywords( $content );
		$links    = self::scan_links( $content );
		$checks[] = self::check_orphans( $content, $links );
		$checks[] = self::check_broken_links( $links );
		$checks[] = self::check_images( $content );
		$checks[] = self::check_business();
		$checks[] = self::check_quick_contact();
		$checks[] = self::check_seo_mode();
		$checks[] = self::check_front_pages();
		$checks[] = self::check_disabled_languages( $content );
		$checks[] = self::check_sitemap( $loopback );
		$checks[] = self::check_head( $loopback );

		/**
		 * Filters the health checks (other modules may append their own).
		 *
		 * @param array[] $checks Checks [ id, severity, label, detail, link ].
		 */
		$checks = (array) apply_filters( 'mrtu_health_checks', $checks );

		$order = array(
			'error'   => 0,
			'warning' => 1,
			'ok'      => 2,
		);
		usort(
			$checks,
			static function ( $a, $b ) use ( $order ) {
				$sa = isset( $order[ $a['severity'] ] ) ? $order[ $a['severity'] ] : 1;
				$sb = isset( $order[ $b['severity'] ] ) ? $order[ $b['severity'] ] : 1;
				return $sa - $sb;
			}
		);
		return array_values( $checks );
	}

	/**
	 * Builds one check.
	 *
	 * @param string $id       Check id.
	 * @param string $severity ok|warning|error.
	 * @param string $label    Label.
	 * @param string $detail   Detail.
	 * @param string $link     Admin link.
	 * @return array
	 */
	private static function check( $id, $severity, $label, $detail = '', $link = '' ) {
		return array(
			'id'       => $id,
			'severity' => in_array( $severity, array( 'ok', 'warning', 'error' ), true ) ? $severity : 'warning',
			'label'    => $label,
			'detail'   => $detail,
			'link'     => $link,
		);
	}

	/**
	 * Admin URL of one of our screens.
	 *
	 * @param string $slug Screen slug.
	 * @return string
	 */
	private static function screen( $slug ) {
		return admin_url( 'admin.php?page=' . $slug );
	}

	/**
	 * Joins example strings with an ellipsis when there are more.
	 *
	 * @param string[] $items Items.
	 * @return string
	 */
	private static function examples( array $items ) {
		$count = count( $items );
		$items = array_slice( $items, 0, self::MAX_EXAMPLES );
		$text  = implode( '; ', $items );
		if ( $count > self::MAX_EXAMPLES ) {
			/* translators: %d: number of further items. */
			$text .= ' ' . sprintf( __( '… and %d more', 'mr-tu-multilang-seo' ), $count - self::MAX_EXAMPLES );
		}
		return $text;
	}

	/**
	 * "Title (LANG, #id)" label of a content item.
	 *
	 * @param array $item Content item.
	 * @return string
	 */
	private static function name( array $item ) {
		return sprintf( '%1$s (%2$s #%3$d)', $item['title'], strtoupper( $item['lang'] ), $item['id'] );
	}

	/*
	 * Content snapshot
	 * ------------------------------------------------------------------
	 */

	/**
	 * Published translatable content with the data every check needs.
	 *
	 * @return array[] id => [ id, type, lang, title, seo_title, seo_desc, focus_kw, content, role, parent, url ]
	 */
	public static function collect_content() {
		$query = new \WP_Query(
			array(
				'post_type'           => Translations::post_types(),
				'post_status'         => 'publish',
				'posts_per_page'      => self::MAX_POSTS,
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'suppress_filters'    => true,
				Router::QUERY_VAR     => 'all',
			)
		);
		$posts = $query->posts;
		if ( ! $posts ) {
			return array();
		}
		Translations::prime( wp_list_pluck( $posts, 'ID' ) );

		$out = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$seo_title              = (string) get_post_meta( $post->ID, Seeder::META_SEO_TITLE, true );
			$out[ (int) $post->ID ] = array(
				'id'        => (int) $post->ID,
				'type'      => $post->post_type,
				'lang'      => Translations::get_post_language( $post ),
				'title'     => (string) $post->post_title,
				'seo_title' => '' !== $seo_title ? $seo_title : (string) $post->post_title,
				'seo_desc'  => (string) get_post_meta( $post->ID, Seeder::META_SEO_DESC, true ),
				'focus_kw'  => mb_strtolower( trim( (string) get_post_meta( $post->ID, Seeder::META_FOCUS_KW, true ) ) ),
				'content'   => (string) $post->post_content,
				'role'      => Roles::get_post_role( $post->ID ),
				'parent'    => (int) $post->post_parent,
				'url'       => (string) get_permalink( $post ),
			);
		}
		return $out;
	}

	/*
	 * Translations
	 * ------------------------------------------------------------------
	 */

	/**
	 * Role × language matrix.
	 *
	 * @return array {
	 *     @type array    $matrix    role => [ lang => [ 'status' => published|draft|missing, 'id' => int ] ].
	 *     @type string[] $languages Enabled language codes.
	 *     @type int      $published Published cells.
	 *     @type int      $total     Cells.
	 *     @type int      $percent   Completeness percentage.
	 * }
	 */
	public static function completeness() {
		$languages = Languages::codes( true );
		$map       = Roles::map();
		$matrix    = array();
		$published = 0;
		$total     = 0;
		foreach ( Roles::registry() as $role => $info ) {
			$members = isset( $map[ $role ] ) ? Translations::group_map( (int) $map[ $role ] ) : array();
			foreach ( $languages as $code ) {
				$id     = isset( $members[ $code ] ) ? (int) $members[ $code ] : 0;
				$status = 'missing';
				if ( $id && get_post( $id ) ) {
					$status = 'publish' === get_post_status( $id ) ? 'published' : 'draft';
				} else {
					$id = 0;
				}
				$matrix[ $role ][ $code ] = array(
					'status' => $status,
					'id'     => $id,
				);
				++$total;
				if ( 'published' === $status ) {
					++$published;
				}
			}
		}
		return array(
			'matrix'    => $matrix,
			'languages' => $languages,
			'published' => $published,
			'total'     => $total,
			'percent'   => $total ? (int) round( 100 * $published / $total ) : 0,
		);
	}

	/**
	 * Translation completeness per role × language.
	 *
	 * @return array
	 */
	private static function check_translations() {
		$data     = self::completeness();
		$problems = array();
		$severity = 'ok';
		$roles    = Roles::registry();
		foreach ( $data['matrix'] as $role => $row ) {
			$missing = array();
			$drafts  = array();
			foreach ( $row as $code => $cell ) {
				if ( 'missing' === $cell['status'] ) {
					$missing[] = strtoupper( $code );
				} elseif ( 'draft' === $cell['status'] ) {
					$drafts[] = strtoupper( $code );
				}
			}
			if ( ! $missing && ! $drafts ) {
				continue;
			}
			$severity = 'home' === $role && 'page' === get_option( 'show_on_front' ) ? 'error' : ( 'error' === $severity ? 'error' : 'warning' );
			$label    = isset( $roles[ $role ]['label'] ) ? $roles[ $role ]['label'] : $role;
			$parts    = array();
			if ( $missing ) {
				/* translators: %s: comma-separated language codes. */
				$parts[] = sprintf( __( 'missing: %s', 'mr-tu-multilang-seo' ), implode( ', ', $missing ) );
			}
			if ( $drafts ) {
				/* translators: %s: comma-separated language codes. */
				$parts[] = sprintf( __( 'not published: %s', 'mr-tu-multilang-seo' ), implode( ', ', $drafts ) );
			}
			$problems[] = $label . ' — ' . implode( ', ', $parts );
		}
		$detail = sprintf(
			/* translators: 1: percentage, 2: published count, 3: total count. */
			__( '%1$d%% complete (%2$d of %3$d language versions published).', 'mr-tu-multilang-seo' ),
			$data['percent'],
			$data['published'],
			$data['total']
		);
		if ( $problems ) {
			$detail .= ' ' . self::examples( $problems );
		}
		return self::check( 'translations', $severity, __( 'Translation completeness (core pages × languages)', 'mr-tu-multilang-seo' ), $detail, self::screen( 'mrtu-translations' ) );
	}

	/**
	 * Top-level published pages without a page role (not reachable from the generated menus).
	 *
	 * @param array[] $content Content snapshot.
	 * @return array
	 */
	private static function check_pages_without_role( array $content ) {
		$front = 'page' === get_option( 'show_on_front' ) ? Router::raw_option( 'page_on_front' ) : 0;
		$blog  = 'page' === get_option( 'show_on_front' ) ? Router::raw_option( 'page_for_posts' ) : 0;
		$list  = array();
		foreach ( $content as $item ) {
			if ( 'page' !== $item['type'] || '' !== $item['role'] || $item['parent'] ) {
				continue;
			}
			if ( ( $front && Translations::in_same_group( $item['id'], $front ) ) || ( $blog && Translations::in_same_group( $item['id'], $blog ) ) ) {
				continue;
			}
			$list[] = self::name( $item );
		}
		if ( ! $list ) {
			return self::check( 'pages_without_role', 'ok', __( 'Pages without a role', 'mr-tu-multilang-seo' ), __( 'Every top-level page has a role, so menus and footers can place it.', 'mr-tu-multilang-seo' ), self::screen( 'mrtu-translations' ) );
		}
		return self::check(
			'pages_without_role',
			'warning',
			__( 'Pages without a role', 'mr-tu-multilang-seo' ),
			/* translators: 1: number of pages, 2: examples. */
			sprintf( _n( '%1$d top-level page has no role and does not appear in the generated menus: %2$s', '%1$d top-level pages have no role and do not appear in the generated menus: %2$s', count( $list ), 'mr-tu-multilang-seo' ), count( $list ), self::examples( $list ) ),
			self::screen( 'mrtu-translations' )
		);
	}

	/*
	 * SEO metadata
	 * ------------------------------------------------------------------
	 */

	/**
	 * Duplicate SEO titles / descriptions within one language.
	 *
	 * @param array[] $content Content snapshot.
	 * @param string  $field   title|desc.
	 * @return array
	 */
	private static function check_duplicate_meta( array $content, $field ) {
		$key    = 'title' === $field ? 'seo_title' : 'seo_desc';
		$groups = array();
		foreach ( $content as $item ) {
			$value = mb_strtolower( trim( $item[ $key ] ) );
			if ( '' === $value ) {
				continue;
			}
			$groups[ $item['lang'] ][ $value ][] = $item;
		}
		$list = array();
		foreach ( $groups as $lang => $values ) {
			foreach ( $values as $items ) {
				if ( count( $items ) < 2 ) {
					continue;
				}
				$ids    = array_map( 'intval', wp_list_pluck( $items, 'id' ) );
				$list[] = sprintf( '%1$s: “%2$s” ×%3$d (#%4$s)', strtoupper( $lang ), mb_substr( $items[0][ $key ], 0, 50 ), count( $items ), implode( ', #', $ids ) );
			}
		}
		$id    = 'title' === $field ? 'seo_duplicate_titles' : 'seo_duplicate_descriptions';
		$label = 'title' === $field ? __( 'Duplicate SEO titles within a language', 'mr-tu-multilang-seo' ) : __( 'Duplicate meta descriptions within a language', 'mr-tu-multilang-seo' );
		if ( ! $list ) {
			return self::check( $id, 'ok', $label, __( 'No duplicates.', 'mr-tu-multilang-seo' ), self::screen( 'mrtu-seo' ) );
		}
		return self::check( $id, 'warning', $label, self::examples( $list ), self::screen( 'mrtu-seo' ) );
	}

	/**
	 * SEO titles longer than 60 characters.
	 *
	 * @param array[] $content Content snapshot.
	 * @return array
	 */
	private static function check_title_length( array $content ) {
		$list = array();
		foreach ( $content as $item ) {
			$len = mb_strlen( $item['seo_title'] );
			if ( $len > self::TITLE_MAX ) {
				$list[] = sprintf( '%1$s — %2$d', self::name( $item ), $len );
			}
		}
		$label = __( 'SEO title length (≤ 60 characters)', 'mr-tu-multilang-seo' );
		if ( ! $list ) {
			return self::check( 'seo_title_length', 'ok', $label, __( 'Every title fits.', 'mr-tu-multilang-seo' ), self::screen( 'mrtu-seo' ) );
		}
		/* translators: 1: number of pages, 2: examples with character counts. */
		return self::check( 'seo_title_length', 'warning', $label, sprintf( _n( '%1$d title is longer than 60 characters: %2$s', '%1$d titles are longer than 60 characters: %2$s', count( $list ), 'mr-tu-multilang-seo' ), count( $list ), self::examples( $list ) ), self::screen( 'mrtu-seo' ) );
	}

	/**
	 * Meta descriptions outside 120–160 characters (and missing ones).
	 *
	 * @param array[] $content Content snapshot.
	 * @return array
	 */
	private static function check_desc_length( array $content ) {
		$list    = array();
		$missing = 0;
		foreach ( $content as $item ) {
			$len = mb_strlen( trim( $item['seo_desc'] ) );
			if ( 0 === $len ) {
				++$missing;
				continue;
			}
			if ( $len < self::DESC_MIN || $len > self::DESC_MAX ) {
				$list[] = sprintf( '%1$s — %2$d', self::name( $item ), $len );
			}
		}
		$label = __( 'Meta description length (120–160 characters)', 'mr-tu-multilang-seo' );
		$parts = array();
		if ( $list ) {
			/* translators: 1: number of pages, 2: examples with character counts. */
			$parts[] = sprintf( _n( '%1$d description is outside 120–160 characters: %2$s', '%1$d descriptions are outside 120–160 characters: %2$s', count( $list ), 'mr-tu-multilang-seo' ), count( $list ), self::examples( $list ) );
		}
		if ( $missing ) {
			/* translators: %d: number of pages. */
			$parts[] = sprintf( _n( '%d published page/post has no meta description (the excerpt or content is used instead).', '%d published pages/posts have no meta description (the excerpt or content is used instead).', $missing, 'mr-tu-multilang-seo' ), $missing );
		}
		if ( ! $parts ) {
			return self::check( 'seo_desc_length', 'ok', $label, __( 'Every description is within range.', 'mr-tu-multilang-seo' ), self::screen( 'mrtu-seo' ) );
		}
		return self::check( 'seo_desc_length', 'warning', $label, implode( ' ', $parts ), self::screen( 'mrtu-seo' ) );
	}

	/**
	 * Focus keyword used by several pages of one language (cannibalization).
	 *
	 * @param array[] $content Content snapshot.
	 * @return array
	 */
	private static function check_focus_keywords( array $content ) {
		$groups = array();
		foreach ( $content as $item ) {
			if ( '' === $item['focus_kw'] ) {
				continue;
			}
			$groups[ $item['lang'] ][ $item['focus_kw'] ][] = $item;
		}
		$list = array();
		foreach ( $groups as $lang => $keywords ) {
			foreach ( $keywords as $keyword => $items ) {
				if ( count( $items ) < 2 ) {
					continue;
				}
				$ids    = array_map( 'intval', wp_list_pluck( $items, 'id' ) );
				$list[] = sprintf( '%1$s: “%2$s” ×%3$d (#%4$s)', strtoupper( $lang ), $keyword, count( $items ), implode( ', #', $ids ) );
			}
		}
		$label = __( 'Focus keyword cannibalization', 'mr-tu-multilang-seo' );
		if ( ! $list ) {
			return self::check( 'seo_focus_keywords', 'ok', $label, __( 'Each focus keyword is used by one page per language.', 'mr-tu-multilang-seo' ), self::screen( 'mrtu-seo' ) );
		}
		return self::check( 'seo_focus_keywords', 'warning', $label, __( 'Several pages of one language target the same keyword and compete with each other:', 'mr-tu-multilang-seo' ) . ' ' . self::examples( $list ), self::screen( 'mrtu-seo' ) );
	}

	/*
	 * Links
	 * ------------------------------------------------------------------
	 */

	/**
	 * Resolves every internal link of the published content.
	 *
	 * @param array[] $content Content snapshot.
	 * @return array {
	 *     @type array $inbound  target post id => [ source post ids ] (same-language links only).
	 *     @type array $broken   [ [ 'source' => item, 'url' => string ] ].
	 *     @type array $cross    [ [ 'source' => item, 'url' => string, 'lang' => string ] ].
	 *     @type int   $checked  Number of internal links checked.
	 * }
	 */
	public static function scan_links( array $content ) {
		$out = array(
			'inbound' => array(),
			'broken'  => array(),
			'cross'   => array(),
			'checked' => 0,
		);
		foreach ( $content as $item ) {
			foreach ( self::extract_hrefs( $item['content'] ) as $href ) {
				$url = self::absolute_url( $href );
				if ( '' === $url ) {
					continue;
				}
				$target = self::resolve_internal( $url );
				if ( null === $target ) {
					continue; // External or asset.
				}
				++$out['checked'];
				if ( $target['lang'] !== $item['lang'] ) {
					$out['cross'][] = array(
						'source' => $item,
						'url'    => $url,
						'lang'   => $target['lang'],
					);
				}
				if ( ! $target['ok'] ) {
					$out['broken'][] = array(
						'source' => $item,
						'url'    => $url,
					);
					continue;
				}
				if ( $target['post_id'] && $target['lang'] === $item['lang'] && $target['post_id'] !== $item['id'] ) {
					$out['inbound'][ $target['post_id'] ][] = $item['id'];
				}
			}
		}
		return $out;
	}

	/**
	 * Href values of the anchors in a block of HTML.
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	private static function extract_hrefs( $html ) {
		if ( ! preg_match_all( '/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1/is', (string) $html, $matches ) ) {
			return array();
		}
		return array_map( 'html_entity_decode', $matches[2] );
	}

	/**
	 * Turns an href into an absolute site URL ('' for anchors, mailto/tel/javascript and empty values).
	 *
	 * @param string $href Href.
	 * @return string
	 */
	private static function absolute_url( $href ) {
		$href = trim( (string) $href );
		if ( '' === $href || '#' === $href[0] ) {
			return '';
		}
		if ( preg_match( '#^(mailto|tel|sms|javascript|data):#i', $href ) ) {
			return '';
		}
		if ( 0 === strpos( $href, '//' ) ) {
			return set_url_scheme( 'http:' . $href );
		}
		if ( '/' === $href[0] ) {
			$home = wp_parse_url( home_url() );
			return $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) . $href;
		}
		if ( preg_match( '#^https?://#i', $href ) ) {
			return $href;
		}
		return '';
	}

	/**
	 * Resolves an absolute URL under the site home to its language and target.
	 *
	 * @param string $url URL.
	 * @return array|null Null for external URLs and assets; else [ ok (bool), lang, post_id ].
	 */
	private static function resolve_internal( $url ) {
		$parts = Links::split( strtok( $url, '#' ) );
		if ( null === $parts ) {
			return null;
		}
		$rest = (string) strtok( $parts[1], '?' );
		$lang = (string) Links::language_of_url( $url );
		$path = trim( Links::strip_language_prefix( $rest ), '/' );
		if ( preg_match( '#^(wp-content|wp-includes|wp-admin|wp-json|wp-login\.php|xmlrpc\.php|feed|comments|robots\.txt|favicon\.ico)(/|$)#', $path ) || preg_match( '/\.(xml|xsl|txt|pdf|jpe?g|png|gif|webp|avif|svg|css|js|woff2?)$/i', $path ) ) {
			return null;
		}
		$result = array(
			'ok'      => false,
			'lang'    => $lang,
			'post_id' => 0,
		);
		if ( ! Languages::is_enabled( $lang ) ) {
			return $result;
		}
		// Pagination and feeds of the target do not change what it points at.
		$path = (string) preg_replace( '#(^|/)(page/\d+|feed|embed)$#', '', $path );
		$path = trim( $path, '/' );

		if ( '' === $path ) {
			if ( 'page' === get_option( 'show_on_front' ) ) {
				$front             = Router::raw_option( 'page_on_front' );
				$home              = $front ? Translations::get_post_translation( $front, $lang, true ) : 0;
				$result['ok']      = $home > 0;
				$result['post_id'] = (int) $home;
			} else {
				$result['ok'] = true;
			}
			return $result;
		}

		foreach ( Translations::taxonomies() as $taxonomy ) {
			$tax = get_taxonomy( $taxonomy );
			if ( ! $tax || empty( $tax->rewrite['slug'] ) ) {
				continue;
			}
			$base = trim( (string) $tax->rewrite['slug'], '/' );
			if ( 0 === strpos( $path, $base . '/' ) ) {
				$segments     = explode( '/', substr( $path, strlen( $base ) + 1 ) );
				$term         = get_term_by( 'slug', sanitize_title( (string) end( $segments ) ), $taxonomy );
				$result['ok'] = $term instanceof \WP_Term && Translations::get_term_language( $term ) === $lang;
				return $result;
			}
		}

		$types        = Translations::post_types();
		$hierarchical = array_values( array_filter( $types, 'is_post_type_hierarchical' ) );
		$flat         = array_values( array_diff( $types, $hierarchical ) );
		$id           = $hierarchical ? Translations::find_post_by_path( $path, $lang, $hierarchical ) : 0;
		if ( ! $id && $flat && false === strpos( $path, '/' ) ) {
			$id = Translations::find_post_by_name( $path, $lang, $flat );
		}
		if ( $id && 'publish' === get_post_status( $id ) ) {
			$result['ok']      = true;
			$result['post_id'] = (int) $id;
		}
		return $result;
	}

	/**
	 * Published content nobody links to from same-language content (role pages are in the menus).
	 *
	 * @param array[] $content Content snapshot.
	 * @param array   $links   scan_links() result.
	 * @return array
	 */
	private static function check_orphans( array $content, array $links ) {
		$front = 'page' === get_option( 'show_on_front' ) ? Router::raw_option( 'page_on_front' ) : 0;
		$blog  = 'page' === get_option( 'show_on_front' ) ? Router::raw_option( 'page_for_posts' ) : 0;
		$list  = array();
		foreach ( $content as $item ) {
			if ( '' !== $item['role'] || isset( $links['inbound'][ $item['id'] ] ) ) {
				continue;
			}
			if ( ( $front && Translations::in_same_group( $item['id'], $front ) ) || ( $blog && Translations::in_same_group( $item['id'], $blog ) ) ) {
				continue;
			}
			$list[] = self::name( $item );
		}
		$label = __( 'Orphan pages and posts (no internal links)', 'mr-tu-multilang-seo' );
		if ( ! $list ) {
			return self::check( 'orphans', 'ok', $label, __( 'Every page and post is linked from same-language content or placed by its role.', 'mr-tu-multilang-seo' ) );
		}
		/* translators: 1: number of items, 2: examples. */
		return self::check( 'orphans', 'warning', $label, sprintf( _n( '%1$d item has no internal link from content in its language: %2$s', '%1$d items have no internal link from content in their language: %2$s', count( $list ), 'mr-tu-multilang-seo' ), count( $list ), self::examples( $list ) ), admin_url( 'edit.php?post_type=page' ) );
	}

	/**
	 * Internal links that do not resolve to published content in the linked language.
	 *
	 * @param array $links scan_links() result.
	 * @return array
	 */
	private static function check_broken_links( array $links ) {
		$label = __( 'Internal links', 'mr-tu-multilang-seo' );
		$list  = array();
		foreach ( $links['broken'] as $broken ) {
			$list[] = sprintf( '%1$s → %2$s', self::name( $broken['source'] ), $broken['url'] );
		}
		$cross = array();
		foreach ( $links['cross'] as $item ) {
			$cross[] = sprintf( '%1$s → %2$s', self::name( $item['source'] ), $item['url'] );
		}
		$parts = array();
		if ( $list ) {
			/* translators: 1: number of links, 2: examples. */
			$parts[] = sprintf( _n( '%1$d link points to a page that is not published in that language: %2$s', '%1$d links point to pages that are not published in that language: %2$s', count( $list ), 'mr-tu-multilang-seo' ), count( $list ), self::examples( $list ) );
		}
		if ( $cross ) {
			/* translators: 1: number of links, 2: examples. */
			$parts[] = sprintf( _n( '%1$d link leaves the language of its page: %2$s', '%1$d links leave the language of their page: %2$s', count( $cross ), 'mr-tu-multilang-seo' ), count( $cross ), self::examples( $cross ) );
		}
		if ( ! $parts ) {
			/* translators: %d: number of links checked. */
			return self::check( 'broken_links', 'ok', $label, sprintf( _n( '%d internal link checked, all resolve to published same-language content.', '%d internal links checked, all resolve to published same-language content.', $links['checked'], 'mr-tu-multilang-seo' ), $links['checked'] ) );
		}
		return self::check( 'broken_links', $list ? 'error' : 'warning', $label, implode( ' ', $parts ) );
	}

	/**
	 * Images without an alt attribute in published content.
	 *
	 * @param array[] $content Content snapshot.
	 * @return array
	 */
	private static function check_images( array $content ) {
		$list  = array();
		$total = 0;
		foreach ( $content as $item ) {
			if ( ! preg_match_all( '/<img\b[^>]*>/i', $item['content'], $matches ) ) {
				continue;
			}
			$missing = 0;
			foreach ( $matches[0] as $tag ) {
				++$total;
				if ( ! preg_match( '/\balt\s*=/i', $tag ) ) {
					++$missing;
				}
			}
			if ( $missing ) {
				$list[] = sprintf( '%1$s — %2$d', self::name( $item ), $missing );
			}
		}
		$label = __( 'Images without alt text', 'mr-tu-multilang-seo' );
		if ( ! $list ) {
			/* translators: %d: number of images. */
			return self::check( 'images_alt', 'ok', $label, sprintf( _n( '%d image in content, every one has an alt attribute.', '%d images in content, every one has an alt attribute.', $total, 'mr-tu-multilang-seo' ), $total ) );
		}
		return self::check( 'images_alt', 'warning', $label, __( 'Images without an alt attribute (count per page):', 'mr-tu-multilang-seo' ) . ' ' . self::examples( $list ) );
	}

	/*
	 * Business, Quick Contact, SEO mode
	 * ------------------------------------------------------------------
	 */

	/**
	 * Unverified business facts (shown as PENDING VERIFICATION, never published).
	 *
	 * @return array
	 */
	private static function check_business() {
		$business = Business::get_business();
		$pricing  = Business::get_pricing();
		$labels   = array(
			'brand'         => __( 'brand', 'mr-tu-multilang-seo' ),
			'display_name'  => __( 'listing name', 'mr-tu-multilang-seo' ),
			'address'       => __( 'address', 'mr-tu-multilang-seo' ),
			'phone'         => __( 'phone', 'mr-tu-multilang-seo' ),
			'hours'         => __( 'opening hours', 'mr-tu-multilang-seo' ),
			'service_model' => __( 'service model', 'mr-tu-multilang-seo' ),
			'service_area'  => __( 'service area', 'mr-tu-multilang-seo' ),
			'maps_url'      => __( 'Google Maps link', 'mr-tu-multilang-seo' ),
		);
		$pending  = array();
		foreach ( $labels as $key => $label ) {
			if ( empty( $business[ $key ]['verified'] ) ) {
				$pending[] = $label;
			}
		}
		foreach ( array( 'whatsapp', 'zalo' ) as $channel ) {
			if ( ! empty( $business['channels'][ $channel ]['enabled'] ) && empty( $business['channels'][ $channel ]['verified'] ) ) {
				$pending[] = ucfirst( $channel );
			}
		}
		$vehicles   = 0;
		$unverified = 0;
		foreach ( $pricing['vehicles'] as $vehicle ) {
			if ( 'hidden' === $vehicle['status'] ) {
				continue;
			}
			++$vehicles;
			if ( empty( $vehicle['verified'] ) ) {
				++$unverified;
			}
		}
		$parts = array();
		if ( $pending ) {
			/* translators: %s: comma-separated field names. */
			$parts[] = sprintf( __( 'Pending verification: %s.', 'mr-tu-multilang-seo' ), implode( ', ', $pending ) );
		}
		if ( empty( $pricing['verified'] ) ) {
			$parts[] = __( 'The price list is not confirmed yet (prices show as “on request”).', 'mr-tu-multilang-seo' );
		} elseif ( $unverified ) {
			/* translators: 1: unverified vehicles, 2: public vehicles. */
			$parts[] = sprintf( __( '%1$d of %2$d public vehicles have unconfirmed prices.', 'mr-tu-multilang-seo' ), $unverified, $vehicles );
		}
		$label = __( 'Business data verification', 'mr-tu-multilang-seo' );
		if ( ! $parts ) {
			return self::check( 'business', 'ok', $label, __( 'Store information and prices are verified.', 'mr-tu-multilang-seo' ), self::screen( 'mrtu-business' ) );
		}
		$severity = empty( $business['phone']['verified'] ) ? 'error' : 'warning';
		if ( 'error' === $severity ) {
			array_unshift( $parts, __( 'The phone number is not verified, so the website shows no call button.', 'mr-tu-multilang-seo' ) );
		}
		return self::check( 'business', $severity, $label, implode( ' ', $parts ), self::screen( 'mrtu-business' ) );
	}

	/**
	 * Quick Contact items that are switched on but cannot be published.
	 *
	 * @return array
	 */
	private static function check_quick_contact() {
		$label = __( 'Quick Contact', 'mr-tu-multilang-seo' );
		if ( ! class_exists( __NAMESPACE__ . '\\Quick_Contact' ) ) {
			return self::check( 'quick_contact', 'warning', $label, __( 'The Quick Contact module is not available.', 'mr-tu-multilang-seo' ) );
		}
		$settings = Quick_Contact::settings();
		if ( empty( $settings['enabled'] ) ) {
			return self::check( 'quick_contact', 'ok', $label, __( 'Switched off.', 'mr-tu-multilang-seo' ), self::screen( 'mrtu-quick-contact' ) );
		}
		$labels  = Quick_Contact::labels();
		$live    = array();
		$blocked = array();
		foreach ( Quick_Contact::item_status() as $item => $status ) {
			if ( ! $status['on'] ) {
				continue;
			}
			$name = isset( $labels[ $item ] ) ? $labels[ $item ] : $item;
			if ( $status['publishable'] ) {
				$live[] = $name;
			} else {
				$blocked[] = $name . ( '' !== $status['reason'] ? ' (' . $status['reason'] . ')' : '' );
			}
		}
		/* translators: %s: comma-separated item names. */
		$detail = $live ? sprintf( __( 'Showing: %s.', 'mr-tu-multilang-seo' ), implode( ', ', $live ) ) : __( 'No item can be shown right now.', 'mr-tu-multilang-seo' );
		if ( $blocked ) {
			/* translators: %s: comma-separated item names with reasons. */
			$detail .= ' ' . sprintf( __( 'Switched on but hidden: %s.', 'mr-tu-multilang-seo' ), implode( ', ', $blocked ) );
		}
		$severity = $live ? ( $blocked ? 'warning' : 'ok' ) : 'error';
		return self::check( 'quick_contact', $severity, $label, $detail, self::screen( 'mrtu-quick-contact' ) );
	}

	/**
	 * Whether Yoast SEO is active.
	 *
	 * @return bool
	 */
	public static function yoast_active() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' );
	}

	/**
	 * Effective SEO mode (compat|standalone) from the settings and the Yoast presence.
	 *
	 * @return array [ 'setting' => auto|compat|standalone, 'effective' => compat|standalone, 'yoast' => bool, 'module' => bool ]
	 */
	public static function seo_mode() {
		$setting = (string) Languages::setting( 'seo_mode', 'auto' );
		$yoast   = self::yoast_active();
		if ( function_exists( 'mrtu_is_seo_standalone' ) ) {
			$effective = mrtu_is_seo_standalone() ? 'standalone' : 'compat';
		} elseif ( 'auto' === $setting ) {
			$effective = $yoast ? 'compat' : 'standalone';
		} else {
			$effective = 'compat' === $setting ? 'compat' : 'standalone';
		}
		return array(
			'setting'   => $setting,
			'effective' => $effective,
			'yoast'     => $yoast,
			'module'    => class_exists( __NAMESPACE__ . '\\Seo' ),
		);
	}

	/**
	 * SEO mode + Yoast presence.
	 *
	 * @return array
	 */
	private static function check_seo_mode() {
		$mode   = self::seo_mode();
		$label  = __( 'SEO mode', 'mr-tu-multilang-seo' );
		$names  = array(
			'auto'       => __( 'automatic', 'mr-tu-multilang-seo' ),
			'compat'     => __( 'Yoast compatibility', 'mr-tu-multilang-seo' ),
			'standalone' => __( 'standalone', 'mr-tu-multilang-seo' ),
		);
		$detail = sprintf(
			/* translators: 1: configured mode, 2: effective mode, 3: "active" or "not active". */
			__( 'Setting: %1$s · running: %2$s · Yoast SEO: %3$s.', 'mr-tu-multilang-seo' ),
			isset( $names[ $mode['setting'] ] ) ? $names[ $mode['setting'] ] : $mode['setting'],
			$names[ $mode['effective'] ],
			$mode['yoast'] ? __( 'active', 'mr-tu-multilang-seo' ) : __( 'not active', 'mr-tu-multilang-seo' )
		);
		if ( ! $mode['module'] ) {
			return self::check( 'seo_mode', 'warning', $label, $detail . ' ' . __( 'The SEO module is not loaded: no canonical, hreflang or sitemap output.', 'mr-tu-multilang-seo' ), self::screen( 'mrtu-seo' ) );
		}
		if ( 'standalone' === $mode['effective'] && $mode['yoast'] ) {
			return self::check( 'seo_mode', 'warning', $label, $detail . ' ' . __( 'Standalone mode with Yoast active: Yoast head output is switched off by the plugin; make sure only one canonical appears (see the head audit).', 'mr-tu-multilang-seo' ), self::screen( 'mrtu-seo' ) );
		}
		return self::check( 'seo_mode', 'ok', $label, $detail, self::screen( 'mrtu-seo' ) );
	}

	/*
	 * Front pages, languages, sitemap, head
	 * ------------------------------------------------------------------
	 */

	/**
	 * Front page and posts page resolve in every enabled language.
	 *
	 * @return array
	 */
	private static function check_front_pages() {
		$label = __( 'Front page and posts page per language', 'mr-tu-multilang-seo' );
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return self::check( 'front_pages', 'warning', $label, __( 'The site shows the latest posts on the front page. Set a static front page (seed with “Set reading settings”) so every language gets its own home page.', 'mr-tu-multilang-seo' ), admin_url( 'options-reading.php' ) );
		}
		$front   = Router::raw_option( 'page_on_front' );
		$blog    = Router::raw_option( 'page_for_posts' );
		$no_home = array();
		$no_blog = array();
		foreach ( Languages::codes( true ) as $code ) {
			if ( ! $front || ! Translations::get_post_translation( $front, $code, true ) ) {
				$no_home[] = strtoupper( $code );
			}
			if ( ! $blog || ! Translations::get_post_translation( $blog, $code, true ) ) {
				$no_blog[] = strtoupper( $code );
			}
		}
		$parts = array();
		if ( $no_home ) {
			/* translators: %s: comma-separated language codes. */
			$parts[] = sprintf( __( 'No published home page in: %s (their home address returns “not found”).', 'mr-tu-multilang-seo' ), implode( ', ', $no_home ) );
		}
		if ( $no_blog ) {
			/* translators: %s: comma-separated language codes. */
			$parts[] = sprintf( __( 'No published posts page in: %s.', 'mr-tu-multilang-seo' ), implode( ', ', $no_blog ) );
		}
		if ( ! $parts ) {
			return self::check( 'front_pages', 'ok', $label, __( 'Every enabled language has a published home page and posts page.', 'mr-tu-multilang-seo' ), admin_url( 'options-reading.php' ) );
		}
		return self::check( 'front_pages', $no_home ? 'error' : 'warning', $label, implode( ' ', $parts ), admin_url( 'options-reading.php' ) );
	}

	/**
	 * Disabled languages (with published content → warning).
	 *
	 * @param array[] $content Content snapshot.
	 * @return array
	 */
	private static function check_disabled_languages( array $content ) {
		$label    = __( 'Disabled languages', 'mr-tu-multilang-seo' );
		$disabled = array();
		foreach ( Languages::all( false ) as $code => $lang ) {
			if ( ! $lang['enabled'] ) {
				$disabled[ $code ] = 0;
			}
		}
		if ( ! $disabled ) {
			return self::check( 'disabled_languages', 'ok', $label, __( 'All five languages are enabled.', 'mr-tu-multilang-seo' ), self::screen( 'mrtu-languages' ) );
		}
		foreach ( $content as $item ) {
			if ( isset( $disabled[ $item['lang'] ] ) ) {
				++$disabled[ $item['lang'] ];
			}
		}
		$parts    = array();
		$severity = 'ok';
		foreach ( $disabled as $code => $count ) {
			if ( $count ) {
				$severity = 'warning';
				/* translators: 1: language code, 2: number of published pages/posts. */
				$parts[] = sprintf( __( '%1$s (%2$d published pages/posts are unreachable)', 'mr-tu-multilang-seo' ), strtoupper( $code ), $count );
			} else {
				$parts[] = strtoupper( $code );
			}
		}
		/* translators: %s: comma-separated language codes. */
		return self::check( 'disabled_languages', $severity, $label, sprintf( __( 'Switched off: %s.', 'mr-tu-multilang-seo' ), implode( ', ', $parts ) ), self::screen( 'mrtu-languages' ) );
	}

	/**
	 * Loopback GET (local requests to the site itself).
	 *
	 * @param string $url URL.
	 * @return array|\WP_Error [ 'code' => int, 'body' => string ]
	 */
	private static function fetch( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
				'headers'     => array( 'X-MrTu-Health' => '1' ),
				'user-agent'  => 'Mr Tu Health Check',
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => (string) wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * Sitemap index reachable (standalone mode).
	 *
	 * @param bool $loopback Whether HTTP requests are allowed.
	 * @return array
	 */
	private static function check_sitemap( $loopback ) {
		$label = __( 'XML sitemap', 'mr-tu-multilang-seo' );
		$mode  = self::seo_mode();
		if ( ! function_exists( 'mrtu_sitemap_flush' ) && ! class_exists( __NAMESPACE__ . '\\Sitemaps' ) ) {
			return self::check( 'sitemap', 'warning', $label, __( 'The sitemap module is not available.', 'mr-tu-multilang-seo' ), self::screen( 'mrtu-seo' ) );
		}
		if ( 'compat' === $mode['effective'] ) {
			return self::check( 'sitemap', 'ok', $label, __( 'Yoast compatibility mode: Yoast publishes the sitemap; the plugin adds its hreflang alternates.', 'mr-tu-multilang-seo' ), self::screen( 'mrtu-seo' ) );
		}
		$url = home_url( '/mrtu-sitemap-index.xml' );
		if ( ! $loopback ) {
			/* translators: %s: sitemap URL. */
			return self::check( 'sitemap', 'ok', $label, sprintf( __( 'Not fetched in this run. Index: %s', 'mr-tu-multilang-seo' ), $url ), $url );
		}
		$result = self::fetch( $url );
		if ( is_wp_error( $result ) ) {
			/* translators: 1: sitemap URL, 2: error message. */
			return self::check( 'sitemap', 'warning', $label, sprintf( __( 'Could not fetch %1$s from the server itself (%2$s). Open it in a browser to verify.', 'mr-tu-multilang-seo' ), $url, $result->get_error_message() ), $url );
		}
		if ( 200 !== $result['code'] || false === stripos( $result['body'], '<sitemapindex' ) ) {
			/* translators: 1: sitemap URL, 2: HTTP status code. */
			return self::check( 'sitemap', 'error', $label, sprintf( __( '%1$s does not return a sitemap index (HTTP %2$d). Try “Flush rewrite rules”.', 'mr-tu-multilang-seo' ), $url, $result['code'] ), $url );
		}
		$children = preg_match_all( '#<loc>#i', $result['body'] );
		/* translators: 1: sitemap URL, 2: number of child sitemaps. */
		return self::check( 'sitemap', 'ok', $label, sprintf( __( '%1$s is reachable with %2$d language sitemaps.', 'mr-tu-multilang-seo' ), $url, $children ), $url );
	}

	/**
	 * Live head audit of every language home: exactly one canonical, one description, at most one
	 * robots tag, and hreflang alternates when at least two homes are published.
	 *
	 * @param bool $loopback Whether HTTP requests are allowed.
	 * @return array
	 */
	private static function check_head( $loopback ) {
		$label = __( 'Live head audit of the language home pages', 'mr-tu-multilang-seo' );
		if ( ! $loopback ) {
			return self::check( 'head_audit', 'ok', $label, __( 'Not fetched in this run.', 'mr-tu-multilang-seo' ) );
		}
		$front = 'page' === get_option( 'show_on_front' ) ? Router::raw_option( 'page_on_front' ) : 0;
		$homes = array();
		foreach ( Languages::codes( true ) as $code ) {
			if ( ! $front || Translations::get_post_translation( $front, $code, true ) ) {
				$homes[ $code ] = Links::language_home( $code );
			}
		}
		if ( ! $homes ) {
			return self::check( 'head_audit', 'error', $label, __( 'No language has a published home page.', 'mr-tu-multilang-seo' ) );
		}
		$severity = 'ok';
		$lines    = array();
		$failed   = 0;
		foreach ( $homes as $code => $url ) {
			$result = self::fetch( $url );
			if ( is_wp_error( $result ) ) {
				++$failed;
				$lines[] = sprintf( '%1$s: %2$s', strtoupper( $code ), $result->get_error_message() );
				continue;
			}
			if ( 200 !== $result['code'] ) {
				$severity = 'error';
				$lines[]  = sprintf( '%1$s: HTTP %2$d', strtoupper( $code ), $result['code'] );
				continue;
			}
			$head        = self::head_of( $result['body'] );
			$canonical   = preg_match_all( '/<link\b[^>]*\brel\s*=\s*["\']canonical["\']/i', $head );
			$description = preg_match_all( '/<meta\b[^>]*\bname\s*=\s*["\']description["\']/i', $head );
			$robots      = preg_match_all( '/<meta\b[^>]*\bname\s*=\s*["\']robots["\']/i', $head );
			$hreflang    = preg_match_all( '/<link\b[^>]*\bhreflang\s*=/i', $head );
			$issues      = array();
			if ( 1 !== $canonical ) {
				$severity = 'error';
				/* translators: %d: number of canonical tags. */
				$issues[] = sprintf( _n( '%d canonical', '%d canonicals', $canonical, 'mr-tu-multilang-seo' ), $canonical );
			}
			if ( $description > 1 ) {
				$severity = 'error';
				/* translators: %d: number of description tags. */
				$issues[] = sprintf( __( '%d descriptions', 'mr-tu-multilang-seo' ), $description );
			} elseif ( 0 === $description ) {
				$severity = 'error' === $severity ? 'error' : 'warning';
				$issues[] = __( 'no description', 'mr-tu-multilang-seo' );
			}
			if ( $robots > 1 ) {
				$severity = 'error';
				/* translators: %d: number of robots tags. */
				$issues[] = sprintf( __( '%d robots tags', 'mr-tu-multilang-seo' ), $robots );
			}
			if ( count( $homes ) >= 2 && $hreflang < 2 ) {
				$severity = 'error' === $severity ? 'error' : 'warning';
				$issues[] = __( 'no hreflang alternates', 'mr-tu-multilang-seo' );
			}
			$lines[] = sprintf(
				/* translators: 1: language code, 2: canonical count, 3: description count, 4: robots count, 5: hreflang count, 6: issues. */
				__( '%1$s: canonical %2$d · description %3$d · robots %4$d · hreflang %5$d%6$s', 'mr-tu-multilang-seo' ),
				strtoupper( $code ),
				$canonical,
				$description,
				$robots,
				$hreflang,
				$issues ? ' — ' . implode( ', ', $issues ) : ''
			);
		}
		if ( count( $homes ) === $failed ) {
			return self::check( 'head_audit', 'warning', $label, __( 'The server could not fetch its own pages (loopback blocked). Check the head tags in a browser.', 'mr-tu-multilang-seo' ) . ' ' . implode( ' · ', $lines ) );
		}
		return self::check( 'head_audit', $severity, $label, implode( ' · ', $lines ) );
	}

	/**
	 * The `<head>` part of an HTML document (whole document when no `</head>`).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function head_of( $html ) {
		$end = stripos( $html, '</head>' );
		return false === $end ? $html : substr( $html, 0, $end );
	}

	/*
	 * Actions
	 * ------------------------------------------------------------------
	 */

	/**
	 * Rebuilds every translation map (Translations::rebuild_group() for every group).
	 *
	 * @return int Groups processed.
	 */
	public static function rebuild_translation_maps() {
		$count = Translations::rebuild_all();
		self::flush();
		return $count;
	}

	/**
	 * Flushes the rewrite rules.
	 */
	public static function flush_rewrite() {
		flush_rewrite_rules();
		self::flush();
	}

	/**
	 * Flushes the sitemap cache through the public API.
	 *
	 * @return bool False when the sitemap module is not available.
	 */
	public static function flush_sitemap() {
		self::flush();
		if ( ! function_exists( 'mrtu_sitemap_flush' ) ) {
			return false;
		}
		mrtu_sitemap_flush();
		return true;
	}
}
