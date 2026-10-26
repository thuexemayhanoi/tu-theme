# Hướng dẫn triển khai lên Hostinger (an toàn, có đường lui)

> Áp dụng cho website **https://hanoi.thuexemaynguyentu.com/** (WordPress 7.1.x trên Hostinger, LiteSpeed Cache, Yoast SEO
> + Yoast SEO Premium đang hoạt động). Tài liệu này mô tả cách cài **bản đóng gói đã qua QA** từ nhánh
> `claude/mr-tu-flagship`. Không thao tác nào ở đây đụng tới DNS, tên miền hay các website khác.
> Tên các mục trong giao diện Hostinger/hPanel có thể khác đôi chút theo phiên bản; ý nghĩa thao tác không đổi.
> Tên menu/nút trong WordPress ở đây là tên **tiếng Việt thật** của plugin (menu **Mr Tu**: Tổng quan · Cửa hàng · Bảng giá ·
> Xe · Ngôn ngữ · Bản dịch · SEO · Gọi nhanh · Giao diện · Kiểm tra sức khỏe).

## 0. Trước khi làm gì cũng phải có bản sao lưu

1. **hPanel → Websites → Manage → Backups**: tạo một bản sao lưu *Files + Database* mới, **tải về máy/điện thoại** và kiểm tra
   dung lượng > 0. Ghi lại thời điểm.
2. Tạo thêm một bản SQL: **hPanel → Databases → phpMyAdmin → Export** (gzip).
3. Trong WordPress: **Tools → Export** (All content) để có thêm bản XML nội dung hiện tại.
4. Chụp màn hình/ghi lại: **tên theme đang active** (Appearance → Themes — không giả định, không xóa theme cũ), danh sách
   plugin đang bật kèm phiên bản, **Settings → General** (Site Title, Tagline, Timezone, Date format), **Settings → Reading**
   (Your homepage displays, Homepage, Posts page), **Settings → Permalinks** (phải là `/%postname%/`), danh sách Pages/Posts
   hiện có kèm slug. Quá trình cài sẽ đổi một số giá trị này (xem §2.3).
5. **Yoast → Tools → Import/Export → Export settings** và lưu file.
6. **Bắt buộc thử trên Staging trước** (hPanel → Websites → Manage → Staging → *Create staging*): làm toàn bộ mục 1–3 và
   checklist §2.7 trên bản staging. Chỉ khi staging đạt checklist mới lặp lại trên production. Không có staging → dừng lại
   và hỏi; không cài thẳng lên production.
7. Chọn giờ vắng khách (sáng sớm) và chuẩn bị sẵn `docs/rollback.md` mở trên điện thoại.

Bản sao lưu dùng để quay lại là bản ở bước 1. Mọi bước bên dưới đều có thể hoàn tác bằng cách tắt theme/plugin Mr Tu — dữ liệu
không bị xóa khi tắt — **trừ** các trang/chuyên mục do *Tạo nội dung* tạo ra (chuyển về Nháp/xóa thủ công) và các giá trị
Settings → Reading/General mà *Tạo nội dung* đã đổi (đặt lại theo ghi chép ở bước 4).

## 1. Những gì sẽ cài

| Gói | File | Vai trò |
|---|---|---|
| Plugin Mr Tu Multilang SEO | `dist/mr-tu-multilang-seo.zip` | 5 ngôn ngữ, SEO, NAP/giá/xe, block dữ liệu, mục lục, Gọi nhanh, nội dung mẫu. |
| Theme Mr Tu | `dist/mr-tu-theme.zip` | Giao diện (trình bày). |
| Plugin Trợ lý Mr Tu (riêng tư, **chưa phát hành**) | `dist-private/mr-tu-local-assistant.zip` — tạo bởi `scripts/build.sh` chỉ khi có thư mục `private/`, gửi riêng sau, **không** có trên GitHub | Hỏi đáp nội bộ không dùng API. Không thuộc đợt cài này; website hoạt động đầy đủ khi chưa có. |

