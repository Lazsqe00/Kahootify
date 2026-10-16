# KẾ HOẠCH PHÂN CHIA NHIỆM VỤ DỰ ÁN BÀI TẬP NHÓM (4 THÀNH VIÊN)
## ĐỀ TÀI: XÂY DỰNG ỨNG DỤNG TRẮC NGHIỆM TRỰC TUYẾN KAHOOT! CLONE

---

## 🎯 1. BẢNG PHÂN CHIA VAI TRÒ TỔNG THỂ

| Thành Viên | Phân Hệ / Module Phụ Trách | Trọng Tâm Công Việc | Thư Mục / File Chính |
| :--- | :--- | :--- | :--- |
| **Thành viên 1** *(Trưởng nhóm)* | **Auth & Dashboard** | Quản lý Tài khoản, Phân quyền Giáo viên/Học sinh, Bảng điều khiển thống kê tổng quan | `modules/auth/`<br>`includes/auth_check.php` |
| **Thành viên 2** | **Quiz & Question Bank** | Quản lý Bộ đề (CRUD), Ngân hàng câu hỏi, 4 phương án màu, Thời gian & Điểm số | `modules/quiz/` |
| **Thành viên 3** | **Game Host Engine** | Điều khiển phòng thi đấu (Lobby PIN 6 số, Màn chiếu câu hỏi, Đồng hồ đếm ngược, Leaderboard & Bục trao giải) | `modules/host/`<br>`modules/api/` |
| **Thành viên 4** | **Player Client** | Màn hình Người chơi (Nhập PIN, Nickname, Giao diện 4 nút màu chạm cảm ứng, Tính điểm theo tốc độ) | `modules/player/`<br>`modules/api/` |

---

## 📋 2. CHI TIẾT NHIỆM VỤ TỪNG THÀNH VIÊN & HƯỚNG DẪN MỞ RỘNG

### 👤 THÀNH VIÊN 1: Phân hệ Xác thực & Bảng điều khiển (Auth & Dashboard)
* **Mục tiêu:** Đảm bảo hệ thống bảo mật, quản lý giáo viên/host đăng nhập, thống kê trực quan.
* **Các file phụ trách hiện tại:**
  - `BTN/modules/auth/login.php` (Xử lý đăng nhập)
  - `BTN/modules/auth/register.php` (Xử lý đăng ký tài khoản)
  - `BTN/modules/auth/logout.php` (Xử lý đăng xuất)
  - `BTN/modules/auth/profile.php` (Xem & đổi thông tin cá nhân, đổi mật khẩu)
  - `BTN/modules/auth/dashboard.php` (Bảng thống kê số lượng đề, phòng, người chơi)
  - `BTN/includes/auth_check.php` (Middleware kiểm tra phiên làm việc)
* **Checklist nâng cấp tiếp theo để đạt điểm tối đa:**
  - [ ] **Upload Avatar cá nhân:** Thêm chức năng chọn ảnh đại diện từ máy tính (`.jpg`, `.png`), lưu vào thư mục `assets/img/avatars/` và hiển thị trên thanh navbar.
  - [ ] **Xác thực dữ liệu chặt chẽ (Validation):** Kiểm tra email hợp lệ, mật khẩu có ít nhất 6 ký tự gồm chữ và số, thông báo lỗi đẹp bằng Bootstrap Alert / SweetAlert.
  - [ ] **Ghi nhớ đăng nhập (Remember Me):** Sử dụng Cookie an toàn để lưu trạng thái đăng nhập khi người dùng chọn "Nhớ mật khẩu".
  - [ ] **Phân quyền nâng cao (Admin Panel):** Nếu tài khoản có `role = 3` (Admin), hiển thị thêm menu quản lý danh sách toàn bộ giáo viên và các bộ đề trong hệ thống.

---

### 👤 THÀNH VIÊN 2: Phân hệ Quản lý Bộ đề & Ngân hàng câu hỏi (Quiz Management)
* **Mục tiêu:** Cung cấp công cụ soạn thảo đề trắc nghiệm trực quan, dễ dùng cho giáo viên.
* **Các file phụ trách hiện tại:**
  - `BTN/modules/quiz/list.php` (Danh sách My Kahoots kèm tìm kiếm, số câu)
  - `BTN/modules/quiz/create.php` (Form tạo bộ đề mới)
  - `BTN/modules/quiz/edit.php` (Trình quản lý câu hỏi & 4 phương án màu trắc nghiệm)
  - `BTN/modules/quiz/delete.php` (Xóa an toàn bộ đề và các câu hỏi phụ thuộc)
