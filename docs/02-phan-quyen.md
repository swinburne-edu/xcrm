# 02 — Vai trò & phân quyền

Phân quyền 3 lớp:
1. **Quyền thao tác** `{object}.{action}` (spatie/laravel-permission). Action: `view`, `create`, `update`, `delete`, `export`, `import`, `assign`, `merge`.
2. **Phạm vi dữ liệu** (Global Scope `DataScope`): `own` (owner_id = tôi), `team` (team_id thuộc nhóm tôi), `territory` (tỉnh/trường THPT thuộc vùng phụ trách), `all`.
3. **Ẩn trường nhạy cảm**: CCCD, SĐT đầy đủ (che `09******89`) với vai trò không có quyền `applicant.view_sensitive`.

## Vai trò mặc định (seed)

| Mã vai trò | Tên | Phạm vi | Quyền chính |
| --- | --- | --- | --- |
| `admin` | Quản trị hệ thống | all | Toàn quyền, cấu hình, người dùng, custom field, workflow |
| `admission_head` | Trưởng phòng tuyển sinh | all | Mùa, chỉ tiêu, duyệt chiến dịch, quy tắc phân bổ, mọi báo cáo, export |
| `team_lead` | Trưởng nhóm khu vực | territory | Chia/thu hồi hồ sơ, KPI nhóm, sửa hồ sơ trong vùng |
| `counselor` | Cán bộ tư vấn | own + kho chung | Tư vấn, cập nhật thí sinh/hồ sơ/nguyện vọng, lịch hẹn, task, gửi tin |
| `school_officer` | Cán bộ phụ trách trường THPT | territory (trường) | Trường THPT, người liên hệ, hoạt động tại trường, check-in |
| `marketing` | Marketing | all (chỉ đọc hồ sơ) | Chiến dịch, form, UTM, chi phí, báo cáo kênh |
| `admission_clerk` | Thư ký xét tuyển | all | Duyệt giấy tờ, nguyện vọng, nhập kết quả, đối soát dữ liệu Bộ |
| `faculty_viewer` | Khoa (chỉ xem) | ngành của khoa | Tiến độ chỉ tiêu, danh sách trúng tuyển |
| `ambassador` | Cộng tác viên / Đại sứ | own (người mình giới thiệu) | Tạo thí sinh, xem trạng thái, SĐT bị che |

## Kho chung (pool)

Hồ sơ có `owner_id = null` nằm trong kho chung. `counselor` thấy kho chung của team/vùng mình và có thể "Nhận" (assign cho chính mình).

## Audit log

Ghi log (spatie/activitylog) cho: export file, xem CCCD, xoá, gộp trùng, đổi người phụ trách, đổi quyền.
Mục đích: tuân thủ Luật Bảo vệ dữ liệu cá nhân (hiệu lực 1/1/2026) và ngăn mang dữ liệu ra ngoài.
