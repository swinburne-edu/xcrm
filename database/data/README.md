# Dữ liệu địa giới hành chính cho `CatalogSeeder`

`php artisan db:seed --class=CatalogSeeder` đọc các file dưới đây **nếu có**. Thiếu file thì seeder chỉ cảnh báo và bỏ qua,
các danh mục khác (KV ưu tiên, môn, tổ hợp, phương thức) vẫn được tạo. Chạy lại nhiều lần không bị trùng (khớp theo `code`).

Địa giới theo mô hình mới từ 01/07/2025: **34 tỉnh/thành phố**, không còn cấp huyện. Nên lấy mã theo danh mục đơn vị hành chính
của Tổng cục Thống kê (bản sau sắp xếp).

| File | Bắt buộc | Nội dung |
| --- | --- | --- |
| `tinh_thanh_pho.json` | Có (để có tỉnh) | 34 tỉnh/thành phố: `province_code`, `province_name` ("Thành phố Hà Nội"), `province_type` ("Thành phố" / "Tỉnh") |
| `xa_phuong.json` | Có (để có xã/phường) | 3.321 xã/phường/đặc khu: `ward_code`, `ward_name`, `ward_type` ("Xã" / "Phường" / "Đặc khu"), `province_code` |
| `danh_sach_truong_thpt_2026.json` | Không | Danh sách trường THPT của Bộ GDĐT (4.922 dòng): `province_code`, `school_code`, `school_name`, `ward_code`, `address`, `region` (KV1 / KV2_NT / KV2 / KV3). Do `HighSchoolSeeder` đọc |
| `ward_mappings.json` | Không | Ánh xạ địa chỉ cũ → xã mới (`old_province_name`, `old_district_name`, `old_ward_name`, `new_ward_code`). **File là nguồn chuẩn: mỗi lần seed sẽ thay toàn bộ bảng `ward_mappings`.** |

Seeder bỏ tiền tố loại đơn vị khỏi tên ("Thành phố Hà Nội" → "Hà Nội", "Phường Ba Đình" → "Ba Đình"); loại nằm ở cột `type`.

## Trường THPT và ngành

- `php artisan db:seed --class=HighSchoolSeeder` nạp danh sách trường cho tổ chức đầu tiên (cần `CatalogSeeder` trước). Mã Bộ chỉ duy nhất trong từng tỉnh nên `high_schools.code` = mã tỉnh + mã trường (Phan Đình Phùng, Hà Nội → `01066`). Chạy lại chỉ cập nhật tên, loại, tỉnh, xã, địa chỉ, khu vực; người phụ trách, hạng, quan hệ đối tác do người dùng nhập được giữ nguyên.
- `php artisan db:seed --class=MajorSeeder` tạo 2 ngành: **Leadership and Management** (`LM`), **Marketing and Communication** (`MC`). Chỉ tiêu theo mùa nhập ở màn hình Chỉ tiêu.
- `DatabaseSeeder` (local/testing) gọi cả hai cho tổ chức của tài khoản test; `DemoSeeder` dùng lại hai ngành này và trường thật của Hà Nội.

Các file `*.example.json` trong thư mục này chỉ là ví dụ định dạng, seeder không đọc chúng. Có thể nhập/sửa từng dòng
trên giao diện **Cài đặt → Danh mục**.
