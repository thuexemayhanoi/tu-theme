# Mr Tu — Design System & Art Direction (v1)

> Concept: **“Km 0 — Hà Nội bắt đầu từ đây.”** Every Vietnamese road trip is measured from a kilometre stone,
> and Hanoi's own Km 0 sits a short walk from the Old Quarter. Mr Tu is the place where a traveller's Hanoi
> starts moving. The identity borrows two objects every visitor sees within minutes of landing — the **cột cây số**
> (kilometre stone: white body, coloured rounded cap, black stencil type) and the **biển số xe** (white licence
> plate, thin black frame, condensed numerals) — and renders them with editorial precision. Remove the logo and
> the site still reads as Mr Tu: orange-capped stones, plate labels, a dashed road line, generous white/black rhythm.

## 1. Principles (the creative-director checklist)

1. **Useful first, then memorable.** Every section answers a renter's question (what bikes, how much, how to rent,
   is it trustworthy, how to reach Mr Tu). Decoration that answers nothing is removed.
2. **Honesty is a design feature.** Unverified prices render as an elegant “price on request” state with a call
   CTA — never as fake certainty. No fake photos of a shop, no fake reviews, no invented numbers.
3. **Mobile is the flagship.** Designed at 360–430 px first; thumb zone CTAs; bottom-sheet navigation; desktop is
   the expansion, not the origin.
4. **Typography carries the brand** until real photography exists. Big confident headlines, condensed plate labels,
   quiet body copy with perfect Vietnamese diacritics.
5. **Motion explains.** Things move to show order (steps), state (tabs, menus) or arrival (hero). Nothing loops.

## 2. Colour (exactly four brand colours + functional neutrals)

| Token | Light | Dark | Use |
|---|---|---|---|
| `--color-background` | `#FFFFFF` | `#101114` | page |
| `--color-surface` | `#F4F4F2` | `#1A1C21` | bands, cards on white |
| `--color-surface-raised` | `#FFFFFF` | `#23262C` | cards, sheets, menus |
| `--color-text` | `#101114` | `#F4F4F5` | body & headings (18.9:1 / 17.2:1) |
| `--color-text-muted` | `#5B5E66` | `#A3A6AE` | secondary text (6.5:1 / 7.8:1) |
| `--color-accent` (Cosmic Orange) | `#F56A2A` | `#F56A2A` | stone caps, highlights, primary CTA fill, large display marks |
| `--color-accent-ink` | `#B8460F` | `#FF8A55` | orange **text** on background (5.4:1 / ≥ 6:1) |
| `--color-on-accent` | `#101114` | `#101114` | text on orange (6.3:1). White on orange fails AA — never use it |
| `--color-action` (Electric Blue) | `#0866FF` | `#0866FF` | call buttons, interactive fills, toggles (white text 4.8:1) |
| `--color-action-ink` | `#0057E0` | `#5C9DFF` | links/text actions (6.1:1 / 6.9:1) |
| `--color-on-action` | `#FFFFFF` | `#FFFFFF` | text on blue |
| `--color-border` | `#E3E3E0` | `#2C2F36` | hairlines |
| `--color-border-strong` | `#C9CAC6` | `#41454E` | inputs, plate frames (≥ 3:1 vs bg for UI boundaries where required) |
| `--color-focus` | `#0866FF` | `#5C9DFF` | 3 px focus ring + 2 px offset |

Rules: orange = emotion & brand marks; blue = interaction & information; white/black = rhythm (alternating full-bleed
bands). Never more than one orange fill and one blue fill in the same viewport-height of UI. Dark mode is designed, not
inverted: surfaces step up in lightness, orange becomes the “headlight” (soft radial glow behind hero/story at 10–14 %
opacity), shadows become 1 px light borders.

## 3. Typography

- **Be Vietnam Pro** (OFL, designed by Vietnamese type designers Lâm Bảo, Tony Le, ViệtAnh Nguyễn) — 400, 600, 800.
  Correct stacked diacritics for Vietnamese, full DE/FR/ES coverage. `--font-sans`.
