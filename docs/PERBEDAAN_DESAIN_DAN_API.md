# Analisis Kesenjangan: Desain (`dihsain.pen`) vs API & Database Backend

Dokumen ini memetakan perbandingan komprehensif antara 20 frame desain antarmuka pengguna (`design/dihsain.pen`) dengan implementasi API backend (Laravel Sanctum) dan skema database PostgreSQL.

### Pemetaan ID Frame Desain
| No | Layar / Fitur | Node ID Desktop | Node ID Mobile |
|---|---|---|---|
| 1 | **01 Login** | `s9yfbw` | `gFMh9` |
| 2 | **02 Register** | `bqXr9` | `GAmwT` |
| 3 | **03 Dashboard** | `jVAFM` | `N89Ux` |
| 4 | **04 Detail RPS — Tahap 0** (Metadata) | `Q2CPz` | `ofKjk` |
| 5 | **04 Detail RPS — Tahap 1** (Kompetensi) | `g8RHiP` | `GRI61` |
| 6 | **05 Pemetaan Industri** | `MQgvQ` | `sxwG7` |
| 7 | **06 Hasil Analisis — Ringkasan** | `gP4cp` | `n28t2` |
| 8 | **06 Hasil Analisis — Skill Gap** | `gTlaA` | `iFLC6` |
| 9 | **06 Hasil Analisis — Rekomendasi Belajar** | `ZXSVq` | `w4D8PB` |
| 10 | **07 Profil Pengguna** | `zQGdK` | `PgCOd` |

---

## 1. Ringkasan Ketidaksesuaian per Halaman

### 1.1. Login (`s9yfbw` Desktop & `gFMh9` Mobile)

| Komponen Desain | Yang Tampil di Desain | Kondisi API & Database Backend Saat Ini | Dampak & Solusi yang Diperlukan |
|---|---|---|---|
| **Form Login** | Input Email (`nama@email.com`), Kata Sandi, Tombol *"Masuk"*, Link *"Daftar"*. | Sesuai di `POST /api/auth/login` (menerima `email` dan `password`). Mengembalikan user dan plainTextToken Sanctum. | Cocok ✅. |
| **Pesan Kesalahan Autentikasi** | Banner peringatan: *"Email atau kata sandi salah."* | API mengembalikan HTTP `422` atau `401` dengan pesan error validasi default Laravel. | **Frontend:** Tangkap error `401`/`422` dan petakan pesan ke string ramah pengguna *"Email atau kata sandi salah."* |
| **Hero / Banner Informasi (Desktop `s9yfbw`)** | Panel kiri: 3 poin fitur (*Unggah RPS*, *Tinjau kompetensi*, *Bandingkan lowongan*) + disclaimer *"Data lowongan pada contoh ini adalah data contoh"*. | Komponen statis UI presentasional. | Cocok ✅. Khusus mobile (`gFMh9`), panel banner dihilangkan agar fokus pada form. |

---

### 1.2. Register (`bqXr9` Desktop & `GAmwT` Mobile)

| Komponen Desain | Yang Tampil di Desain | Kondisi API & Database Backend Saat Ini | Dampak & Solusi yang Diperlukan |
|---|---|---|---|
| **Form Registrasi** | Input Nama, Email, Kata Sandi (*"Minimal 8 karakter"*), Ulangi Kata Sandi, Tombol *"Daftar"*, Link *"Masuk"*. | Sesuai di `POST /api/auth/register` (menerima `name`, `email`, `password`, `password_confirmation`). Role otomatis `'student'`. | Cocok ✅. |
| **Validasi Password** | Petunjuk teks *"Minimal 8 karakter"*. | Validasi backend di `RegisterRequest` mewajibkan `min:8` dan `confirmed`. | Cocok ✅. |
| **Deteksi Email Duplikat** | Alert error: *"Email sudah terdaftar."* | API mengembalikan HTTP `422 Unprocessable Entity` dengan error key `email`. | **Frontend:** Tangkap respons error validasi `errors.email` lalu tampilkan badge alert merah *"Email sudah terdaftar."* |
| **Banner Informasi (Desktop `bqXr9`)** | Panel kiri *"UNTUK MAHASISWA"* dengan ringkasan alur analisis. | Konten statis frontend. Pada mobile (`GAmwT`) form langsung tampil penuh. | Cocok ✅. |

---

### 1.3. Dashboard (`jVAFM` Desktop & `N89Ux` Mobile)

