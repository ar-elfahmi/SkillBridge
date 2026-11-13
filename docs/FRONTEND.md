# Frontend (React + Vite)

## 1. Pendekatan desain
Modern, minimalis, responsif, **mobile-first**.
- Warna utama biru dan ungu; latar terang.
- Card sudut membulat; navigasi sederhana.
- Visualisasi data dengan chart (komponen chart shadcn/ui atau Recharts).
- Detail bukti dibuka sesuai kebutuhan (drawer/accordion).
- Aksesibilitas: status tidak hanya dibedakan warna; selalu sertakan ikon + label teks.

### Warna status (konsisten di seluruh UI)
| Status | Label UI | Warna | Ikon |
|---|---|---|---|
| `covered` | Tercakup | hijau | centang |
| `partial` | Sebagian | kuning/amber | setengah/lingkaran |
| `missing` | Belum ditemukan di RPS | abu gelap / merah lembut | tanda minus/silang |

### Aturan kata-kata
- Gunakan: "Tercakup dalam RPS", "Belum ditemukan dalam RPS".
- Jangan gunakan: "Anda belum menguasai", "skor kemampuan", "persentase penguasaan".
- Tampilkan keterangan di halaman hasil: *"Hasil menunjukkan cakupan dokumen RPS, bukan kemampuan aktual mahasiswa."*
- Persentase cakupan diberi label "Cakupan skill dalam RPS". Skor similarity (bila ditampilkan) berlabel "Kemiripan semantik" di panel detail, bukan di ringkasan.
- Data simulasi/contoh harus berlabel "Data contoh".

## 2. Struktur halaman (React Router)
| Route | Halaman | Isi |
|---|---|---|
| `/login`, `/register` | Auth | form (tanpa pilihan role; semua pengguna mahasiswa) |
| `/` → `/dashboard` | Beranda | Hero Dropzone unggah PDF RPS, kartu metrik, daftar RPS **milik pengguna** + progress cakupan, sidebar ringkasan portofolio |
| `/rps/:id` | Detail RPS | Stepper 3 tahap. **Tahap 0:** panel koreksi metadata MK (nama, kode, SKS, semester, prodi) + tombol konfirmasi & mulai ekstraksi. **Tahap 1:** counter status, filter, tabel kompetensi + bukti + badge verifikasi, **CompetencyReviewTable** (setujui / edit / tolak / tambah) dan tombol konfirmasi hasil ekstraksi |
| `/analyses/new?rps={id}` | 3. Pemetaan Industri | pilih profesi, daftar lowongan (perusahaan, posisi, skill, sumber), panel detail lowongan, form tambah lowongan manual, pratinjau pemetaan awal, tombol "Mulai Analisis" |
| `/analyses/:id` | 4. Hasil Analisis | tab: Ringkasan (CoverageSummary), Skill Gap (SkillGapTable + EvidenceDrawer), Rekomendasi Belajar |

**Catatan implementasi:** unggah RPS tidak punya route sendiri — dropzone berada di hero `/dashboard` dan langsung mengarah ke `/rps/:id` setelah 202. Route `/rps/upload` dihapus.

Rekomendasi Belajar ditampilkan menonjol pada hasil.

### Halaman hasil — komponen
- **CoverageSummary:** kartu jumlah covered/partial/missing + persentase cakupan + donut/bar chart.
- **SkillGapTable:** kolom skill, status, diminta di N lowongan, kompetensi RPS terkait; filter status; klik baris → **EvidenceDrawer**.
- **EvidenceDrawer:** kutipan RPS (sumber, minggu, halaman, badge `verified/unverified`), kutipan lowongan + perusahaan + tautan sumber, alasan klasifikasi, kemiripan semantik (opsional).
- **LearningRecommendationList:** kartu per skill: topik, alasan, prioritas, kata kunci belajar.
- **SelfDeclaredSkillAction:** tombol "Sudah saya pelajari di luar RPS" pada baris SkillGapTable (`missing`/`partial`) dan pada kartu LearningRecommendationList. Klik → `POST /api/me/skills`, lalu banner "Rekomendasi belum diperbarui" dengan tombol "Perbarui rekomendasi" (`POST .../recommendations/refresh`, polling).
- **SelfDeclaredSkillsPanel:** di tab Rekomendasi Belajar, bagian "Dipelajari mandiri (dilaporkan pengguna)": daftar skill, input bebas untuk menambah skill di luar daftar, tombol hapus untuk koreksi. Keterangan: "Berdasarkan laporan Anda; tidak diverifikasi dan tidak memengaruhi Cakupan skill dalam RPS." Penanda kecil pada baris Skill Gap bila `self_declared = true`; status `missing`/`partial` tidak diubah.
- **DisclaimerBanner:** batasan analisis.

## 3. Status & polling
- Gunakan TanStack Query. Polling (`refetchInterval` 2–3 detik) selama status `pending/processing/queued`; berhenti saat `completed/failed`.
- Tampilkan stepper 4 tahap + status tiap job. Pesan gagal ramah (`error_message`), tombol coba lagi / unggah ulang.
- Status `needs_ocr`: tampilkan penjelasan PDF tampak hasil scan dan sarankan PDF digital.

## 4. Struktur proyek
```
frontend/src/
├── main.tsx, App.tsx    # entry + provider (QueryClient, Router)
├── routes/              # definisi route + halaman (React Router)
├── components/
│   ├── ui/              # shadcn/ui
│   ├── rps/ analysis/ jobs/ common/
├── lib/
│   ├── api/             # fetch client + endpoint functions (typed)
│   ├── query/           # query keys, hooks
│   └── utils.ts
├── types/               # tipe DTO sesuai docs/API.md
└── styles/
```

## 5. API client & auth
- Base URL dari `VITE_API_URL` (kosong/relatif `/api` bila Nginx merutekan di domain yang sama). Semua variabel `VITE_*` terekspos ke browser: jangan taruh rahasia di sana. `fetch` dengan `credentials: "include"`; ambil CSRF cookie (`/sanctum/csrf-cookie`) sebelum login/POST pertama; kirim header `X-XSRF-TOKEN`.
- Tidak ada API key AI di frontend.
- Route guard (komponen `RequireAuth`): redirect ke `/login` bila `GET /api/auth/me` 401.
- Tipe TypeScript untuk setiap respons mengikuti `docs/API.md`; validasi runtime opsional dengan zod.

## 6. Upload
- Validasi sisi klien (tipe PDF, ukuran) hanya sebagai UX; server tetap validasi.
- Progress upload, tangani 409 (duplikat → tawarkan membuka dokumen yang ada), 413, 422.

## 7. Responsif
- Mobile: tabel skill gap menjadi daftar kartu; EvidenceDrawer menjadi bottom sheet.
- Desktop: tabel penuh, drawer samping.
- Uji lebar 375px, 768px, 1280px.

## 8. Kualitas
- `npm run lint`, `npm run typecheck`, `npm test` harus lulus.
- State loading/empty/error untuk setiap daftar.
- Semua teks UI bahasa Indonesia; simpan string di satu tempat (`lib/copy.ts`) agar konsisten.
