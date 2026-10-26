<?php
/**
 * Integration tests — table of contents module (contract §2.9).
 *
 * Run:   wp eval-file tests/php/test-toc.php
 * HTTP:  MRTU_TOC_HTTP=1 wp eval-file tests/php/test-toc.php   (needs the dev server on home_url())
 *
 * Self-contained (no shared bootstrap). Prints PASS:/FAIL: lines, then "RESULT: <passed>/<total>";
 * exits non-zero on failure. Creates fixture posts and removes them (and restores options) on shutdown.
 *
 * @package MrTu\Multilang
 */

use MrTu\Multilang\Toc;

$GLOBALS['mrtu_toc_test'] = array(
	'pass'    => 0,
	'fail'    => 0,
	'posts'   => array(),
	'options' => array(),
);

if ( ! function_exists( 'mrtu_toc_test_assert' ) ) {
	/**
	 * Records one assertion.
	 *
	 * @param bool   $condition Result.
	 * @param string $label     Description.
	 * @param string $detail    Extra info printed on failure.
	 */
	function mrtu_toc_test_assert( $condition, $label, $detail = '' ) {
		if ( $condition ) {
			++$GLOBALS['mrtu_toc_test']['pass'];
			echo 'PASS: ' . $label . "\n";
			return;
		}
		++$GLOBALS['mrtu_toc_test']['fail'];
		echo 'FAIL: ' . $label . ( '' !== $detail ? ' — ' . $detail : '' ) . "\n";
	}

	/**
	 * Strict equality assertion.
	 *
	 * @param mixed  $expected Expected.
	 * @param mixed  $actual   Actual.
	 * @param string $label    Description.
	 */
	function mrtu_toc_test_equals( $expected, $actual, $label ) {
		mrtu_toc_test_assert( $expected === $actual, $label, 'expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}

	/**
	 * Filler text with an exact word count (Vietnamese syllables count as words, like the module counts).
	 *
	 * @param int $words Word count.
	 * @return string
	 */
	function mrtu_toc_test_text( $words ) {
		$pool = explode( ' ', 'Mr Tu giao xe máy tận nơi ở Phố Cổ Hà Nội và hướng dẫn khách thuê xe an toàn mỗi ngày với giấy tờ rõ ràng' );
		$out  = array();
		for ( $i = 0; $i < $words; $i++ ) {
			$out[] = $pool[ $i % count( $pool ) ];
		}
		return implode( ' ', $out ) . '.';
	}

	/**
	 * Block paragraph.
	 *
	 * @param int $words Word count.
	 * @return string
	 */
	function mrtu_toc_test_p( $words ) {
		return "<!-- wp:paragraph -->\n<p>" . mrtu_toc_test_text( $words ) . "</p>\n<!-- /wp:paragraph -->\n\n";
	}

	/**
	 * Block heading.
	 *
	 * @param int    $level 2|3.
	 * @param string $inner Inner HTML.
	 * @param string $id    Optional id attribute.
	 * @return string
	 */
	function mrtu_toc_test_h( $level, $inner, $id = '' ) {
		$attrs = 3 === $level ? ' {"level":3}' : '';
		return '<!-- wp:heading' . $attrs . " -->\n<h" . $level . ' class="wp-block-heading"' . ( $id ? ' id="' . $id . '"' : '' ) . '>' . $inner . '</h' . $level . ">\n<!-- /wp:heading -->\n\n";
	}

	/**
	 * Creates a fixture post (removed on shutdown).
	 *
	 * @param string $content Content.
	 * @param array  $args    Extra wp_insert_post args.
	 * @return int
	 */
	function mrtu_toc_test_post( $content, $args = array() ) {
		$id = wp_insert_post(
			array_merge(
				array(
					'post_title'   => 'TOC test ' . wp_generate_password( 6, false ),
					'post_content' => wp_slash( $content ),
					'post_status'  => 'publish',
					'post_type'    => 'post',
				),
				$args
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			echo 'FAIL: fixture insert — ' . esc_html( $id->get_error_message() ) . "\n";
			exit( 1 );
		}
		$GLOBALS['mrtu_toc_test']['posts'][] = $id;
		return (int) $id;
	}

	/**
	 * Remembers an option once so it can be restored on shutdown.
	 *
	 * @param string $name Option name.
	 */
	function mrtu_toc_test_backup_option( $name ) {
		if ( ! array_key_exists( $name, $GLOBALS['mrtu_toc_test']['options'] ) ) {
			$GLOBALS['mrtu_toc_test']['options'][ $name ] = get_option( $name, '__mrtu_missing__' );
		}
	}

	/**
	 * Sets mrtu_ml_settings['toc'] (other keys untouched).
	 *
	 * @param array $toc TOC settings.
	 */
	function mrtu_toc_test_settings( $toc ) {
		mrtu_toc_test_backup_option( 'mrtu_ml_settings' );
		$all        = get_option( 'mrtu_ml_settings', array() );
		$all        = is_array( $all ) ? $all : array();
		$all['toc'] = $toc;
		update_option( 'mrtu_ml_settings', $all );
	}

	/**
	 * Emulates a singular main query for a post (like WP_UnitTestCase::go_to()).
	 *
	 * @param int $post_id Post id.
	 */
	function mrtu_toc_test_go_to( $post_id ) {
		$post                    = get_post( $post_id );
		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
		$GLOBALS['wp_query']->query( 'page' === $post->post_type ? array( 'page_id' => $post_id ) : array( 'p' => $post_id ) );
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		Toc::flush_cache();
	}

	/**
	 * Renders a post's content like the template does: main query → the loop → the_content.
	 *
	 * @param int  $post_id    Post id.
	 * @param bool $enter_loop Whether to call the_post() first.
	 * @param bool $leave_loop Whether to leave the loop afterwards.
	 * @return string
	 */
	function mrtu_toc_test_render( $post_id, $enter_loop = true, $leave_loop = true ) {
		mrtu_toc_test_go_to( $post_id );
		if ( $enter_loop ) {
			$GLOBALS['wp_query']->the_post();
		}
		$html = apply_filters( 'the_content', get_the_content() );
		if ( $leave_loop ) {
			$GLOBALS['wp_query']->in_the_loop = false;
		}
		return $html;
	}

	/**
	 * Number of inline TOC roots in HTML.
	 *
	 * @param string $html HTML.
	 * @return int
	 */
	function mrtu_toc_test_count_toc( $html ) {
		return preg_match_all( '~<details class="mrtu-toc" data-mrtu-toc>~', $html );
	}

	/**
	 * Link targets of the inline TOC, in order.
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	function mrtu_toc_test_toc_hrefs( $html ) {
		if ( ! preg_match( '~<details class="mrtu-toc" data-mrtu-toc>.*?</details>~s', $html, $m ) ) {
			return array();
		}
		preg_match_all( '~<a href="#([^"]*)">~', $m[0], $links );
		return $links[1];
	}

	/**
	 * Ids of the items.
	 *
	 * @param array $items Items.
	 * @return string[]
	 */
	function mrtu_toc_test_ids( $items ) {
		return array_map(
			static function ( $item ) {
				return $item['id'];
			},
			$items
		);
	}
}

register_shutdown_function(
	static function () {
		foreach ( $GLOBALS['mrtu_toc_test']['posts'] as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		foreach ( $GLOBALS['mrtu_toc_test']['options'] as $name => $value ) {
			if ( '__mrtu_missing__' === $value ) {
				delete_option( $name );
			} else {
				update_option( $name, $value );
			}
		}
	}
);

wp_set_current_user( 1 ); // Administrator: unfiltered_html, like the owner editing in wp-admin.
mrtu_toc_test_settings( array() ); // Defaults.
$title = __( 'Table of contents', 'mr-tu-multilang-seo' );

/*
 * 1. Wiring.
 */
mrtu_toc_test_assert( class_exists( 'MrTu\Multilang\Toc' ), 'Toc class autoloads' );
mrtu_toc_test_assert( function_exists( 'mrtu_toc_items' ) && function_exists( 'mrtu_toc_enabled_for' ), 'public functions mrtu_toc_items() / mrtu_toc_enabled_for() exist' );
mrtu_toc_test_equals( 12, has_filter( 'the_content', array( 'MrTu\Multilang\Toc', 'filter_content' ) ), 'the_content filter registered at priority 12' );
mrtu_toc_test_assert( registered_meta_key_exists( 'post', '_mrtu_toc', 'post' ) && registered_meta_key_exists( 'post', '_mrtu_toc', 'page' ), '_mrtu_toc meta registered for post + page' );

/*
 * 2. Settings.
 */
mrtu_toc_test_equals(
	array(
		'enabled'      => true,
		'min_headings' => 3,
		'min_words'    => 350,
		'position'     => 'after_first_h2',
		'post_types'   => array( 'post', 'page' ),
	),
	Toc::settings(),
	'settings() returns contract defaults when the option has no toc key'
);
$clean = Toc::sanitize_settings(
	array(
		'enabled'      => '0',
		'min_headings' => '-4',
		'min_words'    => '120abc',
		'position'     => 'bottom',
		'post_types'   => 'post, Page ,attachment,my_type!',
	)
);
mrtu_toc_test_equals( false, $clean['enabled'], 'sanitize_settings: "0" → enabled false' );
mrtu_toc_test_equals( 1, $clean['min_headings'], 'sanitize_settings: negative min_headings clamps to 1' );
mrtu_toc_test_equals( 120, $clean['min_words'], 'sanitize_settings: min_words absint' );
mrtu_toc_test_equals( 'after_first_h2', $clean['position'], 'sanitize_settings: unknown position → default' );
mrtu_toc_test_equals( array( 'post', 'page', 'my_type' ), $clean['post_types'], 'sanitize_settings: post_types from CSV, keys sanitized, attachment dropped' );
mrtu_toc_test_equals( 0, Toc::sanitize_settings( array( 'min_words' => '-20' ) )['min_words'], 'sanitize_settings: negative min_words clamps to 0' );
mrtu_toc_test_equals( 50, Toc::sanitize_settings( array( 'min_headings' => 999 ) )['min_headings'], 'sanitize_settings: min_headings capped at 50' );
$partial = Toc::sanitize_settings( array( 'position' => 'before_first_h2' ) );
mrtu_toc_test_assert( 'before_first_h2' === $partial['position'] && 3 === $partial['min_headings'] && array( 'post', 'page' ) === $partial['post_types'], 'sanitize_settings: missing keys merge with defaults' );
mrtu_toc_test_equals( array(), Toc::sanitize_settings( array( 'post_types' => array() ) )['post_types'], 'sanitize_settings: empty post_types allowed (TOC off everywhere)' );

/*
 * 3. Slugs: clean ASCII, Vietnamese đ, German/French/Spanish, empty → section.
 */
$slugs = array(
	array( 'Đặt cọc bao nhiêu?', '', 'dat-coc-bao-nhieu' ),
	array( 'Führerschein & Straße', '', 'fuhrerschein-strasse' ),
	array( 'Führerschein & Straße', 'de', 'fuehrerschein-strasse' ),
	array( 'Où récupérer la moto ?', '', 'ou-recuperer-la-moto' ),
	array( "Où récupérer la moto\u{00A0}?", 'fr', 'ou-recuperer-la-moto' ),
	array( '¿Necesito pasaporte?', 'es', 'necesito-pasaporte' ),
	array( 'Ưu đãi THUÊ XE THÁNG – Hồ Gươm', 'vi', 'uu-dai-thue-xe-thang-ho-guom' ),
	array( "Don\u{2019}t forget your helmet", '', 'dont-forget-your-helmet' ),
	array( 'Tom & Jerry; A < B > C', '', 'tom-jerry-a-b-c' ),
	array( '😀 ¿? 漢字', '', 'section' ),
	array( '', '', 'section' ),
);
foreach ( $slugs as $case ) {
	mrtu_toc_test_equals( $case[2], Toc::slugify( $case[0], $case[1] ), 'slugify "' . $case[0] . '"' . ( $case[1] ? ' [' . $case[1] . ']' : '' ) );
}
if ( class_exists( 'Normalizer' ) ) {
	mrtu_toc_test_equals( 'dat-coc-bao-nhieu', Toc::slugify( Normalizer::normalize( 'Đặt cọc bao nhiêu?', Normalizer::FORM_D ) ), 'slugify: decomposed (NFD) Vietnamese input' );
}
$long = Toc::slugify( str_repeat( 'thuê xe máy hà nội ', 20 ) );
mrtu_toc_test_assert( strlen( $long ) <= 80 && '-' !== substr( $long, -1 ), 'slugify: long text capped at 80 chars on a word boundary', $long );
add_filter(
	'locale',
	$mrtu_de_locale = static function () {
		return 'de_DE';
	}
);
mrtu_toc_test_equals( 'fuhrerschein-strasse', Toc::slugify( 'Führerschein & Straße' ), 'slugify: request locale does not change ids (deterministic)' );
remove_filter( 'locale', $mrtu_de_locale );
$all_ascii = true;
foreach ( $slugs as $case ) {
	$all_ascii = $all_ascii && (bool) preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Toc::slugify( $case[0], $case[1] ) );
}
mrtu_toc_test_assert( $all_ascii, 'every generated slug is clean lowercase ASCII' );

/*
 * 4. Parsing.
 */
$html  = '<p>Intro</p>'
	. '<h3>Early H3</h3>'
	. '<h2 class="wp-block-heading" id="x"><strong>Đặt cọc</strong> <em>bao nhiêu</em>?</h2>'
	. '<h3 data-note="a>b" class=\'q\'>Führerschein &amp; Straße</h3>'
	. '<h3>Giá &amp; tiền cọc &#8211; 2026</h3>'
	. '<details class="wp-block-details"><summary>Q</summary><h2>Inside details</h2></details>'
	. '<section class="wp-block-group mrtu-toc-skip"><div><h3>Inside skip</h3></div></section>'
	. '<h2 class="wp-block-heading mrtu-toc-skip">Skipped itself</h2>'
	. '<div class="mrtu-fleet"><h3 class="mrtu-vehicle__name">Honda Vision</h3></div>'
	. '<H2 CLASS="upper">Où récupérer la moto&nbsp;?</H2>'
	. '<h2 id="">¿Necesito pasaporte?</h2>'
	. '<h2>Main</h2><div id="faq"></div><h2>FAQ</h2><h2>FAQ</h2>'
	. '<h4>Not in TOC</h4><h1>Not in TOC either</h1><h2></h2><h2><img src="x.png" alt=""></h2>'
	. '<script>var tpl = "<h2>no</h2>";</script><!-- <h2>no</h2> --><template><h2>no</h2></template>'
	. "<h2>Line<br>break \xC3\x28 bad</h2>";
$data  = Toc::analyze( $html );
$ids   = mrtu_toc_test_ids( $data['items'] );
mrtu_toc_test_equals( array( 'early-h3', 'x', 'fuhrerschein-strasse', 'gia-tien-coc-2026', 'ou-recuperer-la-moto', 'necesito-pasaporte', 'main-2', 'faq-2', 'faq-3', 'line-break-bad' ), $ids, 'H2/H3 ids: existing kept, generated, unique, skips details/.mrtu-toc-skip/.mrtu-fleet/h1/h4/empty/script/comment/template' );
mrtu_toc_test_equals( array( 3, 2, 3, 3, 2, 2, 2, 2, 2, 2 ), array_map( 'intval', wp_list_pluck( $data['items'], 'level' ) ), 'levels recorded' );
mrtu_toc_test_equals( 'Đặt cọc bao nhiêu?', $data['items'][1]['text'], 'text from nested inline markup' );
mrtu_toc_test_equals( "Giá & tiền cọc \u{2013} 2026", $data['items'][3]['text'], 'entities decoded in item text' );
mrtu_toc_test_equals( "Où récupérer la moto\u{00A0}?", $data['items'][4]['text'], 'French no-break space kept in display text' );
mrtu_toc_test_assert( false !== strpos( $data['html'], '<h2 class="wp-block-heading" id="x"><strong>Đặt cọc</strong>' ), 'existing id: start tag left byte-identical' );
mrtu_toc_test_assert( false !== strpos( $data['html'], '<h3 id="fuhrerschein-strasse" data-note="a>b" class=\'q\'>' ), 'attributes with ">" in quoted values preserved' );
mrtu_toc_test_assert( false !== strpos( $data['html'], '<h2 id="necesito-pasaporte">' ) && false === strpos( $data['html'], 'id=""' ), 'empty id attribute replaced, never duplicated' );
mrtu_toc_test_assert( false !== strpos( $data['html'], '<h2>Inside details</h2>' ) && false !== strpos( $data['html'], '<h3 class="mrtu-vehicle__name">Honda Vision</h3>' ), 'skipped headings untouched' );
mrtu_toc_test_assert( false !== strpos( $data['html'], 'var tpl = "<h2>no</h2>";' ) && false !== strpos( $data['html'], '<!-- <h2>no</h2> -->' ), 'script and comment content untouched' );
mrtu_toc_test_equals( strlen( $html ) + strlen( ' id="early-h3"' ) + strlen( ' id="fuhrerschein-strasse"' ) + strlen( ' id="gia-tien-coc-2026"' ) + strlen( ' id="ou-recuperer-la-moto"' ) + strlen( ' id="necesito-pasaporte"' ) - strlen( ' id=""' ) + strlen( ' id="main-2"' ) + strlen( ' id="faq-2"' ) + strlen( ' id="faq-3"' ) + strlen( ' id="line-break-bad"' ), strlen( $data['html'] ), 'only heading start tags change (byte count)' );
$again = Toc::analyze( $html );
mrtu_toc_test_assert( $again === $data, 'analyze() is deterministic (same html, items, offsets)' );
mrtu_toc_test_equals( $data['html'], Toc::analyze( $data['html'] )['html'], 'second pass keeps the ids it assigned (idempotent)' );
mrtu_toc_test_equals( '<p>No headings</p>', Toc::process( '<p>No headings</p>' ), 'content without headings is returned unchanged' );

/*
 * 5. Markup + nesting + position.
 */
$items    = array(
	array(
		'id'    => 'e',
		'text'  => 'Early',
		'level' => 3,
	),
	array(
		'id'    => 'a',
		'text'  => 'A & B',
		'level' => 2,
	),
	array(
		'id'    => 'a1',
		'text'  => 'A1',
		'level' => 3,
	),
	array(
		'id'    => 'a2',
		'text'  => 'A2',
		'level' => 3,
	),
	array(
		'id'    => 'b',
		'text'  => '<B>',
		'level' => 2,
	),
);
$expected = '<details class="mrtu-toc" data-mrtu-toc>' . "\n"
	. '<summary class="mrtu-toc__summary"><span class="mrtu-toc__title">' . esc_html( $title ) . '</span><span class="mrtu-toc__count">5</span></summary>' . "\n"
	. '<nav class="mrtu-toc__nav" aria-label="' . esc_attr( $title ) . '"><ol class="mrtu-toc__list">'
	. '<li class="mrtu-toc__item mrtu-toc__item--h3"><a href="#e">Early</a></li>'
	. '<li class="mrtu-toc__item mrtu-toc__item--h2"><a href="#a">A &amp; B</a><ol>'
	. '<li class="mrtu-toc__item mrtu-toc__item--h3"><a href="#a1">A1</a></li>'
	. '<li class="mrtu-toc__item mrtu-toc__item--h3"><a href="#a2">A2</a></li></ol></li>'
	. '<li class="mrtu-toc__item mrtu-toc__item--h2"><a href="#b">&lt;B&gt;</a></li>'
	. '</ol></nav>' . "\n" . '</details>';
mrtu_toc_test_equals( $expected, Toc::markup( $items ), 'markup matches contract §2.9 exactly (closed details, count, nav aria-label, nested ol, escaped text)' );

$doc   = '<p>Lead</p><h3>Pre</h3><h2>One</h2><p>x</p><h3>One A</h3><h2>Two</h2>';
$after = Toc::process( $doc );
mrtu_toc_test_assert( false !== strpos( $after, '<h2 id="one">One</h2>' . "\n" . '<details class="mrtu-toc"' ), 'after_first_h2: TOC directly after the first </h2>' );
$before = Toc::process( $doc, '', 'before_first_h2' );
mrtu_toc_test_assert( false !== strpos( $before, '</details>' . "\n" . '<h2 id="one">One</h2>' ) && strpos( $before, '<h3 id="pre">' ) < strpos( $before, '<details' ), 'before_first_h2: TOC directly before the first <h2>' );
$only_h3 = Toc::process( '<p>Lead</p><h3>A</h3><h3>B</h3>' );
mrtu_toc_test_assert( 0 === strpos( $only_h3, '<p>Lead</p>' . "\n" . '<details' ), 'no H2: TOC before the first heading' );
mrtu_toc_test_equals( 1, mrtu_toc_test_count_toc( $after ), 'process() inserts exactly one TOC' );
mrtu_toc_test_assert( (bool) preg_match( '~<li class="mrtu-toc__item mrtu-toc__item--h2"><a href="#one">One</a><ol><li class="mrtu-toc__item mrtu-toc__item--h3"><a href="#one-a">One A</a></li></ol></li>~', $after ), 'H3 nested under the preceding H2' );
mrtu_toc_test_assert( (bool) preg_match( '~<ol class="mrtu-toc__list"><li class="mrtu-toc__item mrtu-toc__item--h3"><a href="#pre">Pre</a></li>~', $after ), 'H3 before any H2 is top-level' );

/*
 * 6. the_content integration (real block content, simulated main query + loop).
 */
$long_content = mrtu_toc_test_p( 80 )
	. mrtu_toc_test_h( 2, 'Đặt cọc bao nhiêu?' ) . mrtu_toc_test_p( 70 )
	. mrtu_toc_test_h( 3, 'Führerschein &amp; Straße' ) . mrtu_toc_test_p( 60 )
	. mrtu_toc_test_h( 2, '<strong>Où récupérer</strong> la moto ?', 'pickup' ) . mrtu_toc_test_p( 60 )
	. mrtu_toc_test_h( 2, '¿Necesito pasaporte?' ) . mrtu_toc_test_p( 60 )
	. "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>Hỏi nhanh</summary><!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Inside details</h2>\n<!-- /wp:heading --></details>\n<!-- /wp:details -->\n\n"
	. "<!-- wp:group {\"tagName\":\"section\",\"className\":\"mrtu-toc-skip\"} -->\n<section class=\"wp-block-group mrtu-toc-skip\">" . mrtu_toc_test_h( 3, 'Skipped group heading' ) . "</section>\n<!-- /wp:group -->\n\n"
	. mrtu_toc_test_h( 2, "Don't forget the helmet" ) . mrtu_toc_test_p( 60 );
$long_ids     = array( 'dat-coc-bao-nhieu', 'fuhrerschein-strasse', 'pickup', 'necesito-pasaporte', 'dont-forget-the-helmet' );
$post_id      = mrtu_toc_test_post( $long_content );

wp_dequeue_script( 'mrtu-toc' );
$rendered = mrtu_toc_test_render( $post_id );
mrtu_toc_test_equals( 1, mrtu_toc_test_count_toc( $rendered ), 'singular post in the loop: one TOC inserted' );
mrtu_toc_test_equals( $long_ids, mrtu_toc_test_toc_hrefs( $rendered ), 'inline TOC links (ids) in document order' );
mrtu_toc_test_assert( false !== strpos( $rendered, '<span class="mrtu-toc__count">5</span>' ), 'count = number of items (5)' );
mrtu_toc_test_assert( (bool) preg_match( '~<h2 id="dat-coc-bao-nhieu" class="wp-block-heading">Đặt cọc bao nhiêu\?</h2>\s*<details class="mrtu-toc" data-mrtu-toc>~u', $rendered ), 'inserted right after the first H2 of real content' );
mrtu_toc_test_assert( false === strpos( $rendered, '<details class="mrtu-toc" data-mrtu-toc open' ), 'details closed by default' );
foreach ( $long_ids as $long_id ) {
	mrtu_toc_test_assert( 1 === substr_count( $rendered, ' id="' . $long_id . '"' ), 'heading id present once: ' . $long_id );
}
mrtu_toc_test_assert( false !== strpos( $rendered, 'Don&#8217;t forget the helmet' ), 'texturized heading text (wptexturize ran before priority 12)' );
mrtu_toc_test_assert( wp_script_is( 'mrtu-toc', 'enqueued' ) && wp_style_is( 'mrtu-toc', 'enqueued' ), 'toc.js + toc.css enqueued when a TOC is printed' );
mrtu_toc_test_equals( 'defer', wp_scripts()->get_data( 'mrtu-toc', 'strategy' ), 'toc.js uses the defer strategy' );
mrtu_toc_test_equals( 1, (int) wp_scripts()->get_data( 'mrtu-toc', 'group' ), 'toc.js prints in the footer' );
wp_dequeue_script( 'mrtu-toc' );
wp_dequeue_style( 'mrtu-toc' );
mrtu_toc_test_go_to( $post_id );
// Simulate a block theme regardless of the active theme (the env may run the classic Mr Tu theme).
$mrtu_block = static function () {
	return 'twentytwentyfive';
};
add_filter( 'stylesheet', $mrtu_block );
add_filter( 'template', $mrtu_block );
Toc::enqueue_style_for_queried_post();
mrtu_toc_test_assert( ! wp_style_is( 'mrtu-toc', 'enqueued' ), 'block theme: no early toc.css (the TOC render enqueues it before wp_head)' );
remove_filter( 'stylesheet', $mrtu_block );
remove_filter( 'template', $mrtu_block );
$mrtu_classic = static function () {
	return 'twentytwentyone';
};
add_filter( 'stylesheet', $mrtu_classic );
add_filter( 'template', $mrtu_classic );
Toc::enqueue_style_for_queried_post();
mrtu_toc_test_assert( wp_style_is( 'mrtu-toc', 'enqueued' ) && ! wp_script_is( 'mrtu-toc', 'enqueued' ), 'classic theme: toc.css enqueued at wp_enqueue_scripts for an eligible post, toc.js not' );
mrtu_toc_test_equals( MRTU_ML_DIR . 'assets/css/toc.css', wp_styles()->get_data( 'mrtu-toc', 'path' ), 'toc.css registered with path data (core may inline it)' );
wp_dequeue_style( 'mrtu-toc' );
update_post_meta( $post_id, '_mrtu_toc', 'off' );
Toc::enqueue_style_for_queried_post();
mrtu_toc_test_assert( ! wp_style_is( 'mrtu-toc', 'enqueued' ), "classic theme: no toc.css when _mrtu_toc is 'off'" );
delete_post_meta( $post_id, '_mrtu_toc' );
remove_filter( 'stylesheet', $mrtu_classic );
remove_filter( 'template', $mrtu_classic );

$js_size = filesize( dirname( MRTU_ML_FILE ) . '/assets/js/toc.js' );
mrtu_toc_test_assert( $js_size > 0 && $js_size < 4096, 'toc.js source is small (' . $js_size . ' bytes; < 2 KB minified, checked by the e2e suite)' );

$second = mrtu_toc_test_render( $post_id );
mrtu_toc_test_equals( $rendered, $second, 'deterministic: rendering twice gives identical output' );

mrtu_toc_test_go_to( $post_id );
$GLOBALS['wp_query']->the_post();
$twice = apply_filters( 'the_content', apply_filters( 'the_content', get_the_content() ) );
mrtu_toc_test_equals( 1, mrtu_toc_test_count_toc( $twice ), 'no double insertion when the_content runs over its own output' );
$excerpt = get_the_excerpt();
mrtu_toc_test_assert( false === strpos( $excerpt, $title ), 'excerpt (get_the_excerpt → the_content) never contains the TOC' );
$GLOBALS['wp_query']->in_the_loop = false;

/*
 * 7. mrtu_toc_items() equals the inline items.
 */
mrtu_toc_test_go_to( $post_id );
$GLOBALS['wp_query']->the_post();
$pre_items  = mrtu_toc_items(); // Before the_content: side-effect-light render.
$inline     = apply_filters( 'the_content', get_the_content() );
$post_items = mrtu_toc_items(); // After: the cached inline result.
$GLOBALS['wp_query']->in_the_loop = false;
mrtu_toc_test_equals( $long_ids, mrtu_toc_test_ids( $pre_items ), 'mrtu_toc_items() before render: ids' );
mrtu_toc_test_assert( $pre_items === $post_items, 'mrtu_toc_items() identical before and after the inline render' );
mrtu_toc_test_equals( mrtu_toc_test_toc_hrefs( $inline ), mrtu_toc_test_ids( $post_items ), 'mrtu_toc_items() ids == inline TOC hrefs' );
mrtu_toc_test_equals( "Don\u{2019}t forget the helmet", $post_items[4]['text'], 'items carry plain decoded text' );
mrtu_toc_test_equals( array( 2, 3, 2, 2, 2 ), wp_list_pluck( $post_items, 'level' ), 'items carry levels' );
mrtu_toc_test_equals( $long_ids, mrtu_toc_test_ids( mrtu_toc_items( get_post( $post_id ) ) ), 'mrtu_toc_items( $post ) outside the loop' );
mrtu_toc_test_assert( mrtu_toc_enabled_for( $post_id ), 'mrtu_toc_enabled_for() true for an eligible post' );

/*
 * 8. Request context guards.
 */
$outside = mrtu_toc_test_render( $post_id, false );
mrtu_toc_test_equals( 0, mrtu_toc_test_count_toc( $outside ), 'not in the loop → no TOC' );

$other_id = mrtu_toc_test_post( $long_content );
mrtu_toc_test_go_to( $post_id );
$GLOBALS['wp_query']->the_post();
$GLOBALS['post'] = get_post( $other_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
setup_postdata( $GLOBALS['post'] );
$secondary = apply_filters( 'the_content', get_the_content() );
$GLOBALS['wp_query']->in_the_loop = false;
mrtu_toc_test_equals( 0, mrtu_toc_test_count_toc( $secondary ), 'another post rendered inside the main loop → no TOC' );

mrtu_toc_test_go_to( $post_id );
$GLOBALS['wp_query']->the_post();
$GLOBALS['wp_query']->is_feed = true;
$feed                         = apply_filters( 'the_content', get_the_content() );
$GLOBALS['wp_query']->is_feed = false;
$GLOBALS['wp_query']->in_the_loop = false;
mrtu_toc_test_equals( 0, mrtu_toc_test_count_toc( $feed ), 'feed → no TOC' );

$secondary_query = new WP_Query( array( 'p' => $post_id ) );
$GLOBALS['wp_query'] = $secondary_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$secondary_query->the_post();
$not_main = apply_filters( 'the_content', get_the_content() );
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
wp_reset_postdata();
mrtu_toc_test_equals( 0, mrtu_toc_test_count_toc( $not_main ), 'not the main query → no TOC' );

/*
 * 9. Thresholds, settings, per-post switch, filter.
 */
$short_id = mrtu_toc_test_post( mrtu_toc_test_h( 2, 'Một' ) . mrtu_toc_test_p( 150 ) . mrtu_toc_test_h( 2, 'Hai' ) . mrtu_toc_test_p( 150 ) . mrtu_toc_test_h( 2, 'Ba' ) . mrtu_toc_test_p( 20 ) );
mrtu_toc_test_equals( 0, mrtu_toc_test_count_toc( mrtu_toc_test_render( $short_id ) ), 'below min_words (≈ 330 < 350) → no TOC' );
mrtu_toc_test_equals( array(), mrtu_toc_items( $short_id ), 'below min_words → mrtu_toc_items() empty' );
mrtu_toc_test_settings( array( 'min_words' => 300 ) );
mrtu_toc_test_equals( 1, mrtu_toc_test_count_toc( mrtu_toc_test_render( $short_id ) ), 'min_words lowered to 300 → TOC' );

$two_id = mrtu_toc_test_post( mrtu_toc_test_h( 2, 'Một' ) . mrtu_toc_test_p( 200 ) . mrtu_toc_test_h( 2, 'Hai' ) . mrtu_toc_test_p( 200 ) );
mrtu_toc_test_settings( array() );
mrtu_toc_test_equals( 0, mrtu_toc_test_count_toc( mrtu_toc_test_render( $two_id ) ), '2 headings < min_headings 3 → no TOC' );
mrtu_toc_test_settings( array( 'min_headings' => 2 ) );
mrtu_toc_test_equals( 1, mrtu_toc_test_count_toc( mrtu_toc_test_render( $two_id ) ), 'min_headings 2 → TOC' );

mrtu_toc_test_settings( array( 'position' => 'before_first_h2' ) );
mrtu_toc_test_assert( (bool) preg_match( '~</details>\s*<h2 id="dat-coc-bao-nhieu"~', mrtu_toc_test_render( $post_id ) ), 'position before_first_h2 from settings' );

mrtu_toc_test_settings( array( 'post_types' => array( 'page' ) ) );
mrtu_toc_test_equals( 0, mrtu_toc_test_count_toc( mrtu_toc_test_render( $post_id ) ), 'post type not allowed → no TOC' );
mrtu_toc_test_assert( ! mrtu_toc_enabled_for( $post_id ), 'post type not allowed → mrtu_toc_enabled_for() false' );

mrtu_toc_test_settings( array( 'enabled' => false ) );
mrtu_toc_test_equals( 0, mrtu_toc_test_count_toc( mrtu_toc_test_render( $post_id ) ), 'TOC disabled in settings → no TOC' );
mrtu_toc_test_settings( array() );

update_post_meta( $post_id, '_mrtu_toc', ' OFF ' );
mrtu_toc_test_equals( 'off', get_post_meta( $post_id, '_mrtu_toc', true ), '_mrtu_toc sanitized to "off"' );
mrtu_toc_test_equals( 0, mrtu_toc_test_count_toc( mrtu_toc_test_render( $post_id ) ), "_mrtu_toc 'off' → no TOC" );
mrtu_toc_test_equals( array(), mrtu_toc_items( $post_id ), "_mrtu_toc 'off' → mrtu_toc_items() empty" );
delete_post_meta( $post_id, '_mrtu_toc' );

add_filter( 'mrtu_toc_enabled', '__return_false' );
mrtu_toc_test_equals( 0, mrtu_toc_test_count_toc( mrtu_toc_test_render( $post_id ) ), 'mrtu_toc_enabled filter → false disables' );
remove_filter( 'mrtu_toc_enabled', '__return_false' );
mrtu_toc_test_equals( 1, mrtu_toc_test_count_toc( mrtu_toc_test_render( $post_id ) ), 'TOC back after removing the filter' );

/*
 * 10. Front page + password.
 */
$front_id = mrtu_toc_test_post( $long_content, array( 'post_type' => 'page' ) );
mrtu_toc_test_equals( 1, mrtu_toc_test_count_toc( mrtu_toc_test_render( $front_id ) ), 'page with enough headings → TOC' );
mrtu_toc_test_backup_option( 'show_on_front' );
mrtu_toc_test_backup_option( 'page_on_front' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $front_id );
mrtu_toc_test_equals( 0, mrtu_toc_test_count_toc( mrtu_toc_test_render( $front_id ) ), 'static front page → no TOC' );
mrtu_toc_test_equals( array(), mrtu_toc_items( $front_id ), 'static front page → mrtu_toc_items() empty' );
update_option( 'show_on_front', 'posts' );
update_option( 'page_on_front', 0 );

$locked_id = mrtu_toc_test_post( $long_content, array( 'post_password' => 'mrtu-secret' ) );
mrtu_toc_test_go_to( $locked_id );
$GLOBALS['wp_query']->the_post();
$locked = apply_filters( 'the_content', get_post( $locked_id )->post_content );
$GLOBALS['wp_query']->in_the_loop = false;
mrtu_toc_test_equals( 0, mrtu_toc_test_count_toc( $locked ), 'password-protected post → no TOC' );
mrtu_toc_test_equals( array(), mrtu_toc_items( $locked_id ), 'password-protected post → mrtu_toc_items() empty (no heading leak)' );
mrtu_toc_test_assert( ! mrtu_toc_enabled_for( $locked_id ), 'password-protected post → mrtu_toc_enabled_for() false' );

/*
 * 11. HTTP (optional, needs the dev server: MRTU_TOC_HTTP=1).
 */
if ( getenv( 'MRTU_TOC_HTTP' ) ) {
	$url      = get_permalink( $post_id );
	$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
	$body     = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );
	mrtu_toc_test_equals( 200, (int) wp_remote_retrieve_response_code( $response ), 'HTTP ' . $url . ' → 200' );
	mrtu_toc_test_equals( 1, mrtu_toc_test_count_toc( $body ), 'HTTP: exactly one TOC in the page' );
	mrtu_toc_test_equals( $long_ids, mrtu_toc_test_toc_hrefs( $body ), 'HTTP: TOC links in order' );
	mrtu_toc_test_assert( (bool) preg_match( '~<script[^>]+src="[^"]*assets/js/toc\.js\?ver=[^"]*"[^>]*defer~', $body ) || (bool) preg_match( '~<script[^>]+defer[^>]+src="[^"]*assets/js/toc\.js~', $body ), 'HTTP: toc.js printed with defer' );
	mrtu_toc_test_assert( (bool) preg_match( '~<link[^>]+href=[\'"][^\'"]*assets/css/toc\.css|<style[^>]+id=[\'"]mrtu-toc-inline-css~', $body ) && strpos( $body, 'mrtu-toc' ) < strpos( $body, '</head>' ), 'HTTP: toc.css linked or inlined in <head>' );

	$rest      = wp_remote_get( rest_url( 'wp/v2/posts/' . $post_id ), array( 'timeout' => 20 ) );
	$rest_body = json_decode( wp_remote_retrieve_body( $rest ), true );
	mrtu_toc_test_assert( isset( $rest_body['content']['rendered'] ) && false !== strpos( $rest_body['content']['rendered'], 'Đặt cọc' ) && 0 === mrtu_toc_test_count_toc( $rest_body['content']['rendered'] ), 'HTTP: REST content.rendered has no TOC' );

	$feed_body = wp_remote_retrieve_body( wp_remote_get( add_query_arg( 'feed', 'rss2', home_url( '/' ) ), array( 'timeout' => 20 ) ) );
	mrtu_toc_test_assert( false !== strpos( $feed_body, '<rss' ) && false === strpos( $feed_body, 'data-mrtu-toc' ), 'HTTP: RSS feed has no TOC' );

	$locked_body = wp_remote_retrieve_body( wp_remote_get( get_permalink( $locked_id ), array( 'timeout' => 20 ) ) );
	mrtu_toc_test_assert( false !== strpos( $locked_body, 'post-password-form' ) && 0 === mrtu_toc_test_count_toc( $locked_body ) && false === strpos( $locked_body, 'necesito-pasaporte' ), 'HTTP: password-protected post shows the form, no TOC, no heading ids' );
} else {
	echo "SKIP: HTTP checks (run with MRTU_TOC_HTTP=1 while the dev server serves home_url())\n";
}

$mrtu_toc_total = $GLOBALS['mrtu_toc_test']['pass'] + $GLOBALS['mrtu_toc_test']['fail'];
echo 'RESULT: ' . (int) $GLOBALS['mrtu_toc_test']['pass'] . '/' . (int) $mrtu_toc_total . "\n";
exit( $GLOBALS['mrtu_toc_test']['fail'] > 0 ? 1 : 0 );
