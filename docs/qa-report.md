# QA report — Mr Tu theme 1.0.0 + Mr Tu Multilang SEO 1.0.0

> Branch `claude/mr-tu-flagship`, 2026-10-26. Every check below ran on a **local** WordPress 7.1.3 (SQLite) install —
> never on production. "Fresh" = database wiped, plugin + theme activated, five languages seeded by `wp mrtu seed --set-front`.
> Numbers are from the last run before packaging; re-run commands are given so anyone can reproduce them.

## 1. Summary

| Area | Result | How to reproduce |
|---|---|---|
| PHP integration suite (7 files, fresh seeded env) | **898 passed, 0 failed** | `MRTU_TEST_URL=http://localhost:8083 bash tests/run-php.sh /home/user/wpenv-business` |
| HTTP routing (prefixes, 301, 404, previews, switcher) | **40 / 40** | `bash tests/http/test-routing.sh http://localhost:8084 /home/user/wpenv-toc` |
| HTTP SEO (head, hreflang, JSON-LD, sitemaps, redirects) | **90 / 90** | `bash tests/http/test-seo.sh http://localhost:8084 /home/user/wpenv-toc` |
| HTTP seeded site (5 languages × roles, archives, 404s) | **52 / 52** | `bash tests/http/test-seeded-site.sh http://localhost:8084 /home/user/wpenv-toc` |
| Front-end QA matrix (5 languages × 12 roles × mobile/desktop × light/dark + axe) | **240 / 240, 0 axe violations** (§3) | `BASE=http://localhost:8083 node tests/e2e/qa-matrix.cjs --shots` |
| Lighthouse | see §4 | `node /home/user/qa-tools/lh-median.mjs <url> 3 <outDir> [--desktop]` |
| WordPress Coding Standards (`phpcs.xml.dist`, incl. security sniffs) | **0 errors** (theme + plugin) | `phpcs --standard=phpcs.xml.dist -n theme/mr-tu plugin/mr-tu-multilang-seo` |
| PHPCompatibilityWP 7.4+ (rulesets 9 and 10) | **0 errors** | `phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- -n …` |
| `php -l` on every PHP file | clean | `find theme plugin -name '*.php' -print0 \| xargs -0 -n1 php -l` |
| Admin at 375 px (10 Mr Tu screens, logged in) | no horizontal overflow, no PHP notices, no JS errors | Playwright script, see §5 |
| Clean install from the ZIPs | see §6 | `WP_ENV_DIR=/home/user/wpenv-theme WP_PORT=8081 bash scripts/zip-install-test.sh` |
| Repository gate `scripts/qa.sh --env /home/user/wpenv-toc` | theme + plugin clean, 898/898, build OK (the gate also scans the private, unreleased assistant source when present — its 8 WPCS errors are outside this release) | `MRTU_TEST_URL=http://localhost:8084 bash scripts/qa.sh --env /home/user/wpenv-toc` |
| GitHub Actions (`QA`: lint 7.4/8.3, WPCS + PHPCompat, WordPress integration, Build ZIPs) | green on every push since run #5 | https://github.com/thuexemayhanoi/tu-theme/actions |

## 2. Fixes made during this QA pass

- **Desktop navigation overlapped the header tools** (Lighthouse desktop `target-size` failure): with five
  Vietnamese/French/Spanish labels the bar ran under the language toggle at 1024–1600 px. The "More" group is now always
  rendered and theme.js moves trailing items into it while the list overflows (priority+ pattern, re-evaluated on
  `fonts.ready` and on header resize). Measured after the fix: no overlap in any of the 5 languages at 1024, 1100, 1200,
  1280, 1350, 1440, 1600 and 1920 px; without JavaScript the bar is clipped instead of overlapping.
- **Admin screens at phone width**: grid items could not shrink below their content, status badges did not wrap, the
  sticky save bar bled 4 px, the SEO screen rendered switch *and* radio controls, and the translations matrix widened the
  page to 406 px (absolutely positioned screen-reader labels escaping the scroll container). All ten screens now measure
  `document.scrollWidth = 375` at 375 px.
- **Vietnamese leaking into other languages** ("Hà Nội" in plates, CTA band and area text) → language-aware
  `mr_tu_city()`; long German footer links wrap.
- **French meta descriptions** lost their non-breaking spaces (sanitiser collapsed U+00A0/U+202F) → fixed.
- **Priority+ navigation did not restore items** when the viewport grew again (measured `scrollWidth`, which equals the
  container once nothing overflows) → measures the bar's used width; verified 1920 → 1024 → 1920 round trip.
- **Release code review** (independent, read-only, see `docs/security-report.md` §8): redirect hit counters moved out
  of the autoloaded map, translations screen hides other authors' drafts from contributors, seeder bookkeeping meta no
  longer REST-writable, private taxonomies no longer assignable through `tax_input`, nested "More" groups can open,
  core-based "current menu item" detection, `Requires at least: 6.6` (theme.json v3), no-JS menu link target.
