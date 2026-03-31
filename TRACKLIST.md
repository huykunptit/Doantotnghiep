# TRACKLIST triển khai LMS (31/03/2026 → 31/07/2026)

## 0) Ưu tiên tổng thể

### Bắt buộc (để kịp MVP + đúng JD)
- [ ] Infrastructure: Docker Compose (MySQL, Redis, MinIO, MongoDB, Laravel, Nuxt, FastAPI)
- [ ] Auth + Google SSO
- [ ] Course CRUD + Lesson + upload video qua queue
- [ ] Marketplace thanh toán (VNPay hoặc Stripe fallback)
- [ ] Presigned URL xem video
- [ ] AI chatbot (Laravel proxy -> FastAPI)
- [ ] Nuxt.js 3 frontend chuẩn (không dùng Vue Vite thuần)
- [ ] CI/CD GitHub Actions

### Nên có (nếu còn thời gian)
- [ ] Progress tracking
- [ ] Quiz
- [ ] Admin dashboard
- [ ] Flutter app

## 1) Tracklist theo phase (kèm phần PHẢI LÀM / PHẢI HỌC)

## Phase 1 (01/04/2026 - 14/04/2026): Foundation

### PHẢI LÀM
- [ ] Tạo `docker-compose.yml` đủ service: `mysql`, `redis`, `minio`, `mongodb`, `backend`, `frontend`, `ai-service`
- [ ] Backend Laravel 10 chạy được với MySQL
- [ ] Cài `spatie/laravel-permission` + seed role `admin/instructor/student`
- [ ] Tạo migration core bảng: users, courses, lessons, enrollments, orders, lesson_progress
- [ ] Cấu hình filesystem S3-compatible (MinIO)
- [ ] Cấu hình queue Redis + Horizon
- [ ] Chuyển frontend sang Nuxt 3 + TS + Pinia + middleware auth

### PHẢI HỌC
- Laravel migrations, Eloquent relations, queue
- Nuxt 3 fundamentals: SSR, route middleware, data fetching
- Docker Compose cơ bản

### Lệnh chạy đề xuất
```bash
# Backend
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve

# Frontend (Nuxt)
cd frontend
npm install
npm run dev

# Queue/Horizon (terminal riêng)
cd backend
php artisan queue:work
php artisan horizon
```

### Link học chính thức
- Laravel 10 docs: https://laravel.com/docs/10.x
- Laravel Queue: https://laravel.com/docs/10.x/queues
- Laravel Horizon: https://laravel.com/docs/10.x/horizon
- Laravel filesystem (S3/MinIO): https://laravel.com/docs/10.x/filesystem#amazon-s3-compatible-filesystems
- Spatie permission: https://spatie.be/docs/laravel-permission
- Nuxt 3 docs: https://nuxt.com/docs/3.x/getting-started/introduction
- Nuxt data fetching: https://nuxt.com/docs/3.x/getting-started/data-fetching
- Nuxt middleware: https://nuxt.com/docs/3.x/guide/directory-structure/middleware
- Pinia: https://pinia.vuejs.org/getting-started.html
- Docker Compose file reference: https://docs.docker.com/compose/compose-file/
- MinIO docs: https://docs.min.io/

## Phase 2 (14/04/2026 - 28/04/2026): Auth + SSO

### PHẢI LÀM
- [ ] API auth: register/login/logout/me/update me
- [ ] Cài Socialite + Google OAuth
- [ ] Lưu `google_id` vào users
- [ ] Frontend login/register + nút login Google
- [ ] Pinia auth store + persist token cookie

### PHẢI HỌC
- OAuth2 flow (Authorization Code)
- Laravel Sanctum/JWT token flow
- Redirect/callback frontend-backend

### Lệnh chạy đề xuất
```bash
cd backend
composer require laravel/socialite
php artisan migrate

# test route/auth
php artisan route:list
php artisan test
```

### Link học chính thức
- Laravel Socialite: https://laravel.com/docs/10.x/socialite
- Google OAuth2: https://developers.google.com/identity/protocols/oauth2
- Laravel Sanctum: https://laravel.com/docs/10.x/sanctum

## Phase 3 (28/04/2026 - 12/05/2026): Course + Lesson + Upload

### PHẢI LÀM
- [ ] Course CRUD + Policy (đúng instructor mới sửa/xóa)
- [ ] Lesson CRUD theo course
- [ ] Upload video qua queue job lên MinIO
- [ ] API lấy presigned URL (TTL 1h)
- [ ] Frontend: Course List / Course Detail / Create-Edit Course

### PHẢI HỌC
- Laravel Policy/Authorization
- Queue job cho upload file lớn
- Presigned URL cơ chế bảo vệ asset

### Lệnh chạy đề xuất
```bash
cd backend
php artisan make:policy CoursePolicy --model=Course
php artisan make:job UploadLessonVideoJob
php artisan storage:link
php artisan queue:work
```

### Link học chính thức
- Laravel Authorization/Policy: https://laravel.com/docs/10.x/authorization
- Laravel Filesystem: https://laravel.com/docs/10.x/filesystem
- AWS S3 presigned URL (tham chiếu cơ chế): https://docs.aws.amazon.com/AmazonS3/latest/userguide/ShareObjectPreSignedURL.html

