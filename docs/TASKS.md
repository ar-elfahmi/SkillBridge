# Rencana Kerja Bertahap (untuk vibe coding)

Kerjakan **satu task per sesi** Claude Code. Setelah selesai dan test lulus, ubah `[ ]` menjadi `[x]`. Baca `CLAUDE.md` dan dokumen yang disebut pada tiap task.

## Template prompt per sesi
```
Baca CLAUDE.md dan {dokumen terkait}. Kerjakan task {ID} dari docs/TASKS.md.
Ikuti kriteria penerimaan, tulis test, jalankan test, lalu centang task.
Jika ada yang ambigu, tanya dulu.
```

---

## Fase 0 — Fondasi
- [ ] **T0.1 Scaffold monorepo.** Buat `backend/` (Laravel + Sanctum + Redis queue + PostgreSQL) dan `frontend/` (React + Vite + React Router + TS + Tailwind + shadcn/ui). `.env.example` kedua sisi, `README` singkat, docker compose opsional untuk Postgres+Redis.
  *Terima:* `php artisan test` dan `npm run build` berjalan; koneksi DB & Redis berfungsi.
- [x] **T0.2 Config SkillBridge.** `config/skillbridge.php` membaca env (model, dimensi, top-K, threshold, batas upload/halaman).
  *Terima:* config bisa di-override di test.

## Fase 1 — Auth & role
Dok: `API.md §2`, `SECURITY.md`
- [x] **T1.1** Register/login/logout/me dengan Sanctum SPA; kolom `users.role` (`student`, `study_program`).
  *Terima:* test auth lulus; CSRF cookie bekerja dari frontend lokal.
- [ ] **T1.2** Halaman `/login` dan `/register` + guard sesi di frontend.

## Fase 2 — Upload & parsing RPS
Dok: `DATABASE.md`, `API.md §3`, `AI_PIPELINE.md §1–2`
- [x] **T2.1** Migrasi `courses`, `rps_documents`, `rps_pages`; model + Policy.
- [x] **T2.2** `POST /api/rps`: validasi (MIME isi, ukuran, halaman), simpan privat, `file_hash`, deteksi duplikat (409), dispatch `ParseRpsJob`.
- [x] **T2.3** `ParseRpsJob`: teks per halaman → `rps_pages`; deteksi `needs_ocr`; status benar.
  *Terima:* fixture PDF digital dan PDF kosong teks diuji.
- [ ] **T2.4** Klien Gemini (`Modules/Ai/GeminiClient`) berbasis `Http` dengan timeout, retry terbatas, pencatatan `ai_runs`; fake di test. Tabel `ai_runs`.
- [ ] **T2.5** `ExtractRpsMetadataJob` + `PATCH /api/rps/{id}` + `GET /api/rps`, `GET /api/rps/{id}`, `DELETE`.
- [ ] **T2.6** Frontend: `/rps/upload` (dropzone, progress, form metadata, notifikasi pemrosesan pihak ketiga) dan polling status.

## Fase 3 — Ekstraksi kompetensi
Dok: `AI_PIPELINE.md §3`, `DATABASE.md (skills, course_skills)`
- [x] **T3.1** Migrasi `skills`, `course_skills`; seeder kamus awal (Laravel Framework, Git, REST API, Docker, dst.) dengan aliases.
- [ ] **T3.2** `SkillNormalizer` (alias → kanonik, buat baru bila perlu) + test.
- [ ] **T3.3** `EvidenceVerifier` (normalisasi teks, substring per halaman) + test halusinasi.
- [ ] **T3.4** `ExtractCompetenciesJob`: prompt `rps-extract-v1`, validasi skema, verifikasi bukti, normalisasi, dedup, simpan. `POST /api/rps/{id}/extract`, `GET /api/rps/{id}/competencies`.
  *Terima:* skema invalid → retry → `failed`; bukti palsu → `unverified`.
- [ ] **T3.5** Frontend `/rps/:id`: status, tabel kompetensi, bukti, badge verifikasi.
- [ ] **T3.6** Konfirmasi hasil ekstraksi. Migrasi kolom `rps_documents.review_status/reviewed_at` dan `course_skills.origin/review_status/user_edited` (+ `evidence` nullable). Endpoint `PATCH/POST/DELETE .../competencies`, `POST /api/rps/{id}/confirm-review`; edit memicu embedding ulang; update `RunAnalysisJob`/prasyarat. Frontend `CompetencyReviewTable` + tombol konfirmasi.
  *Terima:* analisis ditolak (422) sebelum konfirmasi; `rejected` tidak jadi kandidat; kompetensi `origin=user` maksimal `partial`; policy hanya pemilik.

## Fase 4 — Embedding
Dok: `AI_PIPELINE.md §4`
- [ ] **T4.1** `EmbeddingService`: batch, normalisasi L2, `text_hash`, cache lintas `course_skills`/`job_skills`, simpan model & dimensi.
  *Terima:* cache hit tidak memanggil API; model/dimensi berbeda memanggil ulang.
