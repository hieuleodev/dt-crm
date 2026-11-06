# D&T CRM

CRM theo MVC, sử dụng **PHP, React/TypeScript và MySQL**.

## 1. Chuẩn bị

- **PHP 8.5 + Composer 2:** cài qua [Laravel Herd](https://herd.laravel.com/windows). Bật `pdo_sqlite`, `pdo_mysql` theo database sử dụng.
- **Node.js 22 từ 22.12 trở lên:** cài từ [Node.js](https://nodejs.org/en/download), kèm npm.
- **Docker Desktop:** chỉ cần khi chạy MySQL bằng Compose.

Mở PowerShell tại thư mục dự án:

```powershell

$env:Path = "$env:USERPROFILE\.config\herd\bin\php85;$env:USERPROFILE\.config\herd\bin;$env:Path"

php -v
composer -V
node -v
npm.cmd -v
```

## 2. Cài lần đầu

```powershell
composer install --no-interaction
npm.cmd ci
```

**Bản cài mới:** sao chép `.env.example` thành `.env`, rồi chạy `php artisan key:generate`. **Bản đã có dữ liệu:** giữ nguyên `.env`, `APP_KEY` và database.

Chọn một database trong `.env`:

### SQLite — chạy nhanh, giữ dữ liệu hiện tại

```dotenv
DB_CONNECTION=sqlite
```

Bỏ `DB_DATABASE` nếu đang đặt tên MySQL; Laravel sẽ dùng `database/database.sqlite`. Bỏ/cập nhật `DB_URL` nếu có.

```powershell
if (-not (Test-Path database/database.sqlite)) {
    New-Item -ItemType File -Path database/database.sqlite | Out-Null
}
php artisan config:clear
php artisan migrate --no-interaction
```

### MySQL — dùng Docker

Mở Docker Desktop rồi chạy:

```powershell
docker compose -f compose.mysql.yml up -d --wait
```

Sửa `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dt_crm
DB_USERNAME=dt_crm
DB_PASSWORD=dt_crm_local
```

```powershell
php artisan config:clear
php artisan migrate --no-interaction
```

Mật khẩu trên chỉ dùng local. Nếu dùng MySQL cài sẵn, tạo database/user và sửa kết nối tương ứng. **Đổi kết nối không tự chuyển dữ liệu SQLite sang MySQL.** Lệnh chuyển riêng là `php artisan crm:copy-sqlite-to-mysql --source="database/import.sqlite" --dry-run`; cần sao lưu, cập nhật migrations trên bản sao nguồn và chuẩn bị MySQL đích trống trước khi chạy lại lệnh không có `--dry-run`.

## 3. Chạy mỗi ngày

**Terminal 1**, tại thư mục dự án, thiết lập PATH Herd như phần 1 nếu cần:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

**Terminal 2**, cũng tại thư mục dự án:

```powershell
npm.cmd run dev -- --host 127.0.0.1 --port 5173 --strictPort
```

Mở **http://127.0.0.1:8000**. Giữ hai terminal mở; nhấn `Ctrl+C` để dừng. Không cần cài lại thư viện mỗi lần chạy.

## 4. Đăng nhập

| Email quản trị        | Mật khẩu mặc định |
| --------------------- | ----------------- |
| admin@trangthietbi.vn | Admin@12345       |

Để tạo tài khoản trên bản cài mới:

```powershell
php artisan db:seed --class=DatabaseSeeder --no-interaction
```

Chạy lại seeder sẽ **đặt lại mật khẩu admin**. Với hệ thống đang dùng, đăng nhập bằng tài khoản cũ. Đổi mật khẩu mặc định trước khi sử dụng thực tế.

## 5. Build và kiểm tra

```powershell
npm.cmd run build
npx.cmd tsc --noEmit
php artisan test --compact
```

Để chạy bằng bản build, dừng Vite, xóa `public/hot` nếu còn rồi chạy PHP server. Test dùng SQLite trong bộ nhớ. Khi có job chạy nền, mở thêm terminal chạy `php artisan queue:work`.
