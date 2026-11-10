# Hướng dẫn Chức năng Lịch trình (Google Calendar Style) — D&T CRM

Chức năng **Lịch trình** trong hệ thống D&T CRM được thiết kế và xây dựng theo trải nghiệm người dùng của **Google Calendar**, giúp đội ngũ kinh doanh và quản lý theo dõi, điều phối công việc chăm sóc khách hàng một cách trực quan, chính xác theo thời gian thực.

---

## 1. Các chế độ xem (Views)

Hệ thống hỗ trợ 4 chế độ xem linh hoạt chuyển đổi trên thanh công cụ:

| Chế độ xem | Mô tả & Trải nghiệm | Thao tác tương tác |
| :--- | :--- | :--- |
| **Tháng (Month)** | Lưới 7 cột (Thứ 2 đến Chủ Nhật) theo chuẩn lịch Việt Nam. Ngày hiện tại được đánh dấu bằng huy hiệu tròn xanh nổi bật. Mỗi sự kiện hiển thị dưới dạng thẻ (chip) kèm giờ và dải màu nhận diện. | • Click vào ô ngày trống để tạo lịch mới vào 09:00 ngày đó.<br>• Click vào `+ X nhiệm vụ khác` để mở danh sách toàn bộ việc trong ngày.<br>• Click vào thẻ để xem chi tiết. |
| **Tuần (Week)** | Chia 7 cột tương ứng 7 ngày trong tuần với trục thời gian từ `07:00` đến `21:00`. Có **đường chỉ thị giờ thực tế màu đỏ (Red Now-Indicator)** hiển thị chính xác vị trí phút/giờ hiện tại. | • Click vào bất kỳ khung giờ nào để tạo lịch đúng ngày và giờ đó.<br>• Các khối lịch hiển thị chính xác vị trí theo thời gian bắt đầu. |
| **Ngày (Day)** | Dòng thời gian chi tiết theo từng giờ của ngày được chọn. Khối sự kiện mở rộng hiển thị tiêu đề, khách hàng liên quan, loại việc, mức ưu tiên và mô tả vắn tắt. | • Theo dõi chi tiết lịch trình trong ngày của bản thân hoặc nhân viên.<br>• Click vào khung giờ để tạo việc mới. |
| **Lịch biểu (Agenda)** | Danh sách lịch trình theo thứ tự thời gian được gom nhóm theo từng ngày, có nhãn phân biệt *Hôm nay*, *Ngày mai*. | • Thao tác nhanh: bấm nút hoàn thành hoặc chỉnh sửa trực tiếp trên từng mục. |

---

## 2. Bảng mã màu nhận diện theo loại công việc (Color Coding)

Mỗi loại lịch trình được gắn mã màu và biểu tượng riêng giúp nhận biết nhanh chóng trên lịch:

| Biểu tượng | Loại công việc | Mã màu | Ý nghĩa sử dụng |
| :---: | :--- | :---: | :--- |
| 📞 | **Gọi điện** | `#059669` (Xanh lá) | Cuộc gọi trao đổi, tư vấn với khách hàng |
| 💬 | **Liên hệ Zalo** | `#2563eb` (Xanh dương) | Nhắn tin, gửi tài liệu qua Zalo |
| 🤝 | **Gặp / khảo sát** | `#7c3aed` (Tím) | Đi gặp trực tiếp, khảo sát nhà máy/công trường |
| ✉️ | **Gửi email** | `#0891b2` (Xanh ngọc) | Gửi thư chào giá, tài liệu kỹ thuật, hợp đồng |
| 📑 | **Theo dõi báo giá** | `#d97706` (Hổ phách) | Đôn đốc, cập nhật phản hồi báo giá |
| 📦 | **Theo dõi mẫu thử** | `#ea580c` (Cam) | Theo dõi đánh giá chất lượng hàng mẫu |
| 🏢 | **Đăng ký NCC** | `#4f46e5` (Chàm) | Hoàn thiện hồ sơ đăng ký nhà cung cấp |
| 🔄 | **Chăm sóc** | `#0d9488` (Mòng két) | Thăm hỏi định kỳ, duy trì mối quan hệ |
| 📌 | **Nội bộ** | `#475569` (Xám đá) | Công việc nội bộ, họp phòng ban |

---

## 3. Các tính năng nổi bật

