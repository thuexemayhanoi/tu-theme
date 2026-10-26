# Hướng dẫn quản trị website Mr Tu (dành cho chủ cửa hàng, dùng trên iPhone)

> Đăng nhập **wp-admin** như bình thường. Mọi việc của Mr Tu nằm trong menu **Mr Tu** ở cột trái (biểu tượng cột cây số).
> Các màn hình được thiết kế cho màn hình điện thoại: nút lớn, lưu ở thanh dính phía dưới, có thông báo xanh/đỏ sau mỗi lần lưu.
> Nguyên tắc vàng: **thông tin chưa bật "Đã xác minh" sẽ không hiện ra với khách**. Website không bao giờ tự bịa giá, giờ hay địa chỉ.

## 1. Menu Mr Tu có gì

| Mục | Dùng khi | Ghi chú |
|---|---|---|
| **Tổng quan** | Muốn nhìn nhanh tình trạng site, tạo nội dung mẫu, xuất cấu hình | Thẻ số liệu + cảnh báo sức khỏe |
| **Thông tin cửa hàng** | Đổi tên hiển thị, địa chỉ, số điện thoại, giờ, Zalo/WhatsApp, link Google Maps | Mỗi trường có công tắc *Đã xác minh* |
| **Bảng giá** | Đổi giá ngày/tuần/tháng, tiền cọc, phí giao nhận, ngày rà soát | Có xem trước đúng như khách thấy |
| **Dòng xe** | Thêm/sửa/ẩn xe, ảnh xe, mô tả 5 ngôn ngữ | Xe *Ẩn* không bao giờ hiện |
| **Ngôn ngữ** | Bật/tắt ngôn ngữ, tagline theo ngôn ngữ, x-default | Tiếng Việt luôn là mặc định |
| **Bản dịch** | Xem bảng VI/EN/DE/FR/ES của từng trang, tạo bản dịch còn thiếu | ✅ đã đăng · ✏️ nháp · ➕ chưa có |
| **SEO** | Chế độ Yoast/độc lập, sitemap, schema, mục lục, chuyển hướng, kiểm tra head | Chỉ chỉnh khi hiểu rõ |
| **Quick Call** | Bật/tắt nút Gọi, Zalo, WhatsApp, Chỉ đường, Hỏi nhanh ở góc màn hình | Chỉ kênh đã xác minh mới hiện |
| **Chatbot** | Lời chào, câu hỏi – trả lời tùy chỉnh, nhật ký câu chưa trả lời được, tạo lại chỉ mục | Có khi cài plugin Trợ lý |
| **Giao diện** | Ảnh hero, ảnh câu chuyện, hiệu ứng, chế độ màu mặc định | Thuộc theme |
| **Kiểm tra sức khỏe** | Trước khi đăng gì quan trọng, hoặc khi thấy site "lạ" | Có nút sửa nhanh |

## 2. Việc làm thường xuyên nhất

### 2.1 Đổi số điện thoại / địa chỉ / giờ phục vụ
1. **Mr Tu → Thông tin cửa hàng**.
2. Sửa ô cần đổi. Số điện thoại gồm *số hiển thị* (ví dụ `0816 659 199`) và *số quốc tế* (`+84816659199`, hệ thống gợi ý sẵn).
3. Bật **Đã xác minh** ở trường đó nếu thông tin đúng thực tế. Chưa chắc → để tắt, website sẽ ghi "liên hệ để xác nhận".
4. Bấm **Lưu thay đổi**. Toàn bộ 5 ngôn ngữ, footer, trang liên hệ, nút Gọi cập nhật ngay. LiteSpeed cache sẽ tự làm mới;
   nếu chưa thấy đổi, vào **LiteSpeed Cache → Purge All**.

Mỗi lần lưu, bản cũ được giữ trong **Lịch sử** (20 bản). Lỡ tay → **Sao lưu & khôi phục → Khôi phục**.

