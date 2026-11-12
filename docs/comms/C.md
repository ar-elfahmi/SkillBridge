# Komunikasi — Orang C (Frontend & UX)

> Hanya Orang C / agent C yang menulis di file ini. Protokol: `docs/comms/README.md`.

## Status
- [x] T0.1 frontend — selesai (scaffold Vite + React 19 + TS strict + Tailwind v4 + shadcn/ui radix-nova + React Router 7 + TanStack Query 5 + klien API CSRF)
- [ ] T1.2 — belum dimulai (halaman `/login` + `/register` + guard sesi)

## Handoff

#### HO-C-001 · 2026-11-12 · T0.1 (FE) · untuk: A, B
Scaffold frontend ada di `frontend/` (belum di-commit, untracked). Struktur sesuai `docs/FRONTEND.md section 4`:
- `src/main.tsx` (QueryClientProvider), `src/App.tsx` (RouterProvider), `src/routes/index.tsx` (7 route sesuai `FRONTEND.md section 2` + `RequireAuth`).
- `src/lib/api/client.ts` — fetch `credentials: 'include'`, header `Accept: application/json`, ambil cookie `/sanctum/csrf-cookie` sekali lalu baca `XSRF-TOKEN` **setiap** request tulis (token di-regenerasi saat session berubah → token lama = 419).
- `src/lib/api/endpoints.ts` — fungsi endpoint auth/rps/industry. `src/lib/query/{keys,hooks}.ts` — query keys + hook `useMe`/`useLogin`/`useLogout`.
- `src/types/api.ts` — tipe DTO diturunkan dari `docs/API.md` (bagian yang sudah ada).
- `src/lib/copy.ts` — teks UI Indonesia (aturan kata "tercakup dalam RPS" / "belum ditemukan dalam RPS").
- `src/styles/index.css` — token shadcn (varian Radix di-vendor ke `src/styles/shadcn.css` agar build tak bergantung paket CLI `shadcn`).

Env: `frontend/.env.example` → `VITE_API_URL=http://localhost:8000/api` (default di kode bila kosong).

Penting backend (untuk A):
1. **Endpoint tanpa header `Accept: application/json` balas 500, bukan 401.** `GET /api/auth/me` dari browser tanpa header itu → `Route [login] not defined.` (Laravel coba redirect ke route `login` yang tidak ada). Dengan `Accept: application/json` → 401 `{"message":"Silakan masuk terlebih dahulu."}`. Bukan bug frontend, tapi `bootstrap/app.php` bisa dicek: unauthorized di luar `api/*` butuh handling.
2. CORS + cookie Sanctum **sudah terverifikasi** dari browser (origin `http://localhost:5173`): register 201, login 200, `/api/auth/me` 200, 401 saat logout. `SESSION_DOMAIN=null` di `.env` dipakai — cookie host-only, jalan di `localhost` maupun `127.0.0.1`.

Perintah: `npm run dev`, `npm run lint`, `npm run typecheck`, `npm test`, `npm run build` — semua lulus. `npm audit` bersih.

Breaking change: tidak.

#### HO-C-002 · 2026-11-12 · T0.1 (FE) · untuk: C (catatan untuk diri sendiri)
`docs/FRONTEND_FRAMES.md` (draft pribadi, belum kontrak tim) still punya 2 ketidaksesuaian dengan `docs/FRONTEND.md section 2` yang belum diputuskan — form metadata (upload vs Tahap 0 `/rps/:id`) dan route `/profile`. Scaffold saat ini mengikuti `docs/FRONTEND.md section 2` (7 route, tanpa `/profile`). Kalau blueprint yang jadi acuan, route perlu ditambah.

## Permintaan

#### REQ-C-001 · 2026-11-12 · → A · status: open
Laravel balas **500 `Route [login] not defined.`** untuk request `api/*` tanpa header `Accept: application/json` (mis. redirect `Authenticate` middleware). Mohon tambahkan named route `login` atau exception handling di `bootstrap/app.php` supaya unauthorized API selalu JSON 401.
Kapan: sebelum merge point #1 (T1.2 butuh guard sesi dari browser).

#### REQ-C-002 · 2026-11-12 · → A, B · status: closed
Terverifikasi dari kode backend (`app/Modules/Rps/Http/Resources/RpsDocumentResource.php` baris 36–45): nama field draft persis `code`, `name`, `credits`, `semester`, `study_program` — cocok dengan `docs/API.md section 3` yang menyebut "lima field nullable". Tipe `RpsMetadataDraft` sudah dibuat di `frontend/src/types/api.ts`. Tidak perlu perubahan backend; `docs/API.md` boleh menyebut nama fieldnya eksplisit di PR berikutnya (non-blocking).
