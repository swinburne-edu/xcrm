# TASKS — Lộ trình triển khai

Làm **lần lượt từng task**. Mỗi task xong phải: chạy được trên `http://xcrm.test`, `php artisan test` xanh, tick `[x]`.
Ký hiệu tham chiếu: M = module (03-tinh-nang.md), R = ràng buộc (04-database.md), F = luồng (05-luong-nghiep-vu.md).

---

## Giai đoạn 0 — Khởi tạo

- [x] **0.1 Cài Laravel + Vue Starter Kit** — _đã cài Laravel 13 + Inertia 3 (theo quyết định của người dùng, thay cho Laravel 12)_
  - Thư mục gốc đang có `docs/`, `CLAUDE.md`, `AGENTS.md`, `README.md` → cài vào thư mục tạm rồi chuyển ra:
    ```bash
    composer create-project laravel/vue-starter-kit _app
    # chuyển toàn bộ nội dung _app (kể cả file ẩn .env, .gitignore...) ra thư mục gốc, KHÔNG ghi đè docs/, CLAUDE.md, AGENTS.md, README.md
    # gộp README của Laravel vào cuối README.md nếu cần, rồi xoá _app
    ```
  - `.env`: `APP_NAME=xcrm`, `APP_URL=http://xcrm.test`, `APP_LOCALE=vi`, `APP_TIMEZONE=UTC`, DB MySQL `xcrm` (user `root`, mật khẩu rỗng của Laragon), `QUEUE_CONNECTION=database`.
  - `npm install`, `php artisan migrate`, `npm run build`. Mở `http://xcrm.test` đăng nhập được.
  - `git init` + commit đầu tiên.
- [x] **0.2 Cài package** theo CLAUDE.md mục 4: spatie/laravel-permission, spatie/laravel-activitylog, spatie/laravel-medialibrary, maatwebsite/excel, giggsey/libphonenumber-for-php, pestphp/pest (nếu starter kit chưa có); frontend: @tanstack/vue-table, vuedraggable. Publish config + migration của spatie.
- [x] **0.3 Nền tảng code**
  - Tạo cấu trúc `app/Domain/*` (CLAUDE.md mục 5).
  - Traits `BelongsToOrganization` (global scope + tự gán khi tạo), `HasAuditColumns`, `HasCustomData`.
  - Service `PhoneNormalizer`. Morph map. Timezone hiển thị `Asia/Ho_Chi_Minh`.
  - Tiếng Việt: `lang/vi` (validation, auth, pagination), helper format ngày/tiền ở frontend.
  - Migration `organizations`, thêm cột vào `users` (organization_id, phone, is_active, data_scope…), `teams`, `team_user`.
- [ ] **0.4 Layout CRM** — Sidebar theo `06-giao-dien.md` (các mục chưa làm hiển thị trang "Đang phát triển"), header có bộ chọn mùa (tạm rỗng), ô tìm kiếm, user menu. Breadcrumb.
- [ ] **0.5 Component dùng chung bản đầu** — `DataTable`, `FilterBar` (text/select/date), `EntityPicker`, `ProvinceWardSelect`, `RecordDrawer`, badge. Kèm một chuẩn backend cho list (filter/sort/paginate) dùng lại cho mọi resource.

## Giai đoạn 1 — MVP (P0)

### Danh mục & phân quyền
- [ ] **1.1 Danh mục hệ thống (M1)** — migration + model + seeder: provinces, wards, ward_mappings, priority_areas, priority_groups, subjects, subject_combinations, combination_subjects (R2), admission_methods. Seed theo 04-database.md mục 8. Màn hình `/settings/catalogs` CRUD dạng tab.
- [ ] **1.2 Danh mục tổ chức** — lead_sources, lost_reasons, call_results, tags, taggables + seeder + CRUD.
- [ ] **1.3 Phân quyền** — Roles/permissions seeder (02-phan-quyen.md), Policy mẫu, Global Scope `DataScope` (own/team/territory/all), che SĐT/CCCD. Trang `/settings/users`: user, vai trò, team. Test: counselor không xem được hồ sơ người khác.
- [ ] **1.4 Vùng phụ trách (M8)** — territories, territory_assignments; trang `/settings/territories` gán tỉnh/trường THPT cho user/team; service `TerritoryResolver::ownerFor(HighSchool|Province)`.

### Mùa & ngành
- [ ] **1.5 Mùa tuyển sinh (M5)** — admission_cycles, admission_rounds, cycle_methods (R1, công thức JSON nhập bằng form có cấu trúc + chế độ JSON nâng cao). Trang `/settings/cycles`. Bộ chọn mùa ở header (lưu trong session, mặc định `is_current`) + middleware chia sẻ `currentCycle` cho Inertia. R10.
- [ ] **1.6 Ngành (M4)** — faculties, campuses, majors, major_quotas (R11), major_combinations, major_cycle_methods. Trang danh sách + chi tiết ngành có tab "Theo mùa" (chỉ tiêu, tổ hợp, phương thức).
- [ ] **1.7 Sao chép mùa (F1)** — Action `CloneCycle` + nút trên UI. Test.

### Trường THPT & chiến dịch
- [ ] **1.8 Trường THPT (M2)** — high_schools, school_contacts. Danh sách (lọc tỉnh/hạng/trạng thái/người phụ trách), form, chi tiết với tabs (Người liên hệ, Thí sinh, Timeline; Hoạt động & Thống kê để sau). Import Excel trường THPT.
- [ ] **1.9 Chiến dịch (M6)** — campaigns, campaign_targets. Danh sách, form, luồng duyệt (draft → pending_approval → running…), chi tiết hiển thị KPI (tạm tính từ admission_cases).

