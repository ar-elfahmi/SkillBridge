# PRD — SkillBridge

**AI-Powered Curriculum & Industry Competency Mapping Platform**

## 1. Ringkasan
SkillBridge menghubungkan kompetensi dalam kurikulum pendidikan tinggi (dari dokumen RPS) dengan kebutuhan keterampilan industri (dari data lowongan kerja). Hasilnya: keterampilan yang tercakup, tercakup sebagian, dan belum tercakup, lengkap dengan bukti sumber, serta dua jenis rekomendasi.

- **Personal Learning Recommendation** — materi belajar mandiri untuk mahasiswa.
- **Curriculum Recommendation** — materi yang perlu ditinjau program studi.

Fokus: kesesuaian kurikulum dengan kebutuhan industri. Bukan penilaian kemampuan aktual mahasiswa dan bukan penyusunan kurikulum otomatis.

## 2. Masalah
- Kompetensi di RPS tidak terhubung langsung dengan kebutuhan profesi.
- Mahasiswa sulit tahu keterampilan tambahan yang perlu dipelajari.
- Prodi butuh data untuk menentukan materi yang perlu ditinjau.
- Membandingkan RPS dengan banyak lowongan secara manual memakan waktu.

## 3. Tujuan
1. Mengidentifikasi kompetensi yang tercakup dalam RPS.
2. Memetakan kompetensi tersebut ke kebutuhan skill industri.
3. Mengidentifikasi skill gap per profesi.
4. Memberi rekomendasi belajar mandiri untuk mahasiswa.
5. Memberi rekomendasi peninjauan materi untuk prodi.

## 4. Pengguna
| Role (`users.role`) | Kebutuhan |
|---|---|
| `student` (Mahasiswa) | Memahami kompetensi yang tercakup di mata kuliah dan mendapat rekomendasi belajar mandiri sesuai profesi tujuan. |
| `study_program` (Program Studi) | Menganalisis cakupan RPS dan mendapat rekomendasi materi yang perlu ditinjau. |

## 5. Fitur

### 5.1 Curriculum & Industry Mapping
- **Input:** RPS PDF, profesi tujuan, data kebutuhan skill dari lowongan.
- **Proses:** ekstraksi RPS → identifikasi CPL/CPMK/Sub-CPMK/materi/tugas → kompetensi terstruktur → ekstraksi skill lowongan → normalisasi istilah → pencocokan.
- **Output:** daftar kompetensi RPS, daftar skill profesi, tabel pemetaan, referensi bagian RPS pendukung, info lowongan sumber.
- Lowongan hanya sumber data kompetensi, bukan fitur pencarian atau pelamaran kerja.
- **Konfirmasi hasil ekstraksi (Tahap 2):** sebelum pemetaan, pengguna meninjau kompetensi hasil ekstraksi: menandai benar (`confirmed`), mengedit, menolak (`rejected`), atau menambah kompetensi yang terlewat. Kompetensi `unverified` ditonjolkan untuk ditinjau. Analisis hanya dapat dimulai setelah pengguna menekan "Konfirmasi hasil ekstraksi". Kompetensi `rejected` tidak ikut dipetakan. Kompetensi tambahan dari pengguna tidak punya kutipan RPS, ditandai `origin = user` dan berstatus `unverified`, sehingga hasil pencocokannya maksimal `partial`.

### 5.2 Skill Gap Analysis
| Status (`classification`) | Definisi |
|---|---|
| `covered` (Tercakup) | Kompetensi punya bukti pembelajaran memadai di RPS. |
| `partial` (Sebagian) | Berkaitan dengan materi RPS, tetapi cakupan/kedalaman belum sepenuhnya sesuai. |
| `missing` (Belum Tercakup) | Tidak ditemukan secara memadai di RPS yang dianalisis. |

