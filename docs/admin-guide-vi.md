# Hướng dẫn quản trị website Mr Tu (dành cho chủ cửa hàng, dùng trên iPhone)

> Đăng nhập **wp-admin** như bình thường. Mọi việc của Mr Tu nằm trong menu **Mr Tu** ở cột trái (biểu tượng cột cây số).
> Các màn hình được thiết kế cho màn hình điện thoại: nút lớn, lưu ở thanh dính phía dưới, có thông báo xanh/đỏ sau mỗi lần lưu.
> Nguyên tắc vàng: **thông tin chưa bật "Đã xác minh" sẽ không hiện ra với khách**. Website không bao giờ tự bịa giá, giờ hay địa chỉ.
> Sau khi lưu bất kỳ thay đổi nào: **LiteSpeed Cache → Toolbox → Purge All** rồi xem lại site bằng trình duyệt ẩn danh (plugin
> Mr Tu không tự xóa cache).

## 1. Menu Mr Tu có gì

| Mục (tên trong menu) | Dùng khi | Ghi chú |
|---|---|---|
| **Tổng quan** | Muốn nhìn nhanh tình trạng site, tạo nội dung mẫu, xuất cấu hình | Thẻ số liệu + cảnh báo sức khỏe + chế độ SEO |
| **Cửa hàng** (màn *Thông tin cửa hàng*) | Đổi tên hiển thị, địa chỉ, số điện thoại, giờ, Zalo/WhatsApp, link Google Maps; sao lưu & khôi phục | Mỗi trường có công tắc *Đã xác minh — tôi đã tự kiểm tra* |
| **Bảng giá** | Đổi giá ngày/tuần/tháng, tiền cọc, phí giao nhận, ngày kiểm tra giá | Có *Xem trước trên website* (giá đã lưu) |
| **Xe** (thẻ *Dòng xe* bên trong) | Thêm/sửa/ẩn xe, ảnh xe, mô tả 5 ngôn ngữ | Xe *Ẩn khỏi website* không bao giờ hiện |
| **Ngôn ngữ** | Bật/tắt ngôn ngữ, tagline theo ngôn ngữ, x-default | Tiếng Việt luôn là mặc định |
| **Bản dịch** | Xem bảng VI/EN/DE/FR/ES của từng trang, tạo bản dịch còn thiếu, bảo trì | ✅ đã đăng · ✏️ nháp · ➕ chưa có |
| **SEO** | Chế độ Yoast/độc lập, sitemap, schema, mục lục, chuyển hướng, kiểm tra head | Chỉ chỉnh khi hiểu rõ |
| **Gọi nhanh** | Bật/tắt thanh gọi nhanh và các nút *Gọi*, *WhatsApp*, *Zalo*, *Chỉ đường (Google Maps)*, *Trang liên hệ*, *Đặt câu hỏi (trợ lý Mr Tu — chỉ khi đã cài plugin Trợ lý)* | Nút chỉ hiện khi kênh tương ứng đã xác minh |
| **Giao diện** | Ảnh lớn trang chủ, ảnh câu chuyện, hiệu ứng, chế độ màu mặc định | Thuộc theme (mục con trong menu Mr Tu) |
| **Kiểm tra sức khỏe** | Trước khi đăng gì quan trọng, hoặc khi thấy site "lạ" | Mỗi dòng có liên kết *Mở* tới màn hình cần sửa; nút *Chạy lại*, *Dựng lại bản đồ bản dịch*, *Làm mới rewrite rules*, *Xóa cache sitemap*, *Tải JSON* |
| **Chatbot** | Chỉ có khi đã cài plugin Trợ lý (đợt sau) | Không thuộc đợt phát hành này |

## 2. Việc làm thường xuyên nhất

### 2.1 Đổi số điện thoại / địa chỉ / giờ phục vụ
1. **Mr Tu → Cửa hàng**.
2. Sửa ô cần đổi. Số điện thoại gồm *số hiển thị* (ví dụ `0816 659 199`) và *số quốc tế* (`+84816659199`, hệ thống gợi ý sẵn).
3. Bật **Đã xác minh — tôi đã tự kiểm tra** ở trường đó nếu thông tin đúng thực tế. Chưa chắc → để tắt: địa chỉ chưa xác minh
   thì website chỉ ghi "Phố Cổ, Hà Nội" và mời khách gọi; số điện thoại chưa xác minh thì **không có nút Gọi**; giờ chưa xác
   minh thì không hiện giờ.
4. Bấm **Lưu thay đổi**. Toàn bộ 5 ngôn ngữ, footer, trang liên hệ, nút Gọi cập nhật. Sau đó **LiteSpeed Cache → Toolbox →
   Purge All** và kiểm tra bằng trình duyệt ẩn danh.

Mỗi lần lưu, bản cũ được giữ trong **Lịch sử** (20 bản). Lỡ tay → **Cửa hàng → Sao lưu & khôi phục → Lịch sử → Khôi phục**.
Nên tải một bản **Tải bản sao lưu (JSON)** trước những thay đổi lớn; nhập lại bằng *Nhập tệp sao lưu*.

