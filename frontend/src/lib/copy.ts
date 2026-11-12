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
    registerTitle: 'Daftar akun',
    emailLabel: 'Email',
    passwordLabel: 'Kata sandi',
    nameLabel: 'Nama lengkap',
    submitLogin: 'Masuk',
    submitRegister: 'Daftar',
    needAccount: 'Belum punya akun?',
    haveAccount: 'Sudah punya akun?',
  },

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
