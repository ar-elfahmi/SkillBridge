import { useEffect, useRef, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
import { AiQuotaBanner } from '@/components/common/AiQuotaBanner'
import { UploadRpsHero } from '@/components/dashboard/UploadRpsHero'
import { DashboardStats } from '@/components/dashboard/DashboardStats'
import { AnalyzedRpsSection } from '@/components/dashboard/AnalyzedRpsSection'
import { DeleteRpsDialog } from '@/components/dashboard/DeleteRpsDialog'
import { StatusNotice } from '@/components/common/StatusNotice'
import { DashboardSidebar } from '@/components/dashboard/DashboardSidebar'
import {
  useAnalysesList,
  useCareersList,
  useDeleteRps,
  useLogout,
  useMe,
  useMySkills,
  useRpsList,
  useUploadRps,
} from '@/lib/query/hooks'
import { ApiRequestError } from '@/lib/api/client'
import { rpsApi } from '@/lib/api/endpoints'
import { usePageTitle } from '@/lib/usePageTitle'
import { copy } from '@/lib/copy'
import { toDisplayTitle } from '@/lib/labels'

const DELETED_NOTICE_MS = 5000

export function DashboardPage() {
  usePageTitle('Beranda')
  const navigate = useNavigate()
  const notice = (useLocation().state as { notice?: string } | null)?.notice
  const { data: user } = useMe()
  const logout = useLogout()
  const { data: rpsList = [], isLoading: isRpsLoading } = useRpsList()
  const { data: analysesList = [] } = useAnalysesList()
  const { data: careersList = [] } = useCareersList()
  const { data: mySkills = [] } = useMySkills()
  const uploadRps = useUploadRps()
  const deleteRps = useDeleteRps()
  // Tombol hapus hanya ada bila fungsi API-nya tersedia, agar tidak ada kontrol yang tidak berfungsi.
  const canDelete = typeof rpsApi.remove === 'function'
  const [toDelete, setToDelete] = useState<{ id: number; name: string } | null>(null)
  const [deleteFailed, setDeleteFailed] = useState(false)
  const [deletedNotice, setDeletedNotice] = useState(false)
  const noticeRef = useRef<HTMLDivElement>(null)

  const [uploadError, setUploadError] = useState<string | null>(null)
  const [duplicateId, setDuplicateId] = useState<number | null>(null)
  const [searchQuery, setSearchQuery] = useState('')

  // Banner konfirmasi hilang sendiri; tombol tutup tetap tersedia.
  useEffect(() => {
    if (!deletedNotice) return
    const timer = setTimeout(() => setDeletedNotice(false), DELETED_NOTICE_MS)
    return () => clearTimeout(timer)
  }, [deletedNotice])

  function finishDelete() {
    setToDelete(null)
    setDeletedNotice(true)
    setTimeout(() => noticeRef.current?.focus(), 0)
  }

  function confirmDelete() {
    if (!toDelete) return
    setDeleteFailed(false)
    deleteRps.mutate(toDelete.id, {
      onSuccess: finishDelete,
      onError: (err) => {
        // 404 berarti dokumen sudah tidak ada: hasil akhirnya sama dengan terhapus.
        if (err instanceof ApiRequestError && err.status === 404) finishDelete()
        else setDeleteFailed(true)
      },
    })
  }

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
            if (existingId) {
              setDuplicateId(existingId)
              return
            }
            setUploadError(copy.dashboard.uploadDuplicate(null))
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

  const duplicateDoc = duplicateId ? rpsList.find((d) => d.id === duplicateId) : undefined
  const duplicateNotice = duplicateId
    ? {
        message: copy.dashboard.uploadDuplicate(
          duplicateDoc
            ? toDisplayTitle(
                duplicateDoc.course?.name || duplicateDoc.detected_metadata?.name || duplicateDoc.original_name
              )
            : null
        ),
        action: (
          <Link to={`/rps/${duplicateId}`} className="inline-flex min-h-11 items-center underline">
            {copy.dashboard.openExisting}
          </Link>
        ),
        onDismiss: () => setDuplicateId(null),
      }
    : null

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
  const matchedJobsCount = careersList.reduce((acc, c) => acc + c.eligible_job_count, 0)
  const careersCount = careersList.length

  return (
    <div className="flex min-h-svh flex-col bg-background">
      <DashboardTopbar
        user={user}
        onLogout={handleLogout}
        onSearch={setSearchQuery}
      />

      <main className="mx-auto flex w-full max-w-7xl flex-1 items-start gap-8 px-4 py-3 sm:py-6 md:px-8 md:py-8">
        <h1 className="sr-only">Beranda</h1>
        <div className="flex min-w-0 flex-1 flex-col gap-3 sm:gap-6">
          <AiQuotaBanner />
          {notice && (
            <p role="status" className="rounded-lg bg-brand-soft/40 p-3 text-sm font-semibold text-text-primary">
              {notice}
            </p>
          )}
          <div ref={noticeRef} tabIndex={-1} className="outline-none empty:hidden">
            {deletedNotice && (
              <StatusNotice variant="success" onDismiss={() => setDeletedNotice(false)}>
                {copy.dashboard.deleteDoc.done}
              </StatusNotice>
            )}
          </div>
          <UploadRpsHero
            onFileSelect={handleFileUpload}
            isPending={uploadRps.isPending}
            error={uploadError}
            notice={duplicateNotice}
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
            onDelete={
              canDelete
                ? (doc, name) => {
                    setDeleteFailed(false)
                    setDeletedNotice(false)
                    setToDelete({ id: doc.id, name })
                  }
                : undefined
            }
          />
        </div>

        <DashboardSidebar
          user={user}
          rpsCount={rpsList.length}
          analysesCount={analysesList.length}
          skills={mySkills}
        />
      </main>

      {toDelete && (
        <DeleteRpsDialog
          name={toDelete.name}
          isPending={deleteRps.isPending}
          hasError={deleteFailed}
          onConfirm={confirmDelete}
          onCancel={() => setToDelete(null)}
        />
      )}

      <footer className="flex h-14 shrink-0 items-center justify-center border-t border-border-warm px-4 md:justify-start md:px-8">
        <p className="text-xs font-medium text-text-secondary">{copy.footerBrand}</p>
      </footer>
    </div>
  )
}