- **Interface translations**: POTs regenerated after the SEO/seeder/health merge (theme 173 strings, plugin 707);
  vi, de_DE, fr_FR and es_ES catalogues completed and compiled (`.po`, `.mo`, `.l10n.php`); placeholder/HTML/NAP checks
  pass on every catalogue (`scratch po-check.php`: 0 problems).

## 3. Front-end QA matrix

`tests/e2e/qa-matrix.cjs` opens every role page (home, pillar, old-quarter, pricing, fleet, short-term, monthly,
guide-faq, about, contact, blog, legal) in the five languages, on a 390 × 844 phone and a 1280 × 900 desktop, in light and
dark mode, and for each page checks: HTTP 200, `<html lang>`, one `<h1>`, no horizontal overflow, no Vietnamese text in
the UI chrome of non-Vietnamese pages, no English fallbacks in the Vietnamese chrome, working language switcher links,
and an axe-core scan (serious/critical violations fail the page). The five 404 pages are checked separately.

Final run (after every fix in §2): **240 pages checked, 0 failed, 0 axe violations**, five 404 pages correct.
Screenshots: `tests/e2e/artifacts/qa-matrix/` (git-ignored, 240 files).

## 4. Lighthouse (median of 3 runs, Playwright Chromium, local server with 8 PHP workers)

| Page | Device | Performance | Accessibility | Best practices | SEO | LCP | CLS | TBT |
|---|---|---|---|---|---|---|---|---|
| `/` (VI home) | mobile (Moto G, slow 4G) | 93 | 100 | 100 | 100 | 2.7 s | 0 | 30 ms |
| `/` (VI home) | desktop | 100 | 100 | 100 | 100 | 0.6 s | 0.001 | 0 ms |
| `/en/motorbike-rental-hanoi/` (pillar) | mobile | 93 | 100 | 100 | 100 | 2.9 s | 0 | 0 ms |
| `/en/how-to-rent-a-motorbike-in-hanoi/` (guide + TOC + FAQ) | mobile | 93 | 100 | 100 | 100 | 2.7 s | 0 | 10 ms |

Reports: `tests/lighthouse/reports/<name>/median.report.html` (git-ignored). The remaining mobile performance gap is
render-blocking CSS on a throttled connection (the theme ships one 64 KB stylesheet, no CDN); LiteSpeed's CSS
optimisation on Hostinger can be enabled after the cache is confirmed stable.

## 5. Admin on a phone (375 × 812, logged in as administrator)

Screens: Overview, Store information, Prices, Vehicles, Languages, Translations, SEO, Quick Call, Health Check, Theme
appearance, and the Pages list (language columns). For each: HTTP 200, no `.notice-error`, no "Fatal error"/"Warning:"
in the body, no `pageerror` in the browser console, `document.scrollWidth === 375`. Screenshots in `/home/user/screens/admin/`
(local only).

## 6. Clean install from the ZIPs

`scripts/zip-install-test.sh` resets a throw-away WordPress, removes every repository symlink and seed constant, then
does exactly what "Upload Plugin / Upload Theme" does: `wp plugin install dist/mr-tu-multilang-seo.zip --activate`,
`wp theme install dist/mr-tu-theme.zip --activate`, `wp mrtu seed --set-front` from the content **bundled in the ZIP**,
HTTP smoke on the five home pages, sitemap index, robots.txt, `/en` → `/en/`, `<html lang>`, x-default hreflang, one
canonical, theme CSS, JSON-LD, no Nguyễn Tú Long Biên NAP, `wp mrtu health` exit 0, then the rollback path (plugin off →
site 200, plugin on, default theme with plugin only → 200, theme back on) and finally the PHP error log.

Result: **37 / 37 PASS** (`dist/mr-tu-multilang-seo.zip` 843 KB / 196 files, `dist/mr-tu-theme.zip` 276 KB / 94 files;
each ZIP has exactly one root folder; every PHP file lints). The only lines in the PHP log are WordPress trying to reach
wordpress.org from the offline container.

## 7. Known limits and what must be verified on staging

- **Yoast SEO** cannot be installed in the offline local environment. The compatibility mode is exercised against a stub
  that replays Yoast's filters (`tests/php/yoast-stub.php`, 79 assertions). The real check belongs on the Hostinger
  **staging** copy: see `docs/deployment-hostinger.md` §2.4 and the staging checklist in §6 there.
- **Unverified business data** (address representation, hours, prices, deposits, Zalo/WhatsApp) stays hidden on the
  public site until the owner marks each field *Đã xác minh* — by design, not a defect.
- **Performance numbers** are from a local PHP development server; on Hostinger with LiteSpeed Cache they will differ
  (usually better for TTFB). Re-check PageSpeed Insights after the cache is warm.
- The private assistant plugin is **not part of this release** (optional, delivered later, never blocks the site).
