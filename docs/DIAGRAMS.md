# Diagram Sequence — Panduan Implementasi

Diagram ini adalah **kontrak eksekusi** untuk implementasi backend & frontend: apa yang dipanggil, urutannya, siapa penanggung jawabnya, dan cabang mana yang wajib ada. Status `⏳ belum` = belum diimplementasikan (lihat `docs/TASKS.md`).

> Kalau kode dan diagram bertentangan, **diagram yang salah** atau **spec yang berubah**. Tepatkan di grup dulu, lalu perbarui diagramnya (TEAM §6 aturan 10: jangan biarkan AI menebak).

Status diagram: 2026-11-11.

## 1. Aturan pakai

1. Satu operasi backend = satu file diagram. Setiap diagram berdiri sendiri; baca diagram yang dirujuk sebelum mengoding.
2. Tiap langkah harus berupa "panggil X dengan Y → menghasilkan Z". Dilarang langkah kabur seperti "proses data" atau "jalankan pipeline".
3. Tiap langkah yang menyentuh AI wajib punya badge status dan rujukan `docs/AI_PIPELINE.md §N`.
4. Blok **Aturan turunan** di akhir file adalah bagian yang paling sering hilang saat diimplementasikan — jangan dihapus, jangan diganti ringkasan.
5. Perubahan spec → update diagram di PR yang sama (CLAUDE.md aturan 11).

## 2. Kamus lifeline (global, sama di semua diagram)

| Alias | Lifeline | Peran |
|---|---|---|
| `M` | Mahasiswa | aktor manusia |
| `FE` | Browser SPA | React (Vite), polling, CSRF |
| `API` | Laravel API | validasi, otorisasi, orkestrasi, tulis DB |
| `QW` | Queue Worker | Redis queue; menjalankan Job AI |
| `GF` | Gemini Flash | JSON terstruktur: ekstraksi, penilaian, rekomendasi |
| `GE` | Gemini Embedding | vektor 768 dimensi |
| `DB` | PostgreSQL | data + embedding JSONB |
| `DISK` | Private Disk | penyimpanan PDF privat (di luar web root) |

Aturan lifeline:
- `API` dan `QW` **tidak boleh digabung** — validasi/otorisasi di `API`, pemrosesan AI di `QW`.
- Lifeline yang tidak relevan di suatu diagram **tidak dideklarasikan** pada diagram itu (daftar per diagram mengikuti perannya). Bila lifeline aktif lalu mati di tengah alur, gunakan `deactivate`/`activate`.
- `Redis` tidak jadi lifeline terpisah (queue ditampilkan lewat `QW`), kecuali soal rate limiter/`WithoutOverlapping` perlu digambar.

## 3. Badge status

| Badge | Arti |
|---|---|
| `✅ ada` | sudah terimplementasikan di `main` |
| `⏳ belum` | belum ada; status diambil dari centang `docs/TASKS.md` |

Badge **tidak pernah** jadi sumber kebenaran — `docs/TASKS.md` yang jadi acuan. Badge wajib di-update di PR yang sama dengan task.

## 4. Daftar diagram

| # | Diagram | File | Task TASKS.md | Owner | Status implementasi |
|---|---|---|---|---|---|
| 01 | Auth | `diagrams/01-auth.md` | T1.1 | A | ✅ ada |
| 02 | Upload RPS | `diagrams/02-upload-rps.md` | T2.2 | A | ✅ ada |
| 03 | Parse RPS | `diagrams/03-parse-rps.md` | T2.3 | A | ✅ ada |
| 04 | Ekstraksi metadata | `diagrams/04-extract-metadata.md` | T2.5 | B | ⏳ belum |
| 05 | Ekstraksi kompetensi | `diagrams/05-extract-competencies.md` | T3.4 (+T3.2, T3.3, T4.3) | B | ⏳ belum |
| 06 | Review kompetensi | `diagrams/06-review-competencies.md` | T3.6 | B (+C UI) | ⏳ belum |
| 07 | Skill lowongan | `diagrams/07-job-skills.md` | T5.3 | B | ⏳ belum |
| 08 | Analisis & klasifikasi | `diagrams/08-run-analysis.md` | T6.2–T6.4 | B | ⏳ belum |
| 09 | Rekomendasi belajar | `diagrams/09-recommendations.md` | T7.2–T7.3 | B | ⏳ belum |
| 10 | Skill pribadi | `diagrams/10-self-declared-skills.md` | T7.5 | B (+C UI) | ⏳ belum |

> Seluruh 10 diagram sudah ditulis (v1, 2026-11-11) sebagai kontrak eksekusi. Badge status mengikuti centang `docs/TASKS.md`, jadi `01`–`03` berstatus `✅ ada` karena kodenya sudah ada; diagram `04`–`10` masih `⏳ belum`.
> Diagram `01`–`03` ditulis ulang dari kode yang sudah ada; diagram `04`–`10` ditulis dari spec (kontrak, bukan cermin kode).
> `08` dan `09` juga memuat sub-alur UI polling; layar di `docs/FRONTEND.md`.
> Audit terhadap kode (route, migration, action, config) menemukan selisih yang sudah dikoreksi pada 2026-11-11: urutan `rps_pages` vs `needs_ocr`, skip dokumen sudah diparse, delete+insert (bukan upsert), badge per langkah untuk endpoint yang belum ada, dan kolom review yang sebenarnya sudah tersedia.

