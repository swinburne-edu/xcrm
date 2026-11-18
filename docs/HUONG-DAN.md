# Hướng dẫn sử dụng nhanh xcrm (MVP)

Dành cho cán bộ tuyển sinh. Giao diện hoàn toàn tiếng Việt; thanh trên cùng có ô **chọn mùa tuyển sinh** — mọi số liệu, hồ sơ, chiến dịch đều theo mùa đang chọn.

## 0. Chạy thử với dữ liệu mẫu

```bash
php artisan migrate
php artisan db:seed --class=DemoSeeder      # chỉ chạy ở local / testing, chạy lại không tạo trùng
php artisan queue:work                      # cần để import Excel xử lý nền
```

Tài khoản mẫu (mật khẩu `password`): `demo.head@example.com` (trưởng phòng, thấy tất cả), `demo.lead@example.com` (trưởng nhóm), `demo.counselor1..3@example.com` (tư vấn viên, chỉ thấy hồ sơ của mình), `demo.marketing@example.com`. Tài khoản quản trị: `test@example.com`.

## 1. Chuẩn bị mùa tuyển sinh (F1)

1. **Cài đặt → Mùa tuyển sinh**: tạo mùa mới hoặc **Sao chép từ mùa trước** (đợt, phương thức, chỉ tiêu được copy sang).
2. Trong mùa: tối đa 5 phương thức xét tuyển (không tính xét tuyển thẳng), cấu hình đợt và mốc thời gian.
3. **Đào tạo & chỉ tiêu → Ngành**: nhập chỉ tiêu, học phí, điểm chuẩn năm trước cho từng ngành.
4. **Marketing → Chiến dịch**: tạo (nháp) → gửi duyệt → trưởng phòng duyệt thì chiến dịch chạy.
5. Đặt mùa là *hiện tại* và trạng thái *đang diễn ra*.

## 2. Thu nhận thí sinh (F3)

- **Form trên website**: **Marketing → Form thu thập** → tạo biểu mẫu (chọn trường cần hỏi, nguồn, chiến dịch) → nút `</>` lấy mã HTML dán vào website. Form tự gắn tham số `utm_*`; ô ẩn `hp` là chống spam, đừng xoá.
- **Import Excel**: **Thí sinh → Nhập Excel**. File .xlsx/.csv, dòng đầu là tiêu đề cột (Họ và tên, SĐT, Email, Ngày sinh, Trường, Năm tốt nghiệp…). Bước 2 cho ghép cột và xem trước *mới / trùng / lỗi*; bước 3 chạy nền và báo kết quả.
- **Nhập tay**: **Thí sinh → Thêm thí sinh** (có cảnh báo trùng ngay khi gõ SĐT/email/CCCD).

Hệ thống tự: chuẩn hoá SĐT, chống trùng, tạo **hồ sơ tuyển sinh** ở giai đoạn *Mới*, giao theo vùng phụ trách (không khớp thì vào **kho chung**), và tạo việc **"Gọi lần đầu"** với hạn theo SLA giai đoạn Mới (mặc định 24 giờ). Người đăng ký lại không bị tạo mới: hệ thống chỉ bổ sung thông tin còn thiếu và ghi thêm một lần tiếp xúc.

## 2b. Hoạt động tại trường THPT và điểm danh QR (F2)

1. **Trường THPT → Hoạt động tại trường → Thêm hoạt động**: chọn loại (tư vấn tại trường, ngày hội, tham quan, trao học bổng, gặp BGH), trường, chiến dịch, thời gian, địa điểm, nhân sự. Xem dạng **Bảng** hoặc **Lịch** tháng.
2. Mở hoạt động → **mã QR** và link điểm danh (`/checkin/…`). In hoặc tải QR (SVG) để chiếu/dán tại buổi tư vấn.
3. Học sinh quét QR bằng điện thoại, điền họ tên, số điện thoại, lớp, ngành quan tâm và đồng ý nhận thông tin. Không cần đăng nhập. Link chỉ mở từ 2 giờ trước giờ bắt đầu đến 12 giờ sau khi kết thúc, và đóng khi hoạt động đã diễn ra hoặc bị huỷ.
4. Hệ thống tự: chống trùng theo số điện thoại (điểm danh nhiều lần chỉ ghi một lượt và thêm một lần tiếp xúc), tạo thí sinh (trường THPT và khu vực ưu tiên lấy theo trường của hoạt động), tạo **hồ sơ mùa hiện tại** với nguồn "Tư vấn tại trường THPT" (hoặc "Ngày hội tuyển sinh", "Tham quan campus" theo loại), giao cho người phụ trách theo quy tắc phân bổ / vùng của trường, và tạo việc "Gọi lần đầu".
5. Sau buổi: mở hoạt động → **Báo cáo sau hoạt động** (tham dự thực tế, chi phí, kết quả). Lưu báo cáo chuyển hoạt động sang *Đã diễn ra* và đóng điểm danh.
6. Trang mỗi trường có tab **Hoạt động** (các buổi đã/đang lên lịch) và **Thống kê theo mùa**: số thí sinh, hồ sơ, trúng tuyển, nhập học từ trường, số hoạt động, điểm danh và chi phí theo từng mùa.