- [ ] **T4.2** `Support/Cosine` + test numerik.
- [ ] **T4.3** Sambungkan embedding ke akhir `ExtractCompetenciesJob`.

## Fase 5 — Industri (profesi & lowongan)
Dok: `API.md §4`, `AI_PIPELINE.md §5`
- [x] **T5.1** Migrasi `careers`, `job_postings`, `job_skills`; seeder profesi (Backend, Frontend, Full Stack, Data Analyst).
- [ ] **T5.2** Perintah `skillbridge:import-jobs {file}` (CSV/JSON: perusahaan, posisi, deskripsi, kualifikasi, `source_url`, `collected_at`, `dataset_version`) + seeder contoh berlabel "data contoh".
- [ ] **T5.3** `ExtractJobSkillsJob` (prompt `job-extract-v1`), normalisasi, embedding. `POST /api/jobs`, `GET /api/jobs`, `GET /api/jobs/{id}`, `GET /api/careers`.
- [ ] **T5.4** Frontend: pilih profesi, daftar lowongan (perusahaan, posisi, skill, sumber), form tambah lowongan manual.

## Fase 6 — Matching & Skill Gap
Dok: `AI_PIPELINE.md §6`, `API.md §5`
- [ ] **T6.1** Migrasi `analyses`, `analysis_job_postings`, `skill_matches`.
- [ ] **T6.2** `CandidateFinder`: top-K cosine; test dengan vektor sintetis.
- [ ] **T6.3** `MatchJudge` (prompt `match-judge-v1`) + `ClassificationGuardrails` (id kandidat valid, bukti, nama teknologi, unverified→max partial, floor).
- [ ] **T6.4** `RunAnalysisJob`: agregasi skill per `skill_id` + `demand_count`, klasifikasi, `summary`, `model_versions`. Endpoint `POST /api/analyses`, `GET /api/analyses`, `GET /api/analyses/{id}`, `GET .../matches`, `DELETE`.
  *Terima:* prasyarat 422; persentase = covered/total; partial tidak dihitung.
- [ ] **T6.5** Frontend `/analyses/new` (pemetaan awal) dan tab Ringkasan + Skill Gap + EvidenceDrawer di `/analyses/:id`.

## Fase 7 — Rekomendasi
Dok: `AI_PIPELINE.md §7`
- [ ] **T7.1** Migrasi `recommendations`; `PriorityCalculator` sesuai aturan + test.
- [ ] **T7.2** `GenerateRecommendationsJob` untuk `student` dan `study_program` (prompt `reco-v1`), validasi skema, tanpa URL; `related_weeks/sources` dari data.
- [ ] **T7.3** `GET /api/analyses/{id}/recommendations?audience=`.
- [ ] **T7.4** Frontend tab Rekomendasi Belajar dan Rekomendasi Kurikulum + DisclaimerBanner.
- [ ] **T7.5** Skill pribadi (Tahap 4). Migrasi `user_skills`; `GET/POST/DELETE /api/me/skills` (hanya `student`, normalisasi via `SkillNormalizer`, idempoten); `self_declared` pada `GET .../matches`; `GenerateRecommendationsJob` mengecualikan skill pribadi + `meta.self_declared_skills`; `POST /api/analyses/{id}/recommendations/refresh`. Frontend `SelfDeclaredSkillAction` dan `SelfDeclaredSkillsPanel`.
  *Terima:* `summary`/`classification` tidak berubah setelah menambah skill pribadi; hanya pemilik yang dapat membaca/menghapus; role `study_program` 403; refresh idempotent; teks UI memakai "dilaporkan pengguna", bukan "dikuasai".

## Fase 8 — Penyelesaian
Dok: `TESTING.md`, `SECURITY.md`, `FRONTEND.md`
- [ ] **T8.1** Rate limiting, batas kuota AI, pesan error ramah, halaman riwayat/dashboard.
- [ ] **T8.2** Golden dataset + `skillbridge:evaluate` (precision/recall/F1) dan dokumen `docs/CALIBRATION.md`.
- [ ] **T8.3** Responsif (375/768/1280), loading/empty/error state, audit kata-kata UI.
- [ ] **T8.4** Deploy ke VPS: Nginx (serve build statis frontend + reverse proxy ke PHP-FPM), PostgreSQL, Redis, queue worker via systemd/Supervisor, HTTPS (Certbot), storage PDF di disk privat di luar web root, backup DB + storage terjadwal, domain same-site untuk cookie.
- [ ] **T8.5** Verifikasi seluruh checklist kriteria MVP di `PRD.md §9`.

---

## Catatan dependensi urutan
`T0 → T1 → T2 → T3 → T4 → T5 → T6 → T7 → T8`. Frontend tiap fase dikerjakan setelah endpoint fase itu stabil. T5 dapat dikerjakan paralel dengan T3/T4 setelah T2.4 (klien Gemini) selesai.