| Komponen Desain | Yang Tampil di Desain | Kondisi API & Database Backend Saat Ini | Dampak & Solusi yang Diperlukan |
|---|---|---|---|
| **Statistik Widget (3 kartu atas)** | - *Skill yang kurang:* "7 skill · 3 prioritas minggu ini"<br>- *Lowongan cocok:* "12 lowongan · 3 baru"<br>- *Profil pekerjaan:* "2 profil pekerjaan · Target aktif"<br>*(Mobile `N89Ux`: angka besar 7, 12, 2).* | **Tidak ada endpoint agregasi/stat**. Tidak ada tabel pelacakan lowongan baru per minggu, target aktif, atau prioritas mingguan di DB. | **Backend:** Perlu endpoint agregasi baru `GET /api/dashboard/stats` atau frontend menghitung estimasi lokal dari riwayat analisis terakhir. |
| **Kartu RPS Dianalisis** | Menampilkan badge persentase (*"Cakupan skill dalam RPS 41,7%"*) dan *"3 skill belum tercakup"*. | `GET /api/rps` mengembalikan `RpsDocumentResource` yang **hanya berisi status parsing & review**, tanpa metrik cakupan atau relasi ke analisis. | **Backend:** Tambahkan relasi `latestAnalysis` pada `RpsDocumentResource`, atau **Frontend** harus memanggil `GET /api/analyses` dan mencocokkan `rps_document_id`. |
| **Sidebar Desktop: Skill Dimiliki & Aktivitas** | Menampilkan skill mandiri (*Beginner*, *Intermediate*, *Advanced*) dan audit log aktivitas (*"RPS dianalisis · 2 jam lalu"*). | Endpoint skill mandiri (`/api/me/skills`) dan tabel `user_skills` **belum dibuat**. Tidak ada tabel audit log `activities`. | **Database & Backend:** Migrasi `user_skills` (dengan kolom `level`). Frontend mensintesis aktivitas dari `created_at` analisis/RPS. *(Catatan: Pada mobile `N89Ux`, sidebar ini disembunyikan).* |
| **Search Bar Navbar** | *"Cari RPS, skill, atau lowongan"* | **Tidak ada endpoint global search** (`/api/search`). | **Solusi:** Sederhanakan search bar di frontend untuk memfilter item di halaman aktif saja. (Mobile tidak menampilkan search bar). |
| **Navigasi Mobile (`N89Ux`)** | Bottom navigation bar: *Beranda*, *Unggah RPS*, *Profil*. | Diatur di routing client-side React. | Cocok ✅. |

---

### 1.4. Detail RPS Tahap 0 (`Q2CPz` Desktop & `ofKjk` Mobile)

| Komponen Desain | Yang Tampil di Desain | Kondisi API & Database Backend Saat Ini | Dampak & Solusi yang Diperlukan |
|---|---|---|---|
| **Metadata Berkas** | Subtitle berkas: *"18 halaman · 2,4 MB"* | Tabel `rps_documents` memiliki kolom `file_size` (integer byte), tetapi `RpsDocumentResource` **tidak mengekspos `file_size`** ke JSON response (hanya `page_count`). | **Backend:** Tambahkan `'file_size' => $this->file_size` pada `RpsDocumentResource.php`. |
| **Form Metadata Mata Kuliah** | Kode (wajib), Nama, SKS, Semester, Program Studi | Sesuai. `PATCH /api/rps/{id}` menerima lima field ini dan `detected_metadata` mengembalikannya dalam bentuk JSON. | Cocok ✅. |
| **Catatan Reuse Kode MK** | Catatan di bawah form: *"Mata kuliah dengan kode yang sama dipakai ulang, bukan diduplikasi."* | Sesuai dengan logika backend: model `Course` dicari berdasarkan unique `code` sebelum dibuat baru. | Cocok ✅. |
| **Stepper Status & Bottom Nav** | Stepper 3 tahap: 1. Konfirmasi metadata -> 2. Tinjau kompetensi -> 3. Petakan industri. Mobile (`ofKjk`) memiliki bottom navigation. | Diatur di state frontend. | Cocok ✅. |

---

### 1.5. Detail RPS Tahap 1 (`g8RHiP` Desktop & `GRI61` Mobile)

| Komponen Desain | Yang Tampil di Desain | Kondisi API & Database Backend Saat Ini | Dampak & Solusi yang Diperlukan |
|---|---|---|---|
| **Statistik Ringkasan Ekstraksi** | Kartu: *Total kompetensi (24)*, *Belum terverifikasi (6)*, *Menunggu tinjauan (3)* | `GET /api/rps/{id}/competencies` mengembalikan daftar array lengkap `CourseSkillResource`. | Cocok ✅. Frontend dapat menghitung agregasi total/unverified/pending secara langsung dari array kompetensi. |
| **Filter Status** | Tab/Filter: *Semua*, *Belum diverifikasi*, *Menunggu*, *Ditolak* | Data kompetensi memiliki field `verification_status` (`verified`/`unverified`) dan `review_status` (`pending`/`confirmed`/`rejected`). | Cocok ✅. Frontend melakukan filter lokal pada state. |
| **Tampilan Tabel vs Kartu** | Desktop (`g8RHiP`) berupa tabel komprehensif. Mobile (`GRI61`) berupa deretan kartu dengan kutipan bukti (*verbatim evidence*) dan referensi sumber. | Struktur `CourseSkillResource` memuat `competency`, `evidence`, `source_reference`, `week`, `page`. | Cocok ✅. Format card mobile cocok dengan payload data. |
| **Aksi Tinjau Kompetensi** | Setujui (Centang), Tolak (Silang), Edit, Tambah Kompetensi | Endpoint tersedia: `PATCH /api/rps/{id}/competencies/{cid}`, `POST .../competencies`, `DELETE .../competencies/{cid}`. | Cocok ✅. |

