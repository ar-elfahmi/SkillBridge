# 04 — Ekstraksi Metadata Mata Kuliah (oleh AI)

**Sumber:** docs/AI_PIPELINE.md §2 · docs/API.md §3 · docs/DATABASE.md §`courses`, §`rps_documents`
**Owner:** Orang B · **Task:** T2.5
**Status:** ⏳ belum (parse T2.3 ✅ ada, `ai_runs` T2.4 belum)

## Prasyarat

- `rps_documents.parse_status = parsed` dan `rps_pages` terisi (diagram 03).
- `ExtractRpsMetadataJob` dipicu `ParseRpsJob` setelah parse sukses (`ARCHITECTURE.md` §5).
- `ai_runs` (T2.4) untuk pencatatan setiap panggilan Gemini.
- Metadata = atribut **mata kuliah** (`courses`), bukan atribut dokumen; dokumen mereferensikannya lewat `rps_documents.course_id`.

## Diagram

```mermaid
sequenceDiagram
    autonumber
    actor M as Mahasiswa
    participant FE as Browser SPA
    participant API as Laravel API
    participant QW as Queue Worker
    participant GF as Gemini Flash
    participant DB as PostgreSQL
    participant DISK as Private Disk

    Note over QW,GF: Tahap 0 — ekstraksi metadata otomatis (tanpa input manual)
    QW->>DB: load rps_documents + rps_pages (halaman awal)
    QW->>GF: prompt: ambil name, code, credits, semester, study_program (isi dibungkus delimiter document)
    GF-->>QW: JSON {name, code, credits, semester, study_program} (nilai tak ditemukan = null)
    QW->>QW: validasi skema metadata (wajib `code` terisi)
    Note over QW: gagal skema → retry maks 2 → status metadata = failed
    QW->>DB: catat ai_runs (purpose=rps_metadata, model, prompt_version, latency_ms)

    Note over QW,DB: Tahap 1 — simpan draft metadata di rps_documents (bukan `courses`)
    QW->>DB: update rps_documents.detected_metadata = JSON metadata hasil ekstraksi
    Note over QW,DB: Tidak ada baris `courses` dibuat di tahap ini. Course belum ada sampai mahasiswa mengonfirmasi (diagram 05).

    Note over FE,API: Tahap 2 — mahasiswa mengecek dan mengoreksi metadata
    M->>FE: buka /rps/:id, lihat metadata hasil ekstraksi AI
    M->>FE: koreksi bila perlu
    FE->>API: GET /api/rps/{id}
    API->>DB: load rps_documents + detected_metadata (+ course bila sudah tertaut)
    API-->>FE: 200 RPS resource (status, metadata, error)
    FE->>API: PATCH /api/rps/{id} {name, code, credits, semester, study_program}
    API->>DB: update rps_documents.detected_metadata (draft saja, belum menyentuh `courses`)
    alt code kosong / format tidak valid
        API-->>FE: 422 errors.code
    else valid
        API-->>FE: 200 RPS resource
    end

    Note over FE,API: Tahap 3 — hapus dokumen
    M->>FE: hapus dokumen
    FE->>API: DELETE /api/rps/{id}
    API->>DB: hapus rps_documents (cascade rps_pages, course_skills)
    API->>DISK: hapus berkas PDF
    API-->>FE: 204
```

## Aturan turunan

1. **Metadata diisi AI, bukan form manual.** `ExtractRpsMetadataJob` membaca halaman awal PDF; mahasiswa hanya mengecek dan mengoreksi lewat `PATCH /api/rps/{id}`.
2. **Ekstraksi metadata tidak membuat `courses`.** Job menyimpan hasil ke draft di `rps_documents.detected_metadata` (JSONB). `courses` dibuat **hanya** saat konfirmasi (diagram 05). Dokumen yang tidak pernah dikonfirmasi tidak meninggalkan baris `courses` yatim.
3. **Kode mata kuliah unik global.** `courses.code` tidak boleh duplikat; `code` cukup jadi identitas karena kode berbeda antar universitas. Saat **konfirmasi**, lookup `courses` by `code`: sudah ada → pakai baris itu (reuse), belum ada → insert baru. Nama boleh duplikat antar dokumen.
4. **Satu RPS → satu `courses`.** `rps_documents.course_id` diisi saat konfirmasi metadata (diagram 05), bukan saat ekstraksi.
5. **Prompt aman**: isi dokumen dibungkus delimiter dan dinyatakan sebagai DATA, bukan instruksi.
6. **Nilai tak ditemukan = `null`**, bukan tebakan. Metadata tidak lengkap tetap boleh jadi draft; konfirmasi `POST /api/rps/{id}/extract` yang menolak.
7. **Validasi mendahului penyimpanan**: skema gagal → retry maks 2 → status `failed` + `error_message` aman. Field `code` wajib terisi; draft tanpa `code` tidak bisa dikonfirmasi.
8. **Pencatatan AI** (T2.4): `ai_runs` dengan `purpose = rps_metadata`.
9. **Job idempotent**, `tries = 3`, backoff eksponensial, `failed()` menangani status.
10. **Hapus dokumen** cascade ke `rps_pages` dan `course_skills`; berkas PDF di disk ikut terhapus. `courses` **tidak** ikut terhapus: baris itu milik mata kuliah dan bisa dipakai dokumen lain.
11. **Akses hanya pemilik** (Policy). Dokumen milik orang lain → 404.

## Catatan implementasi

- `GET /api/rps` (daftar dokumen), `GET /api/rps/{id}`, `PATCH /api/rps/{id}`, `DELETE /api/rps/{id}` belum ada (T2.5).
- Migration unique `courses.code`: `backend/database/migrations/2026_11_10_100003_add_unique_code_to_courses_table.php`.
- **Butuh kolom baru** `rps_documents.detected_metadata` (JSONB, nullable) sebagai wadah draft metadata sebelum konfirmasi. Belum ada di `docs/DATABASE.md` maupun migration → usul ke A, tercatat sebagai gap `docs/DIAGRAMS.md §5`.
- **Gap:** tidak ada penanda metadata sudah dikonfirmasi (mis. `metadata_confirmed_at`); pembeda saat ini hanya `course_id` terisi atau tidak, ditambah `review_status`.
