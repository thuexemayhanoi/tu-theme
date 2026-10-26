# Mr Tu — Engineering Contract (v1)

> Status: binding contract for every module in this repository. Written 2026-10-25 by the
> principal architect. If an implementation needs to deviate, update this file in the same
> commit and explain why. Vietnamese owner-facing docs live in `docs/*-vi.md`.

## 0. Non-negotiables

1. ONE WordPress, ONE domain `https://hanoi.thuexemaynguyentu.com/`, no Multisite, no extra (sub)domains.
2. EXACTLY five languages: `vi` (default, no prefix `/`), `en` (`/en/`), `de` (`/de/`), `fr` (`/fr/`), `es` (`/es/`).
3. No third-party multilingual/SEO/TOC/quick-call/chat plugins, no runtime external APIs, no CDN JS.
4. Data truth: unverified business data is never presented as fact publicly. Admin shows `PENDING VERIFICATION`.
5. Mr Tu NAP only (17 P. Phúc Tân, Hồng Hà, Hà Nội · 0816 659 199). Never the Nguyễn Tú Long Biên NAP.
6. Code compatible with **PHP 7.4 – 8.4** (no `match`, no union/mixed types, no nullsafe `?->`, no named args,
   no constructor promotion, no enums, no `readonly`). WordPress **6.5+**, tested on **7.1.3**.
7. WordPress Coding Standards: escape late, sanitize early, nonces + capability checks on every write,
   `$wpdb->prepare()` for every dynamic SQL, prefix everything (`mrtu_`, `MRTU_`, `MrTu\…`), i18n every string.
8. Theme switch must not lose translations, SEO data, NAP, prices or chatbot config (all owned by plugins).
9. Never ship code that edits production DB on activation beyond creating our own options/terms. Content
   seeding and Reading-settings changes happen only on an explicit admin/CLI action.

## 1. Repository layout

```
tu-theme/
├── README.md · AGENTS.md
├── theme/mr-tu/                      # Theme "Mr Tu" (presentation only)
├── plugin/mr-tu-multilang-seo/       # Languages, translations, routing, SEO, business data, blocks, TOC, quick contact, health, seeder
├── private/                          # GITIGNORED. mr-tu-local-assistant source (repo is public → never pushed)
├── content/                          # Seed content: content/{lang}/pages.json + content/{lang}/*.html (block markup)
├── config/                           # mr-tu-business.json (seed facts), seo-plan.json (keyword plan)
├── docs/                             # research, architecture, design system, admin guide (VI), QA, security, rollback, progress
├── tests/                            # php integration tests (wp eval-file), e2e (Playwright), lighthouse
├── scripts/                          # dev-env.sh, build.sh, qa.sh, helpers
├── dist/                             # built ZIPs (theme + multilang plugin). Assistant ZIP is delivered privately.
└── .github/workflows/qa.yml
```

Local test environment (not in repo): WordPress 7.1.3 on SQLite at `/home/user/wpenv/wordpress`,
`wp` CLI wrapper at `/usr/local/bin/wp` (already passes `--allow-root --path=…`). The repo folders are
symlinked into `wp-content/themes/mr-tu` and `wp-content/plugins/mr-tu-multilang-seo`. Dev server:
`php -S 127.0.0.1:8080 -t /home/user/wpenv/wordpress /home/user/tu-theme/scripts/router.php`.

## 2. Plugin `mr-tu-multilang-seo`

- Main file `mr-tu-multilang-seo.php`; header: `Plugin Name: Mr Tu Multilang SEO`, `Text Domain: mr-tu-multilang-seo`,
  `Domain Path: /languages`, `Requires at least: 6.5`, `Requires PHP: 7.4`, `License: GPL-2.0-or-later`, `Version: 1.0.0`.
- Constants: `MRTU_ML_VERSION`, `MRTU_ML_FILE`, `MRTU_ML_DIR`, `MRTU_ML_URL`.
- Namespace `MrTu\Multilang`; PSR-4-ish autoloader mapping `MrTu\Multilang\Admin\Foo_Bar` → `includes/admin/class-foo-bar.php`
  and `MrTu\Multilang\Foo_Bar` → `includes/class-foo-bar.php`.
- Public procedural API lives in `includes/functions.php` (all `mrtu_*`, each guarded with `function_exists`). Themes and the
  assistant plugin call ONLY these functions (always behind `function_exists()` checks).

