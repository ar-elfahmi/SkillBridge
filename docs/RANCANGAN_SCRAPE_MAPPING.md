# Rancangan Mapping Hasil Scraping `parts/` → Tabel `job_postings`

Status: **usulan desain** untuk menyetarakan dataset hasil scraping dengan skema tabel `job_postings` dan kebutuhan pipeline AI (ekstraksi `job_skills`).

---

## 1. Kondisi Data Hasil Scraping (`parts/*.json`)

Survei 6 file, total **7.894 lowongan**, keterisian 100% pada semua key:

| Key JSON | Keterisian | Unik | Catatan |
|---|---|---|---|
| `career` | 100% | 21 slug | Semua cocok dengan slug `CareerSeeder` |
| `company` | 100% | 1.050 | Top: SpaceX 640, Databricks 407, OpenAI 320 |
| `title` | 100% | — | Banyak memuat suffix asing, mis. `(m/f/d)` |
| `description` | 100% | — | Median 5.533 karakter, max 16.482 (< batas 20.000) |
| `qualifications` | **0%** | — | **Selalu kosong** di semua 7.894 baris |
| `source_name` | 100% | 15 | Greenhouse 4.679, Ashby 972, Arbeitnow 469, Glints 429, dst. |
| `source_url` | 100% | 7.894 unik | **Tidak ada duplikat URL** |
| `collected_at` | 100% | 2 nilai | `2026-11-10` (7.243 asli) / `2026-11-11` (651 sintetis) |
| `dataset_version` | 100% | 9 nilai | `ats-*` 6.364, `synthetic-*` 651, per-API sisanya |

Metadata tingkat file (`_note`, `source_file`, `part`, `jobs_in_part`, `exported_at`) tidak dipetakan ke tabel — hanya konteks.

---

## 2. Mapping ke Kolom `job_postings`

| Kolom tabel | Sumber JSON | Perlakuan | Alasan |
|---|---|---|---|
| `career_id` | `career` (slug) | Lookup langsung ke `careers.slug` | 21 slug sudah identik dengan seeder |
| `title` | `title` | Trim; opsional rapikan suffix locale (lihat §4b) | Wajib, max 255 |
| `company` | `company` | Trim | Wajib, max 255 |
| `description` | `description` | Simpan apa adanya | `text` column, max import 20.000 — semua baris aman |
| `qualifications` | *(tidak ada)* | **Turunkan otomatis** dari `description` — lihat §4a | Kolom ada di skema & dipakai drawer detail + prompt ekstraksi, tapi scrape tidak menghasilkannya |
| `source_name` | `source_name` | Simpan apa adanya | Atribusi wajib sesuai `_note` |
| `source_url` | `source_url` | Simpan apa adanya | Atribusi + deduplikasi natural (0 duplikat) |
| `collected_at` | `collected_at` | Format `Y-m-d` sudah sesuai | — |
| `dataset_version` | `dataset_version` | Simpan apa adanya | Pembeda asli vs sintetis (`synthetic-*`) |
| `created_by` | — | `NULL` (tidak di-set) | Lowongan kurasi terlihat semua pengguna (`scopeVisibleTo`) |
| `extraction_status` | — | `pending` (default) | Diisi `ExtractJobSkillsJob` setelah seeder |
| `id`, `timestamps` | — | Otomatis Laravel | — |

**Kesimpulan mapping:** semua kolom tabel terisi tanpa kolom JSON yang tersisa. Satu-satunya keputusan desain adalah `qualifications`.

---

## 3. Yang Sudah Benar di Implementasi Sekarang

`PartsJobPostingSeeder` + `JobPostingImporter` sudah:

1. Validasi tiap baris (career slug, max lengths, URL http/https, format tanggal).
2. `updateOrCreate` dengan natural key `(created_by=NULL, career_id, company, title, source_url, dataset_version)` → **idempotent** (terbukti: 7.894 baru, 0 duplikat saat re-run karenda URL unik).
3. Transaction per file.
4. Laporan progres per file + total.

Hasil uji: **7.894/7.894 ter-impor, 0 gagal validasi.**

---

## 4. Usulan Penyempurnaan

### a. Derive `qualifications` dari `description`

Masalah: drawer detail lowongan di frontend menampilkan blok *"kualifikasi"* ber-italic dari `qualifications`, dan prompt `job-extract-v1` memanfaatkannya sebagai konteks ekstraksi. Dengan 100% kosong, kedua fitur kehilangan sinyal.

