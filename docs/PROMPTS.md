# PROMPTS — Prompt mẫu để vibe code

Dán lần lượt vào Claude Code / Cursor. Thay `X.Y` bằng số task trong `TASKS.md`.

## Mở đầu mỗi phiên làm việc

```
Đọc CLAUDE.md và docs/TASKS.md. Cho tôi biết task tiếp theo chưa làm là gì,
liệt kê các file sẽ tạo/sửa, rồi chờ tôi xác nhận.
```

## Giao một task

```
Thực hiện Task X.Y trong docs/TASKS.md.
- Bám theo docs/database.dbml cho migration và docs/04-database.md cho ràng buộc.
- Viết test Pest cho các ràng buộc và luồng liên quan.
- Chạy migrate + test. Nếu test lỗi thì sửa đến khi xanh.
- Xong thì tick task trong TASKS.md và tóm tắt: file đã tạo, cách kiểm tra trên trình duyệt.
```

## Khi màn hình chưa đẹp / chưa đúng

```
Trang /cases đang [mô tả vấn đề]. Xem docs/06-giao-dien.md phần "Hồ sơ tuyển sinh"
và sửa lại cho đúng. Giữ nguyên component dùng chung, chỉ sửa trang này.
```

## Khi gặp lỗi

```
Tôi gặp lỗi sau khi [thao tác]:
<dán lỗi / log từ storage/logs/laravel.log hoặc console trình duyệt>
Tìm nguyên nhân, sửa, và thêm test để lỗi không lặp lại.
```

## Rà soát sau một giai đoạn

```
Rà soát toàn bộ code của Giai đoạn 1 theo CLAUDE.md mục 5 và 6:
quy ước code, ràng buộc nghiệp vụ R1–R11, phân quyền DataScope, N+1 query, test còn thiếu.
Liệt kê vấn đề theo mức độ nghiêm trọng, chưa sửa gì.
```

## Thêm yêu cầu mới

```
Tôi muốn thêm tính năng: [mô tả].
Trước khi code: cập nhật docs liên quan (03-tinh-nang.md, database.dbml, 04-database.md, TASKS.md)
và cho tôi xem thay đổi tài liệu để duyệt.
```