## 5. Gap yang perlu diputuskan tim

Sudah terlihat dari spec — belum ada jawaban di `docs/`, jangan implementasi diam-diam:

1. **Guardrail "id kandidat tidak valid"** (`AI_PIPELINE.md §6`): kalau LLM mengembalikan `matched_course_skill_id` di luar top-K, apa yang dilakukan? Tolak hasil / fallback ke kandidat teratas / turunkan ke `partial`?
2. **Klasifikasi `missing` dari LLM dengan evidence valid**: apakah dibolehkan? (spec hanya membatasi "skor < floor tidak boleh selain missing", dan "evidence tak cocok → turunkan ke partial").
3. **Urutan dispatch `GenerateRecommendationsJob`**: otomatis dari `RunAnalysisJob` (ARCHITECTURE §5) atau endpoint terpisah? Pengaman: `POST /api/analyses/{id}/recommendations/refresh` ada, tapi trigger awalnya belum jelas.
4. **`analyses.status`**: kapan di-set `processing` — di dalam job atau saat dispatch endpoint?
5. **Metadata RPS diisi AI, bukan manual** — tapi `FRONTEND.md §2` masih menyebut "form metadata (nama, kode, SKS, semester, prodi)" di `/rps/upload`. Perlu memutuskan: form itu jadi isian opsional (pre-fill manual) atau dihapus; dokumen `FRONTEND.md` milik C untuk diperbarui.
6. **Penanda konfirmasi metadata**: tidak ada kolom di `DATABASE.md` yang menandai metadata sudah dikonfirmasi (mis. `metadata_confirmed_at`). Tanpa itu, "konfirmasi metadata" pada `POST /api/rps/{id}/extract` tidak bisa dibedakan dari sekadar trigger biasa.
7. ✅ **Sudah diputuskan — `courses` di-resolve saat konfirmasi, `courses.code` unik global.** Kode MK berbeda antar universitas, jadi `code` cukup jadi identitas: `POST /api/rps/{id}/extract` lookup `courses` by `code` dari `detected_metadata`, pakai baris yang ada (reuse), insert baru hanya kalau belum ada. `ExtractRpsMetadataJob` sendiri **tidak** menulis `courses` — hanya draft di `rps_documents.detected_metadata`. Nama boleh duplikat antar dokumen. Kompetensi menempel ke dokumen via `course_skills.rps_document_id`.
8. ✅ **Sudah diputuskan — analisis memakai `rps_document_id`, bukan `course_id`.** `analyses` (migration `2026_11_10_500000`) mereferensikan `rps_documents` + `career_id`; `skill_matches` hanya lewat `analysis_id`. Satu analisis = satu dokumen RPS + satu profesi, walau `courses` dipakai beberapa dokumen.
9. ✅ **Sudah diputuskan — ekstraksi kompetensi terikat pada satu dokumen.** `ExtractCompetenciesJob` bekerja pada `rps_document_id` dokumen yang sedang diproses (RPS yang baru diparsing). Metadata yang dipakai = `courses` yang tertaut lewat `rps_documents.course_id` (sudah terisi pada titik itu, karena konfirmasi = pemicu). `code` yang sama di dokumen lain tidak boleh ikut terambil.
10. ✅ **Sudah diputuskan — `courses` dibuat saat konfirmasi.** `ExtractRpsMetadataJob` menyimpan draft metadata di `rps_documents.detected_metadata`, bukan di `courses`. Baris `courses` dibuat/di-resolve hanya oleh `POST /api/rps/{id}/extract`. Konsekuensi: dokumen yang tidak pernah dikonfirmasi tidak pernah menghasilkan `courses`, jadi **tidak ada course yatim** dan tidak perlu aturan pembersihan. **Membutuhkan kolom baru** `rps_documents.detected_metadata` (JSONB, nullable) — belum ada di `docs/DATABASE.md` maupun migration; ubah di PR yang sama saat T2.5 dikerjakan.
12. **Import lowongan tidak memicu ekstraksi skill**: `skillbridge:import-jobs` (T5.2) mengisi `job_postings` tapi belum dispatch `ExtractJobSkillsJob` (T5.3). Perlu keputusan: dispatch dari perintah import, atau perintah ekstraksi terpisah.
13. **Status metadata tidak eksplisit**: `rps_documents` tidak punya kolom status metadata maupun penanda konfirmasi (mis. `metadata_confirmed_at`); pembeda saat ini hanya `course_id` terisi atau tidak, ditambah `review_status`.

## 6. Format file

```markdown
# NN — Nama operasi

**Sumber:** docs/API.md §N · docs/AI_PIPELINE.md §N
**Owner:** Orang X · **Task:** Tx.y
**Status:** ✅ ada / ⏳ belum

## Prasyarat
- ...

## Diagram
```mermaid
sequenceDiagram
  ...
```

## Aturan turunan
- ...
```
