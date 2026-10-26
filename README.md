# Mr Tu — WordPress Theme & Multilingual SEO (VI · EN · DE · FR · ES)

> **PROJECT BRIEF / SOURCE OF TRUTH — 25/10/2026**  
> Repository: `thuexemayhanoi/tu-theme`  
> **Website duy nhất:** https://hanoi.thuexemaynguyentu.com/  
> **Thương hiệu hiển thị:** **Mr Tu**  
> **Nền tảng:** một WordPress độc lập trên Hostinger, một cơ sở dữ liệu WordPress riêng.  
> **Mục tiêu:** phát triển website cho dịch vụ thuê xe máy Hà Nội, tập trung khu vực Phố Cổ, với SEO thực chất bằng **5 ngôn ngữ trên cùng một tên miền**.  
> **Tình trạng (26/10/2026):** theme `mr-tu` 1.0.0 và plugin `mr-tu-multilang-seo` 1.0.0 đã xây xong, qua QA và đóng gói
> (`dist/mr-tu-theme.zip`, `dist/mr-tu-multilang-seo.zip` — artifact `mr-tu-zips` của GitHub Actions), **chưa cài lên
> production**. Cài đặt: `docs/deployment-hostinger.md` · quay lui: `docs/rollback.md` · vận hành: `docs/admin-guide-vi.md` ·
> kết quả QA: `docs/qa-report.md`. Phần còn lại của README là đặc tả gốc của dự án (nguồn yêu cầu), không phải mô tả tính năng.

## 0. Các nguyên tắc bất biến

1. **CHỈ MỘT DOMAIN:** `hanoi.thuexemaynguyentu.com`. Không lập thêm 5 domain, 5 subdomain ngôn ngữ, 5 WordPress hay WordPress Multisite.
2. **5 NGÔN NGỮ:** Tiếng Việt (VI), tiếng Anh (EN), tiếng Đức (DE), tiếng Pháp (FR), tiếng Tây Ban Nha (ES); không tạo phiên bản Nga/Bồ Đào Nha.
3. **KHÔNG PLUGIN BÊN THỨ BA:** xây theme riêng và **plugin multilingual + SEO riêng** sử dụng WordPress Core APIs. Không lệ thuộc WPML, Polylang, TranslatePress, Yoast, Rank Math, plugin page builder hay dịch vụ dịch máy/API bên ngoài. Nếu môi trường có plugin hệ thống/Hostinger mặc định, phải kiểm tra tương thích, không tự xóa hay vô hiệu hóa.
4. **NAP MR TU RIÊNG BIỆT:** không kế thừa tên, địa chỉ, điện thoại, giờ mở cửa, Google Maps của cửa hàng Nguyễn Tú Long Biên. Giá/dòng xe/dịch vụ có thể *tham khảo* website chính, nhưng phải được xác nhận và quản lý độc lập cho điểm Mr Tu.
5. **KHÔNG TỰ ĐỘNG TRIỂN KHAI:** mọi code cần review, QA, backup và rollback trước khi cài vào WordPress. Không tự động ghi đè hosting, dữ liệu hay theme của site khác.
6. **KHÔNG THỔI PHỒNG LOCAL SEO:** chỉ mô tả điểm đến/nhận xe, giờ mở cửa, biển hiệu và nhân viên khi đúng thực tế. Địa điểm chỉ nhờ treo biển nhưng không có nhân viên tiếp khách thường trực không được mô tả như một cửa hàng đón khách tại chỗ. Không tạo Google Business Profile giả, doorway pages hoặc review giả.
7. **KHÔNG SAO CHÉP NGUYÊN BÀI:** giữ chủ đề và chính sách tham khảo giống website chính, nhưng biên tập bản sắc Mr Tu, ngôn ngữ tự nhiên, địa phương hóa và nội dung hữu ích riêng; tránh trùng lặp/cannibalization không cần thiết.

## 1. Hồ sơ thương hiệu và NAP — Mr Tu (điểm gần Phố Cổ)

### 1.1. NAP cung cấp từ ảnh Google Maps ngày 25/10/2026

