# Nghiên cứu kỹ thuật — Mr Tu (theme `mr-tu`, plugin `mr-tu-multilang-seo`, chatbot riêng)

> **Ngày nghiên cứu:** 25/10/2026 (khảo sát site công khai kéo sang 26/10/2026 giờ Hà Nội).
> **Phạm vi:** một WordPress duy nhất tại `https://hanoi.thuexemaynguyentu.com/`, 5 ngôn ngữ (VI tại `/`, EN `/en/`, DE `/de/`, FR `/fr/`, ES `/es/`), WordPress 7.1.x (bản cục bộ 7.1.3), code tương thích PHP ≥ 7.4, Hostinger + LiteSpeed Cache, Yoast SEO + Yoast SEO Premium có mặt trên production.
> **Vai trò tài liệu:** tổng hợp bằng chứng và bài học để ra quyết định kiến trúc. Tài liệu này **không** thay thế `README.md` (nguồn yêu cầu) hay `docs/architecture-contract.md` (hợp đồng kỹ thuật). Mọi chỗ nghiên cứu khác với contract đều được đánh dấu **⚠ Cần chốt** ở §4.9.
> **Không phải tư vấn pháp lý.** Các dữ kiện luật Việt Nam ở §3.11 là thông tin chung, cần người có thẩm quyền rà soát trước khi đăng.

**Ký hiệu bằng chứng** (giữ nguyên từ notes gốc):

| Ký hiệu | Nghĩa |
|---|---|
| **[probe]** | Đã chạy thử bằng `wp eval-file` trên site SQLite cục bộ 7.1.3, chỉ dùng filter trong bộ nhớ; checksum DB trước/sau giống nhau. |
| **[inference]** | Suy luận của người nghiên cứu, tài liệu chính thức không nói thẳng. |
| **[unverified]** | Chỉ có nguồn thứ cấp hoặc chưa đối chiếu được nguồn chính thức. |
| `file:line` | Dòng trong bản core cục bộ `/home/user/wpenv/wordpress` (`$wp_version = '7.1.3'`). |

---

## 1. Mục tiêu & phương pháp nghiên cứu

### 1.1 Mục tiêu

1. Xác minh, bằng code core thật của WordPress 7.1.3, rằng mô hình **một domain + prefix ngôn ngữ** có thể làm mà không sửa `siteurl`/`home`, không Multisite, không plugin đa ngôn ngữ/SEO bên thứ ba.
2. Học kiến trúc từ các repo chuyên gia (Polylang, Yoast, core, Gutenberg…) để **tự viết** code tốt hơn, không sao chép.
3. Chốt các chuẩn bắt buộc (Google Search Central, schema.org, web.dev, WCAG 2.2, WordPress handbooks) tại thời điểm 25/10/2026, vì nhiều tính năng đã thay đổi trong 2025–2026.
4. Hiểu hiện trạng production (`hanoi.thuexemaynguyentu.com`) và website tham chiếu (`thuexemaynguyentu.com`) để tránh xung đột, trùng lặp và rủi ro NAP.
5. Dựng sẵn bộ công cụ QA chạy được trong container (không Docker, không API trả phí).

### 1.2 Phương pháp

| Mảng | Cách làm | Ghi chú giới hạn |
|---|---|---|
| Core internals | Đọc code `wp-includes/*` bản 7.1.3 cục bộ, trích `file:function`/dòng; chạy **probe script** với filter in-memory (`/tmp/claude-0/-home-user-tu-theme/fd9e4957-12cc-5182-a5be-65ad9274a818/scratchpad/router-probe.php`). Site thử dùng permalink `/%postname%/`, `show_on_front=posts`; static front page được giả lập bằng `pre_option_*`. | Không đụng production. |
| Repo chuyên gia | 18 repo clone nông (`--depth 1 --filter=blob:none --sparse`) vào `/home/user/research/<name>`; ngày commit cuối lấy bằng `git log -1 --format=%cI`; số sao từ GitHub search API (25/10/2026). | Yoast Premium là closed source, không nghiên cứu. |
| Chuẩn & tài liệu chính thức | Đọc trang chính thức qua TinyFish fetch, dữ liệu trình duyệt qua MDN BCD, code qua clone Lighthouse 13.5.0, wordpress-develop, gutenberg, aria-practices, schemaorg. | WebFetch không phân giải được các domain tài liệu; Firecrawl hết credit nên **không dùng**. |
| Site tham chiếu & production | TinyFish `fetch_content` + Nimble `extract` (có `<head>`), REST namespace `/wp-json/`, comment HTML, đường dẫn asset. | Ubersuggest hết hạn mức → **không có số liệu traffic/top pages**. Chỉ đọc công khai, không đăng nhập. |
| Từ khóa | OpenSEO, OpenRush, autocomplete Ubersuggest; chi tiết ở `docs/keyword-research.md` (xem Phụ lục A). | DE/FR/ES thiếu nhiều số liệu. |

### 1.3 Tiêu chí đánh giá repo (không chỉ dựa vào số sao)

Mỗi repo được chấm theo bốn tiêu chí; số sao chỉ là tín hiệu phụ:

1. **Bảo trì:** commit gần nhất, release còn sống, tương thích WordPress 7.x / PHP 7.4. Tất cả repo được dùng đều có commit trong 2026 (xem §2).
2. **Giấy phép:** tương thích GPL cho phần có thể tái sử dụng. Code **GPL-3.0** (Polylang, Yoast) chỉ được dùng làm bài học kiến trúc, **không chép code**. Công cụ dev (MIT/Apache/LGPL/BSD) chỉ chạy ngoài gói phát hành.
3. **Bảo mật:** mẫu escaping/nonce/capability đúng chuẩn; loại bỏ các mẫu rủi ro hoặc chậm (vd. `debug_backtrace()` để lọc URL, gọi tài nguyên từ xa, ghi file vào thư mục plugin).
4. **Phù hợp kiến trúc:** dùng WordPress Core API, một domain, không Multisite, không bước build JS, chạy được trên shared hosting + LiteSpeed.

Ví dụ: MSLS (★141) vẫn được dùng làm bài học hreflang vì cách xuất đúng chuẩn; Lighthouse (★30.943) được dùng vì là công cụ đo chính thức, không vì số sao.

---

## 2. Bảng nguồn

### 2.1 Repo đã nghiên cứu (clone tại `/home/user/research/`)

