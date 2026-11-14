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
- [x] T7.1 migrasi `recommendations` + `PriorityCalculator` — selesai, di-merge (PR #14)
- [x] T5.5 `skillbridge:scrape-jobs` (Remotive/Arbeitnow/Himalayas/Jobicy, hanya rumpun TI → JSON untuk `import-jobs`) — selesai, di-merge (PR #15). **Untuk B/C:** lowongan hasil scrape berbahasa Inggris, `source_name` = `Remotive`/`Arbeitnow`/`Himalayas`/`Jobicy` dan `source_url` terisi (wajib ditampilkan sebagai atribusi); tetap `extraction_status = pending` sampai T5.3. Di Windows lokal, PHP perlu `curl.cainfo` di php.ini bila muncul cURL error 60. Lowongan bisa berbahasa Inggris/Jerman/Prancis (Arbeitnow).
  - **Profesi kini 21 (rumpun TI)**, bukan 4 — lihat `CareerSeeder::careers()`. **Untuk C (T5.4):** pemilih profesi perlu muat ±21 opsi (mis. combobox/select dengan pencarian), jangan hardcode 4. Data contoh tetap hanya untuk 4 profesi awal; profesi lain bisa kosong sampai dataset hasil scrape diimpor. **Untuk B:** tidak ada perubahan skema/kontrak.
- [x] Tindak lanjut PR #16 (sequence diagrams): unique `courses.code` + `rps_documents.detected_metadata`/`metadata_confirmed_at` — selesai, menunggu review (branch `feat/rps-metadata-draft-columns`)
- [ ] Tugas A Hari 1 selesai. Berikutnya bergantung B/C: bantu T6.4 (controller/resource/policy analyses), merge point #3, T8.1, T8.4; ⭐ migrasi T3.6/T7.5 bila diminta.

## Handoff

#### HO-A-015 · 2026-11-14 · T8.4 (bagian Docker) · untuk: B, C
Deploy berbasis image siap pakai (branch `feat/T8.4-docker-deploy`, menunggu review). Panduan: `docs/DEPLOY.md`.
- `deploy/docker/{api,web}.Dockerfile`, `deploy/docker/{php,nginx}/`, `deploy/prod/` (compose, `.env.example`, `deploy.sh`), `.github/workflows/docker-image.yml` (push `main` → GHCR `skillbridge-api` dan `skillbridge-web`, tag SHA + `latest`).
- Diuji lokal: kedua image terbangun; stack lima container sehat; `/up`, fallback SPA, `/sanctum/csrf-cookie`, register + `/api/auth/me` + `/api/careers` lewat satu origin berhasil; RAM idle total sekitar 120 MB.
- **Untuk C:** build produksi memakai `VITE_API_URL=/api` (same-origin). Tidak ada perubahan kode frontend. Jangan taruh rahasia di `VITE_*`.
- **Untuk B:** worker produksi `queue:work redis --tries=3`; `LOG_CHANNEL=stderr`. Tidak ada perubahan kode backend.
- Belum: backup terjadwal, test backend di CI, verifikasi workflow di GitHub (SHA action belum pernah dijalankan). T8.4 belum dicentang.
- Catatan: `php artisan db:seed` tanpa `--class` membuat akun `test@example.com`/`password`; di produksi jalankan seeder per kelas (lihat `docs/DEPLOY.md`).

Breaking change: tidak.

#### HO-A-014 · 2026-11-10 · T5.5 · untuk: B
Dataset lowongan TI asli sudah ada untuk menguji T5.3 (`ExtractJobSkillsJob`).
- **Di DB lokal A:** 737 lowongan diimpor dari `backend/storage/app/private/scraped/verified-2026-11-10.json` (Arbeitnow 469, Jobicy 193, Himalayas 70, Remotive 5) + 8 data contoh = 745, semua `extraction_status = pending`, `created_by = null`, `job_skills` kosong. Folder `storage/app/private/` tidak di-commit; minta file ke A, lalu `php artisan db:seed --class=CareerSeeder` dan `php artisan skillbridge:import-jobs <file>`.
- **Bahasa campur:** banyak lowongan Arbeitnow berbahasa **Jerman** (sebagian Prancis), sisanya Inggris. Prompt `job-extract-v1` perlu menangani ini, atau filter lowongan non-Inggris dulu. `evidence` tetap harus kutipan persis dari teks asli (jangan diterjemahkan), supaya lolos verifikasi substring.
- **Deskripsi panjang & berisik:** ada benefit, info perusahaan, nomor telepon, dan footer sumber (mis. "Find Jobs in Germany on Arbeitnow"). `qualifications` selalu `null` (API tidak memisahkannya).
- **Volume:** ±737 panggilan LLM + embedding. Perhatikan kuota/rate limit Gemini; sediakan perintah untuk mengekstrak lowongan `pending` secara bertahap (lihat HO-A-009).
- **JANGAN impor `backend/storage/app/private/scraped/all-2026-11-10.json` apa adanya.** Isinya 651 baris **sintetis** (`dataset_version = synthetic-2026-11-10`, label LinkedIn/JobStreet/Glints) dengan nama perusahaan & URL asli tetapi deskripsi karangan, jadi akan menjadi bukti palsu (melanggar aturan wajib no. 2). 142 baris lain (We Work Remotely, Remote OK, Working Nomads) ditahan sampai syarat penggunaannya dicek.

Breaking change: tidak.

#### HO-A-013 · 2026-11-10 · tindak lanjut PR #16 · untuk: B, C
Skema untuk alur metadata draft → konfirmasi (`docs/diagrams/04`, `05`; `DIAGRAMS.md` §5 no. 7 & 10) tersedia. Detail di `docs/DATABASE.md`.
- `courses.code` kini **unique** (banyak `null` tetap boleh). Saat konfirmasi: cari by `code`, reuse bila ada, insert bila belum; tangani `UniqueConstraintViolationException` untuk konfirmasi bersamaan (pakai `firstOrCreate` + retry/catch).
- `rps_documents.detected_metadata` (jsonb null, cast `array`): draft `{code, name, credits, semester, study_program}` dari `ExtractRpsMetadataJob`/`PATCH`. Tidak pernah menulis `courses`.
- `rps_documents.metadata_confirmed_at` (timestamp null): diisi bersama `course_id` oleh `POST /api/rps/{id}/extract`. `RpsDocument::isMetadataConfirmed()` = gerbang ekstraksi kompetensi.
- **Untuk B (T2.5):** bentuk `detected_metadata` dan apakah field ini tampil di `RpsDocumentResource` / `API.md` §3 adalah keputusanmu; A tidak mengubah resource/kontrak API.
- **Untuk C:** belum ada perubahan respons API sampai B memperbarui `API.md`.

Breaking change: tidak (kolom baru nullable; unique `code` hanya menolak kode ganda, saat ini belum ada kode yang menulis `courses`).

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

## Jawaban DISCUSS PR #16
- **Jawab DISCUSS-1:** `rps_documents.detected_metadata` (jsonb null) sudah ditambahkan oleh A (lihat HO-A-013), jadi T2.5 tidak perlu membuat migration sendiri.
- **Jawab DISCUSS-4 / gap §5 no. 6 & 13:** penanda eksplisit dibutuhkan → kolom `rps_documents.metadata_confirmed_at` ditambahkan. Konfirmasi = isi `course_id` + `metadata_confirmed_at` dalam satu transaksi. Status metadata terpisah (enum) tidak ditambahkan; draft ada/tidak + `metadata_confirmed_at` sudah cukup.
- **Jawab DISCUSS-7:** migration unique `courses.code` memang belum pernah masuk `main`; dibuat ulang sebagai `2026_11_10_700000_add_unique_code_to_courses_table.php`, catatan di `docs/DATABASE.md` ditambahkan. Env dev: jalankan `php artisan migrate`.
- **Jawab DISCUSS-8:** gap §5 no. 1–4 ada di area B (`Matching`/`Recommendation`, T6.3–T7.2), bukan A. A tidak memutuskan sepihak; menunggu jawaban B di `B.md`. Usulan A (tidak mengikat): (1) id di luar top-K → tolak hasil LLM, fallback ke klasifikasi aturan; (3) `RunAnalysisJob` men-dispatch `GenerateRecommendationsJob` setelah sukses; (4) `processing` di-set di awal `handle()` job, bukan di endpoint.
- **DISCUSS-5** (dispatch `ExtractJobSkillsJob` dari import) tetap menunggu B (lihat HO-A-009). **DISCUSS-6** (URL seeder): data contoh memakai `source_url = null`, jadi tidak ada URL karangan; tidak perlu aturan tambahan.