---

### 1.6. Pemetaan Industri (`MQgvQ` Desktop & `sxwG7` Mobile)

| Komponen Desain | Yang Tampil di Desain | Kondisi API & Database Backend Saat Ini | Dampak & Solusi yang Diperlukan |
|---|---|---|---|
| **Step 1: Pilih Profesi** | Dropdown / kartu profesi (misal: *Frontend Developer* beserta deskripsi). | Sesuai dengan `GET /api/careers`. | Cocok ✅. |
| **Step 2: Badge Skill pada Kartu Lowongan** | Tiap lowongan menampilkan tag skill (misal: *React*, *TypeScript*, *CSS*). | `JobPostingController::index` (`GET /api/jobs`) **tidak me-load relasi `jobSkills`** (`JobPosting::with('career')`). Properti `skills` pada `JobPostingResource` bernilai `undefined`/hilang. | **Backend:** Ubah query di `JobPostingController::index` menjadi `JobPosting::with(['career', 'jobSkills.skillEntry'])`. |
| **Step 3: Pratinjau Pemetaan Awal (`sxwG7` & `MQgvQ`)** | Ringkasan skill teragregasi dari lowongan yang dipilih (*"React · Diminta di 2 lowongan"*). | **Tidak ada endpoint kalkulasi preview**. | **Frontend:** Menghitung agregasi skill di sisi klien dari daftar lowongan yang dipilih setelah relasi `skills` diekspos backend. |
| **Aksi Tambah Lowongan Manual** | Tombol *"Tambah lowongan manual"*. | Endpoint `POST /api/jobs` mendukung pembuatan lowongan custom oleh user (`created_by = user_id`). | Cocok ✅. |

---

### 1.7. Hasil Analisis — Ringkasan (`gP4cp` Desktop & `n28t2` Mobile)

| Komponen Desain | Yang Tampil di Desain | Kondisi API & Database Backend Saat Ini | Dampak & Solusi yang Diperlukan |
|---|---|---|---|
| **Indikator Metrik 3 Kategori** | - *Tercakup:* 41,7% (10 dari 24 skill)<br>- *Sebagian:* 22,6% (5 dari 24 skill)<br>- *Belum ditemukan di RPS:* 35,7% (9 dari 24 skill) | `AnalysisResource` mengembalikan `summary`: `{ total_skills, covered, partial, missing, coverage_percentage }`. | Cocok ✅. Frontend menghitung persentase partial dan missing secara lokal: `(count / total_skills) * 100`. |
| **Visualisasi Cakupan** | Bar / Donut Chart cakupan skill dalam RPS. | Dibangun berdasarkan angka summary `AnalysisResource`. | Cocok ✅. |
| **Header Status & Tombol Perbarui** | Menampilkan *"Analisis selesai"*, tanggal analisis, dan tombol *"Perbarui"*. | Analisis memiliki `status` (`completed`) dan `created_at`. Tombol perbarui memicu `POST /api/analyses` ulang. | Cocok ✅. |

---

### 1.8. Hasil Analisis — Skill Gap (`gTlaA` Desktop & `iFLC6` Mobile)

| Komponen Desain | Yang Tampil di Desain | Kondisi API & Database Backend Saat Ini | Dampak & Solusi yang Diperlukan |
|---|---|---|---|
| **Filter & Pencarian Skill** | Search bar *"Cari skill"*, filter tab (*Semua*, *Tercakup*, *Sebagian*, *Belum ditemukan*), checkbox *"Sembunyikan yang sudah dilaporkan"*. | `GET /api/analyses/{id}/matches` mengembalikan array `SkillMatchResource` dengan properti `classification` dan boolean `self_declared`. | Cocok ✅. Filtering dilakukan lokal pada state frontend. |
| **Penyajian Data (Tabel vs Card)** | Desktop (`gTlaA`) memakai tabel kolom: *Skill*, *Status*, *Diminta di*, *Kompetensi RPS terkait*, *Penanda*.<br>Mobile (`iFLC6`) kartu bertumpuk dengan tombol drawer *"Lihat bukti"*. | `SkillMatchResource` memuat `skill`, `classification`, `demand_count`, `matched_competency`, `job_evidence`, `self_declared`. | Cocok ✅. |
| **Penanda Skill Mandiri** | Badge *"Dilaporkan"* pada skill yang diinput mandiri oleh mahasiswa di luar RPS. | Berdasarkan boolean `self_declared: true`. | Cocok ✅. |

---

### 1.9. Hasil Analisis — Rekomendasi Belajar (`ZXSVq` Desktop & `w4D8PB` Mobile)

