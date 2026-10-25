# Nghiên cứu từ khóa & SERP — Mr Tu (VI · EN · DE · FR · ES)

> **Ngày thu thập:** 25/10/2026 · **File máy đọc:** [`config/seo-plan.json`](../config/seo-plan.json) (seeder/plugin dùng `primary_kw` làm `_mrtu_focus_kw`).
> **Nguyên tắc:** mọi con số đều gắn nhãn *công cụ · thị trường · ngày*. Ô `—` nghĩa là công cụ **không trả dữ liệu**; không có số nào được ước đoán hay nội suy.
> Đây là dữ liệu nghiên cứu, **không** phải cam kết thứ hạng hay traffic.

## 0. Kết luận nhanh

1. **VI là thị trường lớn nhất, áp đảo.** “thuê xe máy hà nội” 8.100 lượt/tháng, KD 7; “cho thuê xe máy hà nội” 1.300 (OS · VN/vi · 25/10/2026). Hai cụm này có **cùng ý định** (top 10 trùng vexere/otosaigon/motorbikerhanoi, local pack giống hệt) nên đặt chung trên **pillar** `/thue-xe-may-ha-noi/`.
2. **EN: phần lớn lượt tìm diễn ra ngay tại Việt Nam.** “motorbike rental hanoi” 2.400 ở VN/en, so với 110 ở US, 70 ở UK, 70 ở AU (OR · 25/10/2026). Local pack, điện thoại di động và đánh giá Google Business Profile (GBP) vì vậy quan trọng hơn thứ hạng tại US. Mùa cao điểm VN/en: 2.900–3.600 (tháng 9–11); thấp nhất 1.300 (tháng 6–7) (OR, trend 12 tháng).
3. **DE/FR/ES có nhu cầu rất nhỏ cho Hà Nội:** DE 30 (“motorrad mieten hanoi”), FR 50 (“location moto hanoi”), ES 10 (“alquiler moto hanoi”) (OS · 25/10/2026). Mexico gần như bằng 0 (OR · MX/es). Tuy nhiên SERP ba ngôn ngữ này **gần như không có trang cho thuê xe viết bằng chính ngôn ngữ đó**: DE toàn trang tiếng Anh; ES có cả trang tiếng Ý, tiếng Pháp. Trang bản ngữ chất lượng gặp ít cạnh tranh nhưng sẽ không mang lại traffic lớn.
4. **Câu hỏi bằng lái/giấy tờ là cơ hội nội dung ở cả 5 ngôn ngữ:** đều có AI Overview và PAA. Đối thủ stylemotorbikes.com đang được AI Overview trích dẫn nhờ bài luật viết bằng tiếng Đức và tiếng Pháp. Trang `guide-faq` có nguồn chính thống (Đại sứ quán Đức/Pháp đều đang xếp hạng) có thể giành được trích dẫn này.
5. **GBP của Mr Tu đã hiện trên SERP:** local pack cho “thuê xe máy phố cổ” hiện listing **“Cho thuê xe máy Phố Cổ - Mr Tú”**, 0816 659 199, “Không có đánh giá nào”, website trỏ tới `thuexemayhanoi.github.io/shop/` (OS-SERP · VN/vi · 25/10/2026). Điều này giúp làm rõ tên GBP đang bị cắt trong README §1.1, nhưng **chủ site vẫn phải xác minh trong GBP**. Sau khi ra mắt, chủ site cần đổi link website của GBP sang domain mới và tích lũy đánh giá thật.
6. **Rủi ro chồng lấn với site cùng hệ:** `blog.thuexemaynguyentu.com` (Nguyễn Tú, Long Biên) đang xếp #9 cho “giá thuê xe máy hà nội” và #4 cho “thuê xe máy hoàn kiếm”. Tripadvisor “Nguyen Tu” cũng có mặt trên SERP EN “monthly”. Site này cùng registrable domain với `hanoi.thuexemaynguyentu.com`, nên cần phối hợp nội dung để không tự cạnh tranh (xem §10).
7. **Ý định “không cọc” có thật** (“thuê xe máy hà nội không cọc” 90, OR · VN/vi), và đối thủ quảng cáo “miễn cọc/không cần đặt cọc” (motogo, hanoibiker). Mr Tu **không** hứa điều này. FAQ cần trả lời minh bạch về đặt cọc.
8. **Hai từ dễ gây nhầm:** “thuê xe máy phố cổ” bị lẫn với Hội An, Ninh Bình, Hoa Lư (UB-AC), nên luôn ghép thêm “Hà Nội”. “xe ga” bị lẫn với “Ga Hà Nội” (vexere xếp hạng với trang “Thuê xe máy ở Ga Hà Nội”), nên dùng “xe tay ga”.

## 1. Công cụ, trạng thái và chi phí

| Công cụ | Trạng thái 25/10/2026 | Dùng cho | Chi phí / ghi chú |
|---|---|---|---|
| **OpenSEO** `get_keyword_metrics` (**OS**) | ✅ chạy được | Volume, KD, CPC, intent: VN/vi, US/en, DE/de, FR/fr, ES/es | 5 lệnh. Tổng OpenSEO đã dùng **102 credit** (108 → 6), thấp hơn nhiều so với trần 2.000. Số dư còn rất ít. |
| **OpenSEO** `get_serp_results` (**OS-SERP**) | ✅ | Top 10 SERP desktop VN/vi (7 truy vấn) | khoảng 3 credit/truy vấn ở depth 10 |
| **OpenRush** `research_keywords` / `inspect_keyword` (**OR**) | ✅ | Gợi ý + volume VN/vi, VN/en, US, UK, AU, MX | Nguồn `search_index`, độ tin cậy 0,7. Các biến thể gần nghĩa **dùng chung một volume nhóm, không được cộng dồn**. Khoảng 29 credit theo bảng giá của công cụ; số dư không hiển thị. |
| **OpenRush** `inspect_serp` (**OR-SERP**) | ✅ | SERP live + tính năng SERP (AI Overview, local pack, PAA) cho EN/DE/FR/ES và VI theo tháng | 11 lệnh, khoảng 22 credit |
| **Ubersuggest** `keyword_overview` | ❌ HTTP 403 | — | Gói free đã dùng hết hạn mức 3 báo cáo/ngày, nên **không có số liệu volume từ Ubersuggest** |
| **Ubersuggest** `google_suggestions` (**UB-AC**) | ✅ | Autocomplete (không có số) vn/vi, us/en, de, fr, es | Không tạo project |
| **Nimble** `nimble_search` (**NM**) | ✅ | Kiểm tra SERP ES “casco antiguo” | 1 lệnh |
| **TinyFish** `search` (**TF**) | ✅ | Kiểm tra SERP FR “vieux quartier” | 1 lệnh |
| **WebSearch** (**WS**) | ✅ (chỉ US) | Kiểm tra định tính “scooter rental hanoi old quarter” | 1 lệnh |
| Firecrawl | ⏭️ bỏ qua | — | Hết credit, theo chỉ dẫn |

- **Không** tạo project, tracker, lịch chạy hay kiểm tra định kỳ nào, và không có thao tác nào cần thanh toán. Các lệnh OpenSEO chạy trong hai project sẵn có của tài khoản (`thuexemaynguyentu.com` vi và `en.rentbikehanoi.com` en). Hai project này chỉ được dùng làm phạm vi tính credit, với lệnh chỉ đọc.
- Volume là lượt tìm trung bình mỗi tháng trên Google. KD là độ khó 0–100, chỉ OS cung cấp. CPC tính bằng USD.
- Mọi số liệu VI trùng giữa OS và OR đều khớp nhau (8.100 / 1.300 / 110 / 70), có lẽ do cùng nguồn Google Ads.
- Ở thị trường nhỏ, OS thường không trả số cho từ khóa dài (danh sách ở Phụ lục).

---

## 2. VI — Việt Nam, tiếng Việt

### 2.1 Số liệu từ khóa