### 2.2 Đổi giá thuê / tiền cọc
1. **Mr Tu → Bảng giá**. Nhập giá VNĐ (chỉ số, không dấu chấm). Ô *từ–đến* cho khoảng giá (ví dụ tháng 900000–1200000).
2. Để trống ô = "Liên hệ báo giá".
3. Bật **Giá đã xác nhận cho Mr Tu** (và điền *Ngày bạn kiểm tra giá lần cuối*) khi giá đúng tại điểm Mr Tu, rồi bật
   **Giá đã xác nhận cho dòng xe này** ở từng xe — giá chỉ hiện khi **cả hai** công tắc bật. Chưa bật → website hiện "giá tham
   khảo, xác nhận trước khi đặt" nếu bật *Hiện giá tham khảo khi chưa xác nhận*, còn không thì hiện "Liên hệ báo giá".
4. Bấm **Lưu thay đổi**, rồi kéo xuống thẻ **Xem trước trên website** để kiểm tra đúng như khách thấy (phần xem trước chỉ hiện
   giá **đã lưu**). Purge All cache.

### 2.3 Thêm hoặc ẩn một dòng xe
1. **Mr Tu → Xe → Thêm xe** (hoặc bấm *Sửa* ở xe có sẵn).
2. Điền tên (ví dụ *Honda Vision*), loại (*Xe số* / *Xe tay ga* / *Xe tay ga 50cc* / *Xe điện*), trạng thái (*Có sẵn* / *Liên hệ
   hỏi xe* / *Ẩn khỏi website*).
3. Ảnh: bấm **Chọn ảnh** → tải ảnh xe thật (khuyến nghị ảnh ngang, rõ nét). Không có ảnh thì website hiện biển số đồ họa.
4. Mô tả ngắn cho từng ngôn ngữ (có thể để trống ngôn ngữ chưa viết — khi đó dùng mô tả tiếng Anh/tiếng Việt).
5. **Lưu xe**. Giá của xe này sửa ở **Bảng giá**.

### 2.4 Sửa chữ trên một trang (ví dụ trang Bảng giá tiếng Anh)
1. **Pages → All Pages**. Trên bảng có 5 cột VI · EN · DE · FR · ES. Bấm ✅ ở cột EN của trang cần sửa.
2. Sửa như mọi trang WordPress (Gutenberg). Không xóa các khối *Mr Tu* (bảng giá, dòng xe, thẻ liên hệ, nút gọi) — chúng tự lấy số liệu.
3. Hộp **SEO Mr Tu** nằm **phía dưới** khung soạn thảo: *Tiêu đề SEO* (≤ 60 ký tự, hậu tố "| Mr Tu" tự thêm), *Mô tả meta*
   (120–158 ký tự), *Từ khóa chính*. Bộ đếm ký tự đổi màu đỏ khi quá dài.
4. Hộp **Ngôn ngữ & bản dịch** (cột phải): thấy trang này nối với bản nào ở ngôn ngữ khác; bấm **+ Sao chép** hoặc **+ Trống**
   để tạo bản dịch còn thiếu.
5. **Update**. Kiểm tra lại trang ngoài site bằng nút *View page*.

### 2.5 Viết bài cẩm nang mới
1. **Posts → Add New**. Trong hộp **Ngôn ngữ & bản dịch** chọn ngôn ngữ của bài (mặc định Tiếng Việt).
2. Chọn chuyên mục đúng ngôn ngữ (ví dụ *Kinh nghiệm* cho VI, *Riding tips* cho EN).
3. Viết bài với tiêu đề H2/H3 rõ ràng — mục lục tự hiện khi bài có từ 3 tiêu đề và đủ dài.
4. Đăng. Muốn có bản tiếng Anh: ở danh sách Posts bấm ➕ ở cột EN → chọn **Sao chép nội dung gốc** (hoặc *Bắt đầu với bản nháp
   trống*) → viết lại bằng tiếng Anh → Publish. Hệ thống **không cho đăng** bản dịch còn y nguyên nội dung tiếng Việt.

### 2.6 Tạo bản dịch còn thiếu
- **Mr Tu → Bản dịch** hiện bảng *vai trò trang × ngôn ngữ*. Ô ➕ = chưa có. Bấm ➕ → bản nháp được tạo và nối đúng nhóm.
- Nhóm dịch bị lệch (ví dụ đổi nhầm) → **Mr Tu → Bản dịch → thẻ Bảo trì → Dựng lại bản đồ bản dịch** (nút này cũng có ở
  *Kiểm tra sức khỏe*).

## 3. Kiểm tra sức khỏe & SEO

### 3.1 Trước khi đăng nội dung quan trọng
**Mr Tu → Kiểm tra sức khỏe → Chạy lại**. Đọc nhóm *Lỗi* trước:
- *Mức hoàn thành bản dịch (trang chính × ngôn ngữ)*: trang chưa có đủ 5 ngôn ngữ (không bắt buộc, nhưng switcher sẽ đưa khách
  về trang chủ ngôn ngữ đó).
- *Tiêu đề SEO / Mô tả meta trùng nhau trong cùng ngôn ngữ* và *Trùng từ khóa chính*: hai trang cùng ngôn ngữ tranh nhau một
  từ khóa.