| Komponen Desain | Yang Tampil di Desain | Kondisi API & Database Backend Saat Ini | Dampak & Solusi yang Diperlukan |
|---|---|---|---|
| **Banner Status Rekomendasi Stale** | Alert: *"Rekomendasi belum diperbarui setelah perubahan skill pribadi."* + Tombol *"Perbarui rekomendasi"*. | **Tidak ada flag atau status dirty/stale** pada resource analisis ketika user menambah skill di Profil. | **Solusi:** Frontend melacak perubahan `user_skills` atau backend menyediakan trigger endpoint untuk re-evaluasi rekomendasi. |
| **Sumber Lowongan pada Rekomendasi** | Kartu menampilkan: *"Diminta di 2 lowongan: PT Contoh — Frontend Developer"*. | `RecommendationResource` **hanya mengirim `skill`, `priority`, `title`, `reason`, dan `details`** (`topics`, `learning_keywords`). Tidak memuat nama perusahaan atau lowongan. | **Backend:** Tambahkan relasi `source_jobs` pada `RecommendationResource` dengan mengambil relasi `skillMatch.source_job_skill_ids`. |
| **Aksi "Sudah saya pelajari di luar RPS"** | Tombol aksi cepat pada kartu rekomendasi agar mahasiswa langsung menandai skill tersebut sebagai skill mandiri. | Menuntut pemanggilan `POST /api/me/skills` (CRUD skill mandiri), lalu me-refresh daftar rekomendasi. | **Database & Backend:** Memerlukan implementasi tabel & API `user_skills`. |

---

### 1.10. Halaman Profil (`zQGdK` Desktop & `PgCOd` Mobile)

| Komponen Desain | Yang Tampil di Desain | Kondisi API & Database Backend Saat Ini | Dampak & Solusi yang Diperlukan |
|---|---|---|---|
| **Header Profil & Logout** | Avatar inisial, nama, email, tombol *"Keluar"*. | `GET /api/auth/me` dan `POST /api/auth/logout`. | Cocok ✅. |
| **Route Halaman `/profile`** | Halaman kelola identitas & daftar skill pribadi. | Route `/profile` belum terdaftar di React Router client (`FRONTEND.md`). | **Frontend:** Daftarkan route `/profile` di `routes/index.tsx`. |
| **Form Tambah Skill Pribadi** | Input teks skill kanonik, dropdown level (*Beginner*, *Intermediate*, *Advanced*), tombol *"Tambah skill"*. | Skema spesifikasi `user_skills` (`user_id`, `skill_id`, `skill`, `source_analysis_id`) **tidak memiliki kolom `level`**. | **Kesenjangan Skema vs Desain:** Desain menampilkan pilihan level kemahiran (*Beginner*, *Intermediate*, *Advanced*). Perlu keputusan: menambahkan kolom `level` (opsional/default `'intermediate'`) pada tabel `user_skills`, atau menghapus dropdown tingkat dari UI. |
| **Kolom "Dilaporkan sebagai"** | Menampilkan teks masukan asli pengguna (*ReactJS* vs kanonik *React*). | Kolom `skill` (string) pada skema `user_skills` menampung teks mentah yang diketik pengguna, sedangkan `skill_id` mereferensikan entri kanonik di tabel `skills`. | Cocok secara konseptual ✅. Di frontend di-render sebagai kolom *"Dilaporkan sebagai"*. |
| **Pencocokan Skill Mandiri** | Keterangan: *"Skill ini dicocokkan dengan skill industri lewat nama kanonik yang sama."* | `Unique(user_id, skill_id)`. Tanpa embedding dan tanpa bukti dokumen. Dicocokkan dengan skill industri lewat `skill_id` yang sama saat data dibaca (tidak disalin ke `skill_matches`). | Sesuai spesifikasi backend ✅. |
| **Korelasi dengan Rekomendasi Belajar** | Tombol *"Sudah saya pelajari di luar RPS"* pada kartu rekomendasi (`ZXSVq` / `w4D8PB`). | Skema menyediakan kolom `source_analysis_id` (FK `analyses.id`, nullable) untuk mencatat analisis asal penambahan skill mandiri. | Sesuai spesifikasi backend ✅. |

---

## 2. Daftar Lengkap Endpoint API & Struktur Respons

Berikut struktur respons JSON aktual yang dihasilkan oleh Laravel backend saat ini:

### 2.1. Autentikasi (`/api/auth/*`)

#### `GET /api/auth/me` & `POST /api/auth/login` & `POST /api/auth/register`
**Status:** `200 OK` (atau `201 Created` untuk register)
```json
{
  "data": {
    "id": 1,
    "name": "Anisa Rahma",
    "email": "anisa@skillbridge.id",
    "role": "student",
    "created_at": "2026-11-10T08:00:00+00:00"
  }
}
```

---

### 2.2. Dokumen RPS (`/api/rps/*`)

#### `POST /api/rps`
**Status:** `202 Accepted`
```json
{
  "data": {
    "id": 12,
    "original_name": "Pemrograman Web 2025.pdf",
    "parse_status": "pending",
    "extraction_status": "pending",
    "review_status": "unreviewed",
    "course": null,
    "detected_metadata": null,
    "page_count": 18,
    "error_message": null,
    "created_at": "2026-11-12T08:00:00+00:00"
  }
}
```