| Repo / URL | Giấy phép | Cập nhật gần nhất | Đánh giá | Áp dụng gì | Không áp dụng gì |
|---|---|---|---|---|---|
| [WordPress/wordpress-develop](https://github.com/WordPress/wordpress-develop) | GPL-2.0-or-later | 2026-10-24 (trunk 7.2-beta1) · ★3.490 | Nguồn chuẩn; mọi symbol đã đối chiếu bản 7.1.3 | Filter sitemap/robots/canonical, link filters, speculative loading mặc định, thuộc tính ảnh, `should_load_separate_core_block_assets` | Thay renderer sitemap core; key ngoài whitelist; hard-code `<meta name="robots">`; flush rewrite mỗi request; đổi `siteurl`/`home` |
| [WordPress/gutenberg](https://github.com/WordPress/gutenberg) | GPL-2.0-or-later (đóng góp từ 15/04/2021 dual GPL/MPL) | 2026-10-25 (24.3.0-rc.1) · ★11.777 | Chuẩn `block.json`/`theme.json` | `apiVersion: 3`, `render` file, block PHP-only (`autoRegister`) hoặc `editor.js` không build dùng `ServerSideRender`; `theme.json` v3 schema `wp/7.1` | Webpack/`@wordpress/scripts`; block tĩnh `save()` cho dữ liệu kinh doanh; `templates/index.html` trong theme |
| [WordPress/WordPress-Coding-Standards](https://github.com/WordPress/WordPress-Coding-Standards) | MIT | 2026-10-23 (3.4.1, 27/07/2026) · ★2.836 | Bắt buộc cho CI | WordPress-Extra + Docs; 0 lỗi `OutputNotEscaped`; prefix `mr_tu_`/`mrtu_`/`MrTu` | `phpcs:disable` hàng loạt; chỉ ignore theo dòng, có mã sniff + lý do |
| [WordPress/plugin-check](https://github.com/WordPress/plugin-check) | GPL-2.0-or-later | 2026-10-22 (v2.1.0) · ★365 | Công cụ chính thức, có runtime checks | Gate mọi ZIP plugin (gồm chatbot) bằng static + runtime checks | Chạy runtime checks trên production (tạo bảng tạm + drop-in `object-cache.php`); tùy chọn `--ai` |
| [WordPress/theme-check](https://github.com/WordPress/theme-check) | GPLv2 (header plugin; repo không có file LICENSE) | 2026-09-02 (20260901) · ★372 | Chạy headless qua WP-CLI | Danh sách REQUIRED cho classic theme; phân định “plugin territory” | Đặt CPT/shortcode/block/SEO trong theme |
| [WordPress/performance](https://github.com/WordPress/performance) | GPL-2.0-or-later | 2026-10-22 · ★466 | Tham khảo kỹ thuật hiệu năng | 1 ảnh LCP `fetchpriority`, lazy dưới màn hình đầu, WOFF2, aspect-ratio; dựa vào speculative loading của core | Đóng gói code Performance Lab; phụ thuộc Optimization Detective; >1 ảnh `fetchpriority=high` |
| [GoogleChrome/lighthouse](https://github.com/GoogleChrome/lighthouse) | Apache-2.0 | 2026-10-23 (13.5.0) · ★30.943 | Công cụ đo chuẩn | Median 5 lần mobile; ngân sách điểm | PSI API key, LHCI server; tin 1 lần chạy |
| [polylang/polylang](https://github.com/polylang/polylang) | **GPL-3.0-or-later** | 2026-10-21 (4.0.0-dev) · ★389 | Kiến trúc trưởng thành nhưng có điểm yếu | **Chỉ bài học**: ngôn ngữ = taxonomy, group map, phát hiện ngôn ngữ sớm, `page_link` cho home từng ngôn ngữ | Chép code; lọc `home_url` toàn cục bằng `debug_backtrace()`; map serialize tìm bằng `LIKE`; redirect theo cookie/trình duyệt; prefix `/vi/` |
| [Yoast/wordpress-seo](https://github.com/Yoast/wordpress-seo) | GPL-3.0 (`license.txt`; composer ghi GPL-2.0-or-later) | 2026-10-23 (28.8-RC6) · ★2.008 | Đang chạy trên production → bắt buộc tương thích | Hợp đồng chung sống qua filter `wpseo_*` (§3.5) | Tắt/cấu hình lại Yoast; ghi `wpseo_*` options hay bảng `wp_yoast_*`; xuất `<title>`/canonical/JSON-LD thứ hai |
| [KittyGiraudel/a11y-dialog](https://github.com/KittyGiraudel/a11y-dialog) | MIT | 2026-10-22 (v8.1.5) · ★2.490 | Mẫu dialog chuẩn APG, ~1,7 KB | Hợp đồng hành vi (focus trap, Esc, trả focus) tự viết ~60 dòng JS hoặc `<dialog>` native | Thêm dependency; dialog lồng nhau |
| [w3c/aria-practices](https://github.com/w3c/aria-practices) | W3C Software and Document License | 2026-09-29 · ★1.360 | Nguồn chuẩn APG | Disclosure navigation, `aria-current="page"` | `role="menu"`/`menubar` cho điều hướng site |
| [lloc/Multisite-Language-Switcher](https://github.com/lloc/Multisite-Language-Switcher) | GPL-2.0-or-later | 2026-10-24 (3.1.0) · ★141 | Ít sao nhưng xuất hreflang đúng | hreflang chỉ mã ngôn ngữ khi mỗi ngôn ngữ có 1 biến thể; escape `esc_url`/`esc_attr` | Kiến trúc Multisite (README cấm) |
| [WordPress/sqlite-database-integration](https://github.com/WordPress/sqlite-database-integration) | GPL-2.0-or-later | 2026-10-24 · ★364 | Đã kiểm chứng chạy installer PHPUnit | Test tích hợp trên SQLite; tránh SQL đặc thù MySQL hoặc có guard | Đóng gói drop-in `db.php` vào sản phẩm |
| [wp-phpunit/wp-phpunit](https://github.com/wp-phpunit/wp-phpunit) | GPL-2.0-or-later | 2026-08-20 (cài 7.1.1) · ★82 | Thư viện test core dạng composer | `WP_UnitTestCase`, `go_to()`, factories cho test router/SEO | Docker, `wp-env` |
| [Yoast/wp-test-utils](https://github.com/Yoast/wp-test-utils) | BSD-3-Clause | 2026-08-07 · ★62 | **Không cài**: nhánh `develop` cần polyfills ^1.1.5, xung đột polyfills 4 | BrainMonkey cho unit test thuần là tùy chọn về sau | — |
| [PHPCompatibility/PHPCompatibilityWP](https://github.com/PHPCompatibility/PHPCompatibilityWP) | LGPL-3.0-or-later | 2026-10-22 · ★224 (PHPCompatibility ★2.308) | Bản stable 2.1.8/9.3.5 **bỏ sót cú pháp PHP 8** (đã kiểm chứng) | Chạy song song 9.3.5 và 10.0.0-alpha2 | Cú pháp PHP 8 (`match`, `?->`, named args, union types, enum, readonly, constructor promotion) |
| [fonttools/fonttools](https://github.com/fonttools/fonttools) | MIT | 2026-10-22 (4.67.0) · ★5.299 | Pipeline subset đã chạy thật | Inter variable 877 KB → **60 KB WOFF2**, đủ glyph 5 ngôn ngữ | Subset mất dấu kết hợp `U+0300-0309, U+0323` |
| [google/fonts](https://github.com/google/fonts) | Theo họ font (Inter = OFL 1.1) | 2026-10-23 · ★20.680 | Nguồn font gốc | Self-host, kèm `OFL.txt` | Google Fonts CDN |

**Clone bổ sung do agent khác tạo** (dùng làm bằng chứng, không đánh giá như thư viện):

| Repo | Dùng cho | Giấy phép / cập nhật |
|---|---|---|
| GlotPress (`/home/user/research/glotpress`) | Bằng chứng locale tiếng Việt: `locales/locales.php` dòng 2804–2815 (`wp_locale = 'vi'`, `facebook_locale = 'vi_VN'`) | Không ghi nhận trong notes |
| schemaorg/schemaorg (`data/schema.ttl`) | Danh sách subtype `AutomotiveBusiness`, định nghĩa `inLanguage` (schema.org 30.1, 16/09/2026) | Không ghi nhận trong notes |

### 2.2 Tài liệu chuẩn (đều kiểm tra 25/10/2026)

| Nhóm | Nguồn chính | Dùng cho |
|---|---|---|
| Google Search Central | `localized-versions`, `managing-multi-regional-sites`, `locale-adaptive-pages`, `consolidate-duplicate-urls`, `build-sitemap`, `large-sitemaps`, `sd-policies`, `search-gallery`, `organization`, `site-names`, `breadcrumb`, `article`, `local-business`, `product`, `product-snippet`, `merchant-listing`, `review-snippet`, blog 2023/08 FAQ/HowTo, blog 2025/06, changelog `/search/updates` | hreflang, canonical, sitemap, structured data |
| schema.org | `AutoRental`, `UnitPriceSpecification`, releases (30.1) | Kiểu và thuộc tính JSON-LD |
| web.dev / Chrome | `vitals`, `inp`, `optimize-lcp`, `fetch-priority`, `preload-critical-assets`, `font-best-practices`, Lighthouse performance scoring + source 13.5.0 | Ngưỡng CWV, trọng số Lighthouse |
| WebKit / MDN BCD | WebKit Features in Safari 26.0; các key BCD (view-transition, animation-timeline, speculationrules, dvh, env, viewport, VisualViewport, fetchpriority, details name, dialog closedby…) | Hỗ trợ trình duyệt, đặc biệt iOS Safari |
| W3C | WCAG 2.2 Understanding 2.4.11, 2.5.8, 3.3.7, 3.2.6, 1.4.3; APG disclosure, dialog-modal | Accessibility |
| WordPress | theme.json intro, `add_theme_support`, block patterns, Theme Review “Required” (cập nhật 09/06/2026), escaping, nonces, capabilities, Settings API, REST custom endpoints, uninstall | Chuẩn theme/plugin |
| Việt Nam | Luật 36/2024/QH15 (Điều 33, 57, 59 qua bản mirror hethongphapluat.com), UN Treaty Collection XI-B-19, UK FCDO, Smartraveller (AU), Cổng TTĐT Chính phủ, danh sách 126 xã/phường Hà Nội (luatvietnam.vn) | Nội dung bằng lái, địa chỉ |

Danh sách URL đầy đủ nằm ở `/home/user/research-notes/standards.md` §8.

---

## 3. Bài học kỹ thuật theo từng thành phần

### 3.1 Router đa ngôn ngữ (prefix URL)

**Cơ chế core đã kiểm chứng**

- `WP::main()` gọi `parse_request()`; **filter `do_parse_request` chạy đầu tiên** (`class-wp.php` dòng 148), sau đó `REQUEST_URI` và `PATH_INFO` được đọc **một lần duy nhất** (dòng 170–201) để tính `$wp->request`. Vì vậy tạm cắt `/en` khỏi `REQUEST_URI` trong `do_parse_request` là đủ để toàn bộ rewrite rule hiện có khớp như URL VI.
- **[probe]** `/en/sample-page/` sau khi cắt → `pagename=sample-page`, `is_page`, `$wp->request = 'sample-page'`. `/en/page/2/` → `paged=2`.
- Nếu router **không** cắt, `/en/sample-page/` khớp rule attachment `[^/]+/([^/]+)/?$` → `attachment=sample-page` (404 hoặc redirect tới file đính kèm), không phải trang. Ngôn ngữ đã tắt phải được ép 404 tường minh, không để rơi vào rule attachment.
- Sau `parse_request` có nhiều nơi **đọc lại `REQUEST_URI`**, nên bắt buộc khôi phục: `redirect_canonical()` (`canonical.php` dòng 73), `add_query_arg()`/`remove_query_arg()` không truyền URL, `get_pagenum_link()` + `paginate_links()`, menu “current item” với custom link (`nav-menu-template.php` dòng 478), `wp_redirect_admin_locations()`, `wp_login_form` redirect, admin bar, feed `self_link`. **[probe]** Có khôi phục: `get_pagenum_link(3)` = `/en/page/3/`; không khôi phục: `/page/3/`. ⇒ Không cần filter `get_pagenum_link` riêng nếu khôi phục đúng.

**Quy tắc cắt/khôi phục an toàn**

1. Đăng ký callback `do_parse_request` ở priority `PHP_INT_MAX`, **chỉ hành động khi `$bool === true`** (callback khác có thể trả `false`, khi đó `parse_request` không chạy và URI sẽ bị kẹt ở dạng đã cắt).
2. Khôi phục ở action `parse_request` priority `PHP_INT_MIN` (trước `rest_api_loaded` ở priority 10, `default-filters.php` dòng 549). Khôi phục dự phòng ở `wp` hoặc `template_redirect` priority 0.
3. Cắt cả `PATH_INFO` khi khác rỗng (với LiteSpeed + `.htaccess` chuẩn thường không đặt).
4. Khớp prefix chính xác bằng `#^/(en|de|fr|es)(/|\?|$)#` để `/english-guide/` không bị bắt; giữ query string (`/en/?s=x` → `/?s=x`).
5. Không cắt khi phần còn lại bắt đầu bằng `wp-json`, `wp-admin`, `wp-login.php`, `wp-sitemap*`, `robots.txt`, `favicon.ico`, `xmlrpc.php` (contract hiện có thêm `wp-content`, `wp-includes` — giữ cả hai danh sách).
6. Gắn lại prefix vào `$wp->request` (`'en/' . $wp->request`) khi khôi phục; core không đọc lại, nhưng theme/plugin hay gọi `home_url( $wp->request )`.
7. **Không đưa ngôn ngữ vào query var** (kể cả private như `mrtu_lang`): `WP::query_posts()` chuyển mọi query var vào `$wp_query->query`, bất kỳ key thừa nào cũng phá logic nhận diện static front page (`class-wp-query.php` dòng 1057–1076). **[probe 5]** `/en/` thành blog listing. Ngôn ngữ hiện hành chỉ nằm trong state của plugin.

**Bản dịch sai ngôn ngữ vẫn trả 200 — router phải tự kiểm tra**

- `redirect_canonical()` **không bao giờ so request singular với `get_permalink()`**. **[probe 2]** `/en/sample-page` chỉ được thêm dấu `/`, prefix giữ nguyên → trang VI phục vụ dưới `/en/` với HTTP 200.
- `tax_query` thêm trong `pre_get_posts` **bị bỏ qua với truy vấn singular** (`p`, `page_id`, `name`, `pagename`, attachment): `class-wp-query.php` dòng 2300–2307 chỉ parse tax query khi `! $this->is_singular`. **[probe 7a]** không có join `term_relationships`; **[probe 7b]** cùng `tax_query` áp dụng bình thường trên blog home.
- `get_page_by_path()` (`post.php` dòng 6281) **không có filter, không biết ngôn ngữ, bỏ qua post_status** — **[probe]** trả về trang *draft*. Rule verbose page cũng gọi hàm này lúc khớp rule.
- ⇒ Hai lớp bảo vệ: (1) filter `request` phân giải `pagename`/`name` trong đúng ngôn ngữ, không thấy thì `$qv['error'] = '404'` (cách sạch để ép 404 trước khi query chạy); (2) trên `wp` hoặc `template_redirect` (trước `redirect_canonical` priority 10) kiểm tra ngôn ngữ của queried object → `set_404()` + `status_header(404)` + `nocache_headers()` hoặc 301 sang bản dịch đúng.
- Lưu trữ theo taxonomy riêng (`is_tax`): clause ngôn ngữ thêm vào `tax_query` có thể thành queried object (dòng 4016–4028) và làm `redirect_canonical` ngừng canonical hóa khi đếm >1 term. Ưu tiên term riêng theo ngôn ngữ hoặc ràng buộc qua `posts_clauses`/`posts_where`.

**Redirect có thể nhảy ngôn ngữ**

- Đoán URL khi 404 (`redirect_guess_404_permalink`) dùng `post_name LIKE 'name%'` trên mọi post type công khai, không biết ngôn ngữ → `/en/xyz` có thể 301 sang bài VI. Bật `strict_redirect_guess_404_permalink` hoặc tự trả lời qua `pre_redirect_guess_404_permalink`.
- `wp_old_slug_redirect` khớp `_wp_old_slug` trên **mọi ngôn ngữ** → kiểm tra bằng `old_slug_redirect_post_id`. **Page (hierarchical) không bao giờ có old-slug 301 tự động** (`query.php` dòng 1075; `wp_check_for_changed_slugs()`), nên plugin cần bảng redirect riêng (contract đã có `mrtu_redirects`).

**Link**

- **Không filter `home_url` toàn cục**: `get_rest_url()` dùng `get_home_url(..., 'rest')` nên REST sẽ chuyển sang `/en/wp-json/`, đồng thời gắn prefix vào bài VI, URL sitemap, `/wp-sitemap.xsl`. Polylang phải dùng whitelist/blacklist `debug_backtrace()` — chậm và mong manh.
- Gắn prefix **theo đối tượng** qua `post_link`, `page_link`, `post_type_link`, `term_link`; **theo request** qua `search_link`, action của search form, feed links. **[probe 2]** Thiếu `page_link`, canonical của `/en/sample-page/` là `/sample-page/`. Theme gọi `mrtu_home_url()` cho logo/menu/search.
- Menu item kiểu post lấy URL từ `get_permalink()` nên tự có prefix. Đổi menu theo ngôn ngữ bằng `theme_mod_nav_menu_locations`/`wp_nav_menu_args` **chỉ ở frontend** — màn hình Menus trong admin đọc `get_nav_menu_locations()` rồi `set_theme_mod()` lại, sẽ lưu vĩnh viễn giá trị đã filter. Đơn giản nhất: đăng ký location theo ngôn ngữ (`primary_vi`, `primary_en`…).
- Speculative loading của core vô hại với URL `/xx/`; LiteSpeed cache theo URL nên mô hình prefix tương thích hoàn toàn (không dùng cookie/Accept-Language).

**Locale & i18n**

- `get_locale()` luôn kết thúc bằng `apply_filters( 'locale', … )`; frontend dùng `get_locale()`, admin dùng user locale (`determine_locale()`).
- Thứ tự `wp-settings.php`: plugin được include (dòng 582–608) → `plugins_loaded` (630) → `setup_theme` (705) → **`load_default_textdomain()` (712)** → `new WP_Locale()` (728) → `WP_Locale_Switcher` (737–738) → `after_setup_theme` (757) → `init` (779). ⇒ **Đăng ký filter `locale` ngay khi file plugin được nạp**, dựa trên prefix thô của `REQUEST_URI`; khi đó chuỗi core, tên tháng/ngày, locale switcher và mọi text domain just-in-time đều đúng ngôn ngữ từ đầu, không cần “unload/reload” kiểu Polylang.
- Rủi ro: domain nào đã nạp trước khi biết ngôn ngữ sẽ kẹt ở ngôn ngữ cũ; WordPress 6.7+ cảnh báo “triggered too early” nếu gọi `__()` trước `after_setup_theme`. `block.json` `title`/`description` được dịch lúc đăng ký ở `init`.
- `switch_to_locale()` trả `false` nếu gói ngôn ngữ core chưa cài. Trên production cần cài core pack `vi`, `de_DE`, `fr_FR`, `es_ES`, nếu không chuỗi core (form bình luận, “Search”, tên tháng) rơi về tiếng Anh.
- File dịch `.l10n.php` (6.5+) được thử trước `.mo`; có thể viết tay mảng PHP, không cần `msgfmt`. Plugin: `languages/mr-tu-multilang-seo-{locale}.l10n.php`; theme: `languages/{locale}.l10n.php`.
- **Locale tiếng Việt của WordPress là `vi`, không phải `vi_VN`** (GlotPress: `wp_locale = 'vi'`, `facebook_locale = 'vi_VN'`). README §3 ghi `vi_VN` cần sửa.
- `get_bloginfo('language')` ưu tiên chuỗi `html_lang_attribute` do core pack dịch (vd. `de-DE`), nếu không thì đổi `_` thành `-`. **[probe 8]** locale `vi` → `lang="vi"`. Muốn `<html lang>` theo mã cấu hình thì filter `language_attributes`.

### 3.2 Translation groups

**Bài học từ Polylang (chỉ kiến trúc)**

- Ngôn ngữ là term của taxonomy (`language`, `term_language`); nhóm bản dịch là term `post_translations`/`term_translations` với **map serialize `[lang => object_id]` trong cột description**; tra ngược bằng SQL `description LIKE %s`; cập nhật hàng loạt bằng SQL thô trên `term_taxonomy`.
- Ưu: tận dụng taxonomy core và object cache, `tax_query` theo ngôn ngữ rẻ cho danh sách. Nhược: dữ liệu mờ trong cột description, tìm kiếm bằng `LIKE`, chiếm dụng cột của core.

**Áp dụng cho Mr Tu**

- Map nhóm phải là **bộ nhớ đệm có thể dựng lại từ term relationships**, không phải nguồn sự thật duy nhất; mọi tra ngược đi qua relationships (vd. term của bài → term nhóm), **không bao giờ `LIKE` trên description**. Cache trong object cache (nhóm `mrtu`) để tránh N+1 khi render switcher/hreflang (README §5.3).
- `wp_insert_post()` tính slug (dòng 4906) **trước** khi gán term (5090–5108) và meta (5111); REST controller cũng vậy (`handle_terms()` sau insert). ⇒ Filter slug cho bài mới phải lấy ngôn ngữ từ request (`$_POST`, body REST) hoặc từ “ngữ cảnh ngôn ngữ chờ” của plugin, không đọc từ DB.
- `tax_input` **bị bỏ qua im lặng** nếu user hiện tại không có `assign_terms` (WP-CLI, cron, seeder) → seeder phải gọi `wp_set_object_terms()` trực tiếp.
- Tính duy nhất của slug: loại phẳng (post) duy nhất theo `post_type`; loại phân cấp (page) chỉ duy nhất giữa các anh em cùng `post_parent`. Hook để cho phép cùng slug khác ngôn ngữ: `pre_wp_unique_post_slug`, `wp_unique_post_slug_is_bad_flat_slug`, `wp_unique_post_slug_is_bad_hierarchical_slug`, `wp_unique_post_slug`. Draft/pending giữ `post_name` rỗng tới khi publish.
- Không tự sinh bản dịch rỗng; chỉ copy bản gốc thành nháp khi chủ site yêu cầu (README §5.1).

### 3.3 SEO head (canonical, hreflang, robots, OG, JSON-LD)

**Canonical**

- Core `rel_canonical()` chỉ in trên **singular**, qua `wp_get_canonical_url()` (filter `get_canonical_url`); archive, home, search, 404 không có canonical core. Yoast thay thế hoàn toàn `rel_canonical`.
- Canonical **luôn tự trỏ, cùng ngôn ngữ**, URL tuyệt đối https; không bao giờ canonical EN/DE/FR/ES về VI. Chỉ một cách khai báo (HTML, không kèm HTTP header). Không để JS đổi canonical.

**hreflang** (Google: HTML `<link>`, HTTP header, sitemap là tương đương; chọn một và giữ nhất quán)

- Mã: `vi`, `en`, `de`, `fr`, `es` + `x-default`. Mã vùng đơn lẻ không hợp lệ; `vi-VN` hợp lệ nhưng thu hẹp phạm vi khớp mà không thêm lợi ích (xem ⚠ §4.9).
- Mỗi thành viên **đã publish, indexable, tự canonical, trả 200** liệt kê chính nó và mọi thành viên khác; tập `<link>` **giống hệt** trên mọi thành viên; thiếu liên kết hai chiều thì Google bỏ qua cặp đó. Bản dịch thiếu bị loại khỏi tập của **mọi** thành viên.
- Không xuất hreflang trên trang noindex, tìm kiếm; archive chỉ nối giữa các tương đương thật (cùng nhóm term, cùng số trang) — Polylang và MSLS bỏ qua trên trang phân trang.
- Google xác định ngôn ngữ bằng **nội dung hiển thị**, không bằng `hreflang`/`lang`/URL ⇒ menu, footer, CTA không được trộn ngôn ngữ.
- **Không bao giờ tự redirect theo IP hay `Accept-Language`**: Googlebot chủ yếu crawl từ Mỹ và không gửi `Accept-Language`. Gợi ý “đổi ngôn ngữ” không chặn thì chấp nhận được.

**Robots**

- Chỉ dùng filter `wp_robots` (hook `wp_head` priority 1); core đã có `wp_robots_noindex` (khi `blog_public=0`), `wp_robots_noindex_search`, `wp_robots_noindex_embeds`, `max-image-preview:large`. Không echo `<meta name="robots">` thủ công.
- `noindex` cho fallback chưa dịch, tìm kiếm nội bộ, trang chưa sẵn sàng; áp dụng nhất quán trên mọi locale.

**Title & Open Graph**

- Standalone: `pre_get_document_title` / `document_title_parts`; theme giữ `add_theme_support('title-tag')`, không tự in `<title>`. Khi có Yoast thì dùng `wpseo_title` (Yoast chiếm `pre_get_document_title` ở priority 15).
- Lỗi production hiện tại: `<title>Mr Tu -</title>` (separator thừa do tagline trống), không có meta description, không có `og:image`/`og:description`. Title/description theo ngôn ngữ đã có trong `config/seo-plan.json`.
- `og:locale` = `vi_VN`, `en_US`, `de_DE`, `fr_FR`, `es_ES`; thêm `og:locale:alternate` cho bản dịch có thật.

**JSON-LD** (JSON-LD là định dạng khuyến nghị; không markup nội dung không hiển thị)

- **`WebSite` chỉ có tác dụng site name ở trang gốc `/`**, không ở `/en/`, `/de/`… Một node duy nhất `@id …/#website`, `name: "Mr Tu"`, `url` = root; **không** `SearchAction` (sitelinks search box đã bị gỡ năm 2024).
- `WebPage` cho mỗi URL với `inLanguage` theo BCP 47, `isPartOf` → `#website`, `breadcrumb` → `#breadcrumb`.
- `BreadcrumbList`: ≥ 2 `ListItem`, tên theo ngôn ngữ trang, khớp breadcrumb hiển thị; rich result **chỉ hiện trên desktop** từ 01/2025 nhưng vẫn nên xuất.
- `BlogPosting`: `author` là Organization “Mr Tu” nếu không có tác giả thật; ngày ISO 8601 có múi giờ `+07:00`; không bịa persona.
- **`AutoRental`** (không có kiểu `MotorcycleRental`; subtype gần nhất của `AutomotiveBusiness`) với `makesOffer` → `Offer` → `UnitPriceSpecification` (`unitCode` `DAY`/`WEE`/`MON` **[unverified]**), **chỉ khi** địa chỉ, điện thoại, giờ và giá đã xác minh; địa chỉ không ghi quận (§3.11); bỏ `postalCode` tới khi xác minh.
- **Không** `Product`/`Offer` kiểu `InStock`, không `Car`/`Vehicle` (vehicle listing đã gỡ khỏi Search 2025); **không** `review`/`aggregateRating` tự phục vụ (quy định fake/undisclosed incentivized reviews thêm ngày 24/07/2026). Widget đánh giá Google hiển thị cho người dùng được, nhưng không markup.
- **FAQ rich result đã chấm dứt**: chỉ còn gov/health từ 08/2023, ngừng từ 07/05/2026, tài liệu gỡ 06/2026. FAQ hiển thị vẫn tốt cho người dùng; `FAQPage` JSON-LD không còn giá trị với Google. HowTo cũng đã ngừng (09/2023).
- Mã hóa: `wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )`; nếu thêm `JSON_UNESCAPED_SLASHES` thì bắt buộc giữ `JSON_HEX_TAG` để chặn `</script>`.
- Block Breadcrumbs của core (7.0) hard-code `home_url('/')` và `__('Home')`; nếu dùng phải sửa qua `block_core_breadcrumbs_items`.

### 3.4 Sitemap

**Chuẩn Google**

- Mỗi file ≤ 50 MB (chưa nén) hoặc ≤ 50.000 URL; index ≤ 50.000 sitemap; UTF-8, URL tuyệt đối, escape entity. Sitemap con phải cùng thư mục hoặc thấp hơn index ⇒ đặt mọi file ở root.
- `<lastmod>` chỉ đổi khi có thay đổi **đáng kể** (nội dung chính, structured data, link); dùng `post_modified_gmt` của **chính bản dịch** **[inference]**. Google bỏ qua `<priority>`/`<changefreq>`; endpoint ping đã chết — khai báo qua dòng `Sitemap:` trong robots.txt và Search Console.
- Chỉ liệt kê URL canonical, indexable (không draft, noindex, redirect, 404, bản dịch rỗng).
- hreflang trong sitemap: khai báo `xmlns:xhtml`, mỗi `<url>` liệt kê **mọi** alternate gồm chính nó; các `xhtml:link` con không tính vào giới hạn URL.

**Core**

- Renderer core chỉ cho phép `loc/lastmod/changefreq/priority` → **không thể** chèn `xhtml:link` vào sitemap core; hreflang của core-mode phải nằm trong `<head>`.
- Tắt sitemap core bằng `add_filter( 'wp_sitemaps_enabled', '__return_false' )` **trước `init`** (lúc nạp plugin hoặc `plugins_loaded`); rewrite rule vẫn được đăng ký và trả 404 (7.1.1 gửi header no-cache). `/sitemap.xml` cũng ánh xạ về index core khi WordPress ở root (`class-wp-rewrite.php` dòng 1291). 7.1 thêm `is_sitemap()`, `handle_404()` bỏ qua sitemap.
- Đăng ký rule riêng bằng `add_rewrite_rule( …, 'top' )`; `flush_rewrite_rules()` chỉ khi kích hoạt/hủy kích hoạt/đổi cài đặt, **không bao giờ trên `init`**.

**Yoast (production)**

- Khi bật sitemap Yoast: Yoast đặt `wp_sitemaps_enabled` = false và 301 `/wp-sitemap*.xml` → `/sitemap_index.xml`; `/sitemap.xml` 404 cũng chuyển về đó.
- Rule `top` của Yoast (`inc/sitemaps/class-sitemaps-router.php` dòng 40–41): `sitemap_index\.xml$`, `([^/]+?)-sitemap([0-9]+)?\.xml$`, `([a-z]+)?-?sitemap\.xsl$`. **Đã kiểm chứng bằng `preg_match` (25/10/2026):** `mrtu-sitemap.xml` **va chạm**; `mrtu-sitemap-index.xml`, `mrtu-sitemap-vi.xml`, `mrtu-sitemap-en.xml`, `sitemap-index.xml`, `sitemap-vi.xml` an toàn.
- Mở rộng sitemap Yoast nếu cần: `wpseo_sitemap_urlset` (thêm `xmlns:xhtml`) + `wpseo_sitemap_url` (chèn `xhtml:link`); loại URL: `wpseo_sitemap_entry`, `wpseo_exclude_from_sitemap_by_post_ids`; nối sitemap riêng: `wpseo_sitemap_index`.

**robots.txt**

- Phục vụ qua rule `robots\.txt$` → `do_robots()`; chỉnh bằng filter `robots_txt`. **File `robots.txt` vật lý ở web root sẽ vượt qua WordPress hoàn toàn** (Hostinger đôi khi tạo file này). Production hiện là khối `# START YOAST BLOCK`; chỉ thêm dòng `Sitemap:` qua filter, không ghi đè.

### 3.5 Tương thích Yoast

**Yoast làm gì với `<head>`** (`src/integrations/front-end-integration.php`)

- `wp_head` → `call_wpseo_head` priority 1 → `wpseo_head` → `present_head` (−9999); filter `pre_get_document_title`/`wp_title` ở priority 15.
- **Gỡ** `rel_canonical`, `index_rel_link`, `start_post_rel_link`, `adjacent_posts_rel_link_wp_head`, `noindex`, `_wp_render_title_tag`, `_block_template_render_title_tag`, `gutenberg_render_title_tag`.
- Robots: khi `wp_robots` có hook, Yoast bỏ presenter riêng và thêm directive qua `wp_robots` ở `PHP_INT_MAX - 10`; có `wpseo_robots`, `wpseo_robots_array`.
- **Yoast free không xuất hreflang ở bất kỳ đâu** → plugin phải tự xuất trong cả hai chế độ.
- Canonical lấy từ **indexable** (`wp_yoast_indexable.permalink`, chụp lúc index qua `get_permalink()`); home là `home_url('/')` không có dấu `/` cuối. `wpseo_dynamic_permalinks_enabled` = true buộc Yoast tính permalink lúc chạy.
- `og:locale` = `wpseo_locale( get_locale() )` → `wpseo_og_locale`; schema `inLanguage` = `wpseo_schema_piece_language( get_bloginfo('language') )` ⇒ **cả hai tự đúng theo filter `locale` của ta**.

**Hợp đồng chế độ tương thích** (phát hiện bằng `defined( 'WPSEO_VERSION' )`)

1. Plugin **không** in title, description, canonical, robots, OG/Twitter, JSON-LD; chỉ cấp dữ liệu qua `wpseo_title`, `wpseo_metadesc`, `wpseo_opengraph_title|desc|url`, `wpseo_twitter_*`, `wpseo_canonical`, `wpseo_robots_array`, `wpseo_schema_webpage`/`wpseo_schema_organization`/`wpseo_schema_graph`, `wpseo_breadcrumb_indexables`.
2. Thêm hreflang `<link rel="alternate">` và `og:locale:alternate` qua `wpseo_frontend_presenters` (mẫu của Polylang) hoặc `wp_head`.
3. Bật `wpseo_dynamic_permalinks_enabled`; link filters của ta phải chạy cả trong admin, cron, CLI để indexable lưu đúng URL. Không dựa vào cột `permalink` của indexable cho URL ngôn ngữ.
4. Canonical home từng ngôn ngữ: ép `trailingslashit` (`/en/`) qua `wpseo_canonical` + `wpseo_opengraph_url`.
5. Cấm: tắt/cấu hình lại Yoast, ghi `wpseo_*` options hay bảng `wp_yoast_*`, in `<title>`/canonical/JSON-LD thứ hai, đua priority trên `pre_get_document_title`. Có thể tắt schema Yoast bằng `wpseo_json_ld_output` → false (Yoast hiển thị “disabled by external code”) — chỉ dùng khi chủ site chọn standalone.

**Hiện trạng production cần lưu ý**

- Yoast SEO 28.7 + Yoast SEO Premium 20.12: hai số phiên bản lệch nhiều → chủ site kiểm tra trong wp-admin.
- REST đang có `yoast_head`/`yoast_head_json`; Yoast Premium có `redirects`, `ai_content_planner`.
- Có thêm nguồn ghi meta khác: `wp-agent/v1` (`seo-meta/{id}`), Yoast AI, `llms.txt` của Hostinger Tools → cần một **nguồn sự thật** rõ ràng cho SEO meta.

### 3.6 Theme (hybrid classic + `theme.json`)

- **Chế độ theme do file quyết định:** có `templates/index.html` (hoặc `block-templates/index.html`) là block theme với cả core lẫn Theme Check. Theme Mr Tu **không** có file này; partial PHP dùng `template-parts/` (hoặc `parts/` theo cấu trúc repo) qua `get_template_part()`.
- **Theme Check REQUIRED (classic):** header `style.css` (Theme Name, Author, Description, Version, Requires at least, Tested up to — chỉ major, vd. `7.1`, Requires PHP, License, License URI, Text Domain; không `Update URI`); `wp_head()`, `wp_footer()`, `wp_body_open()`, `language_attributes()`, meta charset, `body_class()`, `post_class()`, `wp_link_pages()`, `title-tag`, `automatic-feed-links`; một text domain = slug (`mr-tu`); không `<script>` trong template, không CDN, không file ẩn/zip.
- **Plugin territory (cấm trong theme):** `register_post_type`, `register_taxonomy`, `register_block_type`, `add_shortcode`, `add_role`, `registerBlockType`, filter `upload_mimes`/`mime_types`/`user_contactmethods`, gỡ `rel_canonical` hay `redirect_canonical`, tùy chọn SEO, analytics, form liên hệ. ⇒ Theme chỉ trình bày; mọi logic ở plugin, đúng README “đổi theme không mất dữ liệu”.
- **`theme.json` v3** (WordPress 6.6+), `"$schema": "https://schemas.wp.org/wp/7.1/theme.json"` (không dùng `trunk`). Hoạt động trong classic theme: palette, font sizes, `fontFamilies` + `fontFace` (core tự in `@font-face` qua `wp_print_font_faces`, `wp_head` priority 50), spacing, layout → biến `--wp--preset--*`. Cài đặt trong `theme.json` **ghi đè** theme supports tương ứng; `appearance-tools`/`border`/`link-color` theme support chỉ có tác dụng khi **không** có `theme.json`. Đặt `defaultPalette: false`, `defaultFontSizes: false`, tắt custom colors/gradients.
- **Theme supports tối thiểu:** `title-tag`, `automatic-feed-links`, `post-thumbnails`, `html5` (`search-form`, `comment-form`, `comment-list`, `gallery`, `caption`, `navigation-widgets`), `responsive-embeds`, `custom-logo`, `editor-styles`, `menus`. Hai đối số `html5` `script`/`style` đã deprecated và không dùng từ 7.0 → bỏ.
- **Patterns:** file PHP trong `/patterns/` tự đăng ký cho mọi theme kể cả classic; dịch và escape bên trong; tắt kho pattern từ xa bằng `should_load_remote_block_patterns` → false.
- **Block dữ liệu (ở plugin):** `block.json` + `render.php`, `apiVersion: 3` (≤ 2 bị cảnh báo deprecated từ 6.9). Hai cách không build:
  - Block PHP-only: `"supports": { "autoRegister": true }` + render callback/file (mới ở 7.0, có trong 7.1.3); editor tự đăng ký bằng ServerSideRender và tự sinh control cho thuộc tính string/number/integer/boolean. **Rủi ro:** phía JS dùng global `window.__unstableAutoRegisterBlocks` → API nội bộ có thể đổi.
  - `editor.js` viết tay dùng `wp.blocks`, `wp.element.createElement`, `wp.serverSideRender` (handle `wp-server-side-render`); file `*.asset.php` là tùy chọn.
  - Block động đọc từ kho NAP/giá trung tâm ⇒ đổi giá một chỗ cập nhật mọi nơi; **không** dùng block tĩnh `save()` cho dữ liệu kinh doanh.
- **Editor 7.1.3 luôn iframe** (`shouldIframe: true`): CSS editor nạp qua `add_editor_style()`, `editorStyle`/`style` trong `block.json` hoặc `enqueue_block_assets`; **không** qua `admin_enqueue_scripts`.
- REST `/wp/v2/block-renderer/<name>` không có prefix ngôn ngữ → plugin đặt locale theo ngôn ngữ của `post_id` trên route này.
- 7.1.2: `locate_template()` từ chối tên có `..` trỏ ra ngoài theme.

### 3.7 Hiệu năng

- **Core Web Vitals (p75, tách mobile/desktop):** LCP ≤ 2,5 s · INP ≤ 200 ms · CLS ≤ 0,1. **Lighthouse 13.5 (mobile):** TBT 30 %, LCP 25 %, CLS 25 %, FCP 10 %, SI 10 %, INP 0 %; giả lập Moto G Power 412×823, DPR 1,75, slow 4G (150 ms RTT, 1,6 Mbps), CPU ×4.
- **Không nhân đôi việc core 7.1 đã làm:**
  - Speculation rules (prefetch, conservative, chỉ khách chưa đăng nhập, pretty permalinks). Muốn đổi thì dùng `wp_speculation_rules_configuration`; thêm loại trừ (`/wp-json/*`, endpoint chatbot, URL đổi ngôn ngữ có side effect) qua `wp_speculation_rules_href_exclude_paths`. Safari 26.2 chỉ prefetch sau flag.
  - `fetchpriority="high"` cho ảnh lớn đầu tiên (≥ 50.000 px²) **trong main loop** hoặc context `template_part_header`/`get_header_image_tag`; bỏ lazy cho 3 media đầu.
- **Ảnh hero của classic theme** (ngoài loop) không được core xử lý → truyền tường minh `'fetchpriority' => 'high', 'loading' => 'eager'` + `width`/`height` cho `wp_get_attachment_image()`, hoặc đăng ký context qua `wp_loading_optimization_force_header_contexts`. **Đúng một** ảnh `high`. Ảnh dưới màn hình đầu `loading="lazy"` (core thêm `sizes="auto"`); `sizes` chính xác phải tự viết vì tính năng tính `sizes` tự động chỉ có cho block theme.
- Bật `should_load_separate_core_block_assets` (classic mặc định false → nạp toàn bộ CSS block-library).
- **Font:** Inter variable subset 60 KB WOFF2 (Latin + Latin-Ext + tiếng Việt, wght 400–800) cho cả 5 ngôn ngữ, khai báo trong `theme.json` `fontFace` với `unicodeRange`; preload tối đa một file. `font-display`: notes repo đề xuất `swap`; web.dev gợi ý `optional` cho body text (không CLS do font) và `swap` cho tiêu đề — chọn trong design system. Safari chặn hiển thị text vô thời hạn nếu thiếu `font-display`.
- **Không** phụ thuộc Optimization Detective (cần REST công khai + thêm JS, chồng chéo LiteSpeed), không đóng gói Performance Lab; chọn **một** trong Modern Image Formats hoặc WebP của LiteSpeed.
- **iOS Safari:** `viewport-fit=cover` + `env(safe-area-inset-*)` cho thanh cố định; `min-height: 100svh` cho hero, `dvh` chỉ cho overlay toàn màn hình; Safari **không** hỗ trợ `interactive-widget` → bàn phím chatbot xử lý bằng `window.visualViewport`; không `user-scalable=no`/`maximum-scale`; ô nhập ≥ 16px **[unverified]**.
- View transitions liên trang (Safari 18.2+) và scroll-driven animations (Safari 26.0, Firefox 159 ra 27/10/2026) chỉ là tăng cường: bọc `@supports` + `prefers-reduced-motion: no-preference`, không bao giờ ẩn nội dung chờ animation.
- Hostinger Reach nạp `cdn-reach.hostinger.com/js/embed.js` trên front-end → JS bên thứ ba ảnh hưởng iPhone; báo chủ site.
- Số đo lab từ `php -S` chỉ mang tính tương đối (không LiteSpeed, không TLS); xác nhận lại trên staging.

### 3.8 Accessibility (WCAG 2.2 AA)

- **2.4.11 Focus Not Obscured:** header/footer dính, banner, cửa sổ chat không được che **toàn bộ** phần tử đang focus → `scroll-padding-top/bottom` bằng chiều cao thanh dính (+ safe area); chat panel đóng được bằng Esc.
- **2.5.8 Target Size:** tối thiểu 24×24 CSS px (có ngoại lệ spacing/inline/equivalent); mục tiêu dự án 44×44 cho CTA gọi điện, Zalo/WhatsApp, language switcher.
- **3.3.7 Redundant Entry:** form đặt xe/liên hệ và hand-off từ chatbot không bắt nhập lại dữ liệu đã cung cấp.
- **3.2.6 Consistent Help:** điện thoại, Zalo/WhatsApp, link FAQ, nút chatbot giữ **cùng thứ tự tương đối** trên mọi trang, mọi ngôn ngữ.
- 3.3.8 Accessible Authentication: không chặn paste/password manager. Skip link, focus nhìn thấy được là yêu cầu tối thiểu của Theme Review.
- **Mẫu APG:** điều hướng dùng **disclosure** (`<button aria-expanded aria-controls>` + danh sách, `aria-current="page"`, Esc đóng), không `menubar`. Language switcher dùng link thật có `hreflang` và `lang`. FAQ dùng `<details>/<summary>` (accordion loại trừ `name` có ở Safari 17.2+). Dialog modal: `<dialog>.showModal()` (inert nền, Esc); `closedby="any"` chưa có trên Safari. Chat panel không modal: không bẫy focus nhưng phải thỏa 2.4.11.
- **Tương phản:** công thức luminance WCAG; text 4,5:1, chữ lớn 3:1, thành phần UI/focus 3:1. Ví dụ trên nền trắng: `#767676` 4,54 (đạt), `#0066cc` 5,57 (đạt), `#e65100` 3,79 (trượt cho body). Kiểm tra cả palette sáng và tối trong test token.

### 3.9 Bảo mật

- **Escape muộn, đúng ngữ cảnh** (`esc_html`, `esc_attr`, `esc_url`, `esc_js`, `esc_textarea`, `esc_xml`/`ent2ncr` cho sitemap, `wp_kses_post`); biến không escape tại chỗ phải đặt tên `$x_escaped`/`$x_safe`.
- **Nonce là chống CSRF, không phải phân quyền** → luôn đi kèm `current_user_can()` (`manage_options` cho cài đặt; `edit_post` cho từng bản dịch). `wp_nonce_field` + `check_admin_referer`, `check_ajax_referer`, `X-WP-Nonce` (action `wp_rest`).
- **LiteSpeed:** không nhúng nonce vào HTML công khai được cache cho khách chưa đăng nhập **[inference]**; endpoint REST công khai của chatbot không dựa vào nonce mà giới hạn tần suất.
- **REST:** namespace `mr-tu/v1`, `permission_callback` **bắt buộc** (thiếu → `_doing_it_wrong` từ 5.5); route công khai ghi rõ `'__return_true'`; validate/sanitize qua `args`; trả `WP_REST_Response`/`WP_Error`, không `wp_send_json()`.
- **Settings API:** `register_setting()` với `sanitize_callback` (Plugin Check `setting_sanitization`). UI iPhone tùy biến dùng `admin-post.php` hoặc REST với nonce + capability riêng.
- **DB:** `$wpdb->prepare()` cho mọi truy vấn tự viết; không SQL đặc thù MySQL không có guard.
- **Đóng gói:** `defined( 'ABSPATH' ) || exit;` trong mọi file PHP; không ghi file vào thư mục plugin; không tài nguyên CDN; không thư mục `.claude/`, `.cursor/`…; markdown gốc chỉ README/CHANGELOG/LICENSE…; có `composer.json` nếu có `vendor/autoload.php`; không đóng gói drop-in SQLite.
- **Uninstall:** `uninstall.php` với guard `WP_UNINSTALL_PLUGIN`; **không xóa nội dung/bản dịch**; chỉ xóa options/transients khi bật “xóa dữ liệu khi gỡ”.
- **Production (cần chủ site xử lý):** ba plugin điều khiển từ xa quyền cao — `wpvibe/v1` (đọc/ghi/xóa file, chạy CLI, tạo theme), `wp-agent/v1` (ghi SEO meta, custom CSS), `cowboy-mcp/v1` (MCP endpoint, OAuth tự đăng ký client) — và `dfg/v1` chưa rõ nguồn gốc. Author sitemap và feed lộ username `admin`. Bài thử nghiệm “Giới thiệu Claude” đang index.

### 3.10 Công cụ QA

Toàn bộ công cụ nằm **ngoài** repo theme tại `/home/user/qa-tools` (Plugin Check symlink vào WordPress cục bộ). Lệnh chính xác ở §6. Bài học khi dựng:

- Wrapper `/usr/local/bin/wp` đặt cờ trước lệnh nên Plugin Check **không** kích hoạt runtime checks (không lỗi, chỉ im lặng thiếu) → gọi trực tiếp phar với `plugin check` đứng đầu; plugin được kiểm phải đang active.
- PHPCompatibility 9.3.5 không bắt `?->`, `match`, `enum` khi target 7.4 → chạy thêm 10.0.0-alpha2.
- Composer tải dist từ `api.github.com` bị timeout → `--prefer-source`; cần `COMPOSER_ALLOW_SUPERUSER=1` khi chạy root.
- PHPUnit dùng bản WordPress + DB SQLite riêng (`/home/user/qa-tools/wp-tests/db/tests.sqlite`), không đụng DB dev.

### 3.11 Dữ kiện nội dung & kinh doanh (ảnh hưởng schema và copy)

- **Địa chỉ sau cải cách 01/07/2025:** Hà Nội không còn cấp quận; phường Phúc Tân cũ nằm trọn trong **Phường Hồng Hà**; “Hoàn Kiếm” nay là tên **phường** lõi Phố Cổ. Định dạng: `17 P. Phúc Tân, P. Hồng Hà, Hà Nội`, không ghi “quận Hoàn Kiếm”; copy nói “gần Phố Cổ”, không nói “ở Hoàn Kiếm”. `postalCode` “100000” trên Maps là mã cũ — bỏ khỏi schema tới khi xác minh **[unverified]**.
- **Luật 36/2024/QH15 (hiệu lực 01/01/2025):** A1 ≤ 125 cm³ hoặc ≤ 11 kW, A lớn hơn; người nước ngoài cần IDP theo **Công ước Viên 1968** **kèm** bằng quốc gia còn hạn, mang cả hai (Điều 57); 16+ với xe gắn máy, 18+ với A1/A (Điều 59); mũ bảo hiểm bắt buộc cho người lái và người ngồi sau (Điều 33). Chưa xác minh: ngày Việt Nam gia nhập 1968, định nghĩa chính xác xe gắn máy, mức phạt (Nghị định 168/2024) — **không ghi mức phạt, không viết “không cần bằng lái”**. Bản nháp copy an toàn có trong `standards.md` §7.1.
- **Cọc:** UK FCDO khuyên **không dùng hộ chiếu làm cọc** thuê xe; site tham chiếu cũng nêu không giữ CCCD → chính sách cọc của Mr Tu nên dùng tiền mặt/hình thức khác.
- **Mâu thuẫn trên site tham chiếu cần Mr Tu tự thống nhất trước khi viết:** giá tháng gồm bảo dưỡng hay không (trang Vision vs trang thuê tháng); nhiên liệu (“ghi mức xăng” vs “trả đầy bình”); tuổi/bằng (bài cũ 18+ và bằng nước ngoài dịch công chứng vs FAQ 16+ và IDP + bằng quốc gia); Sirius giá tháng vs “liên hệ”; dòng giá Vespa/SH cũ không hợp lý. **Không có chính sách đi tỉnh** → Mr Tu phải tự đặt.
- README §2.1 khớp 100 % với bảng giá trang chủ site tham chiếu (đối chiếu 25/10/2026) — vẫn chỉ là giá **tham khảo**, chưa phải giá Mr Tu.
- Nên học cách gắn nhãn trung thực “Ảnh AI minh họa, không phải ảnh chụp cửa hàng”; tránh: tên tác giả là email, slug > 100 ký tự, dùng sản phẩm WooCommerce làm bảng giá.

---

## 4. Quyết định kiến trúc chính (ADR ngắn)

Trạng thái: **✅ Xác nhận** · **🟡 Xác nhận có điều kiện** (bắt buộc kèm ràng buộc) · **🔴 Cần điều chỉnh**.

### ADR-A — Nhận diện ngôn ngữ bằng prefix URL, cắt `REQUEST_URI` trong lúc parse request — 🟡

- **Quyết định:** không nhân bản rewrite rule theo ngôn ngữ; cắt `/{prefix}` khỏi `REQUEST_URI` (và `PATH_INFO`) trong `do_parse_request`, khôi phục ở `parse_request`; bật/tắt ngôn ngữ không cần flush rewrite.
- **Lý do:** `parse_request` đọc URI đúng một lần ngay sau `do_parse_request` (§3.1); **[probe]** xác nhận page, phân trang, `get_pagenum_link` hoạt động. Ngôn ngữ không nằm trong rewrite rule nên đổi danh sách ngôn ngữ chỉ là đổi state plugin. Phương án Polylang (viết lại rule mọi permastruct, dịch `$matches[n]`) và phương án “bộ route `add_rewrite_rule` tường minh” trong notes repo đều bị loại vì cần flush và nhân đôi rule.
- **Ràng buộc bắt buộc (điều chỉnh so với contract §2.5):**
  1. Priority: `do_parse_request` → `PHP_INT_MAX` (contract ghi 1) và chỉ chạy khi `$bool` true; `parse_request` → `PHP_INT_MIN` (contract ghi 0) + khôi phục dự phòng trên `wp`.
  2. Bổ sung danh sách không cắt: `wp-sitemap*`, `robots.txt`, `favicon.ico`.
  3. `pre_get_posts` + `tax_query` **không** áp dụng cho truy vấn singular (contract hiện liệt kê “slug-based singular queries”) → bắt buộc phân giải trong filter `request` và kiểm tra ngôn ngữ queried object trên `wp`/`template_redirect` trước `redirect_canonical`.
  4. Không thêm query var ngôn ngữ (taxonomy để `query_var=false` như contract).
  5. Ngôn ngữ đã tắt → ép `error=404`, không để rơi vào rule attachment.
  6. Chặn đoán URL 404 và old-slug xuyên ngôn ngữ (§3.1).
- **Test bắt buộc:** `go_to('/en/…')` cho home, page, post, category, tag, search, `page/N`, draft, 404; `REQUEST_URI` sau `parse_request` bằng bản gốc; REST `/wp-json/` không đổi.

### ADR-B — Ngôn ngữ trong taxonomy riêng `mrtu_language`, nhóm dịch trong `mrtu_translations` (description = JSON map) — ✅

- **Quyết định:** một term ngôn ngữ trên mỗi bài; một term nhóm cho mỗi translation group, description chứa JSON `{"vi":12,"en":34}`; tự viết, chỉ học kiến trúc Polylang.
- **Lý do:** tận dụng taxonomy core + object cache; `tax_query` theo ngôn ngữ rẻ cho danh sách/archive (**[probe 7b]**); JSON đọc được, không serialize PHP.
- **Ràng buộc:** map trong description là cache dựng lại được từ relationships (contract đã có `rebuild_group()`); **cấm tra ngược bằng `LIKE`**; taxonomy `public=false`, `rewrite=false`, `query_var=false`; seeder gán bằng `wp_set_object_terms()`; filter slug đọc ngôn ngữ từ request cho bài mới; cache map trong object cache để không N+1.

### ADR-C — Front page theo ngôn ngữ bằng filter `page_on_front`/`page_for_posts` ở frontend + sửa `page_link` cho home mặc định — 🟡 (rủi ro cao)

- **Quyết định:** trên frontend, filter option về bản dịch của ngôn ngữ hiện hành; `page_link` trả `/` hoặc `/{prefix}/` cho mọi trang thuộc nhóm của `page_on_front` gốc.
- **Ưu điểm đã kiểm chứng:** với URI đã cắt, `/en/` là query rỗng → `parse_query` gán `page_id = page_on_front` đã filter → `is_front_page()`, `front-page.php`, `body_class` đúng (**[probe 4]**).
- **Rủi ro đã kiểm chứng:** **[probe 4]** `get_permalink(EN home)` = `/`; **[probe 4/4b]** `redirect_canonical` 301 cả `/en/` lẫn `/en/<home-slug>/` về `/` (gốc VI) do `canonical.php` dòng 264–266; trong admin/REST sẽ làm hỏng Settings → Reading và nhãn “Front Page”; Yoast và block Breadcrumbs core cũng đọc option này.
- **Ràng buộc bắt buộc:**
  1. Chỉ filter khi `! is_admin()`, không AJAX, không REST, và chỉ sau khi đã biết ngôn ngữ; giữ accessor đọc giá trị gốc.
  2. `page_link` ánh xạ **mọi** home ngôn ngữ (kể cả home VI khi đang ở `/en/`) bất kể ngôn ngữ hiện hành; filter này chạy cả admin/cron/CLI để indexable Yoast đúng.
  3. Guard `redirect_canonical`: khi đích là gốc VI nhưng request thuộc ngôn ngữ khác → **thay đích bằng home của ngôn ngữ đó** (để `/en/<home-slug>/` 301 về `/en/`, còn `/en/` không redirect), thay vì chỉ trả `false` như contract (trả `false` để lại URL trùng `/en/<home-slug>/` với HTTP 200).
  4. Áp dụng tương tự cho `page_for_posts`.
- **Phương án dự phòng (an toàn hơn theo core-internals):** không filter option; trong filter `request` ánh xạ path rỗng của `/en/` thành `page_id = EN home` (`is_page`, `redirect_canonical` không can thiệp) và theme dùng `mrtu_is_language_home()`. Chuyển sang phương án này nếu test ở trên không đạt.

### ADR-D — Chế độ tương thích Yoast (mặc định khi Yoast active) vs chế độ standalone — ✅

- **Quyết định:** compat: plugin chỉ xuất hreflang (+ `og:locale:alternate`) và cấp title/description/noindex đã seed cho Yoast **chỉ khi field riêng của Yoast trên bài đó trống**; standalone: plugin xuất toàn bộ head + sitemap.
- **Lý do:** Yoast đã chạy trên production, gỡ canonical/title/noindex core và không có hreflang (§3.5). Mỗi bản dịch là một post riêng nên field Yoast vốn đã theo ngôn ngữ; ưu tiên giá trị chủ site sửa trong Yoast tránh hai nguồn ghi đè nhau. `og:locale` và `inLanguage` của Yoast tự đúng nhờ filter `locale`.
- **Ràng buộc:** `wpseo_dynamic_permalinks_enabled` = true; canonical/OG URL của home ngôn ngữ có dấu `/`; robots qua `wpseo_robots_array`; không ghi dữ liệu Yoast; Health Check xác minh đúng **một** canonical/description/robots mỗi trang; Control Center hiển thị chế độ đang chạy.
- **Điểm lệch cần chốt:** contract §2.8 “đặt WebSite schema url/name về home ngôn ngữ” mâu thuẫn chuẩn site name (chỉ có tác dụng ở `/`) → xem §4.9.

### ADR-E — Sitemap riêng với `xhtml:link` hreflang — 🔴 đổi đường dẫn, phần còn lại xác nhận

- **Quyết định gốc:** index `/mrtu-sitemap.xml` → `/mrtu-sitemap-{lang}.xml`, mỗi URL có `xhtml:link`.
- **Vấn đề:** `/mrtu-sitemap.xml` **khớp rule `top` của Yoast** `([^/]+?)-sitemap([0-9]+)?\.xml$` (kiểm chứng `preg_match` 25/10/2026); rule nào đăng ký trước sẽ thắng → hành vi phụ thuộc thứ tự nạp. File con `/mrtu-sitemap-{lang}.xml` an toàn.
- **Điều chỉnh:** đổi index thành **`/mrtu-sitemap-index.xml`** (an toàn với cả rule Yoast lẫn core); giữ `/mrtu-sitemap-{lang}.xml`.
- **Ràng buộc:** một bộ sinh sitemap cho mỗi môi trường — compat (Yoast bật sitemap) → dùng sitemap Yoast, chỉ thêm loại trừ/alternate qua filter `wpseo_sitemap_*`; standalone → sitemap của ta + tắt sitemap core qua `wp_sitemaps_enabled` trước `init`. `xhtml:link` trong sitemap và `<link>` trong head phải sinh từ **cùng một hàm** (Google: dùng nhiều phương pháp không có lợi, chỉ tăng rủi ro lệch dữ liệu). Chỉ URL canonical, indexable; `lastmod` = `post_modified_gmt` của bản dịch; dòng `Sitemap:` qua `robots_txt`; flush rewrite chỉ khi kích hoạt/đổi cài đặt.

### ADR-F — Theme hybrid classic: template PHP + `theme.json` + block dữ liệu server-rendered từ plugin — ✅

- **Lý do:** Theme Check xếp block/shortcode/CPT/canonical vào plugin territory; `theme.json` v3 cấp design token cho classic theme; block động đọc kho NAP/giá trung tâm nên không đóng băng dữ liệu vào nội dung; không cần bước build (block PHP-only `autoRegister` hoặc `editor.js` viết tay + ServerSideRender).
- **Ràng buộc:** không `templates/index.html`; `$schema` ghim `wp/7.1`; `apiVersion: 3`; CSS editor qua block assets (editor luôn iframe); `should_load_separate_core_block_assets` = true; theme gọi API `mrtu_*` sau `function_exists()`; `autoRegister` dùng API `__unstable` nên giữ đường lui `editor.js`.

### ADR-G — Locale WordPress tiếng Việt và mã ngôn ngữ công khai — 🟡

- **Quyết định:** locale WordPress của tiếng Việt là **`vi`** (đã xác nhận qua GlotPress: `wp_locale = 'vi'`); các locale khác `en_US`, `de_DE`, `fr_FR`, `es_ES`; `<html lang>` và hreflang dùng mã BCP 47 cấu hình được; `og:locale` `vi_VN`.
- **Lý do:** file core pack là `vi.mo`/`vi.l10n.php`; dùng `vi_VN` thì `switch_to_locale()` và just-in-time loading không tìm thấy gói. README §3 cần sửa `vi_VN` → `vi`.
- **Ràng buộc:** filter `locale` đăng ký lúc nạp file plugin (frontend), admin giữ user locale; cài core pack `vi`, `de_DE`, `fr_FR`, `es_ES` trên production; `<html lang>` theo mã cấu hình cần filter `language_attributes` (nếu không, core pack có thể trả chuỗi `html_lang_attribute` riêng); schema Yoast theo `wpseo_schema_piece_language`.
- **Điểm lệch cần chốt:** contract đặt hreflang mặc định VI = `vi-VN`; core-internals và standards đề xuất `vi` (chỉ mã ngôn ngữ, vì mục tiêu là ngôn ngữ chứ không phải quốc gia; `vi-VN` hợp lệ nhưng thu hẹp phạm vi khớp). Xem §4.9.

### ADR-H — Chatbot không nằm trong repo công khai (giao riêng), hợp đồng tích hợp công khai — ✅

- **Quyết định:** mã nguồn chatbot rule/retrieval không API giao riêng (thư mục git-ignored `private/`); repo công khai chỉ chứa hợp đồng tích hợp: filter `mrtu_assistant_active`, API `mrtu_business()`, `mrtu_phone()`, `mrtu_contact_channels()`, `mrtu_vehicles()`, `mrtu_price_display()`, `mrtu_pricing_meta()`, `mrtu_current_language()` (contract §2.4).
- **Lý do:** repo công khai; dữ liệu trả lời phải đến từ kho đã xác minh nên chỉ cần API đọc; site tham chiếu chứng minh mô hình chatbot tra cứu nội bộ hiệu quả (lời chào giới hạn phạm vi “thông tin từ website”, cảnh báo không nhập giấy tờ/dữ liệu riêng tư, giá cần xác nhận).
- **Ràng buộc khi xây chatbot:** endpoint REST công khai có `permission_callback` tường minh, không phụ thuộc nonce (cache LiteSpeed), có giới hạn tần suất; loại endpoint khỏi speculation rules; panel không modal, đóng bằng Esc, không che focus (2.4.11), vị trí nhất quán (3.2.6), hand-off không bắt nhập lại (3.3.7); bàn phím iPhone qua `visualViewport`; gate ZIP bằng Plugin Check như plugin chính; không dùng API trả phí (không `--ai`).

### 4.9 ⚠ Điểm cần architect chốt (nghiên cứu khác contract/README)

| # | Chủ đề | Contract / README hiện tại | Kết quả nghiên cứu | Đề xuất |
|---|---|---|---|---|
| 1 | Đường dẫn sitemap index | `/mrtu-sitemap.xml` | Va chạm rule Yoast (đã kiểm chứng) | `/mrtu-sitemap-index.xml` |
| 2 | Priority router | `do_parse_request` 1, `parse_request` 0 | Nên `PHP_INT_MAX` / `PHP_INT_MIN` + khôi phục dự phòng | Đổi theo nghiên cứu |
| 3 | Lọc ngôn ngữ singular | `pre_get_posts` tax_query cho singular theo slug | `tax_query` bị bỏ qua với singular | Phân giải trong `request` + kiểm tra trên `wp`/`template_redirect` |
| 4 | Guard `redirect_canonical` | Trả `false` khi đích khác ngôn ngữ | Cần 301 `/en/<home-slug>/` → `/en/` | Thay đích bằng home cùng ngôn ngữ |
| 5 | WebSite schema | `url`/`name` theo home từng ngôn ngữ | Site name chỉ có tác dụng ở `/`; một node `WebSite` | `url` = root, `name` “Mr Tu”, một `@id`; ngôn ngữ thể hiện ở `WebPage.inLanguage` |
| 6 | hreflang VI | `vi-VN` | `vi` khuyến nghị (cả hai hợp lệ) | Mặc định `vi`, vẫn cho cấu hình |
| 7 | `x-default` | `en` (contract; keyword-research ủng hộ: EN tại VN 2.400/tháng) | standards đề xuất URL VI (bản mặc định không prefix) | Cả hai hợp lệ theo Google; giữ `en` có số liệu nhu cầu, ghi nhận phương án VI |
| 8 | FAQPage schema | `faq_schema = true` trên `guide-faq` | Rich result FAQ đã ngừng 07/05/2026 | Giữ nếu chính xác và hiển thị, nhưng không ưu tiên/không xây tính năng quanh nó |
| 9 | Locale VI | README §3 ghi `vi_VN` | Locale WordPress là `vi` | Sửa README (contract đã đúng) |
| 10 | `html5` support | standards liệt kê cả `script`, `style` | Deprecated, không dùng từ 7.0 | Bỏ hai đối số này |

---

## 5. Rủi ro & biện pháp

| # | Rủi ro | Mức | Biện pháp | Ai xử lý |
|---|---|---|---|---|
| R1 | `REQUEST_URI` kẹt ở dạng đã cắt khi callback khác trả `false` cho `do_parse_request` → canonical/pagination/login redirect sai | Cao | Priority `PHP_INT_MAX`, chỉ chạy khi `$bool` true, khôi phục `PHP_INT_MIN` + dự phòng trên `wp`; test `REQUEST_URI` sau parse | Plugin |
| R2 | Trang sai ngôn ngữ trả 200 (vd. trang VI dưới `/en/`) → nội dung trùng, hreflang hỏng | Cao | Phân giải trong `request`; kiểm tra queried object trước `redirect_canonical`; 404/301 | Plugin |
| R3 | 301 nhảy ngôn ngữ do đoán 404 (`LIKE 'name%'`) hoặc old slug | Trung bình | `strict_redirect_guess_404_permalink`/`pre_redirect_guess_404_permalink`; `old_slug_redirect_post_id` | Plugin |
| R4 | Đổi slug Page không có 301 tự động | Trung bình | Bảng `mrtu_redirects` tự tạo khi slug/parent đổi, chống chuỗi/vòng | Plugin |
| R5 | Filter `page_on_front` → home EN trỏ `/`, 301 `/en/` → `/`, hỏng Settings → Reading | Cao | Ràng buộc ADR-C; test; phương án dự phòng `page_id` trong `request` | Plugin |
| R6 | Thêm query var ngôn ngữ phá nhận diện front page | Trung bình | Ngôn ngữ chỉ trong state plugin; `query_var=false` | Plugin |
| R7 | Text domain nạp trước khi biết ngôn ngữ → chuỗi sai ngôn ngữ; thiếu core pack → chuỗi core tiếng Anh | Trung bình | Filter `locale` lúc nạp plugin; không `__()` sớm; cài core packs | Plugin + chủ site |
| R8 | Head trùng lặp với Yoast (hai canonical/title/schema) | Cao | Compat mode; Health Check “live head audit” qua loopback | Plugin |
| R9 | Canonical Yoast lấy từ indexable cũ (URL chưa prefix) | Trung bình | `wpseo_dynamic_permalinks_enabled`; link filters chạy trong admin/cron/CLI | Plugin |
| R10 | Đường dẫn sitemap bị Yoast chiếm | Cao | Đổi sang `/mrtu-sitemap-index.xml`; một bộ sinh sitemap mỗi môi trường | Plugin |
| R11 | File `robots.txt` vật lý vượt qua WordPress | Thấp | Kiểm tra web root khi deploy; chỉ chỉnh qua filter `robots_txt` | Deploy |
| R12 | Lọc menu location trong admin bị lưu vĩnh viễn | Thấp | Chỉ filter ở frontend; hoặc location riêng theo ngôn ngữ | Plugin/theme |
| R13 | `autoRegister` dùng API `__unstable` có thể đổi | Thấp | Giữ đường lui `editor.js` + ServerSideRender; test sau mỗi bản WP | Plugin |
| R14 | Nonce trong HTML cache LiteSpeed hết hạn → form/chatbot lỗi | Trung bình | Không nhúng nonce vào HTML công khai cache; REST công khai dùng rate limit | Plugin/chatbot |
| R15 | Plugin điều khiển từ xa (`wpvibe`, `wp-agent`, `cowboy-mcp`, `dfg`) có quyền ghi file/CLI/SEO meta | Cao | Chủ site xác minh nguồn gốc, tắt khi không dùng; xác định nguồn sự thật SEO meta | Chủ site |
| R16 | Yoast Premium 20.12 lệch Yoast 28.7 | Trung bình | Chủ site kiểm tra/cập nhật trong wp-admin | Chủ site |
| R17 | Lộ username `admin` (author sitemap, feed); bài thử “Giới thiệu Claude” đang index | Trung bình | Đổi display name, noindex author; xóa/nháp bài thử; cân nhắc “ngăn công cụ tìm kiếm” tới khi có nội dung thật | Chủ site |
| R18 | `llms.txt` (Hostinger Tools) liệt kê lẫn mọi ngôn ngữ; Hostinger Reach thêm JS bên thứ ba | Thấp | Chủ site quyết định giữ/tắt | Chủ site |
| R19 | NAP chưa xác minh (tên GBP bị cắt, mô hình cửa hàng vs điểm hẹn, giờ thực tế); lẫn NAP Long Biên | Cao | `PENDING VERIFICATION` trong admin; `AutoRental` chỉ khi đủ cờ xác minh; không dùng NAP 112 Nguyễn Văn Cừ/0942 467 674 | Chủ site + plugin |
| R20 | Địa chỉ/copy theo địa giới cũ (“quận Hoàn Kiếm”) | Trung bình | `P. Hồng Hà`, không quận; “gần Phố Cổ”; bỏ `postalCode` | Nội dung |
| R21 | Nội dung bằng lái sai (YMYL), trích AI Overview của bên thứ ba | Cao | Theo Luật 36/2024 + nguồn sứ quán; ghi ngày rà soát; không “không cần bằng lái”, không mức phạt | Nội dung + rà soát |
| R22 | Đánh giá giả / review markup tự phục vụ | Cao | Không `review`/`aggregateRating`; chỉ tích lũy đánh giá GBP thật | Chủ site + plugin |
| R23 | Cannibalization với site chính / `blog.thuexemaynguyentu.com` (Phố Cổ, Hoàn Kiếm, giá) | Trung bình | Nội dung khác biệt thật; không chép; liên kết chéo do chủ site quyết định | Chủ site + nội dung |
| R24 | Nội dung dịch máy hàng loạt bị coi là scaled content abuse | Trung bình | Chỉ index bản dịch biên tập hoàn chỉnh; bản dịch thiếu không vào hreflang/sitemap | Nội dung |
| R25 | Kỳ vọng sai về rich results (FAQ đã ngừng, breadcrumb chỉ desktop, site name chỉ ở `/`) | Thấp | Ghi rõ trong tài liệu SEO; đo bằng GSC sau 8–12 tuần | SEO |
| R26 | PCP runtime checks chạy nhầm trên production (tạo bảng tạm, drop-in) | Cao | Chỉ chạy trên WordPress cục bộ `/home/user/wpenv` | QA |

---

## 6. Công cụ QA đã kiểm chứng

Các lệnh dưới đây đã chạy thành công trong container (25/10/2026). Ruleset `/home/user/qa-tools/phpcs-mrtu.xml`: WordPress-Extra, WordPress-Docs, `DB.DirectDatabaseQuery`, `DB.SlowDBQuery`, `minimum_wp_version=6.8`, prefixes `mr_tu, mrtu, MrTu`, text domain `mr-tu, mr-tu-multilang-seo`, PHPCompatibilityWP `testVersion 7.4-`, loại trừ `vendor`, `node_modules`, `build`, `tests`. Chỉ ba thiết lập WPCS nhận runtime config: `text_domain`, `prefixes`, `minimum_wp_version`.

| Công cụ | Phiên bản | Đường dẫn |
|---|---|---|
| phpcs + WPCS + PHPCompatibilityWP | phpcs 3.13.6, WPCS 3.4.1, PHPCompatibilityWP 2.1.8 (PHPCompatibility 9.3.5) | `/home/user/qa-tools/vendor/bin/phpcs` |
| PHPCompatibility 10 (alpha) | phpcs 4.0.4, PHPCompatibility 10.0.0-alpha2, PHPCompatibilityWP 3.0.0-alpha2 | `/home/user/qa-tools/phpcompat10/vendor/bin/phpcs` |
| Plugin Check | 2.1.0 (build từ source) | symlink `/home/user/wpenv/wordpress/wp-content/plugins/plugin-check` → `/home/user/research/plugin-check` |
| Theme Check | 20260901 (không cài vào WP) | `/home/user/qa-tools/bin/theme-check-mrtu` |
| WP PHPUnit trên SQLite | wp-phpunit 7.1.1, PHPUnit 9.6.38, yoast/phpunit-polyfills 4.0.0 | `/home/user/qa-tools/wp-tests` |
| Lighthouse | 13.5.0 | `/home/user/qa-tools/node_modules/.bin/lighthouse`, wrapper `bin/lighthouse-mrtu`, `lh-median.mjs` |
| fonttools | 4.67.0 (+ venv có brotli) | `/home/user/qa-tools/venv/bin/pyftsubset`, `/usr/local/bin/pyftsubset` |

**PHPCS (WPCS + PHPCompatibility)**

```sh
# WPCS + PHPCompatibilityWP 2.1.8 (testVersion 7.4-)
/home/user/qa-tools/vendor/bin/phpcs --standard=/home/user/qa-tools/phpcs-mrtu.xml --runtime-set text_domain mr-tu-multilang-seo <path>

# Cú pháp PHP 8 (PHPCompatibility 10.0.0-alpha2)
/home/user/qa-tools/phpcompat10/vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- --extensions=php -s <path>

# Chạy cả hai
TEXT_DOMAIN=mr-tu /home/user/qa-tools/bin/phpcs-mrtu /home/user/tu-theme/theme/mr-tu
```

**Plugin Check**

```sh
wp plugin list-checks                 # 37 check ổn định
wp plugin check hello.php             # chỉ static; plugin 1 file phải ghi "hello.php"

# static + runtime (plugin cần kiểm phải đang active) — đã đóng gói trong bin/plugin-check-mrtu
php -d memory_limit=1024M /home/user/wpenv/bin/wp-cli.phar plugin check <slug> \
  --require=/home/user/wpenv/wordpress/wp-content/plugins/plugin-check/cli.php \
  --allow-root --path=/home/user/wpenv/wordpress
/home/user/qa-tools/bin/plugin-check-mrtu <slug> --format=csv
```

**Theme Check**

```sh
/home/user/qa-tools/bin/theme-check-mrtu <theme-slug> [--format=json]   # exit 0 = đạt
/home/user/qa-tools/bin/theme-check-mrtu twentytwentyfive                # đối chứng: < 1 s, đạt
```

**PHPUnit tích hợp (SQLite, không Docker/MySQL)**

```sh
MRTU_TEST_PLUGINS=/abs/path/plugin.php /home/user/qa-tools/bin/wp-phpunit-mrtu -c /abs/phpunit.xml
# smoke suite: OK (2 tests, 5 assertions); nhiều plugin: phân tách đường dẫn tuyệt đối bằng dấu ':'
```

**Lighthouse (median 5 lần, mobile)**

```sh
php -S localhost:8080 -t /home/user/wpenv/wordpress      # siteurl cục bộ là http://localhost:8080; tắt sau khi đo
/home/user/qa-tools/bin/lighthouse-mrtu http://localhost:8080/ 5 /tmp/lh [--desktop]

# một lần chạy (đã chạy được, ~17 s)
CHROME_PATH=/opt/pw-browsers/chromium-1243/chrome-linux64/chrome npx lighthouse http://localhost:8080/ --quiet \
  --chrome-flags="--headless=new --no-sandbox --disable-gpu --no-proxy-server --disable-dev-shm-usage" \
  --only-categories=performance,accessibility,best-practices,seo \
  --form-factor=mobile --screenEmulation.mobile=true --throttling-method=simulate \
  --output=json --output=html --output-path=/tmp/lh/run1
```

`--no-proxy-server` là bắt buộc vì container có HTTPS proxy. Đối chứng Twenty Twenty-Five (SQLite + `php -S`): Perf 99–100, A11y 100, BP 96, SEO 90.

**Font subset**

```sh
F=/home/user/research/google-fonts/ofl/inter
fonttools varLib.instancer "$F/Inter[opsz,wght].ttf" opsz=14 wght=400:800 -o inter-wght.ttf
pyftsubset inter-wght.ttf --flavor=woff2 --output-file=inter-latin-vi-wght.woff2 \
  --unicodes="U+0000-00FF,U+0100-024F,U+0259,U+0300-0301,U+0303-0304,U+0308-0309,U+0323,U+0329,U+1E00-1EFF,U+2000-206F,U+20AB,U+20AC,U+2122,U+2190-2193,U+2212,U+2215,U+FEFF,U+FFFD" \
  --layout-features='kern,liga,calt,ccmp,locl,mark,mkmk,case,tnum'
```

**Cài đặt / lưu ý môi trường**

```sh
export COMPOSER_ALLOW_SUPERUSER=1     # bắt buộc khi composer chạy root
composer install --prefer-source      # tránh timeout dist từ api.github.com
```

**Cổng chất lượng trước mỗi ZIP** (theo thứ tự): `phpcs-mrtu` (WPCS + PHPCompat 9 & 10) → `plugin-check-mrtu` (static + runtime) → `theme-check-mrtu` → `wp-phpunit-mrtu` (SQLite) → `lighthouse-mrtu` (median 5, mobile, từng home ngôn ngữ và template chính).
**Ngân sách:** Perf ≥ 90 · A11y = 100 · Best Practices ≥ 95 · SEO = 100 · LCP < 2,5 s · CLS < 0,1 · TBT < 200 ms. Không dùng PSI API key hay LHCI server; không coi một lần chạy là kết quả.

---

## Phụ lục A — Nghiên cứu từ khóa (tham chiếu)

Chi tiết: [`docs/keyword-research.md`](keyword-research.md) · dữ liệu máy đọc: [`config/seo-plan.json`](../config/seo-plan.json) (25/10/2026).

1. VI áp đảo: “thuê xe máy hà nội” 8.100/tháng (KD 7) và “cho thuê xe máy hà nội” 1.300 cùng ý định → chung pillar `/thue-xe-may-ha-noi/`; home nhắm thương hiệu “Mr Tu”.
2. EN chủ yếu tìm từ trong Việt Nam (“motorbike rental hanoi” 2.400 VN/en vs 110 US), cao điểm tháng 9–11 → local pack, GBP và đánh giá thật quan trọng hơn thứ hạng tại Mỹ.
3. DE/FR/ES nhu cầu rất nhỏ (30/50/10) nhưng SERP gần như không có trang bản ngữ; câu hỏi bằng lái/giấy tờ có AI Overview + PAA ở cả 5 ngôn ngữ → trang `guide-faq` có nguồn chính thống là cơ hội lớn nhất.
4. GBP “Cho thuê xe máy Phố Cổ - Mr Tú” đã lên local pack với 0 đánh giá và link `thuexemayhanoi.github.io/shop/`; `blog.thuexemaynguyentu.com` đang cạnh tranh cùng truy vấn → chủ site xác minh tên, đổi link khi ra mắt, phối hợp nội dung.
5. Bẫy từ ngữ/ý định: luôn ghép “Hà Nội” với “phố cổ”, dùng “xe tay ga” thay “xe ga”, không hứa “không cọc/miễn cọc/không cần bằng lái”; meta không chứa giá, địa chỉ hay cam kết chưa xác minh.

## Phụ lục B — Notes gốc và tài nguyên

| Tài nguyên | Đường dẫn |
|---|---|
| Core internals (WordPress 7.1.3) | `/home/user/research-notes/core-internals.md` |
| Repo chuyên gia + QA tooling | `/home/user/research-notes/expert-repos.md` |
| Hiện trạng production + site tham chiếu | `/home/user/research-notes/reference-site.md` |
| Chuẩn (Google, schema.org, web.dev, WCAG, WP, Việt Nam) | `/home/user/research-notes/standards.md` |
| Nghiên cứu từ khóa | `/home/user/tu-theme/docs/keyword-research.md` |
| Probe script router | `/tmp/claude-0/-home-user-tu-theme/fd9e4957-12cc-5182-a5be-65ad9274a818/scratchpad/router-probe.php` |
| Clone repo | `/home/user/research/` |
| Công cụ QA | `/home/user/qa-tools/` |
| WordPress cục bộ (7.1.3, SQLite) | `/home/user/wpenv/wordpress` |
