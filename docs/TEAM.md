# Pembagian Tim & Aturan Vibe Coding

Tim terdiri dari 3 orang dengan kepemilikan area yang jelas. Tujuan utama: **setiap orang bekerja di folder/file yang berbeda** sehingga konflik merge minimal.

> Ganti `Orang A/B/C` dengan nama anggota.

---

## 1. Peran & kepemilikan

### Orang A — Backend Inti & Infra (lead integrasi)
**Area milik:** `backend/database/migrations/`, `backend/database/seeders/`, `backend/config/`, `backend/routes/api.php`, `backend/app/Models/`, `backend/app/Policies/`, `backend/app/Modules/Auth/`, `backend/app/Modules/Rps/` (upload & parsing), `backend/app/Console/`, `deploy/`, `docker-compose.yml`, `docs/DATABASE.md`.

| Task | Deskripsi |
|---|---|
| T0.1 | Scaffold backend (Postgres, Redis, Sanctum, docker compose) |
| T0.2 | `config/skillbridge.php` |
| T1.1 | Auth Sanctum + `users.role` |
| T2.1–T2.3 | Migrasi RPS, upload, `ParseRpsJob` |
| T3.1 | Migrasi `skills`/`course_skills` + seeder |
| T5.1–T5.2 | Migrasi careers/jobs + `skillbridge:import-jobs` |
| T6.1 | Migrasi analyses |
| T7.1 | Migrasi `recommendations` + `PriorityCalculator` |
| T8.1, T8.4 | Rate limit/kuota/error, deploy VPS |

### Orang B — AI Pipeline
**Area milik:** `backend/app/Modules/Ai/`, `backend/app/Modules/Competency/`, `backend/app/Modules/Matching/`, `backend/app/Modules/Recommendation/`, `backend/app/Support/`, `backend/resources/prompts/`, `docs/AI_PIPELINE.md`, `docs/CALIBRATION.md`.

| Task | Deskripsi |
|---|---|
| T2.4 | `GeminiClient` + `ai_runs` (**prioritas pertama**) |
| T2.5 | `ExtractRpsMetadataJob` + endpoint RPS GET/PATCH/DELETE |
| T3.2–T3.4 | `SkillNormalizer`, `EvidenceVerifier`, `ExtractCompetenciesJob` |
| T4.1–T4.3 | `EmbeddingService`, `Cosine`, integrasi embedding |
| T5.3 | `ExtractJobSkillsJob` + endpoint jobs/careers |
| T6.2–T6.4 | `CandidateFinder`, `MatchJudge` + guardrails, `RunAnalysisJob` |
| T7.2–T7.3 | `GenerateRecommendationsJob` + endpoint |
| T8.2 | Golden dataset + `skillbridge:evaluate` |

### Orang C — Frontend & UX
**Area milik:** seluruh `frontend/`, `docs/FRONTEND.md`.

| Task | Deskripsi |
|---|---|
| T0.1 (FE) | Vite, Tailwind, shadcn/ui, router, klien API + CSRF |
| T1.2 | Login/register + guard sesi |
| T2.6 | Upload RPS + polling |
| T3.5 | Detail RPS & tabel kompetensi |
| T5.4 | Profesi, lowongan, form manual |
| T6.5 | Buat analisis + Ringkasan, Skill Gap, EvidenceDrawer |
| T7.4 | Tab rekomendasi + DisclaimerBanner |
| T8.3 | Responsif, state loading/empty/error, audit kata UI |

### Task bersama
| Task | A | B | C |
|---|---|---|---|
| T3.6 Konfirmasi review | migrasi | endpoint + logika analisis | `CompetencyReviewTable` |
| T7.5 Skill pribadi | migrasi + `/me/skills` | integrasi rekomendasi | panel UI |
| T8.5 Checklist MVP | ✔ | ✔ | ✔ |

Untuk task bersama: **migrasi (A) di-merge dulu**, baru B dan C melanjutkan di branch masing-masing.

---

## 2. Jadwal hackathon (2 hari)

Prioritas: **alur demo end-to-end jalan** (upload RPS → kompetensi → pilih profesi/lowongan → skill gap → rekomendasi). Task bertanda ⭐ = *stretch*, dikerjakan hanya jika waktu tersisa.

### Hari 1 — Fondasi & pipeline ekstraksi