| Trường | Giá trị cho website mới | Trạng thái |
| --- | --- | --- |
| Tên brand ngắn | **Mr Tu** | Đã chọn |
| Tên listing hiển thị | `Cho thuê xe máy Phố Cổ - Mr …` (ảnh bị cắt mất phần cuối) | **Cần đọc tên đầy đủ trên Google Business Profile trước khi đồng bộ** |
| Tên cửa hàng đề xuất trong nội dung | **Cho thuê xe máy Phố Cổ - Mr Tu** | Dự kiến; không tự sửa tên GBP |
| Địa chỉ | **17 P. Phúc Tân, Hồng Hà, Hà Nội 100000, Việt Nam** | Lấy từ ảnh Maps; xác minh biểu diễn địa chỉ hiện hành trước khi publish schema |
| Điện thoại | **0816 659 199** | Lấy từ ảnh Maps |
| Điện thoại dạng `tel:` | `tel:+84816659199` | Chuẩn hóa từ số trong nước |
| Giờ Google Maps | **08:00–17:00 tất cả các ngày trong tuần** | Lấy từ ảnh Maps; cần xác nhận đúng giờ có nhân sự/dịch vụ thực tế |
| Khu vực phục vụ | **Phố Cổ, Hoàn Kiếm và khu vực Hà Nội theo khả năng giao/nhận thực tế** | Phạm vi cụ thể cần xác nhận |
| Website mới | **https://hanoi.thuexemaynguyentu.com/** | Domain duy nhất |
| Google Maps / Place ID / CID | **Chưa cung cấp URL/ID chính xác** | Để trống đến khi chủ site xác nhận; không đoán link |
| Email công khai | **Chưa xác nhận** | Không lấy email admin WordPress làm email bán hàng nếu chưa được phép |

**Phân biệt quan trọng:** NAP của website chính `thuexemaynguyentu.com` thuộc một địa điểm khác (Long Biên) và **KHÔNG** được đưa vào header/footer/contact/schema của Mr Tu. Không ghi lại số điện thoại hay địa chỉ của địa điểm đó trong bất kỳ tài liệu hay tệp nào của dự án này.

**Điều kiện trước khi public LocalBusiness schema hoặc đặt nút “Đến cửa hàng”:** xác minh thực tế địa điểm 17 Phúc Tân có tiếp khách, giao/nhận xe, nhân sự và biển hiệu đúng như khai báo. Nếu đây chỉ là điểm hẹn/giao nhận hoặc treo biển, phải dùng mô tả đúng mô hình hoạt động; không tạo chỉ đường “đến cửa hàng” gây hiểu lầm. Thông tin NAP đang là dữ liệu do chủ site cung cấp qua ảnh, chưa thay thế việc kiểm tra thực địa.

### 1.2. Business facts là nguồn dữ liệu duy nhất

Thiết kế một kho cấu hình tập trung, ví dụ:

- `config/mr-tu-business.json` trong repo làm **tài liệu nguồn** (hoặc cấu hình quản trị plugin WordPress tương đương; không đẩy bí mật vào Git).
- `brand`, `legal_display_name` (sau xác minh), `address`, `phone_display`, `phone_e164`, `hours`, `service_area`, `maps_url`, `contact_channels`, `prices`, `models`, `last_verified_at`.
- Tất cả header, footer, trang Liên hệ, CTA, schema và các phiên bản ngôn ngữ đọc từ **một nơi**, nhưng văn bản xung quanh dịch đúng ngôn ngữ.
- Có dashboard **Business Info** tiếng Việt dễ sửa trên iPhone; validation giá/điện thoại/giờ, quyền `manage_options`, nonce và sanitization. Thay đổi 1 nơi cập nhật toàn site, có audit log hoặc revisions.

## 2. Sản phẩm, dịch vụ và bảng giá tham chiếu

Các mảng dịch vụ tương tự website Nguyễn Tú: **cho thuê xe máy Hà Nội** theo ngày/tuần/tháng, xe số, xe ga, xe 50cc (chỉ khi có xe), phục vụ nhu cầu đi làm, sinh sống tại Hà Nội, khách Việt và khách quốc tế, nhận/trả xe và giao nhận nội đô theo điều kiện thực tế.

### 2.1. Bảng giá THAM KHẢO — KHÔNG MẶC ĐỊNH LÀ GIÁ ĐÃ DUYỆT CỦA MR TU

