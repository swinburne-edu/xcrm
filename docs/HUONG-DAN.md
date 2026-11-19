# Hướng dẫn sử dụng nhanh xcrm (MVP)

Dành cho cán bộ tuyển sinh. Giao diện hoàn toàn tiếng Việt; thanh trên cùng có ô **chọn mùa tuyển sinh** — mọi số liệu, hồ sơ, chiến dịch đều theo mùa đang chọn.

> Trong hệ thống có sẵn trang **Hướng dẫn sử dụng** (menu cuối cùng, `/help`): hướng dẫn từng bước theo vai trò (tư vấn viên, trưởng nhóm, trưởng phòng, thư ký xét tuyển, cán bộ trường THPT, marketing, khoa, cộng tác viên, quản trị). Nội dung sửa ở `resources/js/pages/Help/guides.ts`.

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

## 1b. Tự mở hồ sơ cho học sinh sắp tốt nghiệp (F5)

Khi một mùa chuyển sang **Đang tuyển sinh**, hệ thống tự mở hồ sơ cho mọi thí sinh có **năm tốt nghiệp** trùng năm của mùa mà chưa có hồ sơ. Hồ sơ vào kho chung hoặc được giao theo luật phân bổ / vùng phụ trách. Mỗi đêm lúc 02:00 hệ thống chạy lại để lấy thêm thí sinh mới nhập sau đó. Cần chạy bộ lập lịch (`php artisan schedule:work`) và hàng đợi (`php artisan queue:work`); hoặc chạy tay bằng `php artisan crm:create-graduating-cases`.

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

## 2c. Nhận lead từ Facebook Lead Ads và TikTok

1. **Cài đặt → Tích hợp lead ads → Thêm tích hợp**: chọn nền tảng, đặt tên, chọn nguồn / chiến dịch (để trống thì dùng nguồn mặc định Facebook Ads hoặc TikTok Ads), nhập khoá bí mật. Facebook cần thêm **Page access token** (quyền `leads_retrieval`) và hệ thống tự tạo **verify token**.
2. Sao chép **địa chỉ webhook** (và verify token) dán vào ứng dụng Facebook (Webhooks → Page → leadgen) hoặc cấu hình webhook của TikTok. Mỗi yêu cầu được kiểm tra chữ ký; sai chữ ký bị từ chối và ghi lại.
3. Mỗi lead mới tự tạo thí sinh, hồ sơ mùa hiện tại, giao người phụ trách và tạo việc "Gọi lần đầu"; người đã có trong hệ thống chỉ được bổ sung thông tin và ghi thêm một lần tiếp xúc. Một lead gửi lại nhiều lần chỉ tính một lần.
4. Trường quen thuộc (họ tên, số điện thoại, email, tỉnh, trường, ngành…) tự nhận ra; form quảng cáo dùng tên trường riêng thì thêm dòng ở **Ghép trường**. Trường không ghép được vẫn lưu trong thông tin lần tiếp xúc.
5. Bấm **Lead gần đây** để xem lead vừa nhận và lý do lỗi (ví dụ thiếu số điện thoại). Cần chạy `php artisan queue:work` để lead được xử lý.

## 3. Tư vấn (F4)

- **Hồ sơ tuyển sinh** (`/cases`): bảng Kanban theo giai đoạn (kéo thả để chuyển giai đoạn) hoặc dạng bảng; bấm thẻ để xem nhanh. Hồ sơ ở kho chung: bấm **Nhận hồ sơ** (tư vấn viên) hoặc **Giao** (trưởng nhóm).
- **Ghi cuộc gọi**: mở thí sinh → tab **Timeline** → chọn *Cuộc gọi*, chọn kết quả. Nếu kết quả là *liên hệ được*, hệ thống ghi thời điểm liên hệ đầu tiên và chuyển hồ sơ *Mới → Đã liên hệ*. Điền "Hẹn gọi lại lúc" sẽ tạo việc gọi lại.
- **Công việc của tôi** (`/tasks`): các tab Hôm nay / Quá hạn / Sắp tới / Đã xong.
- **Lịch hẹn** (`/appointments`): đặt lịch tư vấn, tham quan, phỏng vấn, thi năng khiếu; sau buổi hẹn ghi nhận *có đến / vắng / huỷ* (ghi xong không sửa được).
- Chuyển sang giai đoạn **Thất bại** bắt buộc chọn lý do. Mùa đã đóng chỉ đọc.

## 3c. Sửa nhanh ngay trong danh sách thí sinh