- **Output:** jumlah per kategori, persentase cakupan, daftar gap, detail bukti RPS, kebutuhan skill pembanding.
- **Persentase cakupan** = jumlah skill `covered` / total skill industri yang dianalisis × 100. `partial` tidak dihitung sebagai covered.
- `missing` tidak berarti mahasiswa belum menguasai skill tersebut.

### 5.3 Personal Learning Recommendation
- **Input:** profesi tujuan, kompetensi RPS, hasil Skill Gap Analysis.
- **Output per item:** nama skill, topik yang perlu dipelajari, alasan, prioritas (`high`/`medium`/`low`), kata kunci/sumber belajar relevan.
- Contoh (Backend Developer): REST API Development (Tinggi), Docker Fundamentals (Tinggi), Git & Version Control (Sedang), CI/CD Fundamentals (Sedang).
- **Penyesuaian dengan skill dan lowongan yang dibutuhkan:**
  - Rekomendasi hanya dibuat untuk skill berstatus `partial` atau `missing` yang berasal dari lowongan yang dipilih pada analisis tersebut, bukan daftar skill generik profesi.
  - Setiap item menampilkan skill yang dibutuhkan beserta lowongan sumbernya (perusahaan, posisi) dan jumlah lowongan yang memintanya ("diminta di N lowongan"). Bila data lowongan tidak tersedia, simpan `null`, jangan mengarang.
  - Prioritas disesuaikan dengan kebutuhan lowongan: skill yang diminta di lebih banyak lowongan, atau ditandai wajib (*required*) pada lowongan, diprioritaskan lebih tinggi. Aturan penentuan prioritas diambil dari konfigurasi, bukan hardcode.
  - Untuk `partial`, topik disesuaikan dengan bagian RPS yang sudah berkaitan, sehingga yang disarankan adalah pendalaman atau pelengkap, bukan mengulang dari dasar.
  - Alasan rekomendasi merujuk bukti: kutipan lowongan dan, bila ada, kutipan RPS. Kata kunci belajar hanya berupa topik dan kata kunci, tanpa tautan yang tidak diverifikasi.
  - Mengubah pilihan lowongan atau profesi menghasilkan analisis baru dengan rekomendasi yang disesuaikan ulang.
- **Skill pribadi di luar RPS (Tahap 4):** pada hasil analisis, mahasiswa (`student`) dapat melaporkan skill yang sudah dipelajari di luar RPS.
  - Aksi tersedia di baris Skill Gap berstatus `missing`/`partial` ("Sudah saya pelajari di luar RPS") dan di kartu Rekomendasi Belajar; skill di luar daftar juga bisa ditambah lewat input bebas, lalu dinormalisasi ke kamus skill.
  - Daftar disimpan per pengguna (profil) dan dipakai ulang di analisis berikutnya. Pengguna dapat menghapus atau mengoreksinya kapan saja dari panel yang sama.
  - Skill pribadi **tidak** mengubah klasifikasi, `summary`, maupun "Cakupan skill dalam RPS". Ia ditampilkan sebagai lapisan terpisah berlabel "Dipelajari mandiri (dilaporkan pengguna)".
  - Pencocokan dengan skill industri hanya lewat `skill_id` ternormalisasi yang sama (deterministik), bukan tebakan embedding.
  - Rekomendasi Belajar tidak lagi memuat skill tersebut di daftar utama; skill itu muncul di bagian "Sudah dipelajari mandiri". Setelah skill pribadi berubah, pengguna menekan "Perbarui rekomendasi" (hanya rekomendasi dibuat ulang, klasifikasi tidak dihitung ulang).
  - Skill pribadi adalah klaim pengguna, bukan bukti dokumen dan tidak diverifikasi. Rekomendasi Kurikulum (`study_program`) tidak terpengaruh.
- Di luar cakupan: sistem kursus, sertifikasi, penilaian kemampuan, pelacakan progres.