### 2.1 Languages

Fixed registry (code → defaults); settings may toggle `enabled`, change `prefix` (non-default only) and `hreflang`:

| code | WP locale | hreflang (default) | og_locale | prefix | native name | name_vi |
|---|---|---|---|---|---|---|
| vi | `vi` (WordPress.org Vietnamese pack id) | `vi-VN` | `vi_VN` | `` | Tiếng Việt | Tiếng Việt |
| en | `en_US` | `en` | `en_US` | `en` | English | Tiếng Anh |
| de | `de_DE` | `de` | `de_DE` | `de` | Deutsch | Tiếng Đức |
| fr | `fr_FR` | `fr` | `fr_FR` | `fr` | Français | Tiếng Pháp |
| es | `es_ES` | `es` | `es_ES` | `es` | Español | Tiếng Tây Ban Nha |

The brief writes `vi_VN`; WordPress' Vietnamese language pack is registered as `vi`, so the plugin uses `vi` for
`load_textdomain`/core packs and exposes `vi-VN` for hreflang and `vi_VN` for og:locale. `<html lang>` uses the hreflang code.
`x_default` setting (default `en`): international travellers whose language is not offered get English.

Option `mrtu_ml_settings` (array, autoload yes):
```php
[
  'languages'   => [ 'vi' => [ 'enabled' => true, 'prefix' => '', 'hreflang' => 'vi-VN', 'tagline' => '' ], 'en' => [...], ... ],
  'default'     => 'vi',            // fixed in v1
  'x_default'   => 'en',            // '' disables x-default
  'seo_mode'    => 'auto',          // auto | compat | standalone  (auto = compat if Yoast active else standalone)
  'sitemap'     => 'auto',          // auto (on in standalone, off in compat) | on | off
  'local_business_schema' => false, // needs verified address+phone+hours as well
  'faq_schema'  => true,            // only on role guide-faq pages, from core/details blocks
  'noindex_author' => true, 'noindex_date' => true,
  'show_reference_prices' => false, // when false, unverified prices render "price on request"
  'toc'         => [ 'enabled' => true, 'min_headings' => 3, 'min_words' => 350, 'position' => 'after_first_h2', 'post_types' => [ 'post', 'page' ] ],
  'delete_data_on_uninstall' => false,
]
```

### 2.2 Storage model

- Translatable post types: `post`, `page` (filter `mrtu_translatable_post_types`). Translatable taxonomies: `category`, `post_tag`.
- Private taxonomy **`mrtu_language`** (object types: translatable post types; `public=false`, `show_ui=false`,
  `rewrite=false`, `query_var=false`). Terms: slug = language code, name = native name. One term per post.
- Private taxonomy **`mrtu_translations`** (same object types). One term per translation group; term slug `mrtu-g-<uniqid>`;
  term **description = JSON map** `{"vi":12,"en":34}` (all statuses). The map is the cache of truth and is rebuilt from
  relationships by `Translations::rebuild_group()` (Health Check offers "Rebuild translation maps").
- Terms (category/tag): term meta `_mrtu_lang` and `_mrtu_tgroup`; option `mrtu_term_translations` (autoload no)
  `[ group_key => [ lang => term_id ] ]`.
- Page roles: post meta `_mrtu_role` on every post of a group (kept in sync by `mrtu_set_post_role`). Option
  `mrtu_roles_map` `[ role => translations_term_id ]` for O(1) lookups.
- Other post meta (all registered with `register_post_meta`, `show_in_rest` true, `auth_callback` = `current_user_can('edit_post',$id)`):
  `_mrtu_seo_title`, `_mrtu_seo_desc`, `_mrtu_noindex` (`'1'`), `_mrtu_focus_kw`, `_mrtu_nav_label`, `_mrtu_toc` (`''|'off'`),
  `_mrtu_seed_hash`, `_mrtu_seed_id`.
- Term meta: `_mrtu_seo_title`, `_mrtu_seo_desc`, `_mrtu_noindex`.

### 2.3 Page roles (core pages)

