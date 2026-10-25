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
