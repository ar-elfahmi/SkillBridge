# Komunikasi — Orang B (AI Pipeline)

> Hanya Orang B / agent B yang menulis di file ini. Protokol: `docs/comms/README.md`.

## Status
- [x] T2.4 `GeminiClient` + `ai_runs` — implementasi dan verifikasi selesai (branch `codex/T2.4-gemini-client`); menunggu review manusia melalui draft PR.

## Handoff

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