| role | purpose | in header nav | footer column |
|---|---|---|---|
| `home` | front page per language | – | – |
| `pillar` | Motorbike rental Hanoi (primary service hub) | yes | rent |
| `old-quarter` | Old Quarter / Hoàn Kiếm area page | yes | rent |
| `pricing` | Price list + deposits | yes | info |
| `fleet` | Models | yes | info |
| `short-term` | Daily & weekly rental | more | rent |
| `monthly` | Monthly rental | more | rent |
| `guide-faq` | How to rent + FAQ | yes | info |
| `about` | About Mr Tu | more | brand |
| `contact` | Contact | yes (as CTA) | brand |
| `blog` | Posts page (page_for_posts) | more | info |
| `privacy` | Privacy policy | – | legal |
| `terms` | Terms of use | – | legal |
| `rental-policy` | Rental rules | – | legal |

`mrtu_page_roles()` returns this registry (filterable `mrtu_page_roles`) with keys `label` (translated), `nav` (`primary|more|none`),
`footer` (`rent|info|brand|legal|none`), `order` (int).

### 2.4 Public API (`includes/functions.php`)

```php
mrtu_languages( $enabled_only = true )            // array code => [code, locale, hreflang, og_locale, prefix, name, name_vi, enabled, is_default]
mrtu_default_language()                          // 'vi'
mrtu_current_language()                          // frontend: from URL (or queried object for ID-based/preview requests); admin: '' unless editing a post; REST: 'lang' param
mrtu_get_language( $code )                       // array|null
mrtu_is_language_enabled( $code )                // bool
mrtu_get_post_language( $post )                  // code; untagged posts are reported as default language
mrtu_set_post_language( $post_id, $lang )        // bool
mrtu_get_post_translations( $post_id )           // [lang => post_id] all statuses, includes self
mrtu_get_post_translation( $post_id, $lang, $published_only = true ) // int (0 when none)
mrtu_link_translations( array $map )             // bool — merges into one group, validates one post per language
mrtu_unlink_translation( $post_id )              // void
mrtu_get_term_language( $term_id ) / mrtu_set_term_language( $term_id, $lang ) / mrtu_get_term_translations( $term_id ) / mrtu_link_term_translations( array $map )
mrtu_page_roles()                                // see 2.3
mrtu_get_post_role( $post_id )                   // '' when none
mrtu_set_post_role( $post_id, $role )            // applies to the whole group
mrtu_get_page_id_by_role( $role, $lang = null )  // published post id in $lang (current when null) or 0
mrtu_role_url( $role, $lang = null )             // permalink or '' when unavailable
mrtu_home_url( $path = '', $lang = null )        // language home + ltrim($path,'/') ; never filter core home_url
mrtu_switcher_links()                            // array lang => [code,name,hreflang,url,current(bool),available(bool),fallback(bool)]
mrtu_alternate_urls()                            // [hreflang => url] for indexable alternates of the current request incl. self, + 'x-default'
mrtu_language_switcher( $args = [] )             // accessible default markup (theme may render its own from mrtu_switcher_links())
mrtu_breadcrumb_trail()                          // array of [ 'name' => string, 'url' => string ] for the current request (last item = current, url may be '')
mrtu_business( $field = null, $public_only = true )  // NAP access; unverified fields return '' / null when $public_only
mrtu_phone( $format = 'display' )                // 'display' | 'e164' | 'tel' (tel: URI) — '' when not public
mrtu_contact_channels( $enabled_only = true )    // [ 'phone' => [label,url,value], 'whatsapp' => …, 'zalo' => …, 'directions' => …, 'contact' => … ]
mrtu_vehicles( $args = [] )                      // public vehicles (status != hidden) normalized, see 2.7
mrtu_price_display( $vehicle, $period, $lang = null ) // [ 'text' => '200.000 ₫', 'state' => 'verified'|'reference'|'on_request', 'note' => string ]
mrtu_format_vnd( $amount, $lang = null )         // vi "150.000 ₫", en "₫150,000", de "150.000 ₫", fr "150 000 ₫" (narrow nbsp), es "150.000 ₫"
mrtu_pricing_meta()                              // [ 'verified' => bool, 'reviewed_at' => 'Y-m-d'|'' , 'show_reference' => bool, 'currency' => 'VND' ]
mrtu_toc_items( $post = null )                   // [ ['id','text','level'] ] — same data the inline TOC uses
mrtu_is_seo_standalone()                         // bool
```

