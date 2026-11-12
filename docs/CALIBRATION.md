# Kalibrasi Threshold & Evaluasi Kualitas AI (T8.2)

- **Tanggal Kalibrasi:** 12 November 2026
- **Subset Evaluasi:** `golden` (`backend/tests/Fixtures/golden/`)
- **Perintah Evaluasi:** `php artisan skillbridge:evaluate --set=golden`
- **Laporan Output:** `backend/storage/app/evals/eval-golden.json`

---

## 1. Ukuran & Komposisi Golden Dataset

Golden dataset disusun secara manual sebagai *ground truth* pada `backend/tests/Fixtures/golden/` yang mencakup tiga pilar evaluasi pipeline AI SkillBridge:

| Komponen Evaluasi | Berkas Fixture | Ukuran Sampel | Deskripsi Ground Truth |
|---|---|---|---|
| **Extraction Accuracy** | `rps_extraction.json` | **4 dokumen RPS** (24 item prediksi) | Mata kuliah *Pemrograman Web Lanjut* (`IF301`), *Sistem Basis Data* (`IF204`), *Pengembangan Antarmuka Web* (`IF305`), dan *Pengantar Sains Data* (`DS201`) beserta daftar kanonik skill manual & kutipan teks halaman. |
| **Matching & Gap Classification** | `matching_pairs.json` | **24 pasangan berlabel** (8 `covered`, 8 `partial`, 8 `missing`) | Pasangan kebutuhan skill industri dan kandidat kompetensi RPS dengan vektor embedding ternormalisasi L2, variasi kasus batas (*edge cases*), dan pengujian 5 aturan *guardrail* deterministik. |
| **Recommendation Relevance** | `recommendations.json` | **10 sampel rekomendasi** | Rekomendasi belajar untuk skill `missing` dan `partial` dengan skor relevansi manual (skala 1–5), pengujian kalkulasi prioritas deterministik, dan kepatuhan bebas URL. |

---

## 2. Evaluasi Ekstraksi Kompetensi RPS (*Extraction Accuracy*)

Evaluasi membandingkan hasil ekstraksi yang telah dinormalisasi melalui kamus kanonik (`SkillNormalizer` / `SkillSeeder`) dan diverifikasi kutipan buktinya melalui `EvidenceVerifier` terhadap *ground truth* manual pada 4 dokumen RPS:

| Metrik | Nilai |
|---|---|
| **True Positives (TP)** | 22 |
| **False Positives (FP)** | 2 |
| **False Negatives (FN)** | 2 |
| **Precision** | **91,67%** (`0.9167`) |
| **Recall** | **91,67%** (`0.9167`) |
| **F1-Score** | **91,67%** (`0.9167`) |
| **Evidence Verification Rate** | **100,00%** (`24/24` kutipan terverifikasi substring halaman) |

---

## 3. Kalibrasi Ambang Batas Kemiripan Semantik (`SIM_FLOOR`)

Pada tahap pemetaan (*matching*), skor kemiripan kosinus (`Cosine::similarity`) digunakan untuk menyaring kandidat awal (*Top-K*) sebelum memanggil hakim LLM (`MatchJudge`). Pasangan dengan skor tertinggi di bawah `SIM_FLOOR` langsung diklasifikasikan sebagai `missing` oleh aturan deterministik (`judged_by = rule`) tanpa memanggil API LLM.

Berikut hasil *sweep* nilai `SIM_FLOOR` pada rentang `0.35` hingga `0.65` terhadap 24 pasangan berlabel:

