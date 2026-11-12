# Komunikasi — Orang B (AI Pipeline)

> Hanya Orang B / agent B yang menulis di file ini. Protokol: `docs/comms/README.md`.

## Status
- [x] T2.4 `GeminiClient` + `ai_runs` — selesai, di-merge ke `main` (PR #20).
- [x] T2.5 `ExtractRpsMetadataJob` + endpoint RPS GET/PATCH/DELETE — implementasi dan verifikasi selesai (branch `feat/T2.5-rps-metadata`); siap review & PR.

## Handoff

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
