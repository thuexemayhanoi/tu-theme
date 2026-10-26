# Hướng dẫn triển khai lên Hostinger (an toàn, có đường lui)

> Áp dụng cho website **https://hanoi.thuexemaynguyentu.com/** (WordPress 7.1.x trên Hostinger, LiteSpeed Cache, Yoast SEO
> + Yoast SEO Premium đang hoạt động). Tài liệu này mô tả cách cài **bản đóng gói đã qua QA** từ nhánh
> `claude/mr-tu-flagship`. Không thao tác nào ở đây đụng tới DNS, tên miền hay các website khác.
> Tên các mục trong giao diện Hostinger/hPanel có thể khác đôi chút theo phiên bản; ý nghĩa thao tác không đổi.

## 0. Trước khi làm gì cũng phải có bản sao lưu

1. **hPanel → Websites → Manage → Backups**: tạo (hoặc tải về) một bản sao lưu *Files + Database* mới nhất. Ghi lại thời điểm.
2. Trong WordPress: **Tools → Export** (All content) để có thêm bản XML nội dung hiện tại.
3. Ghi lại theme đang active (hiện là *Twenty Twenty-Five*) và danh sách plugin đang active (Plugins → Installed Plugins).
4. Nếu Hostinger có **Staging** (Websites → Manage → Staging): tạo staging từ bản production và thử toàn bộ quy trình bên dưới
   trên staging trước. Đây là cách an toàn nhất.

## 1. Những gì sẽ cài

| Gói | File | Vai trò |
|---|---|---|
| Theme Mr Tu | `dist/mr-tu-theme.zip` | Giao diện (trình bày). |
| Plugin Mr Tu Multilang SEO | `dist/mr-tu-multilang-seo.zip` | 5 ngôn ngữ, SEO, NAP/giá/xe, block dữ liệu, mục lục, Quick Call, nội dung mẫu. |
| Plugin Trợ lý Mr Tu (riêng tư) | `mr-tu-local-assistant.zip` — gửi riêng, **không** có trên GitHub | Hỏi đáp nội bộ không dùng API. |

Thứ tự bắt buộc: **plugin Multilang SEO → theme → (tùy chọn) plugin Trợ lý**. Plugin phải chạy trước để theme đọc được dữ liệu.

## 2. Cài đặt

### 2.1 Plugin Mr Tu Multilang SEO
1. **Plugins → Add New → Upload Plugin** → chọn `mr-tu-multilang-seo.zip` → *Install Now* → *Activate*.
2. Vào menu **Mr Tu → Tổng quan**. Kiểm tra: 5 ngôn ngữ hiện ra, chế độ SEO là **Tương thích Yoast** (tự phát hiện Yoast),
   trạng thái dữ liệu cửa hàng là *CHỜ XÁC MINH* ở các trường chưa xác nhận.
3. **Mr Tu → Thông tin cửa hàng**: kiểm tra số điện thoại `0816 659 199`, địa chỉ, giờ phục vụ, mô hình tiếp khách; bật
   *Đã xác minh* cho từng trường **chỉ khi đúng thực tế**. Trường chưa xác minh sẽ không hiển thị công khai.
4. **Mr Tu → Bảng giá** và **Dòng xe**: sửa giá/cọc theo đúng điểm Mr Tu rồi bật *Đã xác minh*. Chưa xác minh → website hiển thị
   "Liên hệ báo giá" (hoặc "giá tham khảo" nếu bật tùy chọn hiển thị giá tham khảo).

### 2.2 Theme Mr Tu
1. **Appearance → Themes → Add New → Upload Theme** → `mr-tu-theme.zip` → *Install* → **Live Preview** trước, sau đó *Activate*.
2. Sau khi kích hoạt: **Settings → Permalinks → Save Changes** (làm mới rewrite rules, không đổi cấu trúc `/%postname%/`).

### 2.3 Nội dung 5 ngôn ngữ (một lần)
1. **Mr Tu → Tổng quan → Thẻ "Nội dung mẫu"**: chọn *Chạy thử (dry run)* để xem báo cáo, rồi bỏ chọn và bấm **Tạo nội dung**
   với tùy chọn *Thiết lập trang chủ / trang bài viết*. Quy trình này **idempotent**: chạy lại không tạo trùng, không ghi đè
   trang đã sửa tay.
2. Kiểm tra **Mr Tu → Bản dịch**: mỗi vai trò trang phải có đủ VI ✅ EN ✅ DE ✅ FR ✅ ES ✅.
3. Mở `/`, `/en/`, `/de/`, `/fr/`, `/es/` và vài trang con. Nhấn bộ chuyển ngôn ngữ để chắc chắn liên kết đúng bản dịch.
4. Chuyển hai bài mặc định *Hello world!* và bài thử nghiệm *Giới thiệu Claude* sang **Nháp** hoặc **Thùng rác** (chủ site quyết định).

