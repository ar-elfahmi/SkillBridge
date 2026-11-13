# Dokumentasi Task Frontend — Orang C

Dokumen kerja Orang C (Frontend & UX). Konsolidasi task frontend dari `docs/TASKS.md`, kontrak endpoint dari `docs/API.md`, dan desain dari `docs/dihsain.pen`. Detail komponen & struktur: `docs/FRONTEND.md`. Aturan kata UI: `docs/FRONTEND.md section 1`.

**Area milik:** seluruh `frontend/`, `docs/FRONTEND.md`, file ini.

## Definisi selesai (DoD)

Semua task dianggap selesai saat:

- [ ] `npm run lint && npm run typecheck && npm test` lulus
- [ ] Ada test untuk logika non-trivial (form, polling, error state)
- [ ] Teks UI bahasa Indonesia, dari `src/lib/copy.ts`
- [ ] Aturan kata dipatuhi: "tercakup dalam RPS" / "belum ditemukan dalam RPS" (bukan "belum menguasai")
- [ ] Data contoh berlabel "Data contoh"
- [ ] Session/query pakai TanStack Query; tidak ada `any`
- [ ] Akun di-commit hanya file dalam lingkup task (`git diff --stat`)
- [ ] Task dicentang di `docs/TASKS.md`

## Urutan kerja

| # | Task | Route | Prioritas |
|---|---|---|---|
| 1 | T1.2 Login/Register + guard | `/login`, `/register` | ✅ selesai |
| 2 | T2.6 Upload RPS (Hero Dropzone) | `/dashboard` | ✅ selesai |
| 3 | T8.1 Dashboard Riwayat | `/dashboard` | ✅ selesai (FE) |
| 4 | T3.5 Detail RPS & kompetensi | `/rps/:id` | ✅ selesai |
| 5 | T3.6 Review kompetensi (bersama) | `/rps/:id` | ✅ selesai |
| 6 | T5.4 Profesi & lowongan | `/analyses/new` (+ `/analyses/:id` untuk preview) | ✅ selesai |
| 7 | T6.5 Analisis + Ringkasan/Skill Gap | `/analyses/new`, `/analyses/:id` | ✅ selesai |
| 8 | T7.4 Rekomendasi + Disclaimer | `/analyses/:id` | ✅ selesai |
| 9 | T7.5 Skill pribadi (bersama) | `/analyses/:id` | demo inti |
| 10 | T8.3 Responsif & polish | seluruh halaman | akhir |
| 11 | T8.5 Checklist MVP (bersama) | — | akhir |

---

## T1.2 — Login/Register + guard sesi ✅

- **Route:** `/login`, `/register`
- **Desain:** `01 Login — Desktop/Mobile`, `02 Register — Desktop/Mobile`
- **Endpoint:** `POST /api/auth/login`, `POST /api/auth/register`, `GET /api/auth/me`, `POST /api/auth/logout`
- **Komponen:** `LoginPage`, `RegisterPage`, `RequireAuth`, `BrandLogo`, `Button`, `Input`, `Label`
- **Status:** selesai, ter-commit di branch `feat/T1.2-auth-frontend`

## T2.6 — Upload RPS (Hero Dropzone di Dashboard)

- **Route:** `/dashboard` (terintegrasi di hero section Dashboard, route terpisah `/rps/upload` dihapus)
- **Desain:** `03 Dashboard — Desktop/Mobile` (komponen `Upload RPS Hero` / `Mobile Upload RPS Hero`)
- **Endpoint:** `POST /api/rps` (202), redirect ke `/rps/:id` (Tahap 0 Metadata)
- **Komponen:** dropzone PDF, progress upload, notifikasi "pemrosesan pihak ketiga" (AI)
- **Kriteria:**
  - Validasi klien: file tipe PDF, ukuran maks 10 MB
  - Tangani error: 409 (duplikat → tawarkan buka dokumen yang ada), 413, 422, 429
  - Setelah upload diterima (202) → langsung redirect ke `/rps/{id}` (Detail RPS Tahap 0) untuk review metadata AI dan ekstraksi kompetensi

## T3.5 — Detail RPS & tabel kompetensi
- **Status:** selesai — `RpsDetailPage`, `RpsHeader`, `MetadataPanel`. Stepper 3 tahap dapat diklik (bypass) untuk inspeksi desain.

- **Route:** `/rps/:id`
- **Desain:** `04 Detail RPS — Tahap 0/1 — Desktop/Mobile`
- **Endpoint:** `GET /api/rps/{id}`, `GET /api/rps/{id}/competencies`, `POST /api/rps/{id}/extract`
- **Komponen:** status proses (stepper + polling), tabel kompetensi (skill, competency, evidence + sumber, badge `verified/unverified`), editor metadata
- **Kriteria:**
  - Polling status `extraction_status` selagi `pending/processing`
  - Tiap baris kompetensi tampilkan `evidence` + `source_reference` + `page`, dengan badge verifikasi (`verified/unverified`)

## T3.6 — Review kompetensi (bersama)
- **Status:** selesai — `CompetencyReviewTable` (tabel desktop + kartu mobile), filter status, tambah/edit/tolak kompetensi, tombol konfirmasi hasil ekstraksi.