1. Ở **Thí sinh**, bấm vào ô **Họ tên** (biểu tượng bút chì nhỏ khi rê chuột), **Email**, **Điện thoại**, **Trường THPT**, **Tỉnh/Thành phố** hoặc **Tốt nghiệp** để gõ trực tiếp. **Enter** hoặc bấm ra ngoài là lưu, **Esc** là bỏ. Ô Trường THPT và Tỉnh chọn từ danh sách, chọn xong tự lưu.
2. Số điện thoại hoặc email trùng thí sinh khác sẽ không được lưu và báo rõ trùng với ai. Họ tên không được để trống.
3. Cạnh số điện thoại có nút **Gọi** (mở ứng dụng gọi trên điện thoại/máy tính) và **Chép số**. Người chỉ thấy số đã che không sửa được số tại chỗ và không có hai nút này; dùng nút bút chì ở cuối dòng để mở form đầy đủ.
4. Danh sách có thêm cột **Giai đoạn**, **Người phụ trách** và **Hoạt động gần nhất** của hồ sơ mùa mới nhất. Chỉ sửa được thí sinh thuộc phạm vi dữ liệu của bạn và khi bạn có quyền sửa thí sinh.

## 3d. Chi phí chiến dịch

1. Mở một chiến dịch, mục **Chi phí theo ngày**: bấm **Thêm chi phí**, nhập ngày, số tiền (₫) và ghi chú. Sửa hoặc xoá bằng nút bên phải mỗi dòng. **Chi phí thực** của chiến dịch luôn bằng tổng các dòng này.
2. Ghi được chi phí ở mọi trạng thái (kể cả chiến dịch đã kết thúc), trừ mùa tuyển sinh đã đóng. Cần quyền sửa chiến dịch.
3. Ở danh sách chiến dịch bấm **Chi phí và hiệu quả** để xem chi phí, số thí sinh, số nhập học, chi phí mỗi thí sinh, chi phí mỗi nhập học, tỉ lệ chuyển đổi, so sánh giữa các mùa và từng chiến dịch của mùa đang chọn. Nhập **doanh thu dự kiến mỗi sinh viên nhập học** để xem ROI = (doanh thu − chi phí) / chi phí.

## 3b. Phân bổ hồ sơ và SLA

- **Cài đặt → Quy tắc phân bổ**: mỗi quy tắc có *độ ưu tiên* (số nhỏ chạy trước), *điều kiện* (nguồn, chiến dịch, tỉnh, trường THPT, hạng trường, ngành — để trống là áp dụng mọi hồ sơ) và *cách phân bổ*: **cố định** một người, **xoay vòng** (người nhận hồ sơ cách đây lâu nhất), **theo tải** (ít hồ sơ đang mở nhất) hoặc **theo vùng phụ trách**. Có thể chọn danh sách người hoặc một nhóm (lấy thành viên). Hồ sơ mới từ form, import hay nhập tay (không chọn người) được thử lần lượt với các quy tắc; quy tắc đầu tiên khớp và tìm được người nhận sẽ quyết định, không có thì dùng vùng phụ trách, rồi kho chung. Người đã ngừng hoạt động bị bỏ qua.
- **SLA**: mỗi giai đoạn có SLA riêng (Cài đặt → Pipeline; mặc định *Mới* 24 giờ, *Đã liên hệ* 72 giờ). Hồ sơ đã có người phụ trách mà quá hạn SLA kể từ lúc vào giai đoạn/được giao và chưa có cuộc gọi, ghi chú, tin nhắn hay cuộc gặp nào → **trưởng nhóm nhận thông báo** (không có trưởng nhóm thì quản lý), mỗi lần chỉ một thông báo.
- **Thu hồi tự động** (tắt mặc định): bật trong cùng trang, đặt số ngày; hồ sơ không có hoạt động trong N ngày về **kho chung** của nhóm, người phụ trách cũ và trưởng nhóm được báo. Không động tới hồ sơ đã nhập học/thất bại, mùa đã đóng, hay hồ sơ có ngày hành động tiếp theo trong tương lai.
- Thông báo hiện ở **biểu tượng chuông** trên cùng. Việc kiểm tra SLA chạy mỗi 15 phút nhờ lịch của Laravel: chạy `php artisan schedule:work` (hoặc cron `php artisan schedule:run` mỗi phút); kiểm tra ngay bằng `php artisan crm:check-sla`.

## 3e. Gửi tin nhắn hàng loạt

