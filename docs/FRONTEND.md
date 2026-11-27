# Frontend (React + Vite)

## 1. Pendekatan desain
Modern, minimalis, responsif, **mobile-first**.
- Warna utama biru (`#0066CC`) dengan aksen `#0085FF`; latar terang. Hijau, oranye, dan merah hanya untuk status.
- Card sudut membulat sedang (`rounded-xl`), baris dan input di dalamnya `rounded-lg`; hanya hero unggah yang `rounded-2xl`. Navigasi sederhana.
- Visualisasi data dengan chart (komponen chart shadcn/ui atau Recharts).
- Detail bukti dibuka sesuai kebutuhan (drawer/accordion).
- Aksesibilitas: status tidak hanya dibedakan warna; selalu sertakan ikon + label teks.

### Tipografi dan ikon
- Judul halaman memakai DM Serif Display supaya angka dan judul besar (persentase cakupan, nama mata kuliah) terbaca sebagai poin utama. Isi, tabel, dan formulir memakai Plus Jakarta Sans karena angkanya lebar sama dan terbaca jelas di ukuran kecil.
- Ukuran teks isi minimal 14px untuk konten utama dan 12px untuk keterangan. Tidak ada teks di bawah 12px.
- Ikon memakai pustaka Lucide dengan goresan lebih tebal dan ujung persegi (diatur sekali di `src/styles/index.css`) supaya serasi dengan huruf serif dan tidak tampil seperti gaya bawaan pustaka.

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
| `/rps/:id` | Detail RPS | Stepper 3 tahap. **Tahap 0:** panel koreksi metadata MK (nama, kode, SKS, semester, prodi); aksi utama "Simpan dan mulai ekstraksi" (menyimpan perubahan lebih dulu), "Simpan perubahan" sebagai sekunder hanya bila ada perubahan, dan "Mulai ekstraksi" bila tidak ada; galat per kolom; selama metadata dibaca tampil skeleton, selama ekstraksi berjalan tampil panel proses dengan ringkasan metadata. **Tahap 1:** counter status, filter, tabel (desktop) atau kartu (ponsel) kompetensi + bukti + sumber lengkap ("Rencana mingguan, minggu 1, hal. 1"), **CompetencyReviewTable** (setujui / edit / tolak / tambah) dan bilah konfirmasi: "N kompetensi belum ditinjau" dengan tombol "Setujui semua (N) dan lanjut" (membuka dialog konfirmasi) dan, bila relevan, "Setujui semua yang terverifikasi (M)"; bila N = 0, tombol "Lanjut ke pemetaan industri" |
| `/analyses/new?rps={id}` | 3. Pemetaan Industri | kartu profesi (nama, ringkasan, jumlah lowongan siap pakai, saran AI di urutan atas dengan alasan singkat dari `GET /api/rps/{id}/career-suggestions`, polling 2,5 dtk maks. 40 kali; profesi tanpa lowongan siap dinonaktifkan), catatan batas 20 skill, tombol "Mulai analisis" (profesi disarankan AI dikelompokkan di atas, profesi lain dilipat; tautan "Lihat skill yang dipakai"; tanpa `?rps=` diarahkan ke beranda) |
| `/analyses/:id` | 4. Hasil Analisis | selama `queued`/`processing`: panel proses bertahap (tanpa tab) dengan tombol "Kembali ke beranda". Setelah selesai, tab: Ringkasan (CoverageSummary), Skill (SkillGapTable + EvidenceDrawer), Rekomendasi (dikelompokkan per prioritas, kartu akordeon). Judul mata kuliah ditampilkan Title Case dan dibatasi dua baris; lencana status berada di baris subjudul |

**Catatan implementasi:** unggah RPS tidak punya route sendiri — dropzone berada di hero `/dashboard` dan langsung mengarah ke `/rps/:id` setelah 202. Route `/rps/upload` dihapus.

Rekomendasi Belajar ditampilkan menonjol pada hasil.

