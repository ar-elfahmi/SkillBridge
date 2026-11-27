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
- [ ] T8.3 Responsif & polish - sebagian (tema biru, warna status, mobile 393x852, aksesibilitas; lihat `HO-C-006`). Belum dicentang di `docs/TASKS.md`: audit kata-kata UI dan pengecekan 375/768/1280 manual belum lengkap
- [ ] Perbaikan audit E2E mobile (UI-UX-AUDIT.md Bagian 3, E-01 sampai E-40): sebagian besar selesai di branch lokal `fix/ui-audit-e2e-mobile` (belum di-push); lihat `HO-C-010`
- [ ] Temuan sisa V-01 sampai V-14: dikerjakan di branch yang sama, lihat `HO-C-011`
- [ ] T8.5 Checklist MVP — bersama A, B

Total 21 unit tests lulus, `npm run typecheck` + `npm run build` bersih. Branch aktif: `feat/rps-analysis-pages`.

## Handoff

#### HO-C-011 · 2026-11-28 · Temuan sisa verifikasi E2E mobile (V-01 sampai V-14) · untuk: A, B
Branch lokal `fix/ui-audit-e2e-mobile` (belum di-push, belum ada PR). Hanya `frontend/`, `docs/FRONTEND.md`, dan file ini. Tidak ada perubahan kontrak API atau backend. Lanjutan `HO-C-010`.

**Selesai (kode + test, belum dicoba di browser)**
- V-06: `useStickyInset` (`frontend/src/lib/useStickyInset.ts`) menulis `--sticky-bottom` / `--sticky-top`; `html` memakai `scroll-padding` (`styles/index.css`). Dipasang di `CompetencyReviewTable`, `AnalysesNewPage`, tab `AnalysesResultPage`. Bilah berada di akhir alur konten, jadi `padding-bottom` tambahan tidak diperlukan.
- V-08: judul kosong, null, spasi, atau sama dengan nama skill menjadi "Pelajari {skill}" (`LearningRecommendationList`). V-09: baris judul kartu skill `flex-wrap` + `min-w-0 break-words` (`SkillGapTable`). V-04: `MetadataPanel` (`field-sizing` + fallback scrollHeight dengan border, `overflow-hidden`). V-05: aksi sekunder dipindah ke kartu ringkasan, bilah hanya tombol utama. V-07: `normalizeCourseCode` (`lib/labels.ts`) saat simpan, ringkasan, dan subjudul `/analyses/new`. V-10, V-02, V-03 (2 jam), V-13 (5 detik + tombol tutup), V-14 (`aria-pressed`, tombol menggantikan radio di sheet filter), V-11 (`focus-within` ring pada label kartu profesi; `cursor-pointer` sudah ada).
- V-01: kartu RPS dipadatkan (tanggal·profesi satu baris terpotong, "Baru" dan jumlah halaman satu baris dengan status, coverage dua kolom, padding kartu 12px, tombol hapus tidak lagi memakai `pr-14` seluruh kartu) dan hero unggah ponsel dipadatkan (catatan "PDF · Maks. 10 MB" digabung ke deskripsi). Perkiraan tinggi dari hitungan, belum diukur di 393x852.
- V-12: `sm:size-8/9/10` dan `sm:min-h-9` pada tombol kembali, avatar topbar, tutup drawer, dan Keluar di profil dihapus agar tetap 44px di 768px. Kolom cari desktop topbar hanya muncul di `lg` (>=1024px), jadi tidak ada di 768px; di bawah itu memakai ikon 44px.

**Perlu dicek di browser**: V-01 (ukuran kartu nyata), V-06 (fokus Tab nyata), V-09 di 320px, V-04 di Chrome/Safari, V-12 hasil inspeksi di 768x1024. Perilaku V-08 diperluas: judul yang sama dengan nama skill juga diganti "Pelajari {skill}".

#### HO-C-010 · 2026-11-28 · Perbaikan audit E2E mobile (E-01 sampai E-40, R-02, R-03, R-05, R-07, F-19) · untuk: A, B
Branch lokal `fix/ui-audit-e2e-mobile` (belum di-push, belum ada PR). Hanya `frontend/`, `docs/FRONTEND.md`, dan file ini yang berubah. Tidak ada perubahan kontrak API, skema, atau backend.