### 3.1. Thanh điều hướng thông minh (Smart Toolbar)
- **Nút "Hôm nay"**: Quay về thời điểm hiện tại chỉ với 1 cú click.
- **Nút `<` và `>`**: Chuyển nhanh qua lại giữa các tuần, tháng hoặc ngày.
- **Tiêu đề thời gian động**: Hiển thị rõ ràng khoảng thời gian đang xem (VD: *Tháng 11 năm 2026*, *09 - 15 Tháng 11, 2026*).
- **Tìm kiếm tức thì**: Lọc nhanh lịch trình theo tiêu đề hoặc nội dung.
- **Thanh chỉ số nhanh (KPI Chips)**: Hiển thị ngay số lượng việc *Tổng số*, *Chưa xong*, *Quá hạn* (cảnh báo đỏ) và *Đã hoàn thành*.

### 3.2. Cửa sổ chi tiết sự kiện (Event Detail Modal)
Click vào bất kỳ thẻ lịch nào để mở cửa sổ chi tiết với đầy đủ thông tin:
- Thời gian thực hiện (định dạng giờ Việt Nam, GMT+7).
- Huy hiệu loại công việc, mức độ ưu tiên (Bình thường / Cao / Khẩn cấp).
- Trạng thái công việc (Chờ thực hiện / Đang thực hiện / Hoàn thành / Đã hủy).
- Liên kết hồ sơ liên quan (bấm chuyển thẳng đến trang Khách hàng hoặc Lead).
- Người thực hiện (Assignee).
- Nội dung chi tiết và Kết quả thực hiện / Lý do hủy.
- **Phím tắt thao tác nhanh**:
  - `[Hoàn thành]`: Đổi trạng thái sang hoàn tất kèm nhập kết quả.
  - `[Bắt đầu] / [Về chờ]`: Cập nhật tiến độ xử lý.
  - `[Chỉnh sửa]`: Mở modal sửa thông tin.
  - `[Hủy nhiệm vụ]`: Ghi nhận lý do hủy việc.
  - `[Mở lại nhiệm vụ]`: Dành cho Quản lý khi cần mở lại việc đã đóng.

### 3.3. Bộ lọc chuyên sâu (Drawer Filter)
Bấm nút **Bộ lọc** trên thanh công cụ để mở ngăn lọc:
- Lọc theo **Loại lịch trình** (Gọi điện, Gặp mặt, Báo giá, ...).
- Lọc theo **Mức ưu tiên** (Khẩn cấp, Cao, Bình thường).
- Lọc theo **Trạng thái** (Chưa hoàn thành, Hoàn thành, Đã hủy, Tất cả).
- Lọc theo **Người thực hiện** (Quản lý có thể xem toàn bộ nhân viên hoặc lọc riêng từng bạn).

---

## 4. Phân quyền bảo mật (Role-Based Access)

- **Nhân viên kinh doanh (Sales)**: Chỉ xem và quản lý lịch trình của chính mình hoặc những khách hàng/lead mà mình được phân công phụ trách.
- **Quản lý / Quản trị viên (Manager / Admin)**:
  - Xem toàn bộ lịch trình của tất cả nhân viên trong hệ thống.
  - Lọc theo từng nhân viên để kiểm tra tiến độ và điều phối công việc.
  - Có quyền mở lại các nhiệm vụ đã hoàn thành hoặc đã hủy.

---

## 5. Cấu trúc mã nguồn

```
app/Http/Controllers/Care/
└── CalendarController.php          # Backend controller xử lý truy vấn thời gian, phân quyền, bộ lọc

resources/js/pages/Calendar/
├── Index.tsx                       # Trang chính điều phối Toolbar, Stats, Filter Drawer và 4 Views
├── MonthView.tsx                   # Lưới lịch Tháng (Month grid)
├── WeekView.tsx                    # Lưới lịch Tuần (Week time-grid với Red Now Line)
├── DayView.tsx                     # Dòng thời gian Ngày (Day timeline)
├── AgendaView.tsx                  # Lịch biểu dạng danh sách (Agenda list)
├── EventDetailModal.tsx            # Popup xem chi tiết & thao tác nhanh
├── DayEventsModal.tsx              # Popup danh sách công việc khi click "+ X khác"
└── types.ts                        # TypeScript interfaces & bảng màu TYPE_CONFIG

resources/css/app.css               # Bộ quy tắc CSS .crm-cal-* cho toàn bộ giao diện lịch
tests/Feature/CalendarTest.php      # 6 bài kiểm thử tự động (81 assertions) bảo đảm tính năng hoạt động ổn định
```