| Keyword | Vol (OS · VN/vi · 25/10/2026) | KD (OS) | CPC USD (OS) | Intent (OS) | Vol (OR · VN/vi · 25/10/2026) | Vai trò đề xuất |
|---|---:|---:|---:|---|---:|---|
| thuê xe máy | 33.100 | 11 | 0,18 | transactional | — | quá rộng (toàn quốc), không gán |
| cho thuê xe máy | 8.100 | 0 | 0,30 | commercial | — | quá rộng, không gán |
| **thuê xe máy hà nội** | **8.100** | 7 | 0,19 | commercial | 8.100 | **pillar** (primary) |
| thuê xe máy gần đây | 4.400 | 21 | 0,22 | commercial | — | ý định “gần tôi”, phục vụ bằng GBP/local pack |
| cho thuê xe máy hà nội | 1.300 | 7 | 0,17 | commercial | 1.300 | pillar (phụ) |
| thuê xe máy ở hà nội | — | — | — | — | 1.000 | pillar (phụ) |
| thuê xe máy điện hà nội / … vinfast hà nội | — | — | — | — | 260 / 260 | blog (xe điện không mặc định cho thuê) |
| thuê xe máy tại hà nội | — | — | — | — | 140 | pillar (phụ) |
| thuê xe máy tây hồ hà nội | — | — | — | — | 140 | không tạo trang (khu vực chưa xác minh) |
| **giá thuê xe máy hà nội** | **110** | 46 | 0,16 | commercial | 110 | **pricing** (primary) |
| thuê xe máy 50cc hà nội | 110 | — | 0,26 | transactional | 110 | chỉ dùng khi có xe 50cc thật |
| thuê xe máy hoàn kiếm hà nội | — | — | — | — | 90 | old-quarter (phụ) |
| thuê xe máy hà nội giá | — | — | — | — | 90 | pricing (phụ) |
| thuê xe máy hà nội không cọc | — | — | — | — | 90 | **chỉ để hiểu ý định, không hứa** |
| **thuê xe máy phố cổ hà nội** | **70** | — | 0,17 | transactional | 70 | **old-quarter** (primary) |
| **thuê xe máy theo tháng hà nội** | **70** | — | 0,20 | transactional | 70 | **monthly** (primary) |
| thuê xe máy hà nội giá rẻ | 70 | 46 | 0,14 | transactional | 70 | pricing (phụ; không dùng “rẻ nhất”) |
| thuê xe máy tự lái hà nội | 70 | — | 0,12 | transactional | 70 | pillar (phụ) |
| thuê xe máy hà nội giá bao nhiêu | — | — | — | — | 50 | pricing (phụ) |
| thuê xe máy hà nội giao tận nơi | — | — | — | — | 50 | chỉ dùng khi đã xác minh có giao xe |
| dịch vụ thuê xe máy hà nội | — | — | — | — | 50 | pillar (phụ) |
| **thuê xe máy cần giấy tờ gì** | **30** | — | 0,22 | informational | — | **guide-faq** (primary) |
| thủ tục thuê xe máy | 30 | 50 | 0,41 | transactional | — | guide-faq (phụ) |
| thuê xe máy sh hà nội | — | — | — | — | 30 | không có SH trong danh mục |
| thuê xe máy hà nội hoàn kiếm | — | — | — | — | 30 | old-quarter (phụ) |
| **thuê xe máy hà nội theo ngày** | — | — | — | — | **20** | **short-term** (primary) |
| thuê xe máy hà nội phố cổ / … theo tháng | — | — | — | — | 20 / 20 | old-quarter / monthly (phụ) |
| thuê xe máy ga hà nội | — | — | — | — | 20 | dễ nhầm với “Ga Hà Nội”, nên dùng “xe tay ga” |
| bảng giá thuê xe máy hà nội | 10 | — | 0,32 | commercial | 10 | pricing (phụ) |
| thuê xe máy tháng hà nội | 10 | — | 0,33 | transactional | 10 | monthly (phụ) |
| thủ tục thuê xe máy hà nội | — | — | — | — | 10 | guide-faq (phụ) |
| thuê xe máy không cần đặt cọc | 10 | — | — | transactional | — | chỉ để hiểu ý định |
| thuê xe máy hà nội theo giờ | — | — | — | — | 10 | chỉ dùng khi thật sự có thuê theo giờ |

**Autocomplete (UB-AC · vn/vi · 25/10/2026, không có số):**
- “thuê xe máy hà nội …”: giá, giá rẻ, gần đây, theo ngày, không cọc, theo tháng, mỹ đình, giao tận nơi, theo giờ, phố cổ, cầu giấy, hoàn kiếm, **không cần bằng lái**, 50cc.
- “thuê xe máy cần …”: gì, giấy tờ gì, thủ tục gì, cọc gì, **bằng lái không**, điều kiện gì, **cọc bao nhiêu**, lưu ý những gì.
- “thuê xe máy phố cổ …”: **hoa lư, hội an, ninh bình** (dễ nhầm), gần phố cổ, ở phố cổ hà nội.

### 2.2 SERP VI (OS-SERP desktop top 10 · VN/vi · 25/10/2026; dòng “theo tháng” lấy từ OR-SERP)