* **Checklist nâng cấp tiếp theo để đạt điểm tối đa:**
  - [ ] **Upload ảnh minh họa câu hỏi:** Thêm ô chọn ảnh cho từng câu hỏi, lưu vào `assets/img/questions/` để Host có thể chiếu kèm hình ảnh khi thi đấu.
  - [ ] **Thêm loại câu hỏi Đúng / Sai (True / False):** Cho phép giáo viên chọn dạng câu hỏi chỉ có 2 phương án (Xanh dương - Đúng / Đỏ - Sai).
  - [ ] **Chỉnh sửa nội dung câu hỏi đã tạo:** Thêm nút sửa câu hỏi (Edit question modal) để giáo viên cập nhật lại đáp án hoặc đổi thời gian suy nghĩ mà không cần xóa đi tạo lại.
  - [ ] **Import câu hỏi từ file Excel/CSV:** Viết tính năng đọc file mẫu danh sách câu hỏi để tạo hàng loạt 20-30 câu hỏi chỉ với 1 click.

---

### 👤 THÀNH VIÊN 3: Phân hệ Host Engine & Máy chiếu phòng Game (Game Presenter)
* **Mục tiêu:** Màn hình chính của buổi chơi, dùng để trình chiếu lên tivi hoặc máy chiếu trên lớp học.
* **Các file phụ trách hiện tại:**
  - `BTN/modules/host/create_room.php` (Tạo phòng, sinh PIN 6 số)
  - `BTN/modules/host/lobby.php` (Phòng chờ hiển thị PIN lớn, danh sách người chơi real-time)
  - `BTN/modules/host/play.php` (Màn chiếu câu hỏi, đồng hồ đếm ngược tròn, thống kê đáp án)
  - `BTN/modules/host/leaderboard.php` (Bảng điểm tạm thời & Bục trao giải Podium Top 3)
* **Checklist nâng cấp tiếp theo để đạt điểm tối đa:**
  - [ ] **Tạo mã QR tự động:** Sử dụng thư viện `qrcode.js` hoặc Google Chart API để tạo mã QR từ URL tham gia phòng, giúp người chơi chỉ cần quét camera điện thoại là vào phòng ngay.
  - [ ] **Tích hợp Nhạc nền Kahoot (Audio Effects):** Thêm file âm thanh `lobby-music.mp3`, `countdown-timer.mp3`, `times-up.wav` để buổi chơi sôi động y hệt Kahoot thật.
  - [ ] **Hiệu ứng đồ họa chuyển động:** Dùng CSS Animation hoặc thư viện `Animate.css` cho bảng xếp hạng nhảy điểm và pháo hoa rực rỡ ở màn hình Podium trao giải.
  - [ ] **Nút Kick người chơi vi phạm:** Cho phép Host click vào tên người chơi trong phòng chờ để loại bỏ những bạn đặt nickname không phù hợp.

---

### 👤 THÀNH VIÊN 4: Phân hệ Client Người chơi (Player Mobile App)
* **Mục tiêu:** Giao diện tối ưu cho màn hình cảm ứng điện thoại thông minh và máy tính cá nhân.
* **Các file phụ trách hiện tại:**
  - `BTN/modules/player/join.php` (Nhập PIN phòng thi)
  - `BTN/modules/player/nickname.php` (Đặt biệt danh tham gia)
  - `BTN/modules/player/waiting.php` (Màn hình chờ hiệu lệnh từ Host)
  - `BTN/modules/player/play.php` (4 nút màu cảm ứng to rõ, màn hình phản hồi Đúng/Sai)
  - `BTN/modules/api/submit_answer.php` (Thuật toán tính điểm theo tốc độ trả lời)
* **Checklist nâng cấp tiếp theo để đạt điểm tối đa:**
  - [ ] **Thuật toán điểm số theo giây (Speed Bonus):** Điểm tối đa 1000 điểm; nếu trả lời ở giây thứ 1 được 980 điểm, trả lời ở giây thứ 19 được 520 điểm (khuyến khích phản xạ nhanh).
  - [ ] **Chuỗi câu trả lời đúng (Streak Bonus):** Đếm số câu đúng liên tiếp (Answer Streak 🔥 x2, x3), cộng thêm điểm thưởng cho người chơi giữ vững phong độ.
  - [ ] **Hiệu ứng rung (Haptic Feedback):** Trên điện thoại di động, gọi hàm `navigator.vibrate(200)` khi bấm nút để mang lại cảm giác bấm nút vật lý chân thực.
  - [ ] **Báo cáo tổng kết cá nhân:** Kết thúc game, hiển thị chi tiết số câu đúng/sai của bản thân và lời khen/động viên.

---

## 🛠️ 3. QUY TRÌNH PHỐI HỢP GIT ĐỂ KHÔNG BỊ XUNG ĐỘT (NO-CONFLICT WORKFLOW)

