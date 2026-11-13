import { useState } from 'react'
import { useSearchParams, useNavigate, Link } from 'react-router-dom'
import {
  ArrowLeft,
  Check,
  ChevronDown,
  ChevronUp,
  Plus,
  Search,
  Briefcase,
} from 'lucide-react'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  useCareersList,
  useCreateAnalysis,
  useCreateJob,
  useJobsList,
  useLogout,
  useMe,
  useRpsDetail,
} from '@/lib/query/hooks'
import type { JobPosting } from '@/types/api'
import { copy } from '@/lib/copy'

export function AnalysesNewPage() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const rpsId = searchParams.get('rps') ? parseInt(searchParams.get('rps')!, 10) : 0

  const { data: user } = useMe()
  const logout = useLogout()
  const { data: document } = useRpsDetail(rpsId)
  const { data: careers = [], isLoading: isCareersLoading } = useCareersList()

  // State Step 1: Profesi
  const [selectedCareerId, setSelectedCareerId] = useState<number | null>(null)

  // Default pilih career pertama saat data siap
  const currentCareerId = selectedCareerId ?? (careers[0]?.id ?? null)
  const selectedCareer = careers.find((c) => c.id === currentCareerId)

  // Data lowongan sesuai career
  const { data: jobs = [], isLoading: isJobsLoading } = useJobsList(currentCareerId ?? undefined)

  // State Step 2: Lowongan
  const [selectedJobIds, setSelectedJobIds] = useState<number[]>([])
  const [activeJobDetail, setActiveJobDetail] = useState<JobPosting | null>(null)
  const [jobSearch, setJobSearch] = useState('')
  const [jobFilter, setJobFilter] = useState<'all' | 'selected'>('all')

  // State Add Job
  const [isAddJobOpen, setIsAddJobOpen] = useState(false)
  const [newTitle, setNewTitle] = useState('')
  const [newCompany, setNewCompany] = useState('')
  const [newDesc, setNewDesc] = useState('')
  const [newUrl, setNewUrl] = useState('')

  const createJob = useCreateJob()
  const createAnalysis = useCreateAnalysis()
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  function handleLogout() {
    logout.mutate(undefined, {
      onSuccess: () => navigate('/login'),
    })
  }

  function toggleJobSelection(id: number) {
    setSelectedJobIds((prev) =>
      prev.includes(id) ? prev.filter((jId) => jId !== id) : [...prev, id]
    )
  }

  // Filter lowongan
  const filteredJobs = jobs.filter((j) => {
    if (jobFilter === 'selected' && !selectedJobIds.includes(j.id)) return false
    if (!jobSearch.trim()) return true
    const q = jobSearch.toLowerCase()
    return (
      j.title.toLowerCase().includes(q) ||
      j.company.toLowerCase().includes(q) ||
      j.skills?.some((s) => s.skill.toLowerCase().includes(q))
    )
  })

  // Agregasi Step 3: Pratinjau pemetaan awal (skill frekuensi dari lowongan yang dipilih)
  const selectedJobs = jobs.filter((j) => selectedJobIds.includes(j.id))
  const skillFrequencyMap = new Map<string, number>()
  for (const j of selectedJobs) {
    if (j.skills && j.skills.length > 0) {
      for (const s of j.skills) {
        const name = s.canonical_skill || s.skill
        skillFrequencyMap.set(name, (skillFrequencyMap.get(name) || 0) + 1)
      }
    }
  }
  const previewSkills = Array.from(skillFrequencyMap.entries())
    .map(([skill, count]) => ({ skill, count }))
    .sort((a, b) => b.count - a.count)

  function handleAddJob(e: React.FormEvent) {
    e.preventDefault()
    if (!currentCareerId || !newTitle.trim() || !newCompany.trim() || !newDesc.trim()) return
    createJob.mutate(
      {
        career_id: currentCareerId,
        title: newTitle.trim(),
        company: newCompany.trim(),
        description: newDesc.trim(),
        source_url: newUrl.trim() || undefined,
      },
      {
        onSuccess: (res) => {
          if (res?.data?.id) {
            setSelectedJobIds((prev) => [...prev, res.data.id])
          }
          setNewTitle('')
          setNewCompany('')
          setNewDesc('')
          setNewUrl('')
          setIsAddJobOpen(false)
        },
      }
    )
  }

  function handleStartAnalysis() {
    setErrorMsg(null)
    if (!rpsId) {
      setErrorMsg('Dokumen RPS tidak valid.')
      return
    }
    if (!currentCareerId) {
      setErrorMsg('Pilih salah satu profesi tujuan.')
      return
    }
    if (selectedJobIds.length === 0) {
      setErrorMsg('Pilih minimal 1 lowongan pekerjaan untuk dianalisis.')
      return
    }

    createAnalysis.mutate(
      {
        rps_document_id: rpsId,
        career_id: currentCareerId,
        job_posting_ids: selectedJobIds,
      },
      {
        onSuccess: (res) => {
          if (res?.data?.analysis_id) {
            navigate(`/analyses/${res.data.analysis_id}`)
          }
        },
        onError: (err: unknown) => {
          const msg =
            (err as { body?: { message?: string } })?.body?.message ||
            'Gagal memulai analisis. Pastikan status review RPS telah confirmed.'
          setErrorMsg(msg)
        },
      }
    )
  }

  const courseDisplayName =
    document?.course?.name ||
    document?.detected_metadata?.name ||
    document?.original_name ||
    'RPS Dokumen'
  const courseCode =
    document?.course?.code || document?.detected_metadata?.code || ''

  return (
    <div className="flex min-h-svh flex-col bg-background">
      <DashboardTopbar user={user} onLogout={handleLogout} />

      <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4 py-6 md:px-8 md:py-8">
        {/* Header Block */}
        <div className="flex flex-col gap-3 border-b border-border-warm pb-5">
          <div className="flex items-center gap-3">
            <Link
              to={rpsId ? `/rps/${rpsId}` : '/dashboard'}
              className="flex size-9 items-center justify-center rounded-full border border-border-warm bg-white text-text-primary hover:bg-surface-subtle"
            >
              <ArrowLeft className="size-4" />
            </Link>
            <div className="flex flex-col">
              <h1 className="font-display text-xl font-normal text-text-primary md:text-3xl">
                {copy.analysesNew.pageTitle}
              </h1>
              <span className="text-xs text-text-secondary md:text-sm">
                {courseDisplayName} {courseCode ? `· ${courseCode}` : ''}
              </span>
            </div>
          </div>
        </div>

        {errorMsg && (
          <div className="rounded-xl bg-danger/15 p-3 text-xs font-semibold text-danger">
            {errorMsg}
          </div>
        )}

        {/* STEP 1: Pilih Profesi */}
        <section className="flex flex-col gap-4 rounded-2xl border border-border-warm bg-white p-6 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="flex size-7 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">
              1
            </div>
            <h2 className="text-base font-bold text-text-primary md:text-lg">
              {copy.analysesNew.step1Title}
            </h2>
          </div>

          <div className="flex flex-col gap-3 max-w-md">
            <div className="relative">
              <select
                aria-label={copy.analysesNew.step1Title}
                value={currentCareerId ?? ''}
                onChange={(e) => {
                  setSelectedCareerId(parseInt(e.target.value, 10))
                  setSelectedJobIds([])
                  setActiveJobDetail(null)
                }}
                disabled={isCareersLoading}
                className="w-full appearance-none rounded-xl border border-border-warm bg-white px-4 py-3 text-sm font-semibold text-text-primary shadow-xs outline-none focus:border-brand pr-10"
              >
                {careers.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
              <ChevronDown className="pointer-events-none absolute right-3.5 top-3.5 size-4 text-text-secondary" />
            </div>

            {selectedCareer?.description && (
              <p className="text-xs text-text-secondary leading-relaxed">
                {selectedCareer.description}
              </p>
            )}
          </div>
        </section>

        {/* STEP 2: Pilih Lowongan */}
        <section className="flex flex-col gap-4 rounded-2xl border border-border-warm bg-white p-6 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="flex size-7 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">
              2
            </div>
            <h2 className="text-base font-bold text-text-primary md:text-lg">
              {copy.analysesNew.step2Title}
            </h2>
          </div>

          {/* Job Toolbar */}
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-1 max-w-sm items-center gap-2 rounded-full border border-border-warm bg-surface-subtle px-3 py-1.5 text-xs text-text-primary">
              <Search className="size-3.5 text-text-secondary" />
              <input
                type="text"
                placeholder={copy.analysesNew.searchJobsPlaceholder}
                value={jobSearch}
                onChange={(e) => setJobSearch(e.target.value)}
                className="w-full bg-transparent outline-none placeholder:text-text-secondary"
              />
            </div>

            <div className="flex items-center gap-1 rounded-lg bg-surface-subtle p-1 text-xs">
              <button
                type="button"
                onClick={() => setJobFilter('all')}
                className={`rounded-md px-3 py-1 font-semibold transition-colors ${
                  jobFilter === 'all'
                    ? 'bg-white text-brand shadow-xs'
                    : 'text-text-secondary'
                }`}
              >
                {copy.analysesNew.filterAll} ({jobs.length})
              </button>
              <button
                type="button"
                onClick={() => setJobFilter('selected')}
                className={`rounded-md px-3 py-1 font-semibold transition-colors ${
                  jobFilter === 'selected'
                    ? 'bg-white text-brand shadow-xs'
                    : 'text-text-secondary'
                }`}
              >
                {copy.analysesNew.filterSelected} ({selectedJobIds.length})
              </button>
            </div>
          </div>

          {/* Jobs List & Drawer side-by-side */}
          <div className="grid grid-cols-1 gap-5 lg:grid-cols-12">
            {/* List Lowongan */}
            <div className="flex flex-col gap-3 lg:col-span-7">
              {isJobsLoading ? (
                <div className="p-8 text-center text-xs text-text-secondary">
                  Memuat data lowongan…
                </div>
              ) : filteredJobs.length === 0 ? (
                <div className="rounded-xl border border-dashed border-border-warm p-6 text-center text-xs text-text-secondary">
                  Tidak ada lowongan yang sesuai. Tambahkan lowongan manual di bawah.
                </div>
              ) : (
                filteredJobs.map((j) => {
                  const isChecked = selectedJobIds.includes(j.id)
                  const isDrawerActive = activeJobDetail?.id === j.id
                  return (
                    <div
                      key={j.id}
                      onClick={() => setActiveJobDetail(j)}
                      className={`flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition-all ${
                        isDrawerActive
                          ? 'border-brand bg-brand-soft/20'
                          : 'border-border-warm bg-white hover:border-brand/60'
                      }`}
                    >
                      <button
                        type="button"
                        aria-label="Pilih lowongan"
                        onClick={(e) => {
                          e.stopPropagation()
                          toggleJobSelection(j.id)
                        }}
                        className={`mt-0.5 flex size-5 shrink-0 items-center justify-center rounded border transition-colors ${
                          isChecked
                            ? 'border-brand bg-brand text-white'
                            : 'border-border-warm bg-white'
                        }`}
                      >
                        {isChecked && <Check className="size-3.5" />}
                      </button>

                      <div className="flex flex-col gap-1 overflow-hidden">
                        <span className="text-sm font-bold text-text-primary">
                          {j.title}
                        </span>
                        <span className="text-xs text-brand font-medium">
                          {j.company}
                        </span>

                        {j.skills && j.skills.length > 0 && (
                          <div className="mt-1 flex flex-wrap gap-1">
                            {j.skills.map((s) => (
                              <span
                                key={s.id}
                                className="rounded bg-surface-subtle px-2 py-0.5 text-[10px] font-semibold text-text-secondary"
                              >
                                {s.canonical_skill || s.skill}
                              </span>
                            ))}
                          </div>
                        )}

                        <span className="mt-1 text-[11px] text-text-secondary">
                          {j.source_name || 'Industri'}
                          {j.collected_at ? ` · Diambil ${j.collected_at}` : ''}
                        </span>
                      </div>
                    </div>
                  )
                })
              )}

              <p className="text-[11px] text-text-secondary">
                {copy.analysesNew.sampleNote}
              </p>
            </div>

            {/* Drawer Preview Detail Lowongan */}
            <div className="flex flex-col gap-3 rounded-xl border border-border-warm bg-surface-subtle p-5 lg:col-span-5">
              <span className="text-xs font-bold uppercase tracking-wider text-text-secondary">
                {copy.analysesNew.drawerTitle}
              </span>

              {activeJobDetail ? (
                <div className="flex flex-col gap-3">
                  <div className="flex flex-col">
                    <h3 className="text-base font-bold text-text-primary">
                      {activeJobDetail.title}
                    </h3>
                    <span className="text-xs font-semibold text-brand">
                      {activeJobDetail.company}
                    </span>
                  </div>

                  <p className="text-xs text-text-primary leading-relaxed whitespace-pre-line">
                    {activeJobDetail.description}
                  </p>

                  {activeJobDetail.qualifications && (
                    <div className="rounded-lg bg-white p-3 text-xs italic text-text-secondary">
                      “{activeJobDetail.qualifications}”
                    </div>
                  )}

                  <span className="text-[11px] text-text-secondary">
                    Sumber: {activeJobDetail.source_name || 'Industri'}
                    {activeJobDetail.source_url ? ` · ${activeJobDetail.source_url}` : ''}
                    {activeJobDetail.dataset_version
                      ? ` · dataset ${activeJobDetail.dataset_version}`
                      : ''}
                  </span>
                </div>
              ) : (
                <div className="flex h-40 flex-col items-center justify-center gap-2 text-center text-xs text-text-secondary">
                  <Briefcase className="size-6 text-border-warm" />
                  <p>{copy.analysesNew.drawerEmpty}</p>
                </div>
              )}
            </div>
          </div>
        </section>

        {/* STEP 3: Pratinjau Pemetaan Awal */}
        <section className="flex flex-col gap-4 rounded-2xl border border-border-warm bg-white p-6 shadow-sm">
          <div className="flex items-center gap-3">
            <div className="flex size-7 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">
              3
            </div>
            <div className="flex flex-col">
              <h2 className="text-base font-bold text-text-primary md:text-lg">
                {copy.analysesNew.step3Title}
              </h2>
              <span className="text-xs text-text-secondary">
                {copy.analysesNew.step3Subtitle}
              </span>
            </div>
          </div>

          {previewSkills.length === 0 ? (
            <div className="rounded-xl border border-dashed border-border-warm p-6 text-center text-xs text-text-secondary">
              Pilih minimal satu lowongan di atas untuk melihat pratinjau skill yang akan dianalisis.
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-3">
              {previewSkills.map((ps) => (
                <div
                  key={ps.skill}
                  className="flex items-center justify-between rounded-xl border border-border-warm bg-surface-subtle px-3.5 py-2 text-xs"
                >
                  <span className="font-bold text-text-primary">{ps.skill}</span>
                  <span className="text-[11px] text-text-secondary">
                    Diminta di {ps.count} {copy.analysesNew.skillDemandSuffix}
                  </span>
                </div>
              ))}
            </div>
          )}
        </section>

        {/* Collapsible: Tambah Lowongan Manual */}
        <div className="flex flex-col rounded-2xl border border-border-warm bg-white shadow-sm overflow-hidden">
          <button
            type="button"
            onClick={() => setIsAddJobOpen((v) => !v)}
            className="flex items-center justify-between p-4 text-xs font-bold text-text-primary hover:bg-surface-subtle transition-colors"
          >
            <div className="flex items-center gap-2">
              <Plus className="size-4 text-brand" />
              <span>{copy.analysesNew.addJobLabel}</span>
            </div>
            {isAddJobOpen ? (
              <ChevronUp className="size-4 text-text-secondary" />
            ) : (
              <ChevronDown className="size-4 text-text-secondary" />
            )}
          </button>

          {isAddJobOpen && (
            <form onSubmit={handleAddJob} className="flex flex-col gap-4 border-t border-border-warm p-6 bg-surface-subtle/30">
              <span className="text-sm font-bold text-text-primary">
                {copy.analysesNew.addJobTitle}
              </span>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="newJobTitle">{copy.analysesNew.jobTitleLabel}</Label>
                  <Input
                    id="newJobTitle"
                    value={newTitle}
                    onChange={(e) => setNewTitle(e.target.value)}
                    placeholder="Contoh: Frontend Developer"
                    required
                  />
                </div>
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="newJobCompany">{copy.analysesNew.companyLabel}</Label>
                  <Input
                    id="newJobCompany"
                    value={newCompany}
                    onChange={(e) => setNewCompany(e.target.value)}
                    placeholder="Contoh: PT Teknologi Masa Depan"
                    required
                  />
                </div>
              </div>

              <div className="flex flex-col gap-1.5">
                <Label htmlFor="newJobDesc">{copy.analysesNew.jobDescLabel}</Label>
                <textarea
                  id="newJobDesc"
                  rows={3}
                  value={newDesc}
                  onChange={(e) => setNewDesc(e.target.value)}
                  placeholder="Ketik deskripsi pekerjaan dan skill yang dicari..."
                  required
                  className="w-full rounded-lg border border-border-warm bg-background p-3 text-xs outline-none focus:border-brand"
                />
              </div>

              <div className="flex flex-col gap-1.5">
                <Label htmlFor="newJobUrl">{copy.analysesNew.sourceUrlLabel}</Label>
                <Input
                  id="newJobUrl"
                  type="url"
                  value={newUrl}
                  onChange={(e) => setNewUrl(e.target.value)}
                  placeholder="https://..."
                />
              </div>

              <div className="flex justify-end gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => setIsAddJobOpen(false)}
                >
                  Batal
                </Button>
                <Button
                  type="submit"
                  size="sm"
                  disabled={createJob.isPending}
                  className="bg-brand text-white"
                >
                  {createJob.isPending ? 'Menyimpan…' : 'Simpan & Pilih Lowongan'}
                </Button>
              </div>
            </form>
          )}
        </div>

        {/* Action Bar Bawah */}
        <div className="flex flex-col gap-3 rounded-2xl border border-border-warm bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs text-text-secondary">
            {copy.analysesNew.actionRequirement}
          </p>

          <Button
            type="button"
            onClick={handleStartAnalysis}
            disabled={selectedJobIds.length === 0 || createAnalysis.isPending}
            className="h-11 bg-brand px-6 text-sm font-bold text-white hover:bg-brand-hover shadow disabled:opacity-50"
          >
            {createAnalysis.isPending
              ? copy.analysesNew.startingAnalysis
              : copy.analysesNew.startAnalysisBtn}
          </Button>
        </div>
      </main>

      <footer className="flex h-14 shrink-0 items-center justify-center border-t border-border-warm px-4 md:justify-start md:px-8">
        <p className="text-[11px] font-medium text-text-secondary">{copy.footerBrand}</p>
      </footer>
    </div>
  )
}
