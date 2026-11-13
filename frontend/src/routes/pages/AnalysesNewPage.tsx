import { useState, useEffect, useId, useMemo } from 'react'
import { useSearchParams, useNavigate, Link } from 'react-router-dom'
import {
  Briefcase,
  Building2,
  Sparkles,
  CheckCircle2,
  ArrowRight,
  ArrowLeft,
  Check,
  Plus,
  AlertCircle,
  FileText,
  CheckSquare,
  Square,
  RefreshCw,
  AlertTriangle,
  X,
  Search,
  ExternalLink,
} from 'lucide-react'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
import {
  useRpsDetail,
  useRpsList,
  useCareersList,
  useJobsList,
  useCreateAnalysis,
  useCreateJob,
  useConfirmRpsReview,
  useMe,
  useLogout,
} from '@/lib/query/hooks'
import { copy } from '@/lib/copy'

export function AnalysesNewPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()

  const { data: user } = useMe()
  const logout = useLogout()

  // RPS Selection
  const initialRpsId = searchParams.get('rps') ? Number(searchParams.get('rps')) : null
  const [selectedRpsId, setSelectedRpsId] = useState<number | null>(initialRpsId)

  const { data: rpsList = [] } = useRpsList()
  const { data: currentRpsDoc, refetch: refetchRps } = useRpsDetail(selectedRpsId || 0)

  // Careers & Jobs Selection
  const { data: careers = [], isLoading: isCareersLoading } = useCareersList()
  const [selectedCareerId, setSelectedCareerId] = useState<number | null>(null)

  // Filter Sumber: Semua, Riil / Asli, atau Sintetis
  const [sourceTypeFilter, setSourceTypeFilter] = useState<'all' | 'real' | 'synthetic'>('all')

  const { data: jobs = [], isLoading: isJobsLoading } = useJobsList(
    selectedCareerId || undefined,
    sourceTypeFilter
  )
  const [selectedJobIds, setSelectedJobIds] = useState<number[]>([])

  // Search and Filter Jobs
  const [searchJobQuery, setSearchJobQuery] = useState('')

  const filteredJobs = useMemo(() => {
    if (!searchJobQuery.trim()) return jobs
    const q = searchJobQuery.toLowerCase()
    return jobs.filter((job) => {
      const titleMatch = job.title?.toLowerCase().includes(q)
      const companyMatch = job.company?.toLowerCase().includes(q)
      const skillMatch = job.skills?.some((s) => s.skill?.toLowerCase().includes(q))
      return Boolean(titleMatch || companyMatch || skillMatch)
    })
  }, [jobs, searchJobQuery])

  // Mutations
  const createAnalysis = useCreateAnalysis()
  const createJob = useCreateJob()
  const confirmReview = useConfirmRpsReview()

  // Modal Tambah Lowongan
  const [isAddJobModalOpen, setIsAddJobModalOpen] = useState(false)
  const [jobTitle, setJobTitle] = useState('')
  const [jobCompany, setJobCompany] = useState('')
  const [jobDescription, setJobDescription] = useState('')
  const [jobSourceUrl, setJobSourceUrl] = useState('')
  const [addJobError, setAddJobError] = useState<string | null>(null)

  // Form Field IDs for Accessibility
  const manualJobTitleId = useId()
  const manualJobCompanyId = useId()
  const manualJobUrlId = useId()
  const manualJobDescId = useId()

  // Notification Toast
  const [notification, setNotification] = useState<{ type: 'success' | 'error'; message: string } | null>(null)

  // Set default RPS if not provided
  useEffect(() => {
    if (!selectedRpsId && rpsList.length > 0) {
      // Prioritize an extracted RPS
      const completedRps = rpsList.find((r) => r.extraction_status === 'completed')
      setSelectedRpsId(completedRps ? completedRps.id : rpsList[0].id)
    }
  }, [rpsList, selectedRpsId])

  // Set default career if not selected
  useEffect(() => {
    if (!selectedCareerId && careers.length > 0) {
      setSelectedCareerId(careers[0].id)
    }
  }, [careers, selectedCareerId])

  // Pre-select first 3 to 5 jobs when career changes
  useEffect(() => {
    if (jobs.length > 0) {
      const topFiveIds = jobs.slice(0, 5).map((j) => j.id)
      setSelectedJobIds(topFiveIds)
    } else {
      setSelectedJobIds([])
    }
  }, [selectedCareerId, jobs])

  function handleLogout() {
    logout.mutate(undefined, {
      onSuccess: () => navigate('/login'),
    })
  }

  function handleToggleJob(jobId: number) {
    setSelectedJobIds((prev) =>
      prev.includes(jobId) ? prev.filter((id) => id !== jobId) : [...prev, jobId]
    )
  }

  function handleSelectTopFive() {
    const topFiveIds = filteredJobs.slice(0, 5).map((j) => j.id)
    setSelectedJobIds(topFiveIds)
  }

  function handleSelectAllJobs() {
    setSelectedJobIds(filteredJobs.map((j) => j.id))
  }

  function handleDeselectAllJobs() {
    setSelectedJobIds([])
  }

  function handleQuickConfirmRps() {
    if (!selectedRpsId) return
    confirmReview.mutate(
      { rpsId: selectedRpsId, confirmAllPending: true },
      {
        onSuccess: () => {
          refetchRps()
          setNotification({ type: 'success', message: 'RPS berhasil dikonfirmasi!' })
          setTimeout(() => setNotification(null), 3000)
        },
        onError: () => {
          setNotification({ type: 'error', message: 'Gagal mengonfirmasi RPS.' })
        },
      }
    )
  }

  function handleCreateJobSubmit() {
    if (!selectedCareerId) return
    if (!jobTitle.trim() || !jobCompany.trim() || !jobDescription.trim()) {
      setAddJobError('Judul posisi, nama perusahaan, dan deskripsi wajib diisi.')
      return
    }

    createJob.mutate(
      {
        career_id: selectedCareerId,
        title: jobTitle.trim(),
        company: jobCompany.trim(),
        description: jobDescription.trim(),
        source_url: jobSourceUrl.trim() || undefined,
      },
      {
        onSuccess: (res) => {
          setIsAddJobModalOpen(false)
          setJobTitle('')
          setJobCompany('')
          setJobDescription('')
          setJobSourceUrl('')
          setAddJobError(null)

          if (res?.data?.id) {
            setSelectedJobIds((prev) => [...prev, res.data.id])
          }

          setNotification({ type: 'success', message: 'Lowongan baru berhasil ditambahkan dan dipilih!' })
          setTimeout(() => setNotification(null), 3000)
        },
        onError: (err: any) => {
          setAddJobError(err?.body?.message || 'Gagal menambahkan lowongan manual.')
        },
      }
    )
  }

  function handleStartAnalysis() {
    if (!selectedRpsId) {
      setNotification({ type: 'error', message: 'Pilih dokumen RPS terlebih dahulu.' })
      return
    }
    if (!selectedCareerId) {
      setNotification({ type: 'error', message: 'Pilih target profesi terlebih dahulu.' })
      return
    }
    if (selectedJobIds.length === 0) {
      setNotification({ type: 'error', message: 'Pilih minimal 1 lowongan kerja untuk pemetaan.' })
      return
    }

    createAnalysis.mutate(
      {
        rps_document_id: selectedRpsId,
        career_id: selectedCareerId,
        job_posting_ids: selectedJobIds,
      },
      {
        onSuccess: (res: any) => {
          const analysisId = res?.data?.analysis_id || res?.data?.id
          setNotification({
            type: 'success',
            message: 'Analisis keselarasan kurikulum dimulai! Mengarahkan ke hasil...',
          })
          setTimeout(() => {
            navigate(`/analyses/${analysisId}`)
          }, 600)
        },
        onError: (err: any) => {
          setNotification({
            type: 'error',
            message: err?.body?.message || 'Gagal memulai analisis keselarasan.',
          })
        },
      }
    )
  }

  const selectedCareer = careers.find((c) => c.id === selectedCareerId)
  const isRpsReady =
    currentRpsDoc?.extraction_status === 'completed' && currentRpsDoc?.review_status === 'confirmed'

  return (
    <div className="flex min-h-svh flex-col bg-background pb-32">
      {/* Topbar */}
      <DashboardTopbar user={user} onLogout={handleLogout} />

      {/* Notification Toast */}
      {notification && (
        <div
          role="status"
          aria-live="polite"
          className={`fixed top-20 right-6 z-50 flex items-center gap-3 rounded-xl px-4 py-3 shadow-lg border transition-all ${
            notification.type === 'success'
              ? 'bg-emerald-50 border-emerald-200 text-emerald-900'
              : 'bg-rose-50 border-rose-200 text-rose-900'
          }`}
        >
          {notification.type === 'success' ? (
            <CheckCircle2 className="size-5 text-emerald-600 shrink-0" />
          ) : (
            <AlertCircle className="size-5 text-rose-600 shrink-0" />
          )}
          <p className="text-sm font-medium">{notification.message}</p>
        </div>
      )}

      {/* Main Content */}
      <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-8 md:py-8">
        {/* Navigation Breadcrumb */}
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-2 text-xs md:text-sm text-text-secondary">
            <Link to="/dashboard" className="transition hover:text-brand flex items-center gap-1 font-medium">
              <ArrowLeft className="size-3.5" /> Dashboard
            </Link>
            <span>/</span>
            {selectedRpsId && (
              <>
                <Link to={`/rps/${selectedRpsId}`} className="transition hover:text-brand font-medium">
                  Review Ekstraksi RPS #{selectedRpsId}
                </Link>
                <span>/</span>
              </>
            )}
            <span className="text-brand font-semibold">Pemetaan Industri (T5.4)</span>
          </div>
        </div>

        {/* Header Hero Card */}
        <div className="rounded-2xl border border-border-warm bg-white p-6 shadow-xs">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <span className="inline-flex items-center gap-1 rounded-full bg-brand-soft px-3 py-1 text-xs font-bold text-brand uppercase tracking-wider mb-2">
                <Sparkles className="size-3.5" /> Pemetaan Keselarasan Kurikulum
              </span>
              <h1 className="font-serif text-2xl md:text-3xl font-bold text-text-primary">
                Pilih Target Profesi & Lowongan Kerja Industri
              </h1>
              <p className="text-xs md:text-sm text-text-secondary mt-1 max-w-2xl leading-relaxed">
                Petakan kompetensi hasil ekstraksi dokumen RPS terhadap kualifikasi nyata dari lowongan kerja industri untuk mendeteksi keselarasan (*covered*), irisan (*partial*), dan kesenjangan skill (*missing*).
              </p>
            </div>
          </div>

          {/* RPS Target Info Box */}
          <div className="mt-6 pt-5 border-t border-border-warm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div className="flex items-start gap-3">
              <div className="flex size-10 items-center justify-center rounded-xl bg-brand/10 text-brand shrink-0 mt-0.5">
                <FileText className="size-5" />
              </div>
              <div>
                <p className="text-[11px] font-bold text-text-secondary uppercase tracking-wider">Dokumen RPS yang Digunakan</p>
                <div className="flex items-center gap-2 flex-wrap mt-0.5">
                  <h3 className="font-bold text-base text-text-primary">
                    {currentRpsDoc?.course?.name || currentRpsDoc?.detected_metadata?.name || currentRpsDoc?.original_name || 'Pilih RPS'}
                  </h3>
                  {currentRpsDoc?.course?.code && (
                    <span className="rounded bg-surface-canvas border px-2 py-0.5 text-xs font-semibold text-text-secondary">
                      {currentRpsDoc.course.code}
                    </span>
                  )}
                  {currentRpsDoc?.course?.credits && (
                    <span className="text-xs text-text-secondary">
                      • {currentRpsDoc.course.credits} SKS
                    </span>
                  )}
                </div>

                {/* Status RPS */}
                <div className="mt-1 flex items-center gap-2">
                  {isRpsReady ? (
                    <span className="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                      <CheckCircle2 className="size-3" /> Ekstraksi Terkonfirmasi
                    </span>
                  ) : currentRpsDoc?.extraction_status === 'completed' ? (
                    <div className="flex items-center gap-2">
                      <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                        <AlertTriangle className="size-3" /> Belum Dikonfirmasi
                      </span>
                      <button
                        type="button"
                        onClick={handleQuickConfirmRps}
                        disabled={confirmReview.isPending}
                        className="text-[11px] font-bold text-brand hover:underline"
                      >
                        {confirmReview.isPending ? 'Mengonfirmasi...' : 'Konfirmasi Sekarang →'}
                      </button>
                    </div>
                  ) : (
                    <Link
                      to={`/rps/${selectedRpsId}`}
                      className="inline-flex items-center gap-1 text-[11px] font-bold text-brand hover:underline"
                    >
                      Buka Review RPS untuk Menjalankan Ekstraksi AI →
                    </Link>
                  )}
                </div>
              </div>
            </div>

            {/* Switcher RPS (if user has multiple) */}
            {rpsList.length > 1 && (
              <div className="flex items-center gap-2">
                <label htmlFor="rps-select-dropdown" className="text-xs font-bold text-text-secondary whitespace-nowrap">Ganti RPS:</label>
                <select
                  id="rps-select-dropdown"
                  value={selectedRpsId || ''}
                  onChange={(e) => setSelectedRpsId(Number(e.target.value))}
                  className="rounded-xl border border-border-warm bg-surface-canvas px-3 py-1.5 text-xs font-bold text-text-primary outline-none focus:border-brand cursor-pointer"
                >
                  {rpsList.map((doc) => (
                    <option key={doc.id} value={doc.id}>
                      {doc.course?.name || doc.original_name} (#{doc.id})
                    </option>
                  ))}
                </select>
              </div>
            )}
          </div>
        </div>

        {/* STEP 1: PILIH TARGET PROFESI */}
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="flex size-6 items-center justify-center rounded-full bg-brand text-white font-bold text-xs">
                1
              </div>
              <h2 className="text-base font-bold text-text-primary">
                Pilih Target Karier / Profesi Industri
              </h2>
            </div>
            <span className="text-xs text-text-secondary font-medium">
              {careers.length} profil karier tersedia
            </span>
          </div>

          {isCareersLoading ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              {[1, 2, 3, 4].map((i) => (
                <div key={i} className="h-28 rounded-2xl bg-white border border-border-warm p-4 animate-pulse space-y-2">
                  <div className="h-4 w-3/4 bg-gray-200 rounded" />
                  <div className="h-3 w-full bg-gray-100 rounded" />
                  <div className="h-3 w-1/2 bg-gray-100 rounded" />
                </div>
              ))}
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
              {careers.map((career) => {
                const isSelected = selectedCareerId === career.id
                return (
                  <button
                    key={career.id}
                    type="button"
                    onClick={() => setSelectedCareerId(career.id)}
                    className={`rounded-2xl border p-4 text-left transition-all duration-200 hover:-translate-y-1 hover:shadow-md active:scale-95 cursor-pointer relative ${
                      isSelected
                        ? 'border-brand bg-brand-soft/25 ring-2 ring-brand/40 shadow-xs'
                        : 'border-border-warm bg-white hover:border-brand/40'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex items-center gap-2">
                        <span className="text-xl">💼</span>
                        <h3 className="font-bold text-sm text-text-primary leading-tight">
                          {career.name}
                        </h3>
                      </div>
                      {isSelected && (
                        <div className="flex size-5 items-center justify-center rounded-full bg-brand text-white">
                          <Check className="size-3 stroke-[3]" />
                        </div>
                      )}
                    </div>
                    {career.description && (
                      <p className="mt-2 text-xs text-text-secondary line-clamp-2 leading-relaxed">
                        {career.description}
                      </p>
                    )}
                    {career.job_postings_count !== undefined && (
                      <div className="mt-2.5 pt-2 border-t border-border-warm/60 flex items-center justify-between text-[11px]">
                        <span className="font-semibold text-brand flex items-center gap-1">
                          <Briefcase className="size-3 text-brand" />
                          {career.job_postings_count.toLocaleString('id-ID')} lowongan
                        </span>
                        <span className="text-[10px] text-text-secondary">di Database</span>
                      </div>
                    )}
                  </button>
                )
              })}
            </div>
          )}
        </div>

        {/* STEP 2: PILIH LOWONGAN PEKERJAAN */}
        <div className="space-y-4">
          <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div className="flex items-center gap-2">
              <div className="flex size-6 items-center justify-center rounded-full bg-brand text-white font-bold text-xs shrink-0">
                2
              </div>
              <div>
                <h2 className="text-base font-bold text-text-primary">
                  Pilih Lowongan Pekerjaan ({selectedCareer?.name || 'Profesi'})
                </h2>
                <p className="text-xs text-text-secondary">
                  Menampilkan <strong>{filteredJobs.length}</strong> lowongan •{' '}
                  <strong className="text-brand">{selectedJobIds.length}</strong> dipilih untuk analisis
                </p>
              </div>
            </div>

            {/* Filter Sumber Data: Semua, Riil / Asli, Sintetis */}
            <div className="flex items-center gap-1 rounded-xl bg-surface-canvas p-1 border border-border-warm text-xs font-semibold">
              <button
                type="button"
                onClick={() => setSourceTypeFilter('all')}
                className={`px-3 py-1.5 rounded-lg transition font-bold ${
                  sourceTypeFilter === 'all'
                    ? 'bg-white shadow-xs text-brand border border-border-warm'
                    : 'text-text-secondary hover:text-text-primary'
                }`}
              >
                Semua Sumber
              </button>
              <button
                type="button"
                onClick={() => setSourceTypeFilter('real')}
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition font-bold ${
                  sourceTypeFilter === 'real'
                    ? 'bg-white shadow-xs text-emerald-700 border border-emerald-200'
                    : 'text-text-secondary hover:text-text-primary'
                }`}
              >
                <span className="size-2 rounded-full bg-emerald-500" />
                Hanya Riil / Asli
              </button>
              <button
                type="button"
                onClick={() => setSourceTypeFilter('synthetic')}
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition font-bold ${
                  sourceTypeFilter === 'synthetic'
                    ? 'bg-amber-100 shadow-xs text-amber-900 border border-amber-300'
                    : 'text-text-secondary hover:text-text-primary'
                }`}
              >
                <span className="size-2 rounded-full bg-amber-500" />
                Sintetis (Benchmark)
              </button>
            </div>
          </div>

          {/* Banner Peringatan / Keterangan saat Filter Sintetis Aktif */}
          {sourceTypeFilter === 'synthetic' && (
            <div className="flex items-start gap-2.5 rounded-xl border border-amber-300 bg-amber-50/80 p-3.5 text-xs text-amber-900">
              <AlertTriangle className="size-4 shrink-0 text-amber-600 mt-0.5" />
              <div className="space-y-0.5">
                <p className="font-bold">Mode Filter Data Sintetis Aktif (JobStreet, Glints, LinkedIn)</p>
                <p className="text-[11px] text-amber-800 leading-relaxed">
                  Lowongan di bawah merupakan data sintetis/benchmark yang dihimpun dari profil agregat pasar kerja industri. Seluruh skill telah diekstrak dan siap dianalisis keselarasan kurikulumnya.
                </p>
              </div>
            </div>
          )}

          {/* Quick Actions & Search */}
          <div className="flex flex-wrap items-center justify-between gap-3 pt-1">
            {/* Search Box */}
            <div className="relative min-w-[240px] max-w-sm flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-text-secondary" />
              <input
                type="text"
                value={searchJobQuery}
                onChange={(e) => setSearchJobQuery(e.target.value)}
                placeholder="Cari posisi, perusahaan, skill..."
                className="w-full rounded-xl border border-border-warm bg-white pl-8 pr-7 py-1.5 text-xs text-text-primary outline-none focus:border-brand"
              />
              {searchJobQuery && (
                <button
                  type="button"
                  onClick={() => setSearchJobQuery('')}
                  className="absolute right-2 top-1/2 -translate-y-1/2 p-0.5 text-text-secondary hover:text-text-primary"
                  aria-label="Hapus pencarian"
                >
                  <X className="size-3.5" />
                </button>
              )}
            </div>

            {/* Action Buttons */}
            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={handleSelectTopFive}
                className="rounded-xl border border-border-warm bg-white px-3 py-1.5 text-xs font-bold text-text-primary hover:bg-surface-canvas active:scale-95 transition"
              >
                Pilih 5 Teratas
              </button>
              <button
                type="button"
                onClick={handleSelectAllJobs}
                className="rounded-xl border border-border-warm bg-white px-3 py-1.5 text-xs font-semibold text-text-secondary hover:bg-surface-canvas active:scale-95 transition"
              >
                Pilih Semua ({filteredJobs.length})
              </button>
              <button
                type="button"
                onClick={handleDeselectAllJobs}
                className="rounded-xl border border-border-warm bg-white px-3 py-1.5 text-xs font-semibold text-text-secondary hover:bg-surface-canvas active:scale-95 transition"
              >
                Kosongkan
              </button>
              <button
                type="button"
                onClick={() => setIsAddJobModalOpen(true)}
                className="inline-flex items-center gap-1.5 rounded-xl bg-brand px-3 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-brand-hover active:scale-95 transition"
              >
                <Plus className="size-3.5" /> Tambah Lowongan
              </button>
            </div>
          </div>

          {/* Job Postings Grid / List */}
          {isJobsLoading ? (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {[1, 2, 3, 4].map((i) => (
                <div key={i} className="h-32 rounded-2xl bg-white border border-border-warm p-4 animate-pulse space-y-2">
                  <div className="h-4 w-1/2 bg-gray-200 rounded" />
                  <div className="h-3 w-1/3 bg-gray-100 rounded" />
                  <div className="h-3 w-full bg-gray-100 rounded" />
                </div>
              ))}
            </div>
          ) : filteredJobs.length === 0 ? (
            <div className="flex flex-col items-center justify-center rounded-2xl border border-border-warm bg-white p-12 text-center">
              <Briefcase className="size-10 text-text-secondary/50" />
              <h3 className="mt-3 text-sm font-bold text-text-primary">
                {searchJobQuery ? 'Tidak ada lowongan yang cocok' : 'Belum ada lowongan untuk profesi ini'}
              </h3>
              <p className="mt-1 text-xs text-text-secondary max-w-sm">
                {searchJobQuery
                  ? `Tidak ditemukan lowongan dengan kata kunci "${searchJobQuery}". Coba kata kunci lain atau reset filter.`
                  : 'Tambahkan lowongan kerja manual untuk profesi ini atau pilih profesi lain di atas.'}
              </p>
              {searchJobQuery ? (
                <button
                  type="button"
                  onClick={() => setSearchJobQuery('')}
                  className="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-surface-canvas border border-border-warm px-4 py-2 text-xs font-bold text-text-primary hover:bg-white"
                >
                  Reset Filter Pencarian
                </button>
              ) : (
                <button
                  type="button"
                  onClick={() => setIsAddJobModalOpen(true)}
                  className="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white hover:bg-brand-hover"
                >
                  <Plus className="size-4" /> Tambah Lowongan Manual
                </button>
              )}
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {filteredJobs.map((job) => {
                const isSelected = selectedJobIds.includes(job.id)
                const isCompleted = job.extraction_status === 'completed'
                return (
                  <div
                    key={job.id}
                    onClick={() => handleToggleJob(job.id)}
                    className={`rounded-2xl border p-4 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md active:scale-[0.99] cursor-pointer relative ${
                      isSelected
                        ? 'border-brand bg-brand-soft/20 shadow-xs ring-1 ring-brand/30'
                        : 'border-border-warm bg-white hover:border-brand/40'
                    }`}
                  >
                    <div className="flex items-start gap-3">
                      <div className="pt-0.5 text-brand shrink-0">
                        {isSelected ? (
                          <CheckSquare className="size-5 fill-brand text-white" />
                        ) : (
                          <Square className="size-5 text-text-secondary/60" />
                        )}
                      </div>

                      <div className="flex-1 min-w-0 space-y-1.5">
                        <div className="flex items-start justify-between gap-2">
                          <h4 className="font-bold text-sm text-text-primary leading-tight">
                            {job.title}
                          </h4>

                          <div className="flex items-center gap-1.5 shrink-0 flex-wrap">
                            {job.is_synthetic || job.dataset_version?.startsWith('synthetic') ? (
                              <span
                                className="inline-flex items-center gap-1 rounded-md bg-amber-100 border border-amber-300 px-2 py-0.5 text-[10px] font-bold text-amber-900"
                                title="Data sintetis benchmark (JobStreet, Glints, LinkedIn)"
                              >
                                <AlertCircle className="size-2.5 text-amber-700" />
                                Sintetis ({job.source_name || 'Benchmark'})
                              </span>
                            ) : job.source_name ? (
                              <span className="inline-flex items-center gap-1 rounded-md bg-sky-50 border border-sky-200 px-2 py-0.5 text-[10px] font-bold text-sky-800">
                                <Check className="size-2.5 text-sky-600" />
                                {job.source_name}
                              </span>
                            ) : (
                              <span className="rounded-md bg-purple-50 border border-purple-200 px-2 py-0.5 text-[10px] font-bold text-purple-800">
                                Manual
                              </span>
                            )}
                            {isCompleted && (
                              <span className="inline-flex items-center gap-1 rounded-md bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800" title="Kualifikasi skill sudah terekstraksi oleh AI">
                                <CheckCircle2 className="size-2.5" />
                                {job.skills?.length || 0} skill
                              </span>
                            )}
                          </div>
                        </div>

                        <div className="text-xs font-medium text-text-secondary flex items-center justify-between gap-2">
                          <span className="flex items-center gap-1.5 truncate">
                            <Building2 className="size-3.5 text-text-secondary shrink-0" />
                            <strong className="text-text-primary font-semibold truncate">{job.company}</strong>
                          </span>

                          {job.source_url && (
                            <a
                              href={job.source_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              onClick={(e) => e.stopPropagation()}
                              className="inline-flex items-center gap-1 text-[11px] text-brand hover:underline font-semibold shrink-0"
                              title="Buka lowongan asli di web sumber"
                            >
                              <span>Buka Sumber</span>
                              <ExternalLink className="size-3" />
                            </a>
                          )}
                        </div>

                        {/* Skills Chips Preview */}
                        {job.skills && job.skills.length > 0 ? (
                          <div className="pt-1.5 flex flex-wrap items-center gap-1.5">
                            {job.skills.slice(0, 5).map((sk) => (
                              <span
                                key={sk.id}
                                className="rounded-md bg-surface-canvas border border-border-warm px-2 py-0.5 text-[10px] font-medium text-text-secondary"
                              >
                                {sk.skill}
                              </span>
                            ))}
                            {job.skills.length > 5 && (
                              <span className="text-[10px] text-text-secondary font-medium">
                                +{job.skills.length - 5} skill lainnya
                              </span>
                            )}
                          </div>
                        ) : (
                          <p className="text-[11px] text-text-secondary/70 italic pt-1">
                            {job.description ? job.description.slice(0, 90) + '...' : 'Belum ada rincian skill terekstraksi'}
                          </p>
                        )}

                        {(job.is_synthetic || job.dataset_version?.startsWith('synthetic')) && (
                          <p className="text-[10px] text-amber-800/90 font-medium pt-1">
                            * Lowongan sintetis benchmark dari agregat profil {job.source_name}
                          </p>
                        )}
                      </div>
                    </div>
                  </div>
                )
              })}
            </div>
          )}
        </div>

        {/* Disclaimer Footer Note */}
        <div className="flex items-start gap-2.5 rounded-xl border border-border-warm bg-surface-canvas p-4 text-xs text-text-secondary">
          <AlertTriangle className="size-4 shrink-0 text-brand" />
          <p>
            <strong className="font-semibold text-text-primary">Perhatian:</strong> {copy.disclaimer}{' '}
            Analisis ini membandingkan keselarasan semantik antara materi RPS dengan kualifikasi pada lowongan industri.
          </p>
        </div>
      </main>

      {/* BOTTOM STICKY ACTION BAR */}
      <aside
        aria-label="Bilah konfirmasi pemetaan industri"
        className="fixed bottom-0 inset-x-0 z-40 border-t border-border-warm bg-white/95 backdrop-blur-md px-4 py-3 md:px-8 shadow-lg transition-all"
      >
        <div className="mx-auto flex max-w-7xl flex-col sm:flex-row items-center justify-between gap-3">
          <div className="flex items-center gap-3 text-xs md:text-sm">
            <div className="flex size-10 items-center justify-center rounded-full bg-brand-soft text-brand shrink-0">
              <Sparkles className="size-5" />
            </div>
            <div>
              <p className="font-bold text-text-primary">
                Siap Menganalisis: {currentRpsDoc?.course?.name || currentRpsDoc?.original_name || 'RPS'}
              </p>
              <p className="text-xs text-text-secondary">
                Target: <strong>{selectedCareer?.name || 'Profesi'}</strong> • {selectedJobIds.length} lowongan terpilih
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2.5 w-full sm:w-auto justify-end">
            <Link
              to={`/rps/${selectedRpsId}`}
              className="rounded-xl border border-border-warm px-4 py-2.5 text-xs font-bold text-text-secondary hover:bg-surface-canvas transition active:scale-95"
            >
              Kembali ke Review RPS
            </Link>

            <button
              type="button"
              onClick={handleStartAnalysis}
              disabled={createAnalysis.isPending || selectedJobIds.length === 0}
              className="group w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-brand px-6 py-2.5 text-xs font-bold text-white shadow-md transition-all hover:bg-brand-hover hover:shadow-lg active:scale-95 disabled:opacity-50"
            >
              {createAnalysis.isPending ? (
                <>
                  <RefreshCw className="size-4 animate-spin" />
                  <span>Menjalankan Analisis...</span>
                </>
              ) : (
                <>
                  <span>Mulai Analisis Keselarasan</span>
                  <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
                </>
              )}
            </button>
          </div>
        </div>
      </aside>

      {/* MODAL: TAMBAH LOWONGAN MANUAL */}
      {isAddJobModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
          <div className="w-full max-w-lg rounded-2xl border border-border-warm bg-white p-6 shadow-xl animate-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between pb-3 border-b border-border-warm">
              <h3 className="font-bold text-base text-text-primary flex items-center gap-2">
                <Plus className="size-4 text-brand" /> Tambah Lowongan Pekerjaan Manual
              </h3>
              <button
                type="button"
                onClick={() => setIsAddJobModalOpen(false)}
                className="text-text-secondary hover:text-text-primary"
              >
                <X className="size-5" />
              </button>
            </div>

            {addJobError && (
              <div className="mt-3 rounded-xl bg-rose-50 border border-rose-200 p-3 text-xs text-rose-800 font-medium">
                {addJobError}
              </div>
            )}

            <div className="mt-4 space-y-4">
              <div>
                <label htmlFor={manualJobTitleId} className="block text-xs font-bold text-text-primary mb-1">
                  Posisi / Jabatan Pekerjaan <span className="text-danger">*</span>
                </label>
                <input
                  id={manualJobTitleId}
                  type="text"
                  placeholder="Contoh: Backend Engineer, Data Analyst"
                  value={jobTitle}
                  onChange={(e) => setJobTitle(e.target.value)}
                  className="w-full rounded-xl border border-border-warm px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                />
              </div>

              <div>
                <label htmlFor={manualJobCompanyId} className="block text-xs font-bold text-text-primary mb-1">
                  Nama Perusahaan <span className="text-danger">*</span>
                </label>
                <input
                  id={manualJobCompanyId}
                  type="text"
                  placeholder="Contoh: PT Teknologi Bangsa, GoTo"
                  value={jobCompany}
                  onChange={(e) => setJobCompany(e.target.value)}
                  className="w-full rounded-xl border border-border-warm px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                />
              </div>

              <div>
                <label htmlFor={manualJobUrlId} className="block text-xs font-bold text-text-primary mb-1">
                  Tautan Sumber / URL Lowongan (Opsional)
                </label>
                <input
                  id={manualJobUrlId}
                  type="url"
                  placeholder="https://example.com/job-vacancy"
                  value={jobSourceUrl}
                  onChange={(e) => setJobSourceUrl(e.target.value)}
                  className="w-full rounded-xl border border-border-warm px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                />
              </div>

              <div>
                <label htmlFor={manualJobDescId} className="block text-xs font-bold text-text-primary mb-1">
                  Deskripsi & Kualifikasi Skill Lowongan <span className="text-danger">*</span>
                </label>
                <textarea
                  id={manualJobDescId}
                  rows={4}
                  placeholder="Tempel teks kualifikasi lowongan di sini (contoh: Menguasai SQL, PostgreSQL, REST API, Git, dan Docker)..."
                  value={jobDescription}
                  onChange={(e) => setJobDescription(e.target.value)}
                  className="w-full rounded-xl border border-border-warm px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                />
              </div>
            </div>

            <div className="mt-6 flex items-center justify-end gap-2.5">
              <button
                type="button"
                onClick={() => setIsAddJobModalOpen(false)}
                className="rounded-xl border border-border-warm px-4 py-2 text-xs font-bold text-text-secondary hover:bg-surface-canvas"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={handleCreateJobSubmit}
                disabled={createJob.isPending || !jobTitle.trim() || !jobCompany.trim() || !jobDescription.trim()}
                className="rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-brand-hover disabled:opacity-50"
              >
                {createJob.isPending ? 'Menyimpan...' : 'Simpan & Pilih Lowongan'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
