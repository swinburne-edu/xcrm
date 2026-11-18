# Dữ liệu địa giới hành chính cho `CatalogSeeder`

`php artisan db:seed --class=CatalogSeeder` đọc các file dưới đây **nếu có**. Thiếu file thì seeder chỉ cảnh báo và bỏ qua,
các danh mục khác (KV ưu tiên, môn, tổ hợp, phương thức) vẫn được tạo. Chạy lại nhiều lần không bị trùng (khớp theo `code`).

Địa giới theo mô hình mới từ 01/07/2025: **34 tỉnh/thành phố**, không còn cấp huyện. Nên lấy mã theo danh mục đơn vị hành chính
của Tổng cục Thống kê (bản sau sắp xếp).

| File | Bắt buộc | Nội dung |
| --- | --- | --- |
| `provinces.json` | Có (để có tỉnh) | Danh sách tỉnh/thành phố |
| `wards.json` | Có (để có xã/phường) | Danh sách xã/phường/đặc khu, gắn theo `province_code` |
| `ward_mappings.json` | Không | Ánh xạ địa chỉ cũ (tỉnh/huyện/xã trước 7/2025) → xã mới. **File là nguồn chuẩn: mỗi lần seed sẽ thay toàn bộ bảng `ward_mappings`.** |

## Định dạng

`provinces.json` — `type`: `tinh` | `thanh_pho`

```json
[
  { "code": "01", "name": "Hà Nội", "type": "thanh_pho" }
]
```

`wards.json` — `type`: `xa` | `phuong` | `dac_khu`

```json
[
  { "code": "00004", "name": "Ba Đình", "type": "phuong", "province_code": "01" }
]
```

`ward_mappings.json` — `new_ward_code` là mã xã mới trong `wards.json`

```json
[
  {
    "old_province_name": "Hà Nội",
    "old_district_name": "Quận Ba Đình",
    "old_ward_name": "Phường Phúc Xá",
    "new_ward_code": "00004"
  }
]
```

Các file `*.example.json` trong thư mục này chỉ là ví dụ định dạng, seeder không đọc chúng. Có thể nhập/sửa từng dòng
trên giao diện **Cài đặt → Danh mục**.
