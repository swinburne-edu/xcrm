# 05 — Luồng nghiệp vụ (dùng làm kịch bản test)

Mỗi luồng nên có ít nhất một Feature test Pest tương ứng (`tests/Feature/Flows/`).

## F1. Chuẩn bị mùa tuyển sinh (tháng 10 – tháng 2)

1. Trưởng phòng tạo mùa mới, chọn **Sao chép từ mùa trước** → copy rounds (dời năm), cycle_methods, major_combinations, major_cycle_methods, major_quotas (giữ chỉ tiêu cũ làm gợi ý).
2. Cập nhật phương thức (≤ 5, R1), công thức điểm, đợt và mốc thời gian theo quy chế mới.
3. Nhập chỉ tiêu, học phí, điểm chuẩn năm trước cho từng ngành.
4. Xem báo cáo hiệu quả trường THPT mùa trước → xếp hạng lại A/B/C, phân vùng phụ trách.
5. Marketing tạo chiến dịch (draft) → gửi duyệt → trưởng phòng duyệt (running).
6. Đặt mùa mới là `is_current`, trạng thái `active`.

**Test:** sao chép mùa tạo đúng số bản ghi; thêm phương thức thứ 6 (không xét thẳng) bị từ chối.

## F2. Hoạt động tư vấn tại trường THPT

1. Cán bộ phụ trách tạo hoạt động, gắn trường + chiến dịch → hệ thống sinh `checkin_token` + mã QR.
2. Học sinh quét QR → `/checkin/{token}` → form: họ tên, SĐT, lớp, ngành quan tâm, đồng ý nhận tin.
3. Hệ thống: chống trùng → tạo/cập nhật applicant (high_school_id = trường của hoạt động, KV lấy theo trường) → tạo case mùa hiện tại nếu chưa có (stage `new`, source "Tư vấn tại trường THPT", campaign, school_activity_id) → ghi `activity_attendees`, `case_touches` → phân bổ theo vùng của trường.
4. Sau buổi: cán bộ nhập `actual_attendees`, `cost`, `result_note`, status `done`.

**Test:** check-in 2 lần cùng SĐT chỉ tạo 1 applicant, 1 case, 2 touches (hoặc 1 attendee do unique).

## F3. Thí sinh từ kênh online

1. Form website gửi `POST /api/public/leads` (kèm `form_uuid`, UTM) hoặc webhook Facebook Lead Ads.
2. Chuẩn hoá SĐT, chống trùng. Trùng → thêm touch, cập nhật trường trống. Mới → applicant + case (stage `new`).
3. Phân bổ: vùng (theo trường THPT/tỉnh) → nếu không khớp vùng thì vào kho chung của team mặc định.
4. Tạo task "Gọi lần đầu" hạn = now + SLA của stage `new`.
5. (P1) Quá SLA chưa có activity `call` → thông báo trưởng nhóm; quá N ngày không hoạt động → thu hồi về kho chung (ghi activity `owner_changed`).

**Test:** lead mới tạo đủ applicant/case/touch/task; lead trùng không tạo applicant mới.

## F4. Tư vấn đến nhập học

1. Tư vấn viên gọi → ghi activity `call` + call_result → nếu `is_contacted` và `first_contacted_at` null thì set; stage `new` → `contacted`.
2. Cập nhật điểm, ngành quan tâm, temperature; đặt lịch hẹn nếu cần.
3. Nộp hồ sơ xét sớm: tạo applications (R3, R4) → `ScoreCalculator` tính điểm → upload giấy tờ → thư ký duyệt → application `eligible`, case → `eligible`.
4. Trong thời gian đăng ký trên hệ thống Bộ: nhắc thí sinh, ghi `our_preference_rank`, case → `moet_registered`.
5. Có kết quả: import danh sách trúng tuyển (Excel) → khớp theo CCCD/SĐT → application `admitted`, case → `admitted`.
6. Chăm sóc → thí sinh xác nhận → application `confirmed`, case → `confirmed` → nộp hồ sơ nhập học → case `enrolled` (won), set `enrolled_major_id`, `enrolled_at`.
7. Không nhập học → case `lost` + `lost_reason_id` (R6).

**Test:** mỗi lần đổi stage ghi `case_stage_histories` + activity `stage_changed`; chuyển `lost` thiếu lý do bị từ chối; status case đồng bộ stage.type.

## F5. Nuôi dưỡng học sinh lớp 10–11

1. Học sinh lớp 10, 11 → tạo applicant (`current_grade`, `graduation_year`) nhưng **không** tạo case nếu mùa hiện tại không khớp năm tốt nghiệp.
2. Khi một mùa chuyển `active` → job `CreateCasesForGraduatingApplicants` tạo case cho applicant có `graduation_year = cycle.year`, chưa có case, đưa vào kho chung/phân bổ theo vùng.
3. (P2) Gửi nội dung hướng nghiệp định kỳ theo phân khúc.

## F6. Gộp hồ sơ trùng

1. Danh sách "Có thể trùng" (cùng tên + cùng trường THPT, hoặc SĐT phụ huynh trùng).
2. Người dùng chọn bản giữ lại, chọn giá trị từng trường → `MergeApplicants`.

**Test:** sau gộp, mọi bản ghi con thuộc bản giữ lại; bản bị gộp có `merged_into_id` và bị soft delete.
