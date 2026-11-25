# Hướng dẫn Cài đặt, Sử dụng & Mở rộng chức năng Tin nhắn Broadcast (Zalo OA & Email)

Tài liệu này hướng dẫn thành viên tiếp nhận dự án cách **cài đặt môi trường, cấu hình Zalo OA / Email, vận hành Queue & Lịch hẹn, sử dụng trên giao diện** và **mở rộng các giai đoạn tiếp theo** cho chức năng gửi Tin nhắn Broadcast đa bài viết (Rich Media Broadcast) trong hệ thống `dt-crm`.

---

## 1. Tổng quan kiến trúc đã triển khai

Chức năng Broadcast được tích hợp trực tiếp vào phân hệ **Nhắc nhở mua lại (`/repurchase-reminders`)**, hỗ trợ soạn **1 bài viết chính (Hero) + tối đa 4 bài viết phụ** và gửi đồng thời qua **2 kênh**:
- **Email (Rich Media HTML):** gửi trực tiếp qua cấu hình Mail/SMTP của Laravel.
- **Zalo OA (Tin nhắn Truyền thông / Promotion v3):** gửi qua Zalo OA API v3 (`POST /v3.0/oa/message/promotion`) theo `zalo_user_id` của người liên hệ đã quan tâm OA (có chế độ Sandbox để chạy thử khi chưa có OA tích vàng).

### Cấu trúc mã nguồn liên quan

| Thành phần | Đường dẫn | Vai trò |
| :--- | :--- | :--- |
| **Migrations** | `database/migrations/2026_11_24_184500_add_zalo_oa_fields_to_contacts_table.php` | Thêm `zalo_user_id`, `zalo_followed_at` vào bảng `contacts` |
| | `database/migrations/2026_11_24_184501_create_broadcast_tables.php` | Tạo bảng `broadcast_campaigns`, `broadcast_articles`, `broadcast_recipients` |
| | `database/migrations/2026_11_24_191500_add_scheduling_and_zalo_oa_connections.php` | Thêm `scheduled_at`, `delivered_at`, `seen_at` và bảng `zalo_oa_connections` |
| **Models** | `app/Models/BroadcastCampaign.php` | Quản lý chiến dịch Broadcast & thống kê kết quả |
| | `app/Models/BroadcastArticle.php` | Lưu nội dung từng bài viết (1–5 bài) |
| | `app/Models/BroadcastRecipient.php` | Theo dõi trạng thái gửi từng người nhận (`pending`, `sent`, `delivered`, `seen`, `failed`, `skipped`) |
| | `app/Models/ZaloOaConnection.php` | Lưu mã hóa `access_token` và `refresh_token` của Zalo OA |
| **Services & Drivers** | `app/Services/Broadcast/SendBroadcastCampaign.php` | Tạo chiến dịch, lọc opt-out, điều phối gửi, cập nhật `last_contacted_at` & ghi `AuditLog` |
| | `app/Services/Broadcast/Drivers/EmailBroadcastDriver.php` | Driver gửi Email Rich Media |
| | `app/Services/Broadcast/Drivers/ZaloOaBroadcastDriver.php` | Driver dựng payload Promotion v3 & gọi Zalo OA API (hỗ trợ Sandbox) |
| | `app/Services/Broadcast/ZaloOaTokenService.php` | Đổi mã OAuth v4 (PKCE) và tự động làm mới token trước khi hết hạn |
| **Mail & Template** | `app/Mail/RichMediaBroadcastMail.php` | Mailable gửi Email đa bài viết |
| | `resources/views/emails/broadcast/rich-media.blade.php` | Giao diện HTML Email (tự lấy tên, hotline, địa chỉ từ `CompanySetting`) |
| **Queue & Scheduler** | `app/Jobs/DispatchBroadcastCampaignJob.php` | Job gửi chiến dịch bất đồng bộ trong Queue |
| | `routes/console.php` | Lệnh định kỳ `broadcast:dispatch-due` (mỗi phút) và `zalo:refresh-token` (mỗi giờ) |
| **Controllers** | `app/Http/Controllers/BroadcastController.php` | Endpoint `POST /repurchase-reminders/broadcast` |
| | `app/Http/Controllers/ZaloOaOAuthController.php` | OAuth v4 PKCE (`GET /zalo-oa/oauth/install`, `GET /zalo-oa/oauth/callback`) |
| | `app/Http/Controllers/ZaloOaWebhookController.php` | Webhook `POST /webhooks/zalo-oa` (đã miễn CSRF trong `bootstrap/app.php`) |
| **Frontend (React)** | `resources/js/components/broadcast/BroadcastComposerModal.tsx` | Modal soạn đa bài viết, chọn kênh, hẹn lịch & xem trước giao diện điện thoại |
| | `resources/js/components/broadcast/BroadcastHistoryModal.tsx` | Modal xem lịch sử chiến dịch và chi tiết từng người nhận |
| | `resources/js/pages/RepurchaseReminders/Index.tsx` | Tích hợp chọn nhiều dòng, gửi nhanh và hiển thị trạng thái đã Broadcast |
| | `resources/js/components/customer/ContactModal.tsx` | Ô nhập/sửa `Zalo OA User ID` trên hồ sơ Liên hệ Khách hàng |

