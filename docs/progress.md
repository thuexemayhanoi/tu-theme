# Progress checkpoint — Mr Tu flagship

> Read this first when resuming. Update it whenever work stops. Newest entry on top.

## 2026-10-26 — Session 2 (resume)

**Branch:** `claude/mr-tu-flagship` — CI run #5 green (lint 7.4/8.1/8.3 · WPCS · WordPress integration · ZIP build).
Production untouched; nothing merged to `main`.

### Done this session
- Integrated the Wave 1 worktrees: multilingual core (router, translations, links, queries, switcher, breadcrumbs,
  roles, admin translation UI — 185 assertions), flagship theme (templates, parts, patterns, tokens, light/dark/system,
  menu sheet), business module (NAP/pricing/vehicles managers, 4 server-rendered blocks, Quick Contact — 206), TOC (103).
- Fixed CI: theme `style.css` present, WPCS prefixes (`mr_tu`/`MR_TU`) declared, 2 format errors, documented ignores.
- Fixed from screenshots: reveal safety net (content can never stay hidden), CTA block overflow on VI mobile,
  `.mrtu-cta__value` contrast (axe serious → 0).
- Clean-env verification (WordPress 7.1.3 + SQLite, fresh DB): 494 PHP assertions + 40 HTTP routing checks pass with
  plugin + theme active; screenshots of home/pricing/guide/404 × light/dark × 390/1280 reviewed.
- 5-language seed content (16 entries each) committed; validator 0 errors (1 ES warning: idiom "mejor encaja", kept).
- POT catalogues generated for theme (`mr-tu`) and plugin (`mr-tu-multilang-seo`).
- Merged Seeder + WP-CLI + Health + Dashboard (74 + 38 assertions); fresh-env suite 624/624; `wp mrtu seed --set-front`
  created 85 posts/pages (5 languages); HTTP seeded-site checks 52/52.
- Performance pass: home-only CSS split, small plugin CSS inlined, forced reflow removed — Lighthouse mobile home 86 → 94,
  LCP 2.7 s, CLS 0 (real-browser layout-shift probe: 0 on home/guide/pricing).
- Docs: deployment (Hostinger), rollback, Vietnamese admin guide, security review; QA matrix runner
  (`tests/e2e/qa-matrix.cjs`).

### In progress (background agents)
- SEO module in worktree `wf_e1c4a9b4-da7-1` (6 commits: core, head, schema, sitemaps, redirects, Yoast compat, admin;
  tests being written) — merge when green.
- i18n translators (vi / de_DE / fr_FR / es_ES) in fresh worktrees `wf_cc719a3f-2ab-*`.
- Queued after them: private assistant (nothing on disk yet — must be rebuilt), native editorial pass per language.
- Full QA matrix (5 languages × 12 roles × 2 viewports × 2 modes + axe) running on the seeded env; results →
  `tests/e2e/artifacts/qa-matrix/summary.json` and `docs/qa-report.md`.

### Known gaps (to close before release)
- UI strings render in English on non-English pages until the translation files land.
- Theme CSS 77 KB (gzip 15 KB) vs 60 KB target — acceptable for v1, trim later.
- Lighthouse baseline recorded in `tests/lighthouse/reports/` (see QA report when written).
- Remaining: full QA matrix per language after seeding, security review, docs (VI admin guide, deployment, rollback),
  release ZIPs.

## 2026-10-25 — Session 1

**Branch:** `claude/mr-tu-flagship` (feature branch; `main` untouched because production runs *Deployer for Git* —
merging to `main` could auto-deploy. Merge only after review + backup.)

### Done
- Phase 1 discovery running (expert repos, WordPress core internals, standards, keyword/SERP research, reference site).
- Phase 2 foundation: engineering contract (`docs/architecture-contract.md`), design system (`docs/design-system.md`),
  business seed with verified flags (`config/mr-tu-business.json`), plugin bootstrap/autoloader/module registry,
  admin UI kit, self-hosted font subsets, local WordPress 7.1.3 + SQLite envs (`scripts/dev-env.sh`), build + QA scripts,
  GitHub Actions QA workflow.

### In progress
- Wave 1 (parallel, isolated): multilingual core (router, translations, links, query, switcher, breadcrumbs, admin
  translation UI), flagship theme, private local assistant (source in git-ignored `private/`).

### Next
- Wave 2: SEO head/schema/sitemaps/Yoast compat/redirects; business data (NAP, pricing, vehicles) + blocks + Quick Contact;
  TOC, health check, seeder, WP-CLI, dashboard.