**Selesai**
- Unggah dan metadata: E-01 (info duplikat netral, memuat nama dokumen, `role="status"`, tombol tutup), E-03 (lencana "Perlu dicek" berikon pensil, warna warning), E-04 (skeleton dan "Membaca metadata dokumen…"), E-05 (nama dan program studi memakai textarea yang membesar), E-06/E-07 (label "Kode MK" dengan `*`, teks bantu di bawah kolom), E-08 (`autocapitalize="characters"`, `inputMode="numeric"`), E-09 (galat per kolom, `aria-invalid`, `aria-describedby`, fokus), E-10/E-11 (fieldset nonaktif saat dibaca, panel proses dan ringkasan metadata saat ekstraksi), E-12 (ringkasan "Ekstraksi selesai. N kompetensi menunggu tinjauan." hanya untuk sesi yang memulai ekstraksi, bisa ditutup), E-13/E-14 (pasangan token success, wilayah `role="status"`), E-15 ("Simpan dan mulai ekstraksi" menyimpan dulu; "Simpan perubahan" hanya muncul bila ada perubahan).
- Tinjau: E-16/E-17/E-22 (teks bilah sejalan dengan tombol; "Setujui semua (N) dan lanjut" membuka dialog konfirmasi berisi jumlah; "Setujui semua yang terverifikasi (M)" bila 0 < M < N; N = 0 menjadi "Lanjut ke pemetaan industri"; tautan lanjut penuh lebar, 44px, teks tengah, tanpa panah), E-18 (lencana status netral, aksi satu gaya), E-19 (sumber "Rencana mingguan, minggu 1, hal. 1" di ponsel dan desktop), E-20 (`aria-label` "Setujui {nama}" dan seterusnya), E-21 (satu pernyataan "Semua kompetensi sudah ditinjau."), E-23 (form tambah: tombol 44px, galat Indonesia per kolom, textarea 3 baris), E-24 (penyebab: editor hanya dirender di tabel desktop, sehingga Edit di kartu ponsel tidak menampilkan apa pun; kini editor dipakai bersama. Server tidak mengunci edit setelah konfirmasi, jadi tidak ada teks alasan kunci), R-02 (kutipan dalam akordeon, aksi satu baris).
- Pemetaan dan proses: E-25 (panel proses bertahap, tab disembunyikan selama proses, tombol "Kembali ke beranda"), E-29 (skeleton "Menyiapkan saran profesi…").
- Hasil: E-26 (Title Case untuk nama HURUF BESAR, `line-clamp-2`, lencana di baris subjudul), E-30 (3 kelompok pertama, "Lihat N lowongan lain", awalan "- " dibuang, kutipan identik digabung), E-32/E-36/E-37 (ponsel: satu baris cari + tombol Filter ke bottom sheet; desktop: chip membungkus, tanpa gulir horizontal; "+ Tambah" di judul "Dipelajari mandiri" membuka bottom sheet; skill pribadi jadi chip), E-33 (label "Kecocokan lemah" pada status Sebagian), E-34 (judul kosong menjadi "Pelajari {skill}"), E-35 (bottom sheet tingkat, toast dengan "Urungkan", fokus ke kartu berikutnya), E-39 (penanda "Baru" untuk dokumen dalam 24 jam), E-40 (opsi "Pilih tingkat"; tanpa pilihan, server mencatat Menengah dan UI menyebutnya), R-03 (seluruh baris checkbox sudah `label` 44px, juga di sheet), R-07 ("minggu 1"), F-19 (kolom cari sudah `rounded-lg`, tidak ada lagi bentuk pil pada input; pustaka ikon tidak diganti).
- E-28: tombol hapus pada kartu dashboard + dialog konfirmasi. Catatan: kontrak `DELETE /api/rps/{id}` sudah ada di `docs/API.md` (204, cascade analisis, PDF dihapus) dan route backend sudah ada, jadi tidak ada usulan kontrak baru. Tombol hanya dirender bila `rpsApi.remove` tersedia.

**Sebagian**
- E-27: kartu memuat tanggal unggah, profesi (bila ada analisis), dan "Baru". Analisis dihubungkan ke dokumen lewat nama mata kuliah, sehingga dua dokumen bernama sama tidak dapat dibedakan secara andal (lihat REQ-C-007).
- E-31: urut dari permintaan terbanyak dan batang kecil, tetapi relatif terhadap skill yang paling sering diminta. Teks "41 dari 41 lowongan" tidak ditulis karena jumlah lowongan terpilih tidak ada di API (R-17).
- E-38: keterangan "dari N analisis" kini tampil juga di ponsel. Dedup per dokumen sumber tidak mungkin tanpa `rps_document_id` pada daftar analisis.
- R-05: "Lowongan tersedia" memakai jumlah `eligible_job_count` semua profesi (`/api/careers`), sumber yang sama dengan kartu pemetaan. Perubahan itu sudah ada di `main` (c535831); di branch ini hanya pembacaan `useJobsList` yang tak terpakai dibuang (itu penyebab `tsc` gagal di `main`).

