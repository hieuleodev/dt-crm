# D&T CRM — Hệ thống Quản trị Quan hệ Khách hàng

Hệ thống CRM chuyên sâu theo kiến trúc MVC hiện đại, kết hợp sức mạnh của **Laravel 13 (PHP 8.5)** ở phía Backend và **React 19 + TypeScript + Ant Design + Tailwind CSS** ở phía Frontend qua cầu nối **Inertia.js v3**.

---

## 🚀 Các phân hệ chức năng chính

### 1. 📅 Lịch trình công việc (Google Calendar Style)
- Giao diện và trải nghiệm tương tự **Google Calendar** với 4 chế độ xem linh hoạt:
  - **Tháng (Month)**: Lưới 7 cột (T2 - CN), ngày hiện tại được làm nổi bật, hiển thị thẻ lịch trình theo dải màu, hỗ trợ xem thêm `+ X việc khác`.
  - **Tuần (Week)**: Trục thời gian `07:00 - 21:00` với **đường chỉ thị giờ thực tế màu đỏ (Red Now-Indicator)** hiển thị chính xác theo phút hiện tại.
  - **Ngày (Day)**: Dòng thời gian chi tiết từng giờ trong ngày kèm đầy đủ nội dung, thông tin khách hàng và mô tả.
  - **Lịch biểu (Agenda)**: Danh sách theo thứ tự thời gian được gom nhóm theo ngày (nhãn *Hôm nay*, *Ngày mai*).
- **Tương tác thông minh**: Click vào ô ngày hoặc khung giờ để tạo nhanh lịch trình tại đúng thời điểm đó.
- **Phân loại màu trực quan**: 9 loại công việc (*Gọi điện, Zalo, Gặp mặt, Email, Báo giá, Mẫu thử, Đăng ký NCC, Chăm sóc, Nội bộ*) đều có màu sắc và icon nhận diện riêng.
- **Thao tác nhanh**: Xem chi tiết, đánh dấu Hoàn thành, Bắt đầu, Chỉnh sửa hoặc Hủy việc ngay trên popup lịch.
- 👉 *Xem tài liệu chi tiết tại:* **[README_CALENDAR.md](README_CALENDAR.md)**.

### 2. 📋 Nhiệm vụ & Hoạt động chăm sóc (Care Tasks)
- Theo dõi toàn bộ việc cần làm, phân loại theo mức độ ưu tiên (*Bình thường, Cao, Khẩn cấp*).
- Phân loại theo thời hạn (*Chưa hoàn thành, Đến hạn hôm nay, Quá hạn, Hoàn thành, Đã hủy*).
- Ghi nhận kết quả thực hiện hoặc lý do hủy việc, hỗ trợ khóa lạc quan (*Optimistic Locking* với trường `version`).

### 3. ⚡ Công việc hôm nay (Workbench)
- Bảng điều khiển tập trung giúp nhân viên kinh doanh nắm bắt ngay các việc cần ưu tiên xử lý trong ngày.
- Tự động gom nhóm: *Quá hạn*, *Đến hạn hôm nay*, *Sắp tới (7 ngày)* và *Đã hoàn thành hôm nay*.

### 4. 🏢 Quản lý Khách hàng 360° (Customers)
- Quản lý thông tin doanh nghiệp, mã khách hàng, mã số thuế, quy mô, phân khúc (*Tier*), giai đoạn vòng đời (*Lifecycle Stage*).
- Quản lý danh sách nhà máy / cơ sở sản xuất (*Plants*).
- Quản lý danh bạ người liên hệ (*Contacts*) thuộc khách hàng.
- Nhật ký chăm sóc khách hàng (*Customer Notes*) liên kết chặt chẽ với nhiệm vụ.
- Kiểm tra trùng lặp mã số thuế và tên doanh nghiệp khi thêm mới.

