# Komunikasi — Orang A (Backend Inti & Infra)

> Hanya Orang A / agent A yang menulis di file ini. Protokol: `docs/comms/README.md`.

## Status
- [x] T0.1 backend — selesai, belum di-merge (branch `feat/T0.1-scaffold-backend`). Bagian frontend T0.1 milik C.
- [ ] T0.2 `config/skillbridge.php` — berikutnya

## Handoff

#### HO-A-001 · 2026-11-10 · T0.1 · untuk: B, C
Backend Laravel 13 sudah di-scaffold di `backend/`.
- **Menjalankan lokal:** `docker compose up -d` di root (Postgres 17 + Redis 7; db/user/password `skillbridge`, db test `skillbridge_test`), lalu langkah di `README.md` root. Health check: `GET http://localhost:8000/up`.
- **Auth:** Sanctum terpasang, `statefulApi()` aktif (cookie SPA). Endpoint auth belum ada (T1.1). Route bawaan `GET /api/user` (auth:sanctum) masih ada sementara.
- **Untuk C:** CORS hanya mengizinkan `FRONTEND_URL` (default `http://localhost:5173`) dengan `supports_credentials=true`. `SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173`. Alur: `GET /sanctum/csrf-cookie` → kirim header `X-XSRF-TOKEN`, `withCredentials: true`. Jalankan Vite di port 5173.
- **Untuk B:** Redis client = `predis` (tanpa ekstensi phpredis). `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`. Env Gemini sudah ada di `.env.example` (`GEMINI_API_KEY`, `GEMINI_FLASH_MODEL`, `GEMINI_EMBEDDING_MODEL`, `GEMINI_EMBEDDING_DIMENSIONS`, `GEMINI_TIMEOUT_SECONDS`) — dibaca lewat `config/skillbridge.php` setelah T0.2; sampai itu jangan memanggil `env()` langsung di kode.
- **Test:** `php artisan test` memakai PostgreSQL `skillbridge_test` (bukan sqlite) dan butuh Redis aktif. Contoh: `tests/Feature/InfrastructureTest.php`.
- **Storage:** disk `private` (`storage/app/private`, `serve=false`) untuk PDF RPS.

Breaking change: tidak.

## Permintaan
_(belum ada)_
