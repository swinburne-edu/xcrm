# 01 — Tổng quan & phạm vi

## Mục tiêu

xcrm quản lý toàn bộ quy trình tuyển sinh: từ khi có thí sinh tiềm năng đến khi thí sinh **xác nhận nhập học**,
sau đó bàn giao danh sách sang hệ thống đào tạo (API hoặc Excel).

## Phạm vi

**Trong phạm vi**
- Trường THPT, người liên hệ, hoạt động tư vấn tại trường
- Ngành, tổ hợp xét tuyển, chỉ tiêu theo mùa
- Mùa tuyển sinh, đợt xét tuyển, phương thức xét tuyển
- Chiến dịch tuyển sinh (offline + online), đo hiệu quả
- Thí sinh, phụ huynh, điểm, hồ sơ, nguyện vọng
- Tư vấn: cuộc gọi, ghi chú, task, lịch hẹn, tin nhắn
- Phân bổ thí sinh, phân vùng phụ trách
- Tự động hoá (workflow), báo cáo

**Ngoài phạm vi:** lớp học, thời khoá biểu, điểm danh, kết quả học tập, học phí sau nhập học.

## Giả định

- Đơn vị dùng: một trường đại học (có thể nhiều cơ sở). Mô hình mùa/đợt đủ linh hoạt cho cao đẳng xét tuyển quanh năm.
- Single-tenant trước, nhưng mọi bảng nghiệp vụ có `organization_id` để mở rộng SaaS.

## Nguyên tắc thiết kế

1. **Cấu hình theo mùa, không hard-code**: phương thức, tổ hợp, công thức tính điểm, chỉ tiêu, mốc thời gian đều gắn `cycle_id`.
2. **Tách con người khỏi cơ hội**: `applicants` (thí sinh), `guardians` (phụ huynh) là con người; `admission_cases` là hồ sơ theo mùa (cơ hội); `applications` là nguyện vọng trong hồ sơ.
3. **Một timeline cho mọi thứ**: bảng `activities` đa hình (polymorphic).
4. **Chống trùng ngay khi nhập**: SĐT chuẩn hoá, CCCD, email.
5. **Học từ Twenty**: custom field (cột JSON `custom_data`), saved view, Kanban, bộ lọc động.
6. **Phân quyền theo dòng dữ liệu**: own / team / territory / all.

## Quy chế tuyển sinh 2026 (Thông tư 06/2026/TT-BGDĐT) ảnh hưởng thiết kế

| Quy định | Cách xử lý |
| --- | --- |
| Tối đa 5 phương thức, không tính xét tuyển thẳng | Validate khi lưu `cycle_methods` |
| Tổ hợp bắt buộc có Toán hoặc Ngữ văn, trọng số ≥ 1/3 tổng điểm (thang 30) | Validate `combination_subjects` |
| Thí sinh đăng ký tối đa 15 nguyện vọng | `applications.preference_order` 1–15 |
| Không chia chỉ tiêu theo phương thức/tổ hợp, trừ xét tuyển thẳng | `major_quotas.quota_total`, `quota_direct` |
| Năm 2026: đăng ký NV 2/7–14/7; các đợt sau tháng 8–12 | `admission_rounds` lưu mốc, dùng nhắc việc |
| Mã phương thức của Bộ: 100 (thi THPT), 200 (học bạ), 301 (xét thẳng), 401 (ĐGNL/ĐGTD trường tự tổ chức)… | Seed `admission_methods` |

Quy chế thay đổi hằng năm → luôn để là cấu hình.

Nguồn: baoquocte.vn, znews.vn, daibieunhandan.vn, thuvienphapluat.vn, lsvn.vn (tháng 2–5/2026).