### 5. 🎯 Quản lý Lead & Cơ hội (Leads & Opportunities)
- Quản lý danh sách khách hàng tiềm năng qua từng giai đoạn (*Mới, Đang liên hệ, Đủ điều kiện, Báo giá, Đàm phán, Thành công, Thất bại*).
- Chức năng chuyển đổi nhanh từ Lead thành Khách hàng chính thức.

### 6. 🔒 Phân quyền & Bảo mật (RBAC)
- **Quản trị viên (Admin)**: Toàn quyền quản trị hệ thống, người dùng và dữ liệu.
- **Quản lý (Manager)**: Theo dõi toàn bộ khách hàng, phân công người phụ trách, xem lịch trình và nhiệm vụ của mọi nhân viên.
- **Kinh doanh (Sales)**: Chỉ xem và quản lý khách hàng, lead và lịch trình do chính mình phụ trách.

---

## 🛠️ Yêu cầu môi trường

- **PHP 8.5** (hỗ trợ đầy đủ cú pháp mới của Symfony 8 & Laravel 13).
- **Composer 2**
- **Node.js 22 trở lên** (kèm `npm`).
- Cơ sở dữ liệu: **SQLite** (sẵn có, không cần cài đặt thêm) hoặc **MySQL** (qua Docker/Laragon).

---

## 📦 Hướng dẫn cài đặt lần đầu

1. **Cài đặt thư viện:**
   ```powershell
   composer install --no-interaction
   npm ci
   ```

2. **Cấu hình môi trường (`.env`):**
   - Sao chép `.env.example` thành `.env`:
     ```powershell
     Copy-Item .env.example .env
     php artisan key:generate
     ```
   - Dự án đã được cấu hình mặc định sử dụng **SQLite** (`database/database.sqlite`), cực kỳ nhanh và tiện lợi cho môi trường local:
     ```powershell
     if (-not (Test-Path database/database.sqlite)) {
         New-Item -ItemType File -Path database/database.sqlite | Out-Null
     }
     php artisan config:clear
     php artisan migrate --no-interaction
     php artisan db:seed --class=DatabaseSeeder --no-interaction
     ```

*(Nếu muốn dùng MySQL qua Docker Compose, xem hướng dẫn trong file `compose.mysql.yml`)*.

---

## ▶️ Cách khởi chạy ứng dụng

### Cách 1: Chạy nhanh (Dùng bản build tĩnh — 1 Terminal duy nhất)
```powershell
cd C:\dt-crm
php artisan serve
```
Mở trình duyệt: **[http://127.0.0.1:8000](http://127.0.0.1:8000)**

---

### Cách 2: Chế độ phát triển (Dev Mode — Hỗ trợ Hot-Reload React)
Mở **2 cửa sổ PowerShell**:

- **Cửa sổ 1 (Backend PHP):**
  ```powershell
  cd C:\dt-crm
  php artisan serve
  ```

- **Cửa sổ 2 (Frontend Vite):**
  ```powershell
  cd C:\dt-crm
  npm run dev
  ```

Mở trình duyệt: **[http://127.0.0.1:8000](http://127.0.0.1:8000)**

---

## 🔑 Tài khoản đăng nhập mặc định

| Mục | Thông tin |
| :--- | :--- |
| **Email đăng nhập** | `admin@trangthietbi.vn` |
| **Mật khẩu** | `Admin@12345` |
| **Vai trò** | Quản trị hệ thống (Admin) |

> [!NOTE]
> Khi triển khai thực tế, hãy đổi mật khẩu mặc định ngay sau lần đăng nhập đầu tiên.

---

## 🧪 Build & Kiểm thử tự động

- **Kiểm tra TypeScript:**
  ```powershell
  npx tsc --noEmit
  ```

- **Đóng gói tài nguyên giao diện:**
  ```powershell
  npm run build
  ```

- **Chạy toàn bộ bài kiểm thử tự động (PHPUnit):**
  ```powershell
  php artisan test --compact
  ```
  *(Toàn bộ 25 bài kiểm thử tự động bao gồm kiểm thử tính năng Lịch trình Calendar đều đã vượt qua 100%)*
