# 07 — Skill Lowongan Kerja

**Sumber:** docs/API.md §4 · docs/AI_PIPELINE.md §5, §4 · docs/DATABASE.md §`job_postings`, §`job_skills`
**Owner:** Orang B · **Task:** T5.3 (frontend T5.4 belum)
**Status:** ⏳ belum (migrasi T5.1 ✅ ada, import T5.2 ✅ ada)

## Prasyarat

- `careers` terisi (seeder profesi T5.1).
- `ExtractJobSkillsJob` memakai prompt `job-extract-v1` dan `EmbeddingService` (T4.1).
- `POST /api/jobs` menerima `career_id`, `title`, `company`, `description`, `source_url` opsional; teks dibatasi (mis. 20.000 karakter).

## Diagram

```mermaid
sequenceDiagram
    autonumber
    actor M as Mahasiswa
    participant FE as Browser SPA
    participant API as Laravel API
    participant QW as Queue Worker
    participant GF as Gemini Flash
    participant GE as Gemini Embedding
    participant DB as PostgreSQL

    Note over FE,DB: Tahap 0 — memilih profesi dan melihat lowongan
    M->>FE: pilih profesi
    FE->>API: GET /api/careers
    API->>DB: load careers
    API-->>FE: 200 daftar profesi
    FE->>API: GET /api/jobs?career_id={id}
    API->>DB: load job_postings by career_id (paginate)
    API-->>FE: 200 daftar lowongan

    Note over FE,DB: Tahap 1 — simpan lowongan manual
    M->>FE: isi form lowongan (profesi, perusahaan, posisi, deskripsi, sumber)
    FE->>API: POST /api/jobs {career_id, title, company, description, source_url}
    API->>API: validasi (career_id ada, panjang teks, source_url opsional)
    API->>DB: insert job_postings (extraction_status=pending)
    API->>API: dispatch ExtractJobSkillsJob (idempotent, tries=3)
    API-->>FE: 202 job resource
    FE->>API: polling status ekstraksi lowongan

    Note over QW,GE: Tahap 2 — ekstraksi skill lowongan
    QW->>DB: load job_postings (extraction_status=pending)
    QW->>GF: prompt job-extract-v1 + teks lowongan dibungkus delimiter job
    GF-->>QW: JSON {skills[]: skill, category, requirement_text, evidence}
    QW->>QW: validasi skema (field wajib, enum category)
    Note over QW: gagal skema → retry maks 2 → extraction_status=failed
    loop tiap skill hasil LLM
        QW->>QW: verifikasi bukti: evidence substring teks lowongan
        QW->>DB: normalisasi ke skills (canonical_name / aliases)
    end
    QW->>DB: insert job_skills (evidence, requirement_text, skill_id)
    QW->>DB: catat ai_runs (purpose=job_extraction, prompt_version=job-extract-v1)

    Note over QW,GE: Tahap 3 — embedding skill lowongan
    QW->>DB: cek cache embedding (text_hash + model + dimensi)
    QW->>GE: batch embed "{canonical_skill}: {requirement_text}" (768 dim)
    GE-->>QW: vektor float[]
    QW->>DB: simpan embedding + model + dimensi + text_hash
    QW->>DB: set extraction_status=completed

    Note over FE,DB: Tahap 4 — detail lowongan
    M->>FE: buka detail lowongan
    FE->>API: GET /api/jobs/{id}
    API->>DB: load job_postings + job_skills
    API-->>FE: 200 lowongan + skill + bukti + sumber
```

## Aturan turunan

1. **Prompt aman**: teks lowongan dibungkus delimiter dan dinyatakan sebagai DATA, bukan instruksi.
2. **Satu skill per item.** Daftar teknologi dipisah menjadi item terpisah (Laravel, REST API, Redis, Docker).
3. **Bukti wajib persis** dari teks lowongan, diverifikasi sebagai substring; tidak cocok → `unverified`.
4. **Normalisasi ke kamus `skills`** yang sama dengan sisi RPS (T3.2), supaya perbandingan bermakna.
5. **Embedding sisi lowongan** memakai teks deskripsi (`"{canonical_skill}: {requirement_text}"`), bukan nama skill saja, dengan model dan dimensi yang sama seperti sisi RPS.
6. **Cache embedding** dipakai lintas `course_skills` dan `job_skills` lewat `text_hash + model + dimensi`.
7. **Soft skill umum dan benefit diabaikan**, kecuali berbentuk kompetensi teknis.
8. **Pencatatan AI** di `ai_runs` (`purpose = job_extraction`).
9. **Job idempotent**, `tries = 3`, backoff eksponensial, `failed()` → `extraction_status = failed` + pesan aman.

## Catatan implementasi

- `GET /api/careers`, `GET /api/jobs`, `GET /api/jobs/{id}`, `POST /api/jobs` belum ada (T5.3).
- Perintah `skillbridge:import-jobs` (T5.2) sudah bisa mengisi `job_postings` dari CSV/JSON. **Gap:** import itu belum memicu `ExtractJobSkillsJob`, jadi lowongan hasil import tidak punya skill sampai ada pemicu lain.
- Teks lowongan diproses sebagai data tidak tepercaya: panjang dibatasi dan tidak boleh masuk log.
