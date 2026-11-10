# 08 — Analisis & Klasifikasi Skill Gap

**Sumber:** docs/API.md §5 · docs/AI_PIPELINE.md §4, §6, §7
**Owner:** Orang B · **Task:** T6.2 `CandidateFinder`, T6.3 `MatchJudge` + guardrails, T6.4 `RunAnalysisJob` (+ T7.2 `GenerateRecommendationsJob` sebagai tahap lanjutan)
**Status:** ⏳ belum (T6.1 migrasi ✅ ada, T7.1 `PriorityCalculator` ✅ ada)

## Prasyarat

- `rps_documents.extraction_status = completed` **dan** `rps_documents.review_status = confirmed` (konfirmasi hasil ekstraksi, T3.6).
- Semua `job_postings` terpilih: `extraction_status = completed`.
- Semua `job_posting_ids` pada request punya `career_id` yang sama dengan `career_id` pada request.
- Melanggar salah satu → **422**.
- Endpoint: `POST /api/analyses` body `{ "rps_document_id", "career_id", "job_posting_ids": [...] }` → **202** + resource. Status dibaca lewat `GET /api/analyses/{id}` (polling 2–3 detik).

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

    Note over FE,API: Tahap 0 — pilih profesi & lowongan
    FE->>API: GET /api/careers
    API-->>FE: 200 daftar profesi
    FE->>API: GET /api/jobs?career_id={id}
    API-->>FE: 200 daftar lowongan (perusahaan, posisi, skill, sumber)
    M->>FE: pilih profesi + N lowongan
    FE->>API: POST /api/analyses {rps_document_id, career_id, job_posting_ids[]}

    Note over API,DB: Tahap 1 — validasi prasyarat (422 bila gagal)
    API->>DB: load rps_documents, job_postings terpilih
    API->>DB: insert analyses (status=queued) + analysis_job_postings
    API->>QW: dispatch RunAnalysisJob (idempotent, tries=3)
    API-->>FE: 202 resource analysis
    FE->>API: polling GET /api/analyses/{id} tiap 2-3 detik

    Note over QW,DB: Tahap 2 — himpun skill industri
    QW->>DB: load job_skills dari lowongan terpilih
    QW->>QW: group by skill_id → demand_count = jumlah lowongan yang meminta
    QW->>QW: pilih job_skill mewakil = requirement_text terpanjang

    Note over QW,DB: Tahap 3 — kandidat kompetensi RPS
    QW->>DB: load course_skills RPS yang punya embedding
    Note over QW: buang review_status=rejected, origin=user tetap masuk (unverified), user_skills TIDAK jadi kandidat

    Note over QW,GF: Tahap 4 — per skill industri (loop)
    loop untuk setiap skill industri unik
        QW->>QW: cosine similarity ke semua kandidat → top-K (config skillbridge.matching.top_k)
        alt skor tertinggi < sim_floor (skillbridge.matching.sim_floor)
            QW->>DB: upsert skill_matches (classification=missing, judged_by=rule, similarity_score, candidates=[], demand_count, source_job_skill_ids)
            Note over QW: tanpa panggilan LLM
        else skor tertinggi >= sim_floor
            QW->>GF: prompt match-judge-v1 (skill, requirement_text, kandidat {id, competency, evidence})
            GF-->>QW: JSON {classification, matched_course_skill_id, reason, evidence}
            Note over QW: Tahap 5 — guardrail Laravel (WAJIB, lihat aturan turunan)
            QW->>QW: guardrail: id kandidat valid, evidence substring, batas max partial, skor floor
            QW->>DB: upsert skill_matches (classification, course_skill_id, similarity_score, reason, evidence, candidates, demand_count, source_job_skill_ids, judged_by=llm)
        end
    end

    Note over QW,DB: Tahap 6 — ringkasan
    QW->>QW: total_skills = jumlah skill industri unik, hitung covered/partial/missing
    QW->>QW: coverage_percentage = covered / total_skills × 100 (2 desimal, partial tidak dihitung)
    QW->>DB: simpan analyses.summary + analyses.model_versions (model ekstraksi & penilaian, embedding model, dimensi, prompt_version, dataset_version, threshold)
    QW->>DB: catat ai_runs (purpose=match_judge per panggilan, purpose=embedding) ⏳ T2.4
    QW->>DB: analyses.status=completed
    Note over FE: polling berhenti saat completed/failed

    Note over QW,GF: Tahap 7 — rekomendasi belajar (T7.2, detail di diagram 09)
    QW->>GF: prompt reco-v1 per skill_match partial/missing
    GF-->>QW: JSON {title, reason, topics[], learning_keywords[]}
    QW->>QW: PriorityCalculator (Laravel, dari config) → priority high/medium/low
    QW->>DB: upsert recommendations (unique analysis_id + skill_match_id)