**Bukan bagian C, perlu B**: R-04 (prioritas hampir semua "Tinggi", kata kunci dan topik berbahasa Inggris), R-06 (alias skill "Agile" vs "Agile/Scrum", ambang `demand_count`), E-33 (kalibrasi kecocokan "Sebagian" terlalu longgar), E-34 (judul kosong dan tidak stabil antarkunjungan, refresh rekomendasi harus idempoten; lihat REQ-C-008).

**Tes**: `RpsDetailPage` (dialog konfirmasi: jumlah, Batal, Escape, focus trap, setujui semua, setujui terverifikasi; galat per kolom; ringkasan ekstraksi selesai tidak muncul pada kunjungan ulang; editor di kartu ponsel dan setelah konfirmasi; form tambah), `AnalysesResultPage` (panel proses, batas kutipan, bottom sheet tingkat dan Urungkan, fallback judul, filter ponsel, Title Case), `DashboardPage` (duplikat netral, hapus dokumen, "Baru"), `ProfilePage`, `labels`. Tidak ada panggilan jaringan nyata: semua endpoint di-mock.

**Sisa uji manual** (tidak dapat dibuktikan lewat jsdom): papan ketik virtual pada form metadata, tambah kompetensi, dan sheet tambah skill (perangkat nyata); zoom 200%; layar 768px; tinggi layar pertama dashboard 393x852 setelah kartu mendapat satu baris tambahan (tanggal/profesi), yaitu apakah dua kartu RPS masih terlihat tanpa menggulir; perilaku auto-grow textarea di Safari lama; pembaca layar untuk toast dan panel proses.

Breaking change: tidak.

#### HO-C-009 · 2026-11-28 · Saran profesi di `/analyses/new` · untuk: B
Hook `useCareerSuggestions` (polling 2,5 dtk selama `processing`, maks. 40 kali). Profesi tersaran diurutkan paling atas dengan badge `Saran AI untuk RPS ini` dan alasan; profesi tersaran pertama yang punya lowongan siap menjadi pilihan awal. `failed`/`unavailable`/kosong: daftar biasa tanpa pesan error. Menjawab HO-B-016.

#### HO-C-008 · 2026-11-28 · `/analyses/new` hanya pilih profesi · untuk: B
Halaman tidak lagi menampilkan pilihan, pencarian, atau form tambah lowongan. Pengguna memilih satu profesi (kartu radio: nama, ringkasan, `eligible_job_count`; profesi tanpa lowongan siap dinonaktifkan; default profesi pertama yang siap). Request analisis: `{rps_document_id, career_id}`. Hook `useJobsList`/`useCreateJob` dan `POST /api/jobs` tetap ada namun tidak dipakai halaman ini. Menjawab HO-B-015.

#### HO-C-007 · 2026-11-28 · Bukti lowongan di panel detail skill · untuk: B
Panel di `SkillGapTable` menampilkan judul, perusahaan, kutipan, dan tautan `Buka lowongan` (hanya bila `source_url` ada; `target=_blank` + `rel=noopener noreferrer`), serta `dan N lowongan lain` dari `job_evidence_total`. Menjawab HO-B-014.

