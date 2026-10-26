<?php
/**
 * Translation management in wp-admin:
 * - VI/EN/DE/FR/ES columns, language views and a language filter on post/page lists;
 * - "Language & translations" meta box on edit screens;
 * - the "Translations" matrix screen (+ create / link / unlink / rebuild actions);
 * - language + translation fields for categories and tags.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang\Admin;

use MrTu\Multilang\Languages;
use MrTu\Multilang\Roles;
use MrTu\Multilang\Router;
use MrTu\Multilang\Translations;

defined( 'ABSPATH' ) || exit;

/**
 * Translations admin.
 */
final class Admin_Translations {

	const SLUG = 'mrtu-translations';

	const CREATE = 'mrtu_create_translation';

	const LINK = 'mrtu_link_translations';

	const UNLINK = 'mrtu_unlink_translation';

	const REBUILD = 'mrtu_rebuild_translations';

	const TERM_NONCE = 'mrtu_term_lang';

	const PER_PAGE = 30;

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_filter( 'mrtu_admin_pages', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_post_' . self::CREATE, array( __CLASS__, 'handle_create' ) );
		add_action( 'admin_post_' . self::LINK, array( __CLASS__, 'handle_link' ) );
		add_action( 'admin_post_' . self::UNLINK, array( __CLASS__, 'handle_unlink' ) );
		add_action( 'admin_post_' . self::REBUILD, array( __CLASS__, 'handle_rebuild' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_screen_hooks' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ), 10, 1 );
		add_action( 'save_post', array( __CLASS__, 'save_post_role' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'block_editor_notice' ) );
	}

	/**
	 * Hooks that need the final list of post types and taxonomies.
	 */
	public static function register_screen_hooks() {
		foreach ( Translations::post_types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'list_columns' ) );
			add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'list_column' ), 10, 2 );
			add_filter( "views_edit-{$type}", array( __CLASS__, 'list_views' ) );
		}
		add_action( 'restrict_manage_posts', array( __CLASS__, 'list_filter' ), 10, 2 );

		foreach ( Translations::taxonomies() as $taxonomy ) {
			add_action( "{$taxonomy}_add_form_fields", array( __CLASS__, 'term_add_fields' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( __CLASS__, 'term_edit_fields' ), 10, 2 );
			add_action( "created_{$taxonomy}", array( __CLASS__, 'save_new_term' ) );
			add_action( "edited_{$taxonomy}", array( __CLASS__, 'save_edited_term' ) );
			add_filter( "manage_edit-{$taxonomy}_columns", array( __CLASS__, 'term_columns' ) );
			add_filter( "manage_{$taxonomy}_custom_column", array( __CLASS__, 'term_column' ), 10, 3 );
		}
	}

	/**
	 * Registers the matrix screen.
	 *
	 * @param array $pages Pages.
	 * @return array
	 */
	public static function register_page( $pages ) {
		$pages[] = array(
			'slug'       => self::SLUG,
			'title'      => __( 'Translations', 'mr-tu-multilang-seo' ),
			'menu_title' => __( 'Translations', 'mr-tu-multilang-seo' ),
			'capability' => 'edit_posts',
			'callback'   => array( __CLASS__, 'render' ),
			'position'   => 45,
		);
		return $pages;
	}

	/*
	 * Shared helpers
	 */

	/**
	 * Number of posts per language (untagged = default language).
	 *
	 * @param string   $post_type Post type.
	 * @param string[] $statuses  Statuses (default: every status shown in the "All" view).
	 * @return array code => int
	 */
	public static function language_counts( $post_type, $statuses = array() ) {
		global $wpdb;
		$statuses  = $statuses ? array_values( $statuses ) : array_values( get_post_stati( array( 'show_in_admin_all_list' => true ) ) );
		$status_in = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- admin counts; IN lists hold placeholders only.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ( {$status_in} )", array_merge( array( $post_type ), $statuses ) ) );

		$tt_map = array();
		foreach ( Languages::codes( false ) as $code ) {
			$tt = Translations::language_tt_id( $code );
			if ( $tt ) {
				$tt_map[ $tt ] = $code;
			}
		}
		$counts = array_fill_keys( Languages::codes( false ), 0 );
		if ( $tt_map ) {
			$tt_in = implode( ', ', array_fill( 0, count( $tt_map ), '%d' ) );
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tr.term_taxonomy_id AS tt, COUNT(*) AS n FROM {$wpdb->posts} p INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID WHERE p.post_type = %s AND p.post_status IN ( {$status_in} ) AND tr.term_taxonomy_id IN ( {$tt_in} ) GROUP BY tr.term_taxonomy_id",
					array_merge( array( $post_type ), $statuses, array_keys( $tt_map ) )
				)
			);
			foreach ( (array) $rows as $row ) {
				if ( isset( $tt_map[ (int) $row->tt ] ) ) {
					$counts[ $tt_map[ (int) $row->tt ] ] = (int) $row->n;
				}
			}
		}
		// phpcs:enable

		$default            = Languages::default_code();
		$others             = array_sum( $counts ) - $counts[ $default ];
		$counts[ $default ] = max( 0, $total - $others );
		return $counts;
	}

	/**
	 * Status cell for a post in a language: ✅ published / ✏️ other status (links to edit) / ➕ create.
	 *
	 * @param int    $post_id Post id.
	 * @param string $lang    Language.
	 * @return string Escaped HTML.
	 */
	public static function cell( $post_id, $lang ) {
		$map  = Translations::get_post_translations( $post_id );
		$name = Languages::admin_name( $lang );

		if ( isset( $map[ $lang ] ) ) {
			$id        = (int) $map[ $lang ];
			$status    = (string) get_post_status( $id );
			$published = 'publish' === $status;
			$status_ob = get_post_status_object( $status );
			/* translators: 1: language name, 2: post status label. */
			$label = sprintf( __( '%1$s: %2$s – edit', 'mr-tu-multilang-seo' ), $name, $status_ob ? $status_ob->label : $status );
			$icon  = $published ? '✅' : '✏️';
			$url   = get_edit_post_link( $id, 'raw' );
			if ( ! $url ) {
				return '<span class="mrtu-lang-cell" title="' . esc_attr( $label ) . '"><span aria-hidden="true">' . $icon . '</span><span class="screen-reader-text">' . esc_html( $label ) . '</span></span>';
			}
			return '<a class="mrtu-lang-cell" href="' . esc_url( $url ) . '" title="' . esc_attr( $label ) . '"><span aria-hidden="true">' . $icon . '</span><span class="screen-reader-text">' . esc_html( $label ) . '</span></a>';
		}

		/* translators: %s: language name. */
		$label = sprintf( __( '%s: no translation yet – create', 'mr-tu-multilang-seo' ), $name );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return '<span class="mrtu-lang-cell" aria-hidden="true">–</span>';
		}
		$url = Admin::url(
			self::SLUG,
			array(
				'view' => 'create',
				'post' => (int) $post_id,
				'lang' => $lang,
			)
		);
		return '<a class="mrtu-lang-cell" href="' . esc_url( $url ) . '" title="' . esc_attr( $label ) . '"><span aria-hidden="true">➕</span><span class="screen-reader-text">' . esc_html( $label ) . '</span></a>';
	}

	/**
	 * Admin-post URL that creates a translation directly (meta box buttons).
	 *
	 * @param int    $post_id Source post id.
	 * @param string $lang    Target language.
	 * @param bool   $copy    Copy content.
	 * @return string
	 */
	private static function create_url( $post_id, $lang, $copy ) {
		$url = add_query_arg(
			array(
				'action' => self::CREATE,
				'post'   => (int) $post_id,
				'lang'   => $lang,
				'copy'   => $copy ? 1 : 0,
			),
			admin_url( 'admin-post.php' )
		);
		return wp_nonce_url( $url, self::CREATE . '_' . (int) $post_id . '_' . $lang, '_mrtu_nonce' );
	}

	/**
	 * Language select markup.
	 *
	 * @param string $name     Field name.
	 * @param string $id       Element id.
	 * @param string $selected Selected code.
	 * @return string Escaped HTML.
	 */
	private static function language_select( $name, $id, $selected ) {
		$html = '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '">';
		foreach ( Languages::all( false ) as $code => $lang ) {
			$label = Languages::admin_name( $code ) . ' (' . strtoupper( $code ) . ')';
			if ( ! $lang['enabled'] ) {
				/* translators: %s: language name. */
				$label = sprintf( __( '%s – currently off', 'mr-tu-multilang-seo' ), $label );
			}
			$html .= '<option value="' . esc_attr( $code ) . '"' . selected( $code, $selected, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $html . '</select>';
	}

	/*
	 * List tables
	 */

	/**
	 * Adds VI/EN/DE/FR/ES columns after the title.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function list_columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				foreach ( Languages::all( false ) as $code => $lang ) {
					$out[ 'mrtu_lang_' . $code ] = '<span title="' . esc_attr( Languages::admin_name( $code ) ) . '">' . esc_html( strtoupper( $code ) ) . '</span>';
				}
			}
		}
		return $out;
	}

	/**
	 * Renders a language column cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post id.
	 */
	public static function list_column( $column, $post_id ) {
		if ( 0 !== strpos( $column, 'mrtu_lang_' ) ) {
			return;
		}
		echo self::cell( (int) $post_id, substr( $column, 10 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
	}

	/**
	 * One view per language ("VI (12) | EN (8) …").
	 *
	 * @param array $views Views.
	 * @return array
	 */
	public static function list_views( $views ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! Translations::is_translatable_post_type( $screen->post_type ) ) {
			return $views;
		}
		$current = isset( $_GET[ Router::QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ Router::QUERY_VAR ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		if ( '' !== $current && isset( $views['all'] ) ) {
			$views['all'] = str_replace( array( ' class="current"', ' aria-current="page"' ), '', $views['all'] );
		}
		foreach ( self::language_counts( $screen->post_type ) as $code => $count ) {
			$url                           = add_query_arg(
				array(
					'post_type'       => $screen->post_type,
					Router::QUERY_VAR => $code,
				),
				admin_url( 'edit.php' )
			);
			$attrs                         = $current === $code ? ' class="current" aria-current="page"' : '';
			$views[ 'mrtu_lang_' . $code ] = '<a href="' . esc_url( $url ) . '"' . $attrs . ' title="' . esc_attr( Languages::admin_name( $code ) ) . '">' . esc_html( strtoupper( $code ) ) . ' <span class="count">(' . esc_html( number_format_i18n( $count ) ) . ')</span></a>';
		}
		return $views;
	}

	/**
	 * Language filter dropdown above the list.
	 *
	 * @param string $post_type Post type.
	 * @param string $which     top|bottom.
	 */
	public static function list_filter( $post_type, $which = 'top' ) {
		if ( 'top' !== $which || ! Translations::is_translatable_post_type( $post_type ) ) {
			return;
		}
		$current = isset( $_GET[ Router::QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ Router::QUERY_VAR ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- list filter.
		echo '<label class="screen-reader-text" for="mrtu-filter-lang">' . esc_html__( 'Filter by language', 'mr-tu-multilang-seo' ) . '</label>';
		echo '<select name="' . esc_attr( Router::QUERY_VAR ) . '" id="mrtu-filter-lang">';
		echo '<option value="">' . esc_html__( 'All languages', 'mr-tu-multilang-seo' ) . '</option>';
		foreach ( Languages::all( false ) as $code => $lang ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $code, $current, false ) . '>' . esc_html( Languages::admin_name( $code ) ) . '</option>';
		}
		echo '</select>';
	}

	/*
	 * Meta box
	 */

	/**
	 * Registers the "Language & translations" meta box.
	 *
	 * @param string $post_type Post type.
	 */
	public static function add_meta_box( $post_type ) {
		if ( ! Translations::is_translatable_post_type( $post_type ) ) {
			return;
		}
		add_meta_box( 'mrtu-translations', __( 'Language & translations', 'mr-tu-multilang-seo' ), array( __CLASS__, 'render_meta_box' ), $post_type, 'side', 'high' );
	}

	/**
	 * Renders the meta box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		$saved = 'auto-draft' !== $post->post_status;
		$map   = $saved ? Translations::get_post_translations( $post ) : array();
		$lang  = $saved ? Translations::get_post_language( $post ) : Router::effective_language();
		if ( $map ) {
			Translations::prime( array_values( $map ) );
		}

		echo '<div class="mrtu-metabox">';

		if ( count( $map ) <= 1 ) {
			wp_nonce_field( 'mrtu_post_lang_' . $post->ID, '_mrtu_lang_nonce' );
			echo '<p><label for="mrtu-post-lang"><strong>' . esc_html__( 'Language of this content', 'mr-tu-multilang-seo' ) . '</strong></label><br>';
			echo self::language_select( 'mrtu_lang', 'mrtu-post-lang', $lang ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in language_select().
			echo '</p>';
		} else {
			/* translators: %s: language name. */
			echo '<p><strong>' . esc_html( sprintf( __( 'Language: %s', 'mr-tu-multilang-seo' ), Languages::admin_name( $lang ) ) ) . '</strong></p>';
		}

		if ( ! $saved ) {
			echo '<p class="description">' . esc_html__( 'Save a draft first, then you can add the other language versions here.', 'mr-tu-multilang-seo' ) . '</p></div>';
			return;
		}

		echo '<p class="description">' . esc_html__( 'Other language versions:', 'mr-tu-multilang-seo' ) . '</p>';
		foreach ( Languages::all( false ) as $code => $language ) {
			if ( $code === $lang ) {
				continue;
			}
			echo '<div class="mrtu-lang-row"><span><strong>' . esc_html( strtoupper( $code ) ) . '</strong> ' . esc_html( Languages::admin_name( $code ) ) . '</span><span>';
			if ( isset( $map[ $code ] ) ) {
				$id        = (int) $map[ $code ];
				$status    = (string) get_post_status( $id );
				$status_ob = get_post_status_object( $status );
				echo Admin::status( 'publish' === $status ? 'ok' : 'warn', $status_ob ? $status_ob->label : $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by Admin::status().
				$edit = get_edit_post_link( $id, 'raw' );
				if ( $edit ) {
					/* translators: %s: language name. */
					echo ' <a href="' . esc_url( $edit ) . '" aria-label="' . esc_attr( sprintf( __( 'Edit the %s version', 'mr-tu-multilang-seo' ), Languages::admin_name( $code ) ) ) . '">' . esc_html__( 'Edit', 'mr-tu-multilang-seo' ) . '</a>';
				}
				if ( 'publish' === $status ) {
					/* translators: %s: language name. */
					echo ' · <a href="' . esc_url( get_permalink( $id ) ) . '" aria-label="' . esc_attr( sprintf( __( 'View the %s version', 'mr-tu-multilang-seo' ), Languages::admin_name( $code ) ) ) . '">' . esc_html__( 'View', 'mr-tu-multilang-seo' ) . '</a>';
				}
			} elseif ( current_user_can( 'edit_post', $post->ID ) ) {
				/* translators: %s: language name. */
				echo '<a class="button button-small" href="' . esc_url( self::create_url( $post->ID, $code, true ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Create the %s version as a draft copy of this content', 'mr-tu-multilang-seo' ), Languages::admin_name( $code ) ) ) . '">' . esc_html__( '+ Copy', 'mr-tu-multilang-seo' ) . '</a> ';
				/* translators: %s: language name. */
				echo '<a class="button button-small" href="' . esc_url( self::create_url( $post->ID, $code, false ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Create an empty %s draft', 'mr-tu-multilang-seo' ), Languages::admin_name( $code ) ) ) . '">' . esc_html__( '+ Empty', 'mr-tu-multilang-seo' ) . '</a>';
			}
			echo '</span></div>';
		}

		if ( current_user_can( 'manage_options' ) ) {
			$role = Roles::get_post_role( $post->ID );
			wp_nonce_field( 'mrtu_post_role_' . $post->ID, '_mrtu_role_nonce' );
			echo '<p><label for="mrtu-post-role"><strong>' . esc_html__( 'Page role', 'mr-tu-multilang-seo' ) . '</strong></label><br><select name="mrtu_role" id="mrtu-post-role">';
			echo '<option value="">' . esc_html__( '— None —', 'mr-tu-multilang-seo' ) . '</option>';
			foreach ( Roles::registry() as $key => $definition ) {
				echo '<option value="' . esc_attr( $key ) . '"' . selected( $key, $role, false ) . '>' . esc_html( $definition['label'] ) . '</option>';
			}
			echo '</select><br><span class="description">' . esc_html__( 'Applies to every language version. Menus and footers find pages by role.', 'mr-tu-multilang-seo' ) . '</span></p>';
		}

		echo '<p class="mrtu-actions">';
		echo '<a href="' . esc_url(
			Admin::url(
				self::SLUG,
				array(
					'view' => 'link',
					'post' => $post->ID,
				)
			)
		) . '">' . esc_html__( 'Link existing translations…', 'mr-tu-multilang-seo' ) . '</a>';
		if ( count( $map ) > 1 && current_user_can( 'edit_post', $post->ID ) ) {
			$unlink = wp_nonce_url(
				add_query_arg(
					array(
						'action' => self::UNLINK,
						'post'   => $post->ID,
					),
					admin_url( 'admin-post.php' )
				),
				self::UNLINK . '_' . $post->ID,
				'_mrtu_nonce'
			);
			echo ' · <a href="' . esc_url( $unlink ) . '" data-mrtu-confirm="' . esc_attr__( 'Remove this content from its translation group? The other language versions are kept.', 'mr-tu-multilang-seo' ) . '">' . esc_html__( 'Unlink', 'mr-tu-multilang-seo' ) . '</a>';
		}
		echo '</p></div>';
	}

	/**
	 * Saves the page role chosen in the meta box (language is applied through wp_insert_post's `mrtu_lang`).
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 */
	public static function save_post_role( $post_id, $post ) {
		if ( ! isset( $_POST['_mrtu_role_nonce'], $_POST['mrtu_role'] ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_mrtu_role_nonce'] ) ), 'mrtu_post_role_' . $post_id ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! $post instanceof \WP_Post || ! Translations::is_translatable_post_type( $post->post_type ) ) {
			return;
		}
		$role = sanitize_key( wp_unslash( $_POST['mrtu_role'] ) );
		if ( ( '' === $role || Roles::exists( $role ) ) && Roles::get_post_role( $post_id ) !== $role ) {
			Roles::set_post_role( $post_id, $role );
		}
	}

	/**
	 * Prints pending one-time notices on post and term screens.
	 */
	public static function admin_notices() {
		global $pagenow;
		if ( in_array( $pagenow, array( 'post.php', 'post-new.php', 'edit.php', 'edit-tags.php', 'term.php' ), true ) ) {
			Admin::flash();
		}
	}

	/**
	 * The block editor ignores admin_notices: hand a pending notice to its notices store.
	 */
	public static function block_editor_notice() {
		$key   = 'mrtu_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( ! $flash || empty( $flash['message'] ) ) {
			return;
		}
		delete_transient( $key );
		$type = in_array( $flash['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $flash['type'] : 'info';
		wp_add_inline_script(
			'wp-notices',
			sprintf(
				'window.addEventListener("load",function(){wp.data.dispatch("core/notices").createNotice(%s,%s,{isDismissible:true});});',
				wp_json_encode( $type ),
				wp_json_encode( (string) $flash['message'] )
			)
		);
	}

	/*
	 * Matrix screen
	 */

	/**
	 * Renders the Translations screen (matrix, create confirmation or link form).
	 */
	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation parameters only.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		$post = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		$lang = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : '';
		// phpcs:enable

		if ( 'create' === $view && $post ) {
			self::render_create( $post, $lang );
			return;
		}
		if ( 'link' === $view && $post ) {
			self::render_link( $post );
			return;
		}
		self::render_matrix();
	}

	/**
	 * Groups of a post type as rows: [ 'id' => representative post id, 'map' => lang => id ].
	 *
	 * @param string $post_type Post type.
	 * @return array[]
	 */
	private static function rows( $post_type ) {
		$ids = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => array_values( get_post_stati( array( 'show_in_admin_all_list' => true ) ) ),
				'perm'             => 'readable', // Contributors never see other authors' drafts/private items here.
				'fields'           => 'ids',
				'numberposts'      => 2000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- ids only; groups are deduplicated and sorted in PHP (site has a few hundred posts at most).
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
				Router::QUERY_VAR  => 'all',
			)
		);
		Translations::prime( $ids );

		$default = Languages::default_code();
		$rows    = array();
		foreach ( $ids as $id ) {
			$map   = Translations::get_post_translations( $id );
			$group = Translations::get_post_group( $id );
			$key   = $group ? 'g' . $group : 'p' . $id;
			if ( isset( $rows[ $key ] ) ) {
				continue;
			}
			$rows[ $key ] = array(
				'id'  => isset( $map[ $default ] ) ? (int) $map[ $default ] : (int) reset( $map ),
				'map' => $map,
			);
		}
		uasort(
			$rows,
			static function ( $a, $b ) {
				return strnatcasecmp( (string) get_the_title( $a['id'] ), (string) get_the_title( $b['id'] ) );
			}
		);
		return array_values( $rows );
	}

	/**
	 * Renders the matrix of translation groups.
	 */
	private static function render_matrix() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation parameters only.
		$types   = array_values( array_filter( Translations::post_types(), 'post_type_exists' ) );
		$type    = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ( in_array( 'page', $types, true ) ? 'page' : reset( $types ) );
		$missing = ! empty( $_GET['missing'] );
		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable
		if ( ! in_array( $type, $types, true ) ) {
			$type = reset( $types );
		}
		$enabled = Languages::all( true );
		$rows    = self::rows( $type );
		$groups  = count( $rows );

		// Coverage per language (published translations).
		$coverage = array_fill_keys( array_keys( $enabled ), 0 );
		foreach ( $rows as $row ) {
			foreach ( $enabled as $code => $lang ) {
				if ( isset( $row['map'][ $code ] ) && 'publish' === get_post_status( $row['map'][ $code ] ) ) {
					++$coverage[ $code ];
				}
			}
		}
		if ( $missing ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $enabled ) {
						return count( array_intersect_key( $row['map'], $enabled ) ) < count( $enabled );
					}
				)
			);
		}
		$total = count( $rows );
		$pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$paged = min( $paged, $pages );
		$rows  = array_slice( $rows, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE );

		Admin::page_open(
			__( 'Translations', 'mr-tu-multilang-seo' ),
			__( 'Every row is one piece of content in all languages. ✅ published · ✏️ draft or other status · ➕ create the missing version.', 'mr-tu-multilang-seo' )
		);

		// Coverage stats.
		echo '<div class="mrtu-stats">';
		foreach ( $enabled as $code => $lang ) {
			echo '<div class="mrtu-stat"><span class="mrtu-stat__value">' . esc_html( number_format_i18n( $coverage[ $code ] ) . '/' . number_format_i18n( $groups ) ) . '</span><span class="mrtu-stat__label">' . esc_html( Languages::admin_name( $code ) ) . '</span></div>';
		}
		echo '</div>';

		// Type tabs + missing filter.
		echo '<p class="mrtu-actions">';
		foreach ( $types as $candidate ) {
			$object = get_post_type_object( $candidate );
			$url    = Admin::url( self::SLUG, array( 'type' => $candidate ) );
			$class  = $candidate === $type ? 'button button-primary' : 'button';
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '"' . ( $candidate === $type ? ' aria-current="page"' : '' ) . '>' . esc_html( $object ? $object->labels->name : $candidate ) . '</a>';
		}
		$toggle_url = Admin::url(
			self::SLUG,
			array(
				'type'    => $type,
				'missing' => $missing ? 0 : 1,
			)
		);
		echo '<a class="button" href="' . esc_url( $toggle_url ) . '">' . esc_html( $missing ? __( 'Show all', 'mr-tu-multilang-seo' ) : __( 'Only missing translations', 'mr-tu-multilang-seo' ) ) . '</a>';
		echo '</p>';

		Admin::card_open( __( 'Translation matrix', 'mr-tu-multilang-seo' ) );
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'Nothing to show.', 'mr-tu-multilang-seo' ) . '</p>';
		} else {
			echo '<div class="mrtu-scroll"><table class="mrtu-table"><caption class="screen-reader-text">' . esc_html__( 'Language versions per content', 'mr-tu-multilang-seo' ) . '</caption><thead><tr>';
			echo '<th scope="col">' . esc_html__( 'Content', 'mr-tu-multilang-seo' ) . '</th><th scope="col">' . esc_html__( 'Role', 'mr-tu-multilang-seo' ) . '</th>';
			foreach ( Languages::all( false ) as $code => $lang ) {
				echo '<th scope="col" title="' . esc_attr( Languages::admin_name( $code ) ) . '">' . esc_html( strtoupper( $code ) ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			$roles = Roles::registry();
			foreach ( $rows as $row ) {
				$title = get_the_title( $row['id'] );
				$role  = Roles::get_post_role( $row['id'] );
				$edit  = get_edit_post_link( $row['id'], 'raw' );
				echo '<tr><th scope="row">';
				if ( $edit ) {
					echo '<a href="' . esc_url( $edit ) . '">' . esc_html( '' !== $title ? $title : __( '(no title)', 'mr-tu-multilang-seo' ) ) . '</a>';
				} else {
					echo esc_html( '' !== $title ? $title : __( '(no title)', 'mr-tu-multilang-seo' ) );
				}
				echo '</th><td>' . esc_html( '' !== $role && isset( $roles[ $role ] ) ? $roles[ $role ]['label'] : '—' ) . '</td>';
				foreach ( Languages::all( false ) as $code => $lang ) {
					echo '<td>' . self::cell( $row['id'], $code ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cell().
				}
				echo '</tr>';
			}
			echo '</tbody></table></div>';

			if ( $pages > 1 ) {
				echo '<p class="mrtu-actions">';
				for ( $i = 1; $i <= $pages; $i++ ) {
					$url = Admin::url(
						self::SLUG,
						array(
							'type'    => $type,
							'missing' => $missing ? 1 : 0,
							'paged'   => $i,
						)
					);
					echo '<a class="button' . ( $i === $paged ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $i === $paged ? ' aria-current="page"' : '' ) . '>' . esc_html( number_format_i18n( $i ) ) . '</a>';
				}
				echo '</p>';
			}
		}
		Admin::card_close();

		if ( current_user_can( 'manage_options' ) ) {
			Admin::card_open( __( 'Maintenance', 'mr-tu-multilang-seo' ), __( 'Rebuilds every translation map from the saved relationships. Safe to run at any time.', 'mr-tu-multilang-seo' ) );
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::REBUILD, '_mrtu_nonce' );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::REBUILD ) . '">';
			echo '<button type="submit" class="button">' . esc_html__( 'Rebuild translation maps', 'mr-tu-multilang-seo' ) . '</button></form>';
			Admin::card_close();
		}
		Admin::page_close();
	}

	/**
	 * Confirmation step for creating a translation (copy content or start empty).
	 *
	 * @param int    $post_id Source post id.
	 * @param string $lang    Target language.
	 */
	private static function render_create( $post_id, $lang ) {
		$post = get_post( $post_id );
		if ( ! $post || ! Languages::exists( $lang ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'This translation cannot be created.', 'mr-tu-multilang-seo' ), 403 );
		}
		$existing = Translations::get_post_translation( $post_id, $lang, false );
		$name     = Languages::admin_name( $lang );

		/* translators: %s: language name. */
		Admin::page_open( sprintf( __( 'Create the %s version', 'mr-tu-multilang-seo' ), $name ), __( 'A new draft is linked to the original. Nothing is published automatically.', 'mr-tu-multilang-seo' ) );
		/* translators: 1: post title, 2: language name. */
		Admin::card_open( sprintf( __( 'Original: “%1$s” (%2$s)', 'mr-tu-multilang-seo' ), get_the_title( $post ), Languages::admin_name( Translations::get_post_language( $post ) ) ) );

		if ( $existing ) {
			echo '<p>' . esc_html__( 'This language version already exists.', 'mr-tu-multilang-seo' ) . ' <a class="button" href="' . esc_url( (string) get_edit_post_link( $existing, 'raw' ) ) . '">' . esc_html__( 'Edit it', 'mr-tu-multilang-seo' ) . '</a></p>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( self::CREATE . '_' . $post_id . '_' . $lang, '_mrtu_nonce' );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::CREATE ) . '">';
			echo '<input type="hidden" name="post" value="' . esc_attr( (string) $post_id ) . '">';
			echo '<input type="hidden" name="lang" value="' . esc_attr( $lang ) . '">';
			echo '<p class="mrtu-actions">';
			echo '<button type="submit" name="copy" value="1" class="button button-primary mrtu-button">' . esc_html__( 'Copy the original content', 'mr-tu-multilang-seo' ) . '</button>';
			echo '<button type="submit" name="copy" value="0" class="button mrtu-button">' . esc_html__( 'Start with an empty draft', 'mr-tu-multilang-seo' ) . '</button>';
			echo '</p>';
			echo '<p class="mrtu-meta">' . esc_html__( 'A copy keeps the layout so you only replace the text. A draft that is still identical to another language can never be published.', 'mr-tu-multilang-seo' ) . '</p>';
			echo '</form>';
		}
		Admin::card_close();
		echo '<p><a href="' . esc_url( Admin::url( self::SLUG, array( 'type' => $post->post_type ) ) ) . '">' . esc_html__( '← Back to translations', 'mr-tu-multilang-seo' ) . '</a></p>';
		Admin::page_close();
	}

	/**
	 * Form linking existing posts as translations of a post.
	 *
	 * @param int $post_id Post id.
	 */
	private static function render_link( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! Translations::is_translatable_post_type( $post->post_type ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
		}
		$map  = Translations::get_post_translations( $post );
		$lang = Translations::get_post_language( $post );

		Admin::page_open( __( 'Link existing translations', 'mr-tu-multilang-seo' ), __( 'Choose which existing content is the same page in another language. A post can belong to only one group.', 'mr-tu-multilang-seo' ) );
		/* translators: 1: post title, 2: language name. */
		Admin::card_open( sprintf( __( '“%1$s” (%2$s)', 'mr-tu-multilang-seo' ), get_the_title( $post ), Languages::admin_name( $lang ) ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="post" value="' . esc_attr( (string) $post_id ) . '">';

		foreach ( Languages::all( false ) as $code => $language ) {
			if ( $code === $lang ) {
				continue;
			}
			$candidates = get_posts(
				array(
					'post_type'        => $post->post_type,
					'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private' ),
					'perm'             => 'readable',
					'numberposts'      => 300,
					'orderby'          => 'title',
					'order'            => 'ASC',
					'suppress_filters' => true,
					Router::QUERY_VAR  => $code,
				)
			);
			$current    = isset( $map[ $code ] ) ? (int) $map[ $code ] : 0;
			$field_id   = 'mrtu-link-' . $code;
			echo '<div class="mrtu-field"><label class="mrtu-field__label" for="' . esc_attr( $field_id ) . '">' . esc_html( Languages::admin_name( $code ) . ' (' . strtoupper( $code ) . ')' ) . '</label>';
			echo '<select id="' . esc_attr( $field_id ) . '" name="translation[' . esc_attr( $code ) . ']"><option value="0">' . esc_html__( '— None —', 'mr-tu-multilang-seo' ) . '</option>';
			foreach ( $candidates as $candidate ) {
				$status = get_post_status_object( $candidate->post_status );
				$label  = ( '' !== $candidate->post_title ? $candidate->post_title : __( '(no title)', 'mr-tu-multilang-seo' ) ) . ' — ' . ( $status ? $status->label : $candidate->post_status ) . ' #' . $candidate->ID;
				echo '<option value="' . esc_attr( (string) $candidate->ID ) . '"' . selected( $candidate->ID, $current, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></div>';
		}
		// The nonce is bound to the post id, so the save bar is built here rather than with Admin::save_bar().
		wp_nonce_field( self::LINK . '_' . $post_id, '_mrtu_nonce' );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::LINK ) . '">';
		echo '<div class="mrtu-savebar"><button type="submit" class="button button-primary mrtu-button">' . esc_html__( 'Save links', 'mr-tu-multilang-seo' ) . '</button></div>';
		echo '</form>';
		Admin::card_close();
		echo '<p><a href="' . esc_url( (string) get_edit_post_link( $post_id, 'raw' ) ) . '">' . esc_html__( '← Back to the editor', 'mr-tu-multilang-seo' ) . '</a></p>';
		Admin::page_close();
	}

	/*
	 * admin-post handlers
	 */

	/**
	 * Creates a translation (GET from the meta box or POST from the confirmation screen).
	 */
	public static function handle_create() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below with check_admin_referer().
		$post_id = isset( $_REQUEST['post'] ) ? absint( wp_unslash( $_REQUEST['post'] ) ) : 0;
		$lang    = isset( $_REQUEST['lang'] ) ? sanitize_key( wp_unslash( $_REQUEST['lang'] ) ) : '';
		$copy    = ! empty( $_REQUEST['copy'] );
		// phpcs:enable
		check_admin_referer( self::CREATE . '_' . $post_id . '_' . $lang, '_mrtu_nonce' );

		$post = get_post( $post_id );
		$type = $post ? get_post_type_object( $post->post_type ) : null;
		if ( ! $post || ! $type || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( $type->cap->create_posts ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
		}

		$result = Translations::create_translation( $post_id, $lang, $copy );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && ! empty( $data['post_id'] ) ) {
				Admin::redirect_with_notice( (string) get_edit_post_link( (int) $data['post_id'], 'raw' ), $result->get_error_message(), 'info' );
			}
			Admin::redirect_with_notice( (string) get_edit_post_link( $post_id, 'raw' ), $result->get_error_message(), 'error' );
		}
		$message = $copy
			/* translators: %s: language name. */
			? __( '%s draft created with a copy of the original. Translate the title and the text, then publish.', 'mr-tu-multilang-seo' )
			/* translators: %s: language name. */
			: __( 'Empty %s draft created. Write the title and the text, then publish.', 'mr-tu-multilang-seo' );
		Admin::redirect_with_notice( (string) get_edit_post_link( $result, 'raw' ), sprintf( $message, Languages::admin_name( $lang ) ) );
	}

	/**
	 * Saves the "link existing translations" form.
	 */
	public static function handle_link() {
		$post_id = isset( $_POST['post'] ) ? absint( wp_unslash( $_POST['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce action includes the id; verified next.
		Admin::verify_request( self::LINK . '_' . $post_id, 'edit_posts' );
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
		}
		$lang    = Translations::get_post_language( $post );
		$desired = array( $lang => $post_id );
		$posted  = isset( $_POST['translation'] ) && is_array( $_POST['translation'] ) ? wp_unslash( $_POST['translation'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- nonce verified by Admin::verify_request() above; keys/values sanitized below.
		foreach ( $posted as $code => $id ) {
			$code = sanitize_key( $code );
			$id   = absint( $id );
			if ( $id && $code !== $lang && Languages::exists( $code ) ) {
				if ( ! current_user_can( 'edit_post', $id ) || get_post_type( $id ) !== $post->post_type ) {
					wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
				}
				$desired[ $code ] = $id;
			}
		}

		// Remove versions that were deselected, and detach chosen posts from their previous groups.
		$group = Translations::get_post_group( $post_id );
		foreach ( Translations::get_post_translations( $post_id ) as $code => $id ) {
			if ( $id !== $post_id && ( ! isset( $desired[ $code ] ) || $desired[ $code ] !== $id ) ) {
				Translations::unlink_translation( $id );
			}
		}
		foreach ( $desired as $id ) {
			$other = Translations::get_post_group( $id );
			if ( $id !== $post_id && $other && $other !== $group ) {
				Translations::unlink_translation( $id );
			}
		}

		$back = Admin::url(
			self::SLUG,
			array(
				'view' => 'link',
				'post' => $post_id,
			)
		);
		if ( count( $desired ) > 1 && ! Translations::link_translations( $desired ) ) {
			Admin::redirect_with_notice( $back, __( 'The links could not be saved: each language can have only one version.', 'mr-tu-multilang-seo' ), 'error' );
		}
		Admin::redirect_with_notice( $back, __( 'Translation links saved.', 'mr-tu-multilang-seo' ) );
	}

	/**
	 * Removes a post from its translation group.
	 */
	public static function handle_unlink() {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified next.
		check_admin_referer( self::UNLINK . '_' . $post_id, '_mrtu_nonce' );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'mr-tu-multilang-seo' ), 403 );
		}
		Translations::unlink_translation( $post_id );
		Admin::redirect_with_notice( (string) get_edit_post_link( $post_id, 'raw' ), __( 'Removed from its translation group.', 'mr-tu-multilang-seo' ) );
	}

	/**
	 * Rebuilds every translation map.
	 */
	public static function handle_rebuild() {
		Admin::verify_request( self::REBUILD, 'manage_options' );
		$count = Translations::rebuild_all();
		/* translators: %s: number of translation groups. */
		Admin::redirect_with_notice( Admin::url( self::SLUG ), sprintf( _n( '%s translation map rebuilt.', '%s translation maps rebuilt.', $count, 'mr-tu-multilang-seo' ), number_format_i18n( $count ) ) );
	}

	/*
	 * Terms (category, post_tag)
	 */

	/**
	 * Language field on the "Add new term" form (prefilled when creating a translation).
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public static function term_add_fields( $taxonomy ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- prefill parameters only.
		$lang = isset( $_GET[ Router::QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ Router::QUERY_VAR ] ) ) : Languages::default_code();
		$from = isset( $_GET['mrtu_from'] ) ? absint( wp_unslash( $_GET['mrtu_from'] ) ) : 0;
		// phpcs:enable
		wp_nonce_field( self::TERM_NONCE, '_mrtu_term_nonce' );
		echo '<div class="form-field term-mrtu-lang-wrap"><label for="mrtu-term-lang">' . esc_html__( 'Language', 'mr-tu-multilang-seo' ) . '</label>';
		echo self::language_select( 'mrtu_term_lang', 'mrtu-term-lang', Languages::exists( $lang ) ? $lang : Languages::default_code() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in language_select().
		$source = $from ? get_term( $from, $taxonomy ) : null;
		if ( $source instanceof \WP_Term ) {
			echo '<input type="hidden" name="mrtu_term_from" value="' . esc_attr( (string) $source->term_id ) . '">';
			/* translators: %s: term name. */
			echo '<p>' . esc_html( sprintf( __( 'Will be linked as a translation of “%s”.', 'mr-tu-multilang-seo' ), $source->name ) ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Categories and tags belong to one language; their archive is only shown in that language.', 'mr-tu-multilang-seo' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Language + translations rows on the "Edit term" form.
	 *
	 * @param \WP_Term $term     Term.
	 * @param string   $taxonomy Taxonomy.
	 */
	public static function term_edit_fields( $term, $taxonomy ) {
		$lang = Translations::get_term_language( $term );
		$map  = Translations::get_term_translations( $term );
		wp_nonce_field( self::TERM_NONCE, '_mrtu_term_nonce' );

		echo '<tr class="form-field term-mrtu-lang-wrap"><th scope="row"><label for="mrtu-term-lang">' . esc_html__( 'Language', 'mr-tu-multilang-seo' ) . '</label></th><td>';
		echo self::language_select( 'mrtu_term_lang', 'mrtu-term-lang', $lang ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in language_select().
		echo '</td></tr>';

		echo '<tr class="form-field term-mrtu-translations-wrap"><th scope="row">' . esc_html__( 'Translations', 'mr-tu-multilang-seo' ) . '</th><td>';
		foreach ( Languages::all( false ) as $code => $language ) {
			if ( $code === $lang ) {
				continue;
			}
			$field_id = 'mrtu-term-tr-' . $code;
			$terms    = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 300,
					'exclude'    => array( $term->term_id ),
					'meta_query' => Languages::default_code() === $code // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin form, small table.
						? array(
							'relation' => 'OR',
							array(
								'key'     => Translations::META_TERM_LANG,
								'compare' => 'NOT EXISTS',
							),
							array(
								'key'   => Translations::META_TERM_LANG,
								'value' => $code,
							),
						)
						: array(
							array(
								'key'   => Translations::META_TERM_LANG,
								'value' => $code,
							),
						),
				)
			);
			$current  = isset( $map[ $code ] ) ? (int) $map[ $code ] : 0;
			echo '<p><label for="' . esc_attr( $field_id ) . '">' . esc_html( Languages::admin_name( $code ) . ' (' . strtoupper( $code ) . ')' ) . '</label><br>';
			echo '<select id="' . esc_attr( $field_id ) . '" name="mrtu_term_tr[' . esc_attr( $code ) . ']"><option value="0">' . esc_html__( '— None —', 'mr-tu-multilang-seo' ) . '</option>';
			foreach ( is_array( $terms ) ? $terms : array() as $candidate ) {
				echo '<option value="' . esc_attr( (string) $candidate->term_id ) . '"' . selected( $candidate->term_id, $current, false ) . '>' . esc_html( $candidate->name ) . '</option>';
			}
			echo '</select> ';
			if ( ! $current ) {
				$add = add_query_arg(
					array(
						'taxonomy'        => $taxonomy,
						Router::QUERY_VAR => $code,
						'mrtu_from'       => $term->term_id,
					),
					admin_url( 'edit-tags.php' )
				);
				/* translators: %s: language name. */
				echo '<a href="' . esc_url( $add ) . '" aria-label="' . esc_attr( sprintf( __( 'Add the %s translation', 'mr-tu-multilang-seo' ), Languages::admin_name( $code ) ) ) . '">' . esc_html__( '+ Add new', 'mr-tu-multilang-seo' ) . '</a>';
			}
			echo '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Whether a term form submission carries a valid nonce and the user may edit the term.
	 *
	 * @param int $term_id Term id.
	 * @return bool
	 */
	private static function term_request_ok( $term_id ) {
		if ( ! isset( $_POST['_mrtu_term_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_mrtu_term_nonce'] ) ), self::TERM_NONCE ) ) {
			return false;
		}
		return current_user_can( 'edit_term', $term_id );
	}

	/**
	 * Saves language (and the source link) of a new term.
	 *
	 * @param int $term_id Term id.
	 */
	public static function save_new_term( $term_id ) {
		if ( ! self::term_request_ok( $term_id ) ) {
			return;
		}
		$lang = isset( $_POST['mrtu_term_lang'] ) ? sanitize_key( wp_unslash( $_POST['mrtu_term_lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in term_request_ok().
		$from = isset( $_POST['mrtu_term_from'] ) ? absint( wp_unslash( $_POST['mrtu_term_from'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in term_request_ok().
		if ( ! Languages::exists( $lang ) ) {
			return;
		}
		Translations::set_term_language( $term_id, $lang );
		if ( $from && current_user_can( 'edit_term', $from ) ) {
			$map = Translations::get_term_translations( $from );
			if ( $map && ! isset( $map[ $lang ] ) ) {
				$map[ $lang ] = (int) $term_id;
				Translations::link_term_translations( $map );
			}
		}
	}

	/**
	 * Saves language + translation links of an edited term.
	 *
	 * @param int $term_id Term id.
	 */
	public static function save_edited_term( $term_id ) {
		if ( ! self::term_request_ok( $term_id ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in term_request_ok().
		$lang   = isset( $_POST['mrtu_term_lang'] ) ? sanitize_key( wp_unslash( $_POST['mrtu_term_lang'] ) ) : '';
		$posted = isset( $_POST['mrtu_term_tr'] ) && is_array( $_POST['mrtu_term_tr'] ) ? wp_unslash( $_POST['mrtu_term_tr'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		// phpcs:enable
		if ( Languages::exists( $lang ) ) {
			Translations::set_term_language( $term_id, $lang );
		}
		$lang    = Translations::get_term_language( $term_id );
		$desired = array( $lang => (int) $term_id );
		foreach ( $posted as $code => $id ) {
			$code = sanitize_key( $code );
			$id   = absint( $id );
			if ( $id && $code !== $lang && Languages::exists( $code ) && current_user_can( 'edit_term', $id ) ) {
				$desired[ $code ] = $id;
			}
		}
		foreach ( Translations::get_term_translations( $term_id ) as $code => $id ) {
			if ( (int) $id !== (int) $term_id && ( ! isset( $desired[ $code ] ) || $desired[ $code ] !== (int) $id ) ) {
				Translations::unlink_term_translation( (int) $id );
			}
		}
		$own = (string) get_term_meta( $term_id, Translations::META_TERM_GROUP, true );
		foreach ( $desired as $id ) {
			$other = (string) get_term_meta( $id, Translations::META_TERM_GROUP, true );
			if ( (int) $id !== (int) $term_id && '' !== $other && $other !== $own ) {
				Translations::unlink_term_translation( $id );
			}
		}
		if ( count( $desired ) > 1 ) {
			Translations::link_term_translations( $desired );
		}
	}

	/**
	 * Adds a language column to term lists.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function term_columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'name' === $key ) {
				$out['mrtu_lang'] = __( 'Language', 'mr-tu-multilang-seo' );
			}
		}
		return $out;
	}

	/**
	 * Renders the language column of term lists ("EN · vi de").
	 *
	 * @param string $content Column content.
	 * @param string $column  Column key.
	 * @param int    $term_id Term id.
	 * @return string
	 */
	public static function term_column( $content, $column, $term_id ) {
		if ( 'mrtu_lang' !== $column ) {
			return $content;
		}
		$lang   = Translations::get_term_language( $term_id );
		$others = array();
		foreach ( Translations::get_term_translations( $term_id ) as $code => $id ) {
			if ( $code !== $lang ) {
				$link     = get_edit_term_link( (int) $id );
				$others[] = $link ? '<a href="' . esc_url( $link ) . '" title="' . esc_attr( Languages::admin_name( $code ) ) . '">' . esc_html( $code ) . '</a>' : esc_html( $code );
			}
		}
		$out = '<strong title="' . esc_attr( Languages::admin_name( $lang ) ) . '">' . esc_html( strtoupper( $lang ) ) . '</strong>';
		if ( $others ) {
			$out .= ' · ' . implode( ' ', $others );
		}
		return $out;
	}
}