#### `GET /api/rps/{id}` & `PATCH /api/rps/{id}`
**Status:** `200 OK`
```json
{
  "data": {
    "id": 12,
    "original_name": "Pemrograman Web 2025.pdf",
    "parse_status": "parsed",
    "extraction_status": "pending",
    "review_status": "unreviewed",
    "course": {
      "code": "IF2013",
      "name": "Pemrograman Web",
      "credits": 4,
      "semester": 4,
      "study_program": "Teknik Informatika"
    },
    "detected_metadata": {
      "code": "IF2013",
      "name": "Pemrograman Web",
      "credits": 4,
      "semester": 4,
      "study_program": "Teknik Informatika"
    },
    "page_count": 18,
    "error_message": null,
    "created_at": "2026-11-12T08:00:00+00:00"
  }
}
```

#### `GET /api/rps/{id}/competencies`
**Status:** `200 OK`
```json
{
  "data": [
    {
      "id": 101,
      "skill": "Algoritma dasar",
      "canonical_skill": "Algoritma",
      "category": "concept",
      "competency": "Mampu membuat algoritma sederhana",
      "evidence": "Mahasiswa mampu membuat algoritma sederhana",
      "source_type": "Sub-CPMK",
      "source_reference": "Sub-CPMK 3",
      "week": 2,
      "page": 4,
      "verification_status": "verified",
      "origin": "ai",
      "review_status": "confirmed",
      "user_edited": false
    }
  ]
}
```

---

### 2.3. Industri: Profesi & Lowongan (`/api/careers`, `/api/jobs`)

#### `GET /api/careers`
**Status:** `200 OK`
```json
{
  "data": [
    {
      "id": 1,
      "name": "Backend Developer",
      "slug": "backend-developer",
      "description": "Merancang dan membangun logika sisi server, API, dan arsitektur database."
    }
  ]
}
```

#### `GET /api/jobs?career_id=1`
**Status:** `200 OK` (Paginasi)
```json
{
  "data": [
    {
      "id": 10,
      "career_id": 1,
      "career": {
        "id": 1,
        "name": "Backend Developer",
        "slug": "backend-developer"
      },
      "created_by": null,
      "is_curated": true,
      "title": "Backend Developer (PHP/Laravel)",
      "company": "PT Contoh Solusi Digital",
      "description": "Membangun REST API performa tinggi...",
      "qualifications": "Pengalaman minimal 1 tahun dengan Laravel...",
      "source_name": "JobStreet",
      "source_url": "https://example.com/job/10",
      "collected_at": "2026-11-12",
      "dataset_version": "v1.4",
      "extraction_status": "completed",
      "skills": []
    }
  ]
}
```
*(Catatan: `skills` saat ini kosong jika dipanggil via list `/jobs` karena belum di-eager-load).*

#### `GET /api/jobs/{id}`
**Status:** `200 OK`
```json
{
  "data": {
    "id": 10,
    "career_id": 1,
    "title": "Backend Developer (PHP/Laravel)",
    "company": "PT Contoh Solusi Digital",
    "description": "Membangun REST API performa tinggi...",
    "qualifications": "Pengalaman minimal 1 tahun...",
    "source_name": "JobStreet",
    "source_url": "https://example.com/job/10",
    "collected_at": "2026-11-12",
    "dataset_version": "v1.4",
    "extraction_status": "completed",
    "skills": [
      {
        "id": 501,
        "skill": "Laravel",
        "canonical_skill": "Laravel Framework",
        "category": "framework",
        "requirement_text": "Pengalaman dengan framework Laravel",
        "evidence": "Pengalaman minimal 1 tahun dengan Laravel"
      }
    ]
  }
}
```

---

### 2.4. Analisis & Skill Gap (`/api/analyses/*`)

#### `POST /api/analyses`
**Request Payload:**
```json
{
  "rps_document_id": 12,
  "career_id": 1,
  "job_posting_ids": [10, 11]
}
```
**Status:** `202 Accepted` (atau `200 OK` jika proses sinkron)

#### `GET /api/analyses/{id}`
**Status:** `200 OK`
```json
{
  "data": {
    "analysis_id": 101,
    "status": "completed",
    "career": "Backend Developer",
    "course": "Pemrograman Web",
    "summary": {
      "total_skills": 24,
      "covered": 10,
      "partial": 5,
      "missing": 9,
      "coverage_percentage": 41.67
    },
    "model_versions": {
      "judge_model": "gemini-2.5-flash",
      "embedding_model": "text-embedding-004",
      "embedding_dimensions": 768,
      "prompt_version": "match-judge-v1",
      "sim_floor": 0.5,
      "sim_high": 0.8,
      "top_k": 3
    },
    "error_message": null,
    "created_at": "2026-11-12T10:30:00.000000Z"
  }
}
```

#### `GET /api/analyses/{id}/matches`
**Status:** `200 OK`
```json
{
  "data": [
    {
      "skill": "Laravel Framework",
      "demand_count": 2,
      "self_declared": false,
      "classification": "covered",
      "similarity_score": 0.92,
      "reason": "Sub-CPMK 3 mencakup pengembangan web dengan framework MVC Laravel.",
      "matched_competency": {
        "id": 101,
        "competency": "Mampu mengembangkan aplikasi web menggunakan Laravel",
        "evidence": "Pengembangan web menggunakan framework MVC Laravel",
        "source_reference": "Sub-CPMK 3",
        "page": 4
      },
      "job_evidence": [
        {
          "job_posting_id": 10,
          "company": "PT Contoh Solusi Digital",
          "evidence": "Pengalaman minimal 1 tahun dengan Laravel"
        }
      ]
    }
  ]
}
```

