<?php
/**
 * Seed content validator. Run inside WordPress (needs parse_blocks):
 *   wp eval-file scripts/validate-content.php [lang ...]
 * Prints ERROR/WARN lines and a summary; exits 1 when any ERROR is found.
 *
 * Checks: manifest schema + SEO lengths, allowed blocks only, no H1, heading order, no inline styles/images/tables,
 * same-language root-relative links that resolve to known slugs, forbidden claims/data, mixed-language heuristics,
 * required data blocks per role, FAQ count on guide-faq.
 *
 * @package MrTu
 */

// phpcs:disable -- developer tool, not shipped.

$repo    = dirname( __DIR__ );
$content = $repo . '/content';
$plan    = json_decode( (string) file_get_contents( $repo . '/config/seo-plan.json' ), true );
$langs   = array( 'vi', 'en', 'de', 'fr', 'es' );
$args    = isset( $args ) && is_array( $args ) && $args ? $args : $langs;
$GLOBALS['mrtu_vc_errors'] = 0;
$GLOBALS['mrtu_vc_warns']  = 0;

$fixed_slugs = array(
	'blog'          => array( 'vi' => 'cam-nang', 'en' => 'guides', 'de' => 'ratgeber', 'fr' => 'conseils', 'es' => 'guias' ),
	'privacy'       => array( 'vi' => 'chinh-sach-bao-mat', 'en' => 'privacy-policy', 'de' => 'datenschutz', 'fr' => 'politique-de-confidentialite', 'es' => 'politica-de-privacidad' ),
	'terms'         => array( 'vi' => 'dieu-khoan-su-dung', 'en' => 'terms-of-use', 'de' => 'nutzungsbedingungen', 'fr' => 'conditions-d-utilisation', 'es' => 'terminos-de-uso' ),
	'rental-policy' => array( 'vi' => 'quy-dinh-thue-xe', 'en' => 'rental-terms', 'de' => 'mietbedingungen', 'fr' => 'conditions-de-location', 'es' => 'condiciones-de-alquiler' ),
);
$allowed_blocks = array( 'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/details', 'core/group', 'core/buttons', 'core/button', 'core/columns', 'core/column', 'mrtu/fleet', 'mrtu/pricing-table', 'mrtu/contact-card', 'mrtu/cta' );
$required_blocks = array(
	'home'       => array( 'mrtu/fleet', 'mrtu/pricing-table', 'mrtu/contact-card' ),
	'pillar'     => array( 'mrtu/fleet', 'mrtu/pricing-table' ),
	'pricing'    => array( 'mrtu/pricing-table' ),
	'fleet'      => array( 'mrtu/fleet' ),
	'short-term' => array( 'mrtu/pricing-table' ),
	'monthly'    => array( 'mrtu/pricing-table' ),
	'contact'    => array( 'mrtu/contact-card' ),
);
$forbidden = array(
	'/nguy[eễ]n\s*t[uú]/iu'                         => 'mentions Nguyễn Tú (other location)',
	'/0942\s?467\s?674|\+?84\s?942/u'                 => 'Long Biên phone number',
	'/nguy[eễ]n\s*v[aă]n\s*c[uừ]|long\s*bi[eê]n/iu'  => 'Long Biên address',
	'/\b\d{1,3}([.,\x{202F} ]\d{3})+\s*(₫|đ|vnd|vnđ|dong)/iu' => 'hard-coded price',
	'/\b\d+\s*(k|nghìn|ngàn|triệu|tr)\b\s*(\/|một|mỗi|per|pro|par|por)?/iu' => 'hard-coded amount',
	'/0816\s?659\s?199|816659199/u'                   => 'hard-coded phone (use blocks)',
	'/17\s*(p\.|phố|pho)?\s*ph[uú]c\s*t[aâ]n/iu'      => 'hard-coded street address (unverified)',
	'/mi[eễ]n\s*c[oọ]c|kh[oô]ng\s*(c[aầ]n\s*)?(đ[aặ]t\s*)?c[oọ]c|no[\s-]deposit|ohne\s+kaution|sans\s+caution|sin\s+fianza/iu' => 'no-deposit promise',
	'/24\s*\/\s*7/u'                                  => '24/7 claim',
	'/\b(best|cheapest|#\s?1|number one)\b|r[eẻ]\s*nh[aấ]t|uy\s*t[ií]n\s*s[oố]\s*1|tốt nhất|beste[nr]?|billigste|meilleur|moins cher|mejor|más barato/iu' => 'superlative claim',
	'/\b(free delivery|giao xe miễn phí|kostenlose lieferung|livraison gratuite|entrega gratis)\b/iu' => 'free delivery claim',
	'/\bairport|sân bay|flughafen|aéroport|aeropuerto/iu' => 'airport mention (service unverified) — check context',
	'/\b(review|đánh giá \d|sterne|étoiles|estrellas|rating)\b/iu' => 'ratings/reviews mention — check context',
	'/<h1/i'                                          => 'H1 inside content',
	'/style\s*=/i'                                    => 'inline style',
	'/<img|<table/i'                                  => 'image/table in seed content',
);
$warn_only = array( 'airport mention (service unverified) — check context', 'ratings/reviews mention — check context', 'superlative claim', 'hard-coded amount' );

