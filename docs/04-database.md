# 04 — Database: quy ước, ràng buộc, dữ liệu seed

Schema chi tiết từng cột: **`docs/database.dbml`** (nguồn sự thật). File này mô tả quy ước, quan hệ chính, ràng buộc nghiệp vụ, index và dữ liệu seed.

## 1. Quy ước

- MySQL 8, `utf8mb4_unicode_ci`. Tên bảng tiếng Anh, số nhiều, snake_case. Bảng nối theo chuẩn Laravel (`applicant_guardian`).
- PK `id` BIGINT auto-increment. Đối tượng lộ ra URL/API công khai (applicants, admission_cases, forms) có thêm `uuid`.
- Cột chung mọi bảng nghiệp vụ: `organization_id`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`.
  → Trait: `BelongsToOrganization` (global scope theo org của user đăng nhập), `HasAuditColumns`, `SoftDeletes`.
- Enum: cột `string`, PHP backed enum, cast trong model.
- Tiền `decimal(15,0)` VNĐ. Điểm `decimal(5,2)`. Thời gian lưu UTC.
- Custom field: cột `custom_data` JSON trên applicants, admission_cases, high_schools, majors, campaigns, school_contacts.
- Polymorphic dùng morph map ngắn (`AppServiceProvider::boot` → `Relation::enforceMorphMap([...])`):
  `applicant`, `admission_case`, `high_school`, `school_contact`, `campaign`, `major`, `province`.

## 2. Nhóm bảng

| Nhóm | Bảng |
| --- | --- |
| Hệ thống & phân quyền | organizations, users, teams, team_user, (spatie: roles, permissions…), territories, territory_assignments, activity_log |
| Danh mục | provinces, wards, ward_mappings, priority_areas, priority_groups, subjects, subject_combinations, combination_subjects, admission_methods, lead_sources, lost_reasons, call_results, tags, taggables |
| Trường THPT | high_schools, school_contacts, school_activities, school_activity_user, activity_attendees |
| Ngành | faculties, campuses, majors, major_quotas, major_combinations, major_cycle_methods |
| Mùa & chiến dịch | admission_cycles, admission_rounds, cycle_methods, campaigns, campaign_targets, campaign_costs, forms |
| Thí sinh & hồ sơ | applicants, guardians, applicant_guardian, applicant_scores, applicant_certificates, applicant_documents, pipelines, pipeline_stages, admission_cases, case_stage_histories, case_touches, case_interested_majors, applications |
| Tương tác | activities, tasks, appointments, message_templates, message_logs, media (spatie) |
| Mở rộng | custom_field_definitions, saved_views, assignment_rules, workflows, workflow_runs, import_jobs, webhooks |

## 3. Trục quan hệ chính

```
high_schools 1─n applicants 1─n admission_cases n─1 admission_cycles
     │                               │  │  │
     1─n school_activities ─1─n──────┘  │  └─1─n applications n─1 majors
              n─1 campaigns ───1─n──────┘              │   n─1 cycle_methods n─1 admission_methods
                    n─1 admission_cycles               │   n─1 subject_combinations
                                                       │   n─1 admission_rounds