### 5.4 Curriculum Recommendation
- **Input:** kompetensi RPS, kebutuhan industri, hasil Skill Gap Analysis.
- **Output per item:** kompetensi yang ditinjau, alasan, materi/minggu RPS relevan, usulan pengayaan, prioritas.
- Contoh: REST API → pengayaan materi Framework dan MVC; Docker → peninjauan materi container dan deployment.
- Bersifat pendukung keputusan. Sistem tidak mengubah RPS atau kurikulum.

## 6. Alur pengguna (4 tahap)
1. **Upload RPS** — unggah PDF; sistem validasi, simpan, ambil info dasar mata kuliah (nama, kode, SKS, semester, prodi). Output: dokumen tersimpan dan siap diproses.
2. **Ekstraksi Kompetensi** — sistem mengekstraksi CPL, CPMK, Sub-CPMK, materi, rencana mingguan, tugas/evaluasi → daftar kompetensi terstruktur + sumber di RPS. Status proses ditampilkan (polling). Setelah selesai, pengguna meninjau dan mengonfirmasi hasil ekstraksi (benar / edit / tolak / tambah kompetensi).
3. **Pemetaan Industri** — pengguna memilih profesi (mis. Backend Developer, Frontend Developer, Full Stack Developer, Data Analyst) dan lowongan spesifik; sistem menampilkan perusahaan, posisi, kualifikasi skill, sumber lowongan, dan hasil pemetaan awal.
4. **Hasil Analisis** — ringkasan cakupan, detail Skill Gap, Personal Learning Recommendation, Curriculum Recommendation. Detail dapat dilihat per skill, sumber RPS, dan lowongan. Mahasiswa dapat melaporkan skill yang sudah dipelajari di luar RPS dan memperbarui rekomendasi.

## 7. Simulasi (data contoh, bukan hasil industri tervalidasi)
Mata kuliah Pemrograman Web (kode 24060511D21, 2 SKS, semester 2, Teknik Informatika), profesi Backend Developer. Materi RPS: HTTP & client-server, HTML/CSS, PHP, form, MySQL, cookie & session, authentication, OOP & MVC, framework PHP, JavaScript & Fetch API.

12 kompetensi contoh: 5 covered (41,7%), 1 partial (8,3%), 6 missing (50,0%). Dipakai sebagai seed/fixture demo; label sebagai "data contoh".

## 8. Batasan sistem
1. Menganalisis dokumen RPS, bukan kemampuan aktual mahasiswa.
2. Satu RPS = cakupan satu mata kuliah. Analisis kurikulum prodi butuh beberapa RPS (di luar MVP; lihat Pengembangan Lanjut).
3. Lowongan = sumber kebutuhan skill, bukan pencarian/pelamaran kerja.
4. Tanpa pelacakan progres belajar atau verifikasi sertifikat. Skill pribadi hanyalah laporan pengguna yang tidak diverifikasi.
5. Tanpa perubahan kurikulum otomatis.
6. Rekomendasi AI harus didukung data RPS dan kebutuhan industri yang dapat ditelusuri.
7. Kualitas hasil bergantung pada kelengkapan RPS, kualitas ekstraksi, dan relevansi data lowongan.
8. PDF hasil scan (tanpa text layer) tidak bisa diproses parser teks; tandai `needs_ocr` / gagal dengan pesan jelas kecuali OCR tersedia.

