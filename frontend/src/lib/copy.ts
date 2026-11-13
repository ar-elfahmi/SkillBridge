/**
 * Semua teks UI bahasa Indonesia dalam satu tempat.
 * Aturan kata: "tercakup dalam RPS", "belum ditemukan dalam RPS".
 * Jangan pakai "anda belum menguasai" atau "persentase penguasaan".
 */

export const copy = {
  appName: 'SkillBridge',
  tagline: 'Petakan kompetensi RPS terhadap kebutuhan keterampilan industri.',

  auth: {
    loginTitle: 'Masuk',
    loginSubtitle: 'Masuk untuk meninjau hasil analisis RPS Anda.',
    loginEyebrow: 'Mulai dari RPS Anda',
    loginInfoTitle: 'Analisis RPS terhadap kebutuhan industri',
    loginInfoNote: 'Data lowongan pada contoh ini adalah data contoh',
    loginInfoPoints: [
      'Unggah RPS',
      'Tinjau kompetensi',
      'Bandingkan dengan lowongan',
    ],
    emailPlaceholder: 'nama@email.com',
    passwordPlaceholder: 'Masukkan kata sandi',
    showPassword: 'Tampilkan kata sandi',
    hidePassword: 'Sembunyikan kata sandi',
    loginFailed: 'Email atau kata sandi salah.',
    genericError: 'Tidak dapat masuk saat ini. Coba lagi.',
    registerTitle: 'Daftar akun',
    registerSubtitle: 'Buat akun untuk mulai menganalisis RPS Anda.',
    registerEyebrow: 'UNTUK MAHASISWA',
    registerInfoTitle: 'Mulai dari RPS Anda',
    registerInfoNote: 'Sistem memetakan kompetensi dalam RPS terhadap kebutuhan skill lowongan.',
    namePlaceholder: 'Nama lengkap',
    passwordCreatePlaceholder: 'Buat kata sandi',
    passwordConfirmLabel: 'Ulangi kata sandi',
    passwordConfirmPlaceholder: 'Ulangi kata sandi',
    passwordHelper: 'Minimal 8 karakter',
    passwordMismatch: 'Konfirmasi kata sandi tidak cocok.',
    registerFailed: 'Pendaftaran gagal. Periksa kembali isian Anda.',
    emailTaken: 'Email sudah terdaftar.',
    emailLabel: 'Email',
    passwordLabel: 'Kata sandi',
    nameLabel: 'Nama lengkap',
    submitLogin: 'Masuk',
    submitRegister: 'Daftar',
    needAccount: 'Belum punya akun?',
    haveAccount: 'Sudah punya akun?',
  },

  footerBrand: 'SkillBridge · 2026',

  disclaimer:
    'Hasil menunjukkan cakupan dokumen RPS, bukan kemampuan aktual mahasiswa.',
  coverageLabel: 'Cakupan skill dalam RPS',
  similarityLabel: 'Kemiripan semantik',

  status: {
    covered: 'Tercakup',
    partial: 'Sebagian',
    missing: 'Belum ditemukan dalam RPS',
  },

  sampleDataLabel: 'Data contoh',
} as const
