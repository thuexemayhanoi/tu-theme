# Progress checkpoint — Mr Tu flagship

> Read this first when resuming. Update it whenever work stops. Newest entry on top.

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
