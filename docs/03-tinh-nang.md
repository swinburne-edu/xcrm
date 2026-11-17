# 03 — Module & tính năng

Ưu tiên: **P0** = MVP · **P1** = giai đoạn 2 · **P2** = giai đoạn 3.

## M1. Danh mục nền tảng (P0)
- Địa giới hành chính mới: tỉnh/thành, xã/phường (đã bỏ cấp huyện từ 7/2025). Bảng `ward_mappings` ánh xạ địa giới cũ → mới.
- Khu vực ưu tiên (KV1, KV2-NT, KV2, KV3), đối tượng ưu tiên chính sách.
- Môn học, tổ hợp xét tuyển (A00, A01, D01…) + môn trong tổ hợp, trọng số.
- Phương thức xét tuyển (mã Bộ), nguồn thí sinh, lý do thất bại, kết quả cuộc gọi, tag.
- Màn hình CRUD chung cho danh mục.

## M2. Trường THPT (P0)
- Danh sách (bảng + lọc theo tỉnh, hạng, trạng thái hợp tác, người phụ trách), chi tiết, form.
- Hồ sơ: mã, tên, loại hình, địa chỉ, KV ưu tiên, quy mô khối 12, tổ hợp mạnh.
- Hạng tiềm năng A/B/C, trạng thái hợp tác, ngày ký MOU, cán bộ phụ trách.
- Tab trong trang chi tiết: Người liên hệ · Hoạt động · Thí sinh · Thống kê theo mùa · Timeline.
- Thống kê theo mùa: số thí sinh, hồ sơ, trúng tuyển, nhập học từ trường (tự tính).
- Import danh sách trường từ Excel.
- (P2) Bản đồ + heatmap.

## M3. Hoạt động tại trường THPT (P1)
- Loại: tư vấn tại trường, ngày hội hướng nghiệp, tham quan campus, trao học bổng, gặp BGH.
- Lịch (calendar view), nhân sự tham gia, chi phí, số tham dự dự kiến/thực tế, trạng thái.
- **Check-in QR**: link công khai `/checkin/{token}` → form ngắn → chống trùng → tạo/cập nhật thí sinh + hồ sơ mùa hiện tại + `activity_attendees` + `case_touches`.
- Báo cáo sau hoạt động.

## M4. Ngành & chương trình (P0)
- Khoa, cơ sở, ngành (mã Bộ 7 số, mã xét tuyển, loại chương trình, bậc).
- Gán tổ hợp và phương thức cho ngành **theo mùa**.
- Chỉ tiêu theo mùa: tổng, xét thẳng, mục tiêu nhập học, học phí, ngưỡng đầu vào, điểm chuẩn năm trước (JSON).
- Nội dung tư vấn: mô tả, cơ hội việc làm, FAQ.
- (P1) Dashboard tiến độ chỉ tiêu realtime: quan tâm / hồ sơ / đủ ĐK / trúng tuyển / xác nhận nhập học vs chỉ tiêu, cảnh báo thiếu/thừa.

## M5. Mùa tuyển sinh, đợt, phương thức (P0)
- Mùa: tên, năm, bậc, thời gian, trạng thái (`preparing`, `active`, `closed`), mục tiêu, mùa hiện tại.
- **Sao chép cấu hình từ mùa trước** (ngành-tổ hợp, ngành-phương thức, đợt, chỉ tiêu).
- Đợt xét tuyển: xét sớm, đợt 1 hệ thống Bộ, bổ sung — mốc nhận hồ sơ, công bố, hạn xác nhận.
- Phương thức trong mùa (≤ 5 không tính xét thẳng): công thức điểm (JSON), điều kiện, giấy tờ cần nộp.
- Bộ chọn mùa ở header: mọi màn hình lọc theo mùa đang chọn.

## M6. Chiến dịch (P0)
- Loại, kênh, chiến dịch cha/con, thời gian, ngân sách, chi phí thực, người phụ trách, trạng thái (nháp → chờ duyệt → đang chạy → tạm dừng → kết thúc).
- Mã chiến dịch = `utm_campaign`. Đối tượng mục tiêu: ngành, tỉnh, trường THPT.
- KPI: thí sinh, hồ sơ, trúng tuyển, nhập học, chi phí/thí sinh, chi phí/nhập học, ROI.
- (P1) Chi phí theo ngày (`campaign_costs`).