Khung sườn `BTN` đã được **chia nhỏ theo từng thư mục độc lập** (`modules/auth/`, `modules/quiz/`, `modules/host/`, `modules/player/`). Mỗi bạn chỉ làm việc trong thư mục của mình nên tỷ lệ bị xung đột mã nguồn (merge conflict) là gần như bằng 0.

### Quy ước làm việc:
1. **Quy tắc tạo nhánh (Branching):**
   - Nhánh chính: `main` (chỉ merge khi code đã chạy ổn định).
   - Nhánh của Thành viên 1: `feature/auth-dashboard`
   - Nhánh của Thành viên 2: `feature/quiz-management`
   - Nhánh của Thành viên 3: `feature/host-engine`
   - Nhánh của Thành viên 4: `feature/player-client`

2. **Các lệnh Git cơ bản mỗi ngày:**
   ```bash
   # Bước 1: Kéo code mới nhất từ main về trước khi làm việc
   git checkout main
   git pull origin main

   # Bước 2: Chuyển sang nhánh cá nhân của bạn
   git checkout feature/quiz-management
   git merge main

   # Bước 3: Code và Commit công việc của mình
   git add .
   git commit -m "feat(quiz): them chuc nang upload anh minh hoa cho cau hoi"

   # Bước 4: Đẩy lên GitHub
   git push origin feature/quiz-management
   ```
3. **Quy tắc về file cấu hình:**
   - Tuyệt đối **không sửa** file `BTN/config/config.php` và `BTN/config/database.php` một cách tùy tiện. Mọi thay đổi ở 2 file này cần thảo luận cả nhóm trước khi sửa.

---

## 📅 4. KẾ HOẠCH TIẾN ĐỘ THỰC HIỆN (TIMELINE GỢI Ý)

| Tuần | Mục tiêu chính | Nhiệm vụ các thành viên |
| :---: | :--- | :--- |
| **Tuần 1** | **Khởi động & Nắm bắt khung sườn** | - Cả nhóm tải code `BTN/` về chạy thử 2 vai Host & Player trên máy cá nhân.<br>- Nhận phân công nhiệm vụ và tạo nhánh Git cá nhân.<br>- Thành viên 1 hoàn thiện validate form & upload avatar.<br>- Thành viên 2 hoàn thiện giao diện thêm câu hỏi. |
| **Tuần 2** | **Phát triển tính năng nâng cao** | - Thành viên 3 tích hợp âm thanh nhạc nền và mã QR vào phòng chờ Lobby.<br>- Thành viên 4 hoàn thiện tính điểm theo tốc độ và hiệu ứng rung trên mobile.<br>- Thành viên 2 bổ sung dạng câu hỏi Đúng/Sai.<br>- Thành viên 1 xây dựng bảng thống kê chi tiết. |
| **Tuần 3** | **Kiểm thử tích hợp (Integration Test)** | - Cả nhóm họp trực tiếp: 1 bạn làm Host chiếu máy chiếu, 3 bạn dùng điện thoại thật kết nối cùng mạng Wi-Fi vào chơi thử.<br>- Tìm và sửa các lỗi phát sinh (Fix bugs).<br>- Tối ưu tốc độ tải và kiểm tra bảo mật (SQL Injection, XSS). |
| **Tuần 4** | **Viết Báo cáo & Chuẩn bị Slide** | - Đóng gói sản phẩm, xuất file database `.sql` hoàn chỉnh.<br>- Viết báo cáo Word/PDF (Kiến trúc hệ thống, Biểu đồ use-case, ERD).<br>- Làm slide thuyết trình và quay video demo hoạt động của hệ thống. |

---

## 💯 5. BẢNG TIÊU CHÍ ĐÁNH GIÁ ĐỂ ĐẠT ĐIỂM CAO KHI CHẤM THI

1. **Giao diện & Trải nghiệm (UI/UX):** Bám sát 100% nhận diện của Kahoot! (Màu tím, 4 khối màu đáp án, hiệu ứng chuyển màu nền, giao diện responsive mượt mà).
2. **Tính ổn định (Real-time & Stability):** Không bị đơ hay lệch câu hỏi khi Host chuyển câu; chấm điểm chuẩn xác; lưu trữ cơ sở dữ liệu đồng bộ.
3. **Chất lượng mã nguồn (Code Quality):** Code có chú thích (comment) rõ ràng, sử dụng PDO Prepared Statements chống SQL Injection, phân tách module khoa học.
4. **Làm việc nhóm (Teamwork):** Lịch sử commit trên Git đồng đều giữa 4 thành viên; phân chia công việc rõ ràng, rành mạch.