Hooks (public): actions `mrtu_language_detected( $lang )`, `mrtu_business_updated( $old, $new )`, `mrtu_pricing_updated( $old, $new )`,
`mrtu_translations_linked( $map )`; filters `mrtu_translatable_post_types`, `mrtu_page_roles`, `mrtu_quick_contact_items( $items, $lang )`,
`mrtu_assistant_active` (bool, default false — the assistant plugin returns true), `mrtu_toc_enabled( $bool, $post )`,
`mrtu_seo_title( $title, $context )`, `mrtu_seo_description( $desc, $context )`, `mrtu_schema_graph( $graph )`,
`mrtu_sitemap_entries( $entries, $lang )`, `mrtu_breadcrumb_trail( $trail )`.

### 2.5 Router (URL prefix model)

- Detection runs as early as possible (when the plugin file loads) from `$_SERVER['REQUEST_URI']` relative to the home path.
  Frontend only (not `is_admin()`, not `wp-login.php`, not REST `/wp-json/` or `rest_route`, not cron, not `xmlrpc.php`).
- `/{prefix}` (no trailing slash) → 301 to `/{prefix}/` (keep query string).
- In filter `do_parse_request` (priority 1): strip `/{prefix}` from `REQUEST_URI` (and `PATH_INFO` if set), remember the original;
  in action `parse_request` (priority 0) restore `REQUEST_URI` and set `$wp->request = prefix . '/' . $wp->request` (trimmed).
  Never strip when the remainder starts with `wp-json`, `wp-admin`, `wp-content`, `wp-includes`, `wp-login.php`, `xmlrpc.php`.
- Locale: filter `locale` (frontend) returns the language's WP locale. Admin keeps the user locale.
- Front page per language: on the frontend filter `option_page_on_front` and `option_page_for_posts` to the translation in the
  current language (only when it is published). Keep an internal raw accessor for the unfiltered values.
- `request` filter: resolve `pagename` within the current language (respect hierarchy); fall back to a post `name` in the same
  language; otherwise set `error=404`. Leave ID-based queries (`p`, `page_id`, `attachment_id`, previews) untouched.
- `pre_get_posts` (main query, frontend): add a `mrtu_language` tax_query for the current language to home/blog, archives,
  search, feeds and slug-based singular queries of translatable types. Skip attachments and ID-based queries.
- Same slug allowed across languages: filter `wp_unique_post_slug` so a desired slug is only suffixed when it collides with a post
  of the same type, parent **and language**. New translations get their language assigned before the slug is computed.
- `redirect_canonical` filter: never redirect a request to a URL of another language (return false instead).
- Language with no published home translation → its home returns 404 (Health Check warns).
- Disabled language → its prefix is not routed (404) and it disappears from switcher, hreflang, sitemaps.

### 2.6 Links

Filters `post_link`, `page_link`, `post_type_link`, `attachment_link` (no prefix), `term_link`, `post_type_archive_link`,
`search_link`, `get_pagenum_link` (verify), `feed_link`/`post_comments_feed_link`, `author_link`, `year_link`/`month_link`/`day_link`:
- objects with a language → prefix of THEIR language;
- context links (search, date, author, feeds, pagination) → prefix of the current language;
- any page in the translation group of the raw `page_on_front` → that language's home URL (`/` or `/{prefix}/`);
- the theme uses `mrtu_home_url()`; core `home_url` is never filtered globally (would break REST, assets, etc.).

### 2.7 Business data (NAP · pricing · vehicles)

- Options: `mrtu_business` (NAP, shape mirrors `config/mr-tu-business.json → business`), `mrtu_pricing` (mirrors `pricing`),
  `mrtu_business_history` (last 20 snapshots `[time,user_id,type,data]`, autoload no). Seeded from `config/mr-tu-business.json`
  (bundled into the plugin as `data/mr-tu-business.json` at build time) on activation **only if empty**.
- Vehicle record normalized by `mrtu_vehicles()`:
  `id, name, brand, category (manual|scooter|50cc|electric), status (available|on_request|hidden), verified (bool),
   price{day,week,month} each null or [min,max] (VND ints), deposit null|[min,max], image_id (int), desc (string in current lang),
   desc_i18n{vi,en,de,fr,es}, note (admin only)`.
- Public rendering rules: `verified` → show price; unverified + `show_reference_prices` → show with "reference price, confirm before
  booking" label; otherwise → "price on request" + call CTA. Deposit follows the same rule. Hidden vehicles never render.
