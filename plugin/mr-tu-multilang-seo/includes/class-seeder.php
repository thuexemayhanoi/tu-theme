<?php
/**
 * Content seeder (contract §2.13, §6 item 11).
 *
 * Creates the core pages, blog posts and categories of every language from
 * `{source}/{lang}/pages.json` + `categories.json` and the referenced block-markup files.
 * Source: the `MRTU_SEED_DIR` constant (dev env → repository `content/`), else the `seed/`
 * folder bundled into the plugin ZIP.
 *
 * Idempotent: posts are found by `_mrtu_seed_id` + language (pages fall back to role + language),
 * categories by `_mrtu_seed_id` term meta (fallback: slug) + language. Existing posts are skipped
 * unless `update` is requested, and even then a post is only rewritten while its content hash still
 * equals `_mrtu_seed_hash` — owner edits are never overwritten (reported as "customized").
 * Reading settings change only with `set_front`. Never runs on activation.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Seeder.
 */
final class Seeder {

	const META_SEED_ID   = '_mrtu_seed_id';
	const META_SEED_HASH = '_mrtu_seed_hash';
	const META_NAV_LABEL = '_mrtu_nav_label';
	const META_SEO_TITLE = '_mrtu_seo_title';
	const META_SEO_DESC  = '_mrtu_seo_desc';
	const META_FOCUS_KW  = '_mrtu_focus_kw';

	const REPORT_OPTION = 'mrtu_seed_last_report';

	const STATUSES = array( 'publish', 'draft', 'pending', 'private' );

