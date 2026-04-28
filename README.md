# EduPress LMS 🎓

EduPress LMS là nền tảng quản lý học tập (Learning Management System) trực tuyến toàn diện, được thiết kế với giao diện hiện đại (chuẩn Editorial) và kiến trúc mạnh mẽ. Hệ thống cung cấp trải nghiệm học tập và giảng dạy tối ưu, kết hợp với hệ thống thi trắc nghiệm và giám sát thi chuyên nghiệp.

> **Lưu ý**: Đây là dự án Đồ án tốt nghiệp (Học viện Công nghệ Bưu chính Viễn thông - PTIT).

---

## 🌟 Tính năng nổi bật

### 👨‍🎓 Dành cho Học viên (Students)
- **Khám phá Khóa học**: Giao diện danh mục khóa học trực quan, tìm kiếm và lọc khóa học dễ dàng.
- **Trang Chi tiết Khóa học**: Cung cấp đầy đủ thông tin giảng viên, lộ trình học, video học thử miễn phí.
- **Không gian Học tập Tập trung**: Giao diện học tập (video, tài liệu) không phân tâm.
- **Hệ thống Thi & Kiểm tra**: Làm bài thi với bộ đếm ngược thời gian thực, lưu bài tự động (auto-save), xem lại kết quả chi tiết sau khi nộp.
- **Hỗ trợ Đa dạng Câu hỏi**: Trắc nghiệm 1 đáp án, nhiều đáp án, Đúng/Sai, Điền từ, Ghép nối, Câu hỏi số học, và Tự luận.

### 👨‍🏫 Dành cho Giảng viên (Instructors)
- **Quản lý Khóa học**: Khởi tạo và xây dựng nội dung khóa học (Chương, Bài học) trực tiếp qua giao diện Builder.
- **Quản lý Học viên**: Theo dõi tiến độ học tập và kết quả bài kiểm tra của học viên.
- **Quản trị Ngân hàng Câu hỏi**: Xây dựng kho câu hỏi phong phú phân loại theo độ khó, gắn thẻ (tags) và nhóm câu hỏi.

### 🛡️ Dành cho Quản trị viên (Administrators)
- **Kiểm duyệt Khóa học**: Hệ thống workflow xét duyệt khóa học trước khi xuất bản.
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

## 🚀 Hướng dẫn cài đặt (Local Development)

Yêu cầu môi trường: Có cài đặt sẵn **Docker** và **Docker Compose**.

### Bước 1: Clone kho lưu trữ
```bash
git clone https://github.com/your-username/edupress-lms.git
cd edupress-lms
```

### Bước 2: Cấu hình biến môi trường
**Backend:**
```bash
cd backend
cp .env.example .env
# Chỉnh sửa file .env với thông tin database phù hợp (ví dụ: host là mysql_db nếu dùng docker)
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
