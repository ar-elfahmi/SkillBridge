import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
import { RpsHeader } from '@/components/rps/RpsHeader'
import { MetadataPanel } from '@/components/rps/MetadataPanel'
import { CompetencyReviewTable } from '@/components/rps/CompetencyReviewTable'
import {
  useCompetenciesList,
  useConfirmReview,
  useCreateCompetency,
  useLogout,
  useMe,
  useRpsDetail,
  useStartExtraction,
  useUpdateCompetency,
  useUpdateRpsMetadata,
} from '@/lib/query/hooks'
import { copy } from '@/lib/copy'

export function RpsDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const rpsId = id ? parseInt(id, 10) : 0

  const { data: user } = useMe()
  const logout = useLogout()

  const { data: document, isLoading: isDocLoading, isError: isDocError } = useRpsDetail(rpsId)
  const isExtracted = document?.extraction_status === 'completed'
  const { data: competencies = [] } = useCompetenciesList(rpsId, isExtracted)

  const updateMetadata = useUpdateRpsMetadata()
  const startExtraction = useStartExtraction()
  const updateCompetency = useUpdateCompetency()
  const createCompetency = useCreateCompetency()
  const confirmReview = useConfirmReview()

  const [notification, setNotification] = useState<{ type: 'success' | 'error'; message: string } | null>(null)
  const [activeStep, setActiveStep] = useState<1 | 2 | 3 | null>(null)

  function handleLogout() {
    logout.mutate(undefined, {
      onSuccess: () => navigate('/login'),
    })
  }

  if (isDocLoading) {
    return (
      <div className="flex min-h-svh flex-col bg-background">
        <DashboardTopbar user={user} onLogout={handleLogout} />
        <main className="mx-auto flex w-full max-w-5xl flex-1 items-center justify-center p-6 text-sm text-text-secondary">
          Memuat data dokumen RPS…
        </main>
      </div>
    )
  }

  if (isDocError || !document) {
    return (
      <div className="flex min-h-svh flex-col bg-background">
        <DashboardTopbar user={user} onLogout={handleLogout} />
        <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center gap-3 p-6 text-center">
          <p className="text-base font-semibold text-danger">Dokumen RPS tidak ditemukan atau gagal dimuat.</p>
          <button
            type="button"
            onClick={() => navigate('/dashboard')}
            className="rounded-lg bg-brand px-4 py-2 text-xs font-bold text-white hover:bg-brand-hover"
          >
            Kembali ke Beranda
          </button>
        </main>
      </div>
    )
  }

  // Menentukan tahap aktif (bisa di-bypass via stepper / switcher untuk inspeksi desain)
  const isConfirmed = document.review_status === 'confirmed'
  const defaultStep = isConfirmed ? 3 : isExtracted ? 2 : 1
  const currentStep = activeStep ?? defaultStep

  return (
    <div className="flex min-h-svh flex-col bg-background">
      <DashboardTopbar user={user} onLogout={handleLogout} />

      <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4 py-6 md:px-8 md:py-8">
        <RpsHeader
          document={document}
          currentStep={currentStep}
          onStepChange={setActiveStep}
        />

        {notification && (
          <div
            className={`rounded-xl px-4 py-3 text-xs font-semibold ${
              notification.type === 'success'
                ? 'bg-success/15 text-success'
                : 'bg-danger/15 text-danger'
            }`}
          >
            {notification.message}
          </div>
        )}

        {/* TAHAP 0: Form Koreksi Metadata */}
        {currentStep === 1 ? (
          <MetadataPanel
            document={document}
            onSaveMetadata={(payload) => updateMetadata.mutateAsync({ id: rpsId, payload })}
            onStartExtraction={() => {
              startExtraction.mutate(rpsId, {
                onSuccess: () => {
                  setNotification({
                    type: 'success',
                    message: 'Proses ekstraksi kompetensi dimulai. Halaman akan memperbarui status secara otomatis.',
                  })
                },
                onError: (err: unknown) => {
                  const msg = (err as { body?: { message?: string } })?.body?.message || 'Gagal memulai ekstraksi.'
                  setNotification({ type: 'error', message: msg })
                },
              })
            }}
            isSaving={updateMetadata.isPending}
            isExtracting={startExtraction.isPending || document.extraction_status === 'processing'}
          />
        ) : (
          /* TAHAP 1: Tabel Tinjauan Kompetensi */
          <CompetencyReviewTable
            documentId={rpsId}
            competencies={competencies}
            onApprove={(cid) =>
              updateCompetency.mutate({
                docId: rpsId,
                competencyId: cid,
                payload: { review_status: 'confirmed' },
              })
            }
            onReject={(cid) =>
              updateCompetency.mutate({
                docId: rpsId,
                competencyId: cid,
                payload: { review_status: 'rejected' },
              })
            }
            onDelete={(cid) =>
              updateCompetency.mutate({
                docId: rpsId,
                competencyId: cid,
                payload: { review_status: 'rejected' },
              })
            }
            onEdit={(cid, skill, competency) =>
              updateCompetency.mutate({
                docId: rpsId,
                competencyId: cid,
                payload: { skill, competency },
              })
            }
            onCreate={(payload) =>
              createCompetency.mutate({ id: rpsId, payload })
            }
            onConfirmAll={(confirmAllPending) => {
              confirmReview.mutate(
                { id: rpsId, confirmAllPending },
                {
                  onSuccess: () => {
                    setNotification({
                      type: 'success',
                      message: 'Seluruh hasil review kompetensi telah dikonfirmasi! Anda dapat melanjutkan ke Pemetaan Industri.',
                    })
                  },
                  onError: (err: unknown) => {
                    const msg = (err as { body?: { message?: string } })?.body?.message || 'Gagal mengonfirmasi review.'
                    setNotification({ type: 'error', message: msg })
                  },
                }
              )
            }}
            isConfirming={confirmReview.isPending}
            isConfirmedAll={isConfirmed}
          />
        )}
      </main>

      <footer className="flex h-14 shrink-0 items-center justify-center border-t border-border-warm px-4 md:justify-start md:px-8">
        <p className="text-[11px] font-medium text-text-secondary">{copy.footerBrand}</p>
      </footer>
    </div>
  )
}
