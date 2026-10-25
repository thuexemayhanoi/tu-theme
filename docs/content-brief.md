# Content brief — Mr Tu core pages × 5 languages (v1, 2026-10-25)

Writers produce **editorial, native-quality** pages per language (localised, not translated word-for-word). Every language
uses the same facts and the same page roles; emphasis follows each locale's search intent (see `docs/keyword-research.md`,
`config/seo-plan.json`). Quality > length. No filler to reach a word count.

## 1. Voice

- **VI:** thân thiện, rõ ràng, xưng "Mr Tu" / "chúng tôi", gọi khách là "bạn". Câu ngắn, thông tin thực tế, không sáo rỗng,
  không viết hoa tràn lan, không "uy tín số 1", "giá rẻ nhất", "miễn cọc".
- **EN:** warm, concise, practical travel-host tone (British/American neutral spelling: use "motorbike", "scooter").
- **DE:** sachlich-freundlich, "Sie"-Form, klare Struktur, präzise Begriffe (Roller, Automatikroller, Halbautomatik, Kaution, Führerschein, Internationaler Führerschein).
- **FR:** chaleureux et précis, vouvoiement, typographie française (espace insécable avant « : ; ? ! », guillemets « »), "scooter", "moto", "caution", "permis".
- **ES:** cercano y claro, "tú" (público viajero), "moto", "scooter", "fianza", "carné/permiso de conducir", signos ¿ ¡.

## 2. Facts you MAY state (verified)

- Brand **Mr Tu**; website `https://hanoi.thuexemaynguyentu.com/`; phone **0816 659 199** (use the `mrtu/cta` / `mrtu/contact-card`
  blocks to show it — never hard-code the number in body text; the validator rejects it).
- Mr Tu rents motorbikes in **Hanoi**, focused on the **Old Quarter (Phố Cổ) / Hoàn Kiếm** area, for daily, weekly and monthly rental.
- Model families listed in the business data (Honda Wave = semi-automatic "xe số"; Honda Vision, Honda Air Blade, Honda Click,
  Yamaha Mio = automatic scooters). Show the actual list with the `mrtu/fleet` block; describe categories generally.
- Process principles (true for any lawful rental and safe to state as how Mr Tu works): customer chooses a model and dates →
  contacts Mr Tu → Mr Tu confirms availability, price, deposit and hand-over details **before** anything is agreed → both sides
  check the bike together at hand-over (brakes, tyres, lights, horn, mirrors, fuel level) and photograph its condition → a written
  rental agreement/receipt states price, deposit, period, condition and any delivery cost → return at the agreed time/place, deposit
  refunded per the agreement.
- Rental price and deposit are **separate**. Deposit amount depends on the model and is confirmed before booking.
- Legal background (general information, cite "as of 10/2026", link authoritative sources in the guide page only):
  Vietnam's Law on Road Traffic Order and Safety No. 36/2024/QH15 (in force since 1 January 2025): motorcycles (> 50 cm³) need an
  A1/A category licence (age 18+); "xe gắn máy" (≤ 50 cm³ and ≤ 50 km/h; electric equivalent) need no licence but riders must be 16+;
  helmets are mandatory for rider and passenger; foreigners can ride with a Vietnamese licence or an International Driving Permit
  issued under the **1968 Vienna Convention** carried **together with** their valid national licence (1949-Convention IDPs, e.g.
  from the USA/Australia, are generally **not** recognised — say "check with your embassy"); a car-only licence does not cover
  motorcycles. Always add: "This is general information, not legal advice; check the current rules and your travel insurance."
  (Cross-check every legal sentence against `/home/user/research-notes/standards.md` if present; when sources disagree, use the more
  cautious wording.)
- Vietnam's ward/district reform: since 1 July 2025 the district level was abolished; Phúc Tân street belongs to **Hồng Hà ward**,
  Hanoi. "Hoàn Kiếm" remains in common use as the area name (and a new Hoàn Kiếm ward exists). Use "khu Phố Cổ / Hoàn Kiếm" as an area,
  not as an administrative claim.

## 3. Facts you must NOT state (unverified or false)

