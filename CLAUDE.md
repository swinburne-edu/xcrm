# CLAUDE.md — Hướng dẫn cho AI khi code dự án xcrm

> File này được AI coding agent (Claude Code, Cursor, Copilot…) đọc đầu tiên.
> Đọc hết file này và các tài liệu trong `docs/` trước khi viết code.

## 1. Dự án là gì

**xcrm** là CRM tuyển sinh cho trường đại học/cao đẳng tại Việt Nam, xây theo cảm hứng từ Twenty CRM
(metadata-driven: custom field, saved view, Kanban, timeline).

Phạm vi: từ lúc có thí sinh tiềm năng → tư vấn → hồ sơ xét tuyển → nguyện vọng → trúng tuyển → **xác nhận nhập học**.
**Không** làm phần đào tạo (lớp, thời khoá biểu, điểm danh, học phí sau nhập học).

Đối tượng cốt lõi: Trường THPT, Ngành, Mùa tuyển sinh, Chiến dịch, Thí sinh, Hồ sơ tuyển sinh, Nguyện vọng.

## 2. Tài liệu bắt buộc đọc

| File | Nội dung |
| --- | --- |
| `docs/01-tong-quan.md` | Phạm vi, nguyên tắc thiết kế, quy chế tuyển sinh 2026 |
| `docs/02-phan-quyen.md` | Vai trò, phạm vi dữ liệu |
| `docs/03-tinh-nang.md` | 16 module, ưu tiên P0/P1/P2 |
| `docs/04-database.md` | Quy ước DB, ràng buộc, index, dữ liệu seed |
| `docs/database.dbml` | **Schema chuẩn** (nguồn sự thật cho migration) — xem trực quan tại dbdiagram.io |
| `docs/05-luong-nghiep-vu.md` | Luồng nghiệp vụ = kịch bản test |
| `docs/06-giao-dien.md` | Cấu trúc menu, màn hình, component dùng chung |
| `docs/TASKS.md` | **Thứ tự triển khai** — làm lần lượt từng task, tick khi xong |

Khi tài liệu mâu thuẫn: `database.dbml` > `04-database.md` > các file khác. Nếu vẫn không rõ, **hỏi người dùng**, đừng tự đoán nghiệp vụ.

## 3. Môi trường

- Windows + **Laragon**, thư mục `C:\laragon\www\xcrm`, URL `http://xcrm.test`
- PHP 8.3+, Composer, Node 20+, MySQL 8 (Laragon), DB name: `xcrm`
- Lệnh chạy: `php artisan serve` không cần (Laragon tự host). Dev frontend: `npm run dev`
- Test: `php artisan test` (dùng SQLite in-memory hoặc DB `xcrm_test`)

## 4. Stack (đã chốt — không tự đổi)

| Lớp | Công nghệ |
| --- | --- |
| Backend | Laravel 13, PHP 8.3+ (máy dev: PHP 8.4) |
| Frontend | Inertia.js 3 + Vue 3 + TypeScript (Laravel Vue Starter Kit), Tailwind CSS 4, shadcn-vue |
| Bảng dữ liệu | TanStack Table (vue) |
| Kanban | vuedraggable (SortableJS) |
| Phân quyền | spatie/laravel-permission |
| Audit log | spatie/laravel-activitylog |
| Excel | maatwebsite/excel |
| File | spatie/laravel-medialibrary |
| SĐT | giggsey/libphonenumber-for-php |
| Queue | database driver (dev), Redis + Horizon (prod) |
| Test | Pest |

## 5. Quy ước code

### Cấu trúc thư mục backend

```
app/
  Domain/
    Catalog/        # tỉnh, xã, KV ưu tiên, môn, tổ hợp, nguồn, lý do...
    HighSchool/     # high_schools, school_contacts, school_activities
    Major/          # faculties, campuses, majors, major_quotas...
    Cycle/          # admission_cycles, rounds, methods, cycle_methods
    Campaign/
    Applicant/      # applicants, guardians, scores, documents
    Admission/      # pipelines, stages, admission_cases, applications
    Activity/       # activities, tasks, appointments
    Platform/       # custom fields, saved views, assignment rules, workflows
    Organization/   # organizations, teams (User vẫn ở app/Models/User.php — Fortify dùng)
    Shared/         # dùng chung: Concerns/ (BelongsToOrganization, HasAuditColumns, HasCustomData),
                    # Scopes/OrganizationScope, Services/ (CurrentOrganization, PhoneNormalizer),
                    # Support/BlueprintMacros ($table->organizationId(), $table->auditColumns())
  Each domain:  Models/  Enums/  Actions/  Services/  Observers/  Policies/  Data/
app/Http/Controllers/{Domain}/...   # mỏng, gọi Action
app/Http/Requests/{Domain}/...      # validate ở đây
```

### Quy tắc

