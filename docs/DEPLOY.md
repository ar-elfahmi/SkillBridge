# Deploy dengan Docker (image siap pakai dari GHCR)

Image dibangun otomatis di GitHub Actions. Server hanya menarik image, tidak membangun apa pun. GitHub tidak punya akses ke server; redeploy dilakukan manual di server.

## Alur

1. Push/merge ke `main` memicu `.github/workflows/docker-image.yml`.
2. Workflow mendorong dua image ke GHCR, masing-masing bertag SHA commit (panjang) dan `latest`:
   - `ghcr.io/ar-elfahmi/skillbridge-api`: PHP-FPM, dipakai service `app` dan `worker` serta perintah artisan.
   - `ghcr.io/ar-elfahmi/skillbridge-web`: Nginx + build statis frontend (`VITE_API_URL=/api`, same-origin).
3. Di server: `./deploy.sh <sha>` (backup DB, pull, migrate, up, cek kesehatan, rollback tag bila gagal).

Pada PR, workflow hanya membangun (tanpa push).

## Isi `deploy/prod/`

| File | Fungsi |
| --- | --- |
| `docker-compose.yml` | web, app, worker, postgres, redis; batas memori/CPU; hanya `127.0.0.1:8080` yang terbuka |
| `.env.example` | Semua kredensial dan konfigurasi runtime; salin menjadi `.env` |
| `deploy.sh` | Redeploy manual dengan backup dan rollback |
| `nginx-host.conf.example` | Contoh proxy + TLS di Nginx host |

Estimasi RAM saat idle sekitar 120 MB untuk lima container (terukur lokal); batas total di compose sekitar 1,4 GB.

## Setup server (sekali)

Prasyarat: Docker + plugin Compose, Nginx host, domain yang mengarah ke server.

1. Buat folder dan salin isi `deploy/prod/` (mis. dengan `scp`) ke `/opt/skillbridge`.
2. `cp .env.example .env && chmod 600 .env`, lalu isi: `APP_KEY`, `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `DB_PASSWORD`, `REDIS_PASSWORD`, `GEMINI_API_KEY`, `GEMINI_FLASH_MODEL`.
   - `APP_KEY`: `docker run --rm ghcr.io/ar-elfahmi/skillbridge-api:<sha> php artisan key:generate --show`
   - Frontend dan API harus satu domain agar cookie Sanctum same-site dan tanpa CORS.
3. Package GHCR bersifat private bila repo private. Buat PAT (classic) dengan scope `read:packages` hanya untuk server, lalu `docker login ghcr.io -u <user>`.
4. Pasang konfigurasi Nginx host dari `nginx-host.conf.example`, lalu HTTPS dengan Certbot. Firewall: hanya 22, 80, 443.
5. Deploy pertama: `./deploy.sh <sha>`.
6. Seed data referensi (sekali; aman diulang). Jangan jalankan `db:seed` tanpa `--class`, karena `DatabaseSeeder` membuat akun `test@example.com`/`password`.
   ```bash
   docker compose run --rm --no-deps app php artisan db:seed --class=SkillSeeder --force
   docker compose run --rm --no-deps app php artisan db:seed --class=CareerSeeder --force
   ```
   Lowongan: impor lewat `skillbridge:import-jobs` sesuai `docs/PRD.md`. Berkas `parts/` (sekitar 47 MB) tidak masuk image.

## Redeploy

```bash
cd /opt/skillbridge
./deploy.sh <sha-commit>     # atau tanpa argumen untuk memakai IMAGE_TAG di .env
```

Rollback manual: `./deploy.sh <sha-lama>`. Rollback kode tidak membalik migrasi; migrasi harus kompatibel dengan versi sebelumnya, atau restore dari `backups/db-*.sql.gz`.

## Keamanan

- Tidak ada secret di image atau repo. `.env` hanya ada di server.
- `VITE_*` tertanam di bundle dan terlihat browser. Satu-satunya nilai yang dipakai adalah `/api`.
- Container berjalan sebagai non-root. Postgres dan Redis tidak punya port ke host.
- Nginx container hanya mempercayai `X-Forwarded-For` dari jaringan privat; Nginx host harus menimpanya dengan `$remote_addr` (sudah di contoh).
- Aksi GitHub dipin ke SHA commit. Perbarui berkala.

## Belum tercakup

- Backup terjadwal (cron `deploy.sh`-style untuk `pg_dump` dan volume `pdf-storage`) dan penyalinan ke luar server.
- Test backend di CI. Workflow ini hanya membangun image; image `latest` tidak menjamin test lulus.
- Pemantauan dan rotasi log (Docker `json-file` memakai batas bawaan).