majors n─n admission_cycles  (major_quotas: chỉ tiêu)
majors n─n subject_combinations theo mùa (major_combinations)
majors n─n cycle_methods (major_cycle_methods)
admission_cases 1─n activities / tasks / appointments / case_touches / case_stage_histories
```

## 4. Ràng buộc nghiệp vụ (validate ở Form Request / Action)

| # | Ràng buộc | Nơi kiểm tra |
| --- | --- | --- |
| R1 | Mỗi cycle tối đa 5 `cycle_methods` có `is_direct = false` | `StoreCycleMethodRequest` |
| R2 | Tổ hợp đúng 3 môn; có TOAN hoặc VAN; weight môn đó ≥ 1/3 tổng weight | `SubjectCombinationRequest` |
| R3 | `applications` mỗi case tối đa 15; `preference_order` 1–15 unique theo (case, round) | `StoreApplicationRequest` |
| R4 | `applications.combination_id` ∈ `major_combinations` (major, cycle của case); `cycle_method_id` ∈ `major_cycle_methods` của major; cycle_method thuộc cùng cycle | `StoreApplicationRequest` |
| R5 | Một applicant một case mỗi cycle | unique index + `CreateAdmissionCase` action |
| R6 | Chuyển case sang stage `lost` bắt buộc `lost_reason_id` | `MoveCaseStage` action |
| R7 | `admission_cases.status` luôn = `stage.type` | Observer `AdmissionCaseObserver` |
| R8 | Cycle `closed` → mọi bản ghi gắn cycle chỉ đọc | Policy |
| R9 | Không xoá cứng applicant có application `submitted` trở lên | Policy + chỉ soft delete |
| R10 | Chỉ một cycle `is_current = true` mỗi organization (theo level) | Action `SetCurrentCycle` |
| R11 | `quota_direct ≤ quota_total` | Form Request |

## 5. Chống trùng thí sinh

Service `ApplicantDeduplicator::find(array $data): ?Applicant`
1. Chuẩn hoá SĐT bằng libphonenumber (region VN) → `84xxxxxxxxx`.
2. Tìm theo `phone_normalized` → nếu không có, `email` (lowercase) → nếu không có, `citizen_id_hash`.
3. Có trùng: trả applicant cũ; caller thêm `case_touches` và cập nhật trường đang trống.

Gộp trùng `MergeApplicants::handle(Applicant $keep, Applicant $merge, array $fieldChoices)`: chuyển scores, guardians, documents, activities, tasks, taggables, attendees sang `$keep`; nếu cả hai có case cùng cycle thì gộp case (giữ case có stage cao hơn, chuyển applications/activities); set `$merge->merged_into_id`, soft delete; ghi audit log.

## 6. Công thức điểm (`cycle_methods.score_formula`)

```json
{
  "source": "transcript",             // thpt_exam | transcript | competency_exam | direct | talent
  "use_combination": true,            // tính theo 3 môn của tổ hợp
  "terms": ["10", "11", "12"],        // học bạ: kỳ/năm dùng để tính
  "aggregate": "avg",                 // avg | sum
  "subject_scale": 10,
  "scale_to": 30,
  "certificate_conversion": {          // quy đổi chứng chỉ thành điểm môn ngoại ngữ
    "subject": "ANH",
    "table": { "IELTS": { "5.0": 8.0, "5.5": 8.5, "6.0": 9.0, "6.5": 9.5, "7.0": 10 } }
  },
  "priority": {                        // điểm ưu tiên KV + đối tượng
    "apply": true,
    "taper_from": 22.5                 // từ mức này điểm ưu tiên giảm dần: bonus × (30 − tổng) / 7.5
  },
  "encourage_bonus_max": 3,
  "min_total": 18
}
```

`ScoreCalculator::calculate(Application $app): ScoreResult` → `raw_score`, `priority_bonus`, `encourage_bonus`, `final_score`, `breakdown` (lưu vào `score_breakdown`). Công thức có thể khác mỗi năm → luôn đọc từ JSON, có test cho từng trường hợp.

## 7. Index quan trọng

Đã khai báo trong DBML. Bắt buộc: `applicants (organization_id, phone_normalized)`, `(organization_id, email)`, `(organization_id, citizen_id_hash)`; `admission_cases (cycle_id, stage_id, owner_id)`; `applications (major_id, round_id, status)`; `activities (subject_type, subject_id, occurred_at)`.

## 8. Dữ liệu seed

### Seeder hệ thống (`CatalogSeeder`)

- **provinces / wards**: 34 tỉnh/thành theo địa giới mới. Seeder đọc file `database/data/provinces.json`, `wards.json` (người dùng bổ sung file; seeder phải chạy được khi file chưa có — chỉ cảnh báo).
- **priority_areas**: KV1 (0.75) · KV2-NT (0.5) · KV2 (0.25) · KV3 (0). Đây là giá trị tham khảo, sửa được trong cấu hình.
- **priority_groups**: UT1 (2.0) · UT2 (1.0).
- **subjects**: TOAN (core), VAN (core), ANH, LY, HOA, SINH, SU, DIA, GDKTPL, TIN, CNCN, CNNN.
- **subject_combinations** (tối thiểu): A00 Toán-Lý-Hoá · A01 Toán-Lý-Anh · B00 Toán-Hoá-Sinh · B08 Toán-Sinh-Anh · C00 Văn-Sử-Địa · C01 Văn-Toán-Lý · C03 Văn-Toán-Sử · C04 Văn-Toán-Địa · D01 Văn-Toán-Anh · D07 Toán-Hoá-Anh · D14 Văn-Sử-Anh · D15 Văn-Địa-Anh. Cho phép import thêm.
- **admission_methods**: 100 Xét kết quả thi tốt nghiệp THPT · 200 Xét học bạ THPT · 301 Xét tuyển thẳng theo quy chế · 401 Thi ĐGNL/ĐGTD do trường tổ chức · 402 Sử dụng kết quả ĐGNL/ĐGTD đơn vị khác tổ chức · 405 Kết hợp thi THPT và năng khiếu · 500 Phương thức khác. (Kiểm tra lại danh mục mã của năm hiện hành.)

### Seeder theo tổ chức (`OrganizationSeeder`)

- **roles** và permissions: theo `02-phan-quyen.md`.
- **pipeline** mặc định "Đại học chính quy":

| sort | code | name | type | probability |
| --- | --- | --- | --- | --- |
| 1 | new | Mới | open | 5 |
| 2 | contacted | Đã liên hệ | open | 10 |
| 3 | counseling | Đang tư vấn | open | 20 |
| 4 | early_applied | Nộp hồ sơ xét sớm | open | 40 |
| 5 | eligible | Đủ điều kiện | open | 55 |
| 6 | moet_registered | Đã đăng ký NV trên hệ thống Bộ | open | 65 |
| 7 | admitted | Trúng tuyển | open | 80 |
| 8 | confirmed | Xác nhận nhập học | open | 95 |
| 9 | enrolled | Nhập học | won | 100 |
| 10 | lost | Thất bại | lost | 0 |

- **lead_sources**: Website, Facebook Ads, TikTok Ads, Google Ads, Zalo OA, Hotline, Tư vấn tại trường THPT, Ngày hội tuyển sinh, Tham quan campus, Giới thiệu (cựu SV/SV), Cộng tác viên, Import.
- **lost_reasons**: Trúng tuyển trường khác · Không đủ điểm · Học phí cao · Ngành không phù hợp · Xa nhà · Không liên lạc được · Đi du học · Học nghề/đi làm · Khác.
- **call_results**: Không nghe máy · Thuê bao/tắt máy · Sai số · Hẹn gọi lại (contacted) · Quan tâm (contacted) · Không quan tâm (contacted).

### Seeder demo (`DemoSeeder`, chỉ môi trường local)

1 tổ chức, 8 user mỗi vai trò, 1 mùa "Tuyển sinh 2027" (active, đủ 4 đợt, 4 phương thức), 3 khoa, 12 ngành, 60 trường THPT ở 5 tỉnh, 6 chiến dịch, 500 thí sinh + hồ sơ phân bố đều các giai đoạn, nguyện vọng, activities. Dùng Factory + Faker locale `vi_VN`.
