# 06 — Tinjau & Konfirmasi Kompetensi

**Sumber:** docs/API.md §3 · docs/AI_PIPELINE.md §3, §6 · docs/DATABASE.md §`course_skills`, §`rps_documents`
**Owner:** Orang B (backend) · Orang C (UI) · **Task:** T3.6 (frontend T3.5)
**Status:** ⏳ belum (ekstraksi T3.4 belum ada)

## Prasyarat

- `rps_documents.extraction_status = completed` dan `course_skills` terisi (diagram 05).
- Kolom `course_skills.origin`, `review_status`, `user_edited` serta `rps_documents.review_status`, `reviewed_at` (T3.6).
- `SkillNormalizer` (T3.2) dan `EmbeddingService` (T4.1) untuk kasus tambah/edit.

## Diagram

```mermaid
sequenceDiagram
    autonumber
    actor M as Mahasiswa
    participant FE as Browser SPA
    participant API as Laravel API
    participant QW as Queue Worker
    participant GE as Gemini Embedding
    participant DB as PostgreSQL

    Note over FE,DB: Tahap 1 — daftar kompetensi hasil ekstraksi
    M->>FE: buka /rps/:id, tab kompetensi
    FE->>API: GET /api/rps/{id}/competencies
    API->>DB: load course_skills by rps_document_id
    API-->>FE: 200 competencies (skill, canonical_skill, competency, evidence, source_type, source_reference, week, page, verification_status, origin, review_status, user_edited)

    Note over FE,GE: Tahap 2 — tinjau satu kompetensi
    M->>FE: set status (confirmed / rejected) atau ubah skill / competency
    FE->>API: PATCH /api/rps/{id}/competencies/{cid} {review_status, skill?, competency?}
    API->>DB: update course_skills (review_status)
    alt skill atau competency diubah
        API->>DB: set user_edited=true
        API->>DB: normalisasi skill baru (SkillNormalizer)
        API->>GE: embed "{canonical_skill}: {competency}" (cache by text_hash + model + dimensi)
        GE-->>API: vektor float[]
        API->>DB: simpan embedding + text_hash baru
    end
    API-->>FE: 200 competency

    Note over FE,DB: Tahap 3 — tambah kompetensi yang terlewat
    M->>FE: tambah kompetensi manual
    FE->>API: POST /api/rps/{id}/competencies {skill, competency}
    API->>DB: normalisasi skill, insert course_skills (origin=user, evidence=null, verification_status=unverified, review_status=pending)
    API-->>FE: 201 competency

    Note over FE,DB: Tahap 4 — hapus kompetensi tambahan milik sendiri
    FE->>API: DELETE /api/rps/{id}/competencies/{cid}
    alt origin=user
        API->>DB: hapus course_skills
        API-->>FE: 204
    else origin=ai
        API-->>FE: 422 (gunakan review_status=rejected)
    end

    Note over FE,DB: Tahap 5 — konfirmasi hasil ekstraksi
    M->>FE: "Konfirmasi hasil ekstraksi"
    FE->>API: POST /api/rps/{id}/confirm-review
    API->>DB: cek extraction_status=completed
    API->>DB: hitung competencies dengan review_status=pending
    alt masih ada yang pending
        API-->>FE: 422 {message}
    else semua sudah ditinjau
        API->>DB: set rps_documents.review_status=confirmed, reviewed_at=now
        API-->>FE: 200 RPS resource
    end
```

## Aturan turunan

1. **`rejected` tetap dikirim ke klien** agar bisa dipulihkan, tetapi **tidak** dipakai sebagai kandidat analisis (diagram 08).
2. **Kompetensi `origin = user`** punya `evidence = null` dan `verification_status = unverified` → maksimal klasifikasi `partial`.
3. **Edit memicu embedding ulang** dengan teks `"{canonical_skill}: {competency}"`; `user_edited = true` menandai baris yang berubah.
4. **Hapus hanya berlaku untuk `origin = user`.** Hasil AI dibuang lewat `review_status = rejected`, bukan dihapus.
5. **Konfirmasi butuh dua syarat**: `extraction_status = completed` **dan** tidak ada kompetensi `pending`. Gagal → 422 dengan pesan ramah.
6. **Analisis mensyaratkan `review_status = confirmed`** pada dokumen RPS; jika belum → 422 (diagram 08).
7. **Akses hanya pemilik** (Policy); dokumen/kompetensi milik orang lain → 404.
8. **Bukti tidak boleh diedit menjadi bukti baru.** `evidence` milik hasil AI tetap apa adanya; koreksi hanya pada `skill` dan `competency`.

## Catatan implementasi

- Kolom & enum untuk review **sudah ada**: `course_skills.origin`, `course_skills.review_status`, `course_skills.user_edited`, `rps_documents.review_status`, `rps_documents.reviewed_at` (migration + `App\Enums\CompetencyOrigin`, `CompetencyReviewStatus`, `App\Modules\Rps\Enums\ReviewStatus`). T3.6 yang tersisa: endpoint review (`PATCH/POST/DELETE .../competencies`, `POST /api/rps/{id}/confirm-review`), aturan status `confirmed`, dan integrasi ke `RunAnalysisJob`.
- Embedding ulang saat edit bisa dijalankan inline pada request ( seperti di atas) atau lewat job, asalkan model dan dimensi sama dengan sisi lowongan.
- Komponen UI: `CompetencyReviewTable`, badge status verifikasi, tombol konfirmasi (T3.5, milik Orang C).
