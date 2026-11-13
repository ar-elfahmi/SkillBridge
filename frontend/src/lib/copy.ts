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

  dashboard: {
    searchPlaceholder: 'Cari RPS, skill, atau lowongan',
    uploadEyebrow: 'UNGGAH RPS',
    uploadTitle: 'Unggah RPS',
    uploadDescDesktop:
      'Unggah PDF RPS. Sistem membaca dokumen dan menyiapkan kompetensi untuk Anda tinjau.',
    uploadDescMobile: 'Unggah PDF RPS dan mulai tinjau kompetensi.',
    uploadDropTitle: 'Pilih atau tarik PDF RPS',
    uploadNote: 'PDF · Maks. 10 MB',
    chooseFile: 'Pilih File',
    uploading: 'Mengunggah…',
    uploadSuccess: 'RPS berhasil diunggah. Menuju ke tinjauan metadata…',
    uploadDuplicate: 'RPS ini sudah pernah Anda unggah.',
    openExisting: 'Buka Dokumen',
    fileTooLarge: 'Ukuran PDF melebihi batas 10 MB.',
    invalidFile: 'Berkas harus berupa PDF yang valid.',
    stats: {
      missingSkillsLabel: 'Skill yang kurang',
      missingSkillsNote: 'skill · prioritas analisis',
      matchedJobsLabel: 'Lowongan cocok',
      matchedJobsNote: 'lowongan cocok · data industri',
      careersLabel: 'Profil pekerjaan',
      careersNote: 'profil pekerjaan · Target aktif',
    },
    analyzedRpsTitle: 'RPS dianalisis',
    viewAll: 'Lihat semua →',
    emptyRps: 'Belum ada RPS yang diunggah. Mulai dengan mengunggah PDF RPS di atas.',
    sidebar: {
      heading: 'Ringkasan',
      ownedSkillsTitle: 'Skill yang sudah dimiliki',
      ownedSkillsNote: 'Dilaporkan pengguna',
      viewAllSkills: 'Lihat semua skill →',
      recentActivityTitle: 'Aktivitas terbaru',
    },
    nav: {
      home: 'Beranda',
      upload: 'Unggah RPS',
      profile: 'Profil',
    },
  },

  sampleDataLabel: 'Data contoh',
} as const
