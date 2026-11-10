# Kontrak REST API (Laravel)

Base URL: `/api`. Semua endpoint kecuali register/login memerlukan autentikasi (Sanctum cookie SPA, CSRF via `GET /sanctum/csrf-cookie`). Respons JSON.

## 1. Konvensi
- Sukses: `{ "data": ... , "meta": {...}? }`
- Error: `{ "message": "...", "errors": { "field": ["..."] }? }` dengan status HTTP sesuai (401, 403, 404, 409, 413, 422, 429, 500).
- Pekerjaan asinkron mengembalikan **202 Accepted** + resource dengan status; klien melakukan polling `GET`.
- Akses dibatasi pemilik (Policy). Resource milik pengguna lain → 404.
- Rate limit: endpoint upload dan analisis dibatasi per pengguna (mis. 10/menit; atur di config).
- Pesan error untuk pengguna dalam bahasa Indonesia; field dan kode teknis bahasa Inggris.

## 2. Auth
| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/auth/register` | `name, email, password, password_confirmation, role` |
| POST | `/api/auth/login` | `email, password` |
| POST | `/api/auth/logout` | |
| GET | `/api/auth/me` | profil + role |

Alur SPA: `GET /sanctum/csrf-cookie` (204, set cookie `XSRF-TOKEN`) → kirim header `X-XSRF-TOKEN` dan `withCredentials: true` pada setiap request berikutnya. Sesi berbasis cookie; tidak ada token di body.

**User resource** (dipakai register, login, me):
```json
{ "data": { "id": 1, "name": "…", "email": "…", "role": "student", "created_at": "2026-11-10T08:00:00+00:00" } }
```

| Endpoint | Sukses | Error |
|---|---|---|
| `POST /api/auth/register` | **201** + user resource, langsung login | 422 (`email` sudah terdaftar, `password` ≥ 8 & `confirmed`, `role` ∈ `student`/`study_program`), 429 |
| `POST /api/auth/login` | **200** + user resource | 422 `errors.email = ["Email atau kata sandi salah."]`, 429 |
| `POST /api/auth/logout` | **204** | 401 |
| `GET /api/auth/me` | **200** + user resource | 401 `{"message":"Silakan masuk terlebih dahulu."}` |

Email dinormalisasi ke huruf kecil. Register & login dibatasi `skillbridge.rate_limits.auth_per_minute` (default 5/menit per email + IP).

## 3. RPS
| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/rps` | Upload PDF (`multipart/form-data`: `file`). 202. |
| GET | `/api/rps` | Daftar dokumen milik pengguna |
| GET | `/api/rps/{id}` | Status, metadata, error |
| PATCH | `/api/rps/{id}` | Edit metadata mata kuliah (`name, code, credits, semester, study_program`) |
| POST | `/api/rps/{id}/extract` | Konfirmasi metadata & mulai ekstraksi kompetensi. 202. |
| GET | `/api/rps/{id}/competencies` | Kompetensi hasil ekstraksi + bukti |
| PATCH | `/api/rps/{id}/competencies/{cid}` | Tinjau satu kompetensi: `review_status` (`confirmed\|rejected`) dan/atau edit `skill`, `competency` (set `user_edited`; embedding dibuat ulang). 200. |
| POST | `/api/rps/{id}/competencies` | Tambah kompetensi yang terlewat (`skill, competency`); `origin = user`, `evidence = null`, `verification_status = unverified`. 201. |
| DELETE | `/api/rps/{id}/competencies/{cid}` | Hapus kompetensi tambahan pengguna (hanya `origin = user`; untuk hasil AI gunakan `rejected`). |
| POST | `/api/rps/{id}/confirm-review` | Konfirmasi hasil ekstraksi: `review_status = confirmed`. 422 bila ekstraksi belum `completed` atau masih ada kompetensi `pending` yang belum ditinjau. |
| DELETE | `/api/rps/{id}` | Hapus dokumen & berkas |

Validasi upload: MIME `application/pdf` (cek isi, bukan hanya ekstensi), ukuran ≤ `SKILLBRIDGE_MAX_PDF_MB`, halaman ≤ `SKILLBRIDGE_MAX_PAGES`. Duplikat (`file_hash` sama untuk user yang sama) → 409 dengan id dokumen yang ada.

Contoh respons `GET /api/rps/12`:
```json
{
  "data": {
    "id": 12,
    "original_name": "RPS Pemrograman Web.pdf",
    "parse_status": "parsed",
    "extraction_status": "processing",
    "review_status": "unreviewed",
    "course": { "code": "24060511D21", "name": "Pemrograman Web", "credits": 2, "semester": 2, "study_program": "Teknik Informatika" },
    "page_count": 8,
    "error_message": null
  }
}
```

Contoh `GET /api/rps/12/competencies`:
```json
{
  "data": [
    {
      "id": 101,
      "skill": "PHP MVC",
      "canonical_skill": "MVC",
      "category": "concept",
      "competency": "Mampu menerapkan OOP pada pola MVC",
      "evidence": "Penerapan OOP pada pola MVC",
      "source_type": "Sub-CPMK",
      "source_reference": "Sub-CPMK 9",
      "week": 7,
      "page": 3,
      "verification_status": "verified",
      "origin": "ai",
      "review_status": "pending",
      "user_edited": false
    }
  ]
}
```
(Nilai pada contoh bersifat ilustrasi.)

Kompetensi `rejected` tetap dikembalikan (agar bisa dipulihkan) tetapi tidak dipakai analisis.

