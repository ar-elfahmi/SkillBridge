import { useState, useId } from 'react'
import { useParams, useNavigate, Link } from 'react-router-dom'
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  AlertCircle,
  Clock,
  Sparkles,
  ShieldCheck,
  ShieldAlert,
  Search,
  Plus,
  Pencil,
  Trash2,
  Check,
  X,
  Copy,
  FileText,
  BookOpen,
  Filter,
  RefreshCw,
  UserCheck,
  AlertTriangle,
} from 'lucide-react'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
import { AiExtractionLoader } from '@/components/common/AiExtractionLoader'
import {
  useRpsDetail,
  useRpsCompetencies,
  useUpdateRpsMetadata,
  useExtractRps,
  useAddCompetency,
  useUpdateCompetency,
  useDeleteCompetency,
  useConfirmRpsReview,
  useMe,
  useLogout,
} from '@/lib/query/hooks'
import { copy } from '@/lib/copy'
import type { CourseSkill, SkillCategory } from '@/types/api'

export function RpsDetailPage() {
  const { id } = useParams<{ id: string }>()
  const rpsId = Number(id)
  const navigate = useNavigate()

  const { data: user } = useMe()
  const logout = useLogout()

  const { data: rpsDoc, isLoading: isDocLoading, refetch: refetchDoc } = useRpsDetail(rpsId)
  const {
    data: competencies = [],
    isLoading: isCompetenciesLoading,
    refetch: refetchCompetencies,
  } = useRpsCompetencies(rpsId)

  // Mutations
  const updateMetadata = useUpdateRpsMetadata()
  const extractRps = useExtractRps()
  const addCompetency = useAddCompetency()
  const updateCompetency = useUpdateCompetency()
  const deleteCompetency = useDeleteCompetency()
  const confirmReview = useConfirmRpsReview()

  // State: Filter & Search
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState<'all' | 'pending' | 'confirmed' | 'rejected'>('all')
  const [categoryFilter, setCategoryFilter] = useState<string>('all')

  // State: Metadata Editor
  const [isEditingMetadata, setIsEditingMetadata] = useState(false)
  const [metaCode, setMetaCode] = useState('')
  const [metaName, setMetaName] = useState('')
  const [metaCredits, setMetaCredits] = useState<number | ''>('')
  const [metaSemester, setMetaSemester] = useState<number | ''>('')
  const [metaStudyProgram, setMetaStudyProgram] = useState('')
  const [metadataInitialized, setMetadataInitialized] = useState(false)

  // State: Add Manual Modal
  const [isAddModalOpen, setIsAddModalOpen] = useState(false)
  const [newSkillName, setNewSkillName] = useState('')
  const [newCompetencyText, setNewCompetencyText] = useState('')
  const [newCategory, setNewCategory] = useState<SkillCategory>('concept')
  const [newReviewStatus, setNewReviewStatus] = useState<'confirmed' | 'pending'>('confirmed')
  const [addError, setAddError] = useState<string | null>(null)

  // State: Edit Modal
  const [editingItem, setEditingItem] = useState<CourseSkill | null>(null)
  const [editSkillName, setEditSkillName] = useState('')
  const [editCompetencyText, setEditCompetencyText] = useState('')
  const [editError, setEditError] = useState<string | null>(null)

  // State: Micro-interactions
  const [copiedEvidenceId, setCopiedEvidenceId] = useState<number | null>(null)
  const [recentlyUpdatedId, setRecentlyUpdatedId] = useState<{ id: number; status: 'confirmed' | 'rejected' } | null>(null)

  function handleCopyEvidence(evidenceText: string, id: number) {
    if (!evidenceText) return
    navigator.clipboard.writeText(evidenceText)
    setCopiedEvidenceId(id)
    setTimeout(() => setCopiedEvidenceId(null), 1800)
  }

  // State: Action feedback
  const [notification, setNotification] = useState<{ type: 'success' | 'error'; message: string } | null>(null)

  // IDs untuk accessibility labels
  const filterSearchInputId = useId()
  const filterCategorySelectId = useId()
  const addSkillInputId = useId()
  const addCategorySelectId = useId()
  const addCompetencyDescId = useId()
  const editSkillInputId = useId()
  const editCompetencyDescId = useId()

  // Sync initial metadata when rpsDoc arrives
  if (rpsDoc && !metadataInitialized) {
    const meta = rpsDoc.detected_metadata
    const course = rpsDoc.course
    setMetaCode(course?.code ?? meta?.code ?? '')
    setMetaName(course?.name ?? meta?.name ?? rpsDoc.original_name.replace(/\.pdf$/i, ''))
    setMetaCredits(course?.credits ?? meta?.credits ?? '')
    setMetaSemester(course?.semester ?? meta?.semester ?? '')
    setMetaStudyProgram(course?.study_program ?? meta?.study_program ?? '')
    setMetadataInitialized(true)
  }

  function handleLogout() {
    logout.mutate(undefined, {
      onSuccess: () => navigate('/login'),
    })
  }

  function handleSaveMetadata() {
    if (!rpsId) return
    updateMetadata.mutate(
      {
        id: rpsId,
        data: {
          code: metaCode.trim(),
          name: metaName.trim(),
          credits: metaCredits === '' ? null : Number(metaCredits),
          semester: metaSemester === '' ? null : Number(metaSemester),
          study_program: metaStudyProgram.trim() || null,
        },
      },
      {
        onSuccess: () => {
          setIsEditingMetadata(false)
          setNotification({ type: 'success', message: 'Metadata mata kuliah berhasil diperbarui.' })
          setTimeout(() => setNotification(null), 3000)
        },
        onError: () => {
          setNotification({ type: 'error', message: 'Gagal memperbarui metadata.' })
        },
      }
    )
  }

  function handleTriggerExtraction() {
    if (!rpsId) return
    extractRps.mutate(
      { id: rpsId, code: metaCode.trim() || undefined },
      {
        onSuccess: () => {
          setNotification({
            type: 'success',
            message: 'Ekstraksi AI berhasil dimulai. Sistem sedang memproses dokumen...',
          })
          setTimeout(() => setNotification(null), 4000)
        },
        onError: (err: any) => {
          const msg = err?.body?.message || 'Gagal memulai ekstraksi AI. Pastikan kode mata kuliah terisi.'
          setNotification({ type: 'error', message: msg })
        },
      }
    )
  }

  function handleUpdateStatus(competencyId: number, newStatus: 'confirmed' | 'rejected') {
    if (!rpsId) return
    setRecentlyUpdatedId({ id: competencyId, status: newStatus })
    setTimeout(() => setRecentlyUpdatedId(null), 1400)
    updateCompetency.mutate(
      {
        rpsId,
        competencyId,
        payload: { review_status: newStatus },
      },
      {
        onError: () => {
          setNotification({ type: 'error', message: 'Gagal mengubah status kompetensi.' })
        },
      }
    )
  }

  function handleOpenEditModal(skill: CourseSkill) {
    setEditingItem(skill)
    setEditSkillName(skill.skill)
    setEditCompetencyText(skill.competency)
    setEditError(null)
  }

  function handleSaveEdit() {
    if (!editingItem || !rpsId) return
    if (!editSkillName.trim()) {
      setEditError('Nama skill wajib diisi.')
      return
    }
    if (!editCompetencyText.trim()) {
      setEditError('Deskripsi kompetensi wajib diisi.')
      return
    }

    updateCompetency.mutate(
      {
        rpsId,
        competencyId: editingItem.id,
        payload: {
          skill: editSkillName.trim(),
          competency: editCompetencyText.trim(),
        },
      },
      {
        onSuccess: () => {
          setEditingItem(null)
          setNotification({ type: 'success', message: 'Kompetensi berhasil diperbarui.' })
          setTimeout(() => setNotification(null), 3000)
        },
        onError: (err: any) => {
          setEditError(err?.body?.message || 'Gagal memperbarui kompetensi.')
        },
      }
    )
  }

  function handleDeleteManualCompetency(competencyId: number) {
    if (!rpsId) return
    if (!confirm('Apakah Anda yakin ingin menghapus kompetensi manual ini?')) return

    deleteCompetency.mutate(
      { rpsId, competencyId },
      {
        onSuccess: () => {
          setNotification({ type: 'success', message: 'Kompetensi berhasil dihapus.' })
          setTimeout(() => setNotification(null), 3000)
        },
        onError: () => {
          setNotification({ type: 'error', message: 'Gagal menghapus kompetensi.' })
        },
      }
    )
  }

  function handleCreateManualCompetency() {
    if (!rpsId) return
    if (!newSkillName.trim()) {
      setAddError('Nama skill wajib diisi.')
      return
    }
    if (!newCompetencyText.trim()) {
      setAddError('Deskripsi kompetensi wajib diisi.')
      return
    }

    addCompetency.mutate(
      {
        rpsId,
        payload: {
          skill: newSkillName.trim(),
          competency: newCompetencyText.trim(),
          category: newCategory,
          review_status: newReviewStatus,
        },
      },
      {
        onSuccess: () => {
          setIsAddModalOpen(false)
          setNewSkillName('')
          setNewCompetencyText('')
          setAddError(null)
          setNotification({ type: 'success', message: 'Kompetensi manual berhasil ditambahkan.' })
          setTimeout(() => setNotification(null), 3000)
        },
        onError: (err: any) => {
          setAddError(err?.body?.message || 'Gagal menambahkan kompetensi manual.')
        },
      }
    )
  }

  function handleConfirmAllAndProceed() {
    if (!rpsId) return
    confirmReview.mutate(
      { rpsId, confirmAllPending: true },
      {
        onSuccess: () => {
          setNotification({
            type: 'success',
            message: 'Semua kompetensi berhasil dikonfirmasi. Menuju ke Pemetaan Industri...',
          })
          setTimeout(() => {
            navigate(`/analyses/new?rps=${rpsId}`)
          }, 800)
        },
        onError: (err: any) => {
          setNotification({
            type: 'error',
            message: err?.body?.message || 'Gagal mengonfirmasi review ekstraksi.',
          })
        },
      }
    )
  }

  // Filter items
  const filteredCompetencies = competencies.filter((c) => {
    // Status Filter
    if (statusFilter !== 'all' && c.review_status !== statusFilter) {
      return false
    }
    // Category Filter
    if (categoryFilter !== 'all' && c.category !== categoryFilter) {
      return false
    }
    // Search Query
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase()
      const matchSkill = c.skill.toLowerCase().includes(q)
      const matchComp = c.competency.toLowerCase().includes(q)
      const matchEvid = (c.evidence || '').toLowerCase().includes(q)
      const matchRef = (c.source_reference || '').toLowerCase().includes(q)
      if (!matchSkill && !matchComp && !matchEvid && !matchRef) {
        return false
      }
    }
    return true
  })

  // Agregasi counts
  const totalCount = competencies.length
  const pendingCount = competencies.filter((c) => c.review_status === 'pending').length
  const confirmedCount = competencies.filter((c) => c.review_status === 'confirmed').length
  const rejectedCount = competencies.filter((c) => c.review_status === 'rejected').length
  const verifiedCount = competencies.filter((c) => c.verification_status === 'verified').length

  const isExtractionProcessing = rpsDoc?.extraction_status === 'processing'
  const isExtractionCompleted = rpsDoc?.extraction_status === 'completed'
  const isExtractionFailed = rpsDoc?.extraction_status === 'failed'
  const isConfirmed = rpsDoc?.review_status === 'confirmed'

  if (isDocLoading) {
    return (
      <div className="flex min-h-svh flex-col bg-background">
        <DashboardTopbar user={user} onLogout={handleLogout} />
        <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-8 md:py-8">
          <div className="h-5 w-48 rounded bg-gray-200 animate-pulse" />

          {/* Skeleton Hero Card */}
          <div className="relative overflow-hidden rounded-2xl border border-border-warm bg-white p-6 shadow-xs space-y-4">
            <div className="animate-shimmer pointer-events-none absolute inset-0 z-10" />
            <div className="flex items-center gap-2">
              <div className="h-5 w-24 rounded-full bg-gray-200 animate-pulse" />
              <div className="h-5 w-20 rounded-full bg-gray-100 animate-pulse" />
            </div>
            <div className="h-8 w-2/3 rounded-lg bg-gray-200 animate-pulse" />
            <div className="h-4 w-1/3 rounded bg-gray-100 animate-pulse" />

            <div className="pt-4 border-t border-border-warm/60 grid grid-cols-2 md:grid-cols-4 gap-3">
              {[1, 2, 3, 4].map((i) => (
                <div key={i} className="flex items-center gap-2">
                  <div className="size-7 rounded-full bg-gray-200 animate-pulse shrink-0" />
                  <div className="space-y-1 w-full">
                    <div className="h-3 w-16 rounded bg-gray-200 animate-pulse" />
                    <div className="h-2.5 w-24 rounded bg-gray-100 animate-pulse" />
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="py-8 flex flex-col items-center justify-center gap-3 text-text-secondary">
            <RefreshCw className="size-6 animate-spin text-brand" />
            <p className="text-xs font-semibold text-text-secondary">Menyiapkan data dokumen RPS...</p>
          </div>
        </main>
      </div>
    )
  }

  if (!rpsDoc) {
    return (
      <div className="flex min-h-svh flex-col bg-background">
        <DashboardTopbar user={user} onLogout={handleLogout} />
        <main className="mx-auto flex w-full max-w-7xl flex-1 flex-col items-center justify-center gap-4 p-8">
          <AlertCircle className="size-12 text-danger" />
          <h1 className="text-xl font-bold text-text-primary">Dokumen RPS tidak ditemukan</h1>
          <p className="text-sm text-text-secondary">Dokumen yang Anda cari tidak tersedia atau telah dihapus.</p>
          <Link
            to="/dashboard"
            className="mt-2 inline-flex items-center gap-2 rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-hover"
          >
            <ArrowLeft className="size-4" /> Kembali ke Dashboard
          </Link>
        </main>
      </div>
    )
  }

  return (
    <div className="flex min-h-svh flex-col bg-background pb-28">
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
        {/* Navigation Breadcrumb & Back */}
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-2 text-xs md:text-sm text-text-secondary">
            <Link to="/dashboard" className="transition hover:text-brand flex items-center gap-1 font-medium">
              <ArrowLeft className="size-3.5" /> Dashboard
            </Link>
            <span>/</span>
            <span className="font-medium text-text-primary">Detail RPS #{rpsDoc.id}</span>
            <span>/</span>
            <span className="text-brand font-semibold">Tinjauan Ekstraksi</span>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={() => {
                refetchDoc()
                refetchCompetencies()
              }}
              title="Perbarui Data"
              className="inline-flex items-center gap-1.5 rounded-lg border border-border-warm bg-white px-3 py-1.5 text-xs font-semibold text-text-secondary transition hover:bg-surface-canvas hover:text-text-primary"
            >
              <RefreshCw className="size-3.5" /> Perbarui
            </button>
            <Link
              to={`/analyses/new?rps=${rpsDoc.id}`}
              className="inline-flex items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-hover"
            >
              Pemetaan Industri <ArrowRight className="size-3.5" />
            </Link>
          </div>
        </div>

        {/* Header Hero Card */}
        <div className="rounded-2xl border border-border-warm bg-white p-6 shadow-xs">
          <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div className="space-y-1.5">
              <div className="flex flex-wrap items-center gap-2">
                <span className="rounded-full bg-brand-soft px-2.5 py-0.5 text-xs font-bold text-brand uppercase tracking-wider">
                  {rpsDoc.course?.code || rpsDoc.detected_metadata?.code || 'RPS DOKUMEN'}
                </span>
                <span className="rounded-full bg-surface-canvas border border-border-warm px-2.5 py-0.5 text-xs font-medium text-text-secondary">
                  {rpsDoc.page_count} Halaman
                </span>
                {isConfirmed ? (
                  <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-800 px-2.5 py-0.5 text-xs font-semibold">
                    <CheckCircle2 className="size-3.5" /> Telah Dikonfirmasi
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 text-amber-800 px-2.5 py-0.5 text-xs font-semibold">
                    <Clock className="size-3.5" /> Menunggu Tinjauan
                  </span>
                )}
              </div>
              <h1 className="font-serif text-2xl font-bold tracking-tight text-text-primary md:text-3xl">
                {rpsDoc.course?.name || rpsDoc.detected_metadata?.name || rpsDoc.original_name}
              </h1>
              <p className="text-xs md:text-sm text-text-secondary flex items-center gap-2">
                <FileText className="size-3.5 text-text-secondary" />
                <span>Berkas asli: <strong className="font-medium text-text-primary">{rpsDoc.original_name}</strong></span>
                <span>•</span>
                <span>Diunggah pada {new Date(rpsDoc.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</span>
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={() => setIsEditingMetadata(!isEditingMetadata)}
                className="inline-flex items-center gap-1.5 rounded-xl border border-border-warm bg-white px-3.5 py-2 text-xs font-bold text-text-primary shadow-xs transition hover:bg-surface-canvas"
              >
                <Pencil className="size-3.5 text-text-secondary" />
                {isEditingMetadata ? 'Tutup Editor Metadata' : 'Edit Metadata Mata Kuliah'}
              </button>
            </div>
          </div>

          {/* Stepper Pipeline Status */}
          <div className="mt-6 pt-5 border-t border-border-warm grid grid-cols-2 md:grid-cols-4 gap-3">
            <div className="flex items-center gap-2.5">
              <div
                className={`flex size-7 items-center justify-center rounded-full font-bold text-xs shrink-0 ${
                  rpsDoc.parse_status === 'parsed'
                    ? 'bg-emerald-500 text-white'
                    : rpsDoc.parse_status === 'parsing' || rpsDoc.parse_status === 'pending'
                    ? 'bg-amber-500 text-white animate-pulse'
                    : 'bg-rose-500 text-white'
                }`}
              >
                {rpsDoc.parse_status === 'parsed' ? '✓' : rpsDoc.parse_status === 'failed' || rpsDoc.parse_status === 'needs_ocr' ? '✕' : '1'}
              </div>
              <div>
                <p className="text-[11px] font-bold text-text-primary">1. Parsing Berkas</p>
                <p
                  className={`text-[10px] font-medium ${
                    rpsDoc.parse_status === 'parsed'
                      ? 'text-emerald-600'
                      : rpsDoc.parse_status === 'parsing' || rpsDoc.parse_status === 'pending'
                      ? 'text-amber-600'
                      : 'text-rose-600'
                  }`}
                >
                  {rpsDoc.parse_status === 'parsed'
                    ? 'Selesai diparsing'
                    : rpsDoc.parse_status === 'parsing'
                    ? 'Sedang membaca...'
                    : rpsDoc.parse_status === 'pending'
                    ? 'Dalam antrean...'
                    : rpsDoc.parse_status === 'needs_ocr'
                    ? 'Perlu OCR (Scan)'
                    : 'Gagal diparsing'}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2.5">
              <div className={`flex size-7 items-center justify-center rounded-full shrink-0 font-bold text-xs ${
                rpsDoc.course || rpsDoc.detected_metadata ? 'bg-emerald-500 text-white' : 'bg-surface-subtle text-text-secondary'
              }`}>
                {rpsDoc.course || rpsDoc.detected_metadata ? '✓' : '2'}
              </div>
              <div>
                <p className="text-[11px] font-bold text-text-primary">2. Validasi Metadata</p>
                <p className="text-[10px] text-text-secondary font-medium">
                  {rpsDoc.course?.code ? `Kode: ${rpsDoc.course.code}` : 'Periksa metadata'}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2.5">
              <div className={`flex size-7 items-center justify-center rounded-full shrink-0 font-bold text-xs ${
                isExtractionCompleted
                  ? 'bg-emerald-500 text-white'
                  : isExtractionProcessing
                  ? 'bg-brand text-white animate-pulse'
                  : 'bg-surface-subtle text-text-secondary'
              }`}>
                {isExtractionCompleted ? '✓' : isExtractionProcessing ? '⏳' : '3'}
              </div>
              <div>
                <p className="text-[11px] font-bold text-text-primary">3. Ekstraksi AI</p>
                <p className={`text-[10px] font-medium ${
                  isExtractionCompleted
                    ? 'text-emerald-600'
                    : isExtractionProcessing
                    ? 'text-brand font-semibold'
                    : isExtractionFailed
                    ? 'text-danger'
                    : 'text-text-secondary'
                }`}>
                  {isExtractionCompleted
                    ? `${competencies.length} kompetensi`
                    : isExtractionProcessing
                    ? 'Sedang Memproses...'
                    : isExtractionFailed
                    ? 'Gagal'
                    : 'Siap Diekstrak'}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2.5">
              <div className={`flex size-7 items-center justify-center rounded-full shrink-0 font-bold text-xs ${
                isConfirmed ? 'bg-emerald-500 text-white' : 'bg-surface-subtle text-text-secondary'
              }`}>
                {isConfirmed ? '✓' : '4'}
              </div>
              <div>
                <p className="text-[11px] font-bold text-text-primary">4. Tinjauan & Konfirmasi</p>
                <p className={`text-[10px] font-medium ${isConfirmed ? 'text-emerald-600' : 'text-amber-600'}`}>
                  {isConfirmed ? 'Dikonfirmasi' : `${pendingCount} perlu ditinjau`}
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Phase 0: Metadata Editor Drawer / Section */}
        {isEditingMetadata && (
          <div className="rounded-2xl border border-brand/20 bg-brand-soft/20 p-6 shadow-xs animate-in fade-in duration-200">
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <BookOpen className="size-5 text-brand" />
                <h2 className="text-base font-bold text-text-primary">Editor Metadata Mata Kuliah (Tahap 0)</h2>
              </div>
              <span className="text-xs text-text-secondary">Simpan untuk memperbarui informasi dasar mata kuliah</span>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label htmlFor="meta-course-code" className="block text-xs font-bold text-text-primary mb-1">
                  Kode Mata Kuliah <span className="text-danger">*</span>
                </label>
                <input
                  id="meta-course-code"
                  type="text"
                  value={metaCode}
                  onChange={(e) => setMetaCode(e.target.value)}
                  placeholder="Contoh: CS301, EK1204"
                  className="w-full rounded-xl border border-border-warm bg-white px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                />
              </div>

              <div className="md:col-span-2">
                <label htmlFor="meta-course-name" className="block text-xs font-bold text-text-primary mb-1">
                  Nama Mata Kuliah <span className="text-danger">*</span>
                </label>
                <input
                  id="meta-course-name"
                  type="text"
                  value={metaName}
                  onChange={(e) => setMetaName(e.target.value)}
                  placeholder="Contoh: Pemrograman Basis Data"
                  className="w-full rounded-xl border border-border-warm bg-white px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                />
              </div>

              <div>
                <label htmlFor="meta-course-credits" className="block text-xs font-bold text-text-primary mb-1">Bobot SKS</label>
                <input
                  id="meta-course-credits"
                  type="number"
                  min="1"
                  max="12"
                  value={metaCredits}
                  onChange={(e) => setMetaCredits(e.target.value === '' ? '' : Number(e.target.value))}
                  placeholder="3"
                  className="w-full rounded-xl border border-border-warm bg-white px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                />
              </div>

              <div>
                <label htmlFor="meta-course-semester" className="block text-xs font-bold text-text-primary mb-1">Semester</label>
                <input
                  id="meta-course-semester"
                  type="number"
                  min="1"
                  max="14"
                  value={metaSemester}
                  onChange={(e) => setMetaSemester(e.target.value === '' ? '' : Number(e.target.value))}
                  placeholder="5"
                  className="w-full rounded-xl border border-border-warm bg-white px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                />
              </div>

              <div>
                <label htmlFor="meta-course-study-program" className="block text-xs font-bold text-text-primary mb-1">Program Studi</label>
                <input
                  id="meta-course-study-program"
                  type="text"
                  value={metaStudyProgram}
                  onChange={(e) => setMetaStudyProgram(e.target.value)}
                  placeholder="Contoh: Sistem Informasi"
                  className="w-full rounded-xl border border-border-warm bg-white px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                />
              </div>
            </div>

            <div className="mt-5 flex items-center justify-end gap-3">
              <button
                type="button"
                onClick={() => setIsEditingMetadata(false)}
                className="rounded-xl border border-border-warm bg-white px-4 py-2 text-xs font-bold text-text-secondary transition hover:bg-surface-canvas"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={handleSaveMetadata}
                disabled={updateMetadata.isPending || !metaCode.trim()}
                className="rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-brand-hover disabled:opacity-50"
              >
                {updateMetadata.isPending ? 'Menyimpan...' : 'Simpan Perubahan Metadata'}
              </button>
            </div>
          </div>
        )}

        {/* Phase 1: AI Extraction Processing Loader */}
        {(isExtractionProcessing || extractRps.isPending) && (
          <AiExtractionLoader
            pageCount={rpsDoc.page_count}
            fileName={rpsDoc.original_name}
          />
        )}

        {/* Phase 1: AI Extraction Trigger CTA (if not completed or failed) */}
        {!isExtractionCompleted && !isExtractionProcessing && !extractRps.isPending && (
          <div className="flex flex-col md:flex-row items-center justify-between gap-4 rounded-2xl border border-border-warm bg-surface-canvas p-6">
            <div className="space-y-1">
              <div className="flex items-center gap-2">
                <Sparkles className="size-4 text-brand" />
                <h2 className="text-sm font-bold text-text-primary">
                  {rpsDoc.parse_status !== 'parsed'
                    ? rpsDoc.parse_status === 'needs_ocr'
                      ? 'PDF Tampak Hasil Scan Gambar (Perlu OCR)'
                      : 'Berkas Sedang Menunggu Antrean Parsing'
                    : isExtractionFailed
                    ? 'Ekstraksi Sebelumnya Gagal Dieksekusi'
                    : 'Ekstraksi AI Belum Dijalankan'}
                </h2>
              </div>
              <p className="text-xs text-text-secondary">
                {rpsDoc.parse_status === 'needs_ocr'
                  ? 'Dokumen tidak memiliki layer teks digital yang dapat dibaca. Silakan unggah PDF digital yang teksnya bisa diseleksi kursor.'
                  : rpsDoc.parse_status !== 'parsed'
                  ? 'Sistem sedang membaca isi dokumen di latar belakang. Tombol ekstraksi akan aktif otomatis setelah parsing selesai.'
                  : 'Jalankan ekstraksi otomatis dengan model Gemini 2.5 Flash untuk memindai seluruh CPMK dan bukti kutipan teks RPS.'}
              </p>
              {rpsDoc.error_message && (
                <p className="text-xs font-semibold text-danger">Detail: {rpsDoc.error_message}</p>
              )}
            </div>

            <button
              type="button"
              onClick={handleTriggerExtraction}
              disabled={extractRps.isPending || rpsDoc.parse_status !== 'parsed'}
              className="inline-flex items-center gap-2 rounded-xl bg-brand px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-brand-hover disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {rpsDoc.parse_status !== 'parsed' ? (
                <>
                  <RefreshCw className="size-4 animate-spin" />
                  <span>Menunggu Parsing...</span>
                </>
              ) : (
                <>
                  <Sparkles className="size-4" />
                  <span>{extractRps.isPending ? 'Memulai Ekstraksi...' : 'Mulai Ekstraksi Kompetensi AI'}</span>
                </>
              )}
            </button>
          </div>
        )}

        {/* Phase 2: Competency Review Dashboard (T3.5 & T3.6) */}
        {isExtractionCompleted && (
          <div className="space-y-6">
            {/* Metric Summary Cards with Click-to-Filter Micro-Interactions */}
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-5 md:gap-4">
              <button
                type="button"
                onClick={() => setStatusFilter('all')}
                className={`rounded-xl border p-4 text-left transition-all duration-200 hover:-translate-y-1 hover:shadow-md active:scale-95 cursor-pointer ${
                  statusFilter === 'all'
                    ? 'border-brand bg-brand-soft/20 shadow-xs ring-1 ring-brand/30'
                    : 'border-border-warm bg-white hover:border-brand/40'
                }`}
              >
                <p className="text-[11px] font-bold text-text-secondary uppercase tracking-wider">Total Kompetensi</p>
                <p className="mt-1 font-serif text-2xl font-bold text-text-primary">{totalCount}</p>
                <span className="text-[10px] text-text-secondary">Semua ({totalCount})</span>
              </button>

              <button
                type="button"
                onClick={() => setStatusFilter('pending')}
                className={`rounded-xl border p-4 text-left transition-all duration-200 hover:-translate-y-1 hover:shadow-md active:scale-95 cursor-pointer ${
                  statusFilter === 'pending'
                    ? 'border-amber-500 bg-amber-100/60 shadow-xs ring-1 ring-amber-400'
                    : 'border-amber-200 bg-amber-50/50 hover:border-amber-400'
                }`}
              >
                <p className="text-[11px] font-bold text-amber-800 uppercase tracking-wider flex items-center justify-between">
                  <span>Perlu Ditinjau</span>
                  {pendingCount > 0 && <span className="size-2 rounded-full bg-amber-500 animate-ping" />}
                </p>
                <p className="mt-1 font-serif text-2xl font-bold text-amber-700">{pendingCount}</p>
                <span className="text-[10px] text-amber-600 font-medium">Klik untuk filter</span>
              </button>

              <button
                type="button"
                onClick={() => setStatusFilter('confirmed')}
                className={`rounded-xl border p-4 text-left transition-all duration-200 hover:-translate-y-1 hover:shadow-md active:scale-95 cursor-pointer ${
                  statusFilter === 'confirmed'
                    ? 'border-emerald-500 bg-emerald-100/60 shadow-xs ring-1 ring-emerald-400'
                    : 'border-emerald-200 bg-emerald-50/50 hover:border-emerald-400'
                }`}
              >
                <p className="text-[11px] font-bold text-emerald-800 uppercase tracking-wider">Disetujui</p>
                <p className="mt-1 font-serif text-2xl font-bold text-emerald-700">{confirmedCount}</p>
                <span className="text-[10px] text-emerald-600 font-medium">Klik untuk filter</span>
              </button>

              <button
                type="button"
                onClick={() => setStatusFilter('rejected')}
                className={`rounded-xl border p-4 text-left transition-all duration-200 hover:-translate-y-1 hover:shadow-md active:scale-95 cursor-pointer ${
                  statusFilter === 'rejected'
                    ? 'border-rose-500 bg-rose-100/60 shadow-xs ring-1 ring-rose-400'
                    : 'border-rose-200 bg-rose-50/50 hover:border-rose-400'
                }`}
              >
                <p className="text-[11px] font-bold text-rose-800 uppercase tracking-wider">Ditolak</p>
                <p className="mt-1 font-serif text-2xl font-bold text-rose-700">{rejectedCount}</p>
                <span className="text-[10px] text-rose-600 font-medium">Klik untuk filter</span>
              </button>

              <div className="col-span-2 sm:col-span-1 rounded-xl border border-border-warm bg-surface-canvas p-4 shadow-xs transition-all duration-200 hover:-translate-y-1 hover:shadow-md">
                <p className="text-[11px] font-bold text-text-secondary uppercase tracking-wider flex items-center gap-1">
                  <ShieldCheck className="size-3 text-emerald-600" /> Terverifikasi
                </p>
                <p className="mt-1 font-serif text-2xl font-bold text-text-primary">{verifiedCount}</p>
                <span className="text-[10px] text-text-secondary">Kutipan teks verbatim</span>
              </div>
            </div>

            {/* Filter & Action Bar */}
            <div className="flex flex-col gap-3 rounded-2xl border border-border-warm bg-white p-4 shadow-xs md:flex-row md:items-center md:justify-between">
              {/* Status Filter Tabs */}
              <div className="flex flex-wrap items-center gap-1.5 bg-surface-canvas p-1 rounded-xl">
                <button
                  type="button"
                  onClick={() => setStatusFilter('all')}
                  className={`rounded-lg px-3 py-1.5 text-xs font-bold transition ${
                    statusFilter === 'all'
                      ? 'bg-white text-text-primary shadow-xs'
                      : 'text-text-secondary hover:text-text-primary'
                  }`}
                >
                  Semua ({totalCount})
                </button>
                <button
                  type="button"
                  onClick={() => setStatusFilter('pending')}
                  className={`rounded-lg px-3 py-1.5 text-xs font-bold transition ${
                    statusFilter === 'pending'
                      ? 'bg-amber-500 text-white shadow-xs'
                      : 'text-text-secondary hover:text-amber-700'
                  }`}
                >
                  Perlu Ditinjau ({pendingCount})
                </button>
                <button
                  type="button"
                  onClick={() => setStatusFilter('confirmed')}
                  className={`rounded-lg px-3 py-1.5 text-xs font-bold transition ${
                    statusFilter === 'confirmed'
                      ? 'bg-emerald-600 text-white shadow-xs'
                      : 'text-text-secondary hover:text-emerald-700'
                  }`}
                >
                  Disetujui ({confirmedCount})
                </button>
                <button
                  type="button"
                  onClick={() => setStatusFilter('rejected')}
                  className={`rounded-lg px-3 py-1.5 text-xs font-bold transition ${
                    statusFilter === 'rejected'
                      ? 'bg-rose-600 text-white shadow-xs'
                      : 'text-text-secondary hover:text-rose-700'
                  }`}
                >
                  Ditolak ({rejectedCount})
                </button>
              </div>

              {/* Search & Category Filter & Add Manual */}
              <div className="flex flex-wrap items-center gap-2.5">
                {/* Search */}
                <div className="relative flex-1 md:w-56">
                  <label htmlFor={filterSearchInputId} className="sr-only">Cari skill atau bukti...</label>
                  <Search className="absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-text-secondary" />
                  <input
                    id={filterSearchInputId}
                    type="text"
                    placeholder="Cari skill atau bukti..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="w-full rounded-xl border border-border-warm bg-surface-canvas pl-8 pr-7 py-1.5 text-xs text-text-primary outline-none focus:border-brand transition-colors"
                  />
                  {searchQuery && (
                    <button
                      type="button"
                      onClick={() => setSearchQuery('')}
                      className="absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-0.5 text-text-secondary hover:bg-surface-subtle hover:text-text-primary transition-all active:scale-90"
                      title="Hapus pencarian"
                    >
                      <X className="size-3" />
                    </button>
                  )}
                </div>

                {/* Category select */}
                <div className="flex items-center gap-1.5">
                  <label htmlFor={filterCategorySelectId} className="sr-only">Filter kategori kompetensi</label>
                  <Filter className="size-3.5 text-text-secondary" />
                  <select
                    id={filterCategorySelectId}
                    value={categoryFilter}
                    onChange={(e) => setCategoryFilter(e.target.value)}
                    className="rounded-xl border border-border-warm bg-surface-canvas px-2.5 py-1.5 text-xs font-semibold text-text-primary outline-none focus:border-brand transition-colors cursor-pointer"
                  >
                    <option value="all">Semua Kategori</option>
                    <option value="language">Language</option>
                    <option value="framework">Framework</option>
                    <option value="tool">Tool</option>
                    <option value="concept">Concept</option>
                    <option value="practice">Practice</option>
                    <option value="other">Other</option>
                  </select>
                </div>

                {/* Add Manual Button */}
                <button
                  type="button"
                  onClick={() => setIsAddModalOpen(true)}
                  className="inline-flex items-center gap-1.5 rounded-xl bg-brand px-3.5 py-1.5 text-xs font-bold text-white shadow-xs transition-all hover:bg-brand-hover active:scale-95"
                >
                  <Plus className="size-3.5" /> Tambah Manual
                </button>
              </div>
            </div>

            {/* Competency Review Table / Cards List */}
            {isCompetenciesLoading ? (
              <div className="flex flex-col items-center justify-center rounded-2xl border border-border-warm bg-white p-12 text-center">
                <RefreshCw className="size-8 animate-spin text-brand" />
                <p className="mt-3 text-sm font-bold text-text-primary">Memuat daftar kompetensi...</p>
                <p className="mt-1 text-xs text-text-secondary">Mengambil data hasil ekstraksi...</p>
              </div>
            ) : filteredCompetencies.length === 0 ? (
              <div className="flex flex-col items-center justify-center rounded-2xl border border-border-warm bg-white p-12 text-center">
                <BookOpen className="size-10 text-text-secondary/50" />
                <h3 className="mt-3 text-sm font-bold text-text-primary">Tidak ada kompetensi yang sesuai filter</h3>
                <p className="mt-1 text-xs text-text-secondary">
                  Coba ubah kata kunci pencarian atau ubah tab status filter di atas.
                </p>
                <button
                  type="button"
                  onClick={() => {
                    setSearchQuery('')
                    setStatusFilter('all')
                    setCategoryFilter('all')
                  }}
                  className="mt-4 rounded-xl border border-border-warm bg-surface-canvas px-3.5 py-1.5 text-xs font-bold text-text-primary hover:bg-surface-subtle"
                >
                  Reset Filter
                </button>
              </div>
            ) : (
              <div className="space-y-3">
                {filteredCompetencies.map((item) => (
                  <div
                    key={item.id}
                    className={`rounded-2xl border bg-white p-5 transition-all duration-200 shadow-xs hover:-translate-y-0.5 hover:shadow-md ${
                      recentlyUpdatedId?.id === item.id
                        ? recentlyUpdatedId.status === 'confirmed'
                          ? 'ring-2 ring-emerald-500 bg-emerald-50/30 scale-[1.01]'
                          : 'ring-2 ring-rose-500 bg-rose-50/30 scale-[1.01]'
                        : item.review_status === 'confirmed'
                        ? 'border-emerald-200/80 bg-emerald-50/10'
                        : item.review_status === 'rejected'
                        ? 'border-rose-200/80 bg-rose-50/10 opacity-75'
                        : 'border-border-warm hover:border-brand/40'
                    }`}
                  >
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                      {/* Left: Info, Skill, Badges */}
                      <div className="flex-1 space-y-2.5">
                        <div className="flex flex-wrap items-center gap-2">
                          {/* Skill Name */}
                          <span className="font-bold text-sm md:text-base text-text-primary font-sans">
                            {item.skill}
                          </span>

                          {/* Category Badge */}
                          {item.category && (
                            <span className="rounded-md bg-brand-soft px-2 py-0.5 text-[11px] font-semibold text-brand">
                              {item.category}
                            </span>
                          )}

                          {/* Source Reference & Type */}
                          {item.source_reference && (
                            <span className="rounded-md bg-surface-canvas border border-border-warm px-2 py-0.5 text-[11px] font-medium text-text-secondary">
                              {item.source_type}: {item.source_reference}
                            </span>
                          )}

                          {/* Page */}
                          {item.page && (
                            <span className="rounded-md bg-surface-canvas border border-border-warm px-2 py-0.5 text-[11px] font-medium text-text-secondary">
                              Hal. {item.page}
                            </span>
                          )}

                          {/* Verification Badge */}
                          {item.verification_status === 'verified' ? (
                            <span className="inline-flex items-center gap-1 rounded-md bg-emerald-100 text-emerald-800 px-2 py-0.5 text-[11px] font-bold">
                              <ShieldCheck className="size-3 text-emerald-600" /> Terverifikasi RPS
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 rounded-md bg-amber-100 text-amber-800 px-2 py-0.5 text-[11px] font-semibold">
                              <ShieldAlert className="size-3 text-amber-600" /> Belum Terverifikasi
                            </span>
                          )}

                          {/* Origin Badge */}
                          {item.origin === 'ai' ? (
                            <span className="inline-flex items-center gap-1 rounded-md bg-purple-100 text-purple-800 px-2 py-0.5 text-[10px] font-bold">
                              <Sparkles className="size-2.5 text-purple-600" /> AI
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 rounded-md bg-blue-100 text-blue-800 px-2 py-0.5 text-[10px] font-bold">
                              <UserCheck className="size-2.5 text-blue-600" /> Manual
                            </span>
                          )}

                          {/* User Edited Marker */}
                          {item.user_edited && (
                            <span className="rounded-md bg-gray-100 text-gray-700 px-1.5 py-0.5 text-[10px] font-medium italic">
                              diedit
                            </span>
                          )}
                        </div>

                        {/* Competency Description */}
                        <p className="text-xs md:text-sm text-text-primary leading-relaxed">
                          {item.competency}
                        </p>

                        {/* Evidence Verbatim Quote with Copy Micro-Interaction */}
                        {item.evidence && (
                          <div className="rounded-xl border border-border-warm/80 bg-surface-canvas/80 p-3 text-xs text-text-secondary group/quote transition-colors">
                            <div className="flex items-center justify-between mb-1.5">
                              <span className="font-bold text-text-primary text-[11px] flex items-center gap-1.5">
                                <FileText className="size-3 text-brand" /> Bukti Kutipan RPS (Verbatim):
                              </span>
                              <button
                                type="button"
                                onClick={() => handleCopyEvidence(item.evidence!, item.id)}
                                className="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-semibold text-text-secondary hover:bg-white hover:text-text-primary hover:shadow-xs transition-all active:scale-95 border border-transparent hover:border-border-warm cursor-pointer"
                                title="Salin kutipan teks asli"
                              >
                                {copiedEvidenceId === item.id ? (
                                  <>
                                    <Check className="size-3 text-emerald-600 animate-in zoom-in-75" />
                                    <span className="text-emerald-700 font-bold">Tersalin!</span>
                                  </>
                                ) : (
                                  <>
                                    <Copy className="size-3 text-text-secondary group-hover/quote:text-brand" />
                                    <span>Salin</span>
                                  </>
                                )}
                              </button>
                            </div>
                            <blockquote className="italic border-l-2 border-brand/60 pl-2.5 text-text-secondary leading-snug">
                              &ldquo;{item.evidence}&rdquo;
                            </blockquote>
                          </div>
                        )}
                      </div>

                      {/* Right: Actions & Status */}
                      <div className="flex flex-row md:flex-col items-center md:items-end justify-between gap-2.5 shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-border-warm">
                        {/* Current Status Pill */}
                        <div className="mb-1">
                          {item.review_status === 'confirmed' ? (
                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">
                              <CheckCircle2 className="size-3.5" /> Disetujui
                            </span>
                          ) : item.review_status === 'rejected' ? (
                            <span className="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-800">
                              <X className="size-3.5" /> Ditolak
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">
                              <Clock className="size-3.5" /> Perlu Ditinjau
                            </span>
                          )}
                        </div>

                        {/* Review Action Buttons */}
                        <div className="flex items-center gap-1.5">
                          {item.review_status !== 'confirmed' && (
                            <button
                              type="button"
                              onClick={() => handleUpdateStatus(item.id, 'confirmed')}
                              title="Setujui Kompetensi Ini"
                              disabled={updateCompetency.isPending}
                              className="inline-flex items-center gap-1 rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1.5 text-xs font-bold text-emerald-700 transition-all hover:bg-emerald-600 hover:text-white hover:scale-105 active:scale-95 shadow-xs"
                            >
                              <Check className="size-3.5" /> Setujui
                            </button>
                          )}

                          {item.review_status !== 'rejected' && (
                            <button
                              type="button"
                              onClick={() => handleUpdateStatus(item.id, 'rejected')}
                              title="Tolak Kompetensi Ini"
                              disabled={updateCompetency.isPending}
                              className="inline-flex items-center gap-1 rounded-lg border border-rose-300 bg-rose-50 px-2.5 py-1.5 text-xs font-bold text-rose-700 transition-all hover:bg-rose-600 hover:text-white hover:scale-105 active:scale-95 shadow-xs"
                            >
                              <X className="size-3.5" /> Tolak
                            </button>
                          )}

                          {/* Edit Button */}
                          <button
                            type="button"
                            onClick={() => handleOpenEditModal(item)}
                            title="Edit Teks / Skill"
                            className="flex size-7 items-center justify-center rounded-lg border border-border-warm bg-white text-text-secondary transition-all hover:bg-surface-canvas hover:text-text-primary hover:scale-105 active:scale-95 shadow-xs"
                          >
                            <Pencil className="size-3.5" />
                          </button>

                          {/* Delete Button (Only for user-added competencies) */}
                          {item.origin === 'user' && (
                            <button
                              type="button"
                              onClick={() => handleDeleteManualCompetency(item.id)}
                              title="Hapus Kompetensi Manual"
                              disabled={deleteCompetency.isPending}
                              className="flex size-7 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 transition-all hover:bg-rose-600 hover:text-white hover:scale-105 active:scale-95 shadow-xs"
                            >
                              <Trash2 className="size-3.5" />
                            </button>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Disclaimer Footer Note */}
        <div className="flex items-start gap-2.5 rounded-xl border border-border-warm bg-surface-canvas p-4 text-xs text-text-secondary">
          <AlertTriangle className="size-4 shrink-0 text-brand" />
          <p>
            <strong className="font-semibold text-text-primary">Perhatian:</strong> {copy.disclaimer}{' '}
            Peninjauan dosen dan mahasiswa memastikan kompetensi yang diekstrak mencerminkan RPS secara akurat sebelum
            dipetakan terhadap lowongan industri.
          </p>
        </div>
      </main>

      {/* Bottom Sticky Confirmation Bar (T3.6) with Progress Indicator */}
      {isExtractionCompleted && (
        <aside
          aria-label="Bilah konfirmasi ekstraksi RPS"
          className="fixed bottom-0 inset-x-0 z-40 border-t border-border-warm bg-white/95 backdrop-blur-md px-4 py-3 md:px-8 shadow-lg transition-all"
        >
          <div className="mx-auto flex max-w-7xl flex-col sm:flex-row items-center justify-between gap-3">
            <div className="flex items-center gap-3 text-xs md:text-sm">
              <div
                className={`flex size-9 items-center justify-center rounded-full shrink-0 transition-transform ${
                  pendingCount === 0
                    ? 'bg-emerald-100 text-emerald-700 scale-105'
                    : 'bg-brand-soft text-brand'
                }`}
              >
                {pendingCount === 0 ? <CheckCircle2 className="size-5" /> : <Sparkles className="size-4.5" />}
              </div>
              <div>
                <p className="font-bold text-text-primary">
                  {pendingCount === 0
                    ? `Seluruh ${totalCount} kompetensi telah ditinjau!`
                    : `${pendingCount} dari ${totalCount} kompetensi masih berstatus 'Perlu Ditinjau'`}
                </p>
                <p className="text-xs text-text-secondary">
                  {pendingCount === 0
                    ? 'Dokumen siap dipetakan dengan lowongan industri dan profil karier.'
                    : 'Anda dapat menyetujui semua sisa sekaligus atau meninjau satu per satu.'}
                </p>
              </div>
            </div>

            {/* Mini Progress Bar Micro-Interaction */}
            <div className="hidden lg:flex flex-col gap-1 w-44 shrink-0">
              <div className="flex items-center justify-between text-[11px] font-bold text-text-secondary">
                <span>Tinjauan Selesai</span>
                <span className={pendingCount === 0 ? 'text-emerald-600 font-bold' : 'text-brand font-bold'}>
                  {totalCount > 0 ? Math.round(((totalCount - pendingCount) / totalCount) * 100) : 0}%
                </span>
              </div>
              <div className="h-1.5 w-full bg-border-warm/60 rounded-full overflow-hidden">
                <div
                  className={`h-full rounded-full transition-all duration-500 ease-out ${
                    pendingCount === 0 ? 'bg-emerald-500' : 'bg-brand'
                  }`}
                  style={{ width: `${totalCount > 0 ? ((totalCount - pendingCount) / totalCount) * 100 : 0}%` }}
                />
              </div>
            </div>

            <div className="flex items-center gap-2.5 w-full sm:w-auto justify-end">
              {pendingCount > 0 && (
                <button
                  type="button"
                  onClick={handleConfirmAllAndProceed}
                  disabled={confirmReview.isPending}
                  className="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl border border-brand bg-white px-4 py-2 text-xs font-bold text-brand shadow-xs transition-all hover:bg-brand-soft active:scale-95 disabled:opacity-50"
                >
                  <CheckCircle2 className="size-4" />
                  {confirmReview.isPending ? 'Mengonfirmasi...' : 'Setujui Semua Pending'}
                </button>
              )}

              <button
                type="button"
                onClick={handleConfirmAllAndProceed}
                disabled={confirmReview.isPending}
                className="group w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-brand px-5 py-2 text-xs font-bold text-white shadow-sm transition-all hover:bg-brand-hover hover:shadow-md active:scale-95 disabled:opacity-50"
              >
                <span>{pendingCount > 0 ? 'Konfirmasi Semua & Mulai Pemetaan' : 'Lanjut ke Pemetaan Industri'}</span>
                <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
              </button>
            </div>
          </div>
        </aside>
      )}

      {/* Modal: Tambah Manual Kompetensi */}
      {isAddModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
          <div className="w-full max-w-lg rounded-2xl border border-border-warm bg-white p-6 shadow-xl animate-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between pb-3 border-b border-border-warm">
              <h3 className="font-bold text-base text-text-primary flex items-center gap-2">
                <Plus className="size-4 text-brand" /> Tambah Kompetensi Manual
              </h3>
              <button
                type="button"
                onClick={() => setIsAddModalOpen(false)}
                className="text-text-secondary hover:text-text-primary"
              >
                <X className="size-5" />
              </button>
            </div>

            {addError && (
              <div className="mt-3 rounded-xl bg-rose-50 border border-rose-200 p-3 text-xs text-rose-800 font-medium">
                {addError}
              </div>
            )}

            <div className="mt-4 space-y-4">
              <div>
                <label htmlFor={addSkillInputId} className="block text-xs font-bold text-text-primary mb-1">
                  Nama Keterampilan / Skill <span className="text-danger">*</span>
                </label>
                <input
                  id={addSkillInputId}
                  type="text"
                  placeholder="Contoh: PostgreSQL, Machine Learning, Agile"
                  value={newSkillName}
                  onChange={(e) => setNewSkillName(e.target.value)}
                  className="w-full rounded-xl border border-border-warm px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                />
              </div>

              <div>
                <label htmlFor={addCategorySelectId} className="block text-xs font-bold text-text-primary mb-1">
                  Kategori Skill
                </label>
                <select
                  id={addCategorySelectId}
                  value={newCategory}
                  onChange={(e) => setNewCategory(e.target.value as SkillCategory)}
                  className="w-full rounded-xl border border-border-warm px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                >
                  <option value="language">Language (Bahasa Pemrograman)</option>
                  <option value="framework">Framework / Library</option>
                  <option value="tool">Tool / Perangkat Lunak</option>
                  <option value="concept">Concept (Teori / Konsep)</option>
                  <option value="practice">Practice (Praktik / Metodologi)</option>
                  <option value="other">Other (Lainnya)</option>
                </select>
              </div>

              <div>
                <label htmlFor={addCompetencyDescId} className="block text-xs font-bold text-text-primary mb-1">
                  Deskripsi Capaian Pembelajaran / Kompetensi <span className="text-danger">*</span>
                </label>
                <textarea
                  id={addCompetencyDescId}
                  rows={3}
                  placeholder="Contoh: Mahasiswa mampu merancang skema relasional teroptimasi dengan normalisasi..."
                  value={newCompetencyText}
                  onChange={(e) => setNewCompetencyText(e.target.value)}
                  className="w-full rounded-xl border border-border-warm px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                />
              </div>

              <fieldset>
                <legend className="block text-xs font-bold text-text-primary mb-1">Status Awal</legend>
                <div className="flex items-center gap-4 text-xs">
                  <label className="flex items-center gap-1.5 cursor-pointer">
                    <input
                      type="radio"
                      name="initialStatus"
                      checked={newReviewStatus === 'confirmed'}
                      onChange={() => setNewReviewStatus('confirmed')}
                    />
                    <span>Langsung Disetujui (Confirmed)</span>
                  </label>
                  <label className="flex items-center gap-1.5 cursor-pointer">
                    <input
                      type="radio"
                      name="initialStatus"
                      checked={newReviewStatus === 'pending'}
                      onChange={() => setNewReviewStatus('pending')}
                    />
                    <span>Perlu Ditinjau (Pending)</span>
                  </label>
                </div>
              </fieldset>

              <div className="rounded-xl bg-blue-50 border border-blue-200 p-3 text-[11px] text-blue-900">
                Kompetensi manual akan ditandai dengan sumber <strong>Pengguna</strong> (origin: user) dan status
                verifikasi <em>unverified</em>. Sistem backend akan otomatis menghitung embedding semantiknya.
              </div>
            </div>

            <div className="mt-6 flex items-center justify-end gap-2.5">
              <button
                type="button"
                onClick={() => setIsAddModalOpen(false)}
                className="rounded-xl border border-border-warm px-4 py-2 text-xs font-bold text-text-secondary hover:bg-surface-canvas"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={handleCreateManualCompetency}
                disabled={addCompetency.isPending || !newSkillName.trim() || !newCompetencyText.trim()}
                className="rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-brand-hover disabled:opacity-50"
              >
                {addCompetency.isPending ? 'Menyimpan...' : 'Simpan Kompetensi'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal: Edit Kompetensi */}
      {editingItem && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs">
          <div className="w-full max-w-lg rounded-2xl border border-border-warm bg-white p-6 shadow-xl animate-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between pb-3 border-b border-border-warm">
              <h3 className="font-bold text-base text-text-primary flex items-center gap-2">
                <Pencil className="size-4 text-brand" /> Edit Kompetensi
              </h3>
              <button
                type="button"
                onClick={() => setEditingItem(null)}
                className="text-text-secondary hover:text-text-primary"
              >
                <X className="size-5" />
              </button>
            </div>

            {editError && (
              <div className="mt-3 rounded-xl bg-rose-50 border border-rose-200 p-3 text-xs text-rose-800 font-medium">
                {editError}
              </div>
            )}

            <div className="mt-4 space-y-4">
              <div>
                <label htmlFor={editSkillInputId} className="block text-xs font-bold text-text-primary mb-1">
                  Nama Keterampilan / Skill <span className="text-danger">*</span>
                </label>
                <input
                  id={editSkillInputId}
                  type="text"
                  value={editSkillName}
                  onChange={(e) => setEditSkillName(e.target.value)}
                  className="w-full rounded-xl border border-border-warm px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                />
              </div>

              <div>
                <label htmlFor={editCompetencyDescId} className="block text-xs font-bold text-text-primary mb-1">
                  Deskripsi Capaian Pembelajaran / Kompetensi <span className="text-danger">*</span>
                </label>
                <textarea
                  id={editCompetencyDescId}
                  rows={4}
                  value={editCompetencyText}
                  onChange={(e) => setEditCompetencyText(e.target.value)}
                  className="w-full rounded-xl border border-border-warm px-3.5 py-2 text-sm text-text-primary outline-none focus:border-brand"
                />
              </div>

              <div className="rounded-xl bg-surface-canvas border border-border-warm p-3 text-[11px] text-text-secondary">
                <span className="font-bold text-text-primary block mb-0.5">Catatan AI:</span>
                Mengubah nama skill atau teks kompetensi akan memicu pembaharuan embedding semantik secara otomatis di latar belakang.
              </div>
            </div>

            <div className="mt-6 flex items-center justify-end gap-2.5">
              <button
                type="button"
                onClick={() => setEditingItem(null)}
                className="rounded-xl border border-border-warm px-4 py-2 text-xs font-bold text-text-secondary hover:bg-surface-canvas"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={handleSaveEdit}
                disabled={updateCompetency.isPending || !editSkillName.trim() || !editCompetencyText.trim()}
                className="rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-brand-hover disabled:opacity-50"
              >
                {updateCompetency.isPending ? 'Menyimpan...' : 'Simpan Perubahan'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