---

### 2.5. Rekomendasi Belajar (`/api/analyses/{id}/recommendations`)

#### `GET /api/analyses/{id}/recommendations`
**Status:** `200 OK`
```json
{
  "data": [
    {
      "skill": "Docker",
      "priority": "high",
      "title": "Docker Fundamentals",
      "reason": "Tidak ditemukan dalam RPS yang dianalisis dan diminta pada 2 dari 2 lowongan.",
      "details": {
        "topics": [
          "Image & Container",
          "Dockerfile",
          "Docker Compose"
        ],
        "learning_keywords": [
          "docker getting started",
          "dockerfile best practices"
        ]
      }
    }
  ],
  "meta": {
    "self_declared_skills": [
      {
        "skill": "React",
        "classification": "partial",
        "label": "Dipelajari mandiri (dilaporkan pengguna)"
      }
    ]
  }
}
```

---

## 3. Skema Kolom Database PostgreSQL Aktual

Berikut skema tabel-tabel utama di database PostgreSQL backend saat ini:

### `users`
| Kolom | Tipe Data | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint` | NO | Primary key |
| `name` | `varchar` | NO | Nama pengguna |
| `email` | `varchar` | NO | Email unik |
| `email_verified_at` | `timestamp` | YES | Tanggal verifikasi |
| `password` | `varchar` | NO | Hash password bcrypt |
| `role` | `varchar` | NO | Selalu `'student'` |
| `remember_token` | `varchar` | YES | Token sesi |
| `created_at`, `updated_at` | `timestamp` | YES | Timestamps |

### `courses`
| Kolom | Tipe Data | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint` | NO | Primary key |
| `code` | `varchar` | YES | Kode mata kuliah (unik) |
| `name` | `varchar` | NO | Nama mata kuliah |
| `credits` | `smallint` | YES | Jumlah SKS |
| `semester` | `smallint` | YES | Semester mata kuliah |
| `study_program` | `varchar` | YES | Nama program studi |
| `created_at`, `updated_at` | `timestamp` | YES | Timestamps |

### `rps_documents`
| Kolom | Tipe Data | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint` | NO | Primary key |
| `user_id` | `bigint` | NO | Foreign key ke `users.id` |
| `course_id` | `bigint` | YES | Foreign key ke `courses.id` (setelah konfirmasi) |
| `original_name` | `varchar` | NO | Nama file PDF asli |
| `file_path` | `varchar` | NO | Lokasi storage privat |
| `file_hash` | `varchar` | NO | Hash SHA-256 berkas (deteksi duplikat) |
| `file_size` | `integer` | NO | Ukuran file dalam bytes |
| `page_count` | `smallint` | YES | Jumlah halaman PDF |
| `parse_status` | `varchar` | NO | `pending`, `parsing`, `parsed`, `failed`, `needs_ocr` |
| `extraction_status` | `varchar` | NO | `pending`, `processing`, `completed`, `failed` |
| `review_status` | `varchar` | NO | `unreviewed`, `confirmed`, `rejected` |
| `reviewed_at` | `timestamp` | YES | Waktu konfirmasi review |
| `detected_metadata` | `jsonb` | YES | Draft metadata hasil ekstraksi AI |
| `metadata_confirmed_at` | `timestamp` | YES | Waktu konfirmasi metadata |
| `error_message` | `text` | YES | Pesan error bila gagal |
| `created_at`, `updated_at` | `timestamp` | YES | Timestamps |

### `rps_pages`
| Kolom | Tipe Data | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint` | NO | Primary key |
| `rps_document_id` | `bigint` | NO | Foreign key ke `rps_documents.id` |
| `page_number` | `smallint` | NO | Nomor halaman (1-based) |
| `text` | `text` | NO | Teks hasil ekstraksi parser PDF |
| `created_at`, `updated_at` | `timestamp` | YES | Timestamps |

