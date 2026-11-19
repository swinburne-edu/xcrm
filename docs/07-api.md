# REST API và webhook (task 3.5)

Cho hệ thống khác (đào tạo, kế toán, báo cáo) đọc và ghi dữ liệu tuyển sinh, và nhận thông báo khi thí sinh trúng tuyển hoặc xác nhận nhập học.

## Xác thực

- Vào **Cài đặt, API & Webhook** (cần quyền `api_token.manage`), tạo token. Token chỉ hiện một lần. Chọn **Chỉ đọc** hoặc **Đọc và ghi**, có thể đặt ngày hết hạn.
- Gửi kèm mọi request: `Authorization: Bearer <token>` và `Accept: application/json`.
- Token hoạt động **như chính người tạo**: cùng quyền và cùng phạm vi dữ liệu như trên màn hình. Số điện thoại bị che nếu người đó không có quyền xem đầy đủ. CCCD không bao giờ được trả về.
- Giới hạn 120 request mỗi phút cho mỗi token. Chưa có token hoặc token sai: `401`. Token thiếu quyền đọc/ghi: `403`.

## Endpoint (gốc `/api/v1`)

| Phương thức | Đường dẫn | Quyền token | Việc |
| --- | --- | --- | --- |
| GET | `/me` | read | Người sở hữu token |
| GET | `/applicants` | read | Danh sách thí sinh. Lọc `filter[province_id]`, `filter[high_school_id]`, `filter[graduation_year]`, `filter[q]`…, `sort`, `per_page` (10, 25, 50, 100), `page` |
| GET | `/applicants/{uuid}` | read | Một thí sinh |
| POST | `/applicants` | write | Tạo thí sinh (cần họ tên và số điện thoại hoặc email). Chống trùng theo SĐT/email: trả `200` với `created: false` và thí sinh đã có |
| PATCH | `/applicants/{uuid}` | write | Sửa `full_name`, `phone`, `email`, `high_school_id`, `province_id`, `graduation_year` |
| GET | `/cases`, `/cases/{uuid}` | read | Hồ sơ tuyển sinh. Lọc `stage_id`, `status`, `owner_id`, `cycle_id`, `source_id`, `campaign_id` |
| POST | `/cases/{uuid}/stage` | write | Chuyển giai đoạn: `{ "stage_id": 5, "lost_reason_id": null }` |
| GET | `/applications`, `/applications/{id}` | read | Nguyện vọng. Lọc `status`, `major_id`, `round_id`, `cycle_method_id` |
| POST | `/cases/{uuid}/applications` | write | Thêm nguyện vọng: `major_id`, `cycle_method_id`, `combination_id`, `round_id`, `preference_order` |
| POST | `/applications/{id}/{action}` | write | `submit`, `eligible`, `ineligible`, `conditional`, `admit`, `reject`, `confirm`, `withdraw` (cần quyền `application.update` hoặc `application.review` như trên màn hình) |

Danh sách trả `{ "data": [...], "meta": { "current_page", "per_page", "total", "last_page" } }`; một bản ghi trả `{ "data": {...} }`. Lỗi kiểm tra dữ liệu: `422` với `errors`.

```bash
curl -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "https://xcrm.example.edu.vn/api/v1/applicants?filter[graduation_year]=2027&per_page=50"
```

## Webhook

Cài đặt, API & Webhook, **Thêm webhook**: địa chỉ **https công khai** (không nhận localhost và địa chỉ nội bộ), chọn sự kiện. Khoá bí mật chỉ hiện một lần (có thể Đổi khoá).

| Sự kiện | Khi nào |
| --- | --- |
| `application.admitted` | Nguyện vọng chuyển sang Trúng tuyển |
| `case.enrolled` | Thí sinh xác nhận nhập học |

Mỗi sự kiện gửi `POST` JSON:

```json
{
  "event": "case.enrolled",
  "occurred_at": "2027-08-20T09:15:00+07:00",
  "data": {
    "applicant": { "uuid": "...", "code": "TS000123", "full_name": "...", "gender": "male", "dob": "2009-05-01", "phone": "84912345678", "email": "...", "province": "...", "high_school": "...", "graduation_year": 2027 },
    "case": { "uuid": "...", "cycle": "Tuyển sinh 2027" },
    "application": { "id": 55, "major": "...", "major_code": "7480201", "admission_code": "...", "method": "...", "combination": "A00", "round": "Đợt 1", "final_score": 24.5, "status": "confirmed" }
  }
}
```

Header: `X-Xcrm-Event`, `X-Xcrm-Delivery` (mã duy nhất của lần gửi, dùng để chống xử lý trùng) và `X-Xcrm-Signature: sha256=<HMAC-SHA256 của nội dung gửi, khoá bí mật>`. Máy nhận cần kiểm tra chữ ký rồi trả `2xx`.

Nếu máy nhận lỗi hoặc không trả `2xx`, hệ thống thử lại sau 1 phút, 10 phút, 1 giờ (tối đa 4 lần), sau đó đánh dấu **Lỗi**; bấm **Gửi lại** ở trang cài đặt. Cần chạy hàng đợi (`php artisan queue:work`). Nội dung không chứa CCCD.
