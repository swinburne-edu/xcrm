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

## 3. Tư vấn (F4)

- **Hồ sơ tuyển sinh** (`/cases`): bảng Kanban theo giai đoạn (kéo thả để chuyển giai đoạn) hoặc dạng bảng; bấm thẻ để xem nhanh. Hồ sơ ở kho chung: bấm **Nhận hồ sơ** (tư vấn viên) hoặc **Giao** (trưởng nhóm).
- **Ghi cuộc gọi**: mở thí sinh → tab **Timeline** → chọn *Cuộc gọi*, chọn kết quả. Nếu kết quả là *liên hệ được*, hệ thống ghi thời điểm liên hệ đầu tiên và chuyển hồ sơ *Mới → Đã liên hệ*. Điền "Hẹn gọi lại lúc" sẽ tạo việc gọi lại.
- **Công việc của tôi** (`/tasks`): các tab Hôm nay / Quá hạn / Sắp tới / Đã xong.
- **Lịch hẹn** (`/appointments`): đặt lịch tư vấn, tham quan, phỏng vấn, thi năng khiếu; sau buổi hẹn ghi nhận *có đến / vắng / huỷ* (ghi xong không sửa được).
- Chuyển sang giai đoạn **Thất bại** bắt buộc chọn lý do. Mùa đã đóng chỉ đọc.

## 4. Gộp thí sinh trùng (F6)

**Thí sinh → Có thể trùng** liệt kê các hồ sơ cùng họ tên + cùng trường THPT hoặc dùng chung SĐT phụ huynh. Bấm **Gộp vào …** để mở màn so sánh: với mỗi trường khác nhau, chọn giá trị muốn giữ (ô trống tự lấy từ bản kia), có nút **Đổi chiều** để chọn bản giữ lại. Khi gộp, phụ huynh, điểm, hồ sơ, lịch sử và việc của bản kia chuyển sang bản giữ; bản kia bị ẩn khỏi danh sách. Cần quyền *Gộp thí sinh*.

## 5. Theo dõi kết quả

- **Tổng quan** (`/dashboard`): thí sinh mới, hồ sơ, trúng tuyển, nhập học so với mục tiêu; phễu theo giai đoạn; top nguồn, top trường THPT; tiến độ chỉ tiêu theo ngành; việc quá hạn của bạn.
- **Báo cáo** (`/reports`): phễu theo nguồn, chiến dịch, trường THPT, tỉnh, ngành, tư vấn viên; nút **Xuất Excel**.

Số liệu luôn theo phạm vi dữ liệu của người xem (tư vấn viên: hồ sơ của mình; trưởng nhóm: nhóm/khu vực; trưởng phòng: tất cả).

## Chưa có trong MVP

Nguyện vọng xét tuyển và điểm xét tuyển, giấy tờ minh chứng, import danh sách trúng tuyển, hoạt động tại trường + QR check-in, webhook Facebook Lead Ads, thông báo quá SLA / thu hồi hồ sơ tự động (giai đoạn 2 trong `docs/TASKS.md`).