### Thí sinh & hồ sơ
- [ ] **1.10 Thí sinh & phụ huynh (M9)** — applicants, guardians, applicant_guardian, applicant_scores, applicant_certificates. `ApplicantDeduplicator` (mục 5). Form tạo có cảnh báo trùng realtime. Trang chi tiết theo 06-giao-dien.md (Timeline để task 1.13). Bảng nhập điểm nhanh môn × học kỳ.
- [ ] **1.11 Pipeline & hồ sơ tuyển sinh (M10)** — pipelines, pipeline_stages (seeder mặc định), admission_cases, case_stage_histories, case_touches, case_interested_majors. Actions: `CreateAdmissionCase` (R5, tự phân bổ theo vùng), `MoveCaseStage` (R6, R7, ghi history), `AssignCase`, `ClaimCase` (kho chung). Trang `/settings/pipelines`.
- [ ] **1.12 Màn hình Hồ sơ tuyển sinh** — `/cases` Kanban + Bảng, bộ lọc, kho chung, RecordDrawer, thao tác hàng loạt (giao người, đổi stage, gắn tag).
- [ ] **1.13 Timeline, task, lịch hẹn (M12)** — activities, tasks, appointments. Component `Timeline` (ghi chú, cuộc gọi + kết quả, task). Observer ghi `stage_changed`, `owner_changed`. Cập nhật `first_contacted_at`, `last_activity_at`. Trang `/tasks` (Hôm nay / Quá hạn / Sắp tới), `/appointments`.

### Thu nhận & báo cáo
- [ ] **1.14 Thu nhận thí sinh (M7)** — Import Excel thí sinh (ImportWizard, queue, import_jobs), forms + endpoint công khai `POST /api/public/leads` (rate limit, honeypot) + đoạn mã nhúng. Luồng F3 (trừ SLA). Test F3.
- [ ] **1.15 Gộp trùng (F6)** — trang "Có thể trùng", màn hình gộp, `MergeApplicants`. Test.
- [ ] **1.16 Dashboard & báo cáo phễu (M16)** — dashboard theo 06-giao-dien.md; báo cáo phễu theo nguồn, chiến dịch, trường THPT, tỉnh, ngành, tư vấn viên (bảng + biểu đồ). Export Excel.
- [ ] **1.17 Demo data & rà soát MVP** — `DemoSeeder`; chạy toàn bộ luồng F1, F3, F4 (đến bước 2), F6 bằng tay; sửa lỗi; viết hướng dẫn sử dụng ngắn `docs/HUONG-DAN.md`.

## Giai đoạn 2 — P1

- [ ] **2.1 Hoạt động tại trường + check-in QR (M3, F2)** — school_activities, school_activity_user, activity_attendees; lịch + bảng; trang check-in công khai; mã QR; tab Hoạt động & Thống kê theo mùa ở trang trường THPT.
- [ ] **2.2 Nguyện vọng (M11)** — applications (R3, R4), applicant_documents (medialibrary), `ScoreCalculator` + test nhiều công thức, màn hình nguyện vọng trong hồ sơ, danh sách xét tuyển theo ngành.
- [ ] **2.3 Tiến độ chỉ tiêu** — `/quotas` theo ngành, cảnh báo thiếu/thừa; job tổng hợp định kỳ.
- [ ] **2.4 Quy tắc phân bổ & SLA (M8)** — assignment_rules, round-robin/theo tải, scheduler kiểm tra SLA, thu hồi tự động, thông báo (database notifications).
- [ ] **2.5 Custom field** — custom_field_definitions, form động, hiển thị/sửa inline, lọc trong FilterBar.
- [ ] **2.6 Saved view** — saved_views, SavedViewMenu cho cases, applicants, high_schools.
- [ ] **2.7 Webhook lead ads** — Facebook Lead Ads, TikTok (verify chữ ký, map field, ghi touch).
- [ ] **2.8 Chi phí chiến dịch** — campaign_costs, chi phí/thí sinh, chi phí/nhập học, ROI; so sánh giữa các mùa.
- [ ] **2.9 F5** — job tạo hồ sơ cho học sinh đến năm tốt nghiệp khi mùa active.

## Giai đoạn 3 — P2

- [ ] **3.1 Truyền thông** — message_templates, message_logs, driver Zalo ZNS / SMS / email (interface `MessageChannel`), gửi hàng loạt theo bộ lọc.
- [ ] **3.2 Workflow** — workflows, workflow_runs, engine trigger/condition/action chạy qua queue, UI dựng workflow đơn giản.
- [ ] **3.3 Đối soát dữ liệu Bộ** — import danh sách trúng tuyển/xác nhận nhập học, khớp CCCD/SĐT, báo cáo chênh lệch.
- [ ] **3.4 Cổng thí sinh** — đăng nhập OTP, nộp hồ sơ xét sớm, tra cứu, xác nhận nhập học; trang "Tính điểm & gợi ý ngành".
- [ ] **3.5 API & webhook bàn giao** — Sanctum token, REST cho applicants/cases/applications, webhook sự kiện `case.enrolled`.
- [ ] **3.6 Bản đồ** — bản đồ trường THPT + heatmap thí sinh/nhập học theo tỉnh.
- [ ] **3.7 Tổng đài** — click-to-call, nhận cuộc gọi đến, ghi âm vào timeline.
