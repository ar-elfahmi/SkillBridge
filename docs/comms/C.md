# Komunikasi — Orang C (Frontend & UX)

> Hanya Orang C / agent C yang menulis di file ini. Protokol: `docs/comms/README.md`.

## Status
- [x] T0.1 frontend — selesai (scaffold Vite + React 19 + TS strict + Tailwind v4 + shadcn/ui radix-nova + React Router 7 + TanStack Query 5 + klien API CSRF)
- [x] T1.2 — selesai (halaman `/login` + `/register` sesuai frame `dihsain.pen`, brand tokens & font terintegrasi, guard sesi terverifikasi)
- [x] T2.6 & T8.1 Dashboard & Upload — selesai (`/dashboard` dengan Hero Dropzone, metrik riil, daftar RPS, sidebar portofolio)
- [x] T3.5 Detail RPS & kompetensi — selesai (`/rps/:id`, stepper 3 tahap + panel metadata MK)
- [x] T3.6 Review kompetensi — selesai (`CompetencyReviewTable`: setujui/edit/tolak/tambah, konfirmasi hasil ekstraksi)
- [x] T5.4 Profesi & lowongan — selesai (`/analyses/new` Step 1–2, form tambah lowongan manual)
- [x] T6.5 Pemetaan & hasil analisis — selesai (`/analyses/new` Step 3, `/analyses/:id` tab Ringkasan + Skill Gap + EvidenceDrawer)
- [x] T7.4 Rekomendasi Belajar — selesai (tab Rekomendasi + panel skill pribadi read-only + DisclaimerBanner)
- [ ] T7.5 Skill pribadi — terblokir backend (`user_skills` belum ada, lihat `REQ-C-006`)
- [ ] T8.3 Responsif & polish — antrian berikutnya
- [ ] T8.5 Checklist MVP — bersama A, B

Total 21 unit tests lulus, `npm run typecheck` + `npm run build` bersih. Branch aktif: `feat/rps-analysis-pages`.

## Handoff

#### HO-C-005 · 2026-11-13 · T3.5, T3.6, T5.4, T6.5, T7.4 · untuk: A, B
**Seluruh halaman RPS & analisis selesai.** Branch: `feat/rps-analysis-pages`.
1. **Detail RPS (`/rps/:id`)** — `RpsHeader` (stepper 3 tahap, klik untuk bypass inspeksi desain), `MetadataPanel` (form 5 field, simpan draft + mulai ekstraksi), polling otomatis 2,5 detik saat `parsing`/`processing`.
2. **Review kompetensi** — `CompetencyReviewTable`: tabel desktop + kartu mobile, filter (Semua/Belum diverifikasi/Menunggu/Ditolak), aksi setujui/edit/tolak/hapus, tambah kompetensi manual, bar konfirmasi hasil ekstraksi → lanjut ke `/analyses/new`.
3. **Pemetaan industri (`/analyses/new?rps=:id`)** — Step 1 profesi (`GET /api/careers`), Step 2 lowongan (search, filter, checkbox, panel detail), Step 3 pratinjau agregasi skill dihitung klien dari `jobSkills` lowongan terpilih, form tambah lowongan (`POST /api/jobs`), tombol Mulai Analisis (`POST /api/analyses`).
4. **Hasil analisis (`/analyses/:id`)** — `CoverageSummary` (3 kartu + bar cakupan + disclaimer), `SkillGapTable` (filter, toggle sembunyikan `self_declared`, EvidenceDrawer berisi kutipan RPS + kutipan lowongan per perusahaan + alasan + kemiripan semantik), `LearningRecommendationList` (prioritas, topik, kata kunci, tanpa URL).
5. **Perubahan backend yang saya butuhkan dan sudah diterapkan:**
   - `JobPostingController::index` eager-load `jobSkills.skillEntry` (agar badge skill di daftar lowongan terisi).
6. **Endpoint & hook frontend baru:** `analysisApi.matches()`, `analysisApi.recommendations()`, `rpsApi.updateMetadata/startExtraction/competencies/createCompetency/updateCompetency/confirmReview`, `industryApi.createJob`; hook `useAnalysis`, `useAnalysisMatches`, `useAnalysisRecommendations`, `useRpsDetail`, `useCompetenciesList`, dll.
7. **Catatan untuk B (masih terbuka):** `RecommendationResource` belum menyertakan lowongan asal (`source_jobs`) sehingga subtitle *"Diminta di N lowongan: PT Contoh — Frontend Developer"* belum bisa ditampilkan (lihat `REQ-C-005`). Saat ini hanya `reason` yang tampil.

Breaking change: tidak.