Hai file ZIP lấy từ artifact `mr-tu-zips` của lần chạy GitHub Actions mới nhất trên nhánh `claude/mr-tu-flagship` (hoặc
`bash scripts/build.sh` tạo ra `dist/`). Phiên bản 1.0.0; theme yêu cầu WordPress ≥ 6.6, PHP ≥ 7.4.

Thứ tự khuyến nghị: **plugin Multilang SEO → theme**, để ngay khi kích hoạt theme đã có ngôn ngữ, giá và NAP. (Về kỹ thuật
theme vẫn chạy khi plugin tắt — không switcher, không giá, không số điện thoại — chính là cơ chế rollback Mức 2.)

## 2. Cài đặt

### 2.1 Plugin Mr Tu Multilang SEO
1. **Plugins → Add New → Upload Plugin** → chọn `mr-tu-multilang-seo.zip` → *Install Now* → *Activate*.
2. Vào menu **Mr Tu → Tổng quan**. Kiểm tra: 5 ngôn ngữ hiện ra; thẻ **Chế độ SEO** ghi *Đang chạy*: **Tương thích Yoast**
   (tự phát hiện Yoast); bảng xác minh dữ liệu cửa hàng ghi *CHỜ XÁC MINH* ở các trường chưa xác nhận.
3. **Mr Tu → Cửa hàng** (màn *Thông tin cửa hàng*): kiểm tra số điện thoại `0816 659 199`, địa chỉ, giờ phục vụ, mô hình tiếp
   khách; bật *Đã xác minh — tôi đã tự kiểm tra* cho từng trường **chỉ khi đúng thực tế**. Trường chưa xác minh sẽ không hiển
   thị công khai (địa chỉ chưa xác minh → website chỉ ghi "Phố Cổ, Hà Nội"; giờ chưa xác minh → không hiện giờ).
   **Lưu ý:** số điện thoại `0816 659 199` được gói sẵn ở trạng thái *Đã xác minh* và nút *Gọi* của Gọi nhanh bật mặc định,
   nên nút gọi hiện ra ngay sau khi kích hoạt theme. Nếu chủ site chưa gọi thử xác nhận số này, tắt công tắc *Đã xác minh*
   ở mục Số điện thoại (hoặc tắt *Hiện nút gọi trên website*) trước khi kích hoạt theme.
4. **Mr Tu → Bảng giá** và **Mr Tu → Xe**: sửa giá/cọc theo đúng điểm Mr Tu, rồi bật **Giá đã xác nhận cho Mr Tu** (toàn
   bảng, kèm *Ngày bạn kiểm tra giá lần cuối*) và **Giá đã xác nhận cho dòng xe này** ở từng xe; phí giao/nhận có công tắc
   *Đã xác minh* riêng. Chưa xác nhận → website hiển thị "Liên hệ báo giá" (hoặc "giá tham khảo, xác nhận trước khi đặt" nếu
   bật *Hiện giá tham khảo khi chưa xác nhận*).

### 2.2 Theme Mr Tu
1. **Appearance → Themes → Add New → Upload Theme** → `mr-tu-theme.zip` → *Install* → **Live Preview** trước, sau đó *Activate*.
2. Sau khi kích hoạt: **Settings → Permalinks → Save Changes** (làm mới rewrite rules, không đổi cấu trúc `/%postname%/`).

### 2.3 Nội dung 5 ngôn ngữ (một lần)
1. **Mr Tu → Tổng quan → thẻ "Nội dung mẫu"**: chọn *Chạy thử (dry run)* để xem báo cáo, rồi bỏ chọn và bấm **Tạo nội dung**
   với tùy chọn *Thiết lập trang chủ / trang bài viết (cài đặt Đọc)*. Tùy chọn này đặt trang chủ tĩnh = trang chủ VI, trang bài
   viết = blog VI, **múi giờ Asia/Ho_Chi_Minh (nếu đang trống) và định dạng ngày d/m/Y** — các giá trị cũ đã ghi ở §0 bước 4.
   Giữ tùy chọn *Làm mới các trang chưa chỉnh sửa từ tệp* ở trạng thái **tắt**. Quy trình **idempotent**: chạy lại không tạo
   trùng, không ghi đè trang đã sửa tay.
