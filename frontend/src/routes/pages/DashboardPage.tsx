import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
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
import { copy } from '@/lib/copy'

export function DashboardPage() {
  const navigate = useNavigate()
  const { data: user } = useMe()
  const logout = useLogout()
  const { data: rpsList = [], isLoading: isRpsLoading } = useRpsList()
  const { data: analysesList = [] } = useAnalysesList()
  const { data: careersList = [] } = useCareersList()
  const { data: jobsList = [] } = useJobsList()
  const { data: mySkills = [] } = useMySkills()
  const uploadRps = useUploadRps()

  const [uploadError, setUploadError] = useState<string | null>(null)
  const [searchQuery, setSearchQuery] = useState('')

  function handleLogout() {
    logout.mutate(undefined, {
      onSuccess: () => navigate('/login'),
    })
  }

  function handleFileUpload(file: File) {
    setUploadError(null)
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
            if (existingId) {
              if (confirm(`${copy.dashboard.uploadDuplicate} Buka dokumen sekarang?`)) {
                navigate(`/rps/${existingId}`)
                return
              }
            }
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
    <div className="flex min-h-svh flex-col bg-background">
      <DashboardTopbar
        user={user}
        onLogout={handleLogout}
        onSearch={setSearchQuery}
      />

      <main className="mx-auto flex w-full max-w-7xl flex-1 items-start gap-8 px-4 py-6 md:px-8 md:py-8">
        <div className="flex flex-1 flex-col gap-6">
          <UploadRpsHero
            onFileSelect={handleFileUpload}
            isPending={uploadRps.isPending}
            error={uploadError}
          />

          <DashboardStats
            missingSkillsCount={totalMissingSkills}
            matchedJobsCount={matchedJobsCount}
            careersCount={careersCount}
          />

          <AnalyzedRpsSection
            documents={filteredRps}
            analyses={analysesList}
            isLoading={isRpsLoading}
          />
        </div>

        <DashboardSidebar
          user={user}
          rpsCount={rpsList.length}
          analysesCount={analysesList.length}
          skills={mySkills}
        />
      </main>

      <footer className="flex h-14 shrink-0 items-center justify-center border-t border-border-warm px-4 md:justify-start md:px-8">
        <p className="text-[11px] font-medium text-text-secondary">{copy.footerBrand}</p>
      </footer>
    </div>
  )
}
