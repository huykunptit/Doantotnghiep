# ERIPT LMS 🎓

ERIPT LMS là nền tảng quản lý học tập (Learning Management System) trực tuyến toàn diện với trải nghiệm mua khóa học và thanh toán tích hợp. Hệ thống được thiết kế với giao diện hiện đại (chuẩn Editorial) và kiến trúc mạnh mẽ, cung cấp trải nghiệm học tập và giảng dạy tối ưu, kết hợp cùng hệ thống thi trắc nghiệm, giám sát thi chuyên nghiệp và dịch vụ AI mở rộng.

> **Lưu ý**: Đây là dự án Đồ án tốt nghiệp (Học viện Công nghệ Bưu chính Viễn thông - PTIT).

---

## 🌟 Tính năng nổi bật

### 👨‍🎓 Dành cho Học viên (Students)
- **Khám phá Khóa học**: Giao diện danh mục khóa học trực quan, tìm kiếm và lọc khóa học dễ dàng.
- **Trang Chi tiết Khóa học**: Cung cấp đầy đủ thông tin giảng viên, lộ trình học, video học thử miễn phí.
- **Mua Khóa học & Thanh toán**: Học viên có thể mua khóa học, thanh toán trực tuyến và quản lý đơn hàng ngay trong hệ thống.
- **Không gian Học tập Tập trung**: Giao diện học tập (video, tài liệu) không phân tâm.
- **Hệ thống Thi & Kiểm tra**: Làm bài thi với bộ đếm ngược thời gian thực, lưu bài tự động (auto-save), xem lại kết quả chi tiết sau khi nộp.
- **Hỗ trợ Đa dạng Câu hỏi**: Trắc nghiệm 1 đáp án, nhiều đáp án, Đúng/Sai, Điền từ, Ghép nối, Câu hỏi số học, và Tự luận.

### 👨‍🏫 Dành cho Giảng viên (Instructors)
- **Quản lý Khóa học**: Khởi tạo và xây dựng nội dung khóa học (Chương, Bài học) trực tiếp qua giao diện Builder.
- **Quản lý Học viên**: Theo dõi tiến độ học tập và kết quả bài kiểm tra của học viên.
- **Quản trị Ngân hàng Câu hỏi**: Xây dựng kho câu hỏi phong phú phân loại theo độ khó, gắn thẻ (tags) và nhóm câu hỏi.

### 🛡️ Dành cho Quản trị viên (Administrators)
- **Kiểm duyệt Khóa học**: Hệ thống workflow xét duyệt khóa học trước khi xuất bản.
- **Quản lý Thanh toán & Đơn hàng**: Quản lý giao dịch, thanh toán, đơn hàng khóa học và lịch sử thanh toán của học viên.
- **Quản lý Kỳ thi Chuyên nghiệp**:
  - Hỗ trợ cả **Bài thi trong khóa học** và **Kỳ thi độc lập** (Standalone Exams).
  - Cấu hình linh hoạt: xáo trộn câu hỏi/đáp án, giới hạn số lần thi, tùy chỉnh ẩn/hiện kết quả (Review Options chuẩn Moodle).
- **Giám sát Thi Trực tiếp (Live Proctoring)**:
  - Bảng điều khiển giám sát theo thời gian thực (Real-time Monitor Dashboard).
  - Các quyền năng can thiệp: **Tạm dừng (Pause)**, **Cho phép tiếp tục (Resume)**, **Đình chỉ thi (Force Stop)** khi phát hiện vi phạm, và **Gia hạn thời gian (Extend Time)**.
  - Ghi nhận vi phạm tự động (tab-switching, v.v.).

---

## 🛠️ Công nghệ sử dụng

**Backend (RESTful API):**
- [Laravel 11](https://laravel.com/) (PHP)
- MySQL (Database)
- Redis (Caching & Queue)
- JWT Authentication

**Frontend (Client & Admin):**
- [Nuxt.js 3](https://nuxt.com/) (Vue.js 3)
- Tailwind CSS (Styling)
- Pinia (State Management)
- TypeScript

**Hạ tầng & Triển khai:**
- Docker & Docker Compose (Môi trường phát triển cục bộ)

---

## � Thanh toán & Giao dịch
- Hỗ trợ mua khóa học trực tuyến, quản lý giỏ hàng và đơn hàng.
- Quản lý giao dịch, thanh toán, lịch sử đơn hàng cho học viên.
- Dễ dàng bổ sung cổng thanh toán như VNPay, Stripe hoặc PayPal bằng biến môi trường.

## 🧠 Dịch vụ AI mở rộng
- **AI Career Advisor**: phân tích CV, trích xuất kỹ năng và đề xuất lộ trình nghề nghiệp.
- **AI Chatbot**: trợ lý ảo gợi ý khóa học, điều hướng chức năng và trả lời thắc mắc.
- **AI Content Generator**: đề xuất tiêu đề khóa học, mô tả bài học, quiz và đề thi mẫu.
- **Exam Analytics**: phân tích kết quả thi, phát hiện xu hướng sai và đề xuất cải thiện.
- **Smart Tutoring**: gợi ý học tập cá nhân hóa dựa trên tiến độ và hành vi học viên.

---

## �🚀 Hướng dẫn cài đặt (Local Development)

Yêu cầu môi trường: Có cài đặt sẵn **Docker** và **Docker Compose**.

### Bước 1: Clone kho lưu trữ
```bash
git clone https://github.com/your-username/eript-lms.git
cd eript-lms
```

### Bước 2: Cấu hình biến môi trường
**Backend:**
```bash
cd backend
cp .env.example .env
# Chỉnh sửa file .env với thông tin database phù hợp (ví dụ: host là mysql_db nếu dùng docker)
# Thêm cấu hình thanh toán nếu cần: PAYMENT_GATEWAY, PAYMENT_SECRET, PAYMENT_CALLBACK_URL
```

**Frontend:**
```bash
cd ../frontend
cp .env.example .env
# Đảm bảo NUXT_PUBLIC_API_BASE chỉ tới đúng URL của backend API
```

### Bước 3: Khởi chạy bằng Docker
Tại thư mục gốc của project (nơi chứa file `docker-compose.yml`), chạy lệnh:
```bash
docker-compose up -d --build
```

### Bước 4: Khởi tạo Database
Sau khi các container đã chạy, tiến hành migrate database và tạo dữ liệu mẫu (Seeder):
```bash
docker exec -it lms_backend php artisan migrate:fresh --seed
```

### Bước 5: Truy cập Ứng dụng
- **Frontend (Giao diện người dùng/Admin)**: `http://localhost:3000`
- **Backend API**: `http://localhost:8000`

---

## 📸 Ảnh chụp màn hình (Screenshots)

*(Vui lòng thêm link ảnh hoặc upload ảnh vào thư mục `docs/images` và chèn vào đây)*

- **Trang chủ**: `![Trang chủ]()`
- **Chi tiết khóa học**: `![Chi tiết khóa học]()`
- **Giao diện làm bài thi**: `![Làm bài thi]()`
- **Bảng giám sát thi (Admin)**: `![Giám sát thi]()`

---

## 📜 Giấy phép (License)
Dự án được xây dựng phục vụ mục đích học thuật (Đồ án tốt nghiệp).
