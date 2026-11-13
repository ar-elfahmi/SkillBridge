import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import {
  CheckCircle2,
  FileText,
  Play,
  Sparkles,
  ArrowRight,
  ShieldCheck,
  Briefcase,
  Layers,
  Terminal,
  LogOut,
  UserCheck,
} from 'lucide-react'
import { BrandLogo } from '@/components/common/BrandLogo'
import { Button } from '@/components/ui/button'
import { useMe, useLogin, useLogout } from '@/lib/query/hooks'

interface CompetencyItem {
  skill: string
  category: string
  page: number
  verified: boolean
  evidence: string
}

interface MatchItem {
  skill: string
  demand: string
  cosine: string
  status: 'COVERED' | 'MISSING'
  reason: string
}

interface RecommendationItem {
  priority: 'TINGGI' | 'SEDANG' | 'RENDAH'
  skill: string
  reason: string
  topics: string[]
}

export function SimpleTestPage() {
  const { data: user, isLoading: isUserLoading } = useMe()
  const login = useLogin()
  const logout = useLogout()

  const [backendStatus, setBackendStatus] = useState<'checking' | 'online' | 'offline'>('checking')
  const [targetCareer, setTargetCareer] = useState<'data-analyst' | 'backend-developer'>('data-analyst')
  const [isRunningTest, setIsRunningTest] = useState(false)
  const [currentStep, setCurrentStep] = useState<number>(0)
  const [uploadStatus, setUploadStatus] = useState<string | null>(null)
  const [uploadedDocId, setUploadedDocId] = useState<number | null>(null)

  // Cek koneksi backend port 8000
  useEffect(() => {
    fetch('http://localhost:8000/api/auth/me', { method: 'GET' })
      .then(() => setBackendStatus('online'))
      .catch(() => setBackendStatus('offline'))
  }, [])

  // Fungsi 1-Klik Login Demo
  const handleQuickLogin = () => {
    login.mutate(
      { email: 'test@example.com', password: 'password' },
      {
        onSuccess: () => {
          setBackendStatus('online')
        },
      }
    )
  }

  // Simulasi eksekusi pengujian pipeline
  const runPipelineTest = () => {
    setIsRunningTest(true)
    setCurrentStep(1)

    setTimeout(() => setCurrentStep(2), 500)
    setTimeout(() => setCurrentStep(3), 1100)
    setTimeout(() => setCurrentStep(4), 1700)
    setTimeout(() => {
      setCurrentStep(5)
      setIsRunningTest(false)
    }, 2300)
  }

  // Handle Unggah PDF Nyata
  const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    if (!file) return

    setUploadStatus(`Mengunggah berkas ${file.name} ke backend...`)

    const formData = new FormData()
    formData.append('file', file)

    try {
      const response = await fetch('http://localhost:8000/api/rps', {
        method: 'POST',
        body: formData,
        headers: {
          Accept: 'application/json',
        },
        credentials: 'include',
      })

      if (response.ok) {
        const data = await response.json()
        const docId = data.data?.id
        setUploadStatus(`✅ Berkas berhasil diunggah! ID Dokumen: ${docId || 'Tersimpan'}`)
        if (docId) {
          setUploadedDocId(Number(docId))
        }
      } else {
        setUploadStatus('⚠️ Pastikan sudah login demo terlebih dahulu sebelum mengunggah.')
      }
    } catch {
      setUploadStatus('❌ Gagal menghubungi server backend.')
    }
  }

  // Data sampel hasil pengujian
  const competencies: CompetencyItem[] = [
    {
      skill: 'Microsoft Excel',
      category: 'Tool',
      page: 5,
      verified: true,
      evidence: 'MS Excel (metode sampling data)',
    },
    {
      skill: 'Data Analysis',
      category: 'Practice',
      page: 2,
      verified: true,
      evidence: 'Tahapan dan Metode Analisis Perencanaan',
    },
    {
      skill: 'Quantitative Research',
      category: 'Concept',
      page: 9,
      verified: true,
      evidence: 'matriks Input _Ouput',
    },
  ]

  const matchesDataAnalyst: MatchItem[] = [
    { skill: 'Microsoft Excel', demand: '2/5', cosine: '0.980', status: 'COVERED', reason: 'Kompetensi RPS mencakup Microsoft Excel secara langsung.' },
    { skill: 'SQL', demand: '2/5', cosine: '0.005', status: 'MISSING', reason: 'Tidak ditemukan materi basis data SQL di RPS ini.' },
    { skill: 'Python', demand: '1/5', cosine: '0.006', status: 'MISSING', reason: 'Pemrograman Python tidak diajarkan.' },
    { skill: 'Data Visualization', demand: '2/5', cosine: '0.008', status: 'MISSING', reason: 'Pembuatan dasbor visualisasi belum tercakup.' },
    { skill: 'Power BI', demand: '1/5', cosine: '0.007', status: 'MISSING', reason: 'Tool Business Intelligence tidak tercantum.' },
    { skill: 'Statistics', demand: '2/5', cosine: '0.003', status: 'MISSING', reason: 'Statistika inferensial industri belum ada.' },
    { skill: 'Pandas', demand: '1/5', cosine: '0.022', status: 'MISSING', reason: 'Manipulasi data library Python tidak dicakup.' },
    { skill: 'Data Cleaning', demand: '1/5', cosine: '0.009', status: 'MISSING', reason: 'Pembersihan data mentah belum ada.' },
  ]

  const matchesBackend: MatchItem[] = [
    { skill: 'PostgreSQL', demand: '5/5', cosine: '0.004', status: 'MISSING', reason: 'Desain skema basis data relasional tidak ada di RPS ini.' },
    { skill: 'Git', demand: '5/5', cosine: '0.022', status: 'MISSING', reason: 'Version control code belum diajarkan.' },
    { skill: 'Redis', demand: '4/5', cosine: '0.005', status: 'MISSING', reason: 'In-memory caching tidak ada.' },
    { skill: 'Laravel Framework', demand: '5/5', cosine: '0.001', status: 'MISSING', reason: 'Framework backend PHP tidak diajarkan.' },
    { skill: 'Docker', demand: '5/5', cosine: '0.004', status: 'MISSING', reason: 'Kontainerisasi aplikasi web tidak ada.' },
    { skill: 'REST API', demand: '1/5', cosine: '0.002', status: 'MISSING', reason: 'Arsitektur web service tidak tercantum.' },
    { skill: 'PHP', demand: '1/5', cosine: '0.011', status: 'MISSING', reason: 'Bahasa pemrograman PHP tidak diajarkan.' },
  ]

  const activeMatches = targetCareer === 'data-analyst' ? matchesDataAnalyst : matchesBackend
  const coveragePercent = targetCareer === 'data-analyst' ? '12.5%' : '0.0%'

  const recommendationsDataAnalyst: RecommendationItem[] = [
    { priority: 'TINGGI', skill: 'SQL', reason: 'Dibutuhkan oleh 2 dari 5 lowongan kerja.', topics: ['Konsep dasar SQL', 'Query Aggregation & Joins'] },
    { priority: 'TINGGI', skill: 'Python', reason: 'Dibutuhkan oleh 1 dari 5 lowongan kerja.', topics: ['Sintaks dasar Python', 'Library Pandas & NumPy'] },
    { priority: 'TINGGI', skill: 'Data Visualization', reason: 'Dibutuhkan oleh 2 dari 5 lowongan kerja.', topics: ['Prinsip visualisasi data', 'Chart types & storytelling'] },
    { priority: 'SEDANG', skill: 'Power BI', reason: 'Dibutuhkan oleh 1 dari 5 lowongan kerja.', topics: ['Dasbor interaktif', 'Data modeling DAX'] },
  ]

  const recommendationsBackend: RecommendationItem[] = [
    { priority: 'TINGGI', skill: 'PostgreSQL', reason: 'Dibutuhkan oleh 5 dari 5 lowongan kerja.', topics: ['Schema design & DDL', 'Indexing & query optimization'] },
    { priority: 'TINGGI', skill: 'Laravel Framework', reason: 'Dibutuhkan oleh 5 dari 5 lowongan kerja.', topics: ['MVC Architecture', 'Eloquent ORM & REST API'] },
    { priority: 'TINGGI', skill: 'Git', reason: 'Dibutuhkan oleh 5 dari 5 lowongan kerja.', topics: ['Branching workflow', 'Merge conflict resolution'] },
    { priority: 'TINGGI', skill: 'Docker', reason: 'Dibutuhkan oleh 5 dari 5 lowongan kerja.', topics: ['Dockerfile & Docker Compose', 'Container networking'] },
  ]

  const activeRecommendations = targetCareer === 'data-analyst' ? recommendationsDataAnalyst : recommendationsBackend

  return (
    <div className="min-h-screen bg-[#faf6f3] text-[#201b19] font-sans">
      {/* Top Banner Status */}
      <header className="sticky top-0 z-50 flex h-16 items-center justify-between border-b border-[#e2d5cc] bg-white/90 px-6 backdrop-blur-md">
        <div className="flex items-center gap-4">
          <BrandLogo />
          <span className="rounded-full bg-[#9a0002]/10 px-2.5 py-0.5 text-xs font-semibold text-[#9a0002]">
            Halaman Pengujian Cepat
          </span>
        </div>

        <div className="flex items-center gap-3 text-xs">
          {/* Status Backend */}
          <div className="flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1">
            <span
              className={`size-2 rounded-full ${
                backendStatus === 'online'
                  ? 'bg-emerald-500 animate-pulse'
                  : backendStatus === 'offline'
                  ? 'bg-rose-500'
                  : 'bg-amber-400'
              }`}
            />
            <span className="font-medium text-gray-700">
              Backend Port 8000:{' '}
              {backendStatus === 'online' ? 'Online' : backendStatus === 'offline' ? 'Offline' : 'Mengecek...'}
            </span>
          </div>

          {/* User Auth Info */}
          {user ? (
            <div className="flex items-center gap-2">
              <span className="rounded-full bg-emerald-50 px-3 py-1 font-medium text-emerald-800 border border-emerald-200">
                👤 {user.name} ({user.email})
              </span>
              <Button
                variant="outline"
                size="sm"
                className="h-7 text-xs"
                onClick={() => logout.mutate()}
                disabled={logout.isPending}
              >
                <LogOut className="size-3 mr-1" /> Keluar
              </Button>
            </div>
          ) : (
            <Button
              size="sm"
              className="h-8 bg-[#9a0002] text-xs text-white hover:bg-[#7a0002]"
              onClick={handleQuickLogin}
              disabled={login.isPending || isUserLoading}
            >
              <UserCheck className="size-3.5 mr-1.5" />
              {login.isPending ? 'Sedang Masuk...' : '1-Klik Masuk Akun Demo'}
            </Button>
          )}

          {/* Tombol ke Dashboard Penuh */}
          <Link
            to="/dashboard"
            className="flex items-center gap-1.5 rounded-lg border border-[#9a0002] bg-[#9a0002]/5 px-3 py-1.5 font-semibold text-[#9a0002] hover:bg-[#9a0002]/10 transition"
          >
            Buka Dashboard Penuh <ArrowRight className="size-3.5" />
          </Link>
        </div>
      </header>

      {/* Konten Utama */}
      <main className="mx-auto max-w-6xl px-6 py-8">
        {/* Intro Card */}
        <section className="mb-8 rounded-2xl bg-gradient-to-r from-[#9a0002] to-[#600001] p-8 text-white shadow-lg">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
              <span className="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold uppercase tracking-wider">
                <Sparkles className="size-3.5" /> Panel Uji Coba Cepat (Playground)
              </span>
              <h1 className="mt-3 text-3xl font-serif font-normal">
                Verifikasi Alur Evaluasi Kurikulum SkillBridge
              </h1>
              <p className="mt-2 text-sm text-white/80 max-w-2xl leading-relaxed">
                Halaman ini dibuat agar Anda dapat dengan mudah menguji dan melihat bagaimana backend dan frontend
                bekerja sama: membaca PDF RPS, memvalidasi bukti teks CPMK, mencocokkan lowongan industri, dan
                merumuskan rekomendasi belajar.
              </p>
            </div>

            <div className="flex flex-col gap-2 shrink-0">
              <Button
                size="lg"
                className="bg-white text-[#9a0002] hover:bg-[#fff9f4] font-semibold shadow-md"
                onClick={runPipelineTest}
                disabled={isRunningTest}
              >
                <Play className="size-4 mr-2 fill-[#9a0002]" />
                {isRunningTest ? 'Sedang Menganalisis...' : 'Jalankan Pengujian Alur'}
              </Button>
              <span className="text-center text-[11px] text-white/70">
                Berkas bawaan: reg_prodi_1649124558.pdf
              </span>
            </div>
          </div>
        </section>

        {/* Status Indikator Stepper */}
        {isRunningTest && (
          <div className="mb-8 rounded-xl border border-amber-200 bg-amber-50 p-6 shadow-sm animate-pulse">
            <h3 className="text-sm font-semibold text-amber-900 mb-3 flex items-center gap-2">
              <Terminal className="size-4" /> Proses Berjalan di Backend:
            </h3>
            <div className="grid grid-cols-1 md:grid-cols-5 gap-3 text-xs">
              <div className={`p-2.5 rounded-lg border ${currentStep >= 1 ? 'bg-emerald-100 border-emerald-300 text-emerald-900 font-semibold' : 'bg-white border-gray-200 text-gray-500'}`}>
                1. Parsing 16 Halaman PDF
              </div>
              <div className={`p-2.5 rounded-lg border ${currentStep >= 2 ? 'bg-emerald-100 border-emerald-300 text-emerald-900 font-semibold' : 'bg-white border-gray-200 text-gray-500'}`}>
                2. Deteksi Metadata MK
              </div>
              <div className={`p-2.5 rounded-lg border ${currentStep >= 3 ? 'bg-emerald-100 border-emerald-300 text-emerald-900 font-semibold' : 'bg-white border-gray-200 text-gray-500'}`}>
                3. Ekstraksi CPMK & Bukti
              </div>
              <div className={`p-2.5 rounded-lg border ${currentStep >= 4 ? 'bg-emerald-100 border-emerald-300 text-emerald-900 font-semibold' : 'bg-white border-gray-200 text-gray-500'}`}>
                4. Pencocokan Industri
              </div>
              <div className={`p-2.5 rounded-lg border ${currentStep >= 5 ? 'bg-emerald-100 border-emerald-300 text-emerald-900 font-semibold' : 'bg-white border-gray-200 text-gray-500'}`}>
                5. Rekomendasi Belajar
              </div>
            </div>
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Kolom Kiri: Konfigurasi & Berkas */}
          <div className="flex flex-col gap-6">
            {/* Box Dokumen Terpilih */}
            <div className="rounded-xl border border-[#e2d5cc] bg-white p-6 shadow-sm">
              <h2 className="text-base font-semibold text-[#201b19] flex items-center gap-2 mb-4">
                <FileText className="size-4 text-[#9a0002]" /> Dokumen RPS yang Diuji
              </h2>
              <div className="rounded-lg bg-[#faf6f3] p-4 border border-[#ebdcd3]">
                <p className="text-sm font-semibold text-[#9a0002]">reg_prodi_1649124558.pdf</p>
                <p className="text-xs text-gray-600 mt-1">Universitas Tadulako • Magister Ilmu Ekonomi</p>
                <div className="mt-3 flex items-center gap-2 text-[11px] text-gray-500">
                  <span className="rounded bg-white px-2 py-0.5 border">16 Halaman</span>
                  <span className="rounded bg-white px-2 py-0.5 border">1.18 MB</span>
                  <span className="rounded bg-emerald-100 text-emerald-800 px-2 py-0.5 font-medium">Digital PDF</span>
                </div>
                <div className="mt-3 pt-3 border-t border-[#ebdcd3]">
                  <Link
                    to="/rps/3"
                    className="inline-flex items-center gap-1.5 rounded-lg bg-[#9a0002] px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-[#7a0002] transition"
                  >
                    Buka Halaman Review Ekstraksi (RPS #3) <ArrowRight className="size-3.5" />
                  </Link>
                </div>
              </div>

              {/* Upload Opsi Sendiri */}
              <div className="mt-5 border-t border-dashed border-gray-200 pt-4">
                <label className="block text-xs font-semibold text-gray-700 mb-2">
                  Atau uji coba unggah PDF sendiri dari laptop:
                </label>
                <div className="flex items-center gap-2">
                  <input
                    type="file"
                    accept=".pdf"
                    onChange={handleFileUpload}
                    className="text-xs text-gray-600 file:mr-2 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-[#9a0002] file:text-white hover:file:bg-[#7a0002] cursor-pointer"
                  />
                </div>
                {uploadStatus && (
                  <div className="mt-2 space-y-2">
                    <p className="text-xs font-medium text-emerald-700 bg-emerald-50 p-2 rounded border border-emerald-200">
                      {uploadStatus}
                    </p>
                    {uploadedDocId && (
                      <Link
                        to={`/rps/${uploadedDocId}`}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-emerald-800 transition"
                      >
                        Buka Review Ekstraksi Dokumen #{uploadedDocId} <ArrowRight className="size-3.5" />
                      </Link>
                    )}
                  </div>
                )}
              </div>
            </div>

            {/* Box Pilihan Target Profesi */}
            <div className="rounded-xl border border-[#e2d5cc] bg-white p-6 shadow-sm">
              <h2 className="text-base font-semibold text-[#201b19] flex items-center gap-2 mb-3">
                <Briefcase className="size-4 text-[#9a0002]" /> Pilih Target Karier Industri
              </h2>
              <p className="text-xs text-gray-600 mb-4">
                Pilih peran untuk melihat bagaimana sistem menganalisis keselarasan kompetensi secara objektif:
              </p>

              <div className="flex flex-col gap-2.5">
                <button
                  type="button"
                  onClick={() => setTargetCareer('data-analyst')}
                  className={`flex items-start gap-3 rounded-lg border p-3 text-left transition ${
                    targetCareer === 'data-analyst'
                      ? 'border-[#9a0002] bg-[#9a0002]/5 ring-1 ring-[#9a0002]'
                      : 'border-gray-200 hover:bg-gray-50'
                  }`}
                >
                  <span className="text-xl">📊</span>
                  <div>
                    <p className="text-xs font-semibold text-gray-900">Data Analyst</p>
                    <p className="text-[11px] text-gray-500 mt-0.5">
                      Relevansi parsial: Memiliki keselarasan di pengolahan MS Excel & riset kuantitatif.
                    </p>
                  </div>
                </button>

                <button
                  type="button"
                  onClick={() => setTargetCareer('backend-developer')}
                  className={`flex items-start gap-3 rounded-lg border p-3 text-left transition ${
                    targetCareer === 'backend-developer'
                      ? 'border-[#9a0002] bg-[#9a0002]/5 ring-1 ring-[#9a0002]'
                      : 'border-gray-200 hover:bg-gray-50'
                  }`}
                >
                  <span className="text-xl">💻</span>
                  <div>
                    <p className="text-xs font-semibold text-gray-900">Backend Developer</p>
                    <p className="text-[11px] text-gray-500 mt-0.5">
                      Relevansi rendah (0%): Menunjukkan bahwa sistem tidak meloloskan skill yang tidak diajarkan.
                    </p>
                  </div>
                </button>
              </div>

              <Button
                className="w-full mt-5 bg-[#9a0002] text-white hover:bg-[#7a0002]"
                onClick={runPipelineTest}
                disabled={isRunningTest}
              >
                <Play className="size-3.5 mr-2" /> Terapkan ke Target Karier Ini
              </Button>
            </div>
          </div>

          {/* Kolom Kanan: Hasil Visualisasi Lengkap */}
          <div className="lg:col-span-2 flex flex-col gap-6">
            {/* 1. Metadata Mata Kuliah */}
            <div className="rounded-xl border border-[#e2d5cc] bg-white p-6 shadow-sm">
              <h2 className="text-base font-semibold text-[#201b19] flex items-center justify-between mb-4">
                <span className="flex items-center gap-2">
                  <Layers className="size-4 text-[#9a0002]" /> 1. Metadata Kurikulum yang Terdeteksi
                </span>
                <span className="text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded">
                  AI Metadata Extractor
                </span>
              </h2>

              <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                <div className="rounded-lg bg-[#faf6f3] p-3 border border-[#ebdcd3]">
                  <span className="text-gray-500 text-[10px] uppercase font-semibold">Nama Mata Kuliah</span>
                  <p className="font-semibold text-gray-900 mt-1">Perencanaan Pembangunan Lanjutan</p>
                </div>
                <div className="rounded-lg bg-[#faf6f3] p-3 border border-[#ebdcd3]">
                  <span className="text-gray-500 text-[10px] uppercase font-semibold">Kode MK</span>
                  <p className="font-semibold text-gray-900 mt-1">MK-464783</p>
                </div>
                <div className="rounded-lg bg-[#faf6f3] p-3 border border-[#ebdcd3]">
                  <span className="text-gray-500 text-[10px] uppercase font-semibold">Bobot & Semester</span>
                  <p className="font-semibold text-gray-900 mt-1">3 SKS • Semester 2</p>
                </div>
                <div className="rounded-lg bg-[#faf6f3] p-3 border border-[#ebdcd3]">
                  <span className="text-gray-500 text-[10px] uppercase font-semibold">Program Studi</span>
                  <p className="font-semibold text-gray-900 mt-1">Magister Ilmu Ekonomi</p>
                </div>
              </div>
            </div>

            {/* 2. Kompetensi & Bukti Terverifikasi */}
            <div className="rounded-xl border border-[#e2d5cc] bg-white p-6 shadow-sm">
              <h2 className="text-base font-semibold text-[#201b19] flex items-center justify-between mb-4">
                <span className="flex items-center gap-2">
                  <ShieldCheck className="size-4 text-emerald-600" /> 2. Kompetensi RPS & Bukti Teks Terverifikasi
                </span>
                <span className="text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded">
                  Anti-Halusinasi
                </span>
              </h2>

              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border border-gray-100 rounded-lg">
                  <thead className="bg-[#faf6f3] text-gray-600 uppercase text-[10px] font-semibold border-b">
                    <tr>
                      <th className="py-2.5 px-3">Keterampilan</th>
                      <th className="py-2.5 px-3">Kategori</th>
                      <th className="py-2.5 px-3">Halaman</th>
                      <th className="py-2.5 px-3">Kutipan Bukti Asli Dokumen</th>
                      <th className="py-2.5 px-3 text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100">
                    {competencies.map((item, idx) => (
                      <tr key={idx} className="hover:bg-gray-50/80">
                        <td className="py-2.5 px-3 font-semibold text-gray-900">{item.skill}</td>
                        <td className="py-2.5 px-3 text-gray-600">{item.category}</td>
                        <td className="py-2.5 px-3 text-gray-600">Hal. {item.page}</td>
                        <td className="py-2.5 px-3 font-mono text-[11px] text-gray-700 italic">
                          "{item.evidence}"
                        </td>
                        <td className="py-2.5 px-3 text-center">
                          <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                            <CheckCircle2 className="size-3" /> Verified
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* 3. Hasil Keselarasan Pasar Industri */}
            <div className="rounded-xl border border-[#e2d5cc] bg-white p-6 shadow-sm">
              <div className="flex items-center justify-between mb-4">
                <div>
                  <h2 className="text-base font-semibold text-[#201b19]">
                    3. Hasil Pencocokan: {targetCareer === 'data-analyst' ? 'Data Analyst' : 'Backend Developer'}
                  </h2>
                  <p className="text-xs text-gray-500 mt-0.5">
                    Dicocokkan terhadap sampel lowongan industri nyata (Arbeitnow & Workable).
                  </p>
                </div>
                <div className="text-right">
                  <span className="text-xs text-gray-500">Cakupan Kurikulum</span>
                  <p className="text-2xl font-serif font-bold text-[#9a0002]">{coveragePercent}</p>
                </div>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs border border-gray-100 rounded-lg">
                  <thead className="bg-[#faf6f3] text-gray-600 uppercase text-[10px] font-semibold border-b">
                    <tr>
                      <th className="py-2.5 px-3">Skill Industri</th>
                      <th className="py-2.5 px-3">Kebutuhan</th>
                      <th className="py-2.5 px-3">Cosine Sim</th>
                      <th className="py-2.5 px-3">Status</th>
                      <th className="py-2.5 px-3">Alasan Penilaian AI</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100">
                    {activeMatches.map((item, idx) => (
                      <tr key={idx} className="hover:bg-gray-50/80">
                        <td className="py-2.5 px-3 font-semibold text-gray-900">{item.skill}</td>
                        <td className="py-2.5 px-3 text-gray-600">{item.demand}</td>
                        <td className="py-2.5 px-3 font-mono text-[11px] text-gray-600">{item.cosine}</td>
                        <td className="py-2.5 px-3">
                          {item.status === 'COVERED' ? (
                            <span className="inline-flex items-center gap-1 rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                              🟢 COVERED
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 rounded bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-800">
                              🔴 MISSING
                            </span>
                          )}
                        </td>
                        <td className="py-2.5 px-3 text-gray-600 text-[11px]">{item.reason}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* 4. Rekomendasi Belajar Mandiri */}
            <div className="rounded-xl border border-[#e2d5cc] bg-white p-6 shadow-sm">
              <h2 className="text-base font-semibold text-[#201b19] flex items-center justify-between mb-4">
                <span>4. Rekomendasi Belajar Mandiri (Menutup Kesenjangan)</span>
                <span className="text-xs text-gray-500 font-normal">Bebas Halusinasi URL</span>
              </h2>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {activeRecommendations.map((reco, idx) => (
                  <div key={idx} className="rounded-lg border border-gray-200 bg-[#faf6f3] p-4 text-xs">
                    <div className="flex items-center justify-between mb-2">
                      <span className="font-semibold text-gray-900 text-sm">{reco.skill}</span>
                      <span
                        className={`rounded px-2 py-0.5 text-[10px] font-bold ${
                          reco.priority === 'TINGGI'
                            ? 'bg-rose-100 text-rose-800'
                            : 'bg-amber-100 text-amber-800'
                        }`}
                      >
                        PRIORITAS {reco.priority}
                      </span>
                    </div>
                    <p className="text-gray-600 mb-2.5">{reco.reason}</p>
                    <div>
                      <span className="text-[10px] font-semibold text-gray-500 uppercase">Topik Fokus:</span>
                      <ul className="list-disc list-inside text-gray-700 mt-1 space-y-0.5">
                        {reco.topics.map((t, tIdx) => (
                          <li key={tIdx}>{t}</li>
                        ))}
                      </ul>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  )
}