	const ACTIONS = array( 'created', 'updated', 'skipped', 'customized', 'errors' );

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
	}

	/**
	 * Registers `_mrtu_seed_id` / `_mrtu_seed_hash` for the translatable post types (contract §2.2).
	 */
	public static function register_meta() {
		foreach ( Translations::post_types() as $type ) {
			if ( ! post_type_exists( $type ) ) {
				continue;
			}
			foreach ( array( self::META_SEED_ID, self::META_SEED_HASH ) as $key ) {
				if ( registered_meta_key_exists( 'post', $key, $type ) ) {
					continue;
				}
				register_post_meta(
					$type,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'default'           => '',
						'show_in_rest'      => true,
						'sanitize_callback' => 'sanitize_text_field',
						'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
					)
				);
			}
		}
	}

	/**
	 * Meta auth callback.
	 *
	 * @param bool   $allowed   Whether allowed so far.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Post id.
	 * @return bool
	 */
	public static function can_edit_meta( $allowed, $meta_key, $object_id ) {
		return current_user_can( 'edit_post', (int) $object_id );
	}

	/*
	 * Source
	 * ------------------------------------------------------------------
	 */

	/**
	 * Seed source directory (no trailing slash): MRTU_SEED_DIR or the bundled `seed/` folder.
	 *
	 * @return string
	 */
	public static function source_dir() {
		$dir = defined( 'MRTU_SEED_DIR' ) && is_string( MRTU_SEED_DIR ) && '' !== MRTU_SEED_DIR ? MRTU_SEED_DIR : MRTU_ML_DIR . 'seed';
		/**
		 * Filters the seed source directory.
		 *
		 * @param string $dir Directory holding {lang}/pages.json.
		 */
		return untrailingslashit( (string) apply_filters( 'mrtu_seed_dir', $dir ) );
	}

	/**
	 * Languages that have seed content in a source directory.
	 *
	 * @param string|null $dir Source directory (null = source_dir()).
	 * @return array code => [ 'pages' => int, 'posts' => int, 'categories' => int ]
	 */
	public static function available_languages( $dir = null ) {
		$dir = null === $dir ? self::source_dir() : untrailingslashit( (string) $dir );
		$out = array();
		foreach ( Languages::codes( false ) as $code ) {
			$error   = '';
			$entries = self::read_json( $dir . '/' . $code . '/pages.json', $error );
			if ( null === $entries ) {
				continue;
			}
			$counts = array(
				'pages'      => 0,
				'posts'      => 0,
				'categories' => 0,
			);
			foreach ( $entries as $entry ) {
				$type = isset( $entry['type'] ) && 'post' === $entry['type'] ? 'posts' : 'pages';
				++$counts[ $type ];
			}
			$cats = self::read_json( $dir . '/' . $code . '/categories.json', $error );
			if ( is_array( $cats ) ) {
				$counts['categories'] = count( $cats );
			}
			$out[ $code ] = $counts;
		}
		return $out;
	}

	/**
	 * Decodes a JSON file that must hold a list of objects.
	 *
	 * @param string $file  Path.
	 * @param string $error Receives an error message ('' when fine).
	 * @return array|null Null when missing or invalid.
	 */
	private static function read_json( $file, &$error ) {
		$error = '';
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			/* translators: %s: file name. */
			$error = sprintf( __( 'File not found: %s', 'mr-tu-multilang-seo' ), basename( $file ) );
			return null;
		}
		$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled seed file.
		if ( ! is_string( $raw ) ) {
			/* translators: %s: file name. */
			$error = sprintf( __( 'File not readable: %s', 'mr-tu-multilang-seo' ), basename( $file ) );
			return null;
		}
		if ( 0 === strpos( $raw, "\xEF\xBB\xBF" ) ) {
			$raw = substr( $raw, 3 );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			/* translators: 1: file name, 2: JSON error message. */
			$error = sprintf( __( '%1$s is not valid JSON (%2$s).', 'mr-tu-multilang-seo' ), basename( $file ), json_last_error_msg() );
			return null;
		}
		$list = array();
		foreach ( $data as $row ) {
			if ( is_array( $row ) ) {
				$list[] = $row;
			}
		}
		return $list;
	}

	/*
	 * Run
	 * ------------------------------------------------------------------
	 */

	/**
	 * Seeds the content.
	 *
	 * @param array $args {
	 *     Optional.
	 *
	 *     @type bool            $dry_run   Compute the report without writing anything.
	 *     @type bool            $update    Rewrite seeded posts whose content is unchanged since seeding.
	 *     @type bool            $set_front Apply the Reading settings (front page / posts page) + timezone + date format.
	 *     @type string|string[] $lang      Language code(s); default: every registered language.
	 *     @type string          $source    Source directory override (tests / CLI).
	 * }
	 * @return array Report (see build_report()).
	 */
	public static function run( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'dry_run'   => false,
				'update'    => false,
				'set_front' => false,
				'lang'      => array(),
				'source'    => '',
			)
		);
		$dry  = (bool) $args['dry_run'];
		$dir  = '' !== (string) $args['source'] ? untrailingslashit( (string) $args['source'] ) : self::source_dir();

		$report = self::build_report( $dir, $dry, (bool) $args['update'], (bool) $args['set_front'] );

		$codes = self::requested_languages( $args['lang'], $report );
		if ( ! $codes ) {
			return self::finish( $report, $dry );
		}

		if ( ! $dry ) {
			Translations::ensure_language_terms();
		}
		$author  = self::author_id();
		$groups  = array(); // seed_id => [ lang => post_id ].
		$types   = array(); // seed_id => post type.
		$roles   = array(); // seed_id => role.
		$cats    = array(); // seed_id => [ lang => term_id ].
		$by_slug = array(); // lang => [ category slug => term_id ].

		foreach ( $codes as $code ) {
			$lang_dir = $dir . '/' . $code;
			$error    = '';
			$entries  = self::read_json( $lang_dir . '/pages.json', $error );
			$lang_rep = &$report['languages'][ $code ];
			if ( null === $entries ) {
				$lang_rep['missing']  = true;
				$lang_rep['message']  = $error;
				$report['messages'][] = sprintf(
					/* translators: 1: language code, 2: reason. */
					__( '%1$s: no seed content (%2$s). Nothing was changed for this language.', 'mr-tu-multilang-seo' ),
					strtoupper( $code ),
					$error
				);
				unset( $lang_rep );
				continue;
			}

			// Categories first: posts are assigned to them.
			$by_slug[ $code ] = array();
			$cat_entries      = self::read_json( $lang_dir . '/categories.json', $error );
			foreach ( (array) $cat_entries as $cat ) {
				$result = self::seed_category( $cat, $code, $dry );
				self::record( $lang_rep, $result );
				if ( ! empty( $result['term_id'] ) && ! empty( $result['slug'] ) ) {
					$by_slug[ $code ][ $result['slug'] ] = (int) $result['term_id'];
					if ( ! empty( $result['seed_id'] ) ) {
						$cats[ $result['seed_id'] ][ $code ] = (int) $result['term_id'];
					}
				}
			}

			foreach ( $entries as $raw ) {
				$entry = self::normalize_entry( $raw );
				if ( is_wp_error( $entry ) ) {
					self::record( $lang_rep, self::item( 'errors', 'page', isset( $raw['seed_id'] ) ? (string) $raw['seed_id'] : '', 0, isset( $raw['title'] ) ? (string) $raw['title'] : '', $entry->get_error_message() ) );
					continue;
				}
				$term_id = 0;
				if ( 'post' === $entry['type'] && '' !== $entry['category'] ) {
					$term_id = isset( $by_slug[ $code ][ $entry['category'] ] ) ? $by_slug[ $code ][ $entry['category'] ] : self::find_category_by_slug( $entry['category'], $code );
				}
				$result = self::seed_entry( $entry, $code, $lang_dir, $author, $term_id, $dry, (bool) $args['update'] );
				self::record( $lang_rep, $result );
				if ( ! empty( $result['post_id'] ) ) {
					$groups[ $entry['seed_id'] ][ $code ] = (int) $result['post_id'];
					$types[ $entry['seed_id'] ]           = $entry['type'];
					if ( 'page' === $entry['type'] && '' !== $entry['role'] ) {
						$roles[ $entry['seed_id'] ] = $entry['role'];
					}
				}
			}
			unset( $lang_rep );
		}

		if ( ! $dry ) {
			self::link_categories( $cats, $report );
			self::link_groups( $groups, $types, $roles, $codes, $report );
			self::apply_roles( $groups, $roles, $report );
			if ( $args['set_front'] ) {
				self::set_front( $report );
			}
		} elseif ( $args['set_front'] ) {
			$report['front']['messages'][] = __( 'Dry run: the Reading settings were not changed.', 'mr-tu-multilang-seo' );
		}

		return self::finish( $report, $dry );
	}

	/**
	 * Empty report skeleton.
	 *
	 * @param string $dir       Source directory.
	 * @param bool   $dry       Dry run.
	 * @param bool   $update    Update mode.
	 * @param bool   $set_front Set-front mode.
	 * @return array
	 */
	private static function build_report( $dir, $dry, $update, $set_front ) {
		$languages = array();
		foreach ( Languages::codes( false ) as $code ) {
			$languages[ $code ] = array_merge(
				array_fill_keys( self::ACTIONS, 0 ),
				array(
					'requested' => false,
					'missing'   => false,
					'message'   => '',
					'items'     => array(),
				)
			);
		}
		return array(
			'dry_run'   => $dry,
			'update'    => $update,
			'set_front' => $set_front,
			'source'    => $dir,
			'started'   => time(),
			'finished'  => 0,
			'user_id'   => get_current_user_id(),
			'languages' => $languages,
			'totals'    => array_fill_keys( self::ACTIONS, 0 ),
			'front'     => array(
				'applied'        => false,
				'page_on_front'  => 0,
				'page_for_posts' => 0,
				'messages'       => array(),
			),
			'messages'  => array(),
		);
	}

	/**
	 * Validates the requested language codes.
	 *
	 * @param string|string[] $lang   Requested codes (empty = all registered languages).
	 * @param array           $report Report (by reference, receives errors).
	 * @return string[]
	 */
	private static function requested_languages( $lang, array &$report ) {
		$requested = array();
		foreach ( (array) $lang as $code ) {
			foreach ( explode( ',', (string) $code ) as $part ) {
				$part = sanitize_key( trim( $part ) );
				if ( '' !== $part ) {
					$requested[] = $part;
				}
			}
		}
		$requested = array_values( array_unique( $requested ) );
		if ( ! $requested ) {
			$requested = Languages::codes( false );
		}
		$codes = array();
		foreach ( $requested as $code ) {
			if ( ! Languages::exists( $code ) ) {
				/* translators: %s: language code. */
				$report['messages'][] = sprintf( __( 'Unknown language code “%s” was ignored.', 'mr-tu-multilang-seo' ), $code );
				continue;
			}
			$codes[]                                   = $code;
			$report['languages'][ $code ]['requested'] = true;
		}
		// Registry order keeps the default language first.
		return array_values( array_intersect( Languages::codes( false ), $codes ) );
	}

	/**
	 * Totals, timestamps, caches and the stored report.
	 *
	 * @param array $report Report.
	 * @param bool  $dry    Dry run.
	 * @return array
	 */
	private static function finish( array $report, $dry ) {
		foreach ( $report['languages'] as $lang_rep ) {
			foreach ( self::ACTIONS as $action ) {
				$report['totals'][ $action ] += (int) $lang_rep[ $action ];
			}
		}
		$report['finished'] = time();

		if ( ! $dry && ( $report['totals']['created'] || $report['totals']['updated'] || $report['front']['applied'] ) ) {
			flush_rewrite_rules();
			if ( function_exists( 'mrtu_sitemap_flush' ) ) {
				mrtu_sitemap_flush();
			}
		}
		update_option( self::REPORT_OPTION, $report, false );

		if ( ! $dry ) {
			/**
			 * Fires after a seeding run (never on dry runs).
			 *
			 * @param array $report Seeding report.
			 */
			do_action( 'mrtu_seeded', $report );
		}
		return $report;
	}

	/**
	 * Last stored report (dry runs included, flagged).
	 *
	 * @return array|null
	 */
	public static function last_report() {
		$report = get_option( self::REPORT_OPTION, null );
		return is_array( $report ) && isset( $report['languages'], $report['totals'] ) ? $report : null;
	}

	/**
	 * Adds an item to a language report and bumps its counter.
	 *
	 * @param array $lang_rep Language report (by reference).
	 * @param array $item     Item from item().
	 */
	private static function record( array &$lang_rep, array $item ) {
		$action = in_array( $item['action'], self::ACTIONS, true ) ? $item['action'] : 'errors';
		++$lang_rep[ $action ];
		$lang_rep['items'][] = array(
			'seed_id' => $item['seed_id'],
			'type'    => $item['type'],
			'action'  => $action,
			'id'      => (int) ( isset( $item['post_id'] ) ? $item['post_id'] : ( isset( $item['term_id'] ) ? $item['term_id'] : 0 ) ),
			'title'   => $item['title'],
			'message' => $item['message'],
		);
	}

	/**
	 * Report item.
	 *
	 * @param string $action  created|updated|skipped|customized|errors.
	 * @param string $type    page|post|category.
	 * @param string $seed_id Seed id.
	 * @param int    $id      Post or term id.
	 * @param string $title   Title.
	 * @param string $message Message.
	 * @return array
	 */
	private static function item( $action, $type, $seed_id, $id, $title, $message = '' ) {
		$item = array(
			'action'  => $action,
			'type'    => $type,
			'seed_id' => (string) $seed_id,
			'title'   => (string) $title,
			'message' => (string) $message,
		);
		if ( 'category' === $type ) {
			$item['term_id'] = (int) $id;
		} else {
			$item['post_id'] = (int) $id;
		}
		return $item;
	}

	/*
	 * Entries
	 * ------------------------------------------------------------------
	 */

	/**
	 * Validates one pages.json entry.
	 *
	 * @param array $raw Raw entry.
	 * @return array|\WP_Error Normalized entry.
	 */
	public static function normalize_entry( array $raw ) {
		$seed_id = isset( $raw['seed_id'] ) && is_scalar( $raw['seed_id'] ) ? trim( (string) $raw['seed_id'] ) : '';
		$type    = isset( $raw['type'] ) && 'post' === $raw['type'] ? 'post' : 'page';
		$title   = isset( $raw['title'] ) && is_scalar( $raw['title'] ) ? sanitize_text_field( (string) $raw['title'] ) : '';
		$file    = isset( $raw['file'] ) && is_scalar( $raw['file'] ) ? basename( trim( (string) $raw['file'] ) ) : '';
		$role    = isset( $raw['role'] ) && is_scalar( $raw['role'] ) ? sanitize_key( (string) $raw['role'] ) : '';
		if ( '' === $seed_id || ! preg_match( '/^[a-z0-9:_.-]{1,80}$/i', $seed_id ) ) {
			return new \WP_Error( 'mrtu_seed_entry', __( 'Entry without a valid seed_id.', 'mr-tu-multilang-seo' ) );
		}
		if ( '' === $title ) {
			/* translators: %s: seed id. */
			return new \WP_Error( 'mrtu_seed_entry', sprintf( __( '%s: the title is missing.', 'mr-tu-multilang-seo' ), $seed_id ) );
		}
		if ( '' === $file || '.html' !== substr( $file, -5 ) ) {
			/* translators: %s: seed id. */
			return new \WP_Error( 'mrtu_seed_entry', sprintf( __( '%s: the content file must be an .html file.', 'mr-tu-multilang-seo' ), $seed_id ) );
		}
		if ( 'page' === $type && '' !== $role && ! Roles::exists( $role ) ) {
			/* translators: 1: seed id, 2: role key. */
			return new \WP_Error( 'mrtu_seed_entry', sprintf( __( '%1$s: unknown page role “%2$s”.', 'mr-tu-multilang-seo' ), $seed_id, $role ) );
		}
		$status = isset( $raw['status'] ) && is_scalar( $raw['status'] ) ? sanitize_key( (string) $raw['status'] ) : 'publish';
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			$status = 'draft';
		}
		$slug = isset( $raw['slug'] ) && is_scalar( $raw['slug'] ) ? sanitize_title( (string) $raw['slug'] ) : '';
		return array(
			'seed_id'    => $seed_id,
			'type'       => $type,
			'role'       => 'page' === $type ? $role : '',
			'slug'       => $slug,
			'title'      => $title,
			'nav_label'  => self::text( $raw, 'nav_label' ),
			'excerpt'    => self::text( $raw, 'excerpt', true ),
			'seo_title'  => self::text( $raw, 'seo_title' ),
			'seo_desc'   => self::text( $raw, 'seo_desc', true ),
			'focus_kw'   => self::text( $raw, 'focus_kw' ),
			'file'       => $file,
			'status'     => $status,
			'menu_order' => isset( $raw['menu_order'] ) && is_numeric( $raw['menu_order'] ) ? (int) $raw['menu_order'] : 0,
			'category'   => 'post' === $type && isset( $raw['category'] ) && is_scalar( $raw['category'] ) ? sanitize_title( (string) $raw['category'] ) : '',
		);
	}

	/**
	 * Sanitized text field of an entry.
	 *
	 * @param array  $raw       Entry.
	 * @param string $key       Key.
	 * @param bool   $multiline Allow line breaks.
	 * @return string
	 */
	private static function text( array $raw, $key, $multiline = false ) {
		if ( ! isset( $raw[ $key ] ) || ! is_scalar( $raw[ $key ] ) ) {
			return '';
		}
		return $multiline ? sanitize_textarea_field( (string) $raw[ $key ] ) : sanitize_text_field( (string) $raw[ $key ] );
	}

	/**
	 * Seeds one page/post entry.
	 *
	 * @param array  $entry    Normalized entry.
	 * @param string $lang     Language code.
	 * @param string $lang_dir Language folder.
	 * @param int    $author   Author id for new posts.
	 * @param int    $term_id  Category term id (posts).
	 * @param bool   $dry      Dry run.
	 * @param bool   $update   Update mode.
	 * @return array Item (+ post_id when known).
	 */
	private static function seed_entry( array $entry, $lang, $lang_dir, $author, $term_id, $dry, $update ) {
		$type = $entry['type'];
		$file = $lang_dir . '/' . $entry['file'];
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			/* translators: %s: file name. */
			return self::item( 'errors', $type, $entry['seed_id'], 0, $entry['title'], sprintf( __( 'Content file missing: %s', 'mr-tu-multilang-seo' ), $entry['file'] ) );
		}
		$content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled seed file.
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			/* translators: %s: file name. */
			return self::item( 'errors', $type, $entry['seed_id'], 0, $entry['title'], sprintf( __( 'Content file is empty: %s', 'mr-tu-multilang-seo' ), $entry['file'] ) );
		}
		$content = str_replace( "\r\n", "\n", $content );

		$existing = self::find_post( $entry['seed_id'], $type, $lang, $entry['role'] );

		if ( ! $existing ) {
			if ( $dry ) {
				return self::item( 'created', $type, $entry['seed_id'], 0, $entry['title'], __( 'Would be created.', 'mr-tu-multilang-seo' ) );
			}
			$post_id = self::insert_post( $entry, $lang, $content, $author );
			if ( is_wp_error( $post_id ) ) {
				return self::item( 'errors', $type, $entry['seed_id'], 0, $entry['title'], $post_id->get_error_message() );
			}
			self::write_meta( $post_id, $entry, true );
			if ( 'post' === $type && $term_id ) {
				wp_set_object_terms( $post_id, array( (int) $term_id ), 'category', false );
			}
			$item = self::item( 'created', $type, $entry['seed_id'], $post_id, $entry['title'] );
			return $item;
		}

		$post = get_post( $existing );
		if ( ! $update ) {
			return self::item( 'skipped', $type, $entry['seed_id'], $existing, $post->post_title, __( 'Already exists (use update to refresh unchanged content).', 'mr-tu-multilang-seo' ) );
		}

		$current_hash = md5( (string) $post->post_content );
		$stored_hash  = (string) get_post_meta( $existing, self::META_SEED_HASH, true );
		$file_hash    = md5( $content );
		$untouched    = '' !== $stored_hash ? $current_hash === $stored_hash : $current_hash === $file_hash;
		if ( ! $untouched ) {
			return self::item( 'customized', $type, $entry['seed_id'], $existing, $post->post_title, __( 'Edited on the website after seeding; left unchanged.', 'mr-tu-multilang-seo' ) );
		}

		$changes = self::diff( $post, $entry, $content, $term_id );
		if ( ! $changes ) {
			if ( ! $dry && '' === $stored_hash ) {
				update_post_meta( $existing, self::META_SEED_HASH, $current_hash );
			}
			return self::item( 'skipped', $type, $entry['seed_id'], $existing, $post->post_title, __( 'Up to date.', 'mr-tu-multilang-seo' ) );
		}
		if ( $dry ) {
			/* translators: %s: comma-separated list of fields. */
			return self::item( 'updated', $type, $entry['seed_id'], $existing, $post->post_title, sprintf( __( 'Would update: %s', 'mr-tu-multilang-seo' ), implode( ', ', $changes ) ) );
		}

		$result = self::update_post( $post, $entry, $content );
		if ( is_wp_error( $result ) ) {
			return self::item( 'errors', $type, $entry['seed_id'], $existing, $post->post_title, $result->get_error_message() );
		}
		self::write_meta( $existing, $entry, true );
		if ( 'post' === $type && $term_id ) {
			wp_set_object_terms( $existing, array( (int) $term_id ), 'category', false );
		}
		/* translators: %s: comma-separated list of fields. */
		return self::item( 'updated', $type, $entry['seed_id'], $existing, $entry['title'], sprintf( __( 'Updated: %s', 'mr-tu-multilang-seo' ), implode( ', ', $changes ) ) );
	}

	/**
	 * Fields that differ between a stored post and its seed entry.
	 *
	 * @param \WP_Post $post    Post.
	 * @param array    $entry   Entry.
	 * @param string   $content File content.
	 * @param int      $term_id Category term id (posts).
	 * @return string[]
	 */
	private static function diff( \WP_Post $post, array $entry, $content, $term_id ) {
		$changes = array();
		if ( (string) $post->post_content !== $content ) {
			$changes[] = 'content';
		}
		if ( (string) $post->post_title !== $entry['title'] ) {
			$changes[] = 'title';
		}
		if ( (string) $post->post_excerpt !== $entry['excerpt'] ) {
			$changes[] = 'excerpt';
		}
		if ( '' !== $entry['slug'] && (string) $post->post_name !== $entry['slug'] ) {
			$changes[] = 'slug';
		}
		if ( (int) $post->menu_order !== (int) $entry['menu_order'] ) {
			$changes[] = 'menu_order';
		}
		if ( (string) $post->post_status !== $entry['status'] ) {
			$changes[] = 'status';
		}
		foreach ( self::meta_map( $entry ) as $key => $value ) {
			if ( (string) get_post_meta( $post->ID, $key, true ) !== (string) $value ) {
				$changes[] = ltrim( $key, '_' );
			}
		}
		if ( 'post' === $post->post_type && $term_id && ! has_category( (int) $term_id, $post ) ) {
			$changes[] = 'category';
		}
		return $changes;
	}

	/**
	 * Seed meta values of an entry.
	 *
	 * @param array $entry Entry.
	 * @return array meta key => value
	 */
	private static function meta_map( array $entry ) {
		return array(
			self::META_SEED_ID   => $entry['seed_id'],
			self::META_NAV_LABEL => $entry['nav_label'],
			self::META_SEO_TITLE => $entry['seo_title'],
			self::META_SEO_DESC  => $entry['seo_desc'],
			self::META_FOCUS_KW  => $entry['focus_kw'],
		);
	}

	/**
	 * Writes seed meta (empty values are removed) and the content hash.
	 *
	 * @param int   $post_id   Post id.
	 * @param array $entry     Entry.
	 * @param bool  $with_hash Store `_mrtu_seed_hash` from the stored content.
	 */
	private static function write_meta( $post_id, array $entry, $with_hash ) {
		foreach ( self::meta_map( $entry ) as $key => $value ) {
			if ( '' === (string) $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
		if ( $with_hash ) {
			update_post_meta( $post_id, self::META_SEED_HASH, md5( (string) get_post_field( 'post_content', $post_id, 'raw' ) ) );
		}
	}

	/**
	 * Inserts a seeded post (language assigned with wp_set_object_terms(), never tax_input — §6 item 11).
	 *
	 * @param array  $entry   Entry.
	 * @param string $lang    Language.
	 * @param string $content Block markup.
	 * @param int    $author  Author id.
	 * @return int|\WP_Error
	 */
	private static function insert_post( array $entry, $lang, $content, $author ) {
		$postarr = array(
			'post_type'      => $entry['type'],
			'post_status'    => $entry['status'],
			'post_title'     => $entry['title'],
			'post_name'      => $entry['slug'],
			'post_content'   => $content,
			'post_excerpt'   => $entry['excerpt'],
			'menu_order'     => $entry['menu_order'],
			'post_author'    => (int) $author,
			'comment_status' => 'page' === $entry['type'] ? 'closed' : get_default_comment_status( $entry['type'] ),
			'ping_status'    => 'closed',
			'mrtu_lang'      => $lang,
		);
		$kses    = self::pause_kses();
		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		self::resume_kses( $kses );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$post_id = (int) $post_id;
		if ( Translations::get_post_language( $post_id ) !== $lang || ! Translations::has_language_term( $post_id ) ) {
			Translations::set_post_language( $post_id, $lang );
		}
		return $post_id;
	}

	/**
	 * Rewrites a seeded post from its entry.
	 *
	 * @param \WP_Post $post    Post.
	 * @param array    $entry   Entry.
	 * @param string   $content Block markup.
	 * @return int|\WP_Error
	 */
	private static function update_post( \WP_Post $post, array $entry, $content ) {
		$postarr = array(
			'ID'           => $post->ID,
			'post_title'   => $entry['title'],
			'post_content' => $content,
			'post_excerpt' => $entry['excerpt'],
			'menu_order'   => $entry['menu_order'],
			'post_status'  => $entry['status'],
		);
		if ( '' !== $entry['slug'] ) {
			$postarr['post_name'] = $entry['slug'];
		}
		$kses   = self::pause_kses();
		$result = wp_update_post( wp_slash( $postarr ), true );
		self::resume_kses( $kses );
		return $result;
	}

	/**
	 * Seed markup is trusted plugin content (bundled files or MRTU_SEED_DIR), so the user-level KSES
	 * filters of a CLI run (no user) or a non-unfiltered_html user are paused around the insert; every
	 * other field is still sanitized by wp_insert_post().
	 *
	 * @return bool Whether KSES was active.
	 */
	private static function pause_kses() {
		if ( ! has_filter( 'content_save_pre', 'wp_filter_post_kses' ) ) {
			return false;
		}
		kses_remove_filters();
		return true;
	}

	/**
	 * Restores the KSES filters.
	 *
	 * @param bool $was_active Whether they were active.
	 */
	private static function resume_kses( $was_active ) {
		if ( $was_active ) {
			kses_init_filters();
		}
	}

	/**
	 * Finds the seeded post of a seed id in a language (any status but trash / auto-draft).
	 *
	 * @param string $seed_id Seed id.
	 * @param string $type    Post type.
	 * @param string $lang    Language.
	 * @param string $role    Page role (fallback lookup).
	 * @return int 0 when none.
	 */
	public static function find_post( $seed_id, $type, $lang, $role = '' ) {
		$ids   = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'any',
				'meta_key'         => self::META_SEED_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- idempotency lookup on a small table.
				'meta_value'       => $seed_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'           => 'ids',
				'numberposts'      => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
				Router::QUERY_VAR  => 'all',
			)
		);
		$found = 0;
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( Translations::get_post_language( $id ) !== $lang ) {
				continue;
			}
			if ( 'publish' === get_post_status( $id ) ) {
				return $id;
			}
			if ( ! $found ) {
				$found = $id;
			}
		}
		if ( $found || 'page' !== $type || '' === $role ) {
			return $found;
		}
		$map = Roles::map();
		if ( empty( $map[ $role ] ) ) {
			return 0;
		}
		$members = Translations::group_map( (int) $map[ $role ] );
		return isset( $members[ $lang ] ) && get_post( $members[ $lang ] ) ? (int) $members[ $lang ] : 0;
	}

	/*
	 * Categories
	 * ------------------------------------------------------------------
	 */

	/**
	 * Seeds one category entry.
	 *
	 * @param array  $raw  Raw entry.
	 * @param string $lang Language.
	 * @param bool   $dry  Dry run.
	 * @return array Item (+ term_id, slug, seed_id when known).
	 */
	private static function seed_category( $raw, $lang, $dry ) {
		$raw     = is_array( $raw ) ? $raw : array();
		$seed_id = isset( $raw['seed_id'] ) && is_scalar( $raw['seed_id'] ) ? trim( (string) $raw['seed_id'] ) : '';
		$slug    = isset( $raw['slug'] ) && is_scalar( $raw['slug'] ) ? sanitize_title( (string) $raw['slug'] ) : '';
		$name    = isset( $raw['name'] ) && is_scalar( $raw['name'] ) ? sanitize_text_field( (string) $raw['name'] ) : '';
		$desc    = self::text( $raw, 'description', true );
		if ( '' === $slug || '' === $name ) {
			return self::item( 'errors', 'category', $seed_id, 0, $name, __( 'Category entry needs a slug and a name.', 'mr-tu-multilang-seo' ) );
		}
		$term_id = '' !== $seed_id ? self::find_category( $seed_id, $lang ) : 0;
		if ( ! $term_id ) {
			$term_id = self::find_category_by_slug( $slug, $lang );
		}
		if ( $term_id ) {
			if ( ! $dry ) {
				if ( '' !== $seed_id && (string) get_term_meta( $term_id, self::META_SEED_ID, true ) !== $seed_id ) {
					update_term_meta( $term_id, self::META_SEED_ID, $seed_id );
				}
				if ( Translations::get_term_language( $term_id ) !== $lang || '' === (string) get_term_meta( $term_id, Translations::META_TERM_LANG, true ) ) {
					Translations::set_term_language( $term_id, $lang );
				}
			}
			$item            = self::item( 'skipped', 'category', $seed_id, $term_id, $name, __( 'Category already exists.', 'mr-tu-multilang-seo' ) );
			$item['slug']    = (string) get_term_field( 'slug', $term_id, 'category', 'raw' );
			$item['seed_id'] = $seed_id;
			return $item;
		}
		if ( $dry ) {
			$item         = self::item( 'created', 'category', $seed_id, 0, $name, __( 'Would be created.', 'mr-tu-multilang-seo' ) );
			$item['slug'] = $slug;
			return $item;
		}
		$result = wp_insert_term(
			$name,
			'category',
			array(
				'slug'        => $slug,
				'description' => $desc,
			)
		);
		if ( is_wp_error( $result ) ) {
			return self::item( 'errors', 'category', $seed_id, 0, $name, $result->get_error_message() );
		}
		$term_id = (int) $result['term_id'];
		Translations::set_term_language( $term_id, $lang );
		if ( '' !== $seed_id ) {
			update_term_meta( $term_id, self::META_SEED_ID, $seed_id );
		}
		$item            = self::item( 'created', 'category', $seed_id, $term_id, $name );
		$item['slug']    = (string) get_term_field( 'slug', $term_id, 'category', 'raw' );
		$item['seed_id'] = $seed_id;
		return $item;
	}

	/**
	 * Category of a seed id in a language.
	 *
	 * @param string $seed_id Seed id.
	 * @param string $lang    Language.
	 * @return int 0 when none.
	 */
	private static function find_category( $seed_id, $lang ) {
		$ids = get_terms(
			array(
				'taxonomy'        => 'category',
				'hide_empty'      => false,
				'fields'          => 'ids',
				'meta_key'        => self::META_SEED_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- idempotency lookup.
				'meta_value'      => $seed_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				Router::QUERY_VAR => 'all',
			)
		);
		if ( ! is_array( $ids ) ) {
			return 0;
		}
		foreach ( $ids as $id ) {
			if ( Translations::get_term_language( (int) $id ) === $lang ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/**
	 * Category by slug when it belongs to the language (category slugs are unique per taxonomy).
	 *
	 * @param string $slug Slug.
	 * @param string $lang Language.
	 * @return int 0 when none.
	 */
	private static function find_category_by_slug( $slug, $lang ) {
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( ! $term instanceof \WP_Term ) {
			return 0;
		}
		return Translations::get_term_language( $term ) === $lang ? (int) $term->term_id : 0;
	}

	/**
	 * Links the language versions of every seeded category.
	 *
	 * @param array $cats   seed_id => [ lang => term_id ].
	 * @param array $report Report (by reference).
	 */
	private static function link_categories( array $cats, array &$report ) {
		foreach ( $cats as $seed_id => $map ) {
			foreach ( Languages::codes( false ) as $code ) {
				if ( ! isset( $map[ $code ] ) ) {
					$found = self::find_category( (string) $seed_id, $code );
					if ( $found ) {
						$map[ $code ] = $found;
					}
				}
			}
			if ( count( $map ) < 2 ) {
				continue;
			}
			$current = Translations::get_term_translations( (int) reset( $map ) );
			if ( self::sorted( $map ) === $current ) {
				continue;
			}
			if ( ! Translations::link_term_translations( $map ) ) {
				/* translators: %s: seed id. */
				$report['messages'][] = sprintf( __( 'Could not link the language versions of category %s.', 'mr-tu-multilang-seo' ), $seed_id );
			}
		}
	}

	/*
	 * Groups + roles
	 * ------------------------------------------------------------------
	 */

	/**
	 * Links every language version of a seed id into one translation group (existing versions in
	 * languages that were not seeded this time are picked up too).
	 *
	 * @param array    $groups seed_id => [ lang => post_id ] (by reference: completed).
	 * @param array    $types  seed_id => post type.
	 * @param array    $roles  seed_id => role.
	 * @param string[] $codes  Languages processed in this run.
	 * @param array    $report Report (by reference).
	 */
	private static function link_groups( array &$groups, array $types, array $roles, array $codes, array &$report ) {
		foreach ( $groups as $seed_id => $map ) {
			$type = isset( $types[ $seed_id ] ) ? $types[ $seed_id ] : 'page';
			$role = isset( $roles[ $seed_id ] ) ? $roles[ $seed_id ] : '';
			foreach ( Languages::codes( false ) as $code ) {
				if ( isset( $map[ $code ] ) || in_array( $code, $codes, true ) ) {
					continue;
				}
				$found = self::find_post( (string) $seed_id, $type, $code, $role );
				if ( $found ) {
					$map[ $code ] = $found;
				}
			}
			$groups[ $seed_id ] = $map;
			if ( count( $map ) < 2 ) {
				continue;
			}
			$current = Translations::get_post_translations( (int) reset( $map ) );
			if ( self::sorted( $map ) === $current ) {
				continue;
			}
			if ( ! Translations::link_translations( $map ) ) {
				/* translators: %s: seed id. */
				$report['messages'][] = sprintf( __( 'Could not link the language versions of %s into one translation group (another post may already hold one of the languages).', 'mr-tu-multilang-seo' ), $seed_id );
			}
		}
	}

	/**
	 * Assigns the page roles to the seeded groups without stealing a role from another group.
	 *
	 * @param array $groups seed_id => [ lang => post_id ].
	 * @param array $roles  seed_id => role.
	 * @param array $report Report (by reference).
	 */
	private static function apply_roles( array $groups, array $roles, array &$report ) {
		foreach ( $roles as $seed_id => $role ) {
			if ( empty( $groups[ $seed_id ] ) ) {
				continue;
			}
			$member = (int) reset( $groups[ $seed_id ] );
			if ( Roles::get_post_role( $member ) === $role ) {
				continue;
			}
			$map   = Roles::map();
			$group = Translations::get_post_group( $member );
			if ( ! empty( $map[ $role ] ) && (int) $map[ $role ] !== $group && Translations::group_map( (int) $map[ $role ] ) ) {
				$report['messages'][] = sprintf(
					/* translators: 1: role key, 2: seed id. */
					__( 'The role “%1$s” already belongs to another page group; %2$s was left without a role.', 'mr-tu-multilang-seo' ),
					$role,
					$seed_id
				);
				continue;
			}
			if ( ! Roles::set_post_role( $member, $role ) ) {
				/* translators: 1: role key, 2: seed id. */
				$report['messages'][] = sprintf( __( 'Could not assign the role “%1$s” to %2$s.', 'mr-tu-multilang-seo' ), $role, $seed_id );
			}
		}
	}

	/**
	 * Orders a lang => id map by the registry order (for comparisons).
	 *
	 * @param array $map Map.
	 * @return array
	 */
	private static function sorted( array $map ) {
		$out = array();
		foreach ( Languages::codes( false ) as $code ) {
			if ( isset( $map[ $code ] ) ) {
				$out[ $code ] = (int) $map[ $code ];
			}
		}
		return $out;
	}

	/*
	 * Reading settings
	 * ------------------------------------------------------------------
	 */

	/**
	 * Reading settings: show_on_front = page, page_on_front = default-language home, page_for_posts =
	 * default-language blog page (other languages resolve through their translation groups), plus the
	 * timezone (when empty) and the date format.
	 *
	 * @param array $report Report (by reference).
	 */
	private static function set_front( array &$report ) {
		$default = Languages::default_code();
		$home    = self::role_page( 'home', $default );
		$blog    = self::role_page( 'blog', $default );
		$front   = &$report['front'];

		if ( ! $home ) {
			$front['messages'][] = __( 'Reading settings not changed: there is no published home page in the default language yet.', 'mr-tu-multilang-seo' );
			unset( $front );
			return;
		}
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			update_option( 'show_on_front', 'page' );
		}
		if ( (int) Router::raw_option( 'page_on_front' ) !== $home ) {
			update_option( 'page_on_front', $home );
		}
		$front['page_on_front'] = $home;
		if ( $blog ) {
			if ( (int) Router::raw_option( 'page_for_posts' ) !== $blog ) {
				update_option( 'page_for_posts', $blog );
			}
			$front['page_for_posts'] = $blog;
		} else {
			$front['messages'][] = __( 'No published blog page in the default language: the posts page was not set.', 'mr-tu-multilang-seo' );
		}
		if ( '' === (string) get_option( 'timezone_string' ) ) {
			update_option( 'timezone_string', 'Asia/Ho_Chi_Minh' );
			$front['messages'][] = __( 'Timezone set to Asia/Ho_Chi_Minh.', 'mr-tu-multilang-seo' );
		}
		if ( 'd/m/Y' !== get_option( 'date_format' ) ) {
			update_option( 'date_format', 'd/m/Y' );
		}
		$front['applied'] = true;
		unset( $front );
	}

	/**
	 * Published page of a role in a language (through the roles map, any group).
	 *
	 * @param string $role Role.
	 * @param string $lang Language.
	 * @return int
	 */
	private static function role_page( $role, $lang ) {
		$map = Roles::map();
		if ( empty( $map[ $role ] ) ) {
			return 0;
		}
		$members = Translations::group_map( (int) $map[ $role ] );
		$id      = isset( $members[ $lang ] ) ? (int) $members[ $lang ] : 0;
		return $id && 'publish' === get_post_status( $id ) ? $id : 0;
	}

	/**
	 * Author for new posts: the current user, else the first administrator.
	 *
	 * @return int
	 */
	private static function author_id() {
		$id = get_current_user_id();
		if ( $id ) {
			return (int) $id;
		}
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			)
		);
		return $admins ? (int) $admins[0] : 1;
	}
}