Nguồn tham khảo ưu tiên: bảng giá công khai trên **trang chủ** https://thuexemaynguyentu.com/ (đối chiếu 25/10/2026). Một số bài blog cũ có giá khác; **không tự lấy con số trong bài cũ để ghi đè bảng giá chính**.

| Dòng xe | Giá ngày | Giá tuần | Giá tháng | Cọc tham khảo |
| --- | ---: | ---: | ---: | ---: |
| Honda Wave | 150.000đ | 700.000đ | 900.000–1.200.000đ | 2–3 triệu |
| Honda Vision | 200.000đ | 1.000.000đ | 1.800.000–2.000.000đ | 4–5 triệu |
| Honda Air Blade | 200.000đ | 1.000.000đ | 1.500.000đ | 3 triệu |
| Honda Click / Yamaha Mio | 150.000đ | 700.000đ | 900.000–1.200.000đ | 2 triệu |
| Xe tay ga 50cc | 200.000đ | Xác nhận riêng | Xác nhận riêng | 4 triệu |
| Xe máy điện / xe đạp điện | Liên hệ | Liên hệ | Liên hệ | Xác nhận theo mẫu |

**Nguyên tắc giá:**
- Đây là **giá tham khảo từ địa điểm chính**, không là cam kết của Mr Tu. Chỉ xuất bản khi chủ site xác nhận giá thực tế và tình trạng xe tại điểm Mr Tu.
- Tiền cọc tách khỏi tiền thuê; minh bạch điều kiện đặt cọc/hoàn cọc/giấy tờ hợp lệ.
- Phí giao/nhận, phụ thu ngoài giờ, phạm vi hỗ trợ, thuê dài ngày và chính sách đi ngoại tỉnh **phải xác nhận riêng**, không tự sao chép con số/điều kiện nếu khác địa điểm.
- Giá hiển thị ở VND thống nhất giữa 5 ngôn ngữ (có thể chú thích chuyển đổi ngoại tệ nhưng không cố định tỷ giá).
- Dữ liệu giá lưu tập trung theo `model_id` + kỳ thuê + `valid_from`/`reviewed_at`, đổi một chỗ cập nhật toàn site; tránh giá trong bài viết mâu thuẫn. Bài viết có thể dẫn về bảng giá chuẩn.
- Chỉ liệt kê dòng xe thực tế có phục vụ. Không tự khẳng định xe còn sẵn tại điểm Phố Cổ, đặc biệt 50cc/xe điện.

### 2.2. Nhóm dòng xe và chủ đề

- **Xe số:** Honda Wave, Yamaha Sirius (xác minh từng mẫu thực tế).
- **Xe tay ga:** Honda Vision, Honda Air Blade, Honda Click, Yamaha Mio (xác minh).
- **Xe 50cc:** chỉ hiển thị chi tiết xe/giá khi có xe thực.
- **Xe điện:** nội dung hướng dẫn/so sánh trước; không mặc định có cho thuê.
- **Kỳ thuê:** theo ngày, tuần, tháng, đi làm, ở Hà Nội dài hạn.
- **Hỗ trợ:** điều kiện thuê, giấy tờ, đặt cọc, nhận/trả, giao xe, xử lý hỏng xe, an toàn giao thông, quy định bằng lái.
- **Địa phương:** Phố Cổ, Hoàn Kiếm, khu vực giao/nhận được xác thực; nội dung không tự suy diễn có chi nhánh ở phường/quận khác.

## 3. Một WordPress, một tên miền, SEO năm ngôn ngữ

| Ngôn ngữ | WordPress locale | Prefix URL | Ví dụ URL dịch vụ |
| --- | --- | --- | --- |
| **VI** (mặc định) | `vi_VN` | `/` | `/thue-xe-may-ha-noi/` |
| **EN** | `en_US` | `/en/` | `/en/motorbike-rental-hanoi/` |
| **DE** | `de_DE` | `/de/` | `/de/motorrad-mieten-hanoi/` |
| **FR** | `fr_FR` | `/fr/` | `/fr/location-moto-hanoi/` |
| **ES** | `es_ES` | `/es/` | `/es/alquiler-motos-hanoi/` |