#### HO-C-004 · 2026-11-13 · T2.6 & T8.1 (Dashboard & Upload RPS) · untuk: A, B
1. **Dashboard & Hero Dropzone Selesai:**
   - Halaman `/dashboard` diimplementasikan sesuai frame `03 Dashboard — Desktop` dan `03 Dashboard — Mobile`.
   - Mengintegrasikan dropzone berkas PDF RPS langsung di Hero Section Dashboard (route terpisah `/rps/upload` telah dibersihkan).
   - Penanganan berkas: validasi ekstensi PDF & batas 10 MB, pemanggilan `POST /api/rps`, penanganan respons `202 Accepted` (navigasi ke `/rps/:id`) dan `409 Conflict` (notifikasi duplikat + tawaran membuka dokumen lama).
   - Seluruh data bersifat riil dari API (`rpsList`, `analysesList`, `careersList`, `jobsList`) tanpa data dummy hardcoded.
2. **Kualitas & Pengujian:**
   - Unit test `DashboardPage.test.tsx` mencakup skenario rendering dan unggah berkas. Total 13 tests lulus, typecheck dan build lolos.

Breaking change: tidak.

#### HO-C-003 · 2026-11-13 · T1.2 & Desain Audit · untuk: A, B
1. **Halaman Auth (T1.2) Selesai:**
   - `/login` dan `/register` diimplementasikan sesuai frame `01 Login` dan `02 Register` di `docs/dihsain.pen` (Desktop 1280 & Mobile 375).
   - Brand tokens (`#9A0002`, `#201B19`, `#D9C3B8`, `#B3261E`, dll.) dan font Google (`Plus Jakarta Sans Variable` untuk UI, `DM Serif Display` untuk judul) telah dibundel lokal.
   - Komponen UI `Input`, `Label`, dan `BrandLogo` telah ditambahkan di `frontend/src/components/`.
   - Typecheck, build, dan 11 unit tests lulus.
2. **Sinkronisasi Tipe DTO API:**
   - `frontend/src/types/api.ts` telah diperbarui lengkap mencakup tipe Enums, Competencies, Careers, Job Postings, Analyses, Skill Matches, dan Recommendations.
3. **Audit Kesenjangan Desain vs Backend:**
   - Telah dilakukan perbandingan menyeluruh antara 20 frame desain (`docs/dihsain.pen`) dengan implementasi API & PostgreSQL backend. Rincian ada di `docs/Analisis.md` dan `design/docs/PERBEDAAN_DESAIN_DAN_API.md`. Poin yang membutuhkan penyesuaian backend dirangkum pada Permintaan `REQ-C-003` s/d `REQ-C-006` di bawah.

Breaking change: tidak.

#### HO-C-001 · 2026-11-12 · T0.1 (FE) · untuk: A, B
Scaffold frontend ada di `frontend/` (belum di-commit, untracked). Struktur sesuai `docs/FRONTEND.md section 4`:
- `src/main.tsx` (QueryClientProvider), `src/App.tsx` (RouterProvider), `src/routes/index.tsx` (7 route sesuai `FRONTEND.md section 2` + `RequireAuth`).
- `src/lib/api/client.ts` — fetch `credentials: 'include'`, header `Accept: application/json`, ambil cookie `/sanctum/csrf-cookie` sekali lalu baca `XSRF-TOKEN` **setiap** request tulis (token di-regenerasi saat session berubah → token lama = 419).
- `src/lib/api/endpoints.ts` — fungsi endpoint auth/rps/industry. `src/lib/query/{keys,hooks}.ts` — query keys + hook `useMe`/`useLogin`/`useLogout`.
- `src/types/api.ts` — tipe DTO diturunkan dari `docs/API.md` (bagian yang sudah ada).
- `src/lib/copy.ts` — teks UI Indonesia (aturan kata "tercakup dalam RPS" / "belum ditemukan dalam RPS").
- `src/styles/index.css` — token shadcn (varian Radix di-vendor ke `src/styles/shadcn.css` agar build tak bergantung paket CLI `shadcn`).

Env: `frontend/.env.example` → `VITE_API_URL=http://localhost:8000/api` (default di kode bila kosong).

Penting backend (untuk A):
1. **Endpoint tanpa header `Accept: application/json` balas 500, bukan 401.** `GET /api/auth/me` dari browser tanpa header itu → `Route [login] not defined.` (Laravel coba redirect ke route `login` yang tidak ada). Dengan `Accept: application/json` → 401 `{"message":"Silakan masuk terlebih dahulu."}`. Bukan bug frontend, tapi `bootstrap/app.php` bisa dicek: unauthorized di luar `api/*` butuh handling.
2. CORS + cookie Sanctum **sudah terverifikasi** dari browser (origin `http://localhost:5173`): register 201, login 200, `/api/auth/me` 200, 401 saat logout. `SESSION_DOMAIN=null` di `.env` dipakai — cookie host-only, jalan di `localhost` maupun `127.0.0.1`.

