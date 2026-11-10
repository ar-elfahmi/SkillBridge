# SkillBridge — Paket Dokumentasi untuk Vibe Coding

Letakkan seluruh isi paket ini di root repo (`CLAUDE.md` di root, sisanya di `docs/`), lalu buka Claude Code di folder tersebut.

| File | Isi |
|---|---|
| `CLAUDE.md` | Instruksi utama untuk Claude Code: stack, aturan wajib, konvensi |
| `docs/PRD.md` | Produk, 4 fitur, alur, batasan, kriteria MVP, keputusan & asumsi |
| `docs/ARCHITECTURE.md` | Arsitektur, modul, job/queue, env, deployment |
| `docs/DATABASE.md` | Skema PostgreSQL lengkap |
| `docs/API.md` | Kontrak REST + contoh |
| `docs/AI_PIPELINE.md` | Ekstraksi, embedding, matching, klasifikasi, rekomendasi, prompt |
| `docs/FRONTEND.md` | Halaman, komponen, aturan kata-kata UI |
| `docs/SECURITY.md` | Keamanan & privasi |
| `docs/TESTING.md` | Strategi test, evaluasi, kalibrasi threshold |
| `docs/TASKS.md` | Rencana kerja bertahap + template prompt |

Mulai dari `docs/TASKS.md` task **T0.1**.

## Menjalankan backend lokal

Prasyarat: PHP 8.3+ (ekstensi `pdo_pgsql`), Composer, Docker Desktop.

```bash
# dari root repo: PostgreSQL 17 + Redis 7 (db/user/password: skillbridge, plus db skillbridge_test untuk test)
docker compose up -d

cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve                      # http://localhost:8000 (health: /up)
php artisan queue:work redis --tries=3 # terminal terpisah
php artisan test                       # butuh Postgres + Redis aktif
```

Isi `GEMINI_API_KEY` dan `GEMINI_FLASH_MODEL` di `backend/.env` (jangan di-commit).

Upload RPS hingga `SKILLBRIDGE_MAX_PDF_MB` (default 10 MB) butuh batas PHP yang cukup. Default PHP (`upload_max_filesize=2M`, `post_max_size=8M`) akan menolak PDF besar sebelum sampai ke Laravel. Set di `php.ini` (dev) dan PHP-FPM (produksi), plus `client_max_body_size` di Nginx:

```ini
upload_max_filesize = 12M
post_max_size = 12M
```

## Impor lowongan terkurasi

```bash
cd backend
php artisan skillbridge:import-jobs path/ke/lowongan.csv --dataset-version=2026-11 --dry-run   # validasi dulu
php artisan skillbridge:import-jobs path/ke/lowongan.csv --dataset-version=2026-11
```

Kolom CSV/JSON: `career` (slug profesi: `backend-developer`, `frontend-developer`, `full-stack-developer`, `data-analyst`), `company`, `title`, `description` (maks 20.000 karakter), opsional `qualifications`, `source_name`, `source_url` (http/https), `collected_at` (`YYYY-MM-DD`), `dataset_version`. JSON boleh berupa array atau `{ "jobs": [...] }`. Satu baris tidak valid → seluruh impor dibatalkan. Impor ulang baris yang sama (profesi + perusahaan + posisi + `source_url` + `dataset_version`) memperbarui, bukan menduplikasi.

`php artisan db:seed` menambahkan 8 lowongan **data contoh** (`backend/database/data/sample_jobs.json`, perusahaan fiktif, tanpa `source_url`) untuk demo.
