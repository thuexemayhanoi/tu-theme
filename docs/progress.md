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

### In progress (background agents, isolated worktrees)
- Wave 2: SEO module (head/schema/sitemaps/Yoast compat/redirects/admin), Seeder + WP-CLI + Health + Dashboard,
  private assistant (source in git-ignored `private/`, ZIP in `dist-private/`).
- Native editorial pass per language → `docs/content-review-<lang>.md`.
- i18n: translating the POTs to vi / de_DE / fr_FR / es_ES (.po/.mo/.l10n.php + editor JSON).

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