- No prices, deposits, delivery fees, discounts, hours, street address, Google rating, review counts, years in business, fleet size,
  "new bikes", "free delivery", "15-minute delivery", "no deposit", "no licence needed", "insurance included", "24/7", airport
  pick-up, one-way rentals, trips outside Hanoi, Zalo/WhatsApp availability, payment methods, languages spoken by staff, staffed shop
  / walk-in counter, opening hours. When the reader needs any of these, write "Mr Tu confirms … when you contact us" and rely on the
  data blocks, which show verified values automatically.
- No testimonials, ratings, awards, statistics, "best", "#1", "cheapest", "guaranteed".
- Never mention Nguyễn Tú, Long Biên, 112 Nguyễn Văn Cừ or 0942 467 674.
- No passports/ID cards held as collateral claims. Say: identity documents are checked for the rental agreement; the deposit form is
  agreed lawfully before booking. (Vietnamese law prohibits pledging citizen ID cards — you may mention that ID cards are only checked,
  not pledged, as a legal fact for VI.)
- No AI disclaimers, no "as an AI".

## 4. Data blocks (insert, never hard-code the data)

| Block | Use |
|---|---|
| `<!-- wp:mrtu/fleet /-->` | models list (fleet page, home fleet section, pillar) |
| `<!-- wp:mrtu/fleet {"category":"scooter"} /-->` | scooters only |
| `<!-- wp:mrtu/pricing-table /-->` | full price table with Day/Week/Month tabs (pricing, home, pillar) |
| `<!-- wp:mrtu/pricing-table {"period":"month"} /-->` | opens on the monthly tab (monthly page) |
| `<!-- wp:mrtu/pricing-table {"period":"week"} /-->` | short-term page |
| `<!-- wp:mrtu/contact-card /-->` | phone + verified address/hours (contact page, home contact section) |
| `<!-- wp:mrtu/cta /-->` | call + contact buttons (end of service pages) |

## 5. Markup (strict — see `docs/architecture-contract.md` §3)

```html
<!-- wp:paragraph {"className":"mrtu-lead"} -->
<p class="mrtu-lead">Answer-first lead…</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Section title</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Sub section</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Text with an <a href="/en/hanoi-motorbike-rental-prices/">internal link</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>Item</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li><strong>Choose a bike</strong> — text</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Question?</summary><!-- wp:paragraph -->
<p>Direct answer in the first sentence.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:group {"tagName":"section","className":"mrtu-section mrtu-section--story"} -->
<section class="wp-block-group mrtu-section mrtu-section--story"><!-- wp:heading -->
<h2 class="wp-block-heading">…</h2>
<!-- /wp:heading --></section>
<!-- /wp:group -->

<!-- wp:group {"className":"mrtu-callout"} -->
<div class="wp-block-group mrtu-callout"><!-- wp:paragraph -->
<p><strong>Note:</strong> …</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">…</h3>
<!-- /wp:heading --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/en/contact/">Contact Mr Tu</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
```
Rules: no H1 in content; heading order H2 → H3; no inline styles; no images; no tables (use lists or the pricing block); links are
root-relative same-language URLs from §7; blank line between top-level blocks.

## 6. Page outlines (per language; words are guidance for body text)

**home** (≈ 900–1,300 words, sections in this order, each a `core/group` section with the class shown):
1. `mrtu-section--promise` — H2 + 3–4 columns (H3 + 1–2 sentences): clear process, transparent price/deposit confirmation before
   booking, daily→monthly flexibility, near the Old Quarter; help in Vietnamese and English? (**only** "we answer by phone"; do not
   promise languages).
2. `mrtu-section--fleet` — H2 + short intro + `mrtu/fleet` + link to fleet page.
3. `mrtu-section--pricing` — H2 + intro explaining price vs deposit + `mrtu/pricing-table` + link to pricing page.
4. `mrtu-section--story` — H2 + 2–3 paragraphs about starting Hanoi from the Old Quarter (Km 0 idea, streets, practical: arrange
   hand-over by phone) + link to old-quarter page.
