# 10 — Skill Pribadi (Dilaporkan Pengguna)

**Sumber:** docs/API.md §5 · docs/AI_PIPELINE.md §7 · docs/TASKS.md T7.5 · docs/PRD.md §5
**Owner:** Orang B (backend) · Orang C (UI) · **Task:** T7.5
**Status:** ⏳ belum

## Prasyarat

- Tabel `user_skills` (T7.5) dan `SkillNormalizer` (T3.2) tersedia.
- `skill_matches` sudah ada (diagram 08) dan `recommendations` (diagram 09) untuk melihat efeknya.

## Diagram

```mermaid
sequenceDiagram
    autonumber
    actor M as Mahasiswa
    participant FE as Browser SPA
    participant API as Laravel API
    participant QW as Queue Worker
    participant DB as PostgreSQL

    Note over FE,DB: Tahap 1 — melihat dan menambah skill pribadi
    M->>FE: buka panel Skill Pribadi
    FE->>API: GET /api/me/skills
    API->>DB: load user_skills by user_id
    API-->>FE: 200 daftar skill pribadi
    M->>FE: tambah skill (opsional terkait analisis)
    FE->>API: POST /api/me/skills {skill, analysis_id?}
    API->>DB: normalisasi skill ke skills (canonical_name / aliases)
    alt skill sudah ada di skills
        API->>DB: insert user_skills bila belum ada
        API-->>FE: 200 skill yang ada (idempoten)
    else skill baru
        API->>DB: insert skills baru + user_skills
        API-->>FE: 200 skill
    end

    Note over FE,DB: Tahap 2 — koreksi
    M->>FE: hapus skill pribadi yang keliru
    FE->>API: DELETE /api/me/skills/{id}
    API->>DB: hapus user_skills (hanya milik pengguna)
    API-->>FE: 204

    Note over FE,DB: Tahap 3 — dampak pada hasil analisis
    M->>FE: buka /analyses/:id, tab Skill Gap
    FE->>API: GET /api/analyses/{id}/matches
    API->>DB: hitung self_declared per skill_id dari user_skills
    API-->>FE: 200 matches (self_declared=true bila skill ada di user_skills)

    Note over QW,DB: Tahap 4 — effect pada rekomendasi
    M->>FE: "Perbarui rekomendasi"
    FE->>API: POST /api/analyses/{id}/recommendations/refresh
    API->>QW: dispatch GenerateRecommendationsJob
    QW->>DB: exclude skill pribadi, buat ulang recommendations
```

## Aturan turunan

1. **`self_declared` dihitung saat dibaca**, bukan disimpan di `skill_matches`. Sumbernya `user_skills` milik pengguna yang sedang login.
2. **`self_declared` tidak mengubah `classification` maupun `summary`.** Analisis tetap menggambarkan cakupan RPS.
3. **Klasifikasi tetap sama** setelah menambah skill pribadi; hanya rekomendasi yang berubah (diagram 09).
4. **Normalisasi lewat `SkillNormalizer`** yang sama dengan sisi RPS dan lowongan, supaya skill pribadi bisa dicocokkan dengan `skill_id`.
5. **Idempoten**: menambah skill yang sama dua kali tidak menduplikasi baris dan mengembalikan data yang sama.
6. **Akses hanya pemilik**: daftar dan hapus hanya milik pengguna yang login; baris orang lain tidak terlihat.
7. **Bahasa UI**: "dilaporkan pengguna" atau "Dipelajari mandiri (dilaporkan pengguna)", bukan "dikuasai".
8. **Skill pribadi bukan kandidat analisis** dan tidak memengaruhi klasifikasi (diagram 08).
9. **Refresh rekomendasi idempotent**: ganti yang lama, klasifikasi tidak dihitung ulang.

## Catatan implementasi

- Tabel `user_skills`, endpoint `GET/POST/DELETE /api/me/skills`, dan `meta.self_declared_skills` belum ada (T7.5).
- Komponen UI: `SelfDeclaredSkillAction`, `SelfDeclaredSkillsPanel` (T7.5, milik Orang C).
- Kolom `analysis_id` pada `POST /api/me/skills` opsional: dipakai untuk memunculkan aksi cepat dari halaman analisis.