1. **Code tiếng Anh, giao diện tiếng Việt.** Chuỗi UI để trong `lang/vi/*.php` hoặc `resources/js/locales/vi.json`.
2. Controller mỏng → logic nghiệp vụ trong `Actions/` (1 class 1 việc, method `handle()`) hoặc `Services/`.
3. Validate bằng **Form Request**. Phân quyền bằng **Policy** + `spatie/permission`.
4. Enum dùng **PHP backed enum** (`app/Domain/*/Enums`), cột DB kiểu `string`, cast trong model. Mỗi enum có method `label()` trả tiếng Việt.
5. Mọi bảng nghiệp vụ có: `organization_id`, `created_by`, `updated_by`, timestamps, `softDeletes`. Dùng trait `BelongsToOrganization`, `HasAuditColumns`.
6. **Phạm vi dữ liệu** (tư vấn viên chỉ thấy hồ sơ của mình…) áp bằng Global Scope `DataScope` — không lọc rải rác trong controller.
7. Timeline: ghi `activities` qua **Observer** (đổi giai đoạn, đổi người phụ trách…), không ghi tay trong controller.
8. Tiền: `decimal(15,0)` VNĐ. Thời gian lưu UTC, hiển thị `Asia/Ho_Chi_Minh`.
9. SĐT luôn chuẩn hoá về `84xxxxxxxxx` vào cột `phone_normalized` (service `PhoneNormalizer`).
10. CCCD: cast `encrypted` + cột `citizen_id_hash` (sha256) để kiểm tra trùng.
11. Không dùng `eval`. Công thức điểm là JSON, thực thi bằng `ScoreCalculator`.
12. Mỗi tính năng: migration + model + factory + seeder (nếu là danh mục) + Form Request + Policy + controller + trang Vue + **test Pest**.
13. Frontend: trang ở `resources/js/pages/{Domain}/{Index,Show,Form}.vue`; component dùng chung ở `resources/js/components/crm/` (DataTable, KanbanBoard, Timeline, FilterBar, SavedViewMenu, RecordDrawer).
14. **Màn hình danh sách** luôn dùng chuẩn chung: backend `ListQuery::for(...)->filters(Filter::...)->sorts([...])->toProps(fn)` (`app/Domain/Shared/Support/Listing`), frontend `useListQuery(() => props.x)` + `<FilterBar>` + `<DataTable>`. Không tự viết phân trang/lọc riêng. Mẫu đầy đủ: `/dev/components` (`ComponentsDemoController`, chỉ local).
16. **Phân quyền** (task 1.3): quyền `{object}.{action}` và vai trò mặc định khai báo ở `app/Domain/Organization/Services/RoleDefinitions.php` (thêm object mới ở đó rồi `php artisan db:seed --class=RoleSeeder`). Model có chủ sở hữu (thí sinh, hồ sơ, trường THPT…) dùng trait `HasDataScope` (ghi đè `dataScope*` nếu khác cột `owner_id`/`team_id`; phạm vi `territory` ghi đè `applyDataScopeTerritory` ở task 1.4). Policy kế thừa `ResourcePolicy` (quyền + bản ghi nằm trong phạm vi) và đăng ký ở `AppServiceProvider::configurePolicies`. Màn hình mới đã làm thì thêm `permission` vào `config/navigation.php`. SĐT/CCCD trả ra UI phải qua `SensitiveData`. Tổ chức mới phải gọi `SeedOrganizationCatalogs`.
15. **Chọn bản ghi liên kết** (người phụ trách, trường THPT, ngành…) dùng `<EntityPicker entity="...">`; nguồn dữ liệu là một lớp `EloquentLookup` đăng ký trong `config/lookups.php` (phải giới hạn theo tổ chức/quyền). Màn hình mới thêm vào menu: sửa `config/navigation.php` (bỏ cờ `placeholder`).

## 6. Quy tắc nghiệp vụ không được vi phạm

- Mọi cấu hình tuyển sinh (phương thức, tổ hợp, công thức điểm, chỉ tiêu, mốc thời gian) **gắn với một mùa** (`cycle_id`). Không hard-code.
- Một mùa có **tối đa 5 phương thức** `is_direct = false` (xét tuyển thẳng không tính).
- Tổ hợp: đúng 3 môn, **bắt buộc có Toán hoặc Ngữ văn**, trọng số môn đó ≥ 1/3 tổng trọng số.
- Một hồ sơ có **tối đa 15 nguyện vọng**; `preference_order` 1..15, không trùng trong cùng đợt.
- Chỉ tiêu chỉ có `quota_total` và `quota_direct` (không chia theo phương thức/tổ hợp).
- Nguyện vọng: tổ hợp phải thuộc `major_combinations` và phương thức thuộc `major_cycle_methods` của **ngành + mùa** đó.
- Mỗi thí sinh **một hồ sơ / một mùa** (unique `applicant_id, cycle_id`).
- Chống trùng thí sinh theo `phone_normalized`, `email`, `citizen_id_hash` trước khi tạo.
- Hồ sơ chuyển sang giai đoạn thất bại **bắt buộc** `lost_reason_id`.
- Mùa trạng thái `closed` → chỉ đọc.
- Không xoá cứng thí sinh đã có nguyện vọng nộp.

## 7. Cách làm việc với người dùng

- Làm theo `docs/TASKS.md`, **một task mỗi lần**. Xong task: chạy test, tick `[x]`, tóm tắt thay đổi.
- Trước khi tạo nhiều file, nêu ngắn gọn kế hoạch.
- Không tự cài thêm package ngoài danh sách stack mà không hỏi.
- Không sửa migration đã chạy trên DB thật — tạo migration mới.
- Người dùng giao tiếp bằng tiếng Việt; trả lời bằng tiếng Việt.