5. `mrtu-section--steps` — H2 + ordered list of 5 steps (Choose · Contact · Confirm documents & deposit · Inspect & ride · Return).
6. `mrtu-section--trust` — H2 + 3–4 columns of practical facts (documents, deposit, licence & helmets, what is agreed in writing).
7. `mrtu-section--faq` — H2 + 5–6 `core/details` + button to guide-faq.
8. `mrtu-section--contact` — H2 + 1 paragraph + `mrtu/contact-card` + `mrtu/cta`.
**pillar** (1,800–2,500): answer-first lead; who it suits (travellers, expats, students/workers for VI); models (`mrtu/fleet`);
prices & deposit (`mrtu/pricing-table`); rental periods (link short-term & monthly); how renting works (ordered list); documents &
licence summary (link guide-faq); picking up near the Old Quarter (link old-quarter); riding safely in Hanoi (practical list);
5 FAQ (`core/details`); closing `mrtu/cta`.
**old-quarter** (1,200–1,600): what makes renting near the Old Quarter practical, the area (streets, one-way lanes, walking-street
evenings around Hoàn Kiếm lake at weekends — phrase as "usually"), parking etiquette ("gửi xe" paid parking, keep tickets), routes
out of the centre (generic), how hand-over is arranged (call ahead — no storefront claim), 4 FAQ, `mrtu/cta`.
**pricing** (900–1,300): lead; `mrtu/pricing-table`; how prices work (day vs week vs month, why month ≠ 30 × day), deposit explained
(separate, refundable per agreement, amount confirmed per model), what else may cost money (fuel you use, parking, traffic fines are
the renter's — "confirmed in the agreement"), how to get a confirmed quote, 4 FAQ, `mrtu/cta`.
**fleet** (900–1,300): lead; `mrtu/fleet`; scooter vs semi-automatic (who should choose what, city vs hills, beginners), helmets,
condition check at hand-over, availability confirmed when you contact us, 3 FAQ, `mrtu/cta`.
**short-term** (1,000–1,400): daily and weekly rental: when it fits, how pricing tabs work (`mrtu/pricing-table {"period":"week"}`),
extending a rental, returning early (policy confirmed in agreement — no promises), checklist, 4 FAQ, `mrtu/cta`.
**monthly** (1,200–1,700): monthly rental for long stays; `mrtu/pricing-table {"period":"month"}`; maintenance/servicing during the
month "agreed in the contract"; documents for long stays (confirm when contacting); budgeting (rent + deposit + fuel + parking, no
numbers); renewing; 4 FAQ; `mrtu/cta`.
**guide-faq** (1,800–2,600): How to rent (ordered steps) → documents → licence rules (§2 legal background, "as of 10/2026", with 2–4
authoritative source links: official Vietnamese government/embassy pages you can cite from the research notes) → deposit → hand-over
checklist → riding safely → **FAQ with 15–20 `core/details` Q&As** (direct first-sentence answers) → `mrtu/cta`.
**about** (800–1,200): who Mr Tu is (a motorbike rental service near Hanoi's Old Quarter), how we work (transparency, written
agreement, inspection together), what we will and won't promise, how to reach us. No invented history, staff, numbers.
**contact** (300–600): how to contact (phone via blocks), what to send when asking (model, dates, number of riders, where you stay
in the Old Quarter area), response expectations without promising times, `mrtu/contact-card`, `mrtu/cta`, link to guide-faq.
**blog** (posts page): title + short excerpt only, file content can be a single lead paragraph (the theme lists posts).
**privacy** (600–1,200): what the site processes (server logs by the host, phone calls/messages you initiate, the on-site help
assistant: questions are processed on this website without third-party AI; unanswered questions may be stored anonymously for up
to 30 days without IP/contact details; the colour-mode preference is stored in your browser's localStorage, not a cookie), cookies
(WordPress may set technical cookies for logged-in admins only; no advertising cookies are set by the theme/plugins), third-party
links (Google Maps), rights & contact, Vietnam's personal data protection law (Law on Personal Data Protection effective
1 January 2026 and Decree 13/2023/NĐ-CP — say "applicable Vietnamese law"), last updated date 25/10/2026. Add a callout: the site owner
must review this policy whenever analytics or other tools are added.
**terms** (600–1,000): use of the website, information accuracy (prices & availability confirmed at booking, the written rental
agreement prevails), intellectual property, links, liability limits, governing law Vietnam, changes, contact.
**rental-policy** (800–1,300): general rental rules every customer agrees in writing — eligibility & licence, identity check, deposit
(amount per model, confirmed before booking, refunded per agreement), inspection & photos at hand-over, fuel level noted at hand-over
and return, use restrictions (no subletting, no racing, no illegal use, helmets), traffic fines are the renter's responsibility,
breakdowns/accidents (stay safe, call 113/115 when needed, inform Mr Tu, do not repair without agreement), damage assessed fairly on
actual damage per agreement, late/early return per agreement, the signed agreement prevails. No specific fees.
**blog posts** (2 per language, 1,200–1,800 words each, category per language from §7):
- `post:riding-old-quarter` — riding & parking in Hanoi's Old Quarter (traffic flow, horn culture, one-way streets, weekend walking
  street, paid parking, rain, night riding, Google Maps tips), links to pillar + old-quarter + guide-faq.
