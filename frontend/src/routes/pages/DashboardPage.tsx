import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
import { AiQuotaBanner } from '@/components/common/AiQuotaBanner'
import { UploadRpsHero } from '@/components/dashboard/UploadRpsHero'
import { DashboardStats } from '@/components/dashboard/DashboardStats'
import { AnalyzedRpsSection } from '@/components/dashboard/AnalyzedRpsSection'
import { DashboardSidebar } from '@/components/dashboard/DashboardSidebar'
import {
  useAnalysesList,
  useCareersList,
  useJobsList,
  useLogout,
  useMe,
  useMySkills,
  useRpsList,
  useUploadRps,
} from '@/lib/query/hooks'
import { ApiRequestError } from '@/lib/api/client'
import { usePageTitle } from '@/lib/usePageTitle'
import { copy } from '@/lib/copy'

export function DashboardPage() {
  usePageTitle('Beranda')
  const navigate = useNavigate()
  const notice = (useLocation().state as { notice?: string } | null)?.notice
  const { data: user } = useMe()
  const logout = useLogout()
  const { data: rpsList = [], isLoading: isRpsLoading } = useRpsList()
  const { data: analysesList = [] } = useAnalysesList()
  const { data: careersList = [] } = useCareersList()
  const { data: jobsList = [] } = useJobsList()
  const { data: mySkills = [] } = useMySkills()
  const uploadRps = useUploadRps()

  const [uploadError, setUploadError] = useState<string | null>(null)
  const [duplicateId, setDuplicateId] = useState<number | null>(null)
  const [searchQuery, setSearchQuery] = useState('')

  function handleLogout() {
    logout.mutate(undefined, {
      onSuccess: () => navigate('/login'),
    })
  }

  function handleFileUpload(file: File) {
    setUploadError(null)
    setDuplicateId(null)
    uploadRps.mutate(file, {
      onSuccess: (res) => {
        // HTTP 202 Accepted -> redirect ke Detail RPS Tahap 0 (konfirmasi metadata)
        if (res?.data?.id) {
          navigate(`/rps/${res.data.id}`)
        }
      },
      onError: (err) => {
        if (err instanceof ApiRequestError) {
          if (err.status === 409) {
            const existingId = (err.body as { existing_id?: number }).existing_id
            if (existingId) setDuplicateId(existingId)
            setUploadError(copy.dashboard.uploadDuplicate)
            return
          }
          if (err.status === 413) {
            setUploadError(copy.dashboard.fileTooLarge)
            return
          }
          if (err.body?.message) {
            setUploadError(err.body.message)
            return
          }
        }
        setUploadError('Gagal mengunggah berkas. Coba beberapa saat lagi.')
      },
    })
  }

  // Filter RPS berdasarkan search bar
  const filteredRps = rpsList.filter((doc) => {
    if (!searchQuery.trim()) return true
    const q = searchQuery.toLowerCase()
    return (
      doc.original_name.toLowerCase().includes(q) ||
      doc.course?.name?.toLowerCase().includes(q) ||
      doc.detected_metadata?.name?.toLowerCase().includes(q)
    )
  })

  // Agregasi statistik nyata dari API (0 jika belum ada data)
  const totalMissingSkills = analysesList.reduce(
    (acc, a) => acc + (a.summary?.missing ?? 0),
    0
  )
  const matchedJobsCount = jobsList.length
  const careersCount = careersList.length

  return (
    <div className="flex h-dvh flex-col overflow-hidden bg-background sm:h-auto sm:min-h-svh sm:overflow-visible">
      <DashboardTopbar
        user={user}
        onLogout={handleLogout}
        onSearch={setSearchQuery}
      />

      <main className="mx-auto flex min-h-0 w-full max-w-7xl flex-1 items-start gap-8 px-4 py-3 sm:py-6 md:px-8 md:py-8">
        <h1 className="sr-only">Beranda</h1>
        <div className="flex h-full min-h-0 flex-1 flex-col gap-3 sm:h-auto sm:gap-6">
          <AiQuotaBanner />
          {notice && (
            <p role="status" className="rounded-lg bg-brand-soft/40 p-3 text-sm font-semibold text-text-primary">
              {notice}
            </p>
          )}
          <UploadRpsHero
            onFileSelect={handleFileUpload}
            isPending={uploadRps.isPending}
            error={uploadError}
            errorAction={
              duplicateId ? (
                <Link
                  to={`/rps/${duplicateId}`}
                  className="inline-flex min-h-11 items-center underline"
                >
                  {copy.dashboard.openExisting}
                </Link>
              ) : undefined
            }
          />

          <DashboardStats
            missingSkillsCount={totalMissingSkills}
            matchedJobsCount={matchedJobsCount}
            careersCount={careersCount}
            analysesCount={analysesList.length}
          />

          <AnalyzedRpsSection
            documents={filteredRps}
            analyses={analysesList}
            isLoading={isRpsLoading}
            searchQuery={searchQuery.trim()}
            onClearSearch={() => setSearchQuery('')}
          />
        </div>

        <DashboardSidebar
          user={user}
          rpsCount={rpsList.length}
          analysesCount={analysesList.length}
          skills={mySkills}
        />
      </main>

      <footer className="hidden h-14 shrink-0 items-center justify-center border-t border-border-warm px-4 sm:flex md:justify-start md:px-8">
        <p className="text-xs font-medium text-text-secondary">{copy.footerBrand}</p>
      </footer>
    </div>
  )
}