## 3. Tư vấn (F4)

- **Hồ sơ tuyển sinh** (`/cases`): bảng Kanban theo giai đoạn (kéo thả để chuyển giai đoạn) hoặc dạng bảng; bấm thẻ để xem nhanh. Hồ sơ ở kho chung: bấm **Nhận hồ sơ** (tư vấn viên) hoặc **Giao** (trưởng nhóm).
- **Ghi cuộc gọi**: mở thí sinh → tab **Timeline** → chọn *Cuộc gọi*, chọn kết quả. Nếu kết quả là *liên hệ được*, hệ thống ghi thời điểm liên hệ đầu tiên và chuyển hồ sơ *Mới → Đã liên hệ*. Điền "Hẹn gọi lại lúc" sẽ tạo việc gọi lại.
- **Công việc của tôi** (`/tasks`): các tab Hôm nay / Quá hạn / Sắp tới / Đã xong.
- **Lịch hẹn** (`/appointments`): đặt lịch tư vấn, tham quan, phỏng vấn, thi năng khiếu; sau buổi hẹn ghi nhận *có đến / vắng / huỷ* (ghi xong không sửa được).
- Chuyển sang giai đoạn **Thất bại** bắt buộc chọn lý do. Mùa đã đóng chỉ đọc.

## 3b. Phân bổ hồ sơ và SLA

- **Cài đặt → Quy tắc phân bổ**: mỗi quy tắc có *độ ưu tiên* (số nhỏ chạy trước), *điều kiện* (nguồn, chiến dịch, tỉnh, trường THPT, hạng trường, ngành — để trống là áp dụng mọi hồ sơ) và *cách phân bổ*: **cố định** một người, **xoay vòng** (người nhận hồ sơ cách đây lâu nhất), **theo tải** (ít hồ sơ đang mở nhất) hoặc **theo vùng phụ trách**. Có thể chọn danh sách người hoặc một nhóm (lấy thành viên). Hồ sơ mới từ form, import hay nhập tay (không chọn người) được thử lần lượt với các quy tắc; quy tắc đầu tiên khớp và tìm được người nhận sẽ quyết định, không có thì dùng vùng phụ trách, rồi kho chung. Người đã ngừng hoạt động bị bỏ qua.
- **SLA**: mỗi giai đoạn có SLA riêng (Cài đặt → Pipeline; mặc định *Mới* 24 giờ, *Đã liên hệ* 72 giờ). Hồ sơ đã có người phụ trách mà quá hạn SLA kể từ lúc vào giai đoạn/được giao và chưa có cuộc gọi, ghi chú, tin nhắn hay cuộc gặp nào → **trưởng nhóm nhận thông báo** (không có trưởng nhóm thì quản lý), mỗi lần chỉ một thông báo.
- **Thu hồi tự động** (tắt mặc định): bật trong cùng trang, đặt số ngày; hồ sơ không có hoạt động trong N ngày về **kho chung** của nhóm, người phụ trách cũ và trưởng nhóm được báo. Không động tới hồ sơ đã nhập học/thất bại, mùa đã đóng, hay hồ sơ có ngày hành động tiếp theo trong tương lai.
- Thông báo hiện ở **biểu tượng chuông** trên cùng. Việc kiểm tra SLA chạy mỗi 15 phút nhờ lịch của Laravel: chạy `php artisan schedule:work` (hoặc cron `php artisan schedule:run` mỗi phút); kiểm tra ngay bằng `php artisan crm:check-sla`.

