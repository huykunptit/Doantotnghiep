# ERIPT LMS

**ERIPT LMS** là hệ thống quản lý học tập (Learning Management System) toàn diện, định hướng môi trường đại học Việt Nam — tích hợp học tập trực tuyến, quản lý học vụ (CTĐT, thời khóa biểu, điểm danh), khảo thí có giám sát, marketplace khóa học, và dịch vụ AI hỗ trợ hướng nghiệp.

> Đồ án tốt nghiệp — Học viện Công nghệ Bưu chính Viễn thông (**PTIT**).  
> Domain demo: **https://eript-lms.io.vn** (Cloudflare Tunnel)

---

## Tính năng chính (cập nhật đến hiện tại)

### Sinh viên
- **Cổng học viên**: dashboard, khóa học của tôi, không gian học tập (video / tài liệu / SCORM).
- **Học vụ PTIT-style**: chương trình đào tạo (CTĐT), thời khóa biểu theo tuần, bảng điểm / GPA có trọng số tín chỉ & xếp loại, lịch sử học phí, phòng thi.
- **Thẻ sinh viên**: hồ sơ / ID card theo phong cách PTIT.
- **Thi & kiểm tra**: làm bài có đếm ngược, auto-save; xác thực khuôn mặt trước khi vào bài thi có giám sát; xem kết quả chi tiết.
- **Điểm danh buổi offline**: check-in GPS theo bán kính + QR (manual / rotating / static).
- **Marketplace**: khám phá khóa học nổi bật, lộ trình nghề (career paths), thanh toán online (PayOS), đơn hàng & chứng chỉ.
- **Gamification**: điểm thưởng, bảng xếp hạng, chứng chỉ số.
- **AI hỗ trợ**: Study Advisor (gợi ý CTĐT / cảnh báo điểm thấp), Career Advisor (phân tích CV & lộ trình nghề), chatbot (kể cả guest).
- **Chatbot RAG giáo trình**: sinh viên hỏi đáp kiến thức theo giáo trình PTIT đã index (ChromaDB). Chat ngoài khóa tìm trên mọi giáo trình; trong trang học khóa chỉ lấy giáo trình đúng môn. Hiển thị nguồn PDF khi dùng RAG.

### Giảng viên
- **Course Builder**: chương / bài học đa loại (video, file, trang, SCORM, quiz, assignment, forum, survey, buổi offline).
- Quản lý học viên theo khóa, doanh thu khóa học, quản lý đề thi.
- Ngân hàng câu hỏi & cấu hình quiz linh hoạt.

### Quản trị viên
- **Người dùng & RBAC**: quản lý user, vai trò / phân quyền (Spatie), import hàng loạt (kèm ảnh khuôn mặt cho giám sát thi).
- **Học vụ (L&D)**: lịch học vụ, danh mục học thuật, lớp hành chính, lịch học, ghi danh theo lộ trình, điểm danh.
- **Khóa học**: duyệt / quản lý khóa học, danh mục, đánh giá, chứng chỉ, career paths.
- **Khảo thí**: quiz, ngân hàng câu hỏi, theo dõi kỳ thi, **live proctoring** (pause / resume / force stop / gia hạn thời gian).
- **Báo cáo**: khóa học, thi, tiến độ, hoạt động, thanh toán.
- **Hệ thống**: thông báo, tin tức, cấu hình AI, chat, branding (logo / favicon / site name).

### Mobile (Flutter)
- Ứng dụng di động kết nối cùng Backend API (xem hướng dẫn bên dưới).

---

## Công nghệ

