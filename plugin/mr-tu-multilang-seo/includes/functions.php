<?php
/**
 * Public procedural API — core i18n part (contract 2.4).
 *
 * Themes and companion plugins call ONLY these functions, always behind function_exists().
 * Business, SEO and TOC functions live in functions-business.php / functions-seo.php / functions-toc.php.
 *
 * @package MrTu\Multilang
 */

defined( 'ABSPATH' ) || exit;

use MrTu\Multilang\Breadcrumbs;
use MrTu\Multilang\Languages;
use MrTu\Multilang\Links;
use MrTu\Multilang\Roles;
use MrTu\Multilang\Router;
use MrTu\Multilang\Switcher;
use MrTu\Multilang\Translations;

/*
 * Languages
 */

if ( ! function_exists( 'mrtu_languages' ) ) {
	/**
	 * Languages.
	 *
	 * @param bool $enabled_only Only enabled languages.
	 * @return array code => [ code, locale, hreflang, og_locale, prefix, name, name_vi, enabled, is_default, tagline ]
	 */
	function mrtu_languages( $enabled_only = true ) {
		return Languages::all( (bool) $enabled_only );
	}
}

if ( ! function_exists( 'mrtu_default_language' ) ) {
	/**
	 * Default language code ('vi').
	 *
	 * @return string
	 */
	function mrtu_default_language() {
		return Languages::default_code();
	}
}

if ( ! function_exists( 'mrtu_current_language' ) ) {
	/**
	 * Current language. Frontend: from the URL (or the queried object for ID-based/preview requests);
	 * admin: '' unless a post is being edited; REST: the `lang` parameter.
	 *
	 * @return string
	 */
	function mrtu_current_language() {
		return Router::current_language();
	}
}

if ( ! function_exists( 'mrtu_get_language' ) ) {
	/**
	 * One language (enabled or not).
	 *
	 * @param string $code Language code.
	 * @return array|null
	 */
	function mrtu_get_language( $code ) {
		return Languages::get( $code );
	}
}

if ( ! function_exists( 'mrtu_is_language_enabled' ) ) {
	/**
	 * Whether a language is enabled.
	 *
	 * @param string $code Language code.
	 * @return bool
	 */
	function mrtu_is_language_enabled( $code ) {
		return Languages::is_enabled( $code );
	}
}

/*
 * Post translations
 */

if ( ! function_exists( 'mrtu_get_post_language' ) ) {
	/**
	 * Language of a post (untagged posts report the default language).
	 *
	 * @param int|WP_Post $post Post.
	 * @return string '' when the post does not exist.
	 */
	function mrtu_get_post_language( $post ) {
		return Translations::get_post_language( $post );
	}
}

if ( ! function_exists( 'mrtu_set_post_language' ) ) {
	/**
	 * Assigns a language to a post.
	 *
	 * @param int    $post_id Post id.
	 * @param string $lang    Language code.
	 * @return bool False when invalid or when the group already has another post in that language.
	 */
	function mrtu_set_post_language( $post_id, $lang ) {
		return Translations::set_post_language( $post_id, $lang );
	}
}

if ( ! function_exists( 'mrtu_get_post_translations' ) ) {
	/**
	 * All translations of a post (all statuses), including the post itself.
	 *
	 * @param int $post_id Post id.
	 * @return array lang => post_id
	 */
	function mrtu_get_post_translations( $post_id ) {
		return Translations::get_post_translations( $post_id );
	}
}

if ( ! function_exists( 'mrtu_get_post_translation' ) ) {
	/**
	 * Translation of a post in a language.
	 *
	 * @param int    $post_id        Post id.
	 * @param string $lang           Language code.
	 * @param bool   $published_only Only a published translation.
	 * @return int 0 when none.
	 */
	function mrtu_get_post_translation( $post_id, $lang, $published_only = true ) {
		return Translations::get_post_translation( $post_id, $lang, $published_only );
	}
}

if ( ! function_exists( 'mrtu_link_translations' ) ) {
	/**
	 * Links posts as translations (merges existing groups; one post per language).
	 *
	 * @param array $map lang => post_id.
	 * @return bool
	 */
	function mrtu_link_translations( array $map ) {
		return Translations::link_translations( $map );
	}
}

if ( ! function_exists( 'mrtu_unlink_translation' ) ) {
	/**
	 * Removes a post from its translation group.
	 *
	 * @param int $post_id Post id.
	 */
	function mrtu_unlink_translation( $post_id ) {
		Translations::unlink_translation( $post_id );
	}
}

/*
 * Term translations
 */

if ( ! function_exists( 'mrtu_get_term_language' ) ) {
	/**
	 * Language of a term (untagged terms report the default language).
	 *
	 * @param int $term_id Term id.
	 * @return string
	 */
	function mrtu_get_term_language( $term_id ) {
		return Translations::get_term_language( $term_id );
	}
}

