<?php
/**
 * Translation storage: post languages, translation groups, term translations.
 *
 * Posts: private taxonomy `mrtu_language` (one term per post, slug = language code) and private taxonomy
 * `mrtu_translations` (one term per group; its description holds the JSON map {"vi":12,"en":34}, all statuses).
 * Untagged posts are the default language everywhere.
 *
 * Terms (category, post_tag): term meta `_mrtu_lang` + `_mrtu_tgroup`, option `mrtu_term_translations`
 * [ group_key => [ lang => term_id ] ].
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Translation groups and language assignment.
 */
final class Translations {

	const TAX_LANGUAGE = 'mrtu_language';

	const TAX_GROUP = 'mrtu_translations';

	const TERM_OPTION = 'mrtu_term_translations';

	const META_TERM_LANG = '_mrtu_lang';

	const META_TERM_GROUP = '_mrtu_tgroup';

	const META_TRASHED_GROUP = '_mrtu_trashed_group';

	/**
	 * Language terms cache: code => WP_Term (null = not loaded).
	 *
	 * @var array|null
	 */
	private static $language_terms = null;

	/**
	 * Decoded group maps cache: group term id => [ lang => post_id ].
	 *
	 * @var array
	 */
	private static $maps = array();

	/**
	 * Language requested for the post currently being inserted/updated through `mrtu_lang` in wp_insert_post().
	 *
	 * @var array{id:int,lang:string}|null
	 */
	private static $inserting = null;

	/**
	 * Group of the post being deleted (between before_delete_post and deleted_post).
	 *
	 * @var array
	 */
	private static $deleting = array();