### Halaman hasil — komponen
- **CoverageSummary:** kartu jumlah covered/partial/missing + persentase cakupan + donut/bar chart.
- **SkillGapTable:** kolom skill, status, diminta di N lowongan (batang kecil relatif terhadap skill yang paling sering diminta; urut dari permintaan terbanyak), kompetensi RPS terkait (label "Kecocokan lemah" bila `partial`); klik baris → **EvidenceDrawer**. Di ponsel: satu baris kolom cari + tombol "Filter" yang membuka bottom sheet berisi status dan "Sembunyikan yang sudah dilaporkan"; di desktop chip status dan kotak centang tampil langsung.
- **EvidenceDrawer:** kutipan RPS (sumber, minggu, halaman, badge `verified/unverified`), bukti lowongan lewat **EvidenceJobList** (3 kelompok pertama, tombol "Lihat N lowongan lain", awalan "- " dibuang, kutipan identik digabung "Disebut serupa di N lowongan"), alasan klasifikasi, kemiripan semantik (opsional).
- **LearningRecommendationList:** kartu per skill: topik, alasan, prioritas, kata kunci belajar. Judul kosong dari AI diganti "Pelajari {skill}". "Tandai sudah dipelajari" membuka bottom sheet pilihan tingkat (Pemula/Menengah/Mahir), lalu muncul toast "{skill} ditambahkan ke skill Anda." dengan "Urungkan" dan fokus pindah ke kartu berikutnya.
- **SelfDeclaredSkillAction:** tombol "Sudah saya pelajari di luar RPS" pada baris SkillGapTable (`missing`/`partial`) dan pada kartu LearningRecommendationList. Klik → `POST /api/me/skills`, lalu banner "Rekomendasi belum diperbarui" dengan tombol "Perbarui rekomendasi" (`POST .../recommendations/refresh`, polling).
- **SelfDeclaredSkillsPanel:** di tab Rekomendasi, bagian "Dipelajari mandiri": skill tampil sebagai chip (tingkat + tombol hapus 44px), tombol "+ Tambah" di judul membuka bottom sheet (nama skill dan tingkat). Keterangan: "Berdasarkan laporan Anda; tidak diverifikasi dan tidak memengaruhi Cakupan skill dalam RPS." Penanda kecil pada baris Skill Gap bila `self_declared = true`; status `missing`/`partial` tidak diubah.
- **DisclaimerBanner:** batasan analisis.

