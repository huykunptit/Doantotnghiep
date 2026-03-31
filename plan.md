# 📚 LMS Platform — Project Plan

> **Mục tiêu:** Hoàn thành đồ án trước 31/07, đủ điều kiện apply vị trí PHP Laravel & Vue.js Developer
> **Stack:** Laravel 10 · Nuxt.js 3 · Flutter · FastAPI · MySQL · MongoDB · Redis · Docker · GitHub Actions

---

## 🏗️ Kiến trúc hệ thống

```
[ Nuxt.js 3 (SSR) ]     [ Flutter Mobile ]
         ↓                      ↓
           REST API (Laravel 10)
                   ↓
┌─────────────────────────────────────┐
│           Laravel Backend           │
├─────────────────────────────────────┤
│ Auth / User / SSO (Google OAuth)    │
│ Course / Lesson                     │
│ Marketplace (Purchase / VNPay)      │
│ Learning / Progress                 │
│ Quiz                                │
│ AI Integration (HTTP → FastAPI)     │
└─────────────────────────────────────┘
         ↓           ↓          ↓
      MySQL       MongoDB      Redis
         ↓
   MinIO (S3-compatible)
         ↓
   FastAPI (AI Chatbot Microservice)
```

---

## 📁 Cấu trúc repo

```
lms-project/
├── backend/              ← Laravel 10
├── frontend/             ← Nuxt.js 3
├── mobile/               ← Flutter
├── ai-service/           ← FastAPI
├── .github/
│   └── workflows/
│       └── deploy.yml    ← CI/CD
└── docker-compose.yml
```

---

## 🗃️ Database Schema

```sql
-- Core
users           (id, name, email, password, role, avatar, google_id)
courses         (id, user_id, title, description, price, status, thumbnail, category)
lessons         (id, course_id, title, video_url, order, duration)

-- Commerce
enrollments     (id, user_id, course_id, enrolled_at)
orders          (id, user_id, course_id, amount, status, payment_ref)

-- Learning
lesson_progress (id, user_id, lesson_id, completed, watched_seconds, last_watched_at)
certificates    (id, user_id, course_id, issued_at, cert_url)

-- Quiz
quizzes         (id, lesson_id, title)
questions       (id, quiz_id, content)
answers         (id, question_id, content, is_correct)
quiz_attempts   (id, user_id, quiz_id, score, completed_at)

-- MongoDB (chat history — NoSQL)
chat_messages   { user_id, course_id, role, content, created_at }
```

---

## 📅 Timeline (17 tuần: tháng 4 → tháng 7)

### Phase 1 — Nền tảng · Tuần 1–2 (01/04 – 14/04)

**Backend**
- [ ] Khởi tạo Laravel 10, cấu hình `.env`, kết nối MySQL
- [ ] Cài `spatie/laravel-permission` — roles: `admin`, `instructor`, `student`
- [ ] Viết migrations: `users`, `courses`, `lessons`, `enrollments`, `orders`, `lesson_progress`
- [ ] Seeder + Factory để test data
- [ ] Cấu hình Laravel Storage + MinIO (Docker)
- [ ] Cài Redis, cấu hình Queue + Horizon

**Frontend (Nuxt.js 3)** ⚠️ *JD requirement*
- [ ] Khởi tạo Nuxt 3 với TypeScript, Pinia, `@nuxtjs/axios`
- [ ] Tạo `api.ts` base instance (baseURL, interceptor token)
- [ ] Layout: `AuthLayout`, `MainLayout`, `DashboardLayout`
- [ ] Route middleware guard (check auth)

**DevOps**
- [ ] `docker-compose.yml`: mysql, redis, minio, laravel, nuxt-dev, fastapi, mongodb

---

### Phase 2 — Auth & SSO · Tuần 2–3 (14/04 – 28/04)

⚠️ *SSO là JD requirement — ưu tiên cao*

**API Endpoints**
```
POST /api/auth/register
POST /api/auth/login
POST /api/auth/logout
GET  /api/auth/google          ← SSO redirect
GET  /api/auth/google/callback ← SSO callback
GET  /api/me
PUT  /api/me                   ← update profile
```

**Tasks**
- [ ] Cài `laravel/socialite` + Google OAuth2
- [ ] Cấu hình Google Cloud Console, thêm credentials vào `.env`
- [ ] Xử lý `google_id` trong bảng `users`
- [ ] Frontend: trang Login/Register, nút "Đăng nhập Google"
- [ ] Pinia store `useAuthStore`, persist token vào cookie

---

### Phase 3 — Course & Lesson · Tuần 3–4 (28/04 – 12/05)

**Models & Relations**
```
Course → hasMany Lesson
Course → belongsTo User (instructor)
Lesson → hasOne Video (presigned MinIO URL)
```