## 4. Gộp thí sinh trùng (F6)

**Thí sinh → Có thể trùng** liệt kê các hồ sơ cùng họ tên + cùng trường THPT hoặc dùng chung SĐT phụ huynh. Bấm **Gộp vào …** để mở màn so sánh: với mỗi trường khác nhau, chọn giá trị muốn giữ (ô trống tự lấy từ bản kia), có nút **Đổi chiều** để chọn bản giữ lại. Khi gộp, phụ huynh, điểm, hồ sơ, lịch sử và việc của bản kia chuyển sang bản giữ; bản kia bị ẩn khỏi danh sách. Cần quyền *Gộp thí sinh*.

## 4b. Nguyện vọng và giấy tờ

- Mở thí sinh → tab **Nguyện vọng** → **Thêm nguyện vọng**: chọn đợt, ngành, phương thức, tổ hợp. Hệ thống chỉ cho chọn phương thức/tổ hợp đã gán cho ngành trong mùa, mỗi hồ sơ tối đa 15 nguyện vọng.
- **Điểm tự tính** theo công thức của phương thức (điểm thi THPT, học bạ, ĐGNL…, cộng điểm ưu tiên KV/đối tượng, quy đổi chứng chỉ ngoại ngữ). Thiếu điểm môn nào thì báo "thiếu: …" — nhập điểm ở tab **Điểm** rồi bấm *Tính lại*. Mở "Cách tính điểm" để xem từng môn.
- Vòng đời: **Nộp** (cần đủ điểm) → thư ký xét tuyển chọn **Đủ điều kiện / Không đủ điều kiện / Trúng tuyển / Không trúng tuyển** → **Xác nhận nhập học**; có thể **Rút** bất cứ lúc nào trước khi xác nhận. Hồ sơ tuyển sinh tự chuyển giai đoạn tương ứng (Nộp hồ sơ xét sớm → Đủ điều kiện → Trúng tuyển → Xác nhận), không bao giờ lùi.
- Tab **Giấy tờ**: tải học bạ, CCCD… (PDF/ảnh ≤ 10 MB, lưu riêng tư). Thư ký **Duyệt** hoặc **Từ chối** (bắt buộc ghi lý do). Phương thức có danh sách giấy tờ cần nộp, hiện ngay trên nguyện vọng.
- Menu **Tuyển sinh → Nguyện vọng** (`/applications`): danh sách đã nộp theo điểm; chọn một **ngành** để so thứ hạng với chỉ tiêu (xanh = trong chỉ tiêu xét theo điểm).

## 5. Theo dõi kết quả

- **Tổng quan** (`/dashboard`): thí sinh mới, hồ sơ, trúng tuyển, nhập học so với mục tiêu; phễu theo giai đoạn; top nguồn, top trường THPT; tiến độ chỉ tiêu theo ngành; việc quá hạn của bạn.
- **Tiến độ chỉ tiêu** (`/quotas`): mỗi ngành một dòng — quan tâm, đã nộp, đủ điều kiện, trúng tuyển, xác nhận, nhập học so với chỉ tiêu. **Vượt chỉ tiêu** khi số trúng tuyển/nhập học lớn hơn chỉ tiêu; **Thiếu** khi thấp hơn mục tiêu × phần thời gian đã qua của mùa × 0,7 (cần khai báo ngày bắt đầu/kết thúc của mùa). Hằng giờ hệ thống lưu ảnh chụp số liệu và gửi thông báo ở **biểu tượng chuông** cho trưởng phòng/quản trị khi một ngành mới chuyển sang Thiếu hoặc Vượt. Cần chạy `php artisan schedule:work` (hoặc cron `schedule:run` mỗi phút) để lịch chạy.
- **Báo cáo** (`/reports`): phễu theo nguồn, chiến dịch, trường THPT, tỉnh, ngành, tư vấn viên; nút **Xuất Excel**.

Số liệu luôn theo phạm vi dữ liệu của người xem (tư vấn viên: hồ sơ của mình; trưởng nhóm: nhóm/khu vực; trưởng phòng: tất cả).

## Chưa có trong MVP

Import danh sách trúng tuyển, webhook Facebook Lead Ads, trường tuỳ chỉnh, saved view (giai đoạn 2 trong `docs/TASKS.md`).