## 9. Kriteria keberhasilan MVP
- [ ] Pengguna dapat mengunggah RPS PDF.
- [ ] Sistem mengekstraksi CPMK, Sub-CPMK, dan materi pembelajaran.
- [ ] Sistem menghasilkan daftar kompetensi terstruktur dengan bukti.
- [ ] Pengguna dapat meninjau, mengedit, menolak, menambah, dan mengonfirmasi hasil ekstraksi kompetensi.
- [ ] Pengguna dapat memilih profesi tujuan.
- [ ] Sistem menampilkan lowongan spesifik beserta sumbernya.
- [ ] Sistem mengekstraksi kebutuhan skill dari data lowongan.
- [ ] Sistem memetakan kompetensi RPS dengan kebutuhan industri.
- [ ] Sistem mengklasifikasikan skill gap (`covered`/`partial`/`missing`).
- [ ] Sistem menampilkan bukti sumber hasil pemetaan.
- [ ] Sistem menghasilkan rekomendasi belajar mandiri.
- [ ] Mahasiswa dapat melaporkan skill pribadi di luar RPS pada hasil analisis, dan rekomendasi belajar menyesuaikan tanpa mengubah klasifikasi RPS.
- [ ] Sistem menghasilkan rekomendasi peninjauan kurikulum.
- [ ] Hasil tampil baik di mobile dan desktop.

## 10. Nilai utama
1. Evidence-based curriculum mapping — bagian RPS yang menjadi dasar selalu ditampilkan.
2. Industry-driven skill gap analysis — berbasis data lowongan relevan.
3. Dual-perspective recommendation — satu analisis, dua audiens.
4. Context-aware learning recommendation — mempertimbangkan kompetensi yang sudah tercakup.

Klaim kebaruan terhadap produk/penelitian lain belum dikaji dan jangan dipakai di UI atau materi tanpa kajian pembanding.

## 11. Keputusan & asumsi (hasil rekonsiliasi dua dokumen sumber)

### Sudah diputuskan pengguna
| Topik | Keputusan |
|---|---|
| Cakupan | Semua 4 fitur dibangun (dengan urutan kerja bertahap di `TASKS.md`). |
| Label klasifikasi | `covered` / `partial` / `missing`. |
| Tools vibe coding | Claude Code (`CLAUDE.md`). |
| Bahasa dokumen | Indonesia; identifier teknis Inggris. |

### Asumsi (ubah bila tidak sesuai — beri tahu dan dokumen akan diperbarui)
| Topik | Asumsi |
|---|---|
| Struktur repo | Monorepo `backend/` + `frontend/`. |
| Arsitektur | Modular monolith Laravel; tanpa service Python. |
| AI | Gemini Flash untuk ekstraksi & penilaian; `gemini-embedding-001` 768 dimensi. Nama model Flash di env. |
| Embedding | `JSONB`, cosine di PHP, tanpa pgvector. |
| Queue | Laravel Queue + Redis (sesuai dokumen produk); worker terpisah (proses systemd/Supervisor) di VPS yang sama. |
| Auth | Sanctum cookie SPA; production memakai subdomain satu domain induk (mis. `app.` dan `api.`) agar cookie same-site. |
| Role | `student` dan `study_program`; registrasi publik memilih salah satu. Tidak ada role admin di MVP. |
| Data lowongan | Dataset terkurasi (seeder/import CSV-JSON dengan `source_url` dan `collected_at`) **plus** input manual tempel deskripsi lowongan. Tidak ada scraping otomatis. |
| Profesi | Tabel `careers` diisi seeder (Backend, Frontend, Full Stack, Data Analyst). |
| Analisis | Satu RPS per analisis, satu profesi, satu atau lebih lowongan dari profesi tersebut. Skill yang sama dari beberapa lowongan digabung (`demand_count`). |
| Metadata mata kuliah | Diekstrak dari PDF oleh LLM, dapat diedit pengguna sebelum ekstraksi kompetensi dimulai. |
| OCR | Tidak termasuk MVP; PDF scan ditandai `needs_ocr`. |
| Prioritas rekomendasi | Dihitung deterministik oleh Laravel (lihat `AI_PIPELINE.md`), penjelasan teks oleh LLM. |

## 12. Pengembangan lanjut (di luar scope sekarang)
Analisis multi-RPS per prodi (`analysis_documents`), OCR/multimodal untuk PDF scan, pgvector bila data membesar, ekspor laporan PDF, role admin untuk kurasi lowongan.