- Admin writes: capability `manage_options`, nonce, validation (phone E.164 `^\+84\d{9,10}$`, HH:MM, ints ≥ 0, min ≤ max),
  snapshot before save, `mrtu_business_updated`/`mrtu_pricing_updated` actions, "last updated by/at" shown.
- Export/import JSON (both options) with schema validation and automatic snapshot (rollback = restore a snapshot).

### 2.8 SEO

Modes: `compat` (Yoast active, default) — we output only `<link rel="alternate" hreflang>` and `og:locale:alternate`, pass our
seeded `_mrtu_seo_title/_desc/_noindex` into Yoast filters **only when Yoast's own per-post field is empty**, force language-correct
`wpseo_canonical`/`wpseo_opengraph_url` on front pages, and set Yoast WebSite schema url/name to the language home.
`standalone` — we output everything: title (`pre_get_document_title`), meta description, robots (`wp_robots`), canonical (replacing core
`rel_canonical`), hreflang, Open Graph, Twitter Card, JSON-LD `@graph`, sitemaps; when Yoast is still active in standalone mode we switch
its head output off via its own filters (owner opted in, documented). Exactly one canonical, one description, one robots per page — the
Health Check "live head audit" verifies it via loopback.

Robots: noindex,follow for search, 404, date/author archives (settings), attachments, `_mrtu_noindex`, empty term archives.
Canonical: singular → permalink (+ `/N/` for paginated content); front → language home; posts page/term archives → link (+ `/page/N/`).
Hreflang: only published, indexable alternates in enabled languages, self included, `x-default` → alternate in `x_default` language;
emitted only when ≥ 2 alternates; never on noindex pages.
JSON-LD: Organization `#organization`, WebSite `#website` (per-language url + inLanguage), WebPage, BreadcrumbList (from
`mrtu_breadcrumb_trail()`), BlogPosting for posts, `AutoRental` (LocalBusiness) **only** when `local_business_schema` is on AND address,
phone and hours are verified; FAQPage only on `guide-faq` pages from `core/details` blocks. No ratings/reviews.
Sitemaps: `/mrtu-sitemap.xml` (index) → `/mrtu-sitemap-{lang}.xml` with `<xhtml:link rel="alternate">` per URL; only published,
indexable, non-password URLs with content; cached (transient per language), flushed on content/term/settings changes; robots.txt line.
Redirects: option `mrtu_redirects` `[ '/old-path/' => [ 'to' => url, 'code' => 301, 'hits' => 0, 'created' => ts ] ]`,
matched only on 404 at `template_redirect` priority 0, auto-created when a published post/page slug or parent changes, chain/loop safe.

### 2.9 TOC

`the_content` filter (priority 12, after blocks): collect H2/H3, assign stable ids (`remove_accents` + `sanitize_title`, unique
suffix, keep existing ids), insert after the first H2 by default. Shown when enabled, post type allowed, ≥ `min_headings`,
≥ `min_words`, `_mrtu_toc` != off, not the front page. Markup (classes are contract):
```html
<details class="mrtu-toc" data-mrtu-toc>
  <summary class="mrtu-toc__summary"><span class="mrtu-toc__title">Mục lục</span><span class="mrtu-toc__count">6</span></summary>
  <nav class="mrtu-toc__nav" aria-label="Mục lục"><ol class="mrtu-toc__list"><li class="mrtu-toc__item mrtu-toc__item--h2"><a href="#id">…</a><ol>…</ol></li></ol></nav>
</details>
```
Closed by default; small JS (plugin) adds scroll-spy (`aria-current="true"` on the active link) and a reading-progress custom
property `--mrtu-read-progress` on `<html>`. Theme may render a sticky desktop copy from `mrtu_toc_items()`.

### 2.10 Quick Contact

Option `mrtu_quick_contact` `[ 'enabled'=>true, 'items'=>['call'=>true,'whatsapp'=>false,'zalo'=>false,'directions'=>false,'chat'=>true,'contact'=>false], 'position'=>'right', 'collapse_on_scroll'=>true ]`.
Items appear only when their channel is enabled AND verified (chat only when `mrtu_assistant_active`). Rendered in `wp_footer`:
```html
<div class="mrtu-qc" data-mrtu-qc data-position="right">
  <div class="mrtu-qc__panel" id="mrtu-qc-panel" hidden> <a class="mrtu-qc__item mrtu-qc__item--call" href="tel:+84…">…</a> … </div>
  <button class="mrtu-qc__toggle" aria-expanded="false" aria-controls="mrtu-qc-panel">…</button>
</div>
```
Behaviour: safe-area aware (`env(safe-area-inset-*)`), hides while an input is focused (mobile keyboard), while `[data-mrtu-qc-avoid]`
elements (footer contact, forms) intersect the viewport, and while the assistant panel is open. Chat item dispatches
`document.dispatchEvent(new CustomEvent('mrtu:assistant-open'))`.

