# Component markup contract (plugin output ⇄ theme styling)

> The plugin renders this markup; the theme styles it. Plugin ships only minimal structural CSS (layout, visibility,
> states) using theme tokens with fallbacks, e.g. `var(--color-accent, #F56A2A)`. All text is translated; all URLs are
> same-language. `{n}` = per-instance counter for unique ids.

## Shared

- Buttons: `<a class="mrtu-button mrtu-button--accent|--action|--ghost" href="…"><svg class="mrtu-icon" aria-hidden="true" focusable="false">…</svg><span>Label</span></a>`
  (`--accent` = orange fill + black text, `--action` = blue fill + white text, `--ghost` = transparent + border).
- Plate: `<span class="mrtu-plate [mrtu-plate--sm|--lg]"><span class="mrtu-plate__code">XE GA</span><span class="mrtu-plate__name">VISION</span></span>`
- Badges: `<span class="mrtu-badge mrtu-badge--verified">…</span>`, `mrtu-badge--reference`, `mrtu-badge--pending`.
- JS flag: the plugin prints `<script>document.documentElement.classList.add('mrtu-js')</script>` in `wp_head` so progressive
  components can hide non-active panels without layout shift.

## Price (`mrtu_price_display()` → markup helper `mrtu_price_html()`)

```html
<span class="mrtu-price mrtu-price--verified">200.000 ₫</span>
<span class="mrtu-price mrtu-price--reference">200.000 ₫<span class="mrtu-price__flag" aria-hidden="true">*</span><span class="screen-reader-text">(reference price, confirm before booking)</span></span>
<a class="mrtu-price mrtu-price--on-request" href="tel:+84816659199">Price on request</a>   <!-- span when no public phone -->
```
Ranges use an en dash: `900.000–1.200.000 ₫`.

## `mrtu/pricing-table`

```html
<section class="mrtu-pricing" data-mrtu-pricing data-default="day" aria-label="Rental prices">
  <div class="mrtu-pricing__head">
    <p class="mrtu-pricing__meta"><span class="mrtu-badge mrtu-badge--verified|--reference|--pending">…</span> <span class="mrtu-pricing__updated">Updated 25/10/2026</span></p>
    <div class="mrtu-pricing__tabs" role="tablist" aria-label="Rental period">
      <button type="button" class="mrtu-pricing__tab" role="tab" id="mrtu-pt-{n}-day" aria-controls="mrtu-pp-{n}-day" aria-selected="true" tabindex="0">Daily</button>
      <button … id="mrtu-pt-{n}-week" aria-selected="false" tabindex="-1">Weekly</button>
      <button … id="mrtu-pt-{n}-month" aria-selected="false" tabindex="-1">Monthly</button>
    </div>
  </div>
  <div class="mrtu-pricing__panel is-active" role="tabpanel" id="mrtu-pp-{n}-day" aria-labelledby="mrtu-pt-{n}-day" data-period="day">
    <table class="mrtu-pricing__table">
      <caption class="screen-reader-text">Daily rental prices</caption>
      <thead><tr><th scope="col">Model</th><th scope="col">Price / day</th><th scope="col">Deposit</th></tr></thead>
      <tbody>
        <tr class="mrtu-pricing__row" data-category="scooter">
          <th scope="row"><span class="mrtu-pricing__model">Honda Vision</span> <span class="mrtu-pricing__type">Scooter</span></th>
          <td><!-- price markup --></td>
          <td><!-- deposit price markup --></td>
        </tr>
      </tbody>
    </table>
  </div>
  <!-- week + month panels: same structure, without .is-active -->
  <p class="mrtu-pricing__note">Deposit is separate from the rental price and refunded per the contract…</p>
</section>
```
No-JS: every panel is visible, tabs hidden (`:root:not(.mrtu-js) .mrtu-pricing__tabs { display: none }`). JS (`assets/js/pricing.js`):
roving tabindex, arrow/Home/End keys, toggles `.is-active` + `hidden` on panels.

## `mrtu/fleet`

