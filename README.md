<div align="center">

<img src="https://img.shields.io/badge/version-1.0.0-blue?style=for-the-badge" alt="Version"/>
<img src="https://img.shields.io/badge/license-Academic-green?style=for-the-badge" alt="License"/>
<img src="https://img.shields.io/badge/Laravel-11-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel"/>
<img src="https://img.shields.io/badge/Nuxt.js-3-00DC82?style=for-the-badge&logo=nuxt.js&logoColor=white" alt="Nuxt"/>
<img src="https://img.shields.io/badge/Docker-ready-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker"/>

# 🎓 ERIPT LMS

**Nền tảng Quản lý Học tập Trực tuyến Toàn diện**

*Tích hợp thanh toán · Giám sát thi thời gian thực · Dịch vụ AI mở rộng*

[Hướng dẫn cài đặt](#-hướng-dẫn-cài-đặt) · [Tính năng](#-tính-năng) · [Công nghệ](#️-công-nghệ-sử-dụng) · [Screenshots](#-screenshots)

</div>

---

## 📖 Giới thiệu

**ERIPT LMS** là nền tảng quản lý học tập (Learning Management System) trực tuyến toàn diện được thiết kế với giao diện hiện đại theo chuẩn **Editorial** và kiến trúc mạnh mẽ. Hệ thống cung cấp trải nghiệm học tập và giảng dạy tối ưu, tích hợp mua khóa học và thanh toán trực tiếp, hệ thống thi trắc nghiệm chuyên nghiệp cùng giám sát thi theo thời gian thực và các dịch vụ AI nâng cao.

---

## ✨ Tính năng

### 👨‍🎓 Dành cho Học viên

| Tính năng | Mô tả |
|---|---|
| 🔍 Khám phá Khóa học | Giao diện danh mục trực quan, tìm kiếm và lọc khóa học dễ dàng |
| 📄 Chi tiết Khóa học | Thông tin giảng viên đầy đủ, lộ trình học, video học thử miễn phí |
| 💳 Mua & Thanh toán | Mua khóa học, thanh toán trực tuyến và quản lý đơn hàng tích hợp |
| 🎯 Không gian Học tập | Giao diện video/tài liệu tập trung, không phân tâm |
| ⏱️ Hệ thống Thi & Kiểm tra | Bộ đếm ngược thời gian thực, auto-save, xem lại kết quả chi tiết |

**Các dạng câu hỏi được hỗ trợ:**
- Trắc nghiệm 1 đáp án / Nhiều đáp án
- Đúng / Sai · Điền từ · Ghép nối
- Câu hỏi số học · Tự luận

---

### 👨‍🏫 Dành cho Giảng viên

- **📦 Quản lý Khóa học**: Khởi tạo và xây dựng nội dung (Chương, Bài học) trực tiếp qua giao diện Course Builder.
- **👥 Quản lý Học viên**: Theo dõi tiến độ học tập và kết quả bài kiểm tra.
- **🗂️ Ngân hàng Câu hỏi**: Xây dựng kho câu hỏi phong phú, phân loại theo độ khó, gắn thẻ (tags) và nhóm câu hỏi.

---

### 🛡️ Dành cho Quản trị viên

- **✅ Kiểm duyệt Khóa học**: Workflow xét duyệt trước khi xuất bản.
- **💰 Quản lý Thanh toán & Đơn hàng**: Theo dõi giao dịch, lịch sử thanh toán và quản lý đơn hàng học viên.

**🎯 Quản lý Kỳ thi Chuyên nghiệp:**
- Hỗ trợ **Bài thi trong khóa học** và **Kỳ thi độc lập** (Standalone Exams)
- Cấu hình linh hoạt: xáo trộn câu hỏi/đáp án, giới hạn số lần thi
- Tùy chỉnh ẩn/hiện kết quả theo chuẩn **Moodle Review Options**

**🔴 Giám sát Thi Trực tiếp (Live Proctoring):**
- Bảng điều khiển giám sát **thời gian thực** (Real-time Monitor Dashboard)
- Quyền can thiệp: **Tạm dừng** · **Cho phép tiếp tục** · **Đình chỉ thi** · **Gia hạn thời gian**
- Ghi nhận vi phạm tự động (tab-switching, v.v.)

---

## 🛠️ Công nghệ sử dụng

<table>
<tr>
<td valign="top" width="33%">

### ⚙️ Backend
- **[Laravel 11](https://laravel.com/)** (PHP)
- **MySQL** — Cơ sở dữ liệu
- **Redis** — Caching & Queue
- **JWT** — Authentication

</td>
<td valign="top" width="33%">

### 🖥️ Frontend
- **[Nuxt.js 3](https://nuxt.com/)** (Vue.js 3)
- **Tailwind CSS** — Styling
- **Pinia** — State Management
- **TypeScript**

</td>
<td valign="top" width="33%">

### 🏗️ Hạ tầng
- **Docker & Docker Compose**
- Môi trường phát triển cục bộ containerized

</td>
</tr>
</table>

---

## 💳 Thanh toán & Giao dịch

- Hỗ trợ mua khóa học trực tuyến, quản lý giỏ hàng và đơn hàng
- Quản lý giao dịch, lịch sử thanh toán chi tiết cho học viên
- Dễ dàng tích hợp cổng thanh toán qua biến môi trường:

```bash
PAYMENT_GATEWAY=vnpay        # VNPay | Stripe | PayPal
PAYMENT_SECRET=your_secret
PAYMENT_CALLBACK_URL=https://yourdomain.com/payment/callback
```

---

## 🧠 Dịch vụ AI mở rộng

| Dịch vụ | Mô tả |
|---|---|
| 🤖 **AI Chatbot** | Trợ lý ảo gợi ý khóa học, điều hướng chức năng, trả lời thắc mắc |
| 💼 **AI Career Advisor** | Phân tích CV, trích xuất kỹ năng và đề xuất lộ trình nghề nghiệp |
| ✍️ **AI Content Generator** | Đề xuất tiêu đề khóa học, mô tả bài học, quiz và đề thi mẫu |
| 📊 **Exam Analytics** | Phân tích kết quả thi, phát hiện xu hướng sai và đề xuất cải thiện |
| 🎓 **Smart Tutoring** | Gợi ý học tập cá nhân hóa dựa trên tiến độ và hành vi học viên |

---

## 🚀 Hướng dẫn cài đặt

> **Yêu cầu**: Cài đặt sẵn [Docker](https://www.docker.com/) và [Docker Compose](https://docs.docker.com/compose/).

### Bước 1 — Clone kho lưu trữ

```bash
git clone https://github.com/huykunptit/Doantotnghiep.git
cd Doantotnghiep
```

### Bước 2 — Cấu hình biến môi trường

**Backend:**
```bash
cd backend
cp .env.example .env
# Chỉnh sửa thông tin database (host là mysql_db nếu dùng Docker)
# Thêm cấu hình thanh toán nếu cần
```

**Frontend:**
```bash
cd ../frontend
cp .env.example .env
# Đảm bảo NUXT_PUBLIC_API_BASE trỏ đúng URL của backend API
```

### Bước 3 — Khởi chạy bằng Docker

```bash
# Tại thư mục gốc (nơi chứa docker-compose.yml)
docker-compose up -d --build
```

### Bước 4 — Khởi tạo Database

```bash
docker exec -it lms_backend php artisan migrate:fresh --seed
```

### Bước 5 — Truy cập ứng dụng

| Dịch vụ | URL |
|---|---|
| 🌐 Frontend (Người dùng / Admin) | http://localhost:3000 |
| ⚙️ Backend API | http://localhost:8000 |

---

## 📸 Screenshots

> *Thêm link ảnh hoặc upload ảnh vào thư mục `docs/images/` và cập nhật bên dưới.*

| Trang chủ | Chi tiết Khóa học |
|:-:|:-:|
| ![Trang chủ](docs/images/homepage.png) | ![Chi tiết khóa học](docs/images/course-detail.png) |

| Giao diện Làm bài Thi | Bảng Giám sát Thi (Admin) |
|:-:|:-:|
| ![Làm bài thi](docs/images/exam.png) | ![Giám sát thi](docs/images/proctoring.png) |

---

## 📜 Giấy phép

Dự án được xây dựng phục vụ mục đích **học thuật** — Đồ án Tốt nghiệp.

---

<div align="center">

Made with ❤️ · ERIPT LMS © 2025

</div>