### 2.11 Blocks (server-rendered, `blocks/<name>/block.json` + `render.php`, one shared `blocks/editor.js` without build)

`mrtu/pricing-table` (attrs `period` day|week|month = default tab, `showDeposit` bool), `mrtu/fleet` (attrs `category` all|manual|scooter,
`showPrices` bool), `mrtu/contact-card` (attrs `showHours`, `showAddress`), `mrtu/cta` (attrs `variant` call|contact). Classes:
`.mrtu-pricing`, `.mrtu-pricing__tabs`, `.mrtu-pricing__tab[aria-selected]`, `.mrtu-pricing__panel`, `.mrtu-fleet`, `.mrtu-vehicle`,
`.mrtu-vehicle__plate`, `.mrtu-price`, `.mrtu-price--on-request`, `.mrtu-badge--pending`, `.mrtu-contact-card`, `.mrtu-cta`.
Plugin ships minimal structural CSS using the theme tokens with fallbacks: `var(--color-accent, #F56A2A)`.

### 2.12 Admin — "Mr Tu" menu (Vietnamese UI, mobile-first, capability `manage_options` unless noted)

Tổng quan (dashboard) · Thông tin cửa hàng · Bảng giá · Dòng xe · Ngôn ngữ · Bản dịch · SEO · Quick Call · (Chatbot — added by the
assistant plugin) · (Giao diện — added by the theme) · Kiểm tra sức khỏe. Post/page list tables get VI/EN/DE/FR/ES columns
(✅ published, ✏️ draft, ➕ create) + language filter. Edit screens get "Ngôn ngữ & bản dịch" and "SEO Mr Tu" meta boxes.
"Create translation" = admin-post action with nonce → draft in target language linked to the group (optionally copying the block
structure); a translation whose content is byte-identical to another language's content can never be published (reverted to draft
with a notice).

### 2.13 Seeder

Source: `content/{lang}/pages.json` (bundled as `seed/` in the plugin ZIP). Entry:
```json
{ "role": "pillar", "slug": "thue-xe-may-ha-noi", "title": "…H1…", "nav_label": "Thuê xe", "excerpt": "…lead…",
  "seo_title": "… | Mr Tu", "seo_desc": "…", "focus_kw": "…", "file": "pillar.html", "status": "publish", "menu_order": 1 }
```
Posts (blog) use `"type": "post"` and `"category": "slug"`; categories listed in `content/{lang}/categories.json` with a shared `group` key.
Idempotent: lookup by role/seed id + language; skips existing; `--update` only rewrites posts whose current content hash still equals
`_mrtu_seed_hash`. Reading settings (`show_on_front`, `page_on_front`, `page_for_posts`) change only with `--set-front` / the admin checkbox.
WP-CLI: `wp mrtu seed [--dry-run] [--update] [--set-front] [--lang=<code>]`, `wp mrtu health`, `wp mrtu translations`, `wp mrtu export > file.json`,
`wp mrtu import <file>`, `wp mrtu sitemap flush`.

## 3. Content markup contract (seed HTML)

Only these blocks (exact serialization, WordPress 6.5+): `core/paragraph`, `core/heading` (h2/h3, `class="wp-block-heading"`),
`core/list` (`class="wp-block-list"`) + `core/list-item`, `core/details`, `core/group` (tagName `section`, className `mrtu-section …`),
`core/buttons`/`core/button` (`wp-block-button__link wp-element-button`), `core/columns`/`core/column`, and our `mrtu/*` blocks as
self-closing comments `<!-- wp:mrtu/pricing-table {"period":"day"} /-->`. No H1 inside content (the template prints the H1 from the
title). No inline styles, no hard-coded prices/phones/addresses (use blocks), internal links point to same-language URLs.

## 4. Theme `mr-tu`