## Phase 4 (12/05/2026 - 26/05/2026): Marketplace + Payment

### PHẢI LÀM
- [ ] Tạo order pending khi user mua
- [ ] Redirect thanh toán + callback/webhook
- [ ] Verify signature callback
- [ ] Update order paid + tạo enrollment
- [ ] Chặn xem bài học nếu chưa enrolled
- [ ] Frontend checkout + order history

### PHẢI HỌC
- Payment webhook idempotency
- Verify chữ ký callback
- Transaction consistency (order/enrollment)

### Link học chính thức
- VNPay: https://sandbox.vnpayment.vn/apis/docs/
- Stripe (fallback): https://docs.stripe.com/payments/checkout

## Phase 5 (26/05/2026 - 09/06/2026): Learning + Progress + Quiz

### PHẢI LÀM
- [ ] Ghi `lesson_progress`
- [ ] API % tiến độ khóa học
- [ ] Quiz CRUD + quiz_attempts
- [ ] (Optional) cấp certificate khi hoàn thành 100%

### PHẢI HỌC
- Tracking state học theo event video
- Thiết kế API thống kê tiến độ

### Link học chính thức
- Laravel Eloquent relationships: https://laravel.com/docs/10.x/eloquent-relationships
- Laravel API resources: https://laravel.com/docs/10.x/eloquent-resources

## Phase 6 (09/06/2026 - 23/06/2026): AI Chatbot

### PHẢI LÀM
- [ ] Setup FastAPI service
- [ ] Tạo `POST /chat`
- [ ] Inject context course/lesson outline
- [ ] Gọi OpenAI API
- [ ] Lưu history MongoDB
- [ ] Laravel `POST /api/chat` proxy sang FastAPI
- [ ] Frontend chat floating trong course player

### PHẢI HỌC
- Prompt design cho domain-specific assistant
- Service-to-service HTTP giữa Laravel và FastAPI
- MongoDB document model cho history chat

### Lệnh chạy đề xuất
```bash
cd ai-service
python -m venv .venv
source .venv/bin/activate
pip install fastapi uvicorn pydantic openai pymongo
uvicorn app.main:app --reload --host 0.0.0.0 --port 8001
```

### Link học chính thức
- FastAPI tutorial: https://fastapi.tiangolo.com/tutorial/
- Pydantic docs: https://docs.pydantic.dev/latest/
- Uvicorn docs: https://www.uvicorn.org/
- OpenAI API docs: https://platform.openai.com/docs/quickstart
- MongoDB manual: https://www.mongodb.com/docs/manual/

## Phase 7 (23/06/2026 - 07/07/2026): Flutter mobile

### PHẢI LÀM
- [ ] Login/register + Google SSO
- [ ] List/course detail/purchase/video/chat/progress
- [ ] Dùng chung API backend

### PHẢI HỌC
- Quản lý token an toàn trên mobile
- Video player + presigned URL

### Link học chính thức
- Flutter docs: https://docs.flutter.dev/
- Dio: https://pub.dev/packages/dio
- Riverpod: https://pub.dev/packages/flutter_riverpod
- Video player: https://pub.dev/packages/video_player
- Flutter secure storage: https://pub.dev/packages/flutter_secure_storage
- Google sign in: https://pub.dev/packages/google_sign_in

## Phase 8 (07/07/2026 - 21/07/2026): CI/CD + Admin + Polish

### PHẢI LÀM
- [ ] GitHub Actions: test + deploy
- [ ] Admin dashboard (thống kê, user/course/order)
- [ ] README đầy đủ + demo video 3-5 phút
- [ ] Deploy VPS + domain + SSL

### PHẢI HỌC
- CI pipeline cơ bản
- SSH deploy và rollback nhanh

### Link học chính thức
- GitHub Actions docs: https://docs.github.com/en/actions
- Workflow syntax: https://docs.github.com/en/actions/writing-workflows/workflow-syntax-for-github-actions
- Certbot (Let's Encrypt): https://certbot.eff.org/

## 2) Thứ tự thực hiện khi bị thiếu thời gian

1. Phase 1 + 2 + 3 (nền tảng + auth + khóa học)
2. Phase 4 (payment + enrollment gate)
3. Phase 6 (AI chatbot cơ bản)
4. Phase 8 phần CI/CD cơ bản
5. Phase 5, 7 làm sau nếu còn thời gian

## 3) Checklist học hằng tuần (2 giờ/ngày)

- [ ] 45 phút đọc docs chính thức
- [ ] 60 phút code thẳng vào đồ án
- [ ] 15 phút ghi lại note: vấn đề gặp phải + cách xử lý

## 4) Definition of Done (trước 31/07/2026)

- [ ] Repo chạy local bằng Docker Compose
- [ ] Web: đăng ký/đăng nhập Google, mua khóa học, học video, chat AI
- [ ] Có link demo/public hoặc video demo rõ luồng nghiệp vụ
- [ ] CI chạy test pass khi push `main`
- [ ] README mô tả setup + kiến trúc + tài khoản test