2. Kiểm tra **Mr Tu → Bản dịch**: mỗi vai trò trang phải có đủ VI ✅ EN ✅ DE ✅ FR ✅ ES ✅.
3. Mở `/`, `/en/`, `/de/`, `/fr/`, `/es/` và vài trang con. Nhấn bộ chuyển ngôn ngữ để chắc chắn liên kết đúng bản dịch.
4. **Nếu còn**, chuyển bài mặc định *Hello world!* và bài thử nghiệm *Giới thiệu Claude* (thấy trên site ngày 25/10) sang
   **Nháp** hoặc **Thùng rác** (chủ site quyết định).

### 2.4 SEO & Yoast (chế độ tương thích)
- Plugin Mr Tu **không** gỡ/tắt Yoast. Ở chế độ tương thích, Yoast tiếp tục xuất title/description/canonical/schema; Mr Tu bổ
  sung **hreflang** và truyền *Tiêu đề SEO / Mô tả meta* đã biên tập cho Yoast khi ô Yoast của trang đó để trống.
- Kiểm tra **Mr Tu → SEO → thẻ Kiểm tra head trực tiếp → Chạy kiểm tra**: mỗi trang chủ ngôn ngữ phải có đúng **1** canonical,
  **1** meta description, **1** robots. Nếu báo *máy chủ không tự gọi được* (Hostinger có thể chặn loopback), mở `view-source:`
  từng trang chủ và đếm thủ công.
- Yoast → Settings → Site representation: điền *Organization* **Mr Tu** (chưa có thì Yoast không xuất Organization). Không điền
  địa chỉ/giờ vào Yoast khi chưa xác minh (Mr Tu quản lý NAP).
- Sitemap: ở chế độ tương thích dùng sitemap của Yoast (`/sitemap_index.xml`). Khi (và chỉ khi) chủ site quyết định tắt các
  tính năng SEO trùng của Yoast, chuyển **Mr Tu → SEO → Chế độ** sang *Độc lập*; khi đó plugin xuất toàn bộ head +
  `/mrtu-sitemap-index.xml`. Khi chuyển sang *Độc lập* mà Yoast vẫn bật, **phải** tắt sitemap của Yoast tại Yoast → Settings →
  Site features → APIs → *XML sitemaps* (plugin không tắt được giúp), rồi Settings → Permalinks → Save; kiểm tra
  `/sitemap_index.xml` trả 404 và `robots.txt` chỉ còn **một** dòng Sitemap. Yoast vẫn in dòng chú thích
  `<!-- This site is optimized with Yoast -->` — không phải lỗi. Thay đổi này cần kiểm tra lại Google Search Console.
- Gửi sitemap đang dùng trong **Google Search Console**; thêm **5 thư mục** (`/`, `/en/`, `/de/`, `/fr/`, `/es/`) làm bộ lọc
  báo cáo.

### 2.5 LiteSpeed Cache
1. Plugin Mr Tu **không** tự xóa cache. Sau mỗi bước cài/seed và sau mỗi lần lưu Cửa hàng/Bảng giá: **LiteSpeed Cache →
   Toolbox → Purge All**, rồi mở site bằng trình duyệt ẩn danh để kiểm tra.
2. Ngôn ngữ phân theo **đường dẫn URL**, không theo cookie hay trình duyệt, nên cache trang hoạt động bình thường.
   Không bật tính năng "vary by browser language" nào; không bật *Cache mobile riêng* nếu không cần.
3. Nếu bật tối ưu CSS/JS của LiteSpeed: giữ nguyên cài đặt mặc định, kiểm tra lại Light/Dark và menu mobile sau khi bật.
   Nếu có lỗi hiển thị, loại trừ `mr-tu/assets/js/theme.min.js` khỏi *JS Combine/Defer*.