### 2.2 Đổi giá thuê / tiền cọc
1. **Mr Tu → Bảng giá**. Nhập giá VNĐ (chỉ số, không dấu chấm). Ô *từ–đến* cho khoảng giá (ví dụ tháng 900000–1200000).
2. Để trống ô = "Liên hệ báo giá".
3. Bật **Đã xác minh** cho bảng giá (và ngày rà soát) khi giá đúng tại điểm Mr Tu. Chưa bật → website hiện "giá tham khảo" nếu bật
   tùy chọn *Hiển thị giá tham khảo*, còn không thì hiện "Liên hệ báo giá".
4. Xem **Xem trước trên website** ngay dưới form rồi **Lưu**.

### 2.3 Thêm hoặc ẩn một dòng xe
1. **Mr Tu → Dòng xe → Thêm xe** (hoặc bấm vào xe có sẵn).
2. Điền tên (ví dụ *Honda Vision*), loại (*Xe tay ga* / *Xe số* / *50cc* / *Xe điện*), trạng thái (*Đang cho thuê* / *Liên hệ* / *Ẩn*).
3. Ảnh: bấm **Chọn ảnh** → tải ảnh xe thật (ảnh ngang 3:2, tối thiểu 1600 px). Không có ảnh thì website hiện biển số đồ họa.
4. Mô tả ngắn cho từng ngôn ngữ (có thể để trống ngôn ngữ chưa viết — khi đó dùng mô tả tiếng Anh/tiếng Việt).
5. **Lưu**. Giá của xe này sửa ở **Bảng giá**.

### 2.4 Sửa chữ trên một trang (ví dụ trang Bảng giá tiếng Anh)
1. **Pages → All Pages**. Trên bảng có 5 cột VI · EN · DE · FR · ES. Bấm ✅ ở cột EN của trang cần sửa.
2. Sửa như mọi trang WordPress (Gutenberg). Không xóa các khối *Mr Tu* (bảng giá, dòng xe, thẻ liên hệ, nút gọi) — chúng tự lấy số liệu.
3. Hộp **SEO Mr Tu** bên phải: *Tiêu đề tìm kiếm* (≤ 60 ký tự), *Mô tả* (140–158), *Từ khóa chính*. Bộ đếm ký tự đổi màu đỏ khi quá dài.
4. Hộp **Ngôn ngữ & bản dịch**: thấy trang này nối với bản nào ở ngôn ngữ khác; bấm ➕ để tạo bản dịch còn thiếu.
5. **Update**. Kiểm tra lại trang ngoài site bằng nút *View page*.

### 2.5 Viết bài cẩm nang mới
1. **Posts → Add New**. Trong hộp **Ngôn ngữ & bản dịch** chọn ngôn ngữ của bài (mặc định Tiếng Việt).
2. Chọn chuyên mục đúng ngôn ngữ (ví dụ *Kinh nghiệm* cho VI, *Riding tips* cho EN).
3. Viết bài với tiêu đề H2/H3 rõ ràng — mục lục tự hiện khi bài có từ 3 tiêu đề và đủ dài.
4. Đăng. Muốn có bản tiếng Anh: ở danh sách Posts bấm ➕ cột EN → chọn *Sao chép cấu trúc* → viết lại bằng tiếng Anh → Publish.
   Hệ thống **không cho đăng** bản dịch còn y nguyên nội dung tiếng Việt.

### 2.6 Tạo bản dịch còn thiếu
- **Mr Tu → Bản dịch** hiện bảng *vai trò trang × ngôn ngữ*. Ô ➕ = chưa có. Bấm ➕ → bản nháp được tạo và nối đúng nhóm.
- Nhóm dịch bị lệch (ví dụ đổi nhầm) → **Bảo trì → Dựng lại bảng bản dịch**.

## 3. Kiểm tra sức khỏe & SEO

