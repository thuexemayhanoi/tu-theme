<?php
/**
 * Language-aware queries (contract 2.5 `pre_get_posts`).
 *
 * Main frontend query: home/blog, archives, search and feeds are limited to the current language.
 * Any WP_Query (secondary, admin list, REST) can opt in with the `mrtu_lang` argument:
 * a language code, 'current' or 'all'. Untagged posts count as the default language.
 *
 * @package MrTu\Multilang
 */

namespace MrTu\Multilang;

defined( 'ABSPATH' ) || exit;

/**
 * Query scoping.
 */
final class Query {

	const SCOPED_FLAG = '_mrtu_scoped';

	/**
	 * Wires hooks.
	 */
	public static function init() {
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ), 20 );
		add_filter( 'the_posts', array( __CLASS__, 'prime_results' ), 10, 2 );
		// Content-level listings rendered on a page belong to the page language.
		add_filter( 'query_loop_block_query_vars', array( __CLASS__, 'scope_listing_args' ) );
		add_filter( 'widget_posts_args', array( __CLASS__, 'scope_listing_args' ) );
	}

	/**
	 * Query Loop blocks (not inheriting the main query) and the Recent Posts widget list the current language.
	 *
	 * @param array $args WP_Query arguments.
	 * @return array
	 */
	public static function scope_listing_args( $args ) {
		if ( is_array( $args ) && Router::is_frontend() && empty( $args[ Router::QUERY_VAR ] ) && '' !== Router::current_language() ) {
			$args[ Router::QUERY_VAR ] = Router::current_language();
		}
		return $args;
	}

	/**
	 * Tax query clause selecting one language.
	 *
	 * Default language: untagged posts OR posts tagged with it (relation OR + NOT EXISTS).
	 *
	 * @param string $lang Language code.
	 * @return array
	 */
	public static function language_clause( $lang ) {
		$tt = Translations::language_tt_id( $lang );
		if ( Languages::default_code() === $lang ) {
			$clause = array(
				'relation' => 'OR',
				array(
					'taxonomy' => Translations::TAX_LANGUAGE,
					'operator' => 'NOT EXISTS',
				),
			);
			if ( $tt ) {
				$clause[] = array(
					'taxonomy'         => Translations::TAX_LANGUAGE,
					'field'            => 'term_taxonomy_id',
					'terms'            => array( $tt ),
					'include_children' => false,
				);
			}
			return $clause;
		}
		return array(
			'taxonomy'         => Translations::TAX_LANGUAGE,
			'field'            => 'term_taxonomy_id',
			'terms'            => array( $tt ? $tt : 0 ),
			'include_children' => false,
		);
	}

	/**
	 * Adds the language clause to a query's tax_query (AND with whatever is already there).
	 *
	 * @param \WP_Query $query Query.
	 * @param string    $lang  Language code.
	 */
	public static function scope( \WP_Query $query, $lang ) {
		if ( $query->get( self::SCOPED_FLAG ) ) {
			return;
		}
		$clause   = self::language_clause( $lang );
		$existing = $query->get( 'tax_query' );
		$query->set(
			'tax_query',
			is_array( $existing ) && $existing ? array(
				'relation' => 'AND',
				$existing,
				$clause,
			) : array( $clause )
		); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- language scoping is the purpose.
		$query->set( self::SCOPED_FLAG, $lang );
	}

	/**
	 * Applies language scoping.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function pre_get_posts( $query ) {
		if ( ! $query instanceof \WP_Query || $query->get( self::SCOPED_FLAG ) ) {
			return;
		}
		$requested = $query->get( Router::QUERY_VAR );
		if ( is_string( $requested ) && '' !== $requested ) {
			if ( 'all' === $requested ) {
				return;
			}
			$lang = 'current' === $requested ? Router::effective_language() : $requested;
			if ( ! Languages::exists( $lang ) || ! self::targets_translatable_types( $query ) ) {
				return;
			}
			self::scope( $query, $lang );
			return;
		}

		if ( ! $query->is_main_query() || ! Router::is_frontend() || is_admin() ) {
			return;
		}
		$lang = Router::current_language();
		if ( '' === $lang || ! self::is_listing( $query ) || ! self::targets_translatable_types( $query ) ) {
			return;
		}
		self::scope( $query, $lang );
	}

	/**
	 * Whether a main query lists posts (home/blog, archives, search, feeds) rather than one object.
	 *
	 * Singular slug requests were already resolved to one id by the router; tax queries are ignored
	 * by WordPress on singular queries anyway.
	 *
	 * @param \WP_Query $query Query.
	 * @return bool
	 */
	private static function is_listing( \WP_Query $query ) {
		if ( $query->is_singular() || $query->is_attachment() || $query->is_404() ) {
			return false;
		}
		if ( $query->is_posts_page ) {
			return true; // Resolved to page_id by the router, but lists posts.
		}
		foreach ( array( 'p', 'page_id', 'attachment_id', 'post__in' ) as $key ) {
			if ( ! empty( $query->get( $key ) ) ) {
				return false;
			}
		}
		return $query->is_home() || $query->is_archive() || $query->is_search() || $query->is_feed();
	}

	/**
	 * Whether the query may return translatable post types.
	 *
	 * @param \WP_Query $query Query.
	 * @return bool
	 */
	private static function targets_translatable_types( \WP_Query $query ) {
		$types = $query->get( 'post_type' );
		if ( empty( $types ) || 'any' === $types ) {
			return true;
		}
		foreach ( (array) $types as $type ) {
			if ( Translations::is_translatable_post_type( $type ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Primes translation caches for admin list tables and singular frontend views (no N+1 per row).
	 *
	 * @param \WP_Post[] $posts Posts.
	 * @param \WP_Query  $query Query.
	 * @return \WP_Post[]
	 */
	public static function prime_results( $posts, $query ) {
		if ( ! is_array( $posts ) || ! $posts || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return $posts;
		}
		if ( is_admin() || $query->is_singular() ) {
			$ids = array();
			foreach ( $posts as $post ) {
				if ( $post instanceof \WP_Post && Translations::is_translatable_post_type( $post->post_type ) ) {
					$ids[] = $post->ID;
				}
			}
			if ( $ids ) {
				Translations::prime( $ids );
			}
		}
		return $posts;
	}
}