| Truy vấn | Tính năng SERP | Top kết quả (domain · loại trang) |
|---|---|---|
| thuê xe máy hà nội | AI Overview · local pack (3) | vexere.com (aggregator, “Bảng giá tháng 10/2026”) · luhanhvietnam.com.vn (listicle) · nhóm Facebook · otosaigon.com (forum) · baohatinh.vn (bài PR) · motorbikerhanoi.com (trang chủ shop) |
| cho thuê xe máy hà nội | local pack đứng đầu | vexere · justfly.vn (listicle) · otosaigon · 2 nhóm FB · baophutho.vn (PR) · motorbikerhanoi.com |
| thuê xe máy phố cổ | AI Overview · local pack (**có Mr Tú**) | motogo.vn/thue-xe-may-pho-co-ha-noi/ (landing của shop) · thuexemayhaiphong.com (listicle Hoàn Kiếm) · vexere Hoàn Kiếm · FB · mia.vn (cẩm nang) · giahuy.org (listicle) |
| thuê xe máy hoàn kiếm | AI Overview · local pack | chothuexemayohanoi.github.io/vanchinh/ (trang shop) · 2 nhóm FB · **blog.thuexemaynguyentu.com** (#4, bài “gần phố cổ”) · baohatinh (PR) |
| giá thuê xe máy hà nội | AI Overview · không local pack | mototrip.vn (landing có giá trong snippet) · FB · otosaigon · motorbikerhanoi · himoto.vn/bang-gia-… (trang bảng giá riêng) · **blog.thuexemaynguyentu.com** (#9) |
| thuê xe máy theo tháng hà nội | AI Overview (trích himoto, vinwonders, hanoibiker, mototrip) · related | hanoibiker.com/thue-xe-may-theo-thang-dai-han/ (landing riêng) · FB page · motogo bảng giá · mototrip · vinwonders · chothuexenambinh · vinpearl (listicle) |
| thuê xe máy cần giấy tờ gì | AI Overview · PAA | motogo.vn/thu-tuc-thue-xe-may/ (cẩm nang) · FB (Sài Gòn) · chothuexenambinh (Đà Nẵng) · FB chothuexemaynguyentu · dibao · chothuexemaytphcm01 · vinwonders · Tripadvisor FAQ |
| thuê xe tay ga hà nội | local pack · không có AI Overview | motogo.vn/ha-noi/ · FB · hanoibiker.com · vinpearl · mototrip.vn · vinwonders. Chỉ có trang thuê xe chung, không có trang riêng cho “xe tay ga” |

**Mẫu title đối thủ:** “Thuê xe máy Hà Nội – [USP]”, với USP như “Giao xe tận nơi trong 15 phút”, “Xe mới 100%”, “MIỄN CỌC”; ngoài ra có “Bảng giá … 2026” và “Top N địa chỉ …”. Mr Tu **không** sao chép các USP chưa xác minh.

### 2.3 Ý định & nhận định

- **Head term mang ý định thương mại cục bộ.** Local pack và AI Overview chiếm phía trên. Top organic chủ yếu là aggregator, listicle, nhóm FB và bài PR; số trang shop thật ít. KD thấp (7, OS). Một trang dịch vụ tốt cùng GBP có đánh giá thật hoàn toàn có thể cạnh tranh.
- **Phố Cổ và Hoàn Kiếm là một cụm ý định:** trang Hoàn Kiếm của vexere xếp hạng cho “phố cổ”. Vì vậy đặt cả hai vào một trang (`old-quarter`).
- **Theo tháng** có landing riêng xếp #1 (hanoibiker), đủ cơ sở để làm trang `monthly` riêng.
- **Giấy tờ** là truy vấn thông tin trên phạm vi toàn quốc (SERP lẫn kết quả Sài Gòn, Đà Nẵng). Trang hướng dẫn cụ thể cho Hà Nội có lợi thế.

---

## 3. EN — tiếng Anh (US, UK, AU, Việt Nam)

### 3.1 Số liệu từ khóa

| Keyword | VN/en (OR) | US (OS) | KD US (OS) | US (OR) | UK (OR) | AU (OR) | CPC USD | Vai trò |
|---|---:|---:|---:|---:|---:|---:|---|---|
| **motorbike rental hanoi** | **2.400** | 110 | 13 | 110 | 70¹ | 70 | 0,41 (OR VN) · 0,33 (OS US) | **pillar** |
| hanoi motorbike rental | 2.400 | 110 | 10 | 110 | 70¹ | 70 | 0,41 (OR VN) | pillar (phụ) |
| rent motorbike hanoi | — | 110 | 13 | — | 70¹ | — | 0,33 (OS US) | pillar (phụ) |
| motorcycle rental hanoi | — | 110 | 10 | — | — | — | 0,25 (OS US) | pillar (phụ) |
| scooter rental hanoi / hanoi scooter rental | 210 | 30 | 3 / 0 | — | — | — | 0,48 (OR VN, cạnh tranh HIGH) | pillar (phụ) |
| motorbike hire hanoi | — | 10 | — | — | 30 | — | 0,24 (OR UK) | pillar (phụ) |
| **motorbike rental hanoi old quarter** | **30** | 10 | — | 10 | — | 10 | 0,25 (OR VN) | **old-quarter** |
| scooter rental hanoi old quarter | 10 | 10 | — | — | — | — | 0,12 (OR VN) | old-quarter (phụ) |
| **hanoi motorbike rental cost** | **30** | — | — | 10 | — | 10 | 0,56 (OR VN) | **pricing** |
| motorbike rental hanoi price | 10 | 10 | — | 10 | 10 | — | — | pricing (phụ) |
| 50cc scooter rental hanoi | 20 | 10 | — | — | — | — | 0,27 (OR VN) | chỉ dùng khi có xe 50cc |
| motorbike rental hanoi near me | 20 | — | — | 0 | — | — | 0,20 (OR VN) | GBP/local pack |
| international driving permit vietnam | — | 110 | 4 | — | — | — | 4,29 (OS US) | guide-faq (phụ) |
| motorbike license vietnam | — | 40 | 0 | — | — | — | 1,68 (OS US) | guide-faq (phụ) |
| motorbike rental vietnam | — | 320 | 20 | — | — | — | 0,28 (OS US) | phạm vi cả nước; nhắc tới trong pillar/blog |
| scooter rental vietnam | — | 90 | 0 | — | — | — | 0,55 (OS US) | blog |
| hanoi motorbike tour(s) | — | — | — | — | 30 | — | 1,50 (OR UK, HIGH) | không cung cấp tour, không gán |

¹ Với UK, OR gộp khoảng 10 biến thể (motorbike rental hanoi, rent motorbike hanoi, renting a motorbike in hanoi, motorbike for rent hanoi, …) vào **một** con số 70. Không được cộng dồn.
Tất cả số liệu lấy ngày 25/10/2026.

**Mùa vụ (OR trend 12 tháng):** VN/en dao động 1.300 (6–7/2026) đến 3.600 (11/2025); 9/2026 đạt 2.900. US dao động 40 (6/2026) đến 210 (1–2/2026). AU 50–110, cao nhất 9–12/2025. Nên hoàn tất nội dung EN và tối ưu GBP **trước tháng 9**.

**Autocomplete (UB-AC · us/en):**
- Biến thể: old quarter, near me, airport, price, to ho chi minh / to hoi an, one way, reddit, monthly, 50cc, electric/vinfast, vespa.
- Câu hỏi: *how much is motorbike rental in hanoi*, *how much to rent a motorbike in vietnam (for a month)*, *can i rent a motorbike in vietnam (without license)*, *can a tourist/foreigner rent a motorbike in vietnam*, *what do you need to rent a motorbike in vietnam*, *how old to rent…*, *is it safe to rent…*

### 3.2 SERP EN

| Truy vấn (thị trường) | Tính năng | Top kết quả & nhận xét |
|---|---|---|
| motorbike rental hanoi (OR-SERP · VN/en) | AI Overview (bất đồng bộ) · local pack · PAA · related | Local pack: Rental Motorbike Vietnam (5,0★/1.100 đánh giá), 13535 Motorbike Rental (4,8★/2.100), Rental Motorbike Hanoi (5,0★/174). Organic: rentalmotorbikevietnam.com/contact-us/ · Tripadvisor (Nguyen Ha) · motorvina blog · nhóm FB · tripadvisor.co.uk · travelchinaguide (guide) · FB Happy Zip · Tripadvisor Tuan |
| motorbike rental hanoi (OR inspect_keyword · US) | local pack · PAA · related | tigitmotorbikes · rentalmotorbikehanoi · Tripadvisor 13535 · reddit · jamesmotorbikes · stylemotorbikes · FB haibanhgarage · rentalmotorbikevietnam · theextramile |
| monthly motorbike rental hanoi (OR-SERP · VN/en) | local pack · PAA · không có AI Overview | **phungmotorbike.com/monthly-rentals-hanoi** (trang riêng, #1) · FB · rentalmotorbikehanoi · vinwonders · theextramile · Tripadvisor “Nguyen Tu” (Long Biên, **khác địa điểm**) · rentabikevn · **happyzipmotorbikes …/monthly-rental/** (“for Expats”) · jamesmotorbikes |
| motorbike rental hanoi old quarter (OR-SERP · VN/en) | chỉ local pack | phungmotorbike (Long Biên) · Tripadvisor · FB · happyzipmotorbikes (Hoàn Kiếm) · expat.com (forum 2018) · FB · joytime guide · rentalmotorbikehanoi · Tripadvisor 13535. **Không có landing riêng cho “Old Quarter”, đây là cơ hội.** |
| do i need a license to rent a motorbike in vietnam (OR-SERP · US) | AI Overview · PAA · related | Chủ yếu UGC (FB, forum Tripadvisor 2015/2023, reddit) và guide (rentabikevn “2026 Legal Guide”, danangbike, rentalmotorbikevietnam 2019) |
| scooter rental hanoi old quarter (WS · US) | — | offroadvietnam (automatic scooters) · expat.com · bikesbooking · riderly · travelarbitrage |

**PAA đã thấy:**
- *How much is a motorcycle rental in Vietnam?* · *Can tourists drive motorcycles in Vietnam?* · *What is the most popular motorcycle in Vietnam?*
- *How much to rent a motorbike in Vietnam for a month?* · *Can I rent a bike for a few months?* · *Can foreigners rent bikes in Vietnam?*
- *Do you need a motorbike license to drive in Vietnam?* · *How old do I have to be to ride a motorcycle in Vietnam?*

**Related:** motorbike rental hanoi airport / prices / electric; monthly bike rental vietnam; motorbike license for foreigners in vietnam; temporary vietnamese motorcycle licence; a1 license vietnam.

---

## 4. DE — Đức

| Keyword | Vol (OS · DE/de · 25/10/2026) | KD | CPC USD | Intent | Vai trò |
|---|---:|---:|---:|---|---|
| motorradtour vietnam | 260 | 0 | 0,94 | informational | không cung cấp tour, không gán |
| motorrad mieten vietnam | 140 | 0 | 0,67 | transactional | pillar (phụ, chỉ hứa nhận xe ở Hà Nội) |
| internationaler führerschein vietnam | 110 | 0 | — | informational | guide-faq (phụ) |
| roller mieten vietnam | 50 | 0 | — | commercial | pillar (phụ) |
| **motorrad mieten hanoi** | **30** | — | 0,12 | transactional | **pillar** |
| roller fahren vietnam | 30 | — | — | informational | guide-faq (phụ) |
| **roller mieten vietnam führerschein** | **20** | — | — | transactional | **guide-faq** |
| führerschein vietnam roller | 10 | — | — | informational | guide-faq (phụ) |
| roller mieten hanoi | 10 | — | 0,17 | commercial | pillar (phụ) |
| roller mieten hoi an | 10 | — | — | transactional | không liên quan |
| vietnam mit dem roller | 10 | 19 | — | informational | blog |

- **Autocomplete (UB-AC · de):** hanoi roller mieten kosten · roller mieten vietnam preise/kosten/führerschein/**ohne führerschein**/versicherung · 50ccm roller mieten vietnam · vietnam roller mieten (ohne) internationalen führerschein · was kostet roller mieten in vietnam · motorrad ausleihen hanoi · motorrad verleih hanoi · moped mieten hanoi.
- **SERP “motorrad mieten hanoi” (OR-SERP · DE · 25/10/2026):** có AI Overview (bất đồng bộ), local pack (RMV 5,0★/1.134; Tang – Hanoi Motorbike 4,7★/259; 13535 4,8★/2.077) và related. Toàn bộ organic là **trang tiếng Anh**, ngoại trừ tripadvisor.de. Chưa có trang shop tiếng Đức nào.
- **SERP “roller mieten vietnam führerschein”:** AI Overview trích dẫn stylemotorbikes.com (bài tiếng Đức), neario.de, YouTube, reddit và trang Führerschein của Auswärtiges Amt (vietnam.diplo.de). Có thêm PAA và video. Organic gồm reddit (bản dịch máy), mitunsaufreisen.de, globetrotter.de, stylemotorbikes, whatsnextreisen (2020, thông tin cũ), neario.de “Roller mieten Vietnam 2026”.
  - PAA: *Welcher Führerschein für Roller in Vietnam?* · *Kann man in Vietnam einen Roller mieten ohne Führerschein?* · *Kann ich mit deutschem Führerschein in Vietnam fahren?*
- OS không trả số cho: roller mieten hanoi altstadt, langzeitmiete roller hanoi, roller monatlich mieten vietnam, motorroller/scooter/moped mieten hanoi, roller fahren hanoi, … (xem Phụ lục).

---

## 5. FR — Pháp

| Keyword | Vol (OS · FR/fr · 25/10/2026) | KD | CPC USD | Intent | Vai trò |
|---|---:|---:|---:|---|---|
| moto vietnam | 260 | 0 | 2,26 | informational | blog |
| conduire au vietnam | 110 | 0 | 0,37 | informational | guide-faq (phụ) |
| location moto vietnam | 90 | 0 | 0,45 | commercial | pillar (phụ) |
| permis international vietnam | 90 | 10 | — | informational | guide-faq (phụ) |
| location scooter vietnam | 70 | 0 | 0,65 | navigational | blog |
| **location moto hanoi** | **50** | — | 0,40 | commercial | **pillar** |
| location scooter hanoi | 40 | — | 0,26 | transactional | pillar (phụ) |
| louer une moto au vietnam | 30 | — | 0,70 | transactional | pillar (phụ) |
| **permis moto vietnam** | **20** | — | — | informational | **guide-faq** |
| permis de conduire vietnam | 20 | — | — | informational | guide-faq (phụ) |
| **location scooter hanoi prix** | **10** | — | 0,19 | commercial | **pricing** |
| louer moto hanoi | 10 | — | 0,43 | transactional | pillar (phụ) |

- **Autocomplete (UB-AC · fr):** prix location moto hanoi · location moto hanoï · location de moto à hanoï · location moto ha giang (loop) · louer scooter vietnam **sans permis** / permis / prix · comment louer un scooter au vietnam · conditions pour louer un scooter au vietnam · louer scooter 50cc vietnam.
- **SERP “location moto hanoi” (OR-SERP · FR · 25/10/2026):** có AI Overview (bất đồng bộ), local pack (RMV; 13535; Vivumotor 4,9★/1.200), PAA (*Comment louer une moto au Vietnam ?*) và related. Organic gồm fairmoove.fr (guide), rentalmotorbikevietnam (tiếng Anh), vietnam.vn/fr, tripadvisor.ch, aventure-vietnam, worldwildbrice, petitfute ×2, riderly/fr, tripadvisor.fr. Đa số là **guide và danh bạ**; không có landing shop tiếng Pháp.
- **SERP “permis moto vietnam”:** AI Overview do stylemotorbikes (bài tiếng Pháp) chiếm. **vn.diplomatie.gouv.fr** (trang chính thức) xếp #3. Có PAA.
  - PAA: *Est-il possible de conduire au Vietnam avec un permis français ?* · *Est-ce que je peux louer un scooter au Vietnam avec un permis de conduire ?*
- **TF (FR) “location moto vieux quartier Hanoï”:** riderly, bikesbooking, Tripadvisor, rentalmotorbike.com/fr, vietnamdragontravel, reddit (bản dịch máy), lmr-alsace, petitfute, fairmoove, TikTok. Không có trang “vieux quartier” riêng.

---

## 6. ES — Tây Ban Nha (Spain) & Mexico

| Keyword | Vol (OS · ES/es · 25/10/2026) | CPC USD | Intent | Vai trò |
|---|---:|---:|---|---|
| alquilar moto vietnam | 40 | 0,70 | transactional | pillar (phụ) |
| alquiler moto vietnam | 40 | 0,70 | transactional | pillar (phụ) |
| **alquiler moto hanoi** | **10** | 0,49 | commercial | **pillar** |
| alquilar moto en hanoi | 10 | 0,84 | transactional | pillar (phụ) |
| conducir moto en vietnam | 10 | 0,52 | informational | guide-faq (phụ) |
| alquiler motos hanoi | — (null) | — | commercial | — |

- **Mexico (OR · MX/es · 25/10/2026):** “viaje en moto vietnam” 10, “alquilar moto vietnam” 0, các biến thể khác null. Nhu cầu gần như bằng 0, nên **không cần phiên bản es-MX riêng**.
- **SERP “alquiler moto hanoi” (OR-SERP · ES):** **không có local pack**. Có AI Overview (bất đồng bộ), PAA, Google reviews và knowledge graph. Organic gồm tripadvisor.es (Tuan), video FB “Precio Alquiler Motos en Vietnam”, tripadvisor.co, duyanhmotorbikes (tiếng Anh), **rentalmotorbike.com/it (tiếng Ý)**, FB haibanhgarage, riderly/es, **bikesbooking.com/fr (tiếng Pháp)**, tripadvisor.com.ve. Ngôn ngữ kết quả lệch hẳn với truy vấn, cho thấy chất lượng SERP rất thấp.
  - PAA: *¿Cuánto cuesta alquilar una moto en Vietnam?* · *¿Qué se necesita para conducir moto en Vietnam?*
- **SERP “carnet de conducir moto vietnam”:** reddit (bản dịch máy), licenciainternacional.com, indochinamotorbiketours/es, vietnamtourism.com/es ×2 (8/2026 và 10/2026), onationbikes. Có AI Overview, PAA và video.
- **NM (ES) “alquiler de motos en Hanói casco antiguo”:** chỉ có Tripadvisor bằng tiếng Bồ Đào Nha, Ý, Pháp và Anh. **Không có trang tiếng Tây Ban Nha nào.**

---

## 7. Bản đồ từ khóa → vai trò trang (mỗi primary chỉ thuộc một vai trò)

| Vai trò | VI | EN | DE | FR | ES |
|---|---|---|---|---|---|
| home | mr tu thuê xe máy *(thương hiệu)* | mr tu motorbike rental *(thương hiệu)* | mr tu hanoi *(thương hiệu)* | mr tu hanoi *(thương hiệu)* | mr tu hanoi *(thương hiệu)* |
| pillar | **thuê xe máy hà nội** — 8.100 (OS · VN/vi) | **motorbike rental hanoi** — 2.400 (OR · VN/en) | **motorrad mieten hanoi** — 30 (OS · DE) | **location moto hanoi** — 50 (OS · FR) | **alquiler moto hanoi** — 10 (OS · ES) |
| old-quarter | thuê xe máy phố cổ hà nội — 70 (OS) | motorbike rental hanoi old quarter — 30 (OR · VN/en) | roller mieten hanoi altstadt — không có số | location moto vieux quartier hanoi — không có số | alquiler de motos casco antiguo hanói — không có số |
| pricing | giá thuê xe máy hà nội — 110 (OS) | hanoi motorbike rental cost — 30 (OR · VN/en) | roller mieten hanoi kosten — không có số (UB-AC) | location scooter hanoi prix — 10 (OS) | precio alquiler moto hanoi — không có số |
| fleet | thuê xe tay ga hà nội — không có số | automatic scooter rental hanoi — không có số | automatikroller mieten hanoi — không có số | location scooter automatique hanoi — không có số | alquiler de scooter automático hanói — không có số |
| short-term | thuê xe máy hà nội theo ngày — 20 (OR) | daily motorbike rental hanoi — không có số | roller tagesmiete hanoi — không có số | location scooter à la journée hanoi — không có số | alquiler de motos por días hanói — không có số |
| monthly | thuê xe máy theo tháng hà nội — 70 (OS) | monthly motorbike rental hanoi — không có số (có SERP riêng) | langzeitmiete roller hanoi — không có số | location moto au mois hanoi — không có số | alquiler moto mensual hanoi — không có số |
| guide-faq | thuê xe máy cần giấy tờ gì — 30 (OS) | do i need a license to rent a motorbike in vietnam — không có số (AI Overview + PAA) | roller mieten vietnam führerschein — 20 (OS) | permis moto vietnam — 20 (OS) | carnet para alquilar moto en vietnam — không có số |
| about | giới thiệu mr tu | about mr tu | über mr tu | à propos de mr tu | sobre mr tu |
| contact | liên hệ mr tu | contact mr tu motorbike rental | kontakt mr tu | contact mr tu hanoi | contacto mr tu |

“Không có số” nghĩa là OS/OR không trả volume. Từ khóa đó được chọn dựa trên seed của brief, autocomplete (UB-AC) và cách SERP thể hiện ý định. Từ khóa phụ của từng vai trò nằm trong `config/seo-plan.json`; script build đã kiểm tra rằng **không có từ khóa nào xuất hiện ở hai vai trò trong cùng một locale**.

### 7.1 Quyết định chống cannibalization

1. **Home đặt primary là thương hiệu/thực thể (truy vấn điều hướng)**, không phải head term.
   - Head term thuộc pillar: pillar có slug khớp chính xác theo brief, cùng H1 và nội dung sâu. Cụm khu vực thuộc old-quarter.
   - Title trang chủ **giữ nguyên đề xuất README §4** (ví dụ “Cho thuê xe máy Hà Nội, Phố Cổ | Mr Tu”); các chữ head term trong đó chỉ đóng vai trò mô tả.
   - Biện pháp giảm rủi ro: H1 trang chủ mở đầu bằng thương hiệu; trang chủ liên kết sang pillar và old-quarter với anchor là primary của từng trang; không trang nào khác lặp nguyên văn title của trang chủ.
2. **Riêng EN:** title README “Motorbike Rental in Hanoi Old Quarter | Mr Tu” gần như trùng primary của trang `old-quarter` EN, nên đây là điểm có rủi ro cao nhất.
   - Đề xuất: giữ nguyên title, rồi theo dõi Google Search Console (GSC) sau 8–12 tuần. Nếu Google luôn chọn trang chủ cho truy vấn Old Quarter, có thể gộp nội dung hoặc chủ site sửa title EN của trang chủ.
   - Volume liên quan rất nhỏ (30 · OR · VN/en) nên rủi ro chấp nhận được.
3. **Các cụm đồng nghĩa đặt trên một URL:**
   - VI: “thuê” và “cho thuê” Hà Nội; “phố cổ” và “hoàn kiếm”.
   - EN/DE/FR/ES: motorbike/scooter, Motorrad/Roller, moto/scooter đều thuộc pillar.
   - Lý do: SERP trùng nhau nhiều. Tách thành hai trang sẽ chia đôi tín hiệu.
4. **Fleet nhắm loại xe** (xe tay ga, automatic scooter, Automatikroller…). Không công cụ nào có số volume, và SERP “thuê xe tay ga hà nội” chỉ trả trang chung. Trang này phục vụ chuyển đổi và liên kết nội bộ hơn là lượng tìm kiếm.
5. **Tách short-term và monthly theo modifier thời hạn.** Trang monthly có bằng chứng rõ: VI có 70 lượt (OS), EN có các landing “monthly” riêng đang xếp hạng.
6. **Pricing và guide-faq:** “cọc bao nhiêu” (số tiền) thuộc pricing; “cọc gì / giấy tờ gì” (điều kiện thuê) thuộc guide-faq.
7. **Từ khóa cấp quốc gia** (motorrad mieten vietnam 140, location moto vietnam 90, alquiler moto vietnam 40) chỉ là từ khóa phụ của pillar. Nội dung chỉ hứa nhận xe tại Hà Nội; không nói về thuê một chiều hay đi tỉnh khi chưa xác minh.

---

## 8. Đề xuất slug / title / meta / H1

Áp dụng cho mọi locale (script build đã kiểm tra tự động):
- Title tối đa 60 ký tự, đã bao gồm hậu tố “ | Mr Tu”.
- Meta description dài 140–158 ký tự.
- Slug viết thường, chỉ ký tự ASCII và gạch nối; slug trang chủ để trống (`""`).
- Title trang chủ giữ nguyên README §4. Slug pillar giữ nguyên brief vì dữ liệu không mâu thuẫn.

Nội dung meta **không có giá**, **không có cam kết chưa xác minh** (không “uy tín số 1”, “giao xe 15 phút”, “miễn cọc”, “xe mới 100%”, “không cần bằng lái”) và **không ghi địa chỉ** khi NAP chưa được xác minh. Trang Old Quarter nói “điểm hẹn/nhận xe”; chỉ gọi là “cửa hàng” khi đã xác minh thực địa.

### 8.1 VI — tiếng Việt (prefix `/`)

| Vai trò | URL | Title (ký tự) | H1 | Meta description (ký tự) |
|---|---|---|---|---|
| home | `/` | Cho thuê xe máy Hà Nội, Phố Cổ \| Mr Tu (38) | Mr Tu – xe máy cho thuê gần Phố Cổ, Hà Nội | Mr Tu cho thuê xe số, xe tay ga theo ngày, tuần và tháng gần Phố Cổ Hà Nội. Xem các dòng xe, bảng giá, thủ tục thuê và cách liên hệ nhanh qua điện thoại. (153) |
| pillar | `/thue-xe-may-ha-noi/` | Thuê xe máy Hà Nội: xe số, xe ga, theo ngày, tháng \| Mr Tu (58) | Thuê xe máy Hà Nội | Dịch vụ thuê xe máy Hà Nội của Mr Tu: xe số, xe tay ga, thuê theo ngày, tuần hoặc tháng. Điều kiện thuê rõ ràng, giấy tờ cần có và cách đặt xe từng bước. (153) |
| old-quarter | `/thue-xe-may-pho-co-ha-noi/` | Thuê xe máy Phố Cổ Hà Nội, Hoàn Kiếm \| Mr Tu (44) | Thuê xe máy Phố Cổ Hà Nội và Hoàn Kiếm | Thuê xe máy gần Phố Cổ, Hoàn Kiếm cùng Mr Tu: khu vực phục vụ, cách nhận và trả xe, chỗ gửi xe và lưu ý khi chạy xe trong phố cổ. Gọi trước để hẹn giờ. (151) |
| pricing | `/gia-thue-xe-may-ha-noi/` | Giá thuê xe máy Hà Nội theo ngày, tuần, tháng \| Mr Tu (53) | Bảng giá thuê xe máy Hà Nội | Bảng giá thuê xe máy Hà Nội của Mr Tu theo ngày, tuần và tháng cho từng dòng xe, kèm tiền cọc, phụ phí và điều kiện áp dụng. Giá được cập nhật tại một nơi. (155) |
| fleet | `/cac-dong-xe-cho-thue/` | Các dòng xe cho thuê: xe tay ga, xe số Hà Nội \| Mr Tu (53) | Các dòng xe cho thuê tại Mr Tu | Các dòng xe tay ga và xe số Mr Tu cho thuê tại Hà Nội: đặc điểm từng xe, nên chọn xe nào để đi phố hay đi xa, cách kiểm tra xe khi nhận và cách đặt trước. (154) |
| short-term | `/thue-xe-may-theo-ngay/` | Thuê xe máy theo ngày, theo tuần ở Hà Nội \| Mr Tu (49) | Thuê xe máy theo ngày và theo tuần tại Hà Nội | Thuê xe máy ngắn ngày ở Hà Nội: cách tính ngày thuê, thuê theo tuần, giờ nhận và trả xe, cách gia hạn và những điều cần biết trước khi nhận xe từ Mr Tu. (152) |
| monthly | `/thue-xe-may-theo-thang-ha-noi/` | Thuê xe máy theo tháng Hà Nội, thuê dài hạn \| Mr Tu (51) | Thuê xe máy theo tháng tại Hà Nội | Thuê xe máy theo tháng ở Hà Nội cho người đi làm, sinh viên và người nước ngoài đang sống tại Hà Nội: xe phù hợp, tiền cọc, bảo dưỡng và cách gia hạn. (150) |
| guide-faq | `/thu-tuc-thue-xe-may-ha-noi/` | Thuê xe máy cần giấy tờ gì? Thủ tục & hỏi đáp \| Mr Tu (53) | Thủ tục thuê xe máy ở Hà Nội: giấy tờ, bằng lái, đặt cọc | Thuê xe máy ở Hà Nội cần giấy tờ gì, có cần bằng lái, đặt cọc thế nào và khi trả xe cần lưu ý gì? Hướng dẫn từng bước và câu hỏi thường gặp từ Mr Tu. (149) |
| about | `/gioi-thieu/` | Giới thiệu về chúng tôi \| Mr Tu (31) | Giới thiệu Mr Tu | Mr Tu là điểm cho thuê xe máy gần Phố Cổ Hà Nội. Tìm hiểu chúng tôi là ai, cách chuẩn bị xe, cách công bố giá và điều kiện thuê, cùng cách liên hệ với Mr Tu. (157) |
| contact | `/lien-he/` | Liên hệ đặt thuê xe máy \| Mr Tu (31) | Liên hệ Mr Tu | Liên hệ Mr Tu để hỏi xe còn trống, giá thuê và thủ tục: số điện thoại, địa chỉ gần Phố Cổ Hà Nội và giờ làm việc. Hãy gọi trước khi đến để hẹn giờ nhận xe. (155) |

### 8.2 EN — English (`/en/`)

| Vai trò | URL | Title (ký tự) | H1 | Meta description (ký tự) |
|---|---|---|---|---|
| home | `/en/` | Motorbike Rental in Hanoi Old Quarter \| Mr Tu (45) | Mr Tu – scooter and motorbike rental in Hanoi | Rent an automatic scooter or a semi-automatic motorbike from Mr Tu near Hanoi's Old Quarter. Daily, weekly and monthly rentals, clear rules and easy contact. (157) |
| pillar | `/en/motorbike-rental-hanoi/` | Motorbike & Scooter Rental Hanoi: Daily to Monthly \| Mr Tu (58) | Motorbike Rental in Hanoi | Rent a motorbike in Hanoi with Mr Tu: automatic scooters and semi-automatics by the day, week or month. What you need, how booking works and what is included. (158) |
| old-quarter | `/en/motorbike-rental-hanoi-old-quarter/` | Old Quarter Motorbike Rental, Hoan Kiem Hanoi \| Mr Tu (53) | Motorbike Rental in Hanoi's Old Quarter and Hoan Kiem | Renting a scooter in Hanoi's Old Quarter: where to meet Mr Tu near Hoan Kiem, how pick-up and return work, parking tips and riding in the narrow old streets. (157) |
| pricing | `/en/hanoi-motorbike-rental-prices/` | Hanoi Motorbike Rental Prices & Deposit \| Mr Tu (47) | Hanoi Motorbike Rental Prices | How much does it cost to rent a motorbike in Hanoi? See Mr Tu's daily, weekly and monthly rates by model, the deposit, extra fees and what each price covers. (157) |
| fleet | `/en/scooters-and-motorbikes/` | Automatic Scooters & Semi-Automatics for Rent \| Mr Tu (53) | Our Rental Scooters and Motorbikes | Compare the automatic scooters and semi-automatic motorbikes Mr Tu rents in Hanoi: engine size, comfort in town or on long rides, and which bike suits you. (155) |
| short-term | `/en/daily-weekly-motorbike-rental/` | Daily & Weekly Motorbike Rental in Hanoi \| Mr Tu (48) | Daily and Weekly Motorbike Rental in Hanoi | Need a bike for a day or a week in Hanoi? How Mr Tu counts rental days, pick-up and return times, extending your rental and what to check before you ride off. (158) |
| monthly | `/en/monthly-motorbike-rental-hanoi/` | Monthly Motorbike Rental Hanoi, Long-Term Stays \| Mr Tu (55) | Monthly Motorbike Rental in Hanoi | Living in Hanoi for a while? Rent a scooter by the month from Mr Tu: suitable models, deposit and documents, servicing during the rental and how to renew. (154) |
| guide-faq | `/en/how-to-rent-a-motorbike-in-hanoi/` | Motorbike License in Vietnam & Rental FAQ \| Mr Tu (49) | How to Rent a Motorbike in Hanoi: License, Documents and FAQ | Do you need a license to rent a motorbike in Vietnam? Which documents, deposit and helmet rules apply in Hanoi? Clear answers and step-by-step renting tips. (156) |
| about | `/en/about/` | About Us \| Mr Tu (16) | About Mr Tu | Mr Tu is a motorbike rental point near Hanoi's Old Quarter. Learn who we are, how we prepare our bikes, how we publish prices and how to reach us. (146) |
| contact | `/en/contact/` | Contact & Booking \| Mr Tu (25) | Contact Mr Tu | Contact Mr Tu to check bike availability, prices and documents: phone number, address near Hanoi's Old Quarter and opening hours. Call before you come. (151) |

### 8.3 DE — Deutsch (`/de/`)

| Vai trò | URL | Title (ký tự) | H1 | Meta description (ký tự) |
|---|---|---|---|---|
| home | `/de/` | Motorrad & Roller mieten in Hanoi \| Mr Tu (41) | Mr Tu – Ihr Rollerverleih in Hanoi | Roller und Halbautomatik-Motorräder in Hanoi mieten: Mr Tu nahe der Altstadt. Tages-, Wochen- und Monatsmiete, klare Bedingungen und ein einfacher Kontakt. (155) |
| pillar | `/de/motorrad-mieten-hanoi/` | Motorrad mieten Hanoi: Roller, Mietdauer, Ablauf \| Mr Tu (56) | Motorrad mieten in Hanoi | Motorrad oder Roller in Hanoi mieten: welche Modelle es gibt, Tages-, Wochen- und Monatsmiete, nötige Dokumente und wie die Buchung bei Mr Tu abläuft. (150) |
| old-quarter | `/de/roller-mieten-hanoi-altstadt/` | Roller mieten in Hanois Altstadt (Hoan Kiem) \| Mr Tu (52) | Roller mieten in der Altstadt von Hanoi | Roller mieten in der Altstadt von Hanoi: Treffpunkt bei Mr Tu nahe Hoan Kiem, Abholung und Rückgabe, Parken und Tipps für das Fahren in den engen Gassen. (153) |
| pricing | `/de/roller-mieten-hanoi-preise/` | Roller mieten Hanoi: Preise, Kaution & Kosten \| Mr Tu (53) | Preise für Roller- und Motorradmiete in Hanoi | Was kostet ein Mietroller in Hanoi? Tages-, Wochen- und Monatspreise je Modell bei Mr Tu, dazu Kaution, mögliche Zusatzkosten und was im Preis enthalten ist. (157) |
| fleet | `/de/roller-und-motorraeder/` | Automatikroller & Halbautomatik mieten Hanoi \| Mr Tu (52) | Unsere Mietroller und Motorräder | Automatikroller oder Halbautomatik? Vergleichen Sie die Modelle, die Mr Tu in Hanoi vermietet: Hubraum, Komfort in der Stadt und auf längeren Strecken. (151) |
| short-term | `/de/roller-tagesmiete-wochenmiete/` | Roller-Tagesmiete & Wochenmiete in Hanoi \| Mr Tu (48) | Roller für einen Tag oder eine Woche mieten | Roller für einen Tag oder eine Woche in Hanoi mieten: wie Miettage gezählt werden, Abhol- und Rückgabezeiten, Verlängerung und was Sie vor der Fahrt prüfen. (156) |
| monthly | `/de/langzeitmiete-roller-hanoi/` | Langzeitmiete: Roller monatlich mieten in Hanoi \| Mr Tu (55) | Roller monatlich mieten in Hanoi | Länger in Hanoi? Roller monatlich bei Mr Tu mieten: passende Modelle, Kaution und Dokumente, Wartung während der Miete und wie die Verlängerung funktioniert. (157) |
| guide-faq | `/de/roller-mieten-vietnam-fuehrerschein/` | Roller mieten in Vietnam: Führerschein & FAQ \| Mr Tu (52) | Roller mieten in Vietnam: Führerschein, Dokumente und FAQ | Welchen Führerschein brauchen Sie, um in Vietnam Roller zu fahren? Internationaler Führerschein, Dokumente, Kaution und Helmpflicht – klar erklärt für Hanoi. (157) |
| about | `/de/ueber-uns/` | Über uns \| Mr Tu (16) | Über Mr Tu | Mr Tu vermietet Roller und Motorräder nahe der Altstadt von Hanoi. Erfahren Sie, wer wir sind, wie wir Preise veröffentlichen und wie Sie uns erreichen. (152) |
| contact | `/de/kontakt/` | Kontakt & Reservierung \| Mr Tu (30) | Kontakt zu Mr Tu | Kontaktieren Sie Mr Tu, um freie Roller, Preise und nötige Dokumente zu erfragen: Telefon, Adresse nahe der Altstadt von Hanoi und Öffnungszeiten. (146) |

### 8.4 FR — Français (`/fr/`)

| Vai trò | URL | Title (ký tự) | H1 | Meta description (ký tự) |
|---|---|---|---|---|
| home | `/fr/` | Location de motos et scooters à Hanoï \| Mr Tu (45) | Mr Tu, votre loueur de scooters à Hanoï | Louez un scooter ou une moto semi-automatique chez Mr Tu, près du vieux quartier de Hanoï : à la journée, à la semaine ou au mois, conditions claires. (150) |
| pillar | `/fr/location-moto-hanoi/` | Location moto Hanoï : scooters au jour ou au mois \| Mr Tu (57) | Location de moto à Hanoï | Louer une moto ou un scooter à Hanoï avec Mr Tu : modèles proposés, location à la journée, à la semaine ou au mois, documents requis et réservation simple. (155) |
| old-quarter | `/fr/location-moto-vieux-quartier-hanoi/` | Location moto vieux quartier de Hanoï, Hoan Kiem \| Mr Tu (56) | Location de moto dans le vieux quartier de Hanoï | Louer un scooter dans le vieux quartier de Hanoï : point de rendez-vous Mr Tu près de Hoan Kiem, retrait et retour, stationnement et conseils de conduite. (154) |
| pricing | `/fr/prix-location-moto-hanoi/` | Prix location scooter Hanoï : tarifs et caution \| Mr Tu (55) | Prix de la location de moto et de scooter à Hanoï | Combien coûte la location d'un scooter à Hanoï ? Tarifs Mr Tu à la journée, à la semaine et au mois par modèle, caution, frais éventuels et ce qui est inclus. (158) |
| fleet | `/fr/scooters-et-motos/` | Scooters automatiques et motos à louer à Hanoï \| Mr Tu (54) | Nos scooters et motos de location | Scooter automatique ou moto semi-automatique ? Comparez les modèles loués par Mr Tu à Hanoï : cylindrée, confort en ville ou sur route et conseils de choix. (156) |
| short-term | `/fr/location-scooter-jour-semaine/` | Location de scooter à la journée ou à la semaine \| Mr Tu (56) | Location de scooter à la journée et à la semaine à Hanoï | Un scooter pour un jour ou une semaine à Hanoï ? Comment Mr Tu compte les jours, horaires de retrait et de retour, prolongation et vérifications utiles. (152) |
| monthly | `/fr/location-moto-au-mois-hanoi/` | Location moto au mois à Hanoï, longue durée \| Mr Tu (51) | Location de moto au mois à Hanoï | Vous vivez à Hanoï quelques mois ? Louez un scooter au mois chez Mr Tu : modèles adaptés, caution, documents, entretien pendant la location et renouvellement. (158) |
| guide-faq | `/fr/permis-moto-vietnam-location/` | Permis moto au Vietnam : louer un scooter, FAQ \| Mr Tu (54) | Louer une moto au Vietnam : permis, documents et FAQ | Quel permis faut-il pour conduire un scooter au Vietnam ? Permis international, documents, caution et casque obligatoire : nos réponses pour louer à Hanoï. (155) |
| about | `/fr/a-propos/` | À propos \| Mr Tu (16) | À propos de Mr Tu | Mr Tu loue des scooters et des motos près du vieux quartier de Hanoï. Découvrez qui nous sommes, comment nous publions nos prix et comment nous contacter. (154) |
| contact | `/fr/contact/` | Contact et réservation \| Mr Tu (30) | Contacter Mr Tu | Contactez Mr Tu pour connaître les scooters disponibles, les prix et les documents requis : téléphone, adresse près du vieux quartier de Hanoï et horaires. (155) |

### 8.5 ES — Español (`/es/`)

| Vai trò | URL | Title (ký tự) | H1 | Meta description (ký tự) |
|---|---|---|---|---|
| home | `/es/` | Alquiler de motos y scooters en Hanói \| Mr Tu (45) | Mr Tu, tu alquiler de scooters en Hanói | Alquila un scooter o una moto semiautomática con Mr Tu, cerca del casco antiguo de Hanói: por días, semanas o meses, con condiciones claras y contacto rápido. (158) |
| pillar | `/es/alquiler-motos-hanoi/` | Alquiler de motos en Hanói: por día, semana o mes \| Mr Tu (57) | Alquiler de motos en Hanói | Alquila una moto o un scooter en Hanói con Mr Tu: modelos disponibles, alquiler por días, semanas o meses, documentos necesarios y cómo reservar paso a paso. (157) |
| old-quarter | `/es/alquiler-motos-casco-antiguo-hanoi/` | Alquiler de motos en el casco antiguo de Hanói \| Mr Tu (54) | Alquiler de motos en el casco antiguo de Hanói | Alquila un scooter en el casco antiguo de Hanói: punto de encuentro de Mr Tu cerca de Hoan Kiem, recogida y devolución, aparcamiento y consejos para circular. (158) |
| pricing | `/es/precios-alquiler-motos-hanoi/` | Precios del alquiler de motos en Hanói y fianza \| Mr Tu (55) | Precios del alquiler de motos en Hanói | ¿Cuánto cuesta alquilar una moto en Hanói? Tarifas de Mr Tu por día, semana y mes según el modelo, fianza, posibles cargos extra y qué incluye cada precio. (155) |
| fleet | `/es/scooters-y-motos/` | Scooters automáticos y motos de alquiler, Hanói \| Mr Tu (55) | Nuestros scooters y motos de alquiler | ¿Scooter automático o moto semiautomática? Compara los modelos que Mr Tu alquila en Hanói: cilindrada, comodidad en ciudad o en ruta y cuál te conviene más. (156) |
| short-term | `/es/alquiler-moto-por-dias-semanas/` | Alquiler de moto por días o semanas en Hanói \| Mr Tu (52) | Alquiler de moto por días y por semanas en Hanói | ¿Necesitas una moto para un día o una semana en Hanói? Cómo cuenta Mr Tu los días de alquiler, horarios de recogida y devolución, prórrogas y qué revisar. (154) |
| monthly | `/es/alquiler-moto-mensual-hanoi/` | Alquiler de moto mensual en Hanói, larga estancia \| Mr Tu (57) | Alquiler de moto por meses en Hanói | ¿Vives en Hanói una temporada? Alquila un scooter por meses con Mr Tu: modelos adecuados, fianza y documentos, mantenimiento durante el alquiler y renovación. (158) |
| guide-faq | `/es/carnet-alquilar-moto-vietnam/` | Carnet para alquilar moto en Vietnam: guía y FAQ \| Mr Tu (56) | Alquilar una moto en Vietnam: carnet, documentos y preguntas frecuentes | ¿Qué carnet necesitas para conducir una moto en Vietnam? Permiso internacional, documentos, fianza y casco obligatorio: respuestas para alquilar en Hanói. (154) |
| about | `/es/sobre-nosotros/` | Sobre nosotros \| Mr Tu (22) | Sobre Mr Tu | Mr Tu alquila scooters y motos cerca del casco antiguo de Hanói. Conoce quiénes somos, cómo publicamos nuestros precios y cómo contactarnos antes de alquilar. (158) |
| contact | `/es/contacto/` | Contacto y reservas \| Mr Tu (27) | Contactar con Mr Tu | Contacta con Mr Tu para consultar motos disponibles, precios y documentos necesarios: teléfono, dirección cerca del casco antiguo de Hanói y horario. (149) |

---

## 9. Tính năng SERP — tổng hợp (25/10/2026)

| Truy vấn (thị trường · nguồn) | AI Overview | Local pack | PAA | Khác |
|---|---|---|---|---|
| thuê xe máy hà nội (VN/vi · OS-SERP) | ✅ | ✅ | — | — |
| cho thuê xe máy hà nội (VN/vi · OS-SERP) | — | ✅ | — | — |
| thuê xe máy phố cổ (VN/vi · OS-SERP) | ✅ | ✅ (có Mr Tú) | — | — |
| thuê xe máy hoàn kiếm (VN/vi · OS-SERP) | ✅ | ✅ | — | — |
| giá thuê xe máy hà nội (VN/vi · OS-SERP) | ✅ | — | — | — |
| thuê xe máy cần giấy tờ gì (VN/vi · OS-SERP) | ✅ | — | ✅ | — |
| thuê xe tay ga hà nội (VN/vi · OS-SERP) | — | ✅ | — | — |
| thuê xe máy theo tháng hà nội (VN/vi · OR-SERP) | ✅ | — | — | related |
| motorbike rental hanoi (VN/en · OR-SERP) | ✅ (bất đồng bộ) | ✅ | ✅ | related |
| monthly motorbike rental hanoi (VN/en · OR-SERP) | — | ✅ | ✅ | related |
| motorbike rental hanoi old quarter (VN/en · OR-SERP) | — | ✅ | — | — |
| do i need a license … vietnam (US · OR-SERP) | ✅ (bất đồng bộ) | — | ✅ | related |
| motorrad mieten hanoi (DE · OR-SERP) | ✅ (bất đồng bộ) | ✅ | — | related |
| roller mieten vietnam führerschein (DE · OR-SERP) | ✅ | — | ✅ | video |
| location moto hanoi (FR · OR-SERP) | ✅ (bất đồng bộ) | ✅ | ✅ | related |
| permis moto vietnam (FR · OR-SERP) | ✅ | — | ✅ | related |
| alquiler moto hanoi (ES · OR-SERP) | ✅ (bất đồng bộ) | — | ✅ | reviews, knowledge graph |
| carnet de conducir moto vietnam (ES · OR-SERP) | ✅ (bất đồng bộ) | — | ✅ | video |

“Bất đồng bộ” nghĩa là Google tải AI Overview sau, nên công cụ chỉ ghi nhận có mặt mà không lấy được nội dung.

**Hệ quả:**
1. **Local pack có mặt ở cả VI, EN (tìm từ Việt Nam), DE và FR.** Các listing đứng đầu có 1.100–2.100 đánh giá; GBP của Mr Tú hiện chưa có đánh giá nào. Thứ hạng local phụ thuộc vào đánh giá thật, tích lũy theo thời gian. **Tuyệt đối không tạo đánh giá giả.**
2. **AI Overview và PAA có ở hầu hết truy vấn hỏi về giấy tờ/bằng lái.** Trang `guide-faq` nên có câu trả lời ngắn, chính xác, ghi ngày cập nhật và nguồn chính thống. FAQ schema chỉ đặt trên trang này (đúng như contract).

---

## 10. Rủi ro, tuân thủ và việc chủ site cần xác minh

1. **Thông tin pháp lý về bằng lái (YMYL).** AI Overview DE/FR nêu: xe 110–125cc cần bằng A1/A cộng IDP theo Công ước 1968; bằng B (ô tô) không áp dụng cho xe trên 50cc; xe tối đa 50cc không cần bằng.
   - Đây là **trích dẫn từ bên thứ ba**. Phải đối chiếu với nguồn chính thức (luật Việt Nam, vietnam.diplo.de, vn.diplomatie.gouv.fr – cả hai đang xếp hạng) trước khi đăng, và ghi rõ ngày cập nhật.
   - Không viết rằng “không cần bằng lái”.
2. **Đặt cọc:** không hứa “không cọc/miễn cọc”. FAQ trả lời minh bạch theo chính sách đã duyệt.
3. **Giao xe, thuê theo giờ, xe 50cc, xe điện, đi tỉnh/thuê một chiều:** chỉ đưa vào từ khóa và nội dung khi đã xác minh. Hiện các từ khóa này **không** nằm trong JSON (trừ các model xe đã có trong bảng giá tham khảo).
4. **17 Phúc Tân:** chỉ mô tả là điểm nhận/hẹn xe đúng với thực tế. Nút “Đến cửa hàng” chỉ đặt khi đã xác minh có nhân viên tiếp khách (README §0.6).
5. **GBP:** chủ site cần
   - xác minh tên đầy đủ (SERP hiện “Cho thuê xe máy Phố Cổ - Mr Tú”) và quyết định cách viết thống nhất “Mr Tu” hay “Mr Tú”;
   - sau khi ra mắt, đổi link website của GBP từ `thuexemayhanoi.github.io/shop/` sang `https://hanoi.thuexemaynguyentu.com/`;
   - tích lũy đánh giá thật.

   Không ai khác tự sửa GBP thay chủ site.
6. **Site cùng hệ (Nguyễn Tú, Long Biên):** blog.thuexemaynguyentu.com đang xếp hạng cho “giá thuê xe máy hà nội” và “thuê xe máy hoàn kiếm”. Đề xuất (chủ site quyết định; repo này không đụng site khác):
   - không viết hai bài nhắm cùng truy vấn Phố Cổ/Hoàn Kiếm;
   - cân nhắc để bài Hoàn Kiếm của blog liên kết sang trang `old-quarter` của Mr Tu như địa điểm thật ở khu vực đó;
   - không sao chép nội dung giữa hai site;
   - không dùng NAP Long Biên.
7. **Giới hạn công cụ:** OpenSEO còn 6 credit. Ubersuggest đã hết hạn mức báo cáo trong ngày. Lần cập nhật số liệu sau cần nạp credit (việc của chủ tài khoản) hoặc dùng dữ liệu GSC thật sau khi ra mắt.

## 11. Chủ đề blog/cẩm nang (không gán vào vai trò chính)

- **VI:**
  - Thuê xe máy điện / VinFast ở Hà Nội: 260 + 260 (OR · VN/vi). Viết dạng hướng dẫn/so sánh; chỉ nói “có cho thuê” khi thực tế có.
  - Xe 50cc & bằng lái: 110 (OS/OR).
  - Gửi xe trong phố cổ; luật giao thông; mẹo chạy xe ở Hà Nội.
- **EN:**
  - motorbike rental vietnam: 320 (OS · US).
  - international driving permit vietnam: 110 (OS · US); dẫn sang guide-faq.
  - Hanoi → Ninh Binh / Ha Giang by motorbike (autocomplete). Chỉ viết nếu chính sách cho phép đi tỉnh.
  - Airport pickup: chỉ viết nếu có dịch vụ này.
- **DE:**
  - motorradtour vietnam: 260 (OS · DE). Mr Tu không có tour, nên chỉ viết bài thông tin.
  - roller fahren vietnam: 30.
- **FR:**
  - moto vietnam: 260; conduire au vietnam: 110 (OS · FR).
  - Location moto Ha Giang (autocomplete).
- **ES:** “¿Cuánto cuesta alquilar una moto en Vietnam?” và “¿Qué se necesita para conducir moto en Vietnam?” (PAA).

## 12. Khuyến nghị kỹ thuật cho plugin/theme

- **hreflang dạng chung (`en`, `de`, `fr`, `es`)**, đúng như contract hiện tại, được dữ liệu ủng hộ:
  - Nhu cầu EN phân tán ở VN, US, UK và AU.
  - ES gần như chỉ có ở Spain; Mexico xấp xỉ 0.
  - Vì vậy không nên tách `en-US`/`en-GB` hay `es-MX`.
- **`x_default = en`** hợp lý: EN tại Việt Nam (2.400) là thị trường ngoại ngữ lớn nhất.
- **Seeder:** đọc `config/seo-plan.json` và ánh xạ các trường như sau:

  | seo-plan.json | Seeder / post meta |
  |---|---|
  | `primary_kw` | `focus_kw` / `_mrtu_focus_kw` |
  | `title` | `seo_title` |
  | `meta` | `seo_desc` |
  | `h1` | `title` (tiêu đề trang) |
  | `slug` | `slug` |

- **Health check:** cảnh báo khi hai trang cùng locale có cùng `focus_kw`, khi title dài quá 60 ký tự, và khi meta nằm ngoài khoảng 140–158 ký tự. Không nên bắt title chứa nguyên văn primary: các primary dạng câu hỏi (ví dụ EN guide-faq) và primary thương hiệu được diễn đạt tự nhiên trong title.
- **Đo lường sau khi ra mắt:** lọc GSC theo thư mục `/en/`, `/de/`, `/fr/`, `/es/`. Xem lại cannibalization sau 8–12 tuần, nhất là cặp home ↔ pillar và home EN ↔ old-quarter EN.

---

## Phụ lục — Từ khóa công cụ không trả dữ liệu (25/10/2026)

- **OS · VN/vi:**
  - thuê xe máy phố cổ, cho thuê xe máy phố cổ, thuê xe máy hoàn kiếm, thuê xe máy dài hạn hà nội;
  - thuê xe ga hà nội, thuê xe số hà nội, thuê xe tay ga hà nội;
  - thuê xe máy hà nội cần giấy tờ gì;
  - thuê xe máy theo ngày hà nội, thuê xe máy ngày hà nội, thuê xe máy theo tuần hà nội;
  - thuê xe vision hà nội, thuê xe wave hà nội;
  - thuê xe máy giao tận nơi hà nội;
  - thuê xe air blade hà nội (trả về null).
- **OS · US/en:**
  - monthly scooter/motorbike rental hanoi, long term motorbike rental hanoi;
  - rent a motorbike in hanoi, rent a scooter in hanoi, hanoi old quarter motorbike rental;
  - hanoi motorbike rental price, motorbike rental hanoi deposit, motorbike rental hanoi without passport;
  - do i need a license to rent a motorbike in vietnam, can tourists rent motorbikes in vietnam, is it safe to ride a motorbike in hanoi;
  - honda vision rental hanoi, automatic scooter rental hanoi, semi automatic motorbike rental hanoi, weekly motorbike rental hanoi.
- **OS · DE:**
  - roller mieten hanoi altstadt, motorroller mieten hanoi, scooter mieten hanoi, moped mieten hanoi;
  - roller leihen hanoi, motorrad leihen vietnam;
  - vietnam führerschein motorrad, roller fahren hanoi;
  - motorrad vietnam mieten preise, roller mieten hanoi preis;
  - langzeitmiete roller hanoi, roller monatlich mieten vietnam;
  - hanoi roller, hanoi motorrad.
- **OS · FR:**
  - location de moto à hanoï, location moto hanoï, location de scooter à hanoï, location scooter hanoï;
  - location moto vieux quartier hanoï/hanoi;
  - permis moto vietnam location;
  - louer un scooter à hanoi;
  - location moto hanoi prix;
  - location moto au mois hanoi, location moto longue durée vietnam.
- **OS · ES:**
  - alquiler de motos en hanói, alquiler de motos hanoi, alquiler scooter hanoi, alquiler de scooters en hanói, alquiler de motos vietnam;
  - casco antiguo (2 biến thể);
  - carnet para alquilar moto en vietnam, carnet de conducir vietnam, permiso internacional de conducir vietnam;
  - alquilar scooter hanoi, precio alquiler moto hanoi, alquiler moto mensual hanoi;
  - moto en vietnam;
  - rentar moto en hanoi, renta de motos hanoi/vietnam.
- **Ubersuggest `keyword_overview`:** không có dữ liệu (HTTP 403, hết hạn mức ngày).