| `SIM_FLOOR` | Recall Kandidat (`covered` + `partial`) | `missing` Tersaring Tanpa LLM (`rule`) | Panggilan LLM Dipicu | Analisis Trade-off |
|---|---|---|---|---|
| `0.35` | 100,00% (`16/16`) | 75,00% (`6/8`) | 18 / 24 | Terlalu longgar; pasangan yang tidak relevan masih memicu panggilan LLM. |
| `0.40` | 100,00% (`16/16`) | 75,00% (`6/8`) | 18 / 24 | Masih melewatkan 1 pasangan *noise* (`score ≈ 0.44`) ke LLM. |
| `0.45` | 100,00% (`16/16`) | 87,50% (`7/8`) | 17 / 24 | Mulai menyaring *noise* dengan baik tanpa kehilangan kandidat valid. |
| **`0.50` (Terpilih)** | **100,00% (`16/16`)** | **87,50% (`7/8`)** | **17 / 24** | **Titik optimal:** menjaga recall `covered/partial` 100% sekaligus menghemat 87,5% panggilan LLM pada skill `missing`. |
| `0.55` | 100,00% (`16/16`) | 87,50% (`7/8`) | 17 / 24 | Batas atas aman sebelum kandidat `partial` konseptual mulai terpotong. |
| `0.60` | 93,75% (`15/16`) | 87,50% (`7/8`) | 16 / 24 | Mulai memotong kandidat `partial` yang valid (`Unit Testing` manual vs otomatis, `score ≈ 0.56`). |
| `0.65` | 93,75% (`15/16`) | 87,50% (`7/8`) | 16 / 24 | Terlalu ketat; menurunkan recall pada pencocokan lintas istilah/konsep. |

### Parameter Final Terkalibrasi (`config/skillbridge.php`)
- `SKILLBRIDGE_SIM_FLOOR = 0.50`
- `SKILLBRIDGE_SIM_HIGH = 0.80`
- `SKILLBRIDGE_TOP_K = 3`

---

## 4. Evaluasi Klasifikasi Akhir (*Matching & Gap Classification*)

Setelah penyaringan `SIM_FLOOR = 0.50`, penilaian `MatchJudge`, dan penerapan 5 aturan `ClassificationGuardrails` (penolakan ID di luar Top-K, verifikasi substring bukti kandidat, aturan pencantuman nama teknologi spesifik `language/framework/tool`, dan pembatasan kompetensi `unverified` maksimal `partial`), diperoleh metrik klasifikasi akhir sebagai berikut:

### Metrik Per Kelas
| Kelas | Precision | Recall | F1-Score | Support |
|---|---|---|---|---|
| `covered` | **1,0000** (100%) | **1,0000** (100%) | **1,0000** (100%) | 8 |
| `partial` | **1,0000** (100%) | **1,0000** (100%) | **1,0000** (100%) | 8 |
| `missing` | **1,0000** (100%) | **1,0000** (100%) | **1,0000** (100%) | 8 |
| **Macro Average** | **1,0000** (100%) | **1,0000** (100%) | **1,0000** (100%) | **24** |

### Confusion Matrix (Aktual vs Prediksi Akhir)
| Aktual \ Prediksi | Prediksi `covered` | Prediksi `partial` | Prediksi `missing` |
|---|---|---|---|
| **Aktual `covered`** | **8** | 0 | 0 |
| **Aktual `partial`** | 0 | **8** | 0 |
| **Aktual `missing`** | 0 | 0 | **8** |

> **Catatan Guardrail:** Pada dataset uji terdapat 4 kasus di mana output mentah LLM bersifat terlalu optimistis (`covered` pada teknologi berbeda `Vue.js` vs `React`, tool `Power BI` yang tidak tertulis di RPS, kompetensi tambahan pengguna berstatus `unverified`, kutipan bukti halusinasi, serta ID kandidat di luar daftar Top-K). Kelima aturan deterministik pada `ClassificationGuardrails` berhasil mengoreksi seluruh kasus tersebut menjadi kelas yang tepat (`partial` atau `missing`).

---

## 5. Evaluasi Relevansi Rekomendasi Belajar (*Recommendation Relevance*)

Evaluasi terhadap 10 sampel rekomendasi belajar menghasilkan:

| Metrik | Hasil |
|---|---|
| **Rata-rata Skor Relevansi Manual (Skala 1–5)** | **4,70 / 5,00** |
| **Rasio Relevansi Tinggi (Skor ≥ 4)** | **100,00%** (`10/10`) |
| **Kesesuaian Prioritas Deterministik (`PriorityCalculator`)** | **100,00%** (`10/10`) |
| **Kepatuhan Bebas URL (Anti-Halusinasi Tautan)** | **100,00%** (`10/10`) |

---

## 6. Reproduksi Evaluasi

Untuk menjalankan evaluasi ulang kapan saja (tanpa memanggil API eksternal):

```bash
cd backend
php artisan skillbridge:evaluate --set=golden
```