- **Route:** `/rps/:id` (tahap review)
- **Endpoint:** `PATCH /api/rps/{id}/competencies/{cid}`, `POST /api/rps/{id}/competencies`, `DELETE /api/rps/{id}/competencies/{cid}`, `POST /api/rps/{id}/confirm-review`
- **Komponen:** `CompetencyReviewTable` (benar / edit / tolak / tambah kompetensi), tombol "Konfirmasi hasil ekstraksi"
- **Kriteria:**
  - Edit/tambah memicu embedding ulang (backend)
  - Konfirmasi diblokir (422) bila masih ada `pending`
  - `origin=user` ditandai tidak terverifikasi

## T5.4 — Profesi & lowongan
- **Status:** selesai — `AnalysesNewPage` Step 1 & 2: dropdown profesi, daftar lowongan dengan badge skill, panel detail lowongan, form tambah lowongan manual. Backend `GET /api/jobs` sekarang eager-load `jobSkills.skillEntry`.

- **Route:** `/analyses/new?rps={id}`
- **Desain:** `05 Pemetaan Industri — Desktop/Mobile`
- **Endpoint:** `GET /api/careers`, `GET /api/jobs?career_id={id}`, `GET /api/jobs/{id}`, `POST /api/jobs`
- **Komponen:** pilih profesi, daftar lowongan (perusahaan, posisi, skill, sumber), form tambah lowongan manual, preview pemetaan awal, tombol "Mulai Analisis"
- **Kriteria:**
  - Lowongan contoh berlabel "Data contoh"
  - Form manual: `career_id`, `title`, `company`, `description`, `source_url` (opsional)

## T6.5 — Buat analisis + Ringkasan/Skill Gap/EvidenceDrawer
- **Status:** selesai — `AnalysesNewPage` Step 3 (pratinjau agregasi skill dari `job_skills`) + `AnalysesResultPage` tab Ringkasan (`CoverageSummary`) dan Skill Gap (`SkillGapTable` + `EvidenceDrawer`).

- **Route:** `/analyses/new?rps={id}`, `/analyses/:id`
- **Desain:** `05 Pemetaan Industri`, `06 Hasil Analisis — Ringkasan/Skill Gap`
- **Endpoint:** `POST /api/analyses` (202), `GET /api/analyses/{id}`, `GET /api/analyses/{id}/matches`
- **Komponen:** `CoverageSummary`, `SkillGapTable`, `EvidenceDrawer`
- **Kriteria:**
  - Ringkasan: kartu covered/partial/missing + persentase + donut/bar chart
  - Persentase berlabel "Cakupan skill dalam RPS"; similarity (bila ditampilkan) berlabel "Kemiripan semantik" di panel detail saja
  - SkillGapTable: kolom skill, status, `demand_count`, kompetensi terkait; filter status; klik baris → EvidenceDrawer
  - EvidenceDrawer: kutipan RPS (sumber, minggu, halaman, badge) + kutipan lowongan (perusahaan + sumber), alasan klasifikasi
  - Disertai `DisclaimerBanner`

## T7.4 — Rekomendasi + Disclaimer
- **Status:** selesai — `LearningRecommendationList`. Panel skill pribadi dirender *read-only*; tombol tambah/hapus menunggu T7.5 (backend `user_skills` belum ada).

- **Route:** `/analyses/:id` (tab "Rekomendasi Belajar")
- **Endpoint:** `GET /api/analyses/{id}/recommendations`
- **Komponen:** `LearningRecommendationList`, `DisclaimerBanner`
- **Kriteria:**
  - Kartu per skill: `title`, `reason`, `priority`, `details.topics`, `details.learning_keywords`
  - **Tanpa URL sumber belajar** (aturan CLAUDE.md #11)

## T7.5 — Skill pribadi (bersama)

- **Route:** `/analyses/:id`
- **Endpoint:** `GET/POST/DELETE /api/me/skills`, `POST /api/analyses/{id}/recommendations/refresh`
- **Komponen:** `SelfDeclaredSkillAction`, `SelfDeclaredSkillsPanel`
- **Kriteria:**
  - Tombol "Sudah saya pelajari di luar RPS" pada baris `missing/partial` dan kartu rekomendasi
  - Setelah tambah → banner "Rekomendasi belum diperbarui" + tombol "Perbarui rekomendasi" (polling refresh)
  - Panel: label "Dipelajari mandiri (dilaporkan pengguna)", keterangan "tidak diverifikasi dan tidak memengaruhi Cakupan skill dalam RPS"
  - Penanda `self_declared` pada baris Skill Gap; klasifikasi tidak berubah

## T8.1 — Dashboard/riwayat (bagian frontend)

- **Route:** `/dashboard`
- **Desain:** `03 Dashboard — Desktop/Mobile`
- **Endpoint:** `GET /api/rps` (daftar RPS), `GET /api/analyses` (riwayat)
- **Kriteria:** daftar RPS & riwayat analisis, tombol mulai; pesan error ramah (429, 5xx)

## T8.3 — Responsif & polish

- **Kriteria:**
  - Uji lebar **375px, 768px, 1280px**
  - Mobile: tabel skill gap → kartu, EvidenceDrawer → bottom sheet
  - State loading/empty/error untuk semua daftar
  - Audit kata UI menyeluruh (`docs/FRONTEND.md section 1`)

## T8.5 — Checklist MVP (bersama)

Verifikasi seluruh kriteria `docs/PRD.md section 9`; satukan dengan A & B.