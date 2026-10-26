# Rollback — quay lại trạng thái an toàn trong vài phút

Nguyên tắc: mọi thành phần Mr Tu đều có thể **tắt độc lập**, dữ liệu **không bị xóa** khi tắt, và luôn có **bản sao lưu**
được tạo trước khi cài (xem `docs/deployment-hostinger.md` §0). Thứ tự xử lý khi có sự cố: **Mức 1 → Mức 2 → Mức 5**; mỗi mức
mất dưới 5 phút và không cần kiến thức kỹ thuật.

## Mức 0 — Trước khi cài: những thứ giúp quay lại nhanh

- Bản sao lưu *Files + Database* ở hPanel, **đã tải về máy** (bước 0 của hướng dẫn triển khai) — điều kiện bắt buộc để cài.
- Ghi lại (chụp màn hình) **tên theme đang active**, danh sách plugin đang bật, Settings → General (Timezone, Date format),
  Settings → Reading (Your homepage displays, Homepage, Posts page) và Settings → Permalinks. Không giả định theme cũ là gì.
- Sau khi plugin chạy: **Mr Tu → Tổng quan → Xuất cấu hình → Tải bản sao lưu (JSON)** (thông tin cửa hàng + giá) và cất file
  đó; nhập lại được ở **Mr Tu → Cửa hàng → Sao lưu & khôi phục → Nhập tệp sao lưu**.
- Giữ lại hai file ZIP đã cài (theme, plugin) và ghi số phiên bản (1.0.0) để cài lại đúng bản khi cần.
- Đã thử toàn bộ trên **staging** trước (mục 2.7 của hướng dẫn triển khai).

## Mức 1 — Giao diện có vấn đề (lỗi hiển thị, trắng trang sau khi kích hoạt theme)

1. WordPress Admin → **Appearance → Themes** → Activate **theme cũ đã ghi ở Mức 0**. Nội dung, ngôn ngữ, giá, NAP vẫn còn
   nguyên vì chúng thuộc plugin.
2. Không vào được Admin? Dùng **hPanel → File Manager** đổi tên thư mục `wp-content/themes/mr-tu` thành `mr-tu.off`;
   WordPress tự chuyển về theme mặc định **nếu một theme mặc định (ví dụ Twenty Twenty-Five) vẫn còn cài** — vì vậy không
   xóa theme cũ trước khi cài Mr Tu.
3. **LiteSpeed Cache → Toolbox → Purge All**.

## Mức 2 — Plugin Multilang SEO có vấn đề (lỗi URL, 404 hàng loạt, head SEO sai)

1. **Plugins → Deactivate** *Mr Tu Multilang SEO*. Khi tắt: các tiền tố `/en/ /de/ /fr/ /es/` không còn được định tuyến,
   các trang dịch vẫn tồn tại ở URL không tiền tố (WordPress mặc định), Yoast tiếp tục xuất SEO như trước. Theme Mr Tu vẫn
   chạy (không switcher, không giá, không số điện thoại).
2. **Settings → Permalinks → Save** để làm mới rewrite rules.
3. Không vào được Admin? Đổi tên `wp-content/plugins/mr-tu-multilang-seo` → `mr-tu-multilang-seo.off` bằng File Manager.
4. Dữ liệu được giữ: tùy chọn `mrtu_*`, taxonomy ngôn ngữ/bản dịch, meta SEO. Kích hoạt lại là hoạt động như cũ.
   Kể cả khi bấm **Delete** plugin, các tùy chọn `mrtu_*`, taxonomy ngôn ngữ/bản dịch và meta SEO **vẫn được giữ** (plugin
   không có tệp uninstall và không có tùy chọn xóa dữ liệu). Muốn xóa sạch phải làm thủ công bằng WP-CLI
   (`wp option delete mrtu_ml_settings mrtu_business mrtu_pricing mrtu_business_history mrtu_roles_map mrtu_redirects
   mrtu_redirect_hits mrtu_term_translations mrtu_seed_last_report mrtu_ml_version`) và **chỉ khi đã có bản sao lưu**.
5. Nếu plugin phải tắt quá vài giờ: chuyển các trang EN/DE/FR/ES sang **Nháp** (Pages → tìm theo tiêu đề; bộ lọc ngôn ngữ
   không còn hoạt động khi plugin tắt) hoặc bật *noindex* trong Yoast cho chúng, để Google không index các URL không tiền tố;
   bật lại plugin thì trả về *Đã đăng*.

## Mức 3 — Khôi phục giá/NAP/cấu hình

- **Mr Tu → Cửa hàng → Sao lưu & khôi phục**: chọn một bản trong *Lịch sử* (20 bản gần nhất) → *Khôi phục*.
- Hoặc **Nhập tệp sao lưu** từ file JSON đã tải về trước đó.

## Mức 4 — Nội dung mẫu không mong muốn

- Nội dung do *Tạo nội dung* sinh ra được đánh dấu bằng meta `_mrtu_seed_id`. Có thể chuyển về **Nháp** hàng loạt trong
  Pages (lọc theo ngôn ngữ), hoặc xóa. Chạy lại *Tạo nội dung* sẽ **không** ghi đè trang đã sửa tay.
- Trang chủ/bài viết: **Settings → Reading** đặt lại *Your homepage displays*, *Homepage*, *Posts page* về giá trị đã ghi ở
  Mức 0; **Settings → General** đặt lại *Timezone* và *Date format* nếu đã bị đổi (*Tạo nội dung* với tùy chọn thiết lập
  trang chủ đặt múi giờ Asia/Ho_Chi_Minh khi đang trống và định dạng ngày d/m/Y).

## Mức 5 — Khôi phục toàn bộ

- **hPanel → Websites → Manage → Backups → Restore** bản *Files + Database* đã tạo ở bước 0 của hướng dẫn triển khai.
- Sau khi khôi phục: LiteSpeed Purge All; kiểm tra trang chủ, `wp-admin`, Yoast.

## Việc không bao giờ làm khi rollback

- Không xóa thư mục `uploads`.
- Không chạy lệnh xóa dữ liệu plugin nếu chưa có bản sao lưu.
- Không đổi DNS, Site URL/Home URL hay cấu trúc permalink.
- Không gỡ Yoast, LiteSpeed Cache hay các plugin hệ thống của Hostinger để "sửa" lỗi — hãy tắt thành phần Mr Tu trước.