- **Barlow Condensed** (OFL, inspired by highway signage & plates) — 600, 700 for plate labels, prices, km numbers,
  eyebrows. `--font-plate`. Always uppercase with `letter-spacing: .04em`, tabular numerals.
- Self-hosted WOFF2 subsets (Basic Latin, Latin-1, Latin Ext-A, Ơ/Ư block, combining marks, Latin Ext Additional,
  punctuation, ₫ €) ≈ 18 KB per weight. `font-display: swap`; preload only 800 (hero) + 400 (body). Size-adjusted
  fallback `@font-face` on Arial/Helvetica to keep CLS ≈ 0.

Fluid scale (`clamp`, 360 → 1440 px):

| Token | Size | Line height | Weight | Use |
|---|---|---|---|---|
| `--text-hero` | clamp(2.6rem, 1.55rem + 4.7vw, 6rem) | 1.04 | 800, tracking −0.035em | H1 home |
| `--text-display` | clamp(2.1rem, 1.5rem + 2.7vw, 4rem) | 1.06 | 800, −0.03em | H1 inner, H2 hero sections |
| `--text-h2` | clamp(1.65rem, 1.3rem + 1.55vw, 2.6rem) | 1.12 | 800, −0.02em | section titles |
| `--text-h3` | clamp(1.2rem, 1.1rem + .45vw, 1.45rem) | 1.25 | 600 | card titles |
| `--text-lead` | clamp(1.08rem, 1rem + .4vw, 1.3rem) | 1.55 | 400 | intros |
| `--text-body` | 1.0625rem (17 px) | 1.65 | 400 | prose (max 68ch) |
| `--text-small` | .9375rem | 1.5 | 400 | meta |
| `--text-plate` | clamp(.8rem, .76rem + .2vw, .95rem) | 1 | 600 condensed, +.04em | labels |

Vietnamese needs vertical room: never set line-height < 1.04 on text that may contain stacked marks (Ấ, Ể, Ỗ).

## 4. Space, grid, shape

- Spacing scale `--space-1…10`: 4, 8, 12, 16, 24, 32, 48, 64, 96, 128 px (sections use `clamp(64px, 10vw, 128px)`).
- Container `--container-max: 1320px`, `--container-prose: 720px`, gutter `--container-pad: clamp(16px, 4vw, 40px)`.
- Grid: 4 columns < 768, 8 columns 768–1199, 12 columns ≥ 1200. Breakpoints (documented, used in media queries):
  `sm 480 · md 768 · lg 1024 · xl 1200 · 2xl 1440`.
- Radius: `--radius-sm 8px`, `--radius-md 14px`, `--radius-lg 22px`, `--radius-xl 32px`, `--radius-pill 999px`,
  stone cap radius = 50% of stone width.
- Shadows (light only): `--shadow-sm 0 1px 2px rgb(16 17 20 / .06)`, `--shadow-md 0 8px 24px -8px rgb(16 17 20 / .14)`,
  `--shadow-lg 0 24px 60px -20px rgb(16 17 20 / .25)`. Dark: `none` + `1px` border `--color-border`.
- Touch targets ≥ 44 × 44 px (WCAG 2.5.8 minimum is 24 px; we design to 44).

## 5. Signature components

1. **Kilometre stone** (`.mrtu-stone`): white body, orange cap (top 38 %), black condensed label. Variants: hero object
   (“PHỐ CỔ / 0 km” localised), step marker (“KM 01”), 404 (“KM ?”), favicon/brand mark. Pure CSS/SVG, no images.
2. **Plate label** (`.mrtu-plate`): white field, 1.5 px `--color-text` frame, inner 1 px inset line, condensed caps, two
   rows (`29-MT` code + model). Used on vehicle cards, price tags, section eyebrows. In dark mode the plate stays white
   (it is an object, not a surface) — strongest recognisability signal.
3. **Road line** (`.mrtu-road`): 2 px dashed line (12/10 dash) used as divider and as the progress spine of the
   “How renting works” steps; fills with orange as the user scrolls (scroll-driven animation, static fallback).
4. **Price** (`.mrtu-price`): plate numerals, `₫` smaller and raised; states: verified, reference (dotted underline + label),
   on request (muted + call link).
