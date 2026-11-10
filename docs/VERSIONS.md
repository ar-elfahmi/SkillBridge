# Versi Bahasa, Framework & Tools

Catatan versi yang dipakai SkillBridge. Tujuannya agar semua anggota tim (dan server VPS) memakai lingkungan yang sama.

- **Minimum** = syarat yang ditetapkan di file konfigurasi (`composer.json`, `docker-compose.yml`, dll.). Ini yang mengikat.
- **Terverifikasi** = versi yang benar-benar terpasang dan lulus `php artisan test` pada 2026-11-10. Hanya acuan, bukan syarat.
- Versi pasti dependency PHP ada di `backend/composer.lock`; jangan ubah tanpa `composer update` yang disengaja.

Perbarui file ini setiap kali menaikkan versi runtime, framework, atau layanan.

---

## 1. Runtime & bahasa

| Komponen | Minimum | Terverifikasi | Sumber |
|---|---|---|---|
| PHP | 8.3 | 8.4.22 | `backend/composer.json` (`"php": "^8.3"`) |
| Ekstensi PHP | `pdo_pgsql`, `mbstring`, `openssl`, `curl`, `zip` | ✓ semua aktif | `README.md` |
| Composer | 2.x | 2.10.1 | — |
| Node.js | _belum ditetapkan_ | 24.16.0 | — |
| npm | _belum ditetapkan_ | 12.0.2 | — |
| TypeScript | _belum ditetapkan_ (frontend belum di-scaffold) | — | — |

> Catatan: lokal memakai PHP 8.4, sedangkan minimum 8.3. Bila VPS memakai 8.3, jalankan test di 8.3 sebelum deploy.

## 2. Layanan (infrastruktur)

| Komponen | Minimum | Terverifikasi | Sumber |
|---|---|---|---|
| PostgreSQL | 17 | 17.10 | `docker-compose.yml` (`postgres:17-alpine`) |
| Redis | 7 | 7.4.10 | `docker-compose.yml` (`redis:7-alpine`) |
| Docker (dev lokal) | — | 29.9.2 | — |
| Nginx, PHP-FPM, systemd/Supervisor (VPS) | _belum ditetapkan_ | — | `docs/ARCHITECTURE.md` |

## 3. Backend — dependency PHP

| Paket | Constraint | Terkunci (`composer.lock`) | Fungsi |
|---|---|---|---|
| `laravel/framework` | ^13.17 | 13.40.0 | Framework REST API |
| `laravel/sanctum` | ^4.0 | 4.3.3 | Auth cookie SPA |
| `laravel/tinker` | ^3.0 | 3.0.2 | REPL |
| `predis/predis` | ^3.7 | 3.7.1 | Client Redis (tanpa ekstensi phpredis) |
| `smalot/pdfparser` | ^2.13 | 2.13.1 | Ekstraksi teks PDF per halaman |

**Dev / test**

| Paket | Constraint | Terkunci |
|---|---|---|
| `phpunit/phpunit` | ^12.5.12 | 12.5.43 |
| `laravel/pint` | ^1.27 | 1.32.1 |
| `mockery/mockery` | ^1.6 | 1.6.15 |
| `fakerphp/faker` | ^1.23 | 1.24.1 |
| `nunomaduro/collision` | ^8.6 | 8.9.5 |
| `laravel/pail` | ^1.2.5 | 1.2.7 |
| `laravel/pao` | ^1.0.6 | 1.1.5 |

**Aset bawaan Laravel** (`backend/package.json`, bukan aplikasi frontend): `vite` ^8.0.0, `tailwindcss` ^4.0.0, `@tailwindcss/vite` ^4.0.0, `laravel-vite-plugin` ^3.1.

## 4. Frontend

Belum di-scaffold (T0.1 frontend, milik C). Stack yang ditetapkan `CLAUDE.md`: React (Vite SPA), React Router, TypeScript strict, Tailwind CSS, shadcn/ui, TanStack Query. **Versi diisi oleh C** setelah scaffold, lalu kunci versi Node lewat `.nvmrc` dan `engines` di `frontend/package.json`.

| Paket | Constraint | Terkunci |
|---|---|---|
| react | _TBD_ | _TBD_ |
| react-router | _TBD_ | _TBD_ |
| typescript | _TBD_ | _TBD_ |
| vite | _TBD_ | _TBD_ |
| tailwindcss | _TBD_ | _TBD_ |
| @tanstack/react-query | _TBD_ | _TBD_ |

## 5. Layanan AI

Nama model **tidak** dikunci di kode maupun di sini; diatur lewat env dan dibaca via `config/skillbridge.php` (aturan `CLAUDE.md`). Versi model dan prompt yang dipakai tiap proses tercatat di tabel `ai_runs` dan kolom `analyses.model_versions`.

| Env | Nilai di `.env.example` | Catatan |
|---|---|---|
| `GEMINI_FLASH_MODEL` | _kosong_ (diisi B saat T2.4) | Gemini Flash; ekstraksi JSON & penilaian |
| `GEMINI_EMBEDDING_MODEL` | `gemini-embedding-001` | |
| `GEMINI_EMBEDDING_DIMENSIONS` | 768 | harus sama di kedua sisi perbandingan |

Nama model, harga, dan kuota Gemini bisa berubah; cek dokumentasi resmi saat mengimplementasi.

## 6. Cara mengecek versi lokal

```bash
php -v && composer -V && php -m        # PHP, Composer, ekstensi
cd backend && php artisan --version    # Laravel
composer show --locked                  # versi pasti dependency
node -v && npm -v
docker exec skillbridge-postgres postgres --version
docker exec skillbridge-redis redis-server --version
```