### 2.4 SEO & Yoast (chế độ tương thích)
- Plugin Mr Tu **không** gỡ/tắt Yoast. Ở chế độ tương thích, Yoast tiếp tục xuất title/description/canonical/schema; Mr Tu bổ
  sung **hreflang** và truyền tiêu đề/mô tả đã biên tập cho Yoast khi ô Yoast của trang đó để trống.
- Kiểm tra **Mr Tu → SEO → Kiểm tra head trực tiếp**: mỗi trang phải có đúng **1** canonical, **1** meta description, **1** robots.
- Yoast → Settings → Site representation: điền *Organization* **Mr Tu** (chưa có thì Yoast không xuất Organization).
- Sitemap: ở chế độ tương thích dùng sitemap của Yoast (`/sitemap_index.xml`). Khi (và chỉ khi) chủ site quyết định tắt các
  tính năng SEO trùng của Yoast, chuyển **Mr Tu → SEO → Chế độ** sang *Độc lập*; khi đó plugin xuất toàn bộ head +
  `/mrtu-sitemap-index.xml`. Thay đổi này cần kiểm tra lại Google Search Console.
- Gửi sitemap đang dùng trong **Google Search Console**; thêm **5 thư mục** (`/`, `/en/`, `/de/`, `/fr/`, `/es/`) làm bộ lọc
  báo cáo.

### 2.5 LiteSpeed Cache
1. Sau mỗi bước cài/seed: **LiteSpeed Cache → Toolbox → Purge All**.
2. Ngôn ngữ phân theo **đường dẫn URL**, không theo cookie hay trình duyệt, nên cache trang hoạt động bình thường.
   Không bật tính năng "vary by browser language" nào.
3. Nếu bật tối ưu CSS/JS của LiteSpeed: giữ nguyên cài đặt mặc định, kiểm tra lại Light/Dark và menu mobile sau khi bật.
   Nếu có lỗi hiển thị, loại trừ `mr-tu/assets/js/theme.min.js` khỏi *JS Combine/Defer*.

### 2.6 Plugin Trợ lý Mr Tu (riêng tư, tùy chọn)
1. Upload `mr-tu-local-assistant.zip` → Activate. Nút "Hỏi nhanh" xuất hiện trong Quick Call.
2. **Mr Tu → Chatbot → Tạo lại chỉ mục** cho 5 ngôn ngữ, thử vài câu hỏi trong *Bảng kiểm tra*.
3. Trợ lý chỉ trả lời từ dữ liệu đã xác minh và nội dung đã xuất bản; không gọi dịch vụ ngoài, không có chi phí API.

## 3. Kiểm tra sau triển khai (checklist)

- [ ] 5 trang chủ, 5 trang dịch vụ trụ cột, bảng giá, hướng dẫn & FAQ trả về 200; `/en` chuyển hướng 301 → `/en/`.
- [ ] `<html lang>` đúng từng ngôn ngữ; không trộn ngôn ngữ trong menu/footer/CTA.
- [ ] Mr Tu → Kiểm tra sức khỏe: không có lỗi đỏ.
- [ ] Mỗi trang 1 canonical, 1 description, 1 robots; hreflang có đủ các bản đã xuất bản + x-default.
- [ ] Thông tin chưa xác minh **không** hiện công khai (địa chỉ, giờ, giá, cọc).
- [ ] Gọi thử nút **Gọi** trên iPhone; mở menu mobile; chuyển Light/Dark; mở mục lục trên trang hướng dẫn.
- [ ] PageSpeed Insights (mobile) cho trang chủ sau khi LiteSpeed cache đã nóng.
- [ ] Google Business Profile: đổi link website về `https://hanoi.thuexemaynguyentu.com/` (chủ site tự làm).

## 4. Vận hành hằng ngày (trên iPhone)

- Đổi số điện thoại/địa chỉ/giờ: **Mr Tu → Thông tin cửa hàng** → Lưu. Toàn site cả 5 ngôn ngữ cập nhật ngay.
- Đổi giá/cọc: **Mr Tu → Bảng giá** → Lưu (có *Khôi phục* bản trước trong *Lịch sử*).
- Sửa nội dung một trang: Pages → mở đúng bản ngôn ngữ (cột VI/EN/DE/FR/ES) → sửa → Update. Hộp **SEO Mr Tu** bên phải cho
  tiêu đề/mô tả tìm kiếm.
- Viết bài mới: Posts → Add New → chọn ngôn ngữ trong hộp *Ngôn ngữ & bản dịch* → đăng → bấm ➕ ở các cột ngôn ngữ khác để tạo bản dịch.

## 5. Khi có sự cố → xem `docs/rollback.md`