**Không** tạo `/vi/` mặc định; chỉ dùng `/` cho VI. Prefix `en/de/fr/es` chỉ được đưa vào hoạt động sau khi plugin cung cấp route và nội dung hợp lệ. Giữ permalink WordPress `/%postname%/`. Không sửa Site URL/Home URL hoặc DNS để hỗ trợ đa ngôn ngữ.

### 3.1. Mục tiêu từ khóa theo ngôn ngữ (seed keywords)

| Locale | Từ khóa dịch vụ chính | Nhóm mở rộng |
| --- | --- | --- |
| VI | **Cho thuê xe máy Hà Nội** | thuê xe máy Phố Cổ, thuê xe máy Hoàn Kiếm, thuê xe máy tháng Hà Nội, giá thuê xe máy Hà Nội |
| EN | **Motorbike rental Hanoi** | scooter rental Hanoi Old Quarter, monthly motorbike rental Hanoi, 50cc scooter rental Hanoi |
| DE | **Motorrad mieten Hanoi** / Roller mieten Hanoi | Roller mieten Hanoi Altstadt, Langzeitmiete Roller Hanoi |
| FR | **Location de moto à Hanoï** | location de scooter à Hanoï, location de moto vieux quartier Hanoï |
| ES | **Alquiler de motos en Hanói** | alquiler de scooters en Hanói, alquiler de motos casco antiguo de Hanói |

**Chú ý:** đây là keyword seeds/giả thuyết để nghiên cứu, **chưa có số liệu search volume hay vị trí xếp hạng**. Từ khóa chủ lực toàn dự án là **“Cho thuê xe máy Hà Nội”**; mỗi ngôn ngữ dùng truy vấn tương đương theo ý định tìm kiếm tự nhiên, không dịch từng chữ máy móc.

### 3.2. SEO quốc tế bắt buộc

- Mỗi bản dịch là **một URL riêng** với tiêu đề, nội dung, meta title, meta description, H1, breadcrumbs, alt text và internal links theo ngôn ngữ.
- `<html lang>` chính xác; `hreflang` đối ứng đầy đủ chỉ giữa các trang **cùng chủ đề và đã xuất bản**, có thể dùng `x-default` cho trang chọn ngôn ngữ/mặc định khi phù hợp.
- **Canonical tự trỏ về URL cùng ngôn ngữ**; không canonical EN/DE/FR/ES về VI vì như vậy sẽ triệt tiêu mục tiêu index đa ngôn ngữ.
- Từng ngôn ngữ có sitemap URL hợp lệ (có thể `sitemap-index.xml` + sitemap con); loại draft, 404, noindex và bản dịch chưa có nội dung.
- Trang 404, tìm kiếm, menu, footer, CTA và thông tin liên hệ ở ngôn ngữ hiện hành; switcher chỉ nối sang bản tương đương đã publish. Nếu chưa có bản dịch, dẫn về trang ngôn ngữ thay thế minh bạch, không giả vờ URL đã có nội dung.
- Schema `LocalBusiness` / `AutoRental` chỉ khi các điều kiện thực tế đã xác minh; `name`, `address`, `telephone`, `openingHours` khớp NAP; `inLanguage` thích hợp cho `WebPage`/`Article`; không tạo rating/review giả.
- Hỗ trợ robots/meta robots, Open Graph, Twitter Card, JSON-LD, XML sitemap, breadcrumbs, pagination, 301 cho URL cũ có thật; tránh redirect theo IP/ngôn ngữ trình duyệt bắt buộc.
- Site mới không nên xuất bản hàng loạt nội dung dịch máy nghèo nàn. Chỉ index nội dung biên tập hoàn chỉnh với thông tin địa điểm/giá xác thực.

## 4. Kiến trúc nội dung và điều hướng

Tối thiểu cho mỗi ngôn ngữ:

