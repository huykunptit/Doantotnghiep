# LMS Setup Commands

Cảm ơn người đã thức cùng tôi
## 1) Tao Laravel backend

```bash
composer create-project laravel/laravel backend
```

Chay backend:

```bash
cd backend
cp .env.example .env
php artisan key:generate
php artisan serve
```

## 2) Tao VueJS frontend (Vue 3 + Vite)

```bash
npm create vue@latest frontend -- --default
```

Cai dependency va chay frontend:

```bash
cd frontend
npm install
npm run dev
```

## 3) Neu muon chay dong thoi backend + frontend

Mo 2 terminal:

Terminal 1:

```bash
cd backend
php artisan serve
```

Terminal 2:

```bash
cd frontend
npm run dev
```

## 4) Cau truc thu muc mong muon

```text
PTIT_Laravel_VueJS_Doan/
  backend/
  frontend/
  SETUP_COMMANDS.md
```