Usulan (di `JobPostingImporter::import`, sebelum `updateOrCreate`):

```php
private function deriveQualifications(?string $description, string $title): ?string
{
    if ($description === null) {
        return null;
    }

    // 1) Ambil segmen eksplisit bila ada (paling akurat).
    if (preg_match('/(?:requirements|qualifications|what you.?ll need|your profile|was wir suchen)[\s:]\n+(.{40,2000}?)(?:\n(?:benefits|what we offer|nice to have|we offer)|\z)/is', $description, $m)) {
        return trim($m[1]);
    }

    // 2) Ambil daftar bernomor/poin pertama (umum pada lowongan ATS).
    if (preg_match('/\n(?:\d+[.)]|[-•*])\s+.+(?:\n(?:\d+[.)]|[-•*])\s+.+){2,}/', $description, $m)) {
        return trim($m[0]);
    }

    // 3) Fallback: kalimat berisi sinyal kualifikasi (years of experience, dsb.).
    preg_match_all('/^[^.!?]{20,220}\b(?:\d+\+?\s*(?:years?|jahre)|experience with|proficien|familiar with|bachelor|degree|engineer degree)[^.!?]*[.!?]/im', $description, $m);
    $lines = array_slice(array_map('trim', $m[0] ?? []), 0, 5);

    return $lines === [] ? null : implode("\n", $lines);
}
```

Diterapkan hanya saat kolom `qualifications` memang tidak ada di baris JSON (tidak menimpa sumber yang punya kolom itu).

### b. Normalisasi ringan `title`

Suffix locale menumpuk di daftar lowongan. Usulan (opsional, non-destruktif):

```php
$title = preg_replace('/\s*\((?:m\/f\/d|m\/w\/d|f\/m\/d|x\/f\/m|all genders|w\/m\/d)\)\s*$/i', '', $row['title']);
```

Hanya membuang pola yang disepakati; hasil tetap tersimpan di kolom `title`, sementara nilai asli bisa ditaruh di `qualifications`? — **tidak**, nilai asli tidak disalin ke kolom lain karena tidak ada kolom `title_raw`. Jika atribusi judul asli penting, tambahkan migrasi `title_raw` nullable; jika tidak, terima pemangkasan.

### c. Penanda `synthetic-*` tetap dipertahankan

651 baris `dataset_version = 'synthetic-2026-11-11'` (deskripsi sintetis dari profil skill lowongan asli) **tidak boleh digabung** ke kurasi asli tanpa label — sesuai `_note` file dan aturan "Data contoh" di FRONTEND.md. Usulan frontend: badge "Data contoh" bila `dataset_version` berawalan `synthetic-`. Backend tidak perlu berubah.

### d. Ekstraksi `job_skills` — estimasi beban

7.894 baris `extraction_status = pending`:

- Mode `offline` lokal: aman, tetap hitung durasi (perkiraan puluhan menit di queue worker).
- Mode `gemini`: 7.894 panggilan → perhatikan kuota/biaya; jalankan bertahap per `dataset_version`, atau subset profesi yang dipakai demo.

Rekomendasi operasional:

```bash
# Worker terpisah, batch per dataset agar bisa dihentikan:
php artisan queue:work redis --queue=default --tries=3 --max-time=3600
```

dan pertimbangkan command khusus `skillbridge:extract-jobs {--dataset=ats-2026-11-10}` yang men-dispatch hanya baris tertentu.

### e. tidak perlu migrasi skema

Semua kolom `job_postings` sudah cukup. Satu-satunya migrasi yang mungkin (opsional) adalah `title_raw` bila §4b diterima dan atribusi judul asli dianggap wajib.

---

## 5. Ringkasan Keputusan yang Diminta

| # | Keputusan | Dampak bila ya |
|---|---|---|
| 1 | Terima derive `qualifications` (§4a) | Drawer detail & prompt ekstraksi dapat konteks; perlu unit test regex |
| 2 | Terima normalisasi `title` suffix locale (§4b) | Daftar lebih bersih; nilai asli hilang kecuali migrasi `title_raw` |
| 3 | Badge "Data contoh" untuk `dataset_version LIKE 'synthetic-%'` di frontend | Kejujuran data sesuai PRD; perubahan kecil di kartu lowongan |
| 4 | Command dispatch ekstraksi per `dataset_version` (§4d) | Kontrol biaya/kuota AI |

Tanpa keputusan pun, seeding saat ini **sudah benar dan bisa dipakai** — proposal di atas adalah peningkatan kualitas data, bukan perbaikan bug.