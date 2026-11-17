# xcrm — CRM Tuyển sinh

CRM quản lý tuyển sinh đại học/cao đẳng: trường THPT, ngành, mùa tuyển sinh, chiến dịch, thí sinh, hồ sơ, nguyện vọng.
Lấy cảm hứng từ [Twenty CRM](https://twenty.com) (custom field, saved view, Kanban, timeline).

## Thư mục hiện tại chứa gì

```
xcrm/
├── CLAUDE.md            # Hướng dẫn cho AI coding agent (đọc đầu tiên)
├── AGENTS.md            # Trỏ về CLAUDE.md (cho Cursor / Codex / Copilot)
├── README.md
└── docs/
    ├── 01-tong-quan.md
    ├── 02-phan-quyen.md
    ├── 03-tinh-nang.md
    ├── 04-database.md
    ├── database.dbml        # Schema chuẩn, dán vào https://dbdiagram.io để xem ERD
    ├── 05-luong-nghiep-vu.md
    ├── 06-giao-dien.md
    ├── TASKS.md             # Danh sách task theo thứ tự — vibe code theo file này
    └── PROMPTS.md           # Prompt mẫu để giao việc cho AI
```

## Bắt đầu vibe code

### Bước 1 — Kiểm tra môi trường (Laragon)

Mở Terminal của Laragon và chạy:

```bash
php -v        # cần 8.3+
composer -V
node -v       # cần 20+
mysql --version
```

Tạo database `xcrm` (Laragon → Database, hoặc `mysql -u root -e "CREATE DATABASE xcrm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"`).

### Bước 2 — Mở thư mục bằng công cụ AI

- **Claude Code:** mở terminal tại `C:\laragon\www\xcrm`, chạy `claude`.
- **Cursor / Windsurf / VS Code + Copilot:** mở thư mục `xcrm`.

### Bước 3 — Giao task đầu tiên

Dán prompt sau:

> Đọc CLAUDE.md và toàn bộ thư mục docs/. Sau đó thực hiện Task 0.1 trong docs/TASKS.md.

Sau mỗi task, kiểm tra trên trình duyệt `http://xcrm.test`, rồi giao task tiếp theo
(xem `docs/PROMPTS.md`).

> **Lưu ý:** Laravel cần cài vào thư mục trống. Task 0.1 hướng dẫn AI cài vào thư mục tạm `_app` rồi chuyển ra gốc,
> giữ nguyên `docs/`, `CLAUDE.md`, `AGENTS.md`, `README.md`.

## Tài liệu đặc tả đầy đủ (bản online)

Bản đặc tả có sơ đồ ERD: *Đặc tả hệ thống CRM Tuyển sinh (xcrm)* trong Claude.
