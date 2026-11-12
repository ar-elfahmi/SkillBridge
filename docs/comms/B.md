# Komunikasi — Orang B (AI Pipeline)

> Hanya Orang B / agent B yang menulis di file ini. Protokol: `docs/comms/README.md`.

## Status
- [x] T2.4 `GeminiClient` + `ai_runs` — selesai, di-merge ke `main` (PR #20).
- [x] T2.5 `ExtractRpsMetadataJob` + endpoint RPS GET/PATCH/DELETE — selesai, di-merge ke `main` (PR #22).
- [x] T3.2 `SkillNormalizer` & T3.3 `EvidenceVerifier` — selesai, di-merge ke `main` (PR #23).
- [x] T3.4 `ExtractCompetenciesJob` + endpoint RPS extract & competencies — selesai, di-merge ke `main` (PR #24).
- [x] T4.1 `EmbeddingService`, T4.2 `Support/Cosine`, & T4.3 integrasi embedding — implementasi dan verifikasi selesai (branch `feat/T4.1-T4.3-embedding`); siap review & PR.

## Handoff

#### HO-B-006 · 2026-11-12 · T4.1, T4.2, T4.3 · untuk: B (T5.3, T6.2), A
- `Cosine` terpasang di `backend/app/Support/Cosine.php`:
  - `Cosine::similarity(array $a, array $b): float`: menghitung kemiripan kosinus numerik presisi tinggi dengan validasi dimensi, proteksi vektor nol, dan clamping `[-1.0, 1.0]`.
  - `Cosine::l2Normalize(array $vector): array`: normalisasi vektor ke panjang unit (norm L2 = 1.0).
  - `Cosine::dotProduct(array $a, array $b): float`: operasi *dot product* langsung untuk vektor ternormalisasi.
  - 11 unit test numerik lulus (`backend/tests/Unit/Support/CosineTest.php`).
- `EmbeddingService` terpasang di `backend/app/Modules/Ai/EmbeddingService.php`:
  - Menghasilkan vektor embedding melalui endpoint Gemini `:batchEmbedContents` (model: `gemini-embedding-001`, dimensi: 768, taskType: `SEMANTIC_SIMILARITY`).
  - Seluruh vektor dinormalisasi L2 (`Cosine::l2Normalize`) sebelum disimpan dan dikembalikan.
  - Caching cerdas lintas-tabel: memeriksa kecocokan `(text_hash, embedding_model, embedding_dimensions)` pada tabel `course_skills` dan `job_skills`. *Cache hit* tidak melakukan panggilan ke API eksternal. Perubahan model atau dimensi secara otomatis memicu pembuatan embedding baru.
  - Metode batching: `embed(string $text)`, `embedBatch(array $texts)`, `embedCourseSkills(iterable $courseSkills)`, dan `embedJobSkills(iterable $jobSkills)`.
  - Pencatatan audit ke `ai_runs` (`purpose = 'embedding'`).
  - 9 feature test komprehensif lulus (`backend/tests/Feature/Ai/EmbeddingServiceTest.php`).
- Integrasi ke `ExtractCompetenciesJob` (T4.3):
  - Memanggil `EmbeddingService::embedCourseSkills()` di akhir ekstraksi kompetensi RPS.
  - Format teks embedding: `"{canonical_skill}: {competency}"` (sesuai `docs/AI_PIPELINE.md §4`).
  - Mengisi atribut `embedding`, `embedding_model`, `embedding_dimensions`, dan `text_hash` pada model `CourseSkill`.
- Seluruh pengujian backend: Unit Support (11 tests), Feature AI (54 tests), Feature Competency (47 tests) lulus 100%. Pint `--test` lulus.
- Breaking change: tidak.

#### HO-B-005 · 2026-11-12 · T3.4 · untuk: A, C, B (T3.5, T4.3)
- `ExtractCompetenciesJob` terpasang di `backend/app/Modules/Competency/Jobs/ExtractCompetenciesJob.php`:
  - Membaca seluruh halaman `rps_pages` dalam chunks (15 halaman per chunk) dengan penanda `[[PAGE n]]`.
  - Menggunakan prompt template `backend/resources/prompts/rps-extract-v1.txt` dan Gemini Structured Outputs (`purpose = rps_extraction`, `prompt_version = rps-extract-v1`, `subject = RpsDocument`).
  - Memvalidasi skema respons JSON `{course, competencies: [{skill, category, competency, evidence, source_type, source_reference, week, page}]}`. Skema invalid menghasilkan retry otomatis dan bila gagal menjadi `extraction_status = failed` dengan pesan error yang aman.
  - Memverifikasi bukti via `EvidenceVerifier` (T3.3): bukti cocok menghasilkan `verification_status = verified` (dengan koreksi halaman); bukti fiktif/tidak ditemukan menghasilkan `unverified` dan `page = null` (tidak dipercaya).
  - Menormalisasi nama skill via `SkillNormalizer` (T3.2): memetakan ke entri `Skill` kanonik di database (case-insensitive & via aliases JSONB) atau membuat `Skill` baru secara otomatis.
  - Deduplikasi: menggabungkan item dengan pasangan `skill_id` + `source_reference` sama, memprioritaskan rekaman terverifikasi.
  - Menyimpan ke `course_skills` di dalam DB transaction secara idempoten (`origin = ai`, `review_status = pending`, `user_edited = false`).
  - Konfigurasi job: `tries = 3`, backoff `[5, 15, 30]`, middleware `WithoutOverlapping(rpsDocumentId)`.
- Endpoint terpasang di `backend/routes/api/rps.php` (ditangani `CompetencyController`):
  - `POST /api/rps/{id}/extract` (202 Accepted): Gerbang konfirmasi metadata. Membuat atau mencocokkan `courses` berdasarkan kode unik `code`, memperbarui `course_id`, `metadata_confirmed_at`, dan status `processing`, lalu mendispatch `ExtractCompetenciesJob`. Validasi: non-owner (404), belum parsed (422), kode kosong (422), sedang diproses (422).
  - `GET /api/rps/{id}/competencies` (200 OK): Mengembalikan daftar seluruh kompetensi dokumen RPS terurut ID, termasuk field `canonical_skill` dari model `Skill`, badge `verification_status`, `origin`, `review_status`, dan `user_edited` sesuai kontrak `docs/API.md §3`. Kompetensi `rejected` tetap disertakan.
- `CourseSkillResource` di `backend/app/Modules/Competency/Http/Resources/CourseSkillResource.php`.
- Seluruh 47 test di `backend/tests/Feature/Competency/` lulus (321 assertion). 62 test di `backend/tests/Feature/Rps/` lulus. Pint `--test` lulus.
- Breaking change: tidak; mengimplementasikan rute baru sesuai kontrak API.

#### HO-B-004 · 2026-11-12 · T3.2 & T3.3 · untuk: B (T3.4), A
- `SkillNormalizer` terpasang di `backend/app/Modules/Competency/Services/SkillNormalizer.php`:
  - Menormalisasi nama skill mentah ke model `Skill` kanonik: kolaps whitespace, in-memory cache per instans, case-insensitive match pada `canonical_name`, dan case-insensitive match pada array `aliases` JSONB PostgreSQL (`jsonb_array_elements_text`).
  - Jika belum ada di kamus, membuat record `Skill` baru secara otomatis dengan kategori opsional (`SkillCategory|string|null`) dan aliases kosong (`[]`), menangani concurrency / unique constraint violation secara anggun.
  - Menyediakan helper: `canonicalName(?string $rawName): ?string`, `skillId(?string $rawName): ?string`, dan `clearCache(): void`.
- `EvidenceVerifier` dan DTO `EvidenceVerificationResult` di `backend/app/Modules/Competency/Services/`:
  - Memverifikasi kutipan bukti teks terhadap isi dokumen RPS (`RpsDocument` atau koleksi `RpsPage`).
  - Normalisasi teks: smart quotes (`“”, «»` → `"`, `‘’` → `'`), dashes (`–, —, −` → `-`), kolaps whitespace berturut-turut.
  - Substring matching multi-strategi: memeriksa halaman yang diklaim (`claimedPageNumber`), fallback mencari ke seluruh halaman dokumen jika nomor halaman salah / kosong (mengoreksi nomor halaman dan status menjadi `verified`), dan mendeteksi halusinasi jika tidak ditemukan di halaman manapun (`status = unverified`, `page = null`).
  - Menghasilkan DTO immutable `EvidenceVerificationResult` dengan properti: `status` (`verified` / `unverified`), `matchedPageNumber`, `matchedText`, `normalizedEvidence`, dan `isVerified()`.
- Test komprehensif:
  - `backend/tests/Feature/Competency/SkillNormalizerTest.php` (9 test, 34 assertion): canonical match, alias match, new skill creation, in-memory caching, whitespace collapse, race condition handling.
  - `backend/tests/Feature/Competency/EvidenceVerifierTest.php` (11 test, 31 assertion): direct page match, smart quotes/dashes normalization, wrong page correction, missing page fallback, hallucination / unverified detection, empty/whitespace inputs.
  - Total `tests/Feature/Competency`: 30 test, 224 assertion lulus 100%. Pint `--test` lulus.
- Breaking change: tidak; service domain internal baru tanpa mengubah skema tabel maupun endpoint publik.

#### HO-B-003 · 2026-11-12 · T2.5 · untuk: A, C
- `ExtractRpsMetadataJob` terpasang di `app/Modules/Rps/Jobs/ExtractRpsMetadataJob.php`, dipicu otomatis oleh `ParseRpsJob` ketika `parse_status = parsed`. Mengekstrak metadata dari halaman awal dokumen via `GeminiClient` (`purpose = rps_metadata`, prompt `resources/prompts/rps-metadata-v1.txt`), memvalidasi skema `{code, name, credits, semester, study_program}`, dan menyimpan draft ke `rps_documents.detected_metadata`. Jika Gemini gagal, mencatat `error_message` aman dan mengizinkan pengisian manual.
- Route terpisah di `backend/routes/api/rps.php` (di-require oleh `backend/routes/api.php`):
  - `GET /api/rps` → daftar dokumen milik pengguna saja, urut ID terbaru dahulu (200).
  - `GET /api/rps/{id}` → detail dokumen dengan `detected_metadata` dan `course` (200, non-owner 404).
  - `PATCH /api/rps/{id}` → perbarui/koreksi field draft metadata (`code`, `name`, `credits`, `semester`, `study_program`). Validasi FormRequest `UpdateRpsMetadataRequest` menolak payload kosong / field asing / belum parsed / sudah dikonfirmasi (422), non-owner (404). Menghapus pesan error metadata sebelumnya.
  - `DELETE /api/rps/{id}` → hapus dokumen dan cascade halaman, hapus berkas PDF dari disk privat `Storage::disk('private')` (204). Record `courses` dipertahankan.
- `RpsDocumentResource` diperbarui: mengembalikan `detected_metadata` (5 field nullable) atau `null` jika draft belum diekstrak.
- Seluruh 179 test backend lulus (baseline 155 test + 24 test baru untuk T2.5, total 874 assertion). Pint `--test` lulus.
- Breaking change: tidak; sesuai kontrak `docs/API.md §3` yang sudah disepakati di PR #21.

#### HO-B-002 · 2026-11-10 · T2.4 · untuk: A, B
- Izin khusus pengguna: B boleh membuat migrasi baru `ai_runs`, model `AiRun`, dan dokumentasi skema terkait; commit/push branch task serta draft PR juga diizinkan. Tidak mencakup perubahan skema lain, merge, atau deployment.
- Tersedia `App\Modules\Ai\GeminiClient::generateContent()` berbasis Laravel Http, model/key/timeout dari config, maksimal 3 percobaan HTTP dengan backoff 1/2 detik untuk koneksi, 408/429/5xx. Kontrak parameter/hasil dan pembagian retry: `docs/AI_PIPELINE.md` bagian Klien Gemini.
- Migrasi baru `backend/database/migrations/2026_11_10_800000_create_ai_runs_table.php`; model `App\Models\AiRun` dengan `usage` cast array dan relasi `subject()`. Satu audit per percobaan, termasuk kegagalan; tanpa request tidak ada audit. Simpan hash input, penghitung token tersedia, dan kode error aman; tanpa key/prompt/respons mentah. Detail: `docs/DATABASE.md` bagian `ai_runs`.
- Migrasi/seeder lama dipertahankan. Belum menjalankan migrasi baru pada DB pengembangan; setelah branch tersedia, jalankan `php artisan migrate` untuk menambahkan `ai_runs`.
- Verifikasi PHP 8.4.12: platform Composer dari lockfile lulus; baseline 125 test/532 assertion, final seluruh backend **155 test/793 assertion lulus**. Pint `--test` pada lima file PHP task lulus; `git diff --check` lulus. Target DB telah diverifikasi `skillbridge_test` lokal; Redis sehat. Semua request Gemini test memakai `Http::fake` + pencegahan stray request, tanpa API nyata.
- Langkah integrasi berikutnya tetap task terpisah: T2.5 memanggil klien untuk metadata dan memvalidasi JSON/bukti; T4.1 menambahkan embedding. Tidak ada endpoint, job ekstraksi, atau perubahan config/dependensi dalam T2.4.

Breaking change: tidak; tabel dan class baru. Review manusia minimal satu reviewer dan squash merge tetap wajib.

## Permintaan
_(belum ada)_