---

## 2. Hướng dẫn cài đặt môi trường

### Bước 1: Chạy Migration & Build giao diện

```bash
php artisan migrate
npm run build
```

### Bước 2: Cấu hình file `.env`

Mở file `.env` (tham khảo mẫu trong `.env.example`) và cấu hình theo nhu cầu:

#### A. Chế độ chạy thử / phát triển (chưa cần tài khoản Zalo OA thật)

```env
# Dùng 'sync' nếu muốn test gửi ngay trên máy local không cần mở terminal queue:work
# Khi chạy thật trên server nên đổi lại thành 'database'
QUEUE_CONNECTION=sync

# Email: để 'log' nếu chỉ muốn xem nội dung email trong storage/logs/laravel.log
# Hoặc chuyển sang 'smtp' để gửi email thật
MAIL_MAILER=log

# Bật chế độ mô phỏng Zalo OA (ghi nhận thành công mà không gọi API Zalo thật)
ZALO_OA_SANDBOX=true
```

#### B. Chế độ chạy thật (Email SMTP + Zalo OA xác thực)

```env
QUEUE_CONNECTION=database

# 1. Cấu hình SMTP doanh nghiệp (hoặc Gmail App Password khi test)
MAIL_MAILER=smtp
# Lưu ý Laravel 13: dùng 'smtp' cho cổng 587 (STARTTLS) hoặc 'smtps' cho cổng 465 (SSL), KHÔNG dùng 'tls'
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@company.com
# Nếu dùng Gmail: bắt buộc tạo App Password 16 ký tự (bỏ dấu cách), không dùng mật khẩu đăng nhập thường
MAIL_PASSWORD=your-16-char-app-password
MAIL_FROM_ADDRESS="no-reply@company.com"
MAIL_FROM_NAME="${APP_NAME}"

# 2. Cấu hình Zalo Official Account
ZALO_OA_SANDBOX=false
ZALO_OA_APP_ID=your_zalo_app_id
ZALO_OA_APP_SECRET=your_zalo_app_secret
ZALO_OA_REDIRECT_URI=https://your-domain.com/zalo-oa/oauth/callback
ZALO_OA_WEBHOOK_SECRET=your_oa_secret_key
ZALO_OA_ID=your_oa_id
# Tùy chọn dự phòng nếu chưa kết nối OAuth:
ZALO_OA_ACCESS_TOKEN=
```

> **Lưu ý sau khi chỉnh sửa `.env`:** luôn chạy `php artisan config:clear` để hệ thống nạp lại cấu hình mới.

#### C. Hướng dẫn tạo Gmail App Password để gửi thử Email

Nếu chưa có máy chủ email doanh nghiệp riêng, bạn có thể dùng một tài khoản Gmail bất kỳ làm tài khoản **người gửi** (mật khẩu đăng nhập Gmail chính không bị ảnh hưởng):

