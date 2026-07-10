# Sylva LMS 🎓

Sylva LMS là nền tảng quản lý học tập (Learning Management System) trực tuyến toàn diện với trải nghiệm mua khóa học và thanh toán tích hợp. Hệ thống được thiết kế với giao diện hiện đại (chuẩn Editorial) và kiến trúc mạnh mẽ, cung cấp trải nghiệm học tập và giảng dạy tối ưu, kết hợp cùng hệ thống thi trắc nghiệm, giám sát thi chuyên nghiệp và dịch vụ AI mở rộng.

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
- [Laravel 13](https://laravel.com/) (PHP 8.3)
- MySQL 8.0 (Database chính)
- MongoDB 7 (Chat history, logs)
- Redis 7 (Caching & Queue)
- Laravel Sanctum (Authentication)

**Frontend:**
- [Nuxt.js 4](https://nuxt.com/) (Vue.js 3)
- TypeScript
- Pinia (State Management)

**AI Service:**
- FastAPI (Python)
- OpenAI / Gemini / OpenRouter API
- ChromaDB + multilingual-e5-base (RAG Pipeline — planned)

**Hạ tầng:**
- Docker & Docker Compose
- Nginx (Reverse proxy)
- MinIO (Object storage — video, file)
- n8n (Workflow automation)
- phpMyAdmin (Database management)

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

## 🚀 Hướng dẫn cài đặt (Local Development)

Yêu cầu: **Docker** và **Docker Compose** đã được cài đặt.

### Bước 1: Clone repository
```bash
git clone https://github.com/huykunptit/Doantotnghiep.git
cd Doantotnghiep
```

### Bước 2: Build & khởi chạy toàn bộ stack
```bash
sudo docker compose build --no-cache
sudo docker compose up -d
```

> File `.env` của backend được tự động tạo từ `.env.example` khi container khởi động.

### Bước 3: Truy cập ứng dụng

---

## 📱 Hướng dẫn chạy ứng dụng Mobile (Flutter)

Dự án bao gồm ứng dụng di động được xây dựng bằng **Flutter**, nằm trong thư mục `mobile`.

### 1. Yêu cầu hệ thống:
- Đã cài đặt [Flutter SDK](https://docs.flutter.dev/get-started/install).
- Máy ảo (Android Emulator / iOS Simulator) hoặc thiết bị thật đã kết nối.
- Các dịch vụ Backend & Database đang chạy bằng Docker.

### 2. Cấu hình kết nối API:
Theo mặc định, mã nguồn trỏ đến `http://10.0.2.2:8000/api` (địa chỉ mặc định của Android Emulator gọi về host). 
Tuy nhiên, để chạy chính xác theo cấu hình hệ thống hiện tại hoặc trên thiết bị thật, bạn cần cấu hình lại IP Backend bằng cách tạo file `.env` trong thư mục `mobile`:

1. Tạo file mới `mobile/.env`.
2. Khai báo biến `API_URL` cho phù hợp với môi trường của bạn:
   - **Sử dụng Ngrok (Khuyên dùng và Dễ nhất)**: Chạy script `./run_ngrok.sh` ở thư mục gốc dự án, copy đường dẫn ngrok frontend và thiết lập:
     `API_URL=https://<your-ngrok-url>/api`
   - **Android Emulator**: `API_URL=http://10.0.2.2/api` (kết nối qua cổng 80 của Nginx)
   - **iOS Simulator**: `API_URL=http://localhost/api`
   - **Thiết bị thật (chung mạng Wi-Fi)**: `API_URL=http://<IP_LAN_MÁY_TÍNH_CỦA_BẠN>/api` (Ví dụ: `http://192.168.1.15/api`)

### 3. Chạy ứng dụng:
Mở terminal, di chuyển vào thư mục `mobile` và chạy các lệnh sau:
```bash
cd mobile
flutter pub get
flutter run
```

### ⚠️ Các lỗi thường gặp và Lưu ý:
- **Lỗi Network Error / Không thể gọi API**: Hãy chắc chắn `API_URL` trong file `.env` được cấu hình đúng. Lưu ý các dấu `/` ở cuối URL (không nên thừa).
- **Khi kết nối bằng IP LAN**: Máy tính chạy Backend và điện thoại phải kết nối **cùng một mạng Wi-Fi**, và máy tính không bị tường lửa (Firewall) chặn cổng 80.
- Nếu gặp lỗi bộ nhớ hoặc cache trong Flutter, hãy thử chạy lệnh `flutter clean` trước khi `flutter pub get` lại.

---

## 🌐 Danh sách Services & Cổng truy cập

| Service | Container | Cổng host | Truy cập | Ghi chú |
|---|---|---|---|---|
| **Nginx** (Entry point) | `lms_nginx` | `80` | http://localhost | Proxy toàn bộ traffic |
| **Frontend** (Nuxt) | `lms_frontend` | _(internal)_ | http://localhost/ | Qua nginx |
| **Backend** (Laravel) | `lms_backend` | _(internal)_ | http://localhost/api | Qua nginx |
| **AI Service** (FastAPI) | `lms_ai_service` | `8001` | http://localhost:8001 | REST API trực tiếp |
| **phpMyAdmin** | `lms_phpmyadmin` | `8083` | http://localhost:8083 | Quản lý MySQL |
| **n8n** (Automation) | `lms_n8n` | `5678` | http://localhost:5678 | Workflow automation |
| **MinIO Console** | `lms_minio` | `9001` | http://localhost:9001 | Quản lý object storage |
| **MinIO API** | `lms_minio` | `9000` | http://localhost:9000 | S3-compatible API |
| **MySQL** | `lms_mysql` | `3308` | `localhost:3308` | Kết nối qua client |
| **Redis** | `lms_redis` | `6379` | `localhost:6379` | Cache & Queue |
| **MongoDB** | `lms_mongodb` | `27017` | `localhost:27017` | Chat history |

### 🔑 Thông tin đăng nhập mặc định

| Service | Username | Password |
|---|---|---|
| **phpMyAdmin / MySQL** | `root` | `root` |
| **MySQL** (app user) | `lms_user` | `lms_password` |
| **MinIO** | `minioadmin` | `minioadmin123` |
| **n8n** | `admin` | `admin123` |

### 👤 Tài khoản mẫu (Seeder)

Tất cả tài khoản dùng mật khẩu chung: **`password`**

| Vai trò | Email | Số lượng |
|---|---|---|
| **Admin** | `admin@lms.com` | 1 |
| **Giảng viên** | `instructor1@lms.com` → `instructor8@lms.com` | 8 |
| **Sinh viên** | `student1@lms.com` → `student18@lms.com` | 18 |

**Dữ liệu mẫu được tạo kèm:**
- Danh mục khóa học, khóa học core (miễn phí, gắn chương trình đào tạo) và extension (có phí, marketplace)
- Đơn hàng, ghi danh, đánh giá, tiến độ học, kết quả quiz cho sinh viên
- Cấu trúc tổ chức học vụ (khoa, ngành, chương trình đào tạo, lớp, học kỳ)
- Ngân hàng câu hỏi, CV sinh viên, gợi ý nghề nghiệp AI

---

### 🤖 Cấu hình AI Provider

Vào **Admin → Quản lý AI** để cấu hình provider và API key:

| Provider | API Key lấy ở đâu |
|---|---|
| OpenAI (ChatGPT) | https://platform.openai.com/api-keys |
| Google Gemini | https://aistudio.google.com/app/apikey |
| OpenRouter (free models) | https://openrouter.ai/keys |

---

## 🐳 Lệnh Docker thường dùng

```bash
# Khởi động toàn bộ
sudo docker compose up -d

# Build lại không cache
sudo docker compose build --no-cache && sudo docker compose up -d

# Xem log backend
sudo docker compose logs backend -f

# Restart một service
sudo docker compose restart backend

# Dừng toàn bộ (giữ volume)
sudo docker compose down

# Dừng và xóa sạch volume (reset database)
sudo docker compose down -v
```

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