## 4. Industri
| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/api/careers` | Daftar profesi |
| GET | `/api/jobs?career_id={id}` | Lowongan sesuai profesi (paginate) |
| GET | `/api/jobs/{id}` | Detail lowongan + skill + bukti + sumber |
| POST | `/api/jobs` | Simpan lowongan manual & ekstrak skill. 202. |

Body `POST /api/jobs`:
```json
{
  "career_id": 2,
  "title": "Backend Developer",
  "company": "PT Contoh",
  "description": "Teks deskripsi/kualifikasi lowongan...",
  "source_url": "https://example.com/job/1"
}
```
`source_url` opsional. Teks lowongan dibatasi panjangnya (mis. 20.000 karakter).

## 5. Analisis
| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/analyses` | Mulai analisis. 202. |
| GET | `/api/analyses` | Riwayat analisis |
| GET | `/api/analyses/{id}` | Status + ringkasan |
| GET | `/api/analyses/{id}/matches` | Detail pemetaan per skill |
| GET | `/api/analyses/{id}/recommendations?audience=` | Rekomendasi (`student` / `study_program`; default sesuai role pengguna) |
| DELETE | `/api/analyses/{id}` | Hapus analisis |

Prasyarat: `rps_documents.extraction_status = completed`, `rps_documents.review_status = confirmed`, lowongan `extraction_status = completed`, semua lowongan memiliki `career_id` yang sama dengan `career_id` request, selain itu 422.

Request `POST /api/analyses`:
```json
{ "rps_document_id": 1, "career_id": 2, "job_posting_ids": [10, 11] }
```

Respons `GET /api/analyses/101` (struktur contoh, bukan hasil tervalidasi):
```json
{
  "data": {
    "analysis_id": 101,
    "status": "completed",
    "career": "Backend Developer",
    "course": "Pemrograman Web",
    "summary": {
      "total_skills": 12,
      "covered": 5,
      "partial": 1,
      "missing": 6,
      "coverage_percentage": 41.67
    },
    "model_versions": { "extraction_model": "...", "embedding_model": "gemini-embedding-001", "embedding_dimensions": 768, "prompt_version": "v1", "dataset_version": "..." }
  }
}
```

Contoh item `GET /api/analyses/101/matches`:
```json
{
  "data": [
    {
      "skill": "Laravel Framework",
      "demand_count": 2,
      "self_declared": false,
      "classification": "covered",
      "similarity_score": 0.92,
      "reason": "Sub-CPMK menyebut pengembangan aplikasi web dengan framework Laravel.",
      "matched_competency": {
        "id": 101,
        "competency": "Mampu mengembangkan aplikasi web menggunakan Laravel",
        "evidence": "Mahasiswa mampu mengembangkan aplikasi web menggunakan framework Laravel",
        "source_reference": "CPMK 2",
        "page": 3
      },
      "job_evidence": [
        { "job_posting_id": 10, "company": "PT Contoh", "evidence": "Pengalaman dengan Laravel" }
      ]
    }
  ]
}
```
Skor adalah ilustrasi dan **tidak** boleh ditampilkan sebagai persentase penguasaan.

`self_declared` dihitung saat dibaca: `true` bila `skill_id` ada pada `user_skills` milik pengguna yang sedang login. Nilai ini tidak mengubah `classification` dan `summary`.

### Skill pribadi (hanya role `student`, selain itu 403)
| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/api/me/skills` | Daftar skill pribadi |
| POST | `/api/me/skills` | Tambah: `{ "skill": "Docker", "analysis_id": 101? }`; dinormalisasi ke `skills`; duplikat idempoten (200 dengan data yang ada) |
| DELETE | `/api/me/skills/{id}` | Hapus skill pribadi (koreksi) |
| POST | `/api/analyses/{id}/recommendations/refresh` | Buat ulang rekomendasi `student` dengan memperhitungkan skill pribadi terbaru. 202. Klasifikasi tidak dihitung ulang. |

`GET /api/analyses/{id}/recommendations?audience=student` mengembalikan `meta.self_declared_skills`: daftar skill industri berstatus `missing`/`partial` yang sudah dilaporkan pengguna, dengan label "Dipelajari mandiri (dilaporkan pengguna)". Skill tersebut tidak muncul di `data`.

Contoh item rekomendasi `student`:
```json
{
  "skill": "Docker",
  "audience": "student",
  "priority": "high",
  "title": "Docker Fundamentals",
  "reason": "Tidak ditemukan dalam RPS yang dianalisis dan diminta pada 2 dari 2 lowongan.",
  "details": { "topics": ["Image & container", "Dockerfile", "docker compose"], "learning_keywords": ["docker getting started", "dockerfile best practices"] }
}
```

Contoh item rekomendasi `study_program`:
```json
{
  "skill": "REST API",
  "audience": "study_program",
  "priority": "high",
  "title": "Pengayaan materi Framework dan MVC",
  "reason": "Skill REST API belum tercakup; materi terdekat ada di minggu 7 (MVC).",
  "details": { "suggested_enrichment": "Tambahkan sesi desain endpoint REST dan format respons JSON.", "related_weeks": [7, 9], "related_sources": ["Sub-CPMK 9"] }
}
```

## 6. Kode error khusus
| Situasi | Status |
|---|---|
| File bukan PDF / terlalu besar / terlalu banyak halaman | 422 / 413 |
| PDF hasil scan tanpa text layer | 200 pada upload, `parse_status = needs_ocr` dengan `error_message` |
| Dokumen duplikat | 409 |
| Prasyarat analisis belum terpenuhi | 422 |
| Melebihi rate limit | 429 |
| Layanan AI gagal setelah retry | status `failed` + `error_message` aman (tanpa detail internal) |