**API Endpoints**
```
GET    /api/courses                 ← list, filter category/price/instructor
POST   /api/courses                 ← instructor tạo khóa học
PUT    /api/courses/{id}
DELETE /api/courses/{id}
GET    /api/courses/{id}/lessons
POST   /api/courses/{id}/lessons    ← thêm bài học
POST   /api/lessons/{id}/upload     ← upload video → MinIO (queue job)
GET    /api/lessons/{id}/stream-url ← presigned URL (1h)
```

**Upload flow**
```
Frontend → POST multipart → Laravel → dispatch Job → MinIO
                                                  ↓
                                        trả về presigned URL (1h)
```

**Tasks**
- [ ] CRUD Course với policy (chỉ instructor của course mới sửa được)
- [ ] Upload video qua queue job (không xử lý sync)
- [ ] Presigned URL cho video (bảo vệ nội dung trả phí)
- [ ] Frontend: trang Course List, Course Detail, trang tạo/sửa course (instructor)

---

### Phase 4 — Marketplace & Payment · Tuần 4–5 (12/05 – 26/05)

**Logic**
```
Student click mua → tạo Order (pending)
    → Redirect VNPay
    → Webhook callback → Order = paid
    → Tạo Enrollment
    → Kiểm tra Enrollment trước khi xem lesson
```

**Tasks**
- [ ] Tích hợp VNPay (hoặc Stripe)
- [ ] Webhook handler + verify signature
- [ ] Trang "Khóa học của tôi" — danh sách enrolled
- [ ] Gate/Policy: chỉ student đã enrolled mới xem được lesson
- [ ] Frontend: trang checkout, redirect sau thanh toán, trang order history

---

### Phase 5 — Learning & Progress · Tuần 5–6 (26/05 – 09/06)

**Tasks**
- [ ] Track tiến độ: `lesson_progress` (user_id, lesson_id, completed, watched_seconds)
- [ ] `GET /api/courses/{id}/progress` → trả % hoàn thành
- [ ] Quiz CRUD + `quiz_attempts`
- [ ] Tự động cấp `certificate` khi hoàn thành 100%
- [ ] Frontend: progress bar trong course player, trang quiz

---

### Phase 6 — AI Chatbot Microservice · Tuần 6–7 (09/06 – 23/06)

⚠️ *AI integration là JD requirement*

**FastAPI Service**
```python
POST /chat
body: {
  "message": str,
  "course_id": int,
  "history": [{"role": "user"|"assistant", "content": str}]
}
```

**Flow**
```
Student gửi câu hỏi
    ↓
Laravel POST /api/chat → HTTP call → FastAPI
                                        ↓
                              Load context khóa học (title, lessons outline)
                                        ↓
                              Gọi OpenAI API với system prompt:
                              "Bạn là trợ lý khóa học {course_name}.
                               Nội dung: {course_outline}.
                               Chỉ trả lời câu hỏi liên quan."
                                        ↓
                              Lưu history vào MongoDB
                                        ↓
                              Trả về answer → Laravel → Frontend
```

**Tasks**
- [ ] FastAPI project setup, cài `openai`, `uvicorn`, `pydantic`
- [ ] Endpoint `POST /chat` với context injection
- [ ] Lưu chat history vào **MongoDB** (document-based phù hợp hơn MySQL)
- [ ] Laravel: `POST /api/chat` proxy sang FastAPI, lấy history từ MongoDB
- [ ] Frontend: màn hình chat nổi (floating button trong course player)

---

### Phase 7 — Flutter Mobile · Tuần 7–8 (23/06 – 07/07)

**Màn hình chính**
- [ ] Splash / Onboarding
- [ ] Login / Register + Google SSO
- [ ] Trang chủ + danh sách khóa học
- [ ] Chi tiết khóa học + nút mua
- [ ] Video player (`video_player` package + presigned URL)
- [ ] AI Chatbot screen (chat UI đơn giản)
- [ ] Tiến độ học

**Packages**
```yaml
dependencies:
  dio: ^5.0.0
  flutter_riverpod: ^2.0.0
  video_player: ^2.0.0
  cached_network_image: ^3.0.0
  flutter_secure_storage: ^9.0.0
  google_sign_in: ^6.0.0
```

**Lưu ý:** Dùng chung API với web, thêm `Accept: application/json` header, lưu token qua `flutter_secure_storage`.

---

### Phase 8 — CI/CD + Admin + Hoàn thiện · Tuần 8–9 (07/07 – 21/07)

⚠️ *CI/CD là JD requirement*

**GitHub Actions CI/CD**
```yaml
# .github/workflows/deploy.yml
on: [push to main]
jobs:
  test:
    - run: php artisan test
  deploy:
    - ssh vào VPS
    - git pull + composer install + npm build + php artisan migrate
```

