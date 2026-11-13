Dokumen docs/PERBEDAAN_DESAIN_DAN_API.md diperbarui. Pemetaan 20 frame desain (design/dihsain.pen) vs API backend:

1. 01 Login (s9yfbw Desktop, gFMh9 Mobile)
Desain: Form email + sandi, banner kiri (Desktop), alert "Email atau kata sandi salah." (keduanya).
Kondisi API: POST /api/auth/login terima email + password. Kembalikan Sanctum token.
Kesenjangan: API lempar HTTP 401/422 format default Laravel. Frontend perlu tangkap dan tampilkan alert lokal "Email atau kata sandi salah."

2. 02 Register (bqXr9 Desktop, GAmwT Mobile)
Desain: Nama, email, sandi (min 8 karakter), ulangi sandi, alert "Email sudah terdaftar."
Kondisi API: POST /api/auth/register wajibkan name, email, password, password_confirmation. Validasi min:8 dan confirmed ada di RegisterRequest. Role otomatis student.
Kesenjangan: Error email duplikat balik HTTP 422 (errors.email). Frontend petakan ke alert "Email sudah terdaftar."

3. 03 Dashboard (jVAFM Desktop, N89Ux Mobile)
Desain: 3 kartu metrik ("7 skill", "12 lowongan", "2 profil pekerjaan"), kartu RPS dengan cakupan 41,7%, sidebar skill + aktivitas (Desktop).
Kondisi API: Tidak ada endpoint agregasi stat (/api/dashboard/stats). GET /api/rps tidak bawa relasi hasil analisis terbaru. Tabel activities tidak ada.
Solusi: Frontend hitung stat lokal dari riwayat GET /api/analyses. Tambah relasi latestAnalysis ke RpsDocumentResource. Mobile sembunyikan sidebar.

4. 04 Detail RPS — Tahap 0 (Q2CPz Desktop, ofKjk Mobile)
Desain: Subtitle berkas "18 halaman · 2,4 MB", form 5 field metadata MK, stepper 3 tahap.
Kondisi API: DB punya file_size, tapi RpsDocumentResource tidak kirim file_size (hanya page_count). Endpoint PATCH /api/rps/{id} cocok.
Solusi: Tambah 'file_size' => $this->file_size di RpsDocumentResource.php.

5. 04 Detail RPS — Tahap 1 (g8RHiP Desktop, GRI61 Mobile)
Desain: 3 kartu counter (total, belum verifikasi, menunggu). Desktop pakai tabel; Mobile pakai kartu bertumpuk isi bukti verbatim & tombol aksi.
Kondisi API: Cocok GET /api/rps/{id}/competencies dan CRUD /competencies/{cid}. Frontend hitung counter dan filter tab lokal.

6. 05 Pemetaan Industri (MQgvQ Desktop, sxwG7 Mobile)
Desain: Pilih profesi, pilih lowongan dengan badge skill (React, TypeScript, CSS), pratinjau agregasi skill.
Kondisi API: JobPostingController::index query belum eager-load jobSkills.skillEntry. Properti skills kosong di list lowongan.
Solusi: Ubah controller: JobPosting::with(['career', 'jobSkills.skillEntry']). Frontend hitung agregasi pratinjau di sisi klien.

7. 06 Hasil Analisis — Ringkasan (gP4cp Desktop, n28t2 Mobile)
Desain: Indikator metrik 3 kategori (Tercakup 41,7%, Sebagian 22,6%, Belum ditemukan 35,7%), diagram cakupan.
Kondisi API: Cocok AnalysisResource field summary. Persentase partial & missing dihitung rumus (count / total_skills) * 100 di frontend.

8. 06 Hasil Analisis — Skill Gap (gTlaA Desktop, iFLC6 Mobile)
Desain: Filter status, toggle sembunyikan skill pribadi, badge "Dilaporkan". Desktop tabel, Mobile kartu dengan tombol "Lihat bukti".
Kondisi API: Cocok SkillMatchResource (classification, demand_count, matched_competency, self_declared).

9. 06 Hasil Analisis — Rekomendasi Belajar (ZXSVq Desktop, w4D8PB Mobile)
Desain: Alert "Rekomendasi belum diperbarui", asal lowongan ("PT Contoh — Frontend Developer"), tombol "Sudah saya pelajari di luar RPS".
Kondisi API: RecommendationResource belum kirim relasi nama perusahaan/lowongan asal (source_jobs). Belum ada dirty-state tracking setelah user tambah skill pribadi.
Solusi: Eager-load lowongan asal lewat skillMatch.source_job_skill_ids. Hubungkan tombol cepat ke endpoint user_skills.

10. 07 Profil Pengguna (zQGdK Desktop, PgCOd Mobile)
Desain: Kelola skill pribadi, dropdown level (Beginner, Intermediate, Advanced), teks input asli ("ReactJS" vs kanonik "React"). Desktop tabel, Mobile kartu.
Kondisi API: Route /profile belum terdaftar di frontend router. Tabel user_skills dan endpoint /api/me/skills belum ada di backend.
Solusi: Buat migrasi user_skills dengan kolom level dan reported_as. Tambah CRUD /api/me/skills. Daftarkan route /profile.
