# AGENTS.md — rules for AI agents and contributors

Read `README.md` (business brief) and `docs/architecture-contract.md` (engineering contract) before changing code.
`docs/design-system.md` is the art direction for the theme. `docs/progress.md` is the checkpoint log — read it first
when resuming work and update it when you stop.

## Hard rules

- Only this repository (`thuexemayhanoi/tu-theme`). Never touch other repositories or the production WordPress, DNS or Hostinger.
- One WordPress, one domain, five languages (vi default at `/`, `/en/`, `/de/`, `/fr/`, `/es/`). No Multisite, no extra domains,
  no Russian/Portuguese.
- No third-party multilingual/SEO/TOC/quick-call/chat plugins; no paid or external runtime APIs; no CDN scripts.
- Mr Tu NAP only (17 P. Phúc Tân, Hồng Hà, Hà Nội · 0816 659 199). Never the Nguyễn Tú Long Biên NAP.
- Unverified data (address, hours, prices, deposits, channels) is never presented as fact publicly.
- No invented reviews, ratings, years in business, awards, stats or photos of the shop.
- `private/` holds the local assistant source and is git-ignored because this repository is **public**. Never commit it.
- PHP 7.4-compatible syntax, WordPress Coding Standards, escape late / sanitize early / nonce + capability on writes.

## Local environment (never production)

```bash
scripts/dev-env.sh install   # WordPress 7.1.3 + SQLite in /home/user/wpenv (first time)
scripts/dev-env.sh link      # symlink theme/plugins from this repo
scripts/dev-env.sh serve     # http://localhost:8080 (admin / admin)
wp plugin activate mr-tu-multilang-seo && wp theme activate mr-tu
```

## QA before every commit

```bash
scripts/qa.sh            # php -l (7.4 compat), phpcs (WPCS + PHPCompatibilityWP), integration tests, build
```
Never push code that fails `php -l` or the integration tests. Commit per finished module with a clear message.
