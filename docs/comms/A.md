# Komunikasi — Orang A (Backend Inti & Infra)

> Hanya Orang A / agent A yang menulis di file ini. Protokol: `docs/comms/README.md`.

## Status
- [x] T0.1 backend — selesai, di-merge ke `main` (PR #1). Bagian frontend T0.1 milik C.
- [x] T0.2 `config/skillbridge.php` — selesai, di-merge (PR #3)
- [x] T1.1 auth Sanctum + `users.role` — selesai, di-merge (PR #4)
- [x] T2.1 migrasi `courses`/`rps_documents`/`rps_pages` + model + Policy — selesai, di-merge (PR #5)
- [x] `chore: add smalot/pdfparser` — di-merge (PR #6)
- [x] T2.2 `POST /api/rps` upload — selesai, di-merge (PR #7)
- [x] T2.3 `ParseRpsJob` — selesai, menunggu review (branch `feat/T2.3-parse-rps-job`)
- [ ] T3.1 migrasi `skills`/`course_skills` + seeder — berikutnya

## Handoff

#### HO-A-006 · 2026-11-10 · T2.3 · untuk: B, C
`ParseRpsJob` sekarang mengisi `rps_pages` (satu baris per halaman, `page_number` mulai 1, spasi dinormalisasi: spasi berurutan → satu, baris baru dipertahankan).
- Status akhir `parse_status`: `parsed` | `needs_ocr` (rata-rata < `skillbridge.upload.min_chars_per_page` karakter non-spasi per halaman; halaman tetap disimpan) | `failed`. `error_message` berisi pesan aman berbahasa Indonesia.
- **Untuk B (T2.5):** job ini **belum** men-dispatch `ExtractRpsMetadataJob`. Tambahkan dispatch-nya di akhir `handle()` saat status `parsed` (ada komentar penanda), atau minta A lewat `REQ-B-xxx`. `EvidenceVerifier` bisa membaca `RpsDocument::pages` (terurut).
- **Untuk C:** setelah upload (202), polling `GET /api/rps/{id}` sampai `parse_status` ∉ {`pending`, `parsing`}; tampilkan `error_message` bila `needs_ocr`/`failed`.

Breaking change: tidak.

#### HO-A-005 · 2026-11-10 · T2.2 · untuk: B, C
`POST /api/rps` (multipart, field `file`) tersedia; kontrak lengkap di `docs/API.md` §3.
- **Untuk C:** 202 + RPS resource (`id`, `original_name`, `parse_status`, `extraction_status`, `review_status`, `course`, `page_count`, `error_message`, `created_at`) → polling `GET /api/rps/{id}` (endpoint GET milik B, T2.5). Error: 422 `errors.file[0]`, 413 terlalu besar, 409 `existing_id`, 429.
- **Untuk B:** pakai `App\Modules\Rps\Http\Resources\RpsDocumentResource` untuk GET/PATCH di T2.5 agar bentuk respons sama. `App\Modules\Rps\Jobs\ParseRpsJob` sudah di-dispatch (isi `handle()` di T2.3); tes yang butuh PDF bisa memakai `Tests\Support\PdfFixture::make([...teks per halaman])`.
- Berkas tersimpan di disk `private` pada `rps/{user_id}/{sha256}.pdf`.

Breaking change: tidak.

#### HO-A-004 · 2026-11-10 · T2.1 · untuk: B
Tabel & model RPS tersedia (skema: `docs/DATABASE.md`):
- `App\Models\Course`, `App\Models\RpsDocument` (`user`, `course`, `pages` terurut `page_number`), `App\Models\RpsPage` (`document`).
- Status di-cast ke enum `App\Modules\Rps\Enums\{ParseStatus, ExtractionStatus, ReviewStatus}`; default `pending`/`pending`/`unreviewed`. Bandingkan dengan enum, bukan string.
- `RpsDocument::file_path` disembunyikan dari serialisasi; baca file lewat `Storage::disk('private')`.
- `App\Policies\RpsDocumentPolicy` (view/update/delete): hanya pemilik; selain pemilik → 404 (`denyAsNotFound`). Pakai `$this->authorize('view', $document)` / `Gate`.
- Factory: `RpsDocument::factory()`, `RpsPage::factory()`, `Course::factory()`.

Breaking change: tidak.

#### HO-A-003 · 2026-11-10 · T1.1 · untuk: C
Endpoint auth sudah ada di `main`; kontrak lengkap di `docs/API.md` §2.
- `GET /sanctum/csrf-cookie` → `POST /api/auth/register` (201, langsung login) / `POST /api/auth/login` (200) / `POST /api/auth/logout` (204) / `GET /api/auth/me` (200).
- Respons: `{ "data": { id, name, email, role, created_at } }`; `role` ∈ `student` | `study_program`.
- Error: 422 `errors.<field>[]` (pesan Indonesia), login gagal → `errors.email[0] = "Email atau kata sandi salah."`, 401 `{"message":"Silakan masuk terlebih dahulu."}`, 429 setelah 5 percobaan/menit (`SKILLBRIDGE_AUTH_PER_MINUTE`).

Breaking change: route sementara `GET /api/user` dihapus — pakai `/api/auth/me`.

#### HO-A-002 · 2026-11-10 · T0.2 · untuk: B
`backend/config/skillbridge.php` tersedia (setelah PR T0.2 di-merge). Baca selalu lewat `config()`, jangan `env()`:
- `skillbridge.gemini.api_key|flash_model|embedding_model|embedding_dimensions (int)|timeout_seconds (int)`
- `skillbridge.matching.top_k (int)|sim_floor (float)|sim_high (float)` — placeholder, belum dikalibrasi
- `skillbridge.upload.max_pdf_mb (int)|max_pages (int)`
- Override di test: `config(['skillbridge.matching.top_k' => 5])`. Tambah key baru di bagian modul sendiri, satu key per baris.

Breaking change: tidak.

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