### 2.6 Plugin Trợ lý Mr Tu (riêng tư, tùy chọn — đợt sau)
Không cài trong đợt phát hành này. Khi nhận được gói riêng sau này:
1. Upload `mr-tu-local-assistant.zip` → Activate. Nút **Đặt câu hỏi** xuất hiện trong Gọi nhanh.
2. **Mr Tu → Chatbot → Tạo lại chỉ mục** cho 5 ngôn ngữ, thử vài câu hỏi trong bảng kiểm tra.
3. Trợ lý chỉ trả lời từ dữ liệu đã xác minh và nội dung đã xuất bản; không gọi dịch vụ ngoài, không có chi phí API.

## 2.7 Kiểm tra tương thích Yoast trên staging (bắt buộc trước production)

Môi trường kiểm thử của nhóm phát triển không cài được Yoast (offline), nên chế độ tương thích mới chỉ được kiểm tra bằng
bộ giả lập các filter của Yoast. Trên **staging** (Yoast SEO + Premium đang bật) hãy kiểm tra thật:

1. **Mr Tu → Tổng quan → thẻ Chế độ SEO**: dòng *Đang chạy* phải hiện **Tương thích Yoast** và dòng *Yoast SEO* hiện *Đang kích
   hoạt*. Trên màn **Mr Tu → SEO**, dòng *Chế độ đang chạy hiện tại* cũng phải ghi *Tương thích Yoast*. Sau đó bấm **Chạy kiểm
   tra** trong thẻ **Kiểm tra head trực tiếp**: 5 trang chủ ngôn ngữ đều phải có đúng **1 canonical, 1 description, 1 robots**
   và đủ hreflang (vi-VN, en, de, fr, es, x-default).
2. Mở `view-source:` của `/`, `/en/`, một trang bảng giá và một bài cẩm nang: không có hai thẻ `<title>`, hai `canonical` hay hai
   khối `application/ld+json` cùng loại `WebSite`/`Organization`.
3. Yoast → **Settings → Site features**: giữ *XML sitemaps* bật; mở `/sitemap_index.xml` — phải liệt kê các trang ở cả 5 thư mục
   ngôn ngữ; `/mrtu-sitemap-index.xml` lúc này **không** cần tồn tại (chỉ dùng ở chế độ Độc lập).
4. Sửa *Tiêu đề SEO / Mô tả meta* trong hộp **SEO Mr Tu** (phía dưới khung soạn thảo) của một trang mà ô Yoast để trống → head
   phải đổi theo; điền ô Yoast của trang đó → Yoast phải thắng. Xóa thử nghiệm sau khi kiểm tra.
5. Yoast → **Settings → Site representation**: *Organization*, tên **Mr Tu**, logo. Không điền địa chỉ/giờ vào Yoast khi chưa
   xác minh (Mr Tu quản lý NAP).
6. **LiteSpeed Cache → Purge All**, mở site ở trình duyệt ẩn danh trên điện thoại: menu, Light/Dark, nút Gọi, chuyển ngôn ngữ.
7. **Yoast SEO Premium:** kiểm tra giấy phép Premium còn hoạt động trên tên miền staging (MyYoast cho phép thêm site staging) —
   nếu không, Redirect Manager không chạy và bài kiểm tra không đại diện.
8. **Chuyển hướng:** cả Yoast Premium (Redirect Manager) lẫn **Mr Tu → SEO → Chuyển hướng** đều tự tạo chuyển hướng khi đổi
   slug. Trên staging đổi thử slug một trang đã đăng, mở URL cũ và xác nhận chỉ **một** chuyển hướng 301 (không vòng lặp,
   không 2 bước); quyết định giữ một bên (khuyến nghị: để Yoast Premium quản lý, xóa bản trùng trong Mr Tu → SEO → Chuyển hướng
   nếu có).
