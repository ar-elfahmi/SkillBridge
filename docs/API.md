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
| POST | `/api/auth/register` | `name, email, password, password_confirmation` (role selalu `student`; field `role` dari klien diabaikan) |
| POST | `/api/auth/login` | `email, password` |
| POST | `/api/auth/logout` | |
| GET | `/api/auth/me` | profil (`role` selalu `student`) |

Alur SPA: `GET /sanctum/csrf-cookie` (204, set cookie `XSRF-TOKEN`) → kirim header `X-XSRF-TOKEN` dan `withCredentials: true` pada setiap request berikutnya. Sesi berbasis cookie; tidak ada token di body.

**User resource** (dipakai register, login, me):
```json
{ "data": { "id": 1, "name": "…", "email": "…", "role": "student", "created_at": "2026-11-10T08:00:00+00:00" } }
```

| Endpoint | Sukses | Error |
|---|---|---|
| `POST /api/auth/register` | **201** + user resource, langsung login | 422 (`email` sudah terdaftar, `password` ≥ 8 & `confirmed`), 429 |
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

Respons `POST /api/rps`:

| Kasus | Status | Body |
|---|---|---|
| Diterima | **202** | RPS resource (bentuk sama dengan `GET /api/rps/{id}`), `parse_status = pending`, `page_count` terisi; klien polling `GET /api/rps/{id}` |
| Tanpa berkas / bukan PDF (isi atau ekstensi) / PDF rusak / halaman > batas | **422** | `{ "message", "errors": { "file": ["…"] } }` |
| Ukuran > batas | **413** | `{ "message": "Ukuran PDF melebihi batas 10 MB.", "errors": { "file": ["…"] } }` |
| Duplikat | **409** | `{ "message": "RPS ini sudah pernah Anda unggah.", "existing_id": 12 }` |
| Rate limit (`SKILLBRIDGE_UPLOAD_PER_MINUTE`, default 10/menit per user) | **429** | `{ "message" }` |

Semua endpoint di atas memerlukan sesi Sanctum. Untuk endpoint dokumen T2.5, sesi tidak valid menghasilkan **401** dan ID yang tidak ada atau dimiliki pengguna lain menghasilkan **404**.

- `GET /api/rps` → **200** `{"data":[...]}`, dokumen milik pengguna saja, ID terbaru dahulu. Daftar kosong mengembalikan `{"data":[]}`.
- `GET /api/rps/{id}` → **200** `{"data": {...}}`.
- `PATCH /api/rps/{id}` → **200** dengan bentuk yang sama seperti detail. Kirim minimal satu dari lima field draft di tingkat teratas. Field yang dihilangkan mempertahankan nilai lama, dan `null` mengosongkan nilai. `name`, `code`, dan `study_program` berupa string maksimal 255 karakter atau `null`. `credits` dan `semester` berupa integer 0–32767 atau `null`. Payload kosong, field lain, nilai invalid, parse belum `parsed`, atau metadata sudah dikonfirmasi menghasilkan **422** (`message` dan `errors`). Koreksi yang berhasil menjadi draft dan menghapus pesan gagal metadata.
- `DELETE /api/rps/{id}` → **204** tanpa body setelah dokumen dan PDF privat terhapus. Halaman dan kompetensi terkait dihapus melalui cascade. Record `courses` dipertahankan. Kegagalan menghapus berkas menghasilkan **500** dengan pesan aman.

Upload, daftar, detail, dan PATCH memakai `RpsDocumentResource`. `course` berisi mata kuliah yang sudah tertaut, atau `null`. `detected_metadata` berisi draft dengan lima field nullable, atau `null` jika draft belum tersedia.

Polling detail setiap 2–3 detik mengikuti kondisi berikut.

| Kondisi | Tindakan |
|---|---|
| `parse_status` = `pending` atau `parsing` | Lanjutkan polling. |
| `parse_status` = `needs_ocr` atau `failed` | Hentikan polling dan tampilkan `error_message`. |
| `parsed`, draft dan `error_message` sama-sama `null` | Lanjutkan polling untuk upload baru. Gunakan batas tunggu UI agar dokumen lama yang belum pernah dijadwalkan dapat diedit manual. |
| `parsed`, `detected_metadata` berupa objek | Hentikan polling dan tampilkan draft, termasuk bila `code` atau semua field bernilai `null`. |
| `parsed`, draft `null`, `error_message` terisi | Hentikan polling, tampilkan pesan aman dan izinkan koreksi manual. |
| `parsed`, `course` terisi | Hentikan polling metadata dan tampilkan mata kuliah tertaut. |

`extraction_status` mengikuti ekstraksi kompetensi. Pada tahap draft, status tersebut masih `pending`.

Contoh respons `GET /api/rps/12`:
```json
{
  "data": {
    "id": 12,
    "original_name": "RPS Pemrograman Web.pdf",
    "parse_status": "parsed",
    "extraction_status": "pending",
    "review_status": "unreviewed",
    "course": null,
    "detected_metadata": { "code": null, "name": "Pemrograman Web", "credits": 2, "semester": 2, "study_program": "Teknik Informatika" },
    "page_count": 8,
    "error_message": null,
    "created_at": "2026-11-11T02:00:00+00:00"
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
| GET | `/api/jobs?career_id={id}` | Lowongan sesuai profesi (paginate). Sudah menyertakan relasi `career` dan `jobSkills` (array `skills`) agar kartu lowongan dapat menampilkan chip skill tanpa request tambahan. |
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
| GET | `/api/analyses/{id}/recommendations` | Rekomendasi belajar mahasiswa |
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

### Skill pribadi (milik pengguna yang login)
| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/api/me/skills` | Daftar skill pribadi |
| POST | `/api/me/skills` | Tambah: `{ "skill": "Docker", "analysis_id": 101? }`; dinormalisasi ke `skills`; duplikat idempoten (200 dengan data yang ada) |
| DELETE | `/api/me/skills/{id}` | Hapus skill pribadi (koreksi) |
| POST | `/api/analyses/{id}/recommendations/refresh` | Buat ulang rekomendasi dengan memperhitungkan skill pribadi terbaru. 202. Klasifikasi tidak dihitung ulang. |

`GET /api/analyses/{id}/recommendations` mengembalikan `meta.self_declared_skills`: daftar skill industri berstatus `missing`/`partial` yang sudah dilaporkan pengguna, dengan label "Dipelajari mandiri (dilaporkan pengguna)". Skill tersebut tidak muncul di `data`.

Contoh item rekomendasi:
```json
{
  "skill": "Docker",
  "priority": "high",
  "title": "Docker Fundamentals",
  "reason": "Tidak ditemukan dalam RPS yang dianalisis dan diminta pada 2 dari 2 lowongan.",
  "details": { "topics": ["Image & container", "Dockerfile", "docker compose"], "learning_keywords": ["docker getting started", "dockerfile best practices"] }
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