	/**
	 * Re-entrancy guard for the duplicate-content publish guard.
	 *
	 * @var bool
	 */
	private static $reverting = false;

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 0 );

		// Language passed to wp_insert_post( [ 'mrtu_lang' => 'en' ] ) is known before the slug is computed.
		add_filter( 'wp_insert_post_parent', array( __CLASS__, 'capture_language' ), 10, 4 );
		add_action( 'transition_post_status', array( __CLASS__, 'apply_captured_language' ), -100, 3 );

		// A byte-identical copy of another language can never be published.
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard_insert_data' ), 99, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'guard_transition' ), 5, 3 );

		// Keep group maps consistent.
		add_action( 'trashed_post', array( __CLASS__, 'on_trashed' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_untrashed' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_before_delete' ) );
		add_action( 'deleted_post', array( __CLASS__, 'on_deleted' ) );
		add_action( 'delete_term', array( __CLASS__, 'on_term_deleted' ), 10, 3 );
	}

	/**
	 * Activation: registers the taxonomies (init already ran) and creates the language terms.
	 */
	public static function activate() {
		self::register_taxonomies();
		self::ensure_language_terms();
	}

	/*
	 * Registry
	 */

	/**
	 * Translatable post types (filter `mrtu_translatable_post_types`).
	 *
	 * @return string[]
	 */
	public static function post_types() {
		static $types = null;
		if ( null === $types || ! did_action( 'init' ) ) {
			$filtered = (array) apply_filters( 'mrtu_translatable_post_types', array( 'post', 'page' ) );
			$filtered = array_values( array_diff( array_unique( array_map( 'sanitize_key', $filtered ) ), array( 'attachment', 'revision', 'nav_menu_item' ) ) );
			if ( ! did_action( 'init' ) ) {
				return $filtered;
			}
			$types = $filtered;
		}
		return $types;
	}

	/**
	 * Translatable taxonomies.
	 *
	 * @return string[]
	 */
	public static function taxonomies() {
		return (array) apply_filters( 'mrtu_translatable_taxonomies', array( 'category', 'post_tag' ) );
	}

	/**
	 * Whether a post type is translatable.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public static function is_translatable_post_type( $post_type ) {
		return is_string( $post_type ) && in_array( $post_type, self::post_types(), true );
	}

	/**
	 * Whether a taxonomy is translatable.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return bool
	 */
	public static function is_translatable_taxonomy( $taxonomy ) {
		return is_string( $taxonomy ) && in_array( $taxonomy, self::taxonomies(), true );
	}

	/**
	 * Registers the two private taxonomies.
	 */
	public static function register_taxonomies() {
		$common = array(
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => false,
			'show_tagcloud'      => false,
			'show_admin_column'  => false,
			'hierarchical'       => false,
			'rewrite'            => false,
			'query_var'          => false,
			'capabilities'       => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'edit_posts',
			),
		);

		register_taxonomy(
			self::TAX_LANGUAGE,
			self::post_types(),
			array_merge(
				$common,
				array(
					'label'                 => __( 'Languages', 'mr-tu-multilang-seo' ),
					'update_count_callback' => '_update_generic_term_count',
				)
			)
		);
		register_taxonomy(
			self::TAX_GROUP,
			self::post_types(),
			array_merge(
				$common,
				array(
					'label'                 => __( 'Translation groups', 'mr-tu-multilang-seo' ),
					'update_count_callback' => '_update_generic_term_count',
				)
			)
		);
	}

	/*
	 * Language terms
	 */

	/**
	 * Language terms keyed by code (one cached query per request).
	 *
	 * @return array code => WP_Term
	 */
	public static function language_terms() {
		if ( null !== self::$language_terms ) {
			return self::$language_terms;
		}
		if ( ! taxonomy_exists( self::TAX_LANGUAGE ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'               => self::TAX_LANGUAGE,
				'hide_empty'             => false,
				'update_term_meta_cache' => false,
			)
		);
		$map   = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( Languages::exists( $term->slug ) ) {
					$map[ $term->slug ] = $term;
				}
			}
		}
		self::$language_terms = $map;
		return $map;
	}

	/**
	 * Creates missing language terms (slug = code, name = native name).
	 *
	 * @return array code => term_id
	 */
	public static function ensure_language_terms() {
		if ( ! taxonomy_exists( self::TAX_LANGUAGE ) ) {
			self::register_taxonomies();
		}
		self::$language_terms = null;
		$ids                  = array();
		foreach ( Languages::all( false ) as $code => $lang ) {
			$ids[ $code ] = self::ensure_language_term( $code );
		}
		return $ids;
	}

	/**
	 * Term id of a language term, creating it when missing.
	 *
	 * @param string $code Language code.
	 * @return int 0 on failure.
	 */
	public static function ensure_language_term( $code ) {
		$lang = Languages::get( $code );
		if ( ! $lang ) {
			return 0;
		}
		$terms = self::language_terms();
		if ( isset( $terms[ $code ] ) ) {
			return (int) $terms[ $code ]->term_id;
		}
		$existing = get_term_by( 'slug', $code, self::TAX_LANGUAGE );
		if ( $existing ) {
			self::$language_terms = null;
			return (int) $existing->term_id;
		}
		$result               = wp_insert_term( $lang['name'], self::TAX_LANGUAGE, array( 'slug' => $code ) );
		self::$language_terms = null;
		return is_wp_error( $result ) ? 0 : (int) $result['term_id'];
	}

	/**
	 * Term taxonomy id of a language (for tax queries without lookups).
	 *
	 * @param string $code Language code.
	 * @return int 0 when the term does not exist.
	 */
	public static function language_tt_id( $code ) {
		$terms = self::language_terms();
		return isset( $terms[ $code ] ) ? (int) $terms[ $code ]->term_taxonomy_id : 0;
	}

	/*
	 * Post language
	 */

	/**
	 * Language of a post; untagged posts are the default language.
	 *
	 * @param int|\WP_Post $post Post.
	 * @return string Language code ('' when the post does not exist).
	 */
	public static function get_post_language( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return '';
		}
		$terms = get_the_terms( $post, self::TAX_LANGUAGE );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( Languages::exists( $term->slug ) ) {
					return $term->slug;
				}
			}
		}
		return Languages::default_code();
	}

	/**
	 * Whether a post carries an explicit language term.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	public static function has_language_term( $post_id ) {
		$terms = get_the_terms( $post_id, self::TAX_LANGUAGE );
		return is_array( $terms ) && ! empty( $terms );
	}

	/**
	 * Assigns a language. Fails when another post of the same group already has that language.
	 *
	 * @param int    $post_id Post id.
	 * @param string $lang    Language code.
	 * @return bool
	 */
	public static function set_post_language( $post_id, $lang ) {
		$post = get_post( $post_id );
		if ( ! $post || ! self::is_translatable_post_type( $post->post_type ) || ! Languages::exists( $lang ) ) {
			return false;
		}
		$group = self::get_post_group( $post->ID );
		if ( $group ) {
			$map = self::group_map( $group );
			if ( isset( $map[ $lang ] ) && (int) $map[ $lang ] !== $post->ID ) {
				return false;
			}
		}
		$term_id = self::ensure_language_term( $lang );
		if ( ! $term_id ) {
			return false;
		}
		$result = wp_set_object_terms( $post->ID, array( $term_id ), self::TAX_LANGUAGE, false );
		if ( is_wp_error( $result ) ) {
			return false;
		}
		if ( $group ) {
			self::rebuild_group( $group );
		}
		return true;
	}

	/**
	 * Captures `mrtu_lang` from wp_insert_post() data before the unique slug is computed.
	 *
	 * @param int   $post_parent Parent id (returned unchanged).
	 * @param int   $post_id     Post id (0 for new posts).
	 * @param array $new_postarr Sanitized post data.
	 * @param array $postarr     Raw post data.
	 * @return int
	 */
	public static function capture_language( $post_parent, $post_id, $new_postarr, $postarr ) {
		self::$inserting = null;
		if ( ! is_array( $postarr ) || empty( $postarr['mrtu_lang'] ) || ! is_string( $postarr['mrtu_lang'] ) ) {
			return $post_parent;
		}
		$type = isset( $new_postarr['post_type'] ) ? $new_postarr['post_type'] : 'post';
		$lang = sanitize_key( $postarr['mrtu_lang'] );
		if ( ! self::is_translatable_post_type( $type ) || ! Languages::exists( $lang ) ) {
			return $post_parent;
		}
		// The edit-screen field carries its own nonce; programmatic inserts have none.
		if ( isset( $postarr['_mrtu_lang_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $postarr['_mrtu_lang_nonce'] ) ), 'mrtu_post_lang_' . (int) $post_id ) ) {
			return $post_parent;
		}
		self::$inserting = array(
			'id'   => (int) $post_id,
			'lang' => $lang,
		);
		return $post_parent;
	}

	/**
	 * Language to use when checking slug uniqueness for a post being saved.
	 *
	 * @param int $post_id Post id (0 for new posts).
	 * @return string
	 */
	public static function language_for_slug( $post_id ) {
		$post_id = (int) $post_id;
		if ( self::$inserting && ( self::$inserting['id'] === $post_id || ( 0 === self::$inserting['id'] && ( ! $post_id || ! self::has_language_term( $post_id ) ) ) ) ) {
			return self::$inserting['lang'];
		}
		return $post_id ? self::get_post_language( $post_id ) : Languages::default_code();
	}

	/**
	 * Applies the captured language as soon as the post exists (before save_post hooks run).
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public static function apply_captured_language( $new_status, $old_status, $post ) {
		if ( ! self::$inserting || ! $post instanceof \WP_Post ) {
			return;
		}
		$match = self::$inserting['id'] === (int) $post->ID || ( 0 === self::$inserting['id'] && 'new' === $old_status );
		if ( ! $match ) {
			return;
		}
		$lang            = self::$inserting['lang'];
		self::$inserting = null;
		if ( ! self::has_language_term( $post->ID ) || self::get_post_language( $post ) !== $lang ) {
			self::set_post_language( $post->ID, $lang );
		}
	}

	/*
	 * Translation groups (posts)
	 */

	/**
	 * Translation group term id of a post.
	 *
	 * @param int|\WP_Post $post Post.
	 * @return int 0 when the post is not in a group.
	 */
	public static function get_post_group( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return 0;
		}
		$terms = get_the_terms( $post, self::TAX_GROUP );
		return is_array( $terms ) && ! empty( $terms ) ? (int) $terms[0]->term_id : 0;
	}

	/**
	 * Decoded JSON map of a group.
	 *
	 * @param int $group_id Group term id.
	 * @return array lang => post_id (registry order)
	 */
	public static function group_map( $group_id ) {
		$group_id = (int) $group_id;
		if ( ! $group_id ) {
			return array();
		}
		if ( isset( self::$maps[ $group_id ] ) ) {
			return self::$maps[ $group_id ];
		}
		$term = get_term( $group_id, self::TAX_GROUP );
		if ( ! $term instanceof \WP_Term ) {
			return array();
		}
		$decoded = json_decode( (string) $term->description, true );
		$map     = array();
		if ( is_array( $decoded ) ) {
			foreach ( Languages::codes( false ) as $code ) {
				if ( isset( $decoded[ $code ] ) && (int) $decoded[ $code ] > 0 ) {
					$map[ $code ] = (int) $decoded[ $code ];
				}
			}
		}
		self::$maps[ $group_id ] = $map;
		return $map;
	}

	/**
	 * All translations of a post (all statuses), including the post itself.
	 *
	 * @param int|\WP_Post $post Post.
	 * @return array lang => post_id
	 */
	public static function get_post_translations( $post ) {
		$post = get_post( $post );
		if ( ! $post || ! self::is_translatable_post_type( $post->post_type ) ) {
			return array();
		}
		$lang  = self::get_post_language( $post );
		$group = self::get_post_group( $post );
		$map   = $group ? self::group_map( $group ) : array();
		if ( ! in_array( (int) $post->ID, $map, true ) ) {
			$map[ $lang ] = (int) $post->ID;
		}
		return self::sort_map( $map );
	}

	/**
	 * Translation of a post in a language.
	 *
	 * @param int|\WP_Post $post           Post.
	 * @param string       $lang           Language code.
	 * @param bool         $published_only Only return a published translation.
	 * @return int 0 when none.
	 */
	public static function get_post_translation( $post, $lang, $published_only = true ) {
		$map = self::get_post_translations( $post );
		if ( empty( $map[ $lang ] ) ) {
			return 0;
		}
		$id = (int) $map[ $lang ];
		if ( $published_only && 'publish' !== get_post_status( $id ) ) {
			return 0;
		}
		return $id;
	}

	/**
	 * Whether two posts belong to the same translation group.
	 *
	 * @param int $a Post id.
	 * @param int $b Post id.
	 * @return bool
	 */
	public static function in_same_group( $a, $b ) {
		if ( (int) $a === (int) $b ) {
			return true;
		}
		$group = self::get_post_group( $a );
		return $group && self::get_post_group( $b ) === $group;
	}

	/**
	 * Links posts as translations of each other (merging existing groups).
	 *
	 * @param array $map lang => post_id. Each post gets the language of its key.
	 * @return bool False on invalid input or when two different posts would share a language.
	 */
	public static function link_translations( array $map ) {
		$clean = array();
		$type  = null;
		foreach ( $map as $lang => $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! Languages::exists( $lang ) || ! $post || ! self::is_translatable_post_type( $post->post_type ) || 'trash' === $post->post_status ) {
				return false;
			}
			if ( null !== $type && $type !== $post->post_type ) {
				return false;
			}
			$type = $post->post_type;
			if ( in_array( (int) $post->ID, $clean, true ) ) {
				return false;
			}
			$clean[ $lang ] = (int) $post->ID;
		}
		if ( empty( $clean ) ) {
			return false;
		}

		// Merge the existing groups of every post, rejecting language conflicts.
		$groups = array();
		foreach ( $clean as $post_id ) {
			$group = self::get_post_group( $post_id );
			if ( $group ) {
				$groups[ $group ] = $group;
			}
		}
		$merged = $clean;
		foreach ( $groups as $group ) {
			foreach ( self::group_map( $group ) as $lang => $member ) {
				if ( in_array( $member, $clean, true ) ) {
					continue;
				}
				if ( isset( $merged[ $lang ] ) && $merged[ $lang ] !== $member ) {
					return false;
				}
				if ( get_post_type( $member ) !== $type ) {
					return false;
				}
				$merged[ $lang ] = $member;
			}
		}

		// Target group: keep the one that carries a role, else the first, else a new one.
		$target = 0;
		foreach ( $groups as $group ) {
			if ( '' !== Roles::group_role( $group ) ) {
				$target = $group;
				break;
			}
		}
		if ( ! $target && $groups ) {
			$target = (int) reset( $groups );
		}
		if ( ! $target ) {
			$target = self::create_group();
			if ( ! $target ) {
				return false;
			}
		}

		// Roles of merged groups move to the target when it has none.
		if ( '' === Roles::group_role( $target ) ) {
			foreach ( $groups as $group ) {
				$role = Roles::group_role( $group );
				if ( '' !== $role && $group !== $target ) {
					Roles::assign_group( $role, $target );
					break;
				}
			}
		}

		foreach ( $clean as $lang => $post_id ) {
			$term_id = self::ensure_language_term( $lang );
			if ( $term_id && self::get_post_language( $post_id ) !== $lang ) {
				wp_set_object_terms( $post_id, array( $term_id ), self::TAX_LANGUAGE, false );
			} elseif ( $term_id && ! self::has_language_term( $post_id ) ) {
				wp_set_object_terms( $post_id, array( $term_id ), self::TAX_LANGUAGE, false );
			}
		}
		foreach ( $merged as $post_id ) {
			wp_set_object_terms( $post_id, array( $target ), self::TAX_GROUP, false );
		}
		foreach ( $groups as $group ) {
			if ( $group !== $target ) {
				self::rebuild_group( $group );
			}
		}
		$final = self::rebuild_group( $target );

		/**
		 * Fires after posts were linked as translations.
		 *
		 * @param array $final lang => post_id of the resulting group.
		 */
		do_action( 'mrtu_translations_linked', $final );
		return true;
	}

	/**
	 * Removes a post from its translation group (the post keeps its language).
	 *
	 * @param int $post_id Post id.
	 */
	public static function unlink_translation( $post_id ) {
		$post_id = (int) $post_id;
		$group   = self::get_post_group( $post_id );
		if ( ! $group ) {
			return;
		}
		$map = self::group_map( $group );
		if ( count( $map ) <= 1 ) {
			return;
		}
		wp_remove_object_terms( $post_id, array( $group ), self::TAX_GROUP );
		delete_post_meta( $post_id, Roles::META );
		self::rebuild_group( $group );
	}

	/**
	 * Creates an empty group term.
	 *
	 * @return int Group term id (0 on failure).
	 */
	public static function create_group() {
		$slug   = 'mrtu-g-' . str_replace( '.', '', uniqid( '', true ) );
		$result = wp_insert_term(
			$slug,
			self::TAX_GROUP,
			array(
				'slug'        => $slug,
				'description' => '{}',
			)
		);
		return is_wp_error( $result ) ? 0 : (int) $result['term_id'];
	}

	/**
	 * Ensures a post is in a group (creates a group of one) and returns its id.
	 *
	 * @param int $post_id Post id.
	 * @return int Group id (0 on failure).
	 */
	public static function ensure_group( $post_id ) {
		$group = self::get_post_group( $post_id );
		if ( $group ) {
			return $group;
		}
		$post = get_post( $post_id );
		if ( ! $post || ! self::is_translatable_post_type( $post->post_type ) ) {
			return 0;
		}
		$group = self::create_group();
		if ( ! $group ) {
			return 0;
		}
		wp_set_object_terms( $post->ID, array( $group ), self::TAX_GROUP, false );
		self::rebuild_group( $group );
		return $group;
	}

	/**
	 * Rebuilds a group's JSON map from its term relationships (the cache of truth).
	 *
	 * Duplicate languages keep the published (then oldest) post; the others leave the group.
	 * Empty groups are deleted. Role meta of every member is re-synced.
	 *
	 * @param int $group_id Group term id.
	 * @return array lang => post_id
	 */
	public static function rebuild_group( $group_id ) {
		$group_id = (int) $group_id;
		unset( self::$maps[ $group_id ] );
		$term = get_term( $group_id, self::TAX_GROUP );
		if ( ! $term instanceof \WP_Term ) {
			return array();
		}

		$ids = get_objects_in_term( $group_id, self::TAX_GROUP );
		$ids = is_array( $ids ) ? array_map( 'intval', $ids ) : array();
		if ( $ids ) {
			_prime_post_caches( $ids, false, false );
			update_object_term_cache( $ids, self::post_types() );
		}

		$posts = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || 'trash' === $post->post_status || ! self::is_translatable_post_type( $post->post_type ) ) {
				wp_remove_object_terms( $id, array( $group_id ), self::TAX_GROUP );
				continue;
			}
			$posts[] = $post;
		}
		usort(
			$posts,
			static function ( $a, $b ) {
				$pa = 'publish' === $a->post_status ? 0 : 1;
				$pb = 'publish' === $b->post_status ? 0 : 1;
				return $pa === $pb ? $a->ID - $b->ID : $pa - $pb;
			}
		);

		$map = array();
		foreach ( $posts as $post ) {
			$lang = self::get_post_language( $post );
			if ( isset( $map[ $lang ] ) ) {
				wp_remove_object_terms( $post->ID, array( $group_id ), self::TAX_GROUP );
				continue;
			}
			$map[ $lang ] = (int) $post->ID;
		}
		$map = self::sort_map( $map );

		if ( empty( $map ) ) {
			Roles::forget_group( $group_id );
			wp_delete_term( $group_id, self::TAX_GROUP );
			unset( self::$maps[ $group_id ] );
			return array();
		}

		$json = wp_json_encode( (object) $map );
		if ( $json !== $term->description ) {
			wp_update_term( $group_id, self::TAX_GROUP, array( 'description' => wp_slash( $json ) ) );
		}
		self::$maps[ $group_id ] = $map;
		Roles::sync_group( $group_id, $map );
		return $map;
	}

	/**
	 * Rebuilds every group map (Health Check "Rebuild translation maps").
	 *
	 * @return int Number of groups processed.
	 */
	public static function rebuild_all() {
		$groups = get_terms(
			array(
				'taxonomy'               => self::TAX_GROUP,
				'hide_empty'             => false,
				'fields'                 => 'ids',
				'update_term_meta_cache' => false,
			)
		);
		if ( ! is_array( $groups ) ) {
			return 0;
		}
		foreach ( $groups as $group ) {
			self::rebuild_group( (int) $group );
		}
		return count( $groups );
	}

	/**
	 * Orders a lang => id map by the registry order.
	 *
	 * @param array $map Map.
	 * @return array
	 */
	private static function sort_map( array $map ) {
		$sorted = array();
		foreach ( Languages::codes( false ) as $code ) {
			if ( isset( $map[ $code ] ) ) {
				$sorted[ $code ] = (int) $map[ $code ];
			}
		}
		return $sorted;
	}

	/**
	 * Primes post, language and group caches for a set of posts and all their translations.
	 *
	 * @param int[] $post_ids Post ids.
	 */
	public static function prime( array $post_ids ) {
		$post_ids = array_values( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) );
		if ( ! $post_ids || ! taxonomy_exists( self::TAX_GROUP ) ) {
			return;
		}
		_prime_post_caches( $post_ids, false, false );
		update_object_term_cache( $post_ids, self::post_types() );

		$members = array();
		foreach ( $post_ids as $post_id ) {
			$terms = get_object_term_cache( $post_id, self::TAX_GROUP );
			if ( is_array( $terms ) && $terms ) {
				foreach ( self::group_map( $terms[0]->term_id ) as $member ) {
					$members[] = (int) $member;
				}
			}
		}
		$members = array_diff( array_unique( $members ), $post_ids );
		if ( $members ) {
			_prime_post_caches( $members, false, false );
			update_object_term_cache( $members, self::post_types() );
		}
	}

	/*
	 * Lookups used by the router
	 */

	/**
	 * Finds a hierarchical post by path inside one language (language-aware get_page_by_path()).
	 *
	 * Ancestors may be of any language (permalinks use the parents' slugs); the leaf decides.
	 *
	 * @param string   $path  Page path (e.g. "parent/child"), URL-encoded or not.
	 * @param string   $lang  Language code.
	 * @param string[] $types Hierarchical post types to search.
	 * @return int Post id or 0.
	 */
	public static function find_post_by_path( $path, $lang, array $types ) {
		global $wpdb;
		static $cache = array();

		$types = array_values( array_filter( $types, 'is_post_type_hierarchical' ) );
		if ( ! $types ) {
			return 0;
		}
		$page_path = rawurlencode( urldecode( (string) $path ) );
		$page_path = str_replace( array( '%2F', '%20' ), array( '/', ' ' ), $page_path );
		$parts     = array_values( array_filter( array_map( 'sanitize_title_for_query', explode( '/', trim( $page_path, '/' ) ) ), 'strlen' ) );
		if ( ! $parts ) {
			return 0;
		}
		$key = md5( implode( '/', $parts ) . '|' . $lang . '|' . implode( ',', $types ) );
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		$name_in = implode( ', ', array_fill( 0, count( $parts ), '%s' ) );
		$type_in = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- mirrors get_page_by_path() (cached per request above); the IN lists hold placeholders only.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_name, post_parent, post_type, post_status FROM {$wpdb->posts} WHERE post_name IN ( {$name_in} ) AND post_type IN ( {$type_in} ) AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )",
				array_merge( $parts, $types )
			)
		);
		// phpcs:enable
		$by_id = array();
		foreach ( (array) $rows as $row ) {
			$by_id[ (int) $row->ID ] = $row;
		}

		$leaf       = end( $parts );
		$candidates = array();
		foreach ( $by_id as $row ) {
			if ( $row->post_name !== $leaf ) {
				continue;
			}
			// Walk up the parents and compare with the requested path.
			$ok     = true;
			$parent = (int) $row->post_parent;
			for ( $i = count( $parts ) - 2; $i >= 0; $i-- ) {
				if ( ! $parent || ! isset( $by_id[ $parent ] ) || $by_id[ $parent ]->post_name !== $parts[ $i ] ) {
					$ok = false;
					break;
				}
				$parent = (int) $by_id[ $parent ]->post_parent;
			}
			if ( $ok && 0 === $parent ) {
				$candidates[] = $row;
			}
		}

		$found         = self::pick_candidate( $candidates, $lang );
		$cache[ $key ] = $found;
		return $found;
	}

	/**
	 * Finds a non-hierarchical post by slug inside one language.
	 *
	 * @param string   $name  Slug.
	 * @param string   $lang  Language code.
	 * @param string[] $types Post types.
	 * @return int Post id or 0.
	 */
	public static function find_post_by_name( $name, $lang, array $types ) {
		global $wpdb;
		$name = sanitize_title_for_query( (string) $name );
		if ( '' === $name || ! $types ) {
			return 0;
		}
		$type_in = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one lookup per routed request.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_name, post_parent, post_type, post_status FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ( {$type_in} ) AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
				array_merge( array( $name ), $types )
			)
		);
		return self::pick_candidate( (array) $rows, $lang );
	}

	/**
	 * Picks the candidate in a language (published first, then lowest id).
	 *
	 * @param object[] $rows Rows with ID and post_status.
	 * @param string   $lang Language code.
	 * @return int
	 */
	private static function pick_candidate( array $rows, $lang ) {
		if ( ! $rows ) {
			return 0;
		}
		$ids = array_map(
			static function ( $row ) {
				return (int) $row->ID;
			},
			$rows
		);
		update_object_term_cache( $ids, self::post_types() );
		$best      = 0;
		$best_rank = PHP_INT_MAX;
		foreach ( $rows as $row ) {
			if ( self::get_post_language( (int) $row->ID ) !== $lang ) {
				continue;
			}
			$rank = ( 'publish' === $row->post_status ? 0 : ( 'private' === $row->post_status ? 1 : 2 ) ) * 1000000000 + (int) $row->ID;
			if ( $rank < $best_rank ) {
				$best_rank = $rank;
				$best      = (int) $row->ID;
			}
		}
		return $best;
	}

	/*
	 * Creating translations
	 */

	/**
	 * Creates a draft translation of a post in another language and links it to the group.
	 *
	 * @param int    $source_id Source post id.
	 * @param string $lang      Target language.
	 * @param bool   $copy      Copy content + excerpt (the block structure) from the source.
	 * @return int|\WP_Error New post id.
	 */
	public static function create_translation( $source_id, $lang, $copy = false ) {
		$source = get_post( $source_id );
		if ( ! $source || ! self::is_translatable_post_type( $source->post_type ) || 'trash' === $source->post_status ) {
			return new \WP_Error( 'mrtu_invalid_source', __( 'The original post does not exist or cannot be translated.', 'mr-tu-multilang-seo' ) );
		}
		if ( ! Languages::exists( $lang ) ) {
			return new \WP_Error( 'mrtu_invalid_language', __( 'Unknown language.', 'mr-tu-multilang-seo' ) );
		}
		$map = self::get_post_translations( $source );
		if ( isset( $map[ $lang ] ) ) {
			return new \WP_Error( 'mrtu_translation_exists', __( 'This language version already exists.', 'mr-tu-multilang-seo' ), array( 'post_id' => (int) $map[ $lang ] ) );
		}

		// Map the parent page to its translation in the target language (any status).
		$parent = 0;
		if ( $source->post_parent ) {
			$parent = self::get_post_translation( $source->post_parent, $lang, false );
		}

		$postarr = array(
			'post_type'      => $source->post_type,
			'post_status'    => 'draft',
			'post_title'     => $source->post_title,
			'post_content'   => $copy ? $source->post_content : '',
			'post_excerpt'   => $copy ? $source->post_excerpt : '',
			'post_parent'    => $parent,
			'menu_order'     => $source->menu_order,
			'comment_status' => $source->comment_status,
			'ping_status'    => $source->ping_status,
			'post_author'    => get_current_user_id() ? get_current_user_id() : (int) $source->post_author,
			'mrtu_lang'      => $lang,
		);

		if ( 'post' === $source->post_type ) {
			$categories = array();
			foreach ( wp_get_post_categories( $source->ID ) as $cat_id ) {
				$translated = self::get_term_translation( $cat_id, $lang );
				if ( $translated ) {
					$categories[] = $translated;
				}
			}
			if ( $categories ) {
				$postarr['post_category'] = $categories;
			}
		}

		$new_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		if ( self::get_post_language( $new_id ) !== $lang ) {
			self::set_post_language( $new_id, $lang );
		}

		$template = get_post_meta( $source->ID, '_wp_page_template', true );
		if ( $template ) {
			update_post_meta( $new_id, '_wp_page_template', $template );
		}
		$thumbnail = get_post_thumbnail_id( $source );
		if ( $thumbnail ) {
			set_post_thumbnail( $new_id, $thumbnail );
		}
		if ( 'post' === $source->post_type ) {
			$tags = array();
			foreach ( wp_get_post_tags( $source->ID, array( 'fields' => 'ids' ) ) as $tag_id ) {
				$translated = self::get_term_translation( $tag_id, $lang );
				if ( $translated ) {
					$tags[] = $translated;
				}
			}
			if ( $tags ) {
				wp_set_post_terms( $new_id, $tags, 'post_tag' );
			}
		}

		$source_lang = self::get_post_language( $source );
		if ( ! self::has_language_term( $source->ID ) ) {
			self::set_post_language( $source->ID, $source_lang );
		}
		$map[ $source_lang ] = (int) $source->ID;
		$map[ $lang ]        = (int) $new_id;
		if ( ! self::link_translations( $map ) ) {
			wp_delete_post( $new_id, true );
			return new \WP_Error( 'mrtu_link_failed', __( 'The translation could not be linked to the original.', 'mr-tu-multilang-seo' ) );
		}
		return (int) $new_id;
	}

	/*
	 * Duplicate-content publish guard
	 */

	/**
	 * Whether a post's content is byte-identical to an older member of its group (= an untranslated copy).
	 *
	 * Empty content never counts (template-driven pages may legitimately be empty in every language).
	 *
	 * @param int    $post_id Post id.
	 * @param string $content Content about to be saved (unslashed).
	 * @return int Id of the identical original, 0 when none.
	 */
	public static function identical_original( $post_id, $content ) {
		$post_id = (int) $post_id;
		if ( ! $post_id || '' === trim( (string) $content ) ) {
			return 0;
		}
		foreach ( self::get_post_translations( $post_id ) as $other ) {
			if ( $other >= $post_id ) {
				continue;
			}
			$original = get_post( $other );
			if ( $original && $original->post_content === $content ) {
				return (int) $other;
			}
		}
		return 0;
	}

	/**
	 * Forces a draft when a translation identical to its original is about to be published or scheduled.
	 *
	 * @param array $data    Slashed post data.
	 * @param array $postarr Raw post data.
	 * @return array
	 */
	public static function guard_insert_data( $data, $postarr ) {
		if ( ! isset( $data['post_status'], $data['post_type'] ) || ! in_array( $data['post_status'], array( 'publish', 'future' ), true ) ) {
			return $data;
		}
		if ( ! self::is_translatable_post_type( $data['post_type'] ) || empty( $postarr['ID'] ) ) {
			return $data;
		}
		$original = self::identical_original( (int) $postarr['ID'], wp_unslash( $data['post_content'] ) );
		if ( $original ) {
			$data['post_status'] = 'draft';
			self::notify_duplicate( (int) $postarr['ID'], $original );
		}
		return $data;
	}

	/**
	 * Safety net for publishing paths that bypass wp_insert_post() (e.g. scheduled posts via wp_publish_post()).
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public static function guard_transition( $new_status, $old_status, $post ) {
		if ( self::$reverting || 'publish' !== $new_status || 'publish' === $old_status || ! $post instanceof \WP_Post ) {
			return;
		}
		if ( ! self::is_translatable_post_type( $post->post_type ) ) {
			return;
		}
		$original = self::identical_original( $post->ID, $post->post_content );
		if ( ! $original ) {
			return;
		}
		self::$reverting = true;
		wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'draft',
			)
		);
		self::$reverting = false;
		self::notify_duplicate( $post->ID, $original );
	}

	/**
	 * Stores a one-time admin notice explaining why the post stayed a draft.
	 *
	 * @param int $post_id     Translation id.
	 * @param int $original_id Identical original id.
	 */
	private static function notify_duplicate( $post_id, $original_id ) {
		$user_id = get_current_user_id();
		/**
		 * Fires when a translation identical to another language version was kept as a draft.
		 *
		 * @param int $post_id     Translation id.
		 * @param int $original_id Identical original id.
		 */
		do_action( 'mrtu_duplicate_translation_blocked', $post_id, $original_id );
		if ( ! $user_id ) {
			return;
		}
		$message = sprintf(
			/* translators: %s: language name of the original. */
			__( 'Not published: the content is identical to the %s version. Translate it first; it was saved as a draft.', 'mr-tu-multilang-seo' ),
			Languages::admin_name( self::get_post_language( $original_id ) )
		);
		set_transient(
			'mrtu_flash_' . $user_id,
			array(
				'message' => $message,
				'type'    => 'warning',
			),
			300
		);
	}

	/*
	 * Consistency on trash / delete
	 */

	/**
	 * Trashed posts leave their group (so the language slot is free) but remember it for untrash.
	 *
	 * @param int $post_id Post id.
	 */
	public static function on_trashed( $post_id ) {
		$group = self::get_post_group( $post_id );
		if ( ! $group ) {
			return;
		}
		update_post_meta( $post_id, self::META_TRASHED_GROUP, $group );
		wp_remove_object_terms( $post_id, array( $group ), self::TAX_GROUP );
		self::rebuild_group( $group );
	}

	/**
	 * Restored posts rejoin their group when their language slot is still free.
	 *
	 * @param int $post_id Post id.
	 */
	public static function on_untrashed( $post_id ) {
		$group = (int) get_post_meta( $post_id, self::META_TRASHED_GROUP, true );
		delete_post_meta( $post_id, self::META_TRASHED_GROUP );
		if ( ! $group ) {
			return;
		}
		$term = get_term( $group, self::TAX_GROUP );
		if ( $term instanceof \WP_Term ) {
			$map  = self::group_map( $group );
			$lang = self::get_post_language( $post_id );
			if ( ! isset( $map[ $lang ] ) ) {
				wp_set_object_terms( $post_id, array( $group ), self::TAX_GROUP, false );
				self::rebuild_group( $group );
				return;
			}
		}
		// The group is gone or the slot was taken: the post stays on its own without the group role.
		delete_post_meta( $post_id, Roles::META );
	}

	/**
	 * Remembers the group of a post about to be deleted.
	 *
	 * @param int $post_id Post id.
	 */
	public static function on_before_delete( $post_id ) {
		$group = self::get_post_group( $post_id );
		if ( $group ) {
			self::$deleting[ (int) $post_id ] = $group;
		}
	}

	/**
	 * Rebuilds the group of a deleted post.
	 *
	 * @param int $post_id Post id.
	 */
	public static function on_deleted( $post_id ) {
		if ( empty( self::$deleting[ (int) $post_id ] ) ) {
			return;
		}
		$group = self::$deleting[ (int) $post_id ];
		unset( self::$deleting[ (int) $post_id ] );
		self::rebuild_group( $group );
	}

	/*
	 * Term translations
	 */

	/**
	 * Language of a term (default language when untagged).
	 *
	 * @param int|\WP_Term $term Term.
	 * @return string '' when the term does not exist.
	 */
	public static function get_term_language( $term ) {
		$term = get_term( $term );
		if ( ! $term instanceof \WP_Term ) {
			return '';
		}
		$lang = get_term_meta( $term->term_id, self::META_TERM_LANG, true );
		return is_string( $lang ) && Languages::exists( $lang ) ? $lang : Languages::default_code();
	}

	/**
	 * Term translation groups option.
	 *
	 * @return array group_key => [ lang => term_id ]
	 */
	private static function term_groups() {
		$groups = get_option( self::TERM_OPTION, array() );
		return is_array( $groups ) ? $groups : array();
	}

	/**
	 * Saves the term groups option (autoload no).
	 *
	 * @param array $groups Groups.
	 */
	private static function save_term_groups( array $groups ) {
		update_option( self::TERM_OPTION, $groups, false );
	}

	/**
	 * Assigns a language to a term. Fails when another term of its group already has that language.
	 *
	 * @param int    $term_id Term id.
	 * @param string $lang    Language code.
	 * @return bool
	 */
	public static function set_term_language( $term_id, $lang ) {
		$term = get_term( (int) $term_id );
		if ( ! $term instanceof \WP_Term || ! self::is_translatable_taxonomy( $term->taxonomy ) || ! Languages::exists( $lang ) ) {
			return false;
		}
		$key    = (string) get_term_meta( $term->term_id, self::META_TERM_GROUP, true );
		$groups = self::term_groups();
		if ( '' !== $key && isset( $groups[ $key ] ) ) {
			if ( isset( $groups[ $key ][ $lang ] ) && (int) $groups[ $key ][ $lang ] !== $term->term_id ) {
				return false;
			}
			$groups[ $key ]          = array_diff( $groups[ $key ], array( $term->term_id ) );
			$groups[ $key ][ $lang ] = $term->term_id;
			$groups[ $key ]          = self::sort_map( $groups[ $key ] );
			self::save_term_groups( $groups );
		}
		update_term_meta( $term->term_id, self::META_TERM_LANG, $lang );
		return true;
	}

	/**
	 * All translations of a term, including itself.
	 *
	 * @param int|\WP_Term $term Term.
	 * @return array lang => term_id
	 */
	public static function get_term_translations( $term ) {
		$term = get_term( $term );
		if ( ! $term instanceof \WP_Term || ! self::is_translatable_taxonomy( $term->taxonomy ) ) {
			return array();
		}
		$lang   = self::get_term_language( $term );
		$key    = (string) get_term_meta( $term->term_id, self::META_TERM_GROUP, true );
		$map    = array();
		$groups = '' !== $key ? self::term_groups() : array();
		if ( isset( $groups[ $key ] ) && is_array( $groups[ $key ] ) ) {
			$map = array_map( 'intval', $groups[ $key ] );
		}
		if ( ! in_array( (int) $term->term_id, $map, true ) ) {
			$map[ $lang ] = (int) $term->term_id;
		}
		return self::sort_map( $map );
	}

	/**
	 * Translation of a term in a language.
	 *
	 * @param int|\WP_Term $term Term.
	 * @param string       $lang Language.
	 * @return int 0 when none.
	 */
	public static function get_term_translation( $term, $lang ) {
		$map = self::get_term_translations( $term );
		return isset( $map[ $lang ] ) ? (int) $map[ $lang ] : 0;
	}

	/**
	 * Links terms of one taxonomy as translations (merging existing groups).
	 *
	 * @param array $map lang => term_id.
	 * @return bool
	 */
	public static function link_term_translations( array $map ) {
		$clean    = array();
		$taxonomy = null;
		foreach ( $map as $lang => $term_id ) {
			$term = get_term( (int) $term_id );
			if ( ! Languages::exists( $lang ) || ! $term instanceof \WP_Term || ! self::is_translatable_taxonomy( $term->taxonomy ) ) {
				return false;
			}
			if ( null !== $taxonomy && $taxonomy !== $term->taxonomy ) {
				return false;
			}
			$taxonomy = $term->taxonomy;
			if ( in_array( (int) $term->term_id, $clean, true ) ) {
				return false;
			}
			$clean[ $lang ] = (int) $term->term_id;
		}
		if ( empty( $clean ) ) {
			return false;
		}

		$groups = self::term_groups();
		$keys   = array();
		foreach ( $clean as $term_id ) {
			$key = (string) get_term_meta( $term_id, self::META_TERM_GROUP, true );
			if ( '' !== $key && isset( $groups[ $key ] ) ) {
				$keys[ $key ] = $key;
			}
		}
		$merged = $clean;
		foreach ( $keys as $key ) {
			foreach ( (array) $groups[ $key ] as $lang => $member ) {
				$member = (int) $member;
				if ( in_array( $member, $clean, true ) || ! get_term( $member ) instanceof \WP_Term ) {
					continue;
				}
				if ( isset( $merged[ $lang ] ) && $merged[ $lang ] !== $member ) {
					return false;
				}
				$merged[ $lang ] = $member;
			}
		}

		$target = $keys ? (string) reset( $keys ) : 'tg' . strtolower( wp_generate_password( 12, false, false ) );
		foreach ( $keys as $key ) {
			unset( $groups[ $key ] );
		}
		foreach ( $clean as $lang => $term_id ) {
			update_term_meta( $term_id, self::META_TERM_LANG, $lang );
		}
		foreach ( $merged as $term_id ) {
			update_term_meta( $term_id, self::META_TERM_GROUP, $target );
		}
		$groups[ $target ] = self::sort_map( $merged );
		self::save_term_groups( $groups );
		return true;
	}

	/**
	 * Removes a term from its translation group.
	 *
	 * @param int $term_id Term id.
	 */
	public static function unlink_term_translation( $term_id ) {
		$term_id = (int) $term_id;
		$key     = (string) get_term_meta( $term_id, self::META_TERM_GROUP, true );
		delete_term_meta( $term_id, self::META_TERM_GROUP );
		if ( '' === $key ) {
			return;
		}
		$groups = self::term_groups();
		if ( ! isset( $groups[ $key ] ) ) {
			return;
		}
		$groups[ $key ] = array_diff( array_map( 'intval', (array) $groups[ $key ] ), array( $term_id ) );
		if ( count( $groups[ $key ] ) < 2 ) {
			foreach ( $groups[ $key ] as $member ) {
				delete_term_meta( (int) $member, self::META_TERM_GROUP );
			}
			unset( $groups[ $key ] );
		}
		self::save_term_groups( $groups );
	}

	/**
	 * Keeps the term groups option consistent when a term is deleted.
	 *
	 * @param int    $term_id  Term id.
	 * @param int    $tt_id    Term taxonomy id.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function on_term_deleted( $term_id, $tt_id, $taxonomy ) {
		if ( ! self::is_translatable_taxonomy( $taxonomy ) ) {
			return;
		}
		$groups  = self::term_groups();
		$changed = false;
		foreach ( $groups as $key => $map ) {
			if ( ! in_array( (int) $term_id, array_map( 'intval', (array) $map ), true ) ) {
				continue;
			}
			$map     = array_diff( array_map( 'intval', (array) $map ), array( (int) $term_id ) );
			$changed = true;
			if ( count( $map ) < 2 ) {
				foreach ( $map as $member ) {
					delete_term_meta( $member, self::META_TERM_GROUP );
				}
				unset( $groups[ $key ] );
			} else {
				$groups[ $key ] = $map;
			}
		}
		if ( $changed ) {
			self::save_term_groups( $groups );
		}
	}
}
