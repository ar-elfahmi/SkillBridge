# Keamanan & Privasi

## 1. Autentikasi & otorisasi
- Laravel Sanctum (cookie SPA + CSRF). Password di-hash (bcrypt/argon2 bawaan Laravel).
- Satu role (`student`) di MVP; akses data dibatasi per pemilik lewat Policy.
- Policy untuk `RpsDocument`, `Analysis`, `JobPosting` (milik pengguna). Akses resource orang lain → 404.
- Dataset lowongan terkurasi (`created_by = null`) dapat dibaca semua pengguna login, tidak dapat diubah lewat API.
- Rate limiting: login, upload, `POST /api/jobs`, `POST /api/analyses`.

## 2. File PDF
- Validasi MIME dari isi file, ekstensi, ukuran maksimum, jumlah halaman maksimum.
- Simpan di disk **privat** dengan nama acak; jangan sajikan langsung lewat URL publik. Direktori storage berada di luar web root Nginx dan tidak pernah di-serve langsung. Unduhan (bila ada) lewat endpoint terotorisasi atau signed URL kedaluwarsa singkat.
- Hitung `file_hash` untuk deteksi duplikat; jangan mengandalkan nama file asli.
- Hapus berkas saat dokumen dihapus.

## 3. Keamanan AI
- API key Gemini hanya di environment backend. Jangan log key, prompt penuh berisi dokumen, atau teks PDF.
- **Prompt injection:** isi PDF dan lowongan diperlakukan sebagai data. Gunakan delimiter, instruksi eksplisit untuk mengabaikan perintah dalam dokumen, structured output, dan validasi ketat di Laravel. LLM tidak punya akses tool/DB langsung.
- Validasi JSON hasil LLM terhadap skema sebelum disimpan; tolak id kandidat di luar daftar.
- Bukti dicocokkan dengan teks sumber (anti-halusinasi).
- Batasi panjang input per panggilan, jumlah job AI paralel, dan anggaran per pengguna (kuota harian di config).
- Retry terbatas, timeout, jangan mengulang tanpa batas.

## 4. Privasi
- Kirim ke penyedia API hanya teks yang diperlukan. Jangan sertakan nama/email pengguna dalam prompt.
- RPS umumnya memuat nama dosen pengampu; anggap sebagai data yang tidak perlu dan (opsional) redaksi pola nama/NIP sebelum dikirim bila memungkinkan.
- Tampilkan pemberitahuan bahwa pemrosesan melibatkan layanan AI pihak ketiga (pada halaman upload).
- Sediakan penghapusan dokumen dan analisis oleh pemilik.

## 5. Aplikasi web
- CORS dibatasi ke origin frontend; `SANCTUM_STATEFUL_DOMAINS` dan `SESSION_DOMAIN` diatur benar.
- Header keamanan dasar; cookie `Secure`, `HttpOnly`, `SameSite` sesuai lingkungan.
- Escape output di frontend (React default); jangan `dangerouslySetInnerHTML` untuk teks dokumen/lowongan.
- Validasi semua input dengan `FormRequest`; batasi panjang teks lowongan.
- Jangan membocorkan stack trace atau pesan internal ke klien (`APP_DEBUG=false` di produksi).

## 6. Operasional
- `.env` tidak masuk git; sediakan `.env.example` tanpa secret.
- Pantau biaya/kuota API via `ai_runs`.
- Pencadangan database dan kebijakan retensi file.
- Tinjau perubahan nama model, harga, kuota, endpoint Gemini di dokumentasi resmi secara berkala.