### `skills`
| Kolom | Tipe Data | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint` | NO | Primary key |
| `canonical_name` | `varchar` | NO | Nama standar kanonik (unik) |
| `category` | `varchar` | YES | `language`, `framework`, `tool`, `concept`, `practice`, `other` |
| `aliases` | `jsonb` | NO | Array alias/sinonim (misal: `["ReactJS", "React.js"]`) |
| `created_at`, `updated_at` | `timestamp` | YES | Timestamps |

### `course_skills`
| Kolom | Tipe Data | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint` | NO | Primary key |
| `rps_document_id` | `bigint` | NO | Foreign key ke `rps_documents.id` |
| `skill_id` | `bigint` | YES | Foreign key ke `skills.id` |
| `skill` | `varchar` | NO | Nama skill asli dari dokumen |
| `category` | `varchar` | YES | Kategori skill |
| `competency` | `text` | NO | Deskripsi kemampuan/kompetensi |
| `evidence` | `text` | YES | Kutipan verbatim dari PDF RPS |
| `source_type` | `varchar` | NO | `CPL`, `CPMK`, `Sub-CPMK`, `material`, `weekly_plan`, `assignment`, `user` |
| `source_reference` | `varchar` | YES | Label sumber (misal: "Sub-CPMK 3") |
| `week` | `smallint` | YES | Pertemuan ke-N |
| `page` | `smallint` | YES | Nomor halaman bukti di PDF |
| `verification_status` | `varchar` | NO | `verified`, `unverified` |
| `origin` | `varchar` | NO | `ai`, `user` |
| `review_status` | `varchar` | NO | `pending`, `confirmed`, `rejected` |
| `user_edited` | `boolean` | NO | Flag apakah diedit manual oleh mahasiswa |
| `embedding` | `jsonb` | YES | Vektor embedding teks kompetensi |
| `embedding_model` | `varchar` | YES | Nama model embedding |
| `embedding_dimensions` | `smallint` | YES | Dimensi vektor (768) |
| `text_hash` | `varchar` | YES | Hash untuk cache embedding |
| `created_at`, `updated_at` | `timestamp` | YES | Timestamps |

### `careers`
| Kolom | Tipe Data | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint` | NO | Primary key |
| `name` | `varchar` | NO | Nama profesi |
| `slug` | `varchar` | NO | Slug URL (unik) |
| `description` | `text` | YES | Deskripsi profesi |
| `created_at`, `updated_at` | `timestamp` | YES | Timestamps |

### `job_postings`
| Kolom | Tipe Data | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint` | NO | Primary key |
| `career_id` | `bigint` | NO | Foreign key ke `careers.id` |
| `created_by` | `bigint` | YES | Foreign key ke `users.id` (null bila data kurasi/contoh) |
| `title` | `varchar` | NO | Judul posisi |
| `company` | `varchar` | NO | Nama perusahaan |
| `description` | `text` | NO | Uraian pekerjaan lengkap |
| `qualifications` | `text` | YES | Kualifikasi persyaratan |
| `source_name` | `varchar` | YES | Nama platform (JobStreet, LinkedIn, manual) |
| `source_url` | `varchar` | YES | Tautan asli lowongan |
| `collected_at` | `date` | YES | Tanggal data diambil |
| `dataset_version` | `varchar` | YES | Versi dataset kurasi (misal: "v1.4") |
| `extraction_status` | `varchar` | NO | `pending`, `processing`, `completed`, `failed` |
| `created_at`, `updated_at` | `timestamp` | YES | Timestamps |

### `job_skills`
| Kolom | Tipe Data | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint` | NO | Primary key |
| `job_posting_id` | `bigint` | NO | Foreign key ke `job_postings.id` |
| `skill_id` | `bigint` | NO | Foreign key ke `skills.id` |
| `skill` | `varchar` | NO | Nama skill yang diekstrak |
| `requirement_text` | `text` | NO | Konteks kalimat persyaratan di lowongan |
| `evidence` | `text` | NO | Frasa bukti konkret |
| `embedding` | `jsonb` | YES | Vektor embedding |
| `embedding_model` | `varchar` | YES | Nama model embedding |
| `embedding_dimensions` | `smallint` | YES | Dimensi vektor (768) |
| `text_hash` | `varchar` | YES | Hash untuk cache |
| `created_at`, `updated_at` | `timestamp` | YES | Timestamps |

### `analyses`
| Kolom | Tipe Data | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint` | NO | Primary key |
| `user_id` | `bigint` | NO | Foreign key ke `users.id` |
| `rps_document_id` | `bigint` | NO | Foreign key ke `rps_documents.id` |
| `career_id` | `bigint` | NO | Foreign key ke `careers.id` |
| `status` | `varchar` | NO | `queued`, `processing`, `completed`, `failed` |
| `summary` | `jsonb` | YES | JSON agregasi: `{total_skills, covered, partial, missing, coverage_percentage}` |
| `model_versions` | `jsonb` | YES | Metadata versi model dan parameter matching |
| `error_message` | `text` | YES | Pesan error bila pipeline gagal |
| `created_at`, `updated_at` | `timestamp` | YES | Timestamps |

### `skill_matches`
| Kolom | Tipe Data | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint` | NO | Primary key |
| `analysis_id` | `bigint` | NO | Foreign key ke `analyses.id` |
| `skill_id` | `bigint` | NO | Foreign key ke `skills.id` |
| `demand_count` | `smallint` | NO | Frekuensi permintaan skill di lowongan terpilih |
| `source_job_skill_ids` | `jsonb` | NO | Array ID `job_skills` yang meminta skill ini |
| `course_skill_id` | `bigint` | YES | Foreign key ke `course_skills.id` yang paling cocok |
| `similarity_score` | `numeric` | YES | Skor kesamaan kosinus (0.00 - 1.00) |
| `classification` | `varchar` | NO | `covered`, `partial`, `missing` |
| `reason` | `text` | NO | Argumen reasoning klasifikasi dari AI |
| `evidence` | `text` | YES | Kutipan bukti |
| `candidates` | `jsonb` | NO | Top-K kandidat kompetensi terdekat |
| `judged_by` | `varchar` | NO | `rule`, `llm` |
| `created_at`, `updated_at` | `timestamp` | YES | Timestamps |

### `recommendations`
| Kolom | Tipe Data | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint` | NO | Primary key |
| `analysis_id` | `bigint` | NO | Foreign key ke `analyses.id` |
| `skill_match_id` | `bigint` | NO | Foreign key ke `skill_matches.id` |
| `title` | `varchar` | NO | Judul rekomendasi topik |
| `reason` | `text` | NO | Alasan pembelajaran |
| `priority` | `varchar` | NO | `high`, `medium`, `low` |
| `details` | `jsonb` | NO | `{topics: [...], learning_keywords: [...]}` |
| `created_at`, `updated_at` | `timestamp` | YES | Timestamps |

