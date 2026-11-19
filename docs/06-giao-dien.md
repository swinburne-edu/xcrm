# 06 — Giao diện

Phong cách: gọn, nhiều dữ liệu, giống Twenty CRM / Linear. Sidebar trái, header có **bộ chọn mùa tuyển sinh**, ô tìm kiếm (Ctrl+K), thông báo, avatar.
Dùng shadcn-vue + Tailwind. Ngôn ngữ giao diện: tiếng Việt. Định dạng: ngày `dd/MM/yyyy`, tiền `1.250.000 ₫`.

## Menu (sidebar)

```
Tổng quan                  /dashboard
Tuyển sinh
  ├ Hồ sơ tuyển sinh       /cases            (Kanban | Bảng)
  ├ Thí sinh               /applicants
  ├ Có thể trùng           /applicants/duplicates
  ├ Nguyện vọng            /applications
  └ Lịch hẹn               /appointments
Công việc của tôi          /tasks
Trường THPT
  ├ Danh sách trường       /high-schools
  ├ Người liên hệ          /school-contacts
  ├ Bản đồ                 /map
  └ Hoạt động tại trường   /school-activities (Lịch | Bảng)
Marketing
  ├ Chiến dịch             /campaigns
  ├ Chi phí & hiệu quả     /campaigns/performance
  ├ Form thu thập          /forms
  ├ Cuộc gọi               /calls
  └ Tin nhắn đã gửi        /messages
Đào tạo & chỉ tiêu
  ├ Ngành                  /majors
  └ Tiến độ chỉ tiêu       /quotas
Báo cáo                    /reports
Cài đặt
  ├ Mùa tuyển sinh         /settings/cycles
  ├ Pipeline               /settings/pipelines
  ├ Danh mục               /settings/catalogs
  ├ Vùng phụ trách         /settings/territories
  ├ Người dùng & vai trò   /settings/users
  ├ Trường tuỳ chỉnh       /settings/custom-fields
  ├ Tích hợp lead ads      /settings/lead-integrations
  ├ Mẫu tin nhắn           /settings/message-templates
  ├ Tích hợp tổng đài      /settings/call-integrations
  ├ Workflow tự động       /settings/workflows
  └ Quy tắc phân bổ        /settings/assignment-rules
Hướng dẫn sử dụng          /help

Trang công khai (không có trong menu cán bộ)
  ├ Tính điểm & gợi ý ngành /tuyen-sinh
  └ Cổng thí sinh (OTP)     /cong-thi-sinh
```

## Component dùng chung (`resources/js/components/crm/`)

| Component | Mô tả |
| --- | --- |
| `DataTable.vue` | TanStack Table: phân trang server, sắp xếp, chọn cột, chọn nhiều dòng + thao tác hàng loạt, sticky header |
| `FilterBar.vue` | Bộ lọc động theo định nghĩa field (text, select, date range, user, số); hỗ trợ custom field |
| `SavedViewMenu.vue` | Lưu/chọn view (P1) |
| `KanbanBoard.vue` | Cột theo stage, kéo thả (vuedraggable), đếm số thẻ, tải thêm theo cột |
| `RecordDrawer.vue` | Panel trượt bên phải xem nhanh bản ghi khi click dòng/thẻ |
| `Timeline.vue` | Danh sách activities + ô nhập nhanh: Ghi chú / Cuộc gọi / Task |
| `EntityPicker.vue` | Combobox tìm kiếm async (trường THPT, ngành, user, thí sinh) |
| `ProvinceWardSelect.vue` | Chọn tỉnh → xã/phường |
| `StageBadge.vue`, `TemperatureBadge.vue`, `TierBadge.vue` | Badge màu |
| `ImportWizard.vue` | Upload → map cột → xem trước lỗi → chạy (queue) → kết quả |
| `PhoneLink.vue` | Hiển thị SĐT (che theo quyền), click-to-call (P2) |

Backend trả dữ liệu bảng theo một chuẩn chung: `GET /{resource}?filter[...]=...&sort=-created_at&page=1&per_page=50&view_id=` (dùng `spatie/laravel-query-builder` nếu cần — hỏi trước khi cài).

## Màn hình chính

**Hồ sơ tuyển sinh `/cases`** — mặc định Kanban theo stage của mùa đang chọn. Thẻ: tên thí sinh, trường THPT, ngành quan tâm, người phụ trách (avatar), temperature, ngày hành động tiếp theo (đỏ nếu quá hạn). Lọc: người phụ trách (mặc định "Của tôi" với counselor), nguồn, chiến dịch, tỉnh, trường, ngành, temperature, tag. Nút "Kho chung".

**Chi tiết thí sinh `/applicants/{uuid}`** — cột trái: thông tin + phụ huynh + tag + custom field (sửa inline). Giữa: tabs Timeline (mặc định) · Điểm · Nguyện vọng · Giấy tờ · Lịch hẹn. Phải: hồ sơ mùa hiện tại (stage dạng stepper, người phụ trách, nguồn, chiến dịch, next action), danh sách task.

**Chi tiết trường THPT** — header: tên, hạng, trạng thái hợp tác, người phụ trách, KPI mùa hiện tại (thí sinh / hồ sơ / trúng tuyển / nhập học). Tabs: Người liên hệ · Hoạt động · Thí sinh · Thống kê theo mùa · Timeline.

**Dashboard** — thẻ số (thí sinh mới tuần này, hồ sơ, trúng tuyển, nhập học / mục tiêu), phễu theo stage, biểu đồ thí sinh theo ngày, top nguồn, top trường THPT, bảng tiến độ chỉ tiêu theo ngành, task quá hạn của tôi.