if ( ! function_exists( 'mrtu_set_term_language' ) ) {
	/**
	 * Assigns a language to a term.
	 *
	 * @param int    $term_id Term id.
	 * @param string $lang    Language code.
	 * @return bool
	 */
	function mrtu_set_term_language( $term_id, $lang ) {
		return Translations::set_term_language( $term_id, $lang );
	}
}

if ( ! function_exists( 'mrtu_get_term_translations' ) ) {
	/**
	 * All translations of a term, including itself.
	 *
	 * @param int $term_id Term id.
	 * @return array lang => term_id
	 */
	function mrtu_get_term_translations( $term_id ) {
		return Translations::get_term_translations( $term_id );
	}
}

if ( ! function_exists( 'mrtu_get_term_translation' ) ) {
	/**
	 * Translation of a term in a language.
	 *
	 * @param int    $term_id Term id.
	 * @param string $lang    Language code.
	 * @return int 0 when none.
	 */
	function mrtu_get_term_translation( $term_id, $lang ) {
		return Translations::get_term_translation( $term_id, $lang );
	}
}

if ( ! function_exists( 'mrtu_link_term_translations' ) ) {
	/**
	 * Links terms of one taxonomy as translations.
	 *
	 * @param array $map lang => term_id.
	 * @return bool
	 */
	function mrtu_link_term_translations( array $map ) {
		return Translations::link_term_translations( $map );
	}
}

/*
 * Page roles
 */

if ( ! function_exists( 'mrtu_page_roles' ) ) {
	/**
	 * Page role registry.
	 *
	 * @return array role => [ label, nav, footer, order ]
	 */
	function mrtu_page_roles() {
		return Roles::registry();
	}
}

if ( ! function_exists( 'mrtu_get_post_role' ) ) {
	/**
	 * Role of a post.
	 *
	 * @param int $post_id Post id.
	 * @return string '' when none.
	 */
	function mrtu_get_post_role( $post_id ) {
		return Roles::get_post_role( $post_id );
	}
}

if ( ! function_exists( 'mrtu_set_post_role' ) ) {
	/**
	 * Sets the role of a post's whole translation group ('' clears it).
	 *
	 * @param int    $post_id Post id.
	 * @param string $role    Role.
	 * @return bool
	 */
	function mrtu_set_post_role( $post_id, $role ) {
		return Roles::set_post_role( $post_id, $role );
	}
}

if ( ! function_exists( 'mrtu_get_page_id_by_role' ) ) {
	/**
	 * Published post id of a role in a language.
	 *
	 * @param string      $role Role.
	 * @param string|null $lang Language (null = current).
	 * @return int 0 when unavailable.
	 */
	function mrtu_get_page_id_by_role( $role, $lang = null ) {
		return Roles::get_page_id_by_role( $role, $lang );
	}
}

if ( ! function_exists( 'mrtu_role_url' ) ) {
	/**
	 * Permalink of a role in a language.
	 *
	 * @param string      $role Role.
	 * @param string|null $lang Language (null = current).
	 * @return string '' when unavailable.
	 */
	function mrtu_role_url( $role, $lang = null ) {
		return Roles::role_url( $role, $lang );
	}
}

/*
 * URLs, switcher, alternates, breadcrumbs
 */

if ( ! function_exists( 'mrtu_home_url' ) ) {
	/**
	 * Language home + path. Use this instead of home_url() in themes (core home_url is never filtered).
	 *
	 * @param string      $path Path relative to the language home.
	 * @param string|null $lang Language (null = current).
	 * @return string
	 */
	function mrtu_home_url( $path = '', $lang = null ) {
		return Links::home_url( $path, $lang );
	}
}

if ( ! function_exists( 'mrtu_switcher_links' ) ) {
	/**
	 * Language switcher entries for the current request.
	 *
	 * @return array lang => [ code, name, hreflang, url, current, available, fallback ]
	 */
	function mrtu_switcher_links() {
		return Switcher::links();
	}
}

if ( ! function_exists( 'mrtu_alternate_urls' ) ) {
	/**
	 * Indexable alternates of the current request, self included, plus 'x-default'.
	 *
	 * @return array hreflang => url
	 */
	function mrtu_alternate_urls() {
		return Switcher::alternates();
	}
}

if ( ! function_exists( 'mrtu_language_switcher' ) ) {
	/**
	 * Accessible default switcher markup.
	 *
	 * @param array $args echo, display (name|code|both), hide_current, hide_missing, class.
	 * @return string
	 */
	function mrtu_language_switcher( $args = array() ) {
		return Switcher::render( $args );
	}
}

if ( ! function_exists( 'mrtu_breadcrumb_trail' ) ) {
	/**
	 * Breadcrumb trail of the current request.
	 *
	 * @return array[] [ [ 'name' => string, 'url' => string ], … ]
	 */
	function mrtu_breadcrumb_trail() {
		return Breadcrumbs::trail();
	}
}