### `user_skills` (Skill Pribadi di Luar RPS / Klaim Pengguna)
| Kolom | Tipe Data | Nullable | Keterangan |
|---|---|---|---|
| `id` | `bigint` | NO | Primary key |
| `user_id` | `bigint` | NO | Foreign key ke `users.id` (ON DELETE CASCADE) |
| `skill_id` | `bigint` | NO | Foreign key ke `skills.id` (selalu dinormalisasi/kanonik; buat entri baru bila belum ada) |
| `skill` | `varchar` | NO | Nama skill sebagaimana diketik pengguna (tampil di UI: *"Dilaporkan sebagai"*) |
| `source_analysis_id` | `bigint` | YES | Foreign key ke `analyses.id` (analisis tempat skill ditambahkan lewat tombol *"Sudah saya pelajari"*; NULL bila dari profil) |
| `level` *(Opsi UI)* | `varchar` | YES | Tingkat kemahiran (`beginner`, `intermediate`, `advanced`) sesuai dropdown form profil |
| `created_at`, `updated_at` | `timestamp` | YES | Timestamps |
| **Constraint** | `UNIQUE(user_id, skill_id)` | — | Mencegah duplikasi skill kanonik per pengguna |

> **Catatan Arsitektur `user_skills`:**
> - Tanpa vektor embedding dan tanpa bukti dokumen PDF: ini murni klaim pengguna mandiri yang tidak diverifikasi.
> - Dicocokkan dengan kebutuhan industri lewat kesamaan `skill_id` saat query dibaca (runtime join), bukan disalin ke tabel `skill_matches`.
> - Tidak memengaruhi persentase *"Cakupan skill dalam RPS"* (hanya diberi penanda badge *"Dilaporkan"* pada Skill Gap).

---

## 4. Rekomendasi Solusi & Action Plan

Untuk menyelaraskan Desain dengan Backend, berikut langkah yang disarankan:

1. **Prioritas Cepat (Perbaikan Backend Minor):**
   - **`RpsDocumentResource.php`**: Tambahkan `'file_size' => $this->file_size` agar ukuran dokumen (misal: "2.4 MB") dapat ditampilkan di UI.
   - **`JobPostingController.php`**: Ubah query `index()` menjadi `with(['career', 'jobSkills.skillEntry'])` agar tag skill muncul di kartu lowongan pada halaman Pemetaan Industri.
   - **`RecommendationResource.php`**: Tambahkan properti `job_evidence` atau nama perusahaan asal lowongan agar subtitle kartu rekomendasi sesuai dengan desain.

2. **Penyesuaian Task T7.5 (Skill Mandiri & Profil):**
   - Buat migrasi tabel `user_skills` sesuai spesifikasi:
     ```sql
     CREATE TABLE user_skills (
         id BIGSERIAL PRIMARY KEY,
         user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
         skill_id BIGINT NOT NULL REFERENCES skills(id) ON DELETE CASCADE,
         skill VARCHAR(255) NOT NULL, -- nama mentah yang diketik pengguna (UI: 'Dilaporkan sebagai')
         source_analysis_id BIGINT NULL REFERENCES analyses(id) ON DELETE SET NULL,
         level VARCHAR(20) NULL DEFAULT 'intermediate', -- opsional untuk mendukung dropdown Tingkat di UI
         created_at TIMESTAMP,
         updated_at TIMESTAMP,
         CONSTRAINT uq_user_skills_user_skill UNIQUE (user_id, skill_id)
     );
     ```
   - Implementasikan CRUD `/api/me/skills` (GET, POST, DELETE). Saat POST, normalisasikan input ke `skills.id`, simpan teks asli ke `skill`, dan terima opsional `source_analysis_id` serta `level`.
   - Daftarkan route `/profile` di `routes/index.tsx` frontend.

3. **Penyesuaian UI Dashboard (Frontend):**
   - Untuk 3 kartu widget di atas: jika backend belum menyediakan endpoint stat khusus, frontend dapat mengagregasi data dari riwayat analisis terakhir (`GET /api/analyses`) dan daftar dokumen (`GET /api/rps`).
   - Kartu RPS di dashboard: Frontend dapat melakukan lookup ke riwayat analisis untuk memadukan persentase cakupan ke dalam kartu dokumen RPS.
