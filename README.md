# DỰ ÁN BÀI TẬP NHÓM: KAHOOT CLONE (THƯ MỤC BTN)

> **Hệ thống Trắc nghiệm Tương tác Thời gian thực (Real-time Quiz Game)**  
> Phát triển dựa trên giao diện và kiến trúc của Kahoot!

---

## 📌 1. Giới thiệu dự án
Thư mục `BTN` là bộ khung sườn hoàn chỉnh (Architecture Skeleton / Starter Boilerplate) được xây dựng dành cho nhóm 4 sinh viên cùng tham gia phát triển. Hệ thống kế thừa trọn vẹn phong cách giao diện đặc trưng của Kahoot! (màu tím thương hiệu `#46178F`, nền đổi màu sinh động, 4 ô đáp án màu Đỏ ▲, Xanh dương 🔷, Vàng 🟡, Xanh lá 🟩, đồng hồ đếm ngược tròn, màn hình chờ PIN lớn và bục vinh danh Podium).

---

## 📁 2. Cấu trúc thư mục Khung sườn `BTN/`

```text
BTN/
├── config/
│   ├── config.php                  # Cấu hình hệ thống, BASE_URL tự động, màu sắc Kahoot
│   └── database.php                # Kết nối PDO tự thích ứng (hỗ trợ cả port 3306 & 3307), hàm CRUD
├── assets/
│   ├── css/
│   │   └── kahoot-theme.css        # CSS phong cách Kahoot (4 màu nút, responsive mobile/desktop)
│   ├── js/
│   │   └── app.js                  # Xử lý tương tác giao diện, AJAX helper
│   └── img/                        # Logo Kahoot, mockup phone, cover, avatar
├── includes/
│   ├── header.php                  # Header chung (Bootstrap 4, Google Fonts, Theme CSS)
│   ├── navbar.php                  # Thanh điều hướng linh hoạt (trước & sau đăng nhập)
│   ├── footer.php                  # Footer & Modal Đăng nhập / Đăng ký
│   └── auth_check.php              # Middleware bảo vệ trang yêu cầu quyền Host/Creator
├── modules/
│   ├── auth/                       # [THÀNH VIÊN 1] Phân hệ Xác thực & Quản lý Người dùng
│   │   ├── login.php               # Đăng nhập (hỗ trợ SHA512, bcrypt)
│   │   ├── register.php            # Đăng ký tài khoản giáo viên/creator
│   │   ├── logout.php              # Đăng xuất an toàn
│   │   ├── profile.php             # Đổi thông tin cá nhân, đổi mật khẩu
│   │   └── dashboard.php           # Thống kê tổng quan & Danh sách Quiz
│   ├── quiz/                       # [THÀNH VIÊN 2] Phân hệ Quản lý Đề & Câu hỏi
│   │   ├── list.php                # Kho đề trắc nghiệm (My Kahoots)
│   │   ├── create.php              # Tạo đề trắc nghiệm mới
│   │   ├── edit.php                # Thêm/sửa câu hỏi, 4 phương án trắc nghiệm màu, thời gian, điểm
│   │   └── delete.php              # Xóa đề trắc nghiệm
│   ├── host/                       # [THÀNH VIÊN 3] Phân hệ Host / Máy chiếu Phòng Game
│   │   ├── create_room.php         # Khởi tạo phòng thi, sinh Game PIN 6 số ngẫu nhiên
│   │   ├── lobby.php               # Phòng chờ chiếu PIN lớn, danh sách người chơi real-time
│   │   ├── play.php                # Máy chiếu câu hỏi, đồng hồ đếm ngược, hiển thị kết quả
│   │   └── leaderboard.php         # Bảng xếp hạng điểm số & Bục nhận giải Podium Top 3
│   ├── player/                     # [THÀNH VIÊN 4] Phân hệ Người chơi (Client)
│   │   ├── join.php                # Màn hình nhập Game PIN
│   │   ├── nickname.php            # Màn hình đặt tên người chơi
│   │   ├── waiting.php             # Chờ Host bắt đầu trận đấu
│   │   └── play.php                # 4 nút màu bấm chọn đáp án, phản hồi đúng/sai tức thì
│   └── api/                        # API dùng chung phục vụ Polling thời gian thực
│       ├── game_state.php          # Đồng bộ trạng thái phòng & danh sách người chơi
│       └── submit_answer.php       # Chấm điểm câu trả lời theo thời gian
├── database/
│   └── kahoot_btn.sql              # Script CSDL hoàn chỉnh, có sẵn tài khoản và câu hỏi mẫu
├── PHAN_CONG_NHIEM_VU.md           # Hướng dẫn chi tiết phân chia công việc cho 4 người
├── index.php                       # Trang chủ Landing Page chuẩn phong cách Kahoot!
└── README.md                       # Tài liệu hướng dẫn này
```