```html
<div class="mrtu-fleet" data-mrtu-fleet>
  <div class="mrtu-fleet__filters" role="group" aria-label="Vehicle type">   <!-- only when ≥ 2 categories -->
    <button type="button" class="mrtu-fleet__filter" aria-pressed="true" data-filter="all">All</button>
    <button type="button" class="mrtu-fleet__filter" aria-pressed="false" data-filter="scooter">Scooters</button>
    <button type="button" class="mrtu-fleet__filter" aria-pressed="false" data-filter="manual">Semi-automatic</button>
  </div>
  <ul class="mrtu-fleet__list" role="list">
    <li class="mrtu-vehicle" data-category="scooter">
      <article class="mrtu-vehicle__card">
        <div class="mrtu-vehicle__media"><!-- <img> when image_id, otherwise the plate (aria-hidden) --></div>
        <div class="mrtu-vehicle__body">
          <h3 class="mrtu-vehicle__name">Honda Vision</h3>
          <p class="mrtu-vehicle__type">Scooter</p>
          <p class="mrtu-vehicle__desc">…</p>
          <dl class="mrtu-vehicle__prices">
            <div class="mrtu-vehicle__price"><dt>Day</dt><dd><!-- price markup --></dd></div>
            <div class="mrtu-vehicle__price"><dt>Week</dt><dd>…</dd></div>
            <div class="mrtu-vehicle__price"><dt>Month</dt><dd>…</dd></div>
          </dl>
        </div>
      </article>
    </li>
  </ul>
</div>
```

## `mrtu/contact-card`

```html
<div class="mrtu-contact-card" data-mrtu-qc-avoid>
  <dl class="mrtu-contact-card__list">
    <div class="mrtu-contact-card__item mrtu-contact-card__item--phone"><dt>Phone</dt><dd><a href="tel:+84816659199">0816 659 199</a></dd></div>
    <div class="mrtu-contact-card__item mrtu-contact-card__item--address"><dt>Address</dt><dd>…</dd></div>   <!-- only when verified -->
    <div class="mrtu-contact-card__item mrtu-contact-card__item--hours"><dt>Hours</dt><dd>…</dd></div>       <!-- only when verified -->
    <div class="mrtu-contact-card__item mrtu-contact-card__item--area"><dt>Area</dt><dd>Old Quarter, Hanoi</dd></div> <!-- when address not public -->
  </dl>
  <p class="mrtu-contact-card__note">Please call ahead to arrange the hand-over time and place.</p>  <!-- while service model unverified -->
</div>
```

## `mrtu/cta`

```html
<div class="mrtu-cta mrtu-cta--call">
  <a class="mrtu-button mrtu-button--action" href="tel:+84816659199">…<span>Call 0816 659 199</span></a>
  <a class="mrtu-button mrtu-button--ghost" href="/en/contact/">…<span>Contact page</span></a>
</div>
```

## Quick Contact dock (`wp_footer`)

```html
<div class="mrtu-qc" data-mrtu-qc data-position="right">
  <div class="mrtu-qc__panel" id="mrtu-qc-panel" hidden>
    <a class="mrtu-qc__item mrtu-qc__item--call" href="tel:+84816659199"><svg class="mrtu-icon" aria-hidden="true">…</svg><span>Call</span></a>
    <button type="button" class="mrtu-qc__item mrtu-qc__item--chat" data-mrtu-assistant-open><svg …/><span>Ask a question</span></button>
  </div>
  <button type="button" class="mrtu-qc__toggle" aria-expanded="false" aria-controls="mrtu-qc-panel"><svg …/><span class="mrtu-qc__label">Contact</span></button>
</div>
```
States on the root: `.is-open`, `.is-hidden` (avoid zones, keyboard, assistant open), `.is-compact` (after scrolling down).

## TOC

See `docs/architecture-contract.md` §2.9. Root `details.mrtu-toc` (closed by default), `summary.mrtu-toc__summary`,
`nav.mrtu-toc__nav > ol.mrtu-toc__list > li.mrtu-toc__item.mrtu-toc__item--h2|--h3 > a`, active link `aria-current="true"`,
reading progress in `--mrtu-read-progress` (0–1) on `<html>`.
