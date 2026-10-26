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
