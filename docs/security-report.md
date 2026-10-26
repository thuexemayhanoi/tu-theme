# Security review — Mr Tu theme & plugins

> Scope: `theme/mr-tu`, `plugin/mr-tu-multilang-seo`, private `mr-tu-local-assistant`. Method: static scan (PHPCS
> WordPress-Security sniffs + grep for risky calls), code reading of every write path, and runtime checks on the local
> WordPress 7.1.3 install. Date: 2026-10-26. Status per item: ✅ verified · ⚠️ accepted risk / owner action · ⏳ pending.

## 1. Threat model (what we protect)

- Public site read by anonymous visitors: XSS through content/business data, information disclosure (unverified data,
  drafts, usernames), SEO poisoning via redirects.
- wp-admin used by one owner on an iPhone: CSRF on settings/data forms, privilege boundaries (`manage_options` vs
  `edit_posts`), file import, open redirects after admin-post actions.
- Public endpoints: the assistant REST route (`mrtu-assistant/v1/ask`), sitemaps, robots.
- Hosting: shared Hostinger + LiteSpeed cache; other remote-admin plugins already present on the production site
  (`wpvibe`, `wp-agent`, `cowboy-mcp`) — outside our code but relevant to the owner (see §6).

## 2. Write paths (admin)

| Handler (admin-post) | Capability | Nonce | Input handling | Status |
|---|---|---|---|---|
| `mrtu_save_business`, `mrtu_business_import/export/restore` | `manage_options` | `Admin::verify_request()` → `check_admin_referer` | `Business::normalize/validate` (E.164 phone, HH:MM, enums, `esc_url_raw` https-only maps URL); import: `$_FILES` size ≤ 200 KB, JSON strict decode, same normalizer, snapshot before apply | ✅ |
| `mrtu_save_pricing` | `manage_options` | ✅ | ints ≥ 0, min ≤ max, enums, snapshot | ✅ |
| `mrtu_save_vehicle`, `mrtu_vehicles_op` | `manage_options` | ✅ | `sanitize_key` ids, enums, `absint` image id, plain-text descriptions per language (no HTML stored; rendered with `esc_html`) | ✅ |
| `mrtu_save_quick_contact` | `manage_options` | ✅ | booleans + enum position | ✅ |
| Languages settings | `manage_options` | ✅ | `Languages::validate` (prefix `[a-z]{2,5}`, unique, reserved names, hreflang pattern) | ✅ |
| Translations: create / link / unlink / change language | `edit_posts` + `current_user_can('edit_post', $id)` per post, post-type match | ✅ (nonce action includes post id) | `absint`, `sanitize_key`; identical-content publish guard | ✅ |
| Theme settings (`mr_tu_save_settings`) | `edit_theme_options` | ✅ | `absint` media ids, booleans, enum mode | ✅ |
| SEO settings, redirects, meta box, term fields | `manage_options` / `edit_post` / `edit_term` | ✅ | `sanitize_text_field`, `esc_url_raw` redirect targets (same-host or absolute https), status enum 301/302; loops/chains refused | ✅ (release review 2026-10-26) |
| Dashboard "Seed now", Health actions | `manage_options` | ✅ | booleans; seeder reads only bundled/seed dir files (`basename()`, `.html` only, fixed language codes) | ✅ (release review) |
| Assistant settings, Q&A pairs, log clear, reindex | `manage_options` | ✅ | `sanitize_textarea_field`, `esc_url_raw` | ⏳ not part of this release |

Every handler ends with `wp_safe_redirect()` to an admin URL built by `Admin::url()` (no user-controlled destination).

## 3. Output escaping

- PHPCS `WordPress.Security.EscapeOutput` passes with 0 errors on theme + plugin (documented ignores only where the
  string was escaped one line earlier). Blocks render through `esc_html`/`esc_attr`/`esc_url`; vehicle descriptions are plain
  text (`esc_html`), never HTML.
- Content printed through `the_content()` keeps WordPress' own kses rules for authors; our seeded content contains only
  core blocks and our server-rendered blocks.
- JSON-LD is emitted through `wp_json_encode` with `JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP` (no `</script>` break-out).
- Admin tables escape every cell; the only `wp_kses_post` use is for help texts authored in code.

## 4. Data exposure

- Unverified NAP/pricing fields are filtered out by `mrtu_business( …, $public_only = true )`, `mrtu_vehicles()`,
  `mrtu_price_display()`; blocks, schema, Quick Contact and the assistant use only the public accessors. ✅ (tests:
  business 224 assertions incl. "unverified address/hours hidden").
- Drafts/private posts never appear in switcher, hreflang, sitemaps or the assistant index (published + indexable only). ✅
- Author archives and date archives are `noindex` by default (SEO settings); the live site currently exposes the `admin`
  login via Yoast's author sitemap — owner action (§6).