1. **Cài đặt, Mẫu tin nhắn**: tạo mẫu theo kênh (Zalo ZNS, SMS, email). Bấm tên biến (vd. `applicant.first_name`) để chèn vào nội dung. Mẫu ZNS cần mã mẫu đã được Zalo duyệt; email cần tiêu đề.
2. Ở **Thí sinh**, lọc danh sách rồi bấm **Gửi tin**, chọn mẫu và **Xem trước**: hệ thống cho biết bao nhiêu người sẽ nhận, bao nhiêu người bị bỏ qua (chưa đồng ý nhận tin, thiếu SĐT/email) và một tin mẫu. Bấm **Gửi** để xếp hàng gửi. Tối đa 2000 người mỗi lần.
3. **Marketing, Tin nhắn đã gửi** cho thấy từng tin, trạng thái và lỗi nếu có. Tin gửi thành công cũng hiện trong timeline của thí sinh.
4. Quản trị cấu hình nhà cung cấp trong `.env` (`ZALO_ZNS_ACCESS_TOKEN`, `SMS_API_URL`, `SMS_API_TOKEN`, `SMS_BRANDNAME`). Đặt `MESSAGE_SMS_DRIVER=log` (tương tự cho ZNS, email) để thử mà không gửi thật. Cần chạy hàng đợi (`php artisan queue:work`).

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

## 5b. Trường tuỳ chỉnh

1. **Cài đặt → Trường tuỳ chỉnh → Thêm trường**: chọn đối tượng (thí sinh, hồ sơ, trường THPT, ngành, chiến dịch, người liên hệ), tên hiển thị, mã trường (chữ thường không dấu, không đổi được sau khi tạo) và kiểu dữ liệu: văn bản, số, ngày, có/không, chọn một, chọn nhiều, URL, số điện thoại, email. Kiểu chọn cần nhập các lựa chọn, mỗi dòng một lựa chọn.
2. Trường hiện ngay trong form thêm/sửa thí sinh và trường THPT (bắt buộc nếu bạn đánh dấu), và thành thẻ **Trường tuỳ chỉnh** ở trang chi tiết thí sinh, trường THPT và ngăn xem nhanh hồ sơ. Bấm **Sửa** trên thẻ để nhập nhanh tại chỗ.
3. Trường đánh dấu "cho phép lọc" xuất hiện trong bộ lọc của danh sách thí sinh, trường THPT và hồ sơ.
4. Xoá trường chỉ ẩn nó đi; dữ liệu đã nhập vẫn được giữ và hiện lại nếu tạo lại trường cùng mã.

## 5c. Chế độ xem đã lưu

1. Ở danh sách **Thí sinh**, **Trường THPT** và **Hồ sơ** (bảng hoặc Kanban), đặt bộ lọc, sắp xếp, số dòng và cột hiển thị như ý, rồi bấm nút **Chế độ xem** → **Lưu bộ lọc hiện tại…** và đặt tên.
2. Lần sau mở menu **Chế độ xem** và chọn tên để áp dụng lại. Chế độ xem đang khớp với màn hình hiện tại được đánh dấu; mở menu để **Cập nhật** theo bộ lọc hiện có, **Đổi tên** hoặc **Xoá**.
3. Chế độ xem là của riêng bạn. Người có quyền quản lý chế độ xem (trưởng phòng, quản trị) có thể bật **Chia sẻ cho cả tổ chức** để mọi người cùng thấy; chỉ họ sửa hoặc xoá được chế độ xem dùng chung.

## 5d. Workflow tự động

1. **Cài đặt, Workflow tự động**, bấm **Thêm workflow**: chọn đối tượng (hồ sơ tuyển sinh hoặc thí sinh), **Khi** (tạo mới, đổi một trường, đổi giai đoạn, hằng ngày), **Nếu** (điều kiện, tất cả cùng đúng, có thể bỏ trống) và **Thì** (một hoặc nhiều hành động: giao người phụ trách, tạo việc cần làm, gửi tin nhắn, cập nhật mức độ quan tâm hoặc ngành chính, gọi webhook).
2. Bật hoặc tắt từng workflow bằng nút Bật/Tắt. Bấm **Lần chạy gần đây** để xem workflow đã làm gì với từng bản ghi, hành động nào lỗi và vì sao.
3. Lưu ý: workflow hằng ngày chạy lúc 07:00 và chỉ chạy một lần trên mỗi bản ghi. Hành động do workflow làm không kích hoạt thêm workflow khác. Tin nhắn chỉ gửi cho thí sinh đã đồng ý nhận tin. Webhook chỉ gọi địa chỉ https công khai.
4. Cần chạy hàng đợi (`php artisan queue:work`) và bộ lập lịch (`php artisan schedule:work`).

## Chưa có trong MVP

Import danh sách trúng tuyển (giai đoạn 2 trong `docs/TASKS.md`).