5. **Segmented control** (pricing Day/Week/Month, theme mode): pill track on surface, thumb slides 240 ms.
6. **Bottom sheet menu** (mobile): slides up from the bottom, 88 svh max, grab handle, focus-trapped dialog, language +
   theme mode inside; closes on Esc, backdrop tap, swipe-down is NOT required (button always visible).
7. **Quick Contact dock**: one pill (call icon + “Gọi”/“Call”) bottom-right above `env(safe-area-inset-bottom)`,
   expands to channels; retreats while forms/footer contact are visible and while typing.

## 6. Motion language

| Token | Value | Use |
|---|---|---|
| `--ease-out` | `cubic-bezier(.22, 1, .36, 1)` | “throttle release”: everything that arrives |
| `--ease-in-out` | `cubic-bezier(.65, 0, .35, 1)` | state changes |
| `--dur-fast` | 160ms | hover, tap |
| `--dur-base` | 280ms | tabs, toggles, menus |
| `--dur-slow` | 640ms | section reveals |
| `--dur-hero` | 900ms | hero entrance |

- Entrance (hero): headline lines rise 18 px + fade, staggered 70 ms; the stone drops 10 px and its cap colour sweeps
  in; total ≤ 1.1 s; content is visible without JS (animation only adds polish, never gates visibility).
- Reveal: `[data-reveal]` elements fade + translate 14 px once at 12 % visibility (IntersectionObserver adds `.is-revealed`;
  without JS everything is visible).
- Scroll progression: steps road line + reading progress bar (`animation-timeline: scroll()/view()` under `@supports`).
- Hover: cards lift 2 px + shadow-md; arrow icons nudge 3 px; tap: scale .98 for 120 ms.
- Menu/sheet: translateY(100%) → 0 at 320 ms `--ease-out`, backdrop opacity 0 → .5.
- Page transitions: cross-document View Transitions (`@view-transition { navigation: auto; }`) root cross-fade 180 ms —
  progressive enhancement only.
- `prefers-reduced-motion: reduce` → all transforms off, opacity transitions ≤ 120 ms, no scroll-driven effects, no
  view transitions.
- Only `transform`/`opacity` are animated. No scroll hijacking, no parallax, no infinite loops.

## 7. Light / Dark / System

`<html data-theme="light|dark">` set by a tiny inline script in `<head>` before CSS paints (reads `localStorage['mrtu-theme']`
= `light|dark|system`). System = no attribute, CSS follows `prefers-color-scheme`. `color-scheme` property is set accordingly;
two `<meta name="theme-color">` tags (light `#FFFFFF`, dark `#101114`). The toggle is a 3-state control (System · Light · Dark)
with `aria-pressed` semantics, in the header (desktop) and the menu sheet (mobile).

## 8. Imagery

Real photography is the single biggest upgrade still pending. Until the owner uploads real photos (Theme Settings → hero
image, story image; Dòng xe → photo per model), every slot renders a crafted graphic state (stone, plate, road line,
headlight glow) that looks intentional, never a grey placeholder. **Shot list for the owner:** each rental model
three-quarter view against an Old Quarter wall at golden hour (3:2, ≥ 2400 px); the hand-over moment (keys + helmet,
faces optional); a street-level Old Quarter scene with motorbikes (no identifiable plates); helmet + raincoat kit flat-lay.
All images: WebP/AVIF via WordPress, `sizes` set, hero `fetchpriority="high"` and never lazy-loaded.

## 9. Accessibility contract

WCAG 2.2 AA: skip link; landmarks (`header`, `nav[aria-label]`, `main#main`, `footer`); one H1 per page; visible focus
(3 px `--color-focus` + 2 px offset, never removed); dialogs with `aria-modal`, focus trap, Esc, return focus; tabs with
`role=tablist/tab/tabpanel` + arrow keys; `details/summary` for FAQ & TOC; tables with captions and `scope`; information never by
colour alone (badges carry text); reduced motion respected; text resizable to 200 % without loss; focus never hidden under
sticky header or Quick Contact (`scroll-padding-top` + dock avoidance).