1. Đăng nhập tài khoản Gmail dùng để gửi vào trang bảo mật Google: `https://myaccount.google.com/security`.
2. Đảm bảo đã bật **Xác minh 2 bước (2-Step Verification)**.
3. Truy cập trang tạo Mật khẩu ứng dụng: `https://myaccount.google.com/apppasswords` *(hoặc tìm từ khóa "Mật khẩu ứng dụng" / "App passwords" trong Google Account)*.
4. Nhập tên gợi nhớ (ví dụ: `DT CRM`) → bấm **Tạo (Create)** → Google sẽ tự sinh chuỗi **16 ký tự**.
5. Dán chuỗi 16 ký tự đó (**xóa hết dấu cách**) vào dòng `MAIL_PASSWORD` trong `.env`, điền `MAIL_USERNAME` và `MAIL_FROM_ADDRESS` bằng địa chỉ Gmail vừa tạo, đặt `MAIL_SCHEME=smtp`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`.
6. Chạy `php artisan config:clear`, sau đó vào `/repurchase-reminders` để gửi thử Email Broadcast tới bất kỳ địa chỉ email người nhận nào đã gắn trên hồ sơ Khách hàng / Liên hệ. *(Sau khi test xong hoặc khi đổi sang SMTP công ty, có thể xóa App Password này tại trang Google Account bất kỳ lúc nào.)*

### Bước 3: Khởi chạy Queue Worker & Scheduler

Vì chức năng hỗ trợ **gửi bất đồng bộ qua Queue** và **hẹn lịch gửi tự động**, cần chạy 2 tiến trình nền sau trên máy chủ (hoặc mở 2 terminal khi dev):

```bash
# 1. Xử lý hàng đợi gửi Broadcast
php artisan queue:work --tries=3 --timeout=300