9. **LiteSpeed:** sau khi bật cache, mở `/en/` và `/de/` hai lần liên tiếp — nội dung đúng ngôn ngữ, header `x-litespeed-cache:
   hit` ở lần hai; bộ chuyển ngôn ngữ vẫn dẫn đúng.
10. Kết quả đạt → ghi lại ngày giờ, rồi lặp lại đúng thứ tự trên production. Kết quả không đạt → giữ nguyên production, gửi
    ảnh chụp lỗi cho nhóm phát triển.

## 3. Kiểm tra sau triển khai (checklist)

- [ ] 5 trang chủ, 5 trang dịch vụ trụ cột, bảng giá, hướng dẫn & FAQ trả về 200; `/en`, `/de`, `/fr`, `/es` → 301 về dạng có
      dấu `/` cuối; `/vi/` trả 404 (tiếng Việt không có tiền tố).
- [ ] `<html lang>` đúng từng ngôn ngữ; không trộn ngôn ngữ trong menu/footer/CTA.
- [ ] `view-source:` của 5 trang chủ + 1 trang con mỗi ngôn ngữ: đúng 1 `<title>`; **không** có địa chỉ/số điện thoại của địa
      điểm Long Biên; địa chỉ/giờ chưa xác minh **không có trong mã nguồn** (không chỉ ẩn bằng CSS); JSON-LD không có
      `AutoRental`/`LocalBusiness` khi NAP chưa xác minh.
- [ ] `/robots.txt` có đúng **một** dòng Sitemap (của Yoast ở chế độ tương thích); `/wp-sitemap.xml` chỉ tắt ở chế độ Độc lập.
- [ ] Mr Tu → Kiểm tra sức khỏe → Chạy lại: không có dòng trong nhóm *Lỗi*.
- [ ] Mỗi trang 1 canonical, 1 description, 1 robots; hreflang có đủ các bản đã xuất bản + x-default.
- [ ] Thông tin chưa xác minh **không** hiện công khai (địa chỉ, giờ, giá, cọc).
- [ ] Trên iPhone: bấm nút **Gọi** mở đúng `tel:+84816659199`; mở menu mobile; chuyển Light/Dark; mở mục lục trên trang hướng dẫn.
- [ ] LiteSpeed: lần tải thứ hai của `/` và `/en/` có `x-litespeed-cache: hit`; hPanel → Error log không có lỗi PHP mới.
- [ ] PageSpeed Insights (mobile) cho trang chủ sau khi LiteSpeed cache đã nóng.
- [ ] Google Search Console: gửi sitemap đang dùng; Google Business Profile: đổi link website về
      `https://hanoi.thuexemaynguyentu.com/` (chủ site tự làm).

## 4. Vận hành hằng ngày (trên iPhone)

- Đổi số điện thoại/địa chỉ/giờ: **Mr Tu → Cửa hàng** → *Lưu thay đổi* → LiteSpeed Purge All. Toàn site cả 5 ngôn ngữ cập nhật.
- Đổi giá/cọc: **Mr Tu → Bảng giá** → *Lưu thay đổi* → LiteSpeed Purge All. Bản trước được lưu tự động; khôi phục tại
  **Mr Tu → Cửa hàng → Sao lưu & khôi phục → Lịch sử → Khôi phục**.
- Sửa nội dung một trang: Pages → mở đúng bản ngôn ngữ (cột VI/EN/DE/FR/ES) → sửa → Update. Hộp **SEO Mr Tu** phía dưới khung
  soạn thảo cho *Tiêu đề SEO / Mô tả meta / Từ khóa chính*.
- Viết bài mới: Posts → Add New → chọn ngôn ngữ trong hộp *Ngôn ngữ & bản dịch* (cột phải) → đăng → bấm ➕ ở các cột ngôn ngữ
  khác trong danh sách Posts để tạo bản dịch.

## 5. Khi có sự cố → xem `docs/rollback.md`