1. **Trang chủ:** giới thiệu Mr Tu, ưu thế thực tế, thuê xe máy Hà Nội, khu vực Phố Cổ, nhóm xe, giá tham khảo đã xác nhận, liên hệ.
2. **Thuê xe máy Hà Nội (pillar chính):** chủ đề dịch vụ, điều kiện, thời hạn, mẫu xe, CTA.
3. **Bảng giá:** ngày/tuần/tháng, tiền cọc, phí giao/nhận và giới hạn đã duyệt.
4. **Các dòng xe:** xe số, xe ga, 50cc (nếu có), mẫu xe thực tế.
5. **Thuê theo thời hạn:** thuê ngày, thuê tuần, thuê tháng.
6. **Khu vực phục vụ:** Phố Cổ, Hoàn Kiếm và các khu vực phục vụ có thật.
7. **Hướng dẫn & FAQ:** giấy tờ, bằng lái, cọc, nhận/trả, an toàn, bảo dưỡng và hỗ trợ.
8. **Giới thiệu, Liên hệ, Chính sách bảo mật, Điều khoản và quy định thuê xe.**
9. **Blog/cẩm nang:** bài chất lượng phục vụ nhu cầu của từng locale, liên kết về pillar và dịch vụ phù hợp.

**Silo mẫu:** Home → Thuê xe máy Hà Nội → Theo tháng/Phố Cổ → Dòng xe → Bảng giá/FAQ → Liên hệ. Header/menu/footer/switcher dùng cấu trúc và giao diện nhất quán, **không trộn ngôn ngữ**. Có breadcrumb chuẩn, tìm kiếm theo ngôn ngữ, related posts theo locale và internal linking đúng intent. Không tạo hàng trăm trang địa điểm mỏng chỉ đổi địa danh.

### Gợi ý title trang chủ (chỉ là đề xuất, cần biên tập trước khi xuất bản)

- VI: `Cho thuê xe máy Hà Nội, Phố Cổ | Mr Tu`
- EN: `Motorbike Rental in Hanoi Old Quarter | Mr Tu`
- DE: `Motorrad & Roller mieten in Hanoi | Mr Tu`
- FR: `Location de motos et scooters à Hanoï | Mr Tu`
- ES: `Alquiler de motos y scooters en Hanói | Mr Tu`

WordPress General Settings: **Site Title = Mr Tu**; tagline toàn cục **để trống** cho đến khi theme/plugin có cấu hình riêng mỗi locale, tránh tagline tiếng Việt xuất hiện trên trang ngoại ngữ.

## 5. Plugin tự phát triển: `mr-tu-multilang-seo` (bắt buộc, không plugin ngoài)

**Mục đích:** Tự xây **một plugin WordPress native**, độc lập với theme, dễ sử dụng trên điện thoại, quản lý 5 ngôn ngữ + SEO + NAP + giá tập trung. Theme chỉ lo trình bày; logic language routing, dữ liệu business, SEO metadata thuộc plugin để đổi theme không mất dữ liệu.

### 5.1. Tính năng tối thiểu (MVP)

1. **Language manager:** 5 locale cố định; bật/tắt locale trong admin, VI là mặc định; cấu hình prefix.
2. **Translation groups:** tạo/sửa bản dịch cho Post, Page, danh mục và thẻ; liên kết cùng một nội dung bằng group ID; trạng thái nháp/đã xuất bản riêng; không tự sinh bản dịch rỗng.
3. **Trình quản trị đơn giản:** cột VI/EN/DE/FR/ES ngay danh sách bài; nút `+ Tạo bản dịch` / `Sửa` / `Xem`; copy bản gốc làm bản nháp **chỉ khi chủ site yêu cầu**, không publish bản sao; cảnh báo bản dịch thiếu.
4. **Slug và rewrite:** route `/`, `/en/`, `/de/`, `/fr/`, `/es/` cho Post, Page, category, tag, search, archive; xử lý trùng slug và 404 đúng; tránh can thiệp permalink WordPress cũ không cần thiết.
5. **Language switcher:** component/theme API + shortcode/block nếu cần; chỉ dẫn tới translation tồn tại, rõ trạng thái thiếu.
6. **SEO per locale:** SEO title/meta, canonical, hreflang reciprocal, XML sitemaps, robots/meta robots, OG/Twitter, Schema/structured data an toàn, breadcrumbs; không tự noindex trang hợp lệ.
7. **Business/NAP manager:** thông tin Mr Tu một chỗ, giá một chỗ, xe thực tế một chỗ; xuất dữ liệu đã xác nhận cho contact/footer/CTA/schema bằng WP APIs, không hard-code.
8. **Menu, strings và giao diện:** chuỗi theme dịch VI/EN/DE/FR/ES; menu và footer riêng theo locale; không có text bị trộn hoặc bỏ sót.
9. **Language-aware queries:** search, related posts, archive, recent posts, internal links và REST routes lọc đúng locale, không rò bản dịch khác vào kết quả.
10. **Security + operability:** `current_user_can`, nonce, escaping, sanitization, prepared queries, no public credential, uninstall không tự xóa nội dung; đơn giản, nhẹ, không phụ thuộc API key.