- Sitemaps/hreflang contain absolute https URLs built from `home_url()`; no query strings, no attachments.
- Export JSON (business + pricing + settings) contains no secrets; still treat it as business data (do not post publicly).

## 5. Public endpoints & abuse

- Multilang plugin: no REST routes of its own; it only registers a `lang` collection parameter and post meta with
  `auth_callback` = `current_user_can('edit_post')` (meta is not writable anonymously).
- Assistant REST `POST /mrtu-assistant/v1/ask`: public read-only by design (works behind page cache without nonce);
  input ≤ 300 chars sanitized; answers are built from already-public data; rate limit 20 requests / 5 min per hashed
  IP+UA via transients (HTTP 429); unanswered-question log stores no IP/UA and strips e-mails/phone numbers. ⏳
- Loopback requests (health "live head audit", sitemap reachability) only ever target `home_url()`-derived URLs — no
  user-supplied URL is fetched (no SSRF surface).
- No `eval`, `unserialize` of user data, `base64_decode` of input, shell calls, or raw SQL without `$wpdb->prepare()`
  (scan: all 7 `$wpdb` reads use `prepare()`; `IN (…)` placeholders are built from `%s` lists).
- Uploads: only the business JSON import (`manage_options`), parsed with `json_decode` and validated; never written to disk.

## 6. Owner actions on the production site (outside our code)

1. Review and, if unused, deactivate the remote-admin plugins exposing file write / CLI endpoints over REST
   (`wpvibe/v1`, `wp-agent/v1`, `cowboy-mcp/v1`, `dfg/v1`). They can overwrite any file, including ours.
2. Change the display name of the `admin` user (author sitemap and feeds currently reveal the login name) and keep a
   strong password + 2FA where Hostinger offers it.
3. Trash the test post "Giới thiệu Claude" and "Hello world!".
4. Keep Yoast, LiteSpeed and WordPress updated; our plugin declares `Requires at least 6.5` / `Requires PHP 7.4`.
5. Never place our ZIPs in `mu-plugins/` (cannot be deactivated from the admin if something fails).

## 7. Tooling results

- `phpcs --standard=phpcs.xml.dist` (WordPress incl. Security sniffs): 0 errors (theme + plugin).
- PHPCompatibilityWP 7.4+ and PHPCompatibility 10 (modern-syntax detection): 0 errors.
- Integration tests on a clean install: see `docs/qa-report.md`.

## 8. Release review (2026-10-26) and open items

An independent read-only review of every plugin file (24 `admin_post_*` handlers, router, redirects, sitemaps, head,
schema, seeder, CLI, admin JS) and every theme file was run before packaging. Candidates for open redirect, redirect
loops, import XSS, path traversal, SQL injection, transient-key abuse, CSRF and REST `lang` abuse were traced and
refuted. Findings that survived were fixed in the release build:

| Finding | Severity | Fix |
|---|---|---|
| Redirect hit counter rewrote the autoloaded `mrtu_redirects` map on every public hit (DB write per request, lost updates against the owner's edits) | medium | Counters moved to the non-autoloaded `mrtu_redirect_hits` option; the map is only written from the admin screen. A visitor still causes one small option write per *redirected 404* — accepted. |
| Translations screen (`edit_posts`) listed other authors' drafts/private items | low | `perm => readable` on both queries. |
| `_mrtu_seed_id` / `_mrtu_seed_hash` were REST-writable by authors (could hijack a seeded role) | low | `show_in_rest => false`; only the seeder writes them. |
| Private taxonomies granted `assign_terms` to `edit_posts` (`tax_input` bypass of the nonce-guarded language UI) | info | `assign_terms => do_not_allow`; the plugin assigns terms itself via `wp_set_object_terms()`. |
| Head audit loopback hard-coded `sslverify => false` | info | Uses the core `https_local_ssl_verify` filter like the health check. |
| Theme: nested nav group inside "More" could not open; menu "current" detection ignored object type; `theme.json` v3 needs WP 6.6; `rtl-language-support` tag without RTL styles; no-JS menu link to a conditional anchor | low/info | All fixed (toggle handler skips ancestors, core `_wp_menu_item_classes_by_context()`, `Requires at least: 6.6`, tag removed, link → `#site-footer`). |

Still open:

- ⚠️ Real Yoast coexistence must be verified on **staging** (the local env cannot reach wordpress.org); the compat mode is
  tested against a stub that replays Yoast's filters. Checklist: `docs/deployment-hostinger.md` §2.7.
- ⏳ The private assistant plugin is not part of this release; review its REST endpoint again before it ships.
- Tooling after the fixes: WPCS 0 errors, PHPCompatibilityWP 7.4- 0 errors (theme + plugin), PHP suite 898/898.