**Admin Panel**
- [ ] Dashboard: thống kê doanh thu, số học viên, khóa học hot
- [ ] Quản lý user, course, order
- [ ] Duyệt khóa học của instructor

**Polish**
- [ ] Viết README.md đầy đủ (setup, architecture diagram, demo GIF)
- [ ] Deploy lên VPS thật với domain
- [ ] Quay video demo 3–5 phút

---

### Tuần 9–10 — Buffer + Deploy (21/07 – 31/07)

- [ ] Fix bugs, optimize performance
- [ ] Deploy production với Docker Compose trên VPS
- [ ] Setup domain + SSL (Let's Encrypt)
- [ ] Cập nhật CV với link demo + GitHub repo

---

## 📚 Lộ trình học song song

### Tháng 4 — Nuxt.js 3 (ưu tiên số 1)
| Tuần | Nội dung |
|------|----------|
| Tuần 1 | Vue 3 Composition API: `ref`, `computed`, `watch`, `<script setup>` |
| Tuần 2 | Pinia state management, Vue Router (dynamic routes, guards, lazy load) |
| Tuần 3–4 | **Nuxt.js 3**: SSR, file-based routing, `useFetch`, `useAsyncData`, middleware |

> 💡 Học xong là tích hợp ngay vào đồ án — vừa học vừa có sản phẩm demo.

### Tháng 5 — SSO + CI/CD + NoSQL
| Chủ đề | Cách thực hành |
|--------|----------------|
| SSO / OAuth2 | `laravel/socialite` + Google OAuth ngay trong đồ án |
| GitHub Actions | Pipeline test + deploy cho repo đồ án |
| Redis nâng cao | Cache khóa học, queue upload video, session |
| MongoDB | Lưu chat history AI chatbot |

### Tháng 6–7 — Hoàn thiện + Polish CV
- Finish đồ án
- Viết README chuẩn, quay demo
- Deploy lên domain thật
- Cập nhật CV

---

## ✅ MVP Checklist (nếu bị ép thời gian)

**Bắt buộc (MVP)**
- [ ] Setup infrastructure (Docker, DB, Storage)
- [ ] Auth + Google SSO
- [ ] Course CRUD
- [ ] Purchase flow (VNPay)
- [ ] Video player (presigned URL)
- [ ] AI chatbot cơ bản

**Nên có**
- [ ] Progress tracking
- [ ] Quiz
- [ ] Admin dashboard
- [ ] Flutter mobile app
- [ ] GitHub Actions CI/CD

**Tùy chọn**
- [ ] Certificate tự động
- [ ] Review / Rating
- [ ] Push notification
- [ ] Live stream

---

## ⚙️ Lưu ý kỹ thuật quan trọng

| Vấn đề | Giải pháp |
|--------|-----------|
| Video streaming | Không serve qua Laravel. Dùng **presigned URL MinIO** (TTL 1h) |
| AI context | Gửi `course_id` mỗi lần chat, FastAPI load outline khóa học vào system prompt |
| Queue | Upload video + xử lý thanh toán đều qua **Redis Queue + Horizon** |
| Mobile auth | Token lưu qua `flutter_secure_storage`, không dùng SharedPreferences |
| Chat history | Lưu vào **MongoDB** (document-based, không cần schema cứng) |
| SSO callback | Redirect về Nuxt sau khi Laravel xử lý xong Google callback |

---

## 🎯 Gap Analysis — JD vs Hiện tại

| JD Yêu cầu | Trạng thái | Kế hoạch |
|------------|-----------|----------|
| Laravel PHP | ✅ Có | Đang làm trong đồ án |
| Vue.js + Vuex + Router | 🟡 Cần củng cố | Tháng 4, tuần 1–2 |
| **Nuxt.js** | ❌ Chưa có | Tháng 4, tuần 3–4 — **ưu tiên số 1** |
| **SSO / OAuth2** | ❌ Chưa có | Tháng 5, Phase 2 đồ án |
| **CI/CD** | ❌ Chưa có | Tháng 5, GitHub Actions |
| NoSQL (MongoDB) | 🟡 Cơ bản | Dùng cho chat history |
| Git | ✅ Có | — |
| Docker | ✅ Có | Docker Compose trong đồ án |
| AI domain | 🟡 Có Python/ML | FastAPI chatbot trong đồ án |
| Marketplace/Payment | 🟡 Có nền | VNPay integration Phase 4 |

---

## 💬 Câu trả lời phỏng vấn mục tiêu (tháng 8)

> *"Tôi đã xây dựng hệ thống LMS từ đầu với Laravel 10 + Nuxt.js 3 (SSR), tích hợp thanh toán VNPay, AI chatbot qua FastAPI microservice, deploy bằng Docker + GitHub Actions CI/CD. Ngoài ra tôi có 2+ năm vận hành hệ thống LMS Moodle thực tế cho doanh nghiệp và tổ chức quốc tế."*