### Pola antarmuka bersama
- **Panel proses (`ProcessPanel`):** untuk pekerjaan latar yang dipolling (ekstraksi kompetensi, analisis). Isi: spinner (berhenti pada `prefers-reduced-motion`), judul, satu kalimat keterangan, daftar langkah dengan status `selesai/sedang berjalan/menunggu` (juga untuk pembaca layar), `role="status"` dengan `aria-live="polite"`. Perkiraan waktu hanya ditulis bila terukur ("Biasanya kurang dari 1 menit" untuk ekstraksi). Langkah yang belum dimulai tidak boleh diberi status berjalan.
- **Dialog dan bottom sheet (`ModalDialog`):** satu komponen untuk dialog konfirmasi (`variant="dialog"`, di tengah) dan bottom sheet (`variant="sheet"`, menempel di bawah pada ponsel dan di tengah pada desktop). Wajib: `role="dialog"`, `aria-modal`, judul sebagai nama, focus trap, Escape dan klik latar menutup, fokus kembali ke pemicu, area sentuh 44px. Elemen dengan `data-autofocus` menerima fokus awal (pada dialog konfirmasi: tombol Batal). Dialog konfirmasi tidak memakai tombol X karena sudah punya Batal.
- **Dialog konfirmasi tindakan massal atau tak terbalik:** menyebut jumlah dan dampaknya, tombol Batal dan aksi. Dipakai pada "Setujui semua (N) dan lanjut", "Setujui semua yang terverifikasi (M)", dan hapus dokumen. Hapus dokumen tidak punya "Urungkan" karena berkas PDF ikut dihapus di server.
- **Pesan status (`StatusNotice`):** varian `success`/`error`/`info` selalu berikon dan memakai pasangan token `success-soft`/`success`, `danger-soft`/`danger`, `brand-soft`/`text-primary` (kontras terukur 7,82:1, 5,38:1, 14,12:1). Pesan yang muncul setelah aksi berada di wilayah `role="status"` yang sudah ada. Info yang bukan galat (mis. dokumen duplikat) memakai `info`, bukan merah.
- **Toast dengan aksi (`UndoToast`):** `role="status"`, hilang sendiri setelah 10 detik, ada tombol tutup dan aksi 44px.
- **Galat per kolom:** `noValidate` pada form, pesan Indonesia tepat di bawah kolom (`FieldError`), `aria-invalid`, `aria-describedby`, fokus ke kolom bermasalah pertama. Penanda wajib `*` memakai CSS pada label dan `aria-required`.
- **Bilah lengket dan fokus papan ketik (`useStickyInset`):** bilah bawah (`CompetencyReviewTable`, `/analyses/new`, hanya di bawah 640px) dan tab lengket `/analyses/:id` menulis tingginya ke `--sticky-bottom` / `--sticky-top` di `<html>`, yang dipakai `scroll-padding-bottom` / `scroll-padding-top` di `index.css`. Fokus papan ketik tidak boleh tertutup bilah (WCAG 2.2, 2.4.11). Bilah lengket langkah tinjau hanya memuat teks ringkas dan tombol utama (maks. 97px); aksi sekunder "Setujui semua yang terverifikasi (M)" berada di kartu ringkasan di atas daftar.
- **Textarea tumbuh otomatis:** `field-sizing: content` bila didukung, selain itu `scrollHeight` ditambah border; `overflow-hidden`, minimal 2 baris, maksimal 12rem.
- **Normalisasi tampilan dan data:** kode mata kuliah disimpan huruf besar (`normalizeCourseCode`); nama HURUF BESAR memakai `toDisplayTitle` di kartu dan pesan duplikat; judul rekomendasi kosong/null/sama dengan nama skill menjadi "Pelajari {skill}"; "Baru" hanya untuk dokumen yang diunggah dalam 2 jam terakhir; `aria-label` hapus dokumen memakai nama berkas; banner "Dokumen dihapus." hilang sendiri setelah 5 detik dan bisa ditutup; opsi status di sheet filter memakai `aria-pressed`.
- **Tombol hapus dokumen** hanya dirender bila `rpsApi.remove` tersedia; tanpa fungsi API, tidak ada tombol.
- **Tampilan seluler vs desktop:** `useIsMobile()` (`<768px`) memilih tampilan; tanpa `matchMedia` (mis. jsdom) dianggap desktop.

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
- Uji lebar 375px, 768px, 1280px; di mobile juga 320x568, 360x640, 393x852.
- Pola frame (keputusan pemilik produk, menggantikan aturan PR #47 "dashboard muat satu bingkai"):
  - Satu bingkai setinggi layar hanya untuk halaman pendek dan tetap (login, register, profil kosong). Halaman lain, termasuk langkah metadata `/rps/:id`, memakai gulir halaman tunggal bila isinya lebih tinggi dari layar.
  - Halaman berdaftar (dashboard, `/rps/:id` langkah tinjau, `/analyses/:id`, `/analyses/new`) memakai gulir halaman tunggal tanpa wadah gulir bersarang, dengan tab atau bilah aksi lengket.
  - Layar pertama dashboard di 393x852 menampilkan unggah, tiga kartu statistik, dan minimal dua kartu RPS tanpa menggulir.
- Target sentuh minimal 44px (seluruh baris checkbox berupa `label`), input 16px, teks isi minimal 14px.

## 8. Kualitas
- `npm run lint`, `npm run typecheck`, `npm test` harus lulus.
- State loading/empty/error untuk setiap daftar.
- Semua teks UI bahasa Indonesia; simpan string di satu tempat (`lib/copy.ts`) agar konsisten.