Hybrid classic theme: PHP templates + `theme.json` (v3) for editor tokens. Text domain `mr-tu`. Works without the plugin (guards).
Design tokens (CSS custom properties on `:root`, dark set under `[data-theme="dark"]` and `prefers-color-scheme` when no explicit choice):
`--color-background --color-surface --color-surface-raised --color-text --color-text-muted --color-accent --color-accent-ink
--color-on-accent --color-action --color-action-ink --color-on-action --color-border --color-border-strong --color-focus`,
typography `--font-sans --font-plate --text-*`, `--space-*`, `--radius-*`, `--shadow-*`, `--container-*`, `--ease-*`, `--dur-*`.
See `docs/design-system.md` for values and component specs.

## 5. Assistant plugin `mr-tu-local-assistant` (private)

Source in `private/mr-tu-local-assistant/` (gitignored). Reads only public data through the API above and WordPress queries for
published content; REST `mrtu-assistant/v1/ask` (public, rate-limited, no nonce so cached pages keep working); assets lazy-loaded
on first open. Registers `add_filter( 'mrtu_assistant_active', '__return_true' )` and listens for `mrtu:assistant-open`.

## 6. Amendments after core-internals research (2026-10-25, supersede earlier sections)

Source: `docs/research.md` §4 (ADR-A…G) and §4.9, with probes against WordPress 7.1.3.

1. **Router priorities:** strip the prefix in `do_parse_request` at `PHP_INT_MAX` (only when the incoming `$bool` is true); restore
   `REQUEST_URI`/`PATH_INFO` in `parse_request` at `PHP_INT_MIN` and again on `wp` as a fallback. Never strip `wp-json`, `wp-admin`,
   `wp-content`, `wp-includes`, `wp-login.php`, `xmlrpc.php`, `wp-sitemap*`, `robots.txt`, `favicon.ico`.
2. **No public language query var.** Any extra main-query var breaks static-front-page detection for `/en/`. The language lives in
   plugin state (`Router::current()` / `mrtu_current_language()`).
3. **Singular language check:** `tax_query` is ignored for singular queries. Resolve `pagename`/`name` in the `request` filter and,
   on `wp` (before `redirect_canonical`), 404 any slug-based singular request whose object language ≠ URL language. ID-based
   requests (`?p=`, `?page_id=`, previews) keep working and adopt the object's language.
4. **Redirect guards:** turn on `strict_redirect_guess_404_permalink` and keep guesses inside the current language; check
   `old_slug_redirect_post_id` for language; in `redirect_canonical`, when the target is the default-language home but the request
   belongs to another language, **replace** the target with that language's home (so `/en/<home-slug>/` → 301 `/en/`, `/en/` itself
   is not redirected); otherwise never redirect across languages (return false).
5. **Front pages:** option filters for `page_on_front`/`page_for_posts` only on true frontend requests (not admin, AJAX, REST, CLI,
   cron) after the language is known; `page_link` maps every language home everywhere (admin/CLI too, for Yoast indexables).
   Fallback if tests fail: map the empty path to `page_id` in `request` and expose `mrtu_is_language_home()`.
6. **Sitemap index path:** `/mrtu-sitemap-index.xml` (the old `/mrtu-sitemap.xml` collides with Yoast's `([^/]+?)-sitemap\.xml`
   rule). Children stay `/mrtu-sitemap-{lang}.xml`. Head hreflang and sitemap alternates come from the same function.
7. **WebSite schema:** one `WebSite` node, `@id` = root `#website`, `url` = root, `name` = "Mr Tu", `inLanguage` = all enabled
   language codes. Language is expressed on `WebPage.inLanguage`. In compat mode Yoast's WebSite node is left as is.
8. **hreflang for Vietnamese:** stays `vi-VN` by default because the owner's brief requires it (both `vi` and `vi-VN` are valid;
   `vi` is broader). Configurable in Languages settings.
9. **FAQPage schema:** kept only where the Q&A is visible on the guide page; FAQ rich results were retired by Google (2026), so no
   feature depends on it.
10. **Theme:** no `html5` `script`/`style` args (deprecated); `should_load_separate_core_block_assets` true; editor CSS via block
    assets (iframed editor).
11. **Seeder** assigns languages with `wp_set_object_terms()` (never `tax_input`, which is dropped without `assign_terms`).
