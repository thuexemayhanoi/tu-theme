# Rollback — quay lại trạng thái an toàn trong vài phút

Nguyên tắc: mọi thành phần Mr Tu đều có thể **tắt độc lập**, dữ liệu **không bị xóa** khi tắt, và luôn có **bản sao lưu**
được tạo trước khi cài (xem `docs/deployment-hostinger.md` §0).

## Mức 1 — Giao diện có vấn đề (lỗi hiển thị, trắng trang sau khi kích hoạt theme)

1. WordPress Admin → **Appearance → Themes** → Activate **Twenty Twenty-Five** (theme cũ). Nội dung, ngôn ngữ, giá, NAP vẫn còn
   nguyên vì chúng thuộc plugin.
2. Không vào được Admin? Dùng **hPanel → File Manager** đổi tên thư mục `wp-content/themes/mr-tu` thành `mr-tu.off`;
   WordPress tự chuyển về theme mặc định còn lại.
3. **LiteSpeed Cache → Purge All**.

## Mức 2 — Plugin Multilang SEO có vấn đề (lỗi URL, 404 hàng loạt, head SEO sai)

1. **Plugins → Deactivate** *Mr Tu Multilang SEO*. Khi tắt: các tiền tố `/en/ /de/ /fr/ /es/` không còn được định tuyến,
   các trang dịch vẫn tồn tại ở URL không tiền tố (WordPress mặc định), Yoast tiếp tục xuất SEO như trước.
2. **Settings → Permalinks → Save** để làm mới rewrite rules.
3. Không vào được Admin? Đổi tên `wp-content/plugins/mr-tu-multilang-seo` → `mr-tu-multilang-seo.off` bằng File Manager.
4. Dữ liệu được giữ: tùy chọn `mrtu_*`, taxonomy ngôn ngữ/bản dịch, meta SEO. Kích hoạt lại là hoạt động như cũ.
   Chỉ khi bật *Xóa dữ liệu khi gỡ cài đặt* trong **Mr Tu → SEO** rồi **Delete** plugin thì tùy chọn mới bị xóa.

## Mức 3 — Khôi phục giá/NAP/cấu hình

- **Mr Tu → Thông tin cửa hàng → Sao lưu & khôi phục**: chọn một bản trong *Lịch sử* (20 bản gần nhất) → *Khôi phục*.
- Hoặc **Nhập JSON** từ file đã *Xuất* trước đó.

## Mức 4 — Nội dung mẫu không mong muốn

- Nội dung do *Tạo nội dung* sinh ra được đánh dấu bằng meta `_mrtu_seed_id`. Có thể chuyển về **Nháp** hàng loạt trong
  Pages (lọc theo ngôn ngữ), hoặc xóa. Chạy lại *Tạo nội dung* sẽ **không** ghi đè trang đã sửa tay.
- Trang chủ/bài viết: **Settings → Reading** đặt lại *Your homepage displays* về giá trị cũ nếu cần.

## Mức 5 — Khôi phục toàn bộ

- **hPanel → Websites → Manage → Backups → Restore** bản *Files + Database* đã tạo ở bước 0 của hướng dẫn triển khai.
- Sau khi khôi phục: LiteSpeed Purge All; kiểm tra trang chủ, `wp-admin`, Yoast.

## Việc không bao giờ làm khi rollback

- Không xóa thư mục `uploads`.
- Không chạy lệnh xóa dữ liệu plugin nếu chưa có bản sao lưu.
- Không đổi DNS, Site URL/Home URL hay cấu trúc permalink.
- Không gỡ Yoast, LiteSpeed Cache hay các plugin hệ thống của Hostinger để "sửa" lỗi — hãy tắt thành phần Mr Tu trước.