- Content: 10 core roles + legal pages × 5 languages from the keyword plan; editorial QA per language.
- i18n: POT → vi/de_DE/fr_FR/es_ES .po/.mo/.l10n.php for theme + plugin.
- Full QA matrix, Lighthouse, security review, ZIPs, docs (VI admin guide, deployment, rollback), final audit.

### Blockers / owner input needed
- Verify on site: full Google Business Profile name, address representation, real hand-over hours, service model
  (staffed shop vs appointment), Google Maps URL, WhatsApp/Zalo on 0816 659 199, actual prices/deposits at Mr Tu.
- Real photography (see shot list in `docs/design-system.md` §8).
- The repository is public → the assistant plugin source is delivered privately (not committed). A private repository is
  required if the owner wants it versioned on GitHub.

## 2026-10-26 — Seeder / Health / CLI / Dashboard module (worktree `wf_e1c4a9b4-da7-2`)

### Done
- `includes/class-seeder.php` (idempotent 5-language seeder, `--update` hash guard, `--set-front`), `class-health.php`
  (checks + 10-min transient + maintenance actions), `class-cli.php` (`wp mrtu seed|health|translations|export|import|sitemap`).
- Admin: `admin/class-admin-dashboard.php` (slug `mrtu`, position 0: stat cards, verification summary, SEO mode, Quick Contact,
  seed card with dry-run / set-front / update toggles, last report, export) and `admin/class-admin-health.php` (slug
  `mrtu-health`, position 90: run again, checks grouped by severity, role × language matrix, rebuild maps / flush rewrite /
  flush sitemap (`function_exists`) / Download JSON).
- `scripts/seed-local.sh` (reset env → activate → `wp mrtu seed --set-front` → language home URLs).
- Tests: `tests/php/test-seeder.php` (74 on a fresh env / 71 on a seeded env), `tests/php/test-health.php` (38 / 41),
  `tests/http/test-seeded-site.sh` (52). Whole PHP suite: 624 passed, 0 failed (`MRTU_TEST_URL=http://localhost:8084`).
- WPCS + PHPCompatibilityWP 7.4-: zero errors / warnings on every module file.

### Notes
- `tests/php/test-business.php` defaults its HTTP section to `http://localhost:8083`; set `MRTU_TEST_URL` when the env serves
  another port.
- Term queries (`get_terms`) do not honour the `mrtu_lang` argument outside the frontend; filter by
  `Translations::get_term_language()` in CLI/tests.

### SEO module checkpoint (agent)
## 2026-10-26 — Session 2 (SEO module, branch `worktree-wf_e1c4a9b4-da7-1`)

### Done
- SEO module complete (contract §2.8 + amendments 6–9): `Seo` core, `Seo_Head`, `Schema`, `Sitemaps`
  (`/mrtu-sitemap-index.xml` → `/mrtu-sitemap-{lang}.xml`, xhtml alternates from the same resolver as the head,
  transient cache + flush hooks, robots.txt line, core sitemap off in standalone), `Redirects` (option `mrtu_redirects`,
  404-only, hits, auto 301 on slug/parent change, chain/loop safe, cap 500), `Yoast_Compat` (filter names verified
  against the wordpress-seo source; compat prints only hreflang/og:locale:alternate; standalone silences Yoast via
  `wpseo_frontend_presenters` / `wpseo_json_ld_output`), `Admin_Seo` screen (mode, sitemap, schema, robots, TOC,
  redirects, live head audit) + "SEO Mr Tu" meta box and term fields with snippet preview (`assets/admin/seo.js`).
- Tests: `tests/php/test-seo.php` (195), `tests/php/test-seo-yoast.php` (79, via `tests/php/yoast-stub.php` installed
  as a temporary mu-plugin by `tests/run-php.sh`), `tests/http/test-seo.sh` (88) on `tests/php/fixtures-seo.php`.
  Whole PHP suite: 786 passed. WPCS zero errors, PHPCompatibilityWP 7.4- clean.

### Limits / notes
- Yoast's XML sitemap cannot be disabled by filter in standalone mode (owner switches it off in Yoast); Yoast's head
  comment wrapper still prints. Real Yoast verification happens on staging (the test env is offline).
- AutoRental schema stays off until address, phone and hours are marked verified on the Business data screen.
- Live head audit depends on loopback HTTP (some hosts block it; the screen explains).
