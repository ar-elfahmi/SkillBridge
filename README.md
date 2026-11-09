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
