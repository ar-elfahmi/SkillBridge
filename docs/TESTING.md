# Testing & Evaluasi

## 1. Prinsip
- Panggilan Gemini **selalu di-fake** pada test otomatis (`Http::fake`). Sediakan fixture respons JSON valid, tidak valid, dan hasil halusinasi.
- Setiap task di `TASKS.md` selesai hanya jika test relevan lulus.

## 2. Backend (Pest atau PHPUnit)
| Area | Contoh test |
|---|---|
| Auth & Policy | pengguna A tidak bisa membaca RPS/analisis pengguna B (404); role dibatasi |
| Upload | tolak non-PDF, terlalu besar, terlalu banyak halaman; duplikat → 409 |
| Parsing | PDF digital fixture → `rps_pages` terisi; PDF tanpa text layer → `needs_ocr` |
| Ekstraksi | skema valid tersimpan; skema invalid → retry → `failed`; `evidence` tidak ada di halaman → `unverified` dan `page` null |
| Normalisasi | `PHP Laravel` → `Laravel Framework`; `RESTful API` → `REST API`; skill baru membuat entri `skills` |
| Embedding | cache hit untuk `text_hash+model+dimensions` sama (tidak memanggil API); model/dimensi beda → panggil ulang; vektor ter-normalisasi |
| Cosine | nilai pasti untuk vektor kecil; vektor nol tidak menyebabkan error |
| Matching | skor < floor → `missing` tanpa panggilan LLM; id kandidat di luar daftar ditolak; teknologi tidak disebut → maksimal `partial`; kompetensi `unverified` tidak bisa `covered` |
| Ringkasan | `coverage_percentage = covered/total`, `partial` tidak dihitung; total 0 tidak membagi nol |
| Rekomendasi | prioritas sesuai aturan; tidak ada URL pada output; `related_weeks` berasal dari data |
| Job | idempotent saat dijalankan ulang; `failed()` mengubah status |
| API | struktur respons sesuai `docs/API.md`; rate limit 429 |

## 3. Frontend
- Unit/komponen (Vitest + Testing Library): `SkillGapTable`, `EvidenceDrawer`, badge status (ikon + teks), copy tidak memuat frasa terlarang.
- E2E (Playwright, opsional): upload → status → hasil dengan API di-mock.
- `npm run typecheck` dan `npm run lint` bersih.

## 4. Evaluasi kualitas AI (golden dataset)
Buat folder `backend/tests/Fixtures/golden/` berisi:
- 3–5 RPS contoh beserta kompetensi hasil identifikasi manual (ground truth) → **Extraction Accuracy**.
- Pasangan (skill industri, kompetensi RPS) dengan label manual `covered/partial/missing` → **Matching Accuracy** dan **Gap Classification**.
- Sampel rekomendasi dengan penilaian relevansi manual (skala 1–5) → **Recommendation Relevance**.

### Perintah evaluasi
`php artisan skillbridge:evaluate {--set=golden}` menghitung precision, recall, F1 per kelas dan confusion matrix; keluarkan laporan ke `storage/app/evals/`.

### Kalibrasi threshold
1. Hitung skor cosine untuk semua pasangan berlabel.
2. Telusuri nilai `SIM_FLOOR` pada rentang tertentu; pilih nilai yang menjaga recall `covered/partial` tinggi dengan biaya panggilan LLM yang wajar.
3. Evaluasi ulang klasifikasi akhir (setelah LLM dan guardrail), bukan hanya skor mentah.
4. Catat nilai final, ukuran dataset berlabel, dan tanggal kalibrasi di `docs/CALIBRATION.md`.
5. Sebelum kalibrasi, UI/dokumen tidak boleh mengklaim akurasi tertentu.

## 5. Checklist sebelum rilis
- [ ] Semua test lulus; tidak ada panggilan API nyata di CI.
- [ ] Evaluasi golden dataset dijalankan dan dicatat.
- [ ] Threshold dikalibrasi atau ditandai "belum dikalibrasi" di dokumentasi.
- [ ] Uji manual dengan PDF scan, PDF besar, dan lowongan tanpa skill teknis.
- [ ] Uji tampilan mobile dan desktop.