| Lớp | Stack |
|---|---|
| **Backend** | Laravel 13 (PHP 8.3), Sanctum, Spatie Permission, PayOS |
| **Frontend** | Nuxt 4, Vue 3, TypeScript, Pinia, PrimeVue 4, i18n |
| **AI Service** | FastAPI (Python), OpenAI / Gemini / OpenRouter / Claude / Ollama, **RAG** (ChromaDB + giáo trình PTIT) |
| **DB & cache** | MySQL 8, MongoDB 7, Redis 7 |
| **Storage** | MinIO (S3-compatible) |
| **Hạ tầng** | Docker Compose, Nginx, Cloudflare Tunnel, n8n, phpMyAdmin |
| **Mobile** | Flutter |

---

## Hướng dẫn cài đặt (Local)

Yêu cầu: **Docker** và **Docker Compose**.

### 1. Clone
```bash
git clone https://github.com/huykunptit/Doantotnghiep.git
cd Doantotnghiep
```

### 2. Build & chạy stack
```bash
docker compose build --no-cache
docker compose up -d
```

> Backend `.env` được tạo từ `.env.example` khi container khởi động (nếu chưa có).

### 3. Public domain Cloudflare (`eript-lms.io.vn`)

Đã gắn **Cloudflare Tunnel** (profile `public`) — không cần mở port router.

1. Tạo tunnel trên Cloudflare Zero Trust → Public Hostname `eript-lms.io.vn` → `http://nginx:80`
2. Dán token vào `.env` gốc: `CLOUDFLARE_TUNNEL_TOKEN=...`
3. Chạy:
   ```powershell
   .\scripts\start-public.ps1
   ```
   hoặc `docker compose --profile public up -d`

Chi tiết: [`docs/cloudflare-tunnel.md`](docs/cloudflare-tunnel.md) → **https://eript-lms.io.vn**

### 4. Truy cập local

| Service | URL |
|---|---|
| Ứng dụng (Nginx) | http://localhost |
| API | http://localhost/api |
| AI Service | http://localhost:8001 |
| phpMyAdmin | http://localhost:8083 |
| n8n | http://localhost:5678 |
| MinIO Console | http://localhost:9001 |

---

## Ứng dụng Mobile (Flutter)

Thư mục: `mobile/`

