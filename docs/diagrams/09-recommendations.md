# 09 — Rekomendasi Belajar Mahasiswa

**Sumber:** docs/API.md §5 · docs/AI_PIPELINE.md §7 · docs/DATABASE.md §`recommendations`
**Owner:** Orang B · **Task:** T7.2, T7.3 (frontend T7.4 belum)
**Status:** ⏳ belum (`PriorityCalculator` T7.1 ✅ ada)

## Prasyarat

- `skill_matches` sudah ada dan tervalidasi dari analisis (`analyses.status = completed`, diagram 08).
- `GenerateRecommendationsJob` memakai prompt `reco-v1`; ambang prioritas di `config('skillbridge.recommendation.priority')`.
- `user_skills` (T7.5) untuk pengecualian skill pribadi — lihat diagram 10.

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

    Note over FE,DB: Tahap 1 — pengguna membuka rekomendasi
    M->>FE: buka tab Rekomendasi pada /analyses/:id
    FE->>API: GET /api/analyses/{id}/recommendations
    API->>DB: load recommendations by analysis_id
    API->>DB: load user_skills milik pengguna
    API-->>FE: 200 {data: rekomendasi, meta.self_declared_skills}

    Note over QW,GF: Tahap 2 — buat rekomendasi (otomatis atau refresh)
    QW->>DB: load skill_matches tervalidasi + data RPS dan lowongan terkait
    QW->>DB: exclude skill yang ada di user_skills pengguna
    QW->>DB: hitung prioritas via PriorityCalculator
    Note over QW: missing dan diminta ≥ 50% lowongan → high, missing lain → medium, partial → medium bila ≥ 50% selain itu low, covered → tidak ada rekomendasi
    loop tiap skill yang perlu rekomendasi
        QW->>GF: prompt reco-v1 + konteks skill_match dibungkus delimiter data
        GF-->>QW: JSON {title, reason, topics[], learning_keywords[]}
        QW->>QW: validasi skema, pastikan tidak ada URL
        Note over QW: gagal skema → retry maks 2 → lewati skill ini
    end
    QW->>DB: replace recommendations (idempotent)
    QW->>DB: catat ai_runs (purpose=recommendation, prompt_version=reco-v1)

    Note over FE,API: Tahap 3 — refresh setelah skill pribadi berubah
    M->>FE: "Perbarui rekomendasi"
    FE->>API: POST /api/analyses/{id}/recommendations/refresh
    API->>API: dispatch GenerateRecommendationsJob
    API-->>FE: 202
```

## Aturan turunan

1. **Input hanya `skill_matches` yang sudah divalidasi.** Data RPS dan lowongan hanya sebagai konteks pendukung.
2. **Prioritas dihitung Laravel**, bukan LLM. LLM hanya menyusun `title`, `reason`, `topics`, `learning_keywords`.
3. **Tidak boleh ada URL.** Rekomendasi berisi topik dan kata kunci belajar saja, tanpa tautan yang tidak diverifikasi.
4. **Skill pribadi dikeluarkan.** Skill yang ada di `user_skills` tidak dibuatkan rekomendasi; muncul di `meta.self_declared_skills` dengan label "Dipelajari mandiri (dilaporkan pengguna)".
5. **Refresh idempotent**: rekomendasi lama diganti, bukan ditumpuk. Klasifikasi dan `summary` **tidak** dihitung ulang.
6. **Bahasa aman untuk mahasiswa**: jangan menyiratkan mahasiswa belum menguasai skill; nyatakan hanya bahwa skill belum ditemukan di RPS.
7. **Pencatatan AI** di `ai_runs` (`purpose = recommendation`).
8. **Job idempotent**, `tries = 3`, backoff eksponensial, `failed()` tidak boleh meninggalkan rekomendasi setengah jadi.

## Catatan implementasi

- `PriorityCalculator` sudah ada (T7.1) dengan ambang dari `config('skillbridge.recommendation.priority')` (`SKILLBRIDGE_PRIORITY_HIGH_DEMAND_RATIO`, default 0.5).
- Penanda skill "wajib (*required*)" dari PRD §5.3 belum dipakai karena `job_skills` tidak menyimpannya.
- **Gap:** urutan dispatch `GenerateRecommendationsJob` belum jelas — otomatis dari `RunAnalysisJob` atau hanya lewat endpoint refresh; lihat `docs/DIAGRAMS.md §5`.
- `GET /api/analyses/{id}/recommendations` dan `POST .../recommendations/refresh` belum ada (T7.3, T7.5).