### 3.1 Trước khi đăng nội dung quan trọng
**Mr Tu → Kiểm tra sức khỏe → Chạy kiểm tra**. Đọc các dòng đỏ trước:
- *Thiếu bản dịch*: trang chưa có đủ 5 ngôn ngữ (không bắt buộc, nhưng switcher sẽ đưa khách về trang chủ ngôn ngữ đó).
- *Trùng tiêu đề / mô tả / từ khóa*: hai trang cùng ngôn ngữ tranh nhau một từ khóa.
- *Liên kết hỏng*: link trong bài trỏ tới trang không tồn tại.
- *Dữ liệu chưa xác minh*: nhắc bật *Đã xác minh* khi đã chắc.

### 3.2 SEO
- **Chế độ**: *Tự động* (mặc định) = khi Yoast đang bật thì dùng *Tương thích Yoast*: Yoast xuất title/description/schema, Mr Tu thêm
  hreflang. *Độc lập* = Mr Tu xuất toàn bộ (chỉ chuyển khi đã quyết định tắt các tính năng trùng của Yoast).
- **Kiểm tra head trực tiếp**: bấm để xem mỗi trang chủ ngôn ngữ có đúng 1 canonical, 1 description, 1 robots, đủ hreflang.
- **Chuyển hướng**: khi đổi slug trang đã đăng, hệ thống tự tạo chuyển hướng 301 từ link cũ. Có thể thêm/xóa thủ công.
- **Mục lục**: số tiêu đề/từ tối thiểu, vị trí (sau H2 đầu tiên), loại bài áp dụng.

### 3.3 Google
- Google Search Console: gửi sitemap (ở chế độ Yoast: `/sitemap_index.xml`; chế độ độc lập: `/mrtu-sitemap-index.xml`).
- Google Business Profile: đổi link website về `https://hanoi.thuexemaynguyentu.com/`, giữ tên/địa chỉ/số điện thoại **giống hệt**
  Thông tin cửa hàng.

## 4. Giao diện (theme)

**Mr Tu → Giao diện**: ảnh hero (ảnh xe thật, ngang), ảnh câu chuyện Phố Cổ, bật/tắt hiệu ứng xuất hiện khi cuộn, bật/tắt hiệu ứng
chuyển trang, chế độ màu mặc định (*Theo hệ thống* khuyến nghị). Chưa có ảnh thật thì website dùng đồ họa cột cây số/biển số — không
bao giờ dùng ảnh giả cửa hàng.

Logo riêng: **Appearance → Customize → Site Identity → Logo** (SVG/PNG nền trong, cao ≥ 80 px).

## 5. Chatbot (Trợ lý Mr Tu)

- Trả lời khách dựa trên **dữ liệu đã xác minh** và **bài đã đăng**; không gọi dịch vụ ngoài, không tốn phí.
- **Mr Tu → Chatbot**: sửa lời chào từng ngôn ngữ; thêm cặp *Câu hỏi – Trả lời* ưu tiên (ví dụ chính sách riêng của Mr Tu); xem
  *Câu chưa trả lời được* (ẩn danh, giữ 30 ngày) để bổ sung nội dung; **Tạo lại chỉ mục** sau khi sửa nhiều bài.
- Thử trong *Bảng kiểm tra* trước khi tin tưởng câu trả lời.

## 6. Khi gặp lỗi

| Hiện tượng | Làm gì |
|---|---|
| Trang chủ không đổi sau khi lưu | LiteSpeed Cache → Purge All; thử trình duyệt ẩn danh |
| Nút Gọi không hiện | Thông tin cửa hàng → số điện thoại phải *Đã xác minh*; Quick Call → nút Gọi bật |
| Giá hiện "Liên hệ báo giá" | Bảng giá chưa *Đã xác minh* (hoặc chưa bật hiển thị giá tham khảo) |
| Trang tiếng Anh ra 404 | Settings → Permalinks → Save; Mr Tu → Ngôn ngữ → tiếng Anh phải đang bật |
| Website lỗi trắng | Xem `docs/rollback.md` mức 1–2 (đổi theme/tắt plugin), site không mất dữ liệu |

Lưu ý bảo mật: không chia sẻ tài khoản admin; đổi tên hiển thị của tài khoản `admin` thành tên khác (Users → Profile → Display name)
để không lộ tên đăng nhập ra ngoài.