1. Cài [Flutter SDK](https://docs.flutter.dev/get-started/install); Backend Docker đang chạy.
2. Tạo `mobile/.env` với `API_URL`:
   - **Ngrok** (khuyên dùng): chạy `./run_ngrok.sh`, rồi `API_URL=https://<ngrok-url>/api`
   - **Android Emulator**: `API_URL=http://10.0.2.2/api`
   - **iOS Simulator**: `API_URL=http://localhost/api`
   - **Máy thật (cùng Wi-Fi)**: `API_URL=http://<IP_LAN>/api`
3. Chạy:
   ```bash
   cd mobile
   flutter pub get
   flutter run
   ```

---

## Services & cổng

| Service | Container | Cổng host | Ghi chú |
|---|---|---|---|
| **Nginx** | `lms_nginx` | `80` | Entry point · https://eript-lms.io.vn |
| **Cloudflare Tunnel** | `lms_cloudflared` | — | Profile `public` |
| **Frontend** (Nuxt) | `lms_frontend` | internal | http://localhost/ |
| **Backend** (Laravel) | `lms_backend` | internal | http://localhost/api |
| **AI Service** | `lms_ai_service` | `8001` | FastAPI |
| **phpMyAdmin** | `lms_phpmyadmin` | `8083` | MySQL UI |
| **n8n** | `lms_n8n` | `5678` | Automation |
| **MinIO** | `lms_minio` | `9000` / `9001` | API / Console |
| **MySQL** | `lms_mysql` | `3306` | DB chính |
| **Redis** | `lms_redis` | `6379` | Cache & queue |
| **MongoDB** | `lms_mongodb` | `27017` | Chat / logs |

### Đăng nhập hạ tầng

| Service | Username | Password |
|---|---|---|
| phpMyAdmin / MySQL root | `root` | `root` |
| MySQL (app) | `lms_user` | `lms_password` |
| MinIO | `minioadmin` | `minioadmin123` |
| n8n | `admin` | `admin123` |

### Tài khoản mẫu (Seeder)

Mật khẩu chung: **`password`**

| Vai trò | Email | Số lượng |
|---|---|---|
| Admin | `admin@lms.com` | 1 |
| Giảng viên | `instructor1@lms.com` → `instructor8@lms.com` | 8 |
| Sinh viên | `student1@lms.com` → `student18@lms.com` | 18 |

**Seed kèm theo:** danh mục & khóa học (core miễn phí gắn CTĐT + extension marketplace), đơn hàng / ghi danh / tiến độ / quiz, cấu trúc học vụ (khoa, ngành, CTĐT, lớp, học kỳ), ngân hàng câu hỏi, CV & gợi ý nghề, career paths theo ngành.

---

## AI Provider

Cấu hình tại **Admin → Quản lý AI**:

| Provider | Lấy API key |
|---|---|
| OpenAI | https://platform.openai.com/api-keys |
| Google Gemini | https://aistudio.google.com/app/apikey |
| OpenRouter | https://openrouter.ai/keys |

Backend gọi `ai-service` qua `AiServiceClient` (fallback chuỗi provider nếu primary lỗi).  
Local dev: `AI_SERVICE_URL=http://127.0.0.1:8001` trong `backend/.env` và **bật** FastAPI (`uvicorn` / container `lms_ai_service`).

### RAG giáo trình (chatbot sinh viên)

Nguồn PDF: [Giao-Trinh-PTIT](https://github.com/0xl4p/Giao-Trinh-PTIT).  
Gói minh chứng **3 môn / 3 ngành** (commit cùng source): `ai-service/rag/textbooks/`.  
Vector store: ChromaDB (`ai-service/data/chroma`, không commit).

```bash
cd ai-service
pip install -r requirements.txt

# Index PDF đã có trong rag/textbooks (không tải GitHub)
python -m rag.ingest --demo-pack
```

Production:

```bash
docker compose build ai-service && docker compose up -d ai-service
docker exec lms_ai_service python -m rag.ingest --demo-pack
```

Chi tiết lệnh: [`ai-service/rag/INGEST.txt`](ai-service/rag/INGEST.txt).

| Ngữ cảnh chat | Hành vi RAG |
|---|---|
| Chat toàn cục (layout sinh viên) | Tìm mọi giáo trình đã ingest; chọn 1 môn phù hợp (điểm cao / hòa thì random) |
| Trong khóa học (`/learn/...`) | Chỉ giáo trình khớp tên môn/khóa |
| Guest (chưa đăng nhập) | Không dùng RAG |

---

## Docker thường dùng

```bash
# Khởi động
docker compose up -d

# Build lại
docker compose build --no-cache
docker compose up -d

# Log / restart
docker compose logs backend -f
docker compose restart backend

# Dừng (giữ volume) / reset DB
docker compose down
docker compose down -v
```

---

## Cấu trúc thư mục

```
├── backend/          # Laravel API
├── frontend/         # Nuxt 4 (admin + instructor + student + public)
├── admin-ui/         # Legacy admin UI (tham chiếu / migration)
├── ai-service/       # FastAPI AI (+ rag/ ingest giáo trình → ChromaDB)
├── mobile/           # Flutter app
├── docker/           # Nginx & Docker configs
├── docs/             # Tài liệu (tunnel, báo cáo, checklist)
├── scripts/          # start-public, tiện ích
└── docker-compose.yml
```

---

## Tài liệu liên quan

- Postman: `PTIT_LMS_API.postman_collection.json`
- SRS / overview: `frontend/public/SRS_LMS-1.html`, `frontend/public/SYLVA_LMS_SYSTEM_OVERVIEW.html`
- Cloudflare Tunnel: `docs/cloudflare-tunnel.md`

---

## License

Dự án phục vụ mục đích học thuật (Đồ án tốt nghiệp PTIT).