- *Liên kết nội bộ*: link trong bài trỏ tới trang không tồn tại.
- *Xác minh dữ liệu cửa hàng*: nhắc bật *Đã xác minh — tôi đã tự kiểm tra* khi đã chắc.
Mỗi dòng có liên kết **Mở** dẫn tới màn hình cần sửa.

### 3.2 SEO
- **Chế độ**: *Tự động* (mặc định) = khi Yoast đang bật thì dùng *Tương thích Yoast*: Yoast xuất title/description/schema, Mr Tu thêm
  hreflang. *Độc lập* = Mr Tu xuất toàn bộ (chỉ chuyển khi đã quyết định tắt các tính năng trùng của Yoast — xem
  `docs/deployment-hostinger.md` §2.4, phải tắt sitemap của Yoast).
- **Kiểm tra head trực tiếp → Chạy kiểm tra**: xem mỗi trang chủ ngôn ngữ có đúng 1 canonical, 1 description, 1 robots, đủ
  hreflang. Nếu máy chủ không tự gọi được (một số hosting chặn), mở `view-source:` và đếm thủ công.
- **Chuyển hướng**: khi đổi slug trang đã đăng, hệ thống tự tạo chuyển hướng 301 từ link cũ (Yoast Premium cũng làm việc này —
  giữ một bên, xem hướng dẫn triển khai §2.7). Có thể thêm/xóa thủ công.
- **Mục lục**: số tiêu đề/từ tối thiểu, vị trí (sau H2 đầu tiên), loại bài áp dụng.

### 3.3 Google
- Google Search Console: gửi sitemap (ở chế độ Yoast: `/sitemap_index.xml`; chế độ độc lập: `/mrtu-sitemap-index.xml`).
- Google Business Profile: đổi link website về `https://hanoi.thuexemaynguyentu.com/`, giữ tên/địa chỉ/số điện thoại **giống hệt**
  Thông tin cửa hàng.

## 4. Giao diện (theme)

**Mr Tu → Giao diện** (mục con trong menu Mr Tu, không phải menu *Giao diện/Appearance* của WordPress): *Ảnh lớn trang chủ* (ảnh
xe thật, ngang, ≥ 2400 px), *Ảnh câu chuyện*, bật/tắt *Hiện dần các phần khi cuộn trang*, bật/tắt *Chuyển trang mờ dần*,
*Chế độ màu mặc định* (khuyến nghị *Theo thiết bị (Hệ thống)*). Chưa có ảnh thật thì website dùng đồ họa cột cây số/biển số —
không bao giờ dùng ảnh giả cửa hàng.

Logo riêng: **Appearance → Customize → Site Identity → Logo** (SVG/PNG nền trong, cao ≥ 80 px).

## 5. Chatbot (Trợ lý Mr Tu)

> Mục này chỉ áp dụng **sau khi** cài plugin Trợ lý Mr Tu (gói riêng, chưa phát hành trong đợt này). Khi chưa cài, menu
> **Chatbot** không xuất hiện và nút *Đặt câu hỏi* trong Gọi nhanh không hiện.

- Trả lời khách dựa trên **dữ liệu đã xác minh** và **bài đã đăng**; không gọi dịch vụ ngoài, không tốn phí.
- Sửa lời chào từng ngôn ngữ; thêm cặp *Câu hỏi – Trả lời* ưu tiên; xem câu chưa trả lời được (ẩn danh) để bổ sung nội dung;
  tạo lại chỉ mục sau khi sửa nhiều bài. Thử trước khi tin tưởng câu trả lời.

## 6. Khi gặp lỗi

| Hiện tượng | Làm gì |
|---|---|
| Trang chủ không đổi sau khi lưu | LiteSpeed Cache → Toolbox → Purge All; thử trình duyệt ẩn danh |
| Nút Gọi không hiện | **Cửa hàng**: số điện thoại phải *Đã xác minh* và *Hiện nút gọi trên website* bật; **Gọi nhanh**: *Hiện nút Gọi nhanh* và nút *Gọi* bật; sau đó Purge All |
| Giá hiện "Liên hệ báo giá" | Bảng giá: chưa bật *Giá đã xác nhận cho Mr Tu* hoặc *Giá đã xác nhận cho dòng xe này* (hoặc chưa bật *Hiện giá tham khảo khi chưa xác nhận*) |
| Trang tiếng Anh ra 404 | Settings → Permalinks → Save; Mr Tu → Ngôn ngữ → tiếng Anh phải đang bật |
| Website lỗi trắng | Xem `docs/rollback.md` mức 1–2 (đổi theme/tắt plugin), site không mất dữ liệu |

Lưu ý bảo mật: không chia sẻ tài khoản admin. Đổi *Display name* chỉ che tên trên bài viết; đường dẫn `/author/admin/` và REST
API vẫn lộ tên đăng nhập. Cách an toàn: tạo tài khoản quản trị mới với tên đăng nhập khác, đăng nhập bằng tài khoản mới, rồi hạ
quyền (hoặc xóa và chuyển bài) tài khoản `admin` — làm trên staging trước.