| Jam | A (Backend Inti) | B (AI Pipeline) | C (Frontend) |
|---|---|---|---|
| 08.00–08.30 | **Kickoff bersama:** sepakati kontrak `API.md` untuk RPS, kompetensi, jobs, analyses | ← | ← |
| 08.30–10.30 | T0.1 backend, T0.2 config, docker Postgres+Redis | T2.4 `GeminiClient` + `ai_runs` (Http::fake) | Scaffold FE, klien API + CSRF, layout |
| 10.30–12.30 | T1.1 auth, T2.1 migrasi RPS | T3.2 `SkillNormalizer`, T3.3 `EvidenceVerifier` | T1.2 login/register (mock) |
| 12.30–13.30 | Istirahat · **merge #1** ke `main` | | |
| 13.30–16.00 | T2.2 upload, T2.3 `ParseRpsJob`, T3.1 skills + seeder | T2.5 metadata, T3.4 `ExtractCompetenciesJob` | T2.6 upload + polling |
| 16.00–18.00 | T5.1 careers/jobs, T5.2 import + data contoh, T6.1 migrasi analyses | T4.1–T4.3 embedding + cosine | T3.5 detail RPS & tabel kompetensi |
| 18.00–19.00 | Istirahat · **merge #2** + uji integrasi upload → kompetensi | | |
| 19.00–22.00 | T7.1 migrasi rekomendasi + `PriorityCalculator` | T5.3 `ExtractJobSkillsJob` + endpoint jobs | T5.4 profesi & lowongan |

**Target akhir Hari 1:** login → upload RPS → kompetensi + bukti tampil di UI dengan data nyata.

### Hari 2 — Matching, rekomendasi, demo

| Jam | A (Backend Inti) | B (AI Pipeline) | C (Frontend) |
|---|---|---|---|
| 08.00–08.15 | Standup: status, blocker | ← | ← |
| 08.15–11.00 | Bantu B: endpoint analyses (T6.4 bagian controller/resource/policy) | T6.2 `CandidateFinder`, T6.3 `MatchJudge` + guardrails, T6.4 `RunAnalysisJob` | T6.5 `/analyses/new`, Ringkasan, Skill Gap, EvidenceDrawer (mock) |
| 11.00–12.00 | **Merge #3** + uji alur skill gap end-to-end | | |
| 12.00–13.00 | Istirahat | | |
| 13.00–15.00 | T8.1 error ramah + rate limit dasar, T8.4 deploy VPS | T7.2 `GenerateRecommendationsJob`, T7.3 endpoint | T7.4 tab rekomendasi + DisclaimerBanner |
| 15.00–16.30 | **Merge #4 (feature freeze)** · deploy · T8.5 checklist MVP bersama | | |
| 16.30–18.00 | Perbaikan bug demo, siapkan data demo | Siapkan RPS & lowongan contoh yang hasilnya bagus | T8.3 polish: loading/empty/error, responsif, audit kata UI |
| 18.00– | **Latihan presentasi/demo** | ← | ← |

### ⭐ Stretch (jika waktu tersisa)
- T3.6 konfirmasi review kompetensi
- T7.5 skill pribadi
- T8.2 golden dataset + `skillbridge:evaluate` (atau cukup 5–10 contoh berlabel untuk slide)
- Dashboard/riwayat (bagian T8.1)