### 5.2. UX admin dành cho chủ website sử dụng iPhone

- Menu admin rõ ràng: **Mr Tu → Tổng quan · Cửa hàng · Bảng giá · Xe · Ngôn ngữ · Bản dịch · SEO · Gọi nhanh · Giao diện · Kiểm tra sức khỏe** (như đã xây).
- Mặc định hiển thị tiếng Việt; các nút lớn, biểu mẫu gọn, có xem trước, báo trạng thái bản dịch.
- Khi xuất bản: kiểm tra thiếu title/H1/meta, nội dung quá mỏng, trùng canonical, hreflang sai, địa chỉ/giá chưa duyệt, liên kết hỏng — cảnh báo hoặc chặn lỗi nghiêm trọng **nhưng không cản cập nhật hợp lệ một cách vô lý**.
- Một thao tác cập nhật giá/NAP đúng nơi sẽ đồng bộ cả site và cả 5 bản ngôn ngữ.
- Có export/import cấu hình với validate và backup, log thay đổi (không lưu mật khẩu vào Git).

### 5.3. Yêu cầu kỹ thuật, QA, hiệu năng

- Tương thích phiên bản WordPress/PHP thực tế trên Hostinger khi bắt đầu dev; dùng WordPress Coding Standards, i18n APIs, rewrite APIs, metadata và WP-Cron theo nhu cầu.
- Nơi lưu translation mappings có thể là post meta/tax meta với index/caching phù hợp; giải pháp DB riêng nếu cần phải có migration và rollback an toàn.
- Không query N+1 theo từng link; cache translation map, business facts và taxonomy terms; đảm bảo theme nhẹ trên Safari iPhone.
- Test routing và SEO cho mọi locale: homepage, page, post, taxonomy, search, pagination, draft, 404; multilingual canonical/hreflang sitemap; caching và REST; auth và capability; không trùng URL.
- Test nội dung Unicode tiếng Việt (đ/Đ), tiếng Đức (ä/ö/ü/ß), tiếng Pháp (é/à/ç), tiếng Tây Ban Nha (ñ/á/¿) và slug encoding.
- Mọi tính năng phải có hướng dẫn cài đặt, sửa 1 nơi đồng bộ toàn site, kiểm tra tương thích với server LiteSpeed và có rollback.

## 6. Theme `mr-tu` — đặc tả (đã xây trong `theme/mr-tu/`)

- WordPress theme riêng, tên hiển thị **Mr Tu**, mobile-first, tối ưu iPhone Safari, nhẹ, thân thiện người nước ngoài.
- Thiết kế linh hoạt, typography/chế độ sáng-tối/spacing/component đồng bộ; header/footer/menu dùng template parts chung — **sửa một chỗ, áp dụng toàn site**.
- Trang chủ 5 locale, template pillar/service/pricing/vehicle/FAQ/contact/blog/archive/single/404.
- Component: language switcher, call/WhatsApp/Zalo (chỉ kênh thật đã xác nhận), nút chỉ đường (chỉ sau xác minh điểm đến), price tables, cards, TOC, breadcrumb, CTA.
- Không nhúng translation groups / SEO metadata / Business facts vào logic riêng của theme; gọi API/plugin `mr-tu-multilang-seo`.
- Không dùng theme/page-builder/plugin của bên thứ ba.
- Khả năng triển khai: đóng gói ZIP cài trong WordPress; sau có thể thiết lập GitHub→Hostinger deploy với backup + kiểm tra + rollback; **repo này không phải GitHub Pages và không cần trỏ DNS vào GitHub**.

### Cấu trúc repository (thực tế)

