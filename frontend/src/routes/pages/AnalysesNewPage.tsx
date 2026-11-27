import { useState } from 'react'
import { useSearchParams, useNavigate, Link } from 'react-router-dom'
import { ArrowLeft, Check } from 'lucide-react'
import { AiQuotaBanner } from '@/components/common/AiQuotaBanner'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
import { Button } from '@/components/ui/button'
import {
  useCareersList,
  useCreateAnalysis,
  useLogout,
  useMe,
  useRpsDetail,
} from '@/lib/query/hooks'
import { usePageTitle } from '@/lib/usePageTitle'
import { copy } from '@/lib/copy'

export function AnalysesNewPage() {
  usePageTitle('Pemetaan industri')
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const rpsId = searchParams.get('rps') ? parseInt(searchParams.get('rps')!, 10) : 0

  const { data: user } = useMe()
  const logout = useLogout()
  const { data: document } = useRpsDetail(rpsId)
  const { data: careers = [], isLoading: isCareersLoading, isError: isCareersError } =
    useCareersList()

  const [selectedCareerId, setSelectedCareerId] = useState<number | null>(null)

  // Default: first profession that has job postings ready to use.
  const defaultCareerId = careers.find((c) => c.eligible_job_count > 0)?.id ?? null
  const currentCareerId = selectedCareerId ?? defaultCareerId
  const selectedCareer = careers.find((c) => c.id === currentCareerId)

  const createAnalysis = useCreateAnalysis()
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  function handleLogout() {
    logout.mutate(undefined, {
      onSuccess: () => navigate('/login'),
    })
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

    createAnalysis.mutate(
      { rps_document_id: rpsId, career_id: currentCareerId },
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
        <div className="flex flex-col gap-3 border-b border-border-warm pb-5">
          <div className="flex items-center gap-3">
            <Link
              to={rpsId ? `/rps/${rpsId}` : '/dashboard'}
              className="flex size-11 shrink-0 items-center justify-center rounded-full border border-border-warm bg-white text-text-primary hover:bg-surface-subtle active:bg-surface-subtle sm:size-9"
              aria-label="Kembali"
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

        <AiQuotaBanner />

        {errorMsg && (
          <div role="alert" className="rounded-lg bg-danger/15 p-3 text-xs font-semibold text-danger">
            {errorMsg}
          </div>
        )}

        <section className="flex flex-col gap-4 rounded-xl border border-border-warm bg-white p-4 sm:p-6">
          <div className="flex flex-col gap-1">
            <h2 className="text-base font-bold text-text-primary md:text-lg">
              {copy.analysesNew.step1Title}
            </h2>
            <p className="text-xs text-text-secondary md:text-sm">
              {copy.analysesNew.step1Subtitle}
            </p>
          </div>

          {isCareersLoading ? (
            <div className="p-8 text-center text-xs text-text-secondary">
              {copy.analysesNew.careersLoading}
            </div>
          ) : isCareersError ? (
            <div role="alert" className="rounded-lg bg-danger/15 p-4 text-xs font-semibold text-danger">
              {copy.analysesNew.careersError}
            </div>
          ) : careers.length === 0 ? (
            <div className="rounded-lg border border-dashed border-border-warm p-6 text-center text-xs text-text-secondary">
              {copy.analysesNew.careersEmpty}
            </div>
          ) : (
            <div
              role="radiogroup"
              aria-label={copy.analysesNew.step1Title}
              className="grid grid-cols-1 gap-3 md:grid-cols-2"
            >
              {careers.map((c) => {
                const isSelected = c.id === currentCareerId
                const isDisabled = c.eligible_job_count === 0
                return (
                  <label
                    key={c.id}
                    className={`relative flex min-h-11 flex-col gap-1.5 rounded-lg border p-4 transition-colors ${
                      isDisabled
                        ? 'cursor-not-allowed border-border-warm bg-surface-subtle opacity-60'
                        : isSelected
                          ? 'cursor-pointer border-brand bg-brand-soft/30'
                          : 'cursor-pointer border-border-warm bg-white hover:border-brand/60'
                    } has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-accent/40`}
                  >
                    <input
                      type="radio"
                      name="career"
                      value={c.id}
                      checked={isSelected}
                      disabled={isDisabled}
                      onChange={() => setSelectedCareerId(c.id)}
                      className="sr-only"
                    />
                    <span className="flex items-start justify-between gap-2">
                      <span className="text-sm font-bold text-text-primary">{c.name}</span>
                      {isSelected && (
                        <span className="inline-flex shrink-0 items-center gap-1 rounded-md bg-brand px-2 py-0.5 text-xs font-semibold text-white">
                          <Check className="size-3" aria-hidden="true" />
                          {copy.analysesNew.selectedBadge}
                        </span>
                      )}
                    </span>
                    {c.description && (
                      <span className="text-xs leading-relaxed text-text-secondary">
                        {c.description}
                      </span>
                    )}
                    <span className="text-xs font-semibold text-text-secondary">
                      {isDisabled
                        ? copy.analysesNew.noJobsReady
                        : copy.analysesNew.jobsReady(c.eligible_job_count)}
                    </span>
                  </label>
                )
              })}
            </div>
          )}

          {selectedCareer && (
            <p className="rounded-lg bg-surface-subtle p-3 text-xs leading-relaxed text-text-secondary">
              {copy.analysesNew.skillLimitNote}
            </p>
          )}

          <p className="text-xs text-text-secondary">{copy.analysesNew.sampleNote}</p>
        </section>

        <div className="sticky bottom-0 z-10 -mx-4 flex flex-col gap-2 border-t border-border-warm bg-white px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:static sm:mx-0 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:rounded-xl sm:border sm:p-5">
          <p className="text-xs text-text-secondary">{copy.analysesNew.actionRequirement}</p>

          <Button
            type="button"
            onClick={handleStartAnalysis}
            disabled={!currentCareerId || createAnalysis.isPending}
            className="h-11 w-full bg-brand px-6 text-sm font-bold text-white hover:bg-brand-hover disabled:opacity-50 sm:w-auto"
          >
            {createAnalysis.isPending
              ? copy.analysesNew.startingAnalysis
              : copy.analysesNew.startAnalysisBtn}
          </Button>
        </div>
      </main>

      <footer className="flex h-14 shrink-0 items-center justify-center border-t border-border-warm px-4 md:justify-start md:px-8">
        <p className="text-xs font-medium text-text-secondary">{copy.footerBrand}</p>
      </footer>
    </div>
  )
}