### Aturan waktu
- **Merge point** (#1–#4) wajib: semua orang push PR sebelum jam itu, lalu A me-merge berurutan.
- **Feature freeze Hari 2 jam 15.00.** Setelah itu hanya perbaikan bug, tanpa fitur baru.
- Task yang molor > 1 jam dari jadwal → potong scope atau pindahkan ke ⭐, umumkan di grup.
- Frontend tidak menunggu backend: selalu pakai mock dari `API.md`, ganti ke API nyata saat merge point.

---

## 3. Aturan Git

1. **`main` dilindungi.** Tidak ada push langsung; semua lewat PR, minimal 1 reviewer.
2. **Satu branch = satu task:** `feat/T2.2-upload-rps`, `fix/T3.3-evidence-normalize`.
3. **Branch berumur pendek** (maks. beberapa jam, harus masuk di merge point berikutnya). PR kecil lebih mudah di-merge.
4. **Sinkron sebelum mulai & sebelum PR:**
   ```bash
   git checkout main && git pull
   git checkout -b feat/Tx.y-nama
   # sebelum PR:
   git fetch origin && git rebase origin/main
   ```
5. **Squash merge** ke `main`; pesan gaya Conventional Commits.
6. Setelah PR di-merge, **umumkan di grup** agar yang lain `git pull`.

---

## 4. Aturan anti-konflik (file panas)

File berikut sering disentuh banyak orang. Perlakukan khusus:

| File | Aturan |
|---|---|
| `backend/routes/api.php` | Pemilik A. B menambah route di file terpisah `routes/api/<modul>.php` yang di-`require` dari `api.php`. |
| Migrasi | **Hanya A yang membuat migrasi** (kecuali disepakati). Jangan pernah mengedit migrasi yang sudah di-merge — buat migrasi baru. |
| `composer.json` / `composer.lock` | Tambah dependency hanya lewat PR khusus `chore: add <paket>`; jangan resolve `lock` secara manual — ambil versi `main` lalu jalankan ulang `composer require`. |
| `package.json` / `package-lock.json` | Milik C. Sama seperti di atas, pakai `npm install <paket>` ulang bila konflik. |
| `.env.example` | Tambahkan variabel baru **di bagian akhir** dengan komentar nama modul. |
| `config/skillbridge.php` | Satu key per baris, kelompokkan per modul; tambah di bagian modul sendiri. |
| `docs/TASKS.md` | Hanya mencentang task sendiri; jangan merapikan/mengubah format baris orang lain. |
| `docs/API.md` | Kontrak API disepakati **sebelum** implementasi (lihat §5). |

Prinsip umum: **tambahkan file baru daripada mengedit file bersama.**

---

## 5. Kontrak API dulu, kode kemudian

1. Sebelum task yang membuat endpoint baru, pemilik backend menulis/memperbarui bagian `docs/API.md` (request, response, kode error) dalam PR kecil `docs:`.
2. C me-review PR tersebut, lalu membuat mock (MSW/fixture) dari kontrak itu.
3. Backend mengimplementasi persis sesuai kontrak. Perubahan kontrak = PR `docs/API.md` baru + kabari C.
4. Tipe TypeScript di frontend diturunkan dari `API.md`, simpan di `frontend/src/types/api.ts` (milik C).

---

## 6. Aturan vibe coding (bekerja dengan AI agent)

1. **Satu sesi AI = satu task.** Pakai template prompt di `docs/TASKS.md`. Jangan minta AI "sekalian" mengerjakan task lain.
2. **Batasi ruang lingkup di prompt.** Tambahkan:
   ```
   Hanya ubah file di area milik saya: <folder>. Jika perlu mengubah file di luar area
   ini (routes/api.php, migrasi, composer.json, dll), berhenti dan beri tahu saya dulu.
   ```
3. **Jangan biarkan AI melakukan refactor massal**, rename, reformat seluruh file, atau "merapikan" kode orang lain. Tolak diff yang menyentuh file di luar task.
4. **Review diff sebelum commit:** `git diff --stat` — pastikan hanya file yang relevan berubah.
5. **Formatter konsisten:** Laravel Pint (backend) dan Prettier + ESLint (frontend) dengan konfigurasi dari repo. Jangan pakai setelan formatter pribadi; jalankan formatter hanya pada file yang diubah.
6. **AI tidak boleh commit atau push otomatis** ke `main`. Commit dilakukan sendiri setelah review.
7. **Test wajib lulus** (`php artisan test` / `npm run lint && npm run typecheck && npm test`) sebelum PR.
8. **Gemini selalu di-fake di test.** Jangan menjalankan test yang memanggil API sungguhan.
9. **Jangan menempel API key ke prompt AI** atau ke file yang di-commit.
10. **Jika AI ragu atau dokumen bertentangan**, tanyakan ke tim di grup — jangan biarkan AI menebak keputusan desain.
11. **Perbarui dokumen dalam PR yang sama** jika mengubah skema DB/kontrak API (aturan `CLAUDE.md`).

---

## 7. Komunikasi

- **Sync singkat di setiap merge point** (5 menit): selesai apa, berikutnya apa, blocker, file bersama yang akan disentuh.
- **Klaim sebelum menyentuh file bersama:** tulis di grup "aku edit `routes/api.php` sekarang".
- Blocker > 2 jam → minta bantuan, jangan diam.
- Label PR: `backend`, `ai`, `frontend`, `docs`, `shared`.

---

## 8. Checklist sebelum membuka PR

- [ ] Branch sudah di-rebase ke `origin/main` terbaru
- [ ] Hanya file dalam lingkup task yang berubah (`git diff --stat`)
- [ ] Test, lint, dan typecheck lulus
- [ ] Dokumen (`API.md`/`DATABASE.md`) diperbarui bila perlu
- [ ] Tidak ada secret, `.env`, atau file debug ikut ter-commit
- [ ] Teks UI mengikuti aturan kata (“tercakup dalam RPS”, “belum ditemukan dalam RPS”)
- [ ] Task dicentang di `docs/TASKS.md`