# 2. Chạy Scheduler (trên môi trường dev)
php artisan schedule:work
```

Trên máy chủ Linux/Production, thêm dòng sau vào `crontab -e`:

```cron
* * * * * cd /path/to/dt-crm && php artisan schedule:run >> /dev/null 2>&1
```

Bạn cũng có thể chạy thủ công 2 lệnh Artisan bất kỳ lúc nào để kiểm tra:
```bash
php artisan broadcast:dispatch-due
php artisan zalo:refresh-token
```

---

## 3. Hướng dẫn kết nối trên cổng Zalo Developers (khi triển khai thật)

### Điều kiện phía doanh nghiệp
1. Có **Zalo Official Account (OA) Doanh nghiệp đã xác thực (tích vàng)** bằng Giấy phép kinh doanh.
2. Đăng ký **gói dịch vụ Zalo OA trả phí** (Nâng cao / Premium) để có quyền gọi API gửi tin Truyền thông / Broadcast và có hạn mức (quota) tin nhắn hằng tháng.
3. Hệ thống CRM chạy trên tên miền có **HTTPS công khai** (nếu test local có thể dùng `ngrok` hoặc Cloudflare Tunnel).

### Các bước cấu hình trên Zalo Developers (`developers.zalo.me`)
1. Tạo ứng dụng mới (hoặc dùng ứng dụng có sẵn) và liên kết với Zalo OA doanh nghiệp → lấy `App ID` và `App Secret` điền vào `.env`.
2. Trong mục **Official Account → Thiết lập chung**:
   - Điền **Official Account Callback URL**:
     ```
     https://<domain-cua-ban>/zalo-oa/oauth/callback
     ```
   - Đảm bảo giá trị này khớp chính xác với `ZALO_OA_REDIRECT_URI` trong `.env`.
3. Trong mục **Webhook**:
   - Điền **Webhook URL**:
     ```
     https://<domain-cua-ban>/webhooks/zalo-oa
     ```
   - Lấy **OA Secret Key** điền vào `ZALO_OA_WEBHOOK_SECRET` trong `.env` (hệ thống tự động xác minh chữ ký `X-ZEvent-Signature` bằng SHA-256).
   - Bật tối thiểu 4 sự kiện sau:
     - `follow` (tự động khớp SĐT và gán `zalo_user_id` + `zalo_followed_at` cho Liên hệ)
     - `unfollow` (xóa `zalo_followed_at`)
     - `user_received_message` (cập nhật trạng thái người nhận thành **Đã nhận**)
     - `user_seen_message` (cập nhật trạng thái người nhận thành **Đã xem**)
4. Vào CRM bằng tài khoản **Quản lý (Manager/Admin)** → trang `/repurchase-reminders` → mở modal **Gửi Broadcast** → bấm nút **Kết nối Zalo OA (OAuth v4)** và cấp quyền.
   - Sau khi cấp quyền thành công, `access_token` và `refresh_token` được lưu mã hóa trong bảng `zalo_oa_connections` và tự động làm mới định kỳ mỗi giờ qua lệnh `zalo:refresh-token`.

---

## 4. Hướng dẫn sử dụng trên giao diện CRM

1. **Chuẩn bị dữ liệu người nhận (Khách hàng → Liên hệ):**
   - Đảm bảo người liên hệ có **Email** (nếu gửi Email) và **Zalo OA User ID** (nếu gửi Zalo OA).
   - Có thể nhập/sửa thủ công `Zalo OA User ID` trong modal **Thêm / Sửa người liên hệ** tại trang chi tiết Khách hàng (`/customers/{id}`), hoặc để Webhook tự đồng bộ khi khách bấm Quan tâm OA.
   - Nếu liên hệ có tích **"Từ chối nhận tin tiếp thị"** (`marketing_opt_out = true`), hệ thống sẽ tự động bỏ qua và ghi rõ lý do trong lịch sử chiến dịch.

2. **Soạn và gửi Tin nhắn Broadcast:**
   - Truy cập mục **Nhắc nhở mua lại (`/repurchase-reminders`)**.
   - Tick chọn 1 hoặc nhiều đợt giao dịch trên bảng → bấm **"Gửi Broadcast (N)"** (hoặc bấm biểu tượng gửi nhanh trên từng dòng).
   - Trong modal **Soạn & Gửi Tin nhắn Broadcast**:
     - Kiểm tra số lượng khách hợp lệ cho từng kênh (*Email hợp lệ*, *Zalo OA User ID hợp lệ*).
     - Chọn **Mục đích** (*Nhắc nhở mua lại* / *Quảng cáo & Khuyến mãi*) và **Kênh gửi** (*Email* / *Zalo OA* / cả hai).
     - Chọn **Thời điểm gửi**: *Gửi ngay (Queue)* hoặc *Hẹn lịch gửi* (chọn ngày & giờ).
     - Soạn **1 bài viết chính + tối đa 4 bài viết phụ** (Tiêu đề, Tóm tắt, URL ảnh bìa, Đường dẫn CTA, Nhãn nút CTA) và quan sát trực tiếp ở khung **Xem trước (Mobile Preview)**.
     - Bấm **Gửi Broadcast ngay** hoặc **Lưu lịch gửi Broadcast**.

3. **Theo dõi kết quả & Lịch sử:**
   - Bấm nút **Lịch sử Broadcast** ở góc trên trang `/repurchase-reminders` để xem danh sách các chiến dịch đã gửi / đang hẹn lịch.
   - Bấm dấu **`+`** ở đầu mỗi dòng chiến dịch để xem chi tiết từng người nhận (kênh gửi, địa chỉ nhận, trạng thái *Đã gửi / Đã nhận / Đã xem / Thất bại / Bỏ qua* và lý do cụ thể).
   - Các khách hàng gửi thành công được tự động cập nhật `last_contacted_at` và ghi nhận sự kiện `broadcast_sent` vào `audit_logs`.

---

## 5. Chạy kiểm thử tự động (PHPUnit)

Toàn bộ luồng gửi đa kênh, lọc opt-out, phân quyền Sales/Manager, Queue, Hẹn lịch, OAuth v4 PKCE và Webhook đã có bộ test tự động. Chạy lệnh sau để kiểm tra sau mỗi lần chỉnh sửa:

```bash
php vendor/bin/phpunit tests/Feature/BroadcastTest.php tests/Feature/ZaloOaIntegrationTest.php tests/Feature/RepurchaseReminderTest.php
```

---

## 6. Gợi ý mở rộng cho giai đoạn tiếp theo

Thành viên tiếp nhận có thể mở rộng thêm các tính năng sau dựa trên kiến trúc Driver hiện có:
1. **Upload ảnh bìa trực tiếp từ máy tính:** thêm endpoint upload ảnh vào `storage/public/broadcasts` (hoặc gọi API `upload/image` của Zalo OA) thay vì chỉ nhập `cover_image_url`.
2. **Chèn nhanh sản phẩm từ Sapo (`sapo_products`):** thêm nút chọn sản phẩm trong `BroadcastComposerModal.tsx` để tự đổ tên, ảnh và mô tả sản phẩm vào bài viết Broadcast.
3. **Thêm Driver ZNS (Zalo Notification Service):** tạo thêm `ZnsBroadcastDriver implements BroadcastDriverInterface` làm kênh dự phòng gửi theo SĐT cho những khách chưa quan tâm Zalo OA.
4. **Rate Limiting khi gửi số lượng lớn:** tách `DispatchBroadcastCampaignJob` thành các job nhỏ theo từng `BroadcastRecipient` kèm `RateLimited` middleware nếu danh sách gửi vượt vài trăm khách hàng/lần.
