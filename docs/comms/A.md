# Komunikasi — Orang A (Backend Inti & Infra)

> Hanya Orang A / agent A yang menulis di file ini. Protokol: `docs/comms/README.md`.

## Status
- [x] T0.1 backend — selesai, di-merge ke `main` (PR #1). Bagian frontend T0.1 milik C.
- [x] T0.2 `config/skillbridge.php` — selesai, di-merge (PR #3)
- [x] T1.1 auth Sanctum + `users.role` — selesai, di-merge (PR #4)
- [x] T2.1 migrasi `courses`/`rps_documents`/`rps_pages` + model + Policy — selesai, di-merge (PR #5)
- [x] `chore: add smalot/pdfparser` — di-merge (PR #6)
- [x] T2.2 `POST /api/rps` upload — selesai, di-merge (PR #7)
- [x] T2.3 `ParseRpsJob` — selesai, di-merge (PR #8)
- [x] T3.1 migrasi `skills`/`course_skills` + seeder — selesai, di-merge (PR #9)
- [x] T5.1 migrasi `careers`/`job_postings`/`job_skills` + seeder profesi — selesai, di-merge (PR #10)
- [x] T5.2 `skillbridge:import-jobs` + data contoh — selesai, di-merge (PR #11)
- [x] Hapus role `study_program` + Rekomendasi Kurikulum (keputusan tim) — di-merge (PR #12)
- [x] T6.1 migrasi `analyses`/`analysis_job_postings`/`skill_matches` — selesai, di-merge (PR #13)
- [x] T7.1 migrasi `recommendations` + `PriorityCalculator` — selesai, menunggu review (branch `feat/T7.1-recommendations-priority`)
- [x] T5.5 `skillbridge:scrape-jobs` (Remotive/Arbeitnow → JSON untuk `import-jobs`) — selesai, menunggu review (branch `feat/T5.5-scrape-jobs`). **Untuk B/C:** lowongan hasil scrape berbahasa Inggris, `source_name` = `Remotive`/`Arbeitnow` dan `source_url` terisi (wajib ditampilkan sebagai atribusi); tetap `extraction_status = pending` sampai T5.3. Di Windows lokal, PHP perlu `curl.cainfo` di php.ini bila muncul cURL error 60.
- [ ] Tugas A Hari 1 selesai. Berikutnya bergantung B/C: bantu T6.4 (controller/resource/policy analyses), merge point #3, T8.1, T8.4; ⭐ migrasi T3.6/T7.5 bila diminta.

## Handoff

#### HO-A-012 · 2026-11-10 · T7.1 · untuk: B
Tabel `recommendations` + kalkulator prioritas tersedia. Siap untuk T7.2–T7.3.
- `App\Models\Recommendation`: `analysis`, `skillMatch`; `priority` → enum `App\Enums\RecommendationPriority` (`weight()` untuk urutan); `details` di-cast `array` (default `{}`), isi `{topics[], learning_keywords[]}`, **tanpa URL**. Unique (`analysis_id`, `skill_match_id`) → refresh: hapus lalu insert ulang (atau `upsert`) per analisis. Relasi: `Analysis::recommendations()`, `SkillMatch::recommendation()`.
- `App\Modules\Recommendation\Services\PriorityCalculator` (di area B; dibuat A sesuai T7.1): `calculate(MatchClassification, int $demandCount, int $selectedPostings): ?RecommendationPriority` atau `forMatch(SkillMatch, int $selectedPostings)`. `covered` → `null` (jangan buat rekomendasi). `$selectedPostings` = jumlah lowongan terpilih di analisis (`$analysis->jobPostings()->count()`).
- Aturan dari `config('skillbridge.recommendation.priority')`; env `SKILLBRIDGE_PRIORITY_HIGH_DEMAND_RATIO` (default 0.5). Penanda skill "wajib" dari PRD belum didukung skema, jadi tidak dipakai.

Breaking change: tidak.

#### HO-A-011 · 2026-11-10 · T6.1 · untuk: B
Tabel & model analisis tersedia (skema: `docs/DATABASE.md`). Siap untuk T6.2–T6.4.
- `App\Models\Analysis` (tabel `analyses`): `user`, `rpsDocument`, `career`, `jobPostings` (pivot `analysis_job_postings`), `skillMatches`, `isOwnedBy()`. `status` → enum `App\Enums\AnalysisStatus` (default `queued`); `summary`, `model_versions` di-cast `array`.
- `App\Models\SkillMatch`: `analysis`, `skill`, `courseSkill`. Enum `App\Enums\MatchClassification` dan `App\Enums\JudgedBy`. `similarity_score` di-cast `decimal:5` (string, mis. `"0.87654"`), **bukan** persen penguasaan; `source_job_skill_ids` dan `candidates` di-cast `array` (default `[]`). Unique (`analysis_id`, `skill_id`) → agregasi per skill harus selesai sebelum insert.
- `App\Policies\AnalysisPolicy`: hanya pemilik; selain itu 404.
- Relasi balik: `RpsDocument::analyses()`, `JobPosting::analyses()`. Factory: `Analysis::factory()` (pemilik = pemilik RPS), `SkillMatch::factory()` (default `missing`/`rule`).
- Catatan: jsonb **tidak menjaga urutan key**; jangan bergantung pada urutan key di `summary`/`model_versions`.

Breaking change: tidak.

#### HO-A-010 · 2026-11-10 · keputusan scope · untuk: B, C
**Keputusan tim: pengguna hanya mahasiswa.** Role `study_program` dan fitur Rekomendasi Kurikulum dihapus dari MVP (dipindah ke Pengembangan Lanjut di `docs/PRD.md`). Dokumen PRD, API, AI_PIPELINE, DATABASE, FRONTEND, ARCHITECTURE, SECURITY, TASKS, TESTING sudah disesuaikan.
- `users.role` tetap ada, selalu `student`. Enum `UserRole` hanya `Student`; `UserFactory::studyProgram()` dihapus.
- **Untuk C:** `POST /api/auth/register` tidak lagi menerima `role` (bila dikirim, diabaikan). Hapus pilihan role di form register. Tab hasil: Ringkasan, Skill Gap, Rekomendasi Belajar (tanpa Rekomendasi Kurikulum / `CurriculumRecommendationList`). `SelfDeclaredSkill*` berlaku untuk semua pengguna.
- **Untuk B (T7.2–T7.3):** hanya rekomendasi mahasiswa. Tidak ada `audience`: kolom `recommendations.audience` dihapus dari skema, `GET /api/analyses/{id}/recommendations` tanpa query `audience`, prompt `reco-v1` tanpa `{{audience}}`, `details` = `{topics[], learning_keywords[]}`. Endpoint `/api/me/skills` tidak lagi punya cek role (tetap hanya data milik pengguna).
- Kolom `courses.study_program` **tetap** (itu metadata nama prodi mata kuliah, bukan role).

Breaking change: **ya** — kontrak register (`role` tidak dipakai) dan rekomendasi (`audience` dihapus).

#### HO-A-009 · 2026-11-10 · T5.2 · untuk: B, C
- `php artisan db:seed` sekarang juga mengisi **8 lowongan data contoh** (2 per profesi, perusahaan fiktif `PT Contoh …`, `source_name = "data contoh"`, `source_url = null`, `dataset_version = "sample-v1"`, `created_by = null`, `extraction_status = pending`). Sumber: `backend/database/data/sample_jobs.json`.
- Impor dataset nyata: `php artisan skillbridge:import-jobs <file.csv|json> [--dataset-version=] [--dry-run]` (format di `README.md`). Logika ada di `App\Modules\Industry\Services\JobPostingImporter` (dipakai juga oleh seeder).
- **Untuk B (T5.3):** perintah impor **belum** men-dispatch `ExtractJobSkillsJob`, jadi lowongan impor tetap `pending` dan `job_skills` kosong. Saat T5.3 siap, tambahkan dispatch untuk lowongan baru/terupdate (ada komentar penanda di `ImportJobsCommand`), atau sediakan perintah untuk mengekstrak lowongan `pending`.
- **Untuk C (T5.4):** lowongan contoh harus ditampilkan dengan label "Data contoh" (`docs/FRONTEND.md`); kenali dari `source_name = "data contoh"` (atau field yang disepakati di `API.md` §4 saat T5.3).

Breaking change: tidak.

#### HO-A-008 · 2026-11-10 · T5.1 · untuk: B, C
Tabel & model industri tersedia (skema: `docs/DATABASE.md`). Siap untuk T5.3 (B) dan T5.4 (C).
- `App\Models\Career` (`jobPostings`), `App\Models\JobPosting` (`career`, `creator`, `jobSkills`, `isCurated()`, scope `visibleTo($user)` = terkurasi + milik sendiri), `App\Models\JobSkill` (`jobPosting`, `skillEntry`; `embedding` disembunyikan).
- `JobPosting::extraction_status` memakai enum `App\Modules\Rps\Enums\ExtractionStatus` (nilai sama: `pending|processing|completed|failed`).
- **Untuk B (T5.3):** `App\Policies\JobPostingPolicy` — `view`: terkurasi atau milik sendiri, selain itu 404; `update`/`delete`: terkurasi → 403 "Lowongan dari dataset terkurasi tidak dapat diubah.", milik orang lain → 404. Untuk `GET /api/jobs` pakai `JobPosting::visibleTo($request->user())`. `job_skills.skill_id` wajib (NOT NULL), jadi normalisasi harus terjadi sebelum insert.
- **Untuk C (T5.4):** profesi ter-seed dengan slug `backend-developer`, `frontend-developer`, `full-stack-developer`, `data-analyst` (`php artisan db:seed`). Data lowongan contoh menyusul di T5.2.

Breaking change: tidak.

#### HO-A-007 · 2026-11-10 · T3.1 · untuk: B
Tabel `skills` & `course_skills` + model tersedia (skema: `docs/DATABASE.md`). Siap untuk T3.2–T3.4 dan T4.x.
- `App\Models\Skill`: `aliases` di-cast `array` (jsonb, default `[]`); `names()` = kanonik + alias. `canonical_name` unique **case-insensitive** (index `LOWER(canonical_name)`), jadi `SkillNormalizer` sebaiknya mencari dengan `LOWER(...) = ?` dan menangani `UniqueConstraintViolationException` saat membuat entri baru secara bersamaan.
- `App\Models\CourseSkill`: relasi `document`, `skillEntry` (bukan `skill`, karena `skill` adalah kolom nama mentah). Scope `usableForMatching()` mengecualikan `rejected`. `embedding` di-cast `array` dan disembunyikan dari serialisasi.
- Enum bersama di `App\Enums`: `SkillCategory` (+ `isTechnologySpecific()`), `CompetencySourceType`, `CompetencyOrigin`, `CompetencyReviewStatus`, `VerificationStatus`. Dipakai juga untuk `job_skills` nanti.
- `RpsDocument::courseSkills()`. Factory: `Skill::factory()`, `CourseSkill::factory()`.
- Seeder: `Database\Seeders\SkillSeeder` (42 skill untuk Backend/Frontend/Full Stack/Data Analyst, idempoten, alias tidak saling bertabrakan). `php artisan db:seed` aman diulang.

Breaking change: tidak.

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