## M7. Thu nhận thí sinh
- P0: nhập tay, import Excel (map cột, xem trước, báo lỗi dòng), form nhúng website có UTM (endpoint `POST /api/public/leads`).
- P1: webhook Facebook Lead Ads, TikTok Lead, Google Ads; Zalo OA.
- P2: tổng đài tạo hồ sơ khi có cuộc gọi đến.
- Chống trùng: `phone_normalized` → `email` → `citizen_id_hash`. Trùng thì thêm `case_touches`, không tạo bản ghi mới.
- Màn hình **gộp trùng** (chọn bản giữ lại, chọn giá trị từng trường).

## M8. Phân bổ & phân vùng
- P0: vùng phụ trách (tỉnh, trường THPT → user/team); khi tạo hồ sơ tự gán theo vùng của trường THPT/tỉnh; giao thủ công; nhận từ kho chung.
- P1: quy tắc phân bổ (theo vùng → xoay vòng → theo tải → theo ngành), SLA liên hệ lần đầu, cảnh báo quá hạn, tự thu hồi khi `last_activity_at` quá N ngày.

## M9. Thí sinh & phụ huynh (P0)
- Thông tin cá nhân, trường THPT, lớp, năm tốt nghiệp, KV và đối tượng ưu tiên (mặc định KV lấy từ trường).
- Điểm học bạ / thi THPT / ĐGNL / ĐGTD / năng khiếu (bảng nhập nhanh theo môn × học kỳ), chứng chỉ ngoại ngữ.
- Phụ huynh n-n, vai trò, người quyết định.
- Trang chi tiết: header (tên, SĐT, trường, giai đoạn hồ sơ mùa hiện tại) · tab Tổng quan · Điểm · Nguyện vọng · Phụ huynh · Giấy tờ · Timeline.

## M10. Pipeline hồ sơ tuyển sinh (P0)
- Mỗi thí sinh một hồ sơ / mùa. Pipeline cấu hình được (tên, thứ tự, loại open/won/lost, xác suất, SLA, trường bắt buộc).
- Kanban kéo thả + bảng; chuyển sang `lost` bắt buộc lý do.
- Lịch sử chuyển giai đoạn (`case_stage_histories`), thời gian ở mỗi giai đoạn.
- Thao tác hàng loạt: giao người, đổi giai đoạn, gắn tag, gửi tin (P2).

## M11. Nguyện vọng & xét tuyển (P1)
- Nguyện vọng: ngành, phương thức, tổ hợp, đợt, thứ tự (1–15), điểm, trạng thái.
- `ScoreCalculator` tự tính điểm theo `cycle_methods.score_formula` + điểm ưu tiên + điểm cộng; lưu `score_breakdown`.
- Hồ sơ xét sớm: giấy tờ, duyệt.
- Danh sách xét tuyển theo ngành, sắp xếp điểm, so chỉ tiêu.
- (P2) Import + đối soát danh sách từ hệ thống của Bộ (Excel).

## M12. Tương tác, task, lịch hẹn (P0)
- Timeline đa hình trên thí sinh, hồ sơ, trường THPT, người liên hệ.
- Ghi cuộc gọi (kết quả, ghi chú, hẹn gọi lại), ghi chú, task (của tôi / hôm nay / quá hạn), lịch hẹn (tư vấn, tham quan, phỏng vấn, thi năng khiếu) + có đến/vắng.

## M13. Truyền thông đa kênh (P2)
- Zalo ZNS, SMS brandname, email; mẫu tin có biến `{{applicant.full_name}}`; gửi hàng loạt theo phân khúc; log gửi.
- Tổng đài: click-to-call, ghi âm, popup.

## M14. Workflow tự động (P2)
- Trigger: tạo bản ghi, đổi trường, đổi giai đoạn, theo lịch. Điều kiện. Hành động: giao người, tạo task, gửi tin, cập nhật trường, gọi webhook.

## M15. Cổng thí sinh & gợi ý ngành (P2)
- Trang công khai "Tính điểm & gợi ý ngành" (đồng thời là form thu thí sinh).
- Cổng thí sinh đăng nhập bằng OTP: nộp hồ sơ xét sớm, tra cứu, xác nhận nhập học.

## M16. Báo cáo & nền tảng
- P0: dashboard tổng quan mùa (phễu theo giai đoạn), phễu theo nguồn/chiến dịch/trường THPT/tỉnh/ngành/tư vấn viên.
- P1: custom field (`custom_field_definitions` + `custom_data`), saved view (cột, lọc, sắp xếp, kiểu xem), so sánh giữa các mùa, KPI tư vấn viên.
- P2: REST API (Sanctum) + webhook bàn giao danh sách nhập học.
- Luôn có: tìm kiếm toàn cục (Ctrl+K), import/export, audit log.