---

## 🚀 3. Hướng dẫn Khởi chạy & Kiểm thử (Quick Start)

### Bước 1: Khởi động XAMPP
1. Mở **XAMPP Control Panel**.
2. Nhấn **Start** ở cả 2 dịch vụ **Apache** và **MySQL**.
   *(Hệ thống đã được lập trình sẵn cơ chế tự động thử cổng 3306 và 3307 của MySQL nên hoạt động được trên máy tính của tất cả các thành viên)*.

### Bước 2: Import Cơ sở dữ liệu (Nếu chạy trên máy mới)
- Mở phpMyAdmin: `http://localhost/phpmyadmin` (hoặc theo cổng Apache của máy bạn).
- Tạo Database tên: `kahoot_btn` (Bảng mã: `utf8mb4_unicode_ci`)
- Chọn tab **Import** &rarr; Chọn file `BTN/database/kahoot_btn.sql` &rarr; Nhấn **Go**.

### Bước 3: Truy cập vào ứng dụng
- Mở trình duyệt web truy cập đường dẫn:
  - Nếu Apache dùng cổng mặc định 80: `http://localhost/Kahoot/BTN/`
  - Nếu Apache dùng cổng riêng (ví dụ máy host 3050): `http://localhost:3050/Kahoot/BTN/`

---

## 🔑 4. Tài khoản mẫu có sẵn để Test

| Vai trò | Tên đăng nhập (Username) | Mật khẩu | Chức năng |
| :--- | :--- | :--- | :--- |
| **Giáo viên / Host** | `admin` | `123456` | Tạo đề, mở phòng thi đấu, chiếu câu hỏi |
| **Giáo viên 2** | `teacher` | `123456` | Quản lý đề cá nhân |

---

## 🎮 5. Quy trình Chơi Thử Nghiệm 2 vai (Host & Player)
Để trải nghiệm trọn vẹn tính năng thời gian thực ngay trên 1 máy tính:
1. **Vai trò Host (Máy chiếu):**
   - Mở trình duyệt Chrome thường: Truy cập `http://localhost:3050/Kahoot/BTN/`
   - Bấm **Log In** &rarr; Nhập `admin` / `123456`.
   - Vào **My Kahoots** &rarr; Bấm **Chơi ngay** ở bộ đề demo &rarr; Màn hình Lobby hiện mã **Game PIN 6 số**.
2. **Vai trò Player (Người chơi):**
   - Mở **Tab Ẩn danh (Incognito)** hoặc trình duyệt khác (Edge/Firefox):
   - Truy cập `http://localhost:3050/Kahoot/BTN/modules/player/join.php`
   - Nhập mã **Game PIN** hiển thị ở màn hình Host &rarr; Bấm **Tham Gia**.
   - Nhập **Nickname** (ví dụ: `NamPro`) &rarr; Nhìn lại màn hình Host sẽ thấy tên xuất hiện ngay lập tức!
3. **Bắt đầu thi đấu:**
   - Trên màn hình Host: Bấm **BẮT ĐẦU GAME**.
   - Màn hình Host đếm ngược câu hỏi và hiện 4 đáp án màu.
   - Màn hình Player xuất hiện 4 nút màu lớn để chạm/click chọn đáp án!
   - Hết giờ hoặc nộp bài xong: Host hiện thống kê, Player hiện thông báo Đúng/Sai kèm điểm thưởng!