function mrtu_vc_report( $level, $lang, $file, $msg ) {
	if ( 'ERROR' === $level ) { $GLOBALS['mrtu_vc_errors']++; } else { $GLOBALS['mrtu_vc_warns']++; }
	echo "{$level} [{$lang}] {$file}: {$msg}\n";
}

function mrtu_vc_walk( $blocks, $cb, $depth = 0 ) {
	foreach ( $blocks as $b ) {
		if ( ! empty( $b['blockName'] ) ) {
			$cb( $b, $depth );
		}
		if ( ! empty( $b['innerBlocks'] ) ) {
			mrtu_vc_walk( $b['innerBlocks'], $cb, $depth + 1 );
		}
	}
}

// Known slugs per language (pages + posts from manifests) to validate links.
$known = array();
foreach ( $langs as $l ) {
	$known[ $l ] = array( '' );
	foreach ( (array) ( $plan['locales'][ $l ]['roles'] ?? array() ) as $role => $r ) {
		$known[ $l ][] = (string) $r['slug'];
	}
	foreach ( $fixed_slugs as $role => $map ) {
		$known[ $l ][] = $map[ $l ];
	}
	$m = $content . "/{$l}/pages.json";
	if ( is_readable( $m ) ) {
		foreach ( (array) json_decode( (string) file_get_contents( $m ), true ) as $e ) {
			if ( ! empty( $e['slug'] ) ) { $known[ $l ][] = $e['slug']; }
		}
	}
	$c = $content . "/{$l}/categories.json";
	if ( is_readable( $c ) ) {
		foreach ( (array) json_decode( (string) file_get_contents( $c ), true ) as $e ) {
			if ( ! empty( $e['slug'] ) ) { $known[ $l ][] = 'category/' . $e['slug']; }
		}
	}
	$known[ $l ] = array_unique( $known[ $l ] );
}