Perintah: `npm run dev`, `npm run lint`, `npm run typecheck`, `npm test`, `npm run build` — semua lulus. `npm audit` bersih.

Breaking change: tidak.

#### HO-C-002 · 2026-11-12 · T0.1 (FE) · untuk: C (catatan untuk diri sendiri)
`docs/FRONTEND_FRAMES.md` (draft pribadi, belum kontrak tim) still punya 2 ketidaksesuaian dengan `docs/FRONTEND.md section 2` yang belum diputuskan — form metadata (upload vs Tahap 0 `/rps/:id`) dan route `/profile`. Scaffold saat ini mengikuti `docs/FRONTEND.md section 2` (7 route, tanpa `/profile`). Kalau blueprint yang jadi acuan, route perlu ditambah.

## Permintaan

#### REQ-C-001 · 2026-11-12 · → A · status: open
Laravel balas **500 `Route [login] not defined.`** untuk request `api/*` tanpa header `Accept: application/json` (mis. redirect `Authenticate` middleware). Mohon tambahkan named route `login` atau exception handling di `bootstrap/app.php` supaya unauthorized API selalu JSON 401.
Kapan: sebelum merge point #1 (T1.2 butuh guard sesi dari browser).

#### REQ-C-002 · 2026-11-12 · → A, B · status: closed
Terverifikasi dari kode backend (`app/Modules/Rps/Http/Resources/RpsDocumentResource.php` baris 36–45): nama field draft persis `code`, `name`, `credits`, `semester`, `study_program` — cocok dengan `docs/API.md section 3` yang menyebut "lima field nullable". Tipe `RpsMetadataDraft` sudah dibuat di `frontend/src/types/api.ts`. Tidak perlu perubahan backend; `docs/API.md` boleh menyebut nama fieldnya eksplisit di PR berikutnya (non-blocking).

#### REQ-C-003 · 2026-11-13 · → A · status: open
**Ekspos `file_size` pada `RpsDocumentResource`:**
Desain frame `04 Detail RPS — Tahap 0` menampilkan ukuran file (misal: "18 halaman · 2,4 MB"). Kolom `file_size` (integer byte) sudah ada di tabel DB `rps_documents`, mohon ditambahkan `'file_size' => $this->file_size` ke `app/Modules/Rps/Http/Resources/RpsDocumentResource.php`.
Kapan: sebelum T2.6/T3.5.

#### REQ-C-004 · 2026-11-13 · → B · status: closed (diterapkan sendiri)
**Eager-load `jobSkills.skillEntry` pada list lowongan (`GET /api/jobs`):**
Desain frame `05 Pemetaan Industri` menampilkan badge skill pada kartu lowongan (*React*, *TypeScript*, *CSS*) dan membutuhkan data skill untuk kalkulasi Step 3 (Pratinjau pemetaan awal). Perubahan sudah diterapkan langsung oleh C di `app/Modules/Industry/Http/Controllers/JobPostingController.php` (`with(['career', 'jobSkills.skillEntry'])`) agar T5.4 tidak tertahan. B hanya perlu review saat merge.

#### REQ-C-005 · 2026-11-13 · → B · status: open
**Sertakan lowongan asal di `RecommendationResource`:**
Desain frame `06 Rekomendasi Belajar` menampilkan asal lowongan pada kartu rekomendasi (*"Diminta di 2 lowongan: PT Contoh — Frontend Developer"*). Saat ini `RecommendationResource` tidak memuat nama perusahaan atau judul posisi. Mohon sertakan ringkasan lowongan asal (misal `source_jobs: [{ company, title }]`) yang diambil via relasi `skillMatch.source_job_skill_ids`.
Kapan: sebelum T7.4.

#### REQ-C-006 · 2026-11-13 · → A, B · status: open
**Kebutuhan Skema & Endpoint Skill Pribadi (T7.5 & `07 Profil`):**
Desain `07 Profil` & `06 Rekomendasi Belajar` membutuhkan kelola skill pribadi dengan fitur:
1. Kolom `level` (Beginner, Intermediate, Advanced) dan `reported_as` (teks masukan asli sebelum normalisasi).
2. Tabel `user_skills` (`id`, `user_id`, `skill_id`, `reported_as`, `level`, timestamps).
3. Endpoint `GET/POST/DELETE /api/me/skills` dan trigger refresh rekomendasi `POST /api/analyses/{id}/recommendations/refresh`.
Kapan: merge point T7.5.