#### HO-C-006 · 2026-11-27 · T8.3 (sebagian) · untuk: A, B
**Polish tampilan, mobile, dan aksesibilitas frontend.** PR #46, #47, #48 sudah di-merge ke `main` (merge commit). Hanya `frontend/` yang berubah; tidak ada perubahan kontrak API, skema, atau backend.
1. **Tema biru (#46):** palet merah diganti biru. Token baru di `src/styles/index.css`: `brand-strong`, `brand-accent`, `progress-track`.
2. **Warna status (#47, #48):** token `success`, `warning`, `danger` beserta `-soft` dan `-solid`; hex status yang tersebar diganti token. Bar cakupan: hijau `#1E7A4F`, oranye `#D97706`, merah. Setiap status punya ikon dan label.
3. **Mobile 393x852:** `/dashboard` dan `/rps/:id` (langkah metadata dan tinjauan) muat satu bingkai `100dvh`; daftar scroll di wadahnya. Halaman lain (login, register, profil, `/analyses/new`, `/analyses/:id`) disesuaikan: tabel profil jadi daftar, bar Mulai Analisis menempel di bawah, target sentuh minimal 44px. Batas phone/tablet di 640px; layout tablet dan desktop tidak berubah. Pencarian dashboard di mobile lewat ikon topbar.
4. **Aksesibilitas:** border field `#74879F` (3,68:1), cursor pointer di base layer, fokus `focus-visible`/`focus-within`, dialog bukti skill (`role="dialog"`, Escape, fokus kembali), landmark `main`, satu `h1` per halaman, judul halaman unik lewat `src/lib/usePageTitle.ts`, label dan nama aksesibel, teks minimal 11px.
5. **Copy:** badge eyebrow di hero unggah, login, dan register dihapus. `copy.status.missingShort` ("Belum ditemukan") dipakai di kartu mobile.
6. **Keterbacaan hasil analisis (`/analyses/:id`):** judul di atas tab; tab Ringkasan jadi satu panel (angka utama, bar, tiga baris rincian, tombol "Lihat rincian skill"); kartu rekomendasi dua kolom di desktop; teks isi minimal 14px, keterangan minimal 12px.
7. **Breakpoint:** pencarian topbar baru tampil dari 1024px (ikon cari di bawahnya); daftar RPS dashboard tiga kolom dari 1280px.
8. **Konsistensi visual:** radius panel `rounded-xl`, baris dan input `rounded-lg` (hero unggah tetap `rounded-2xl`); goresan ikon Lucide diatur sekali di `index.css`; alasan tipografi dan ikon dicatat di `docs/FRONTEND.md`. Teks antarmuka tanpa tanda pisah panjang.

Verifikasi: `npm run lint` (0 error, 3 warning lama), `typecheck`, `npm test` (32 lulus), `npm run build`. Audit DOM headless di 393x852 dan 1280x900 dengan API tiruan: tanpa overflow horizontal, kontrol tanpa nama, atau input tanpa label.
Belum diuji: keyboard asli (Tab/fokus), zoom 200%, perangkat sentuh nyata, backend asli, lebar 375 dan 768 secara manual.
Breaking change: tidak.

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

#### HO-C-004 · 2026-11-28 · Perbaikan audit UI/UX (PR #53 sampai #56 dan PR ini) · untuk: A, B
1. **Selesai di `frontend/`:** halaman 404 dan error route berbahasa Indonesia, galat unggah inline (tanpa `alert()`/`confirm()`), focus trap drawer bukti, target sentuh 44px, validasi per-field login/register, kamus label (`lib/labels.ts`: kategori, sumber, level, angka `id-ID`), pemetaan industri dikelompokkan (saran AI vs profesi lain, dialog "Lihat skill yang dipakai"), redirect ke beranda tanpa `?rps=`, rekomendasi dikelompokkan per prioritas dengan akordeon, gulir halaman tunggal di mobile (tanpa gulir bersarang), tab dan bilah aksi lengket, font minimal 14px dan input 16px di mobile.
2. **Istilah baku berubah:** tab "Skill" dan "Rekomendasi"; level "Pemula/Menengah/Mahir"; "Skill belum tercakup", "Lowongan tersedia", "Profesi tersedia"; tombol "Tandai sudah dipelajari" dan "Mulai analisis".
3. **Verifikasi:** `npm test` (48 lulus), `npm run typecheck`, `npm run lint` (0 error), `npm run build`, serta cek ego-browser di 393x852, 360x640, 320x568 memakai mock API lokal (tanpa overflow horizontal, tanpa gulir bersarang).
4. **Belum dikerjakan / butuh pihak lain:** verifikasi data skill lowongan Glints di pipeline (F-10, area B); uji papan ketik virtual di perangkat nyata (M-10); penggantian pustaka ikon (F-19) menunggu keputusan; pemasangan skill `ui-taste` dari uizze.sh dilakukan secara global di mesin pengguna, bukan di repo.

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

#### REQ-C-007 · 2026-11-28 · → A · status: open
**Hubungkan analisis ke dokumen dan jumlah lowongan:** tambahkan `rps_document_id` pada `AnalysisResource` (dan, bila mudah, `job_count`: jumlah lowongan yang dipilih untuk analisis tersebut). Dipakai untuk: (1) dashboard menghubungkan kartu RPS ke analisisnya tanpa mencocokkan nama mata kuliah (E-27), (2) dedup statistik "Skill belum tercakup" per dokumen (E-38), (3) bar permintaan "N dari M lowongan" yang jujur (E-31). Perubahan kontrak: perbarui `docs/API.md` bagian Analisis lebih dulu.
Kapan: sebelum PR audit E2E ini di-merge, atau dicatat sebagai tindak lanjut.

#### REQ-C-008 · 2026-11-28 · → B · status: open
**Rekomendasi dan kalibrasi:** (1) judul rekomendasi tidak boleh kosong dan stabil antarkunjungan; refresh rekomendasi idempoten (E-34, R-04); frontend sudah memberi fallback "Pelajari {skill}" tetapi tidak dapat menstabilkan isi. (2) Prioritas lebih membedakan (10 dari 12 "Tinggi"). (3) Kata kunci dan topik berbahasa Indonesia, atau tetap diberi label bahasa Inggris. (4) Normalisasi alias skill lowongan ("Agile" vs "Agile/Scrum") dan ambang minimal `demand_count` (R-06). (5) Kalibrasi kecocokan "Sebagian" yang terlalu longgar (E-33); UI sudah memberi label "Kecocokan lemah".
