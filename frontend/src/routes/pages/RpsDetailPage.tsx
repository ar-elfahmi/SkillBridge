import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
import { RpsHeader } from '@/components/rps/RpsHeader'
import { MetadataPanel } from '@/components/rps/MetadataPanel'
import { CompetencyReviewTable } from '@/components/rps/CompetencyReviewTable'
import { StatusNotice } from '@/components/common/StatusNotice'
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
import { usePageTitle } from '@/lib/usePageTitle'
import { copy } from '@/lib/copy'
import { ApiRequestError } from '@/lib/api/client'

function messageFrom(err: unknown, fallback: string): string {
  return err instanceof ApiRequestError && err.body.message ? err.body.message : fallback
}

export function RpsDetailPage() {
  usePageTitle('Detail RPS')
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const rpsId = id ? parseInt(id, 10) : 0

  const { data: user } = useMe()
  const logout = useLogout()

  const { data: document, isLoading: isDocLoading, isError: isDocError, error: docError, refetch: refetchDoc } =
    useRpsDetail(rpsId)
  const isExtracted = document?.extraction_status === 'completed'
  const { data: competencies = [] } = useCompetenciesList(rpsId, isExtracted)

  const updateMetadata = useUpdateRpsMetadata()
  const startExtraction = useStartExtraction()
  const updateCompetency = useUpdateCompetency()
  const createCompetency = useCreateCompetency()
  const confirmReview = useConfirmReview()

  const [errorNotice, setErrorNotice] = useState<string | null>(null)
  // Hanya sesi yang memulai ekstraksi melihat ringkasan "Ekstraksi selesai"; ia hilang saat tahap berganti.
  const [startedExtraction, setStartedExtraction] = useState(false)
  const [doneDismissed, setDoneDismissed] = useState(false)

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
          <p className="text-base font-semibold text-danger">
            {docError instanceof ApiRequestError && docError.status === 404
              ? copy.analysesResult.docNotFound
              : copy.analysesResult.loadFailed}
          </p>
          <div className="flex flex-wrap justify-center gap-2">
            {!(docError instanceof ApiRequestError && docError.status === 404) && (
              <button
                type="button"
                onClick={() => void refetchDoc()}
                className="min-h-11 rounded-lg border border-border-warm px-4 text-sm font-bold text-text-primary hover:bg-surface-subtle"
              >
                {copy.analysesResult.retry}
              </button>
            )}
            <button
              type="button"
              onClick={() => navigate('/dashboard')}
              className="min-h-11 rounded-lg bg-brand px-4 text-sm font-bold text-white hover:bg-brand-hover"
            >
              {copy.analysesResult.backToDashboard}
            </button>
          </div>
        </main>
      </div>
    )
  }

  // Menentukan tahap aktif dari status dokumen
  const isConfirmed = document.review_status === 'confirmed'
  const currentStep = isConfirmed ? 3 : isExtracted ? 2 : 1

  // Parsing PDF dan ekstraksi metadata AI adalah satu penantian yang sama bagi pengguna.
  const isPreparingMetadata =
    document.parse_status === 'pending' ||
    document.parse_status === 'parsing' ||
    (document.parse_status === 'parsed' &&
      document.detected_metadata === null &&
      document.error_message === null)

  const pendingCount = competencies.filter((c) => c.review_status === 'pending').length
  const showExtractionDone = startedExtraction && currentStep === 2 && !doneDismissed

  return (
    <div className="flex min-h-svh flex-col bg-background">
      <DashboardTopbar user={user} onLogout={handleLogout} />

      <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-3 px-4 py-3 sm:gap-6 sm:py-6 md:px-8 md:py-8">
        <RpsHeader document={document} currentStep={currentStep} />

        {errorNotice && (
          <StatusNotice variant="error" onDismiss={() => setErrorNotice(null)}>
            {errorNotice}
          </StatusNotice>
        )}

        {showExtractionDone && (
          <StatusNotice variant="success" onDismiss={() => setDoneDismissed(true)}>
            {copy.rpsDetail.metadata.extractionDone(pendingCount)}
          </StatusNotice>
        )}

        {currentStep === 1 ? (
          <MetadataPanel
            document={document}
            onSaveMetadata={(payload) => updateMetadata.mutateAsync({ id: rpsId, payload })}
            onStartExtraction={() => {
              setErrorNotice(null)
              startExtraction.mutate(rpsId, {
                onSuccess: () => {
                  setStartedExtraction(true)
                  setDoneDismissed(false)
                },
                onError: (err: unknown) =>
                  setErrorNotice(messageFrom(err, copy.rpsDetail.metadata.startFailed)),
              })
            }}
            isSaving={updateMetadata.isPending}
            isExtracting={startExtraction.isPending || document.extraction_status === 'processing'}
            isPreparing={isPreparingMetadata}
          />
        ) : (
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
            onApproveMany={(ids) =>
              Promise.all(
                ids.map((cid) =>
                  updateCompetency.mutateAsync({
                    docId: rpsId,
                    competencyId: cid,
                    payload: { review_status: 'confirmed' },
                  })
                )
              )
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
              updateCompetency.mutateAsync({
                docId: rpsId,
                competencyId: cid,
                payload: { skill, competency },
              })
            }
            onCreate={(payload) => createCompetency.mutateAsync({ id: rpsId, payload })}
            onConfirmAll={(confirmAllPending) => {
              setErrorNotice(null)
              confirmReview.mutate(
                { id: rpsId, confirmAllPending },
                {
                  onSuccess: () => navigate(`/analyses/new?rps=${rpsId}`),
                  onError: (err: unknown) =>
                    setErrorNotice(messageFrom(err, 'Gagal mengonfirmasi tinjauan.')),
                }
              )
            }}
            isConfirming={confirmReview.isPending}
            isConfirmedAll={isConfirmed}
          />
        )}
      </main>

      <footer className="flex h-14 shrink-0 items-center justify-center border-t border-border-warm px-4 md:justify-start md:px-8">
        <p className="text-xs font-medium text-text-secondary">{copy.footerBrand}</p>
      </footer>
    </div>
  )
}