foreach ( $args as $lang ) {
	$dir      = $content . '/' . $lang;
	$manifest = $dir . '/pages.json';
	if ( ! is_readable( $manifest ) ) {
		mrtu_vc_report( 'ERROR', $lang, 'pages.json', 'missing' );
		continue;
	}
	$entries = json_decode( (string) file_get_contents( $manifest ), true );
	if ( ! is_array( $entries ) ) {
		mrtu_vc_report( 'ERROR', $lang, 'pages.json', 'invalid JSON: ' . json_last_error_msg() );
		continue;
	}
	$seen_ids = array();
	$seen_slugs = array();
	$seen_kw = array();
	foreach ( $entries as $i => $e ) {
		$f = isset( $e['file'] ) ? $e['file'] : "entry#{$i}";
		foreach ( array( 'seed_id', 'type', 'slug', 'title', 'excerpt', 'seo_title', 'seo_desc', 'file', 'status' ) as $k ) {
			if ( ! isset( $e[ $k ] ) || ( '' === $e[ $k ] && 'slug' !== $k ) ) {
				mrtu_vc_report( 'ERROR', $lang, $f, "missing field {$k}" );
			}
		}
		$type = $e['type'] ?? 'page';
		if ( 'page' === $type && empty( $e['role'] ) ) {
			mrtu_vc_report( 'ERROR', $lang, $f, 'page without role' );
		}
		if ( isset( $seen_ids[ $e['seed_id'] ?? '' ] ) ) {
			mrtu_vc_report( 'ERROR', $lang, $f, 'duplicate seed_id' );
		}
		$seen_ids[ $e['seed_id'] ?? '' ] = true;
		$slug = (string) ( $e['slug'] ?? '' );
		if ( '' !== $slug && ! preg_match( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug ) ) {
			mrtu_vc_report( 'ERROR', $lang, $f, "slug not ascii-kebab: {$slug}" );
		}
		if ( isset( $seen_slugs[ $slug ] ) ) {
			mrtu_vc_report( 'ERROR', $lang, $f, "duplicate slug {$slug}" );
		}
		$seen_slugs[ $slug ] = true;
		if ( 'page' === $type && ! empty( $e['role'] ) ) {
			$expected = $plan['locales'][ $lang ]['roles'][ $e['role'] ]['slug'] ?? ( $fixed_slugs[ $e['role'] ][ $lang ] ?? null );
			if ( null !== $expected && $expected !== $slug ) {
				mrtu_vc_report( 'WARN', $lang, $f, "slug '{$slug}' differs from plan '{$expected}'" );
			}
		}
		$tl = mb_strlen( (string) ( $e['seo_title'] ?? '' ) );
		if ( $tl > 60 ) {
			mrtu_vc_report( 'ERROR', $lang, $f, "seo_title {$tl} chars > 60" );
		}
		$dl = mb_strlen( (string) ( $e['seo_desc'] ?? '' ) );
		if ( $dl < 120 || $dl > 160 ) {
			mrtu_vc_report( $dl < 100 || $dl > 170 ? 'ERROR' : 'WARN', $lang, $f, "seo_desc {$dl} chars (target 140–158)" );
		}
		if ( ! empty( $e['nav_label'] ) && mb_strlen( $e['nav_label'] ) > 24 ) {
			mrtu_vc_report( 'WARN', $lang, $f, 'nav_label longer than 24 chars' );
		}
		if ( ! empty( $e['focus_kw'] ) ) {
			$kw = mb_strtolower( $e['focus_kw'] );
			if ( isset( $seen_kw[ $kw ] ) ) {
				mrtu_vc_report( 'ERROR', $lang, $f, "focus_kw '{$kw}' duplicates {$seen_kw[$kw]} (cannibalization)" );
			}
			$seen_kw[ $kw ] = $f;
		}
		$path = $dir . '/' . $f;
		if ( ! is_readable( $path ) ) {
			mrtu_vc_report( 'ERROR', $lang, $f, 'file missing' );
			continue;
		}
		$html = (string) file_get_contents( $path );
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( preg_replace( '/<!--.*?-->/s', ' ', $html ) ) ) );
		$words = count( preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) );
		echo "INFO [{$lang}] {$f}: {$words} words\n";

		foreach ( array( $html, (string) ( $e['title'] ?? '' ) . ' ' . (string) ( $e['excerpt'] ?? '' ) . ' ' . (string) ( $e['seo_title'] ?? '' ) . ' ' . (string) ( $e['seo_desc'] ?? '' ) ) as $haystack ) {
			foreach ( $forbidden as $re => $label ) {
				if ( preg_match( $re, $haystack, $mm ) ) {
					mrtu_vc_report( in_array( $label, $warn_only, true ) ? 'WARN' : 'ERROR', $lang, $f, "{$label}: '" . trim( $mm[0] ) . "'" );
				}
			}
		}

		// Mixed-language heuristics.
		if ( 'vi' !== $lang ) {
			$vi_chars = preg_match_all( '/[ăâđêôơưạảấầẩẫậắằẳẵặẹẻẽếềểễệỉịọỏốồổỗộớờởỡợụủứừửữựỳỵỷỹ]/iu', $text );
			if ( $vi_chars > 12 ) {
				mrtu_vc_report( 'ERROR', $lang, $f, "{$vi_chars} Vietnamese-only letters (mixed language?)" );
			}
		} else {
			$en_hits = preg_match_all( '/\b(the|and|with|your|rental|motorbike|scooter)\b/i', $text );
			if ( $en_hits > 8 ) {
				mrtu_vc_report( 'WARN', $lang, $f, "{$en_hits} English words in Vietnamese text" );
			}
		}

		// Blocks.
		$blocks  = parse_blocks( $html );
		$names   = array();
		$last_h  = 1;
		$details = 0;
		mrtu_vc_walk(
			$blocks,
			function ( $b ) use ( &$names, &$last_h, &$details, $allowed_blocks, $lang, $f ) {
				$names[] = $b['blockName'];
				if ( ! in_array( $b['blockName'], $allowed_blocks, true ) ) {
					mrtu_vc_report( 'ERROR', $lang, $f, 'block not allowed: ' . $b['blockName'] );
				}
				if ( 'core/heading' === $b['blockName'] ) {
					$level = isset( $b['attrs']['level'] ) ? (int) $b['attrs']['level'] : 2;
					if ( $level < 2 || $level > 4 ) {
						mrtu_vc_report( 'ERROR', $lang, $f, "heading level {$level}" );
					}
					if ( $level > $last_h + 1 ) {
						mrtu_vc_report( 'WARN', $lang, $f, "heading jumps from h{$last_h} to h{$level}" );
					}
					$last_h = $level;
					$tag = 'h' . $level;
					if ( false === strpos( $b['innerHTML'], '<' . $tag . ' class="wp-block-heading"' ) && false === strpos( $b['innerHTML'], '<' . $tag . ' class="wp-block-heading" id=' ) ) {
						mrtu_vc_report( 'ERROR', $lang, $f, "heading markup must be <{$tag} class=\"wp-block-heading\">" );
					}
				}
				if ( 'core/list' === $b['blockName'] && false === strpos( $b['innerHTML'], 'class="wp-block-list"' ) ) {
					mrtu_vc_report( 'ERROR', $lang, $f, 'list markup must carry class="wp-block-list"' );
				}
				if ( 'core/details' === $b['blockName'] ) {
					$details++;
				}
			}
		);
		$role = $e['role'] ?? '';
		foreach ( $required_blocks[ $role ] ?? array() as $req ) {
			if ( ! in_array( $req, $names, true ) ) {
				mrtu_vc_report( 'ERROR', $lang, $f, "missing required block {$req}" );
			}
		}
		if ( 'guide-faq' === $role && $details < 15 ) {
			mrtu_vc_report( 'ERROR', $lang, $f, "guide-faq has {$details} FAQ items (need 15–20)" );
		}
		if ( 'home' === $role ) {
			foreach ( array( 'promise', 'fleet', 'pricing', 'story', 'steps', 'trust', 'faq', 'contact' ) as $sec ) {
				if ( false === strpos( $html, 'mrtu-section--' . $sec ) ) {
					mrtu_vc_report( 'ERROR', $lang, $f, "home missing section mrtu-section--{$sec}" );
				}
			}
		}

		// Links.
		if ( preg_match_all( '/href="([^"]+)"/', $html, $links ) ) {
			foreach ( $links[1] as $href ) {
				if ( preg_match( '#^https?://#', $href ) ) {
					if ( false !== strpos( $href, 'thuexemaynguyentu.com' ) ) {
						mrtu_vc_report( 'ERROR', $lang, $f, "absolute internal link {$href} (use root-relative)" );
					}
					continue; // external authoritative sources are allowed.
				}
				if ( 0 === strpos( $href, '#' ) || 0 === strpos( $href, 'tel:' ) || 0 === strpos( $href, 'mailto:' ) ) {
					continue;
				}
				$p = trim( (string) wp_parse_url( $href, PHP_URL_PATH ), '/' );
				$parts = explode( '/', $p );
				$link_lang = in_array( $parts[0], array( 'en', 'de', 'fr', 'es' ), true ) ? array_shift( $parts ) : 'vi';
				if ( $link_lang !== $lang ) {
					mrtu_vc_report( 'ERROR', $lang, $f, "link to other language: {$href}" );
					continue;
				}
				$rest = implode( '/', $parts );
				if ( ! in_array( $rest, $known[ $lang ], true ) ) {
					mrtu_vc_report( 'ERROR', $lang, $f, "link to unknown slug: {$href}" );
				}
				if ( '' !== $rest && '/' !== substr( $href, -1 ) && false === strpos( $href, '#' ) ) {
					mrtu_vc_report( 'WARN', $lang, $f, "link without trailing slash: {$href}" );
				}
			}
		}
	}
}

echo 'SUMMARY: ' . $GLOBALS['mrtu_vc_errors'] . ' error(s), ' . $GLOBALS['mrtu_vc_warns'] . " warning(s)\n";
if ( $GLOBALS['mrtu_vc_errors'] > 0 ) {
	exit( 1 );
}