```text
tu-theme/
├── README.md                      # Tài liệu này — nguồn yêu cầu
├── AGENTS.md                      # Quy tắc cho người/agent sửa code
├── theme/mr-tu/                  # WordPress theme (templates, parts/, patterns/, inc/, assets/, languages/)
├── plugin/mr-tu-multilang-seo/   # Plugin 5 ngôn ngữ + SEO + Business (includes/, includes/admin/, blocks/, assets/, languages/)
├── content/                      # Nội dung mẫu 5 ngôn ngữ (đóng vào ZIP plugin dưới seed/)
├── config/                       # Business facts, giá, xe (cờ verified từng trường), kế hoạch SEO
├── docs/                         # Hợp đồng kiến trúc, thiết kế, triển khai, rollback, hướng dẫn quản trị, QA, bảo mật
├── tests/                        # Test PHP (WP-CLI), HTTP, e2e (Playwright), Lighthouse
├── scripts/                      # dev-env, build ZIP, QA gate, clean-install test
└── .github/workflows/qa.yml      # CI: lint, WPCS + PHPCompat, WordPress integration, build ZIP (không deploy)
```

## 7. Nội dung không được tự suy đoán

- Tên đầy đủ của Google Business Profile hiện bị cắt trong ảnh.
- URL Google Maps chính thức / Place ID.
- Tính hợp lệ làm cửa hàng đón khách tại 17 Phúc Tân nếu hiện chỉ có biển hiệu.
- Email kinh doanh/WhatsApp/Zalo của số 0816 659 199.
- Xe đang có ở Phố Cổ; giá và tiền cọc của Mr Tu; phí giao/nhận; giới hạn hỗ trợ/phượt/ngoại tỉnh.
- Chứng chỉ, giấy phép, bảo hiểm, đánh giá Google và thứ hạng SEO.
- Ngân sách SEO, traffic và search volume theo 5 ngôn ngữ.

**Mọi dữ liệu chưa xác minh phải hiển thị `PENDING VERIFICATION` trong quản trị, không được trình bày như sự thật trên website công khai.**

## 8. Thứ tự triển khai khi bắt đầu viết code (sau này)

1. **Verify** NAP/Google Maps, giờ đón khách, danh mục xe và bảng giá của Mr Tu; lưu business facts chuẩn.
2. **Backup** WordPress mới và database riêng; kiểm tra health, PHP, WP version; đảm bảo 3 website cũ không bị tác động.
3. Thiết kế hợp đồng API dữ liệu, mô hình translation groups, routing, sitemap, canonical/hreflang.
4. Viết và kiểm thử **plugin `mr-tu-multilang-seo` trước**, chạy QA trên bản thử/staging.
5. Xây **theme `mr-tu`**, dùng APIs từ plugin; kiểm thử 5 ngôn ngữ và mobile.
6. Tạo từng trang dịch vụ cốt lõi chất lượng theo từng locale, kiểm tra NAP và giá trước khi index.
7. QA bảo mật, performance, SEO, accessibility, HTTP, backup/rollback; đóng gói ZIP và chỉ cài sau khi chủ site cho phép.
8. Đo bằng GSC/analytics sau triển khai; tối ưu dựa trên dữ liệu, không hứa lên top 1.

### Definition of Done

- [ ] Một WordPress, một tên miền, không Multisite.
- [ ] Plugin tự viết hỗ trợ VI/EN/DE/FR/ES, không plugin dịch/SEO bên ngoài.
- [ ] 5 homepage và các trang dịch vụ có URL + bản dịch thật, không trộn ngôn ngữ.
- [ ] Canonical/hreflang/sitemap/robots/schema chính xác và được QA.
- [ ] Mr Tu có NAP riêng, không lẫn Nguyễn Tú Long Biên.
- [ ] Giá/dòng xe đúng thực tế điểm Mr Tu; dùng nguồn dữ liệu tập trung.
- [ ] Giao diện đẹp, nhẹ, mobile-first, đồng bộ toàn site.
- [ ] Không làm ảnh hưởng website khác; có backup và rollback.
- [ ] Có tài liệu sử dụng cho người không chuyên trên điện thoại.

---

**Phạm vi của repository này:** mã nguồn theme/plugin, nội dung mẫu, tài liệu và kiểm thử. **Không** có bước nào tự động đụng
Hostinger, WordPress production, DNS hoặc Google Maps — việc cài đặt do chủ site thực hiện theo `docs/deployment-hostinger.md`.