- `post:pickup-checklist` — what to check when you pick up a rental motorbike (step-by-step inspection, photos, documents, helmet fit,
  fuel, asking questions), links to guide-faq + rental-policy + fleet.

## 7. Slugs (root-relative URLs). Core roles come from `config/seo-plan.json`; these are fixed for the rest:

| role | vi | en | de | fr | es |
|---|---|---|---|---|---|
| blog | cam-nang | guides | ratgeber | conseils | guias |
| privacy | chinh-sach-bao-mat | privacy-policy | datenschutz | politique-de-confidentialite | politica-de-privacidad |
| terms | dieu-khoan-su-dung | terms-of-use | nutzungsbedingungen | conditions-d-utilisation | terminos-de-uso |
| rental-policy | quy-dinh-thue-xe | rental-terms | mietbedingungen | conditions-de-location | condiciones-de-alquiler |
| category | kinh-nghiem | riding-tips | tipps | conseils-pratiques | consejos |

URL pattern: VI `/<slug>/`, others `/<lang>/<slug>/`. Home: `/`, `/en/`, `/de/`, `/fr/`, `/es/`.
Blog post URL: VI `/<post-slug>/`, others `/<lang>/<post-slug>/` (choose short ascii slugs).

## 8. Files to produce (per language `<lang>` in vi, en, de, fr, es)

`content/<lang>/pages.json` — array, one object per page/post:
```json
{
  "seed_id": "page:pillar",            // pages: "page:<role>"; posts: "post:<key>" (same key across languages)
  "type": "page",                      // page | post
  "role": "pillar",                    // pages only
  "slug": "motorbike-rental-hanoi",
  "title": "Motorbike Rental in Hanoi", // = H1
  "nav_label": "Rent a motorbike",      // ≤ 22 chars, for menus/breadcrumbs (pages)
  "excerpt": "Lead shown under the H1 (1–2 sentences, ≤ 200 chars)",
  "seo_title": "… | Mr Tu",            // ≤ 60 chars
  "seo_desc": "…",                     // 140–158 chars, no prices/claims
  "focus_kw": "motorbike rental hanoi",
  "file": "pillar.html",
  "status": "publish",                 // draft when you are not confident in the language quality
  "menu_order": 1,
  "category": "riding-tips"            // posts only
}
```
`content/<lang>/categories.json` — `[ { "seed_id": "cat:tips", "slug": "riding-tips", "name": "Riding tips", "description": "…" } ]`.
`content/<lang>/<file>.html` — block markup only.

## 9. AEO / GEO checklist (every page)

- First paragraph answers the page's main question in ≤ 2 sentences and names the entity ("Mr Tu rents automatic scooters and
  semi-automatic motorbikes near Hanoi's Old Quarter…").
- Clear H2 questions or task headings; lists for steps; FAQ answers start with a direct answer.
- Consistent entity naming: "Mr Tu", "Hanoi Old Quarter (Phố Cổ)", "Hoàn Kiếm".
- Dates in legal sections ("as of October 2026"). No fabricated sources; cite only real official pages from the research notes.