```

## Aturan turunan

1. **Prasyarat 422 persis empat butir**: `rps_documents.extraction_status = completed`; `rps_documents.review_status = confirmed`; semua lowongan terpilih `extraction_status = completed`; semua lowongan terpilih satu `career_id` yang sama dengan request.
2. **Satu analisis = satu RPS + satu profesi + satu atau lebih lowongan.** Skill yang sama dari beberapa lowongan digabung jadi satu baris `skill_matches` dengan `demand_count` (unique `analysis_id + skill_id`).
3. **Per aggregasi skill industri**: `demand_count` = jumlah lowongan terpilih yang meminta skill itu; vektor pembanding = `job_skill` dengan `requirement_text` paling informatif (terpanjang).
4. **Kandidat** = semua `course_skills` dari RPS terpilih yang punya embedding, **kecuali** `review_status = rejected`. Kompetensi `origin = user` ikut, tetapi `unverified` → hasil maksimal `partial`. `user_skills` **tidak** memengaruhi klasifikasi.
5. **Top-K dan floor dari config** (`skillbridge.matching.top_k`, `sim_floor`), bukan hardcode. Nilai env saat ini **placeholder, belum dikalibrasi** — jangan diklaim akurat.
6. **Skor < `sim_floor` → `missing` by rule**, `judged_by = rule`, tanpa panggilan LLM (hemat biaya).
7. **Guardrail (WAJIB, di Laravel — bukan di prompt)**:
   - `matched_course_skill_id` harus salah satu id kandidat top-K.
   - `evidence` harus substring dari bukti/teks kandidat terpilih; tidak cocok → turunkan ke `partial` dan kosongkan `evidence`.
   - Skill kategori `language|framework|tool`: bila nama teknologi atau alias-nya tidak muncul pada kompetensi/bukti kandidat → maksimal `partial`.
   - Kandidat `unverified` tidak boleh menghasilkan `covered` → maksimal `partial`.
   - Skor < `sim_floor` tidak boleh menghasilkan selain `missing`.
8. **Skor similarity = kandidat, bukan keputusan.** Tidak boleh ditampilkan sebagai persen penguasaan; label UI "Kemiripan semantik", hanya di panel detail.
9. **Coverage**: `coverage_percentage = covered / total_skills × 100`, dibulatkan 2 desimal. `partial` **tidak** dihitung sebagai covered. Label UI: "Cakupan skill dalam RPS".
10. **`model_versions` wajib diisi** di `analyses`: model ekstraksi, model penilaian, embedding model + dimensi, versi prompt, `dataset_version`, dan threshold yang dipakai.
11. **`ai_runs`**: setiap panggilan LLM dicatat (T2.4), termasuk yang dilewati karena skor di bawah floor (tidak ada panggilan → tidak ada baris; jangan mencatat panggilan palsu).
12. **Rekomendasi hanya untuk `partial`/`missing`**, kecuali skill yang `skill_id`-nya ada di `user_skills` pengguna (skill itu masuk `meta.self_declared_skills`, bukan `data`). Detail di diagram 09.
13. **Job wajib idempotent**, `tries = 3`, backoff eksponensial, `failed()` → `analyses.status = failed` + `error_message` aman.
14. **Copy UI**: "Tercakup dalam RPS", "Belum ditemukan dalam RPS". Jangan "Anda belum menguasai" / "persentase penguasaan".

## Catatan implementasi

- `CandidateFinder` (T6.2) = cosine + top-K. `Support/Cosine` (T4.2) menghitungnya; embedding di kedua sisi harus memakai model & dimensi yang sama.
- `MatchJudge` (T6.3) = pemanggilan LLM + guardrail; guardrail adalah kode Laravel, bukan prompt.
- Prompt `match-judge-v1` membungkus kandidat & requirement sebagai DATA di dalam delimiter `<candidates>` / `<requirement>`.
- `RunAnalysisJob` (T6.4) setelah selesai memicu `GenerateRecommendationsJob` (T7.2) — urutan dispatch masih menjadi gap (`docs/DIAGRAMS.md §5`).
