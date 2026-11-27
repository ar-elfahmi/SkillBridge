import { useState } from 'react'
import { useSearchParams, useNavigate, Link, Navigate } from 'react-router-dom'
import { ArrowLeft, Check, ChevronDown } from 'lucide-react'
import { AiQuotaBanner } from '@/components/common/AiQuotaBanner'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
import { CareerSkillsDialog } from '@/components/industry/CareerSkillsDialog'
import { Button } from '@/components/ui/button'
import {
  useCareerSuggestions,
  useCareersList,
  useCreateAnalysis,
  useLogout,
  useMe,
  useRpsDetail,
} from '@/lib/query/hooks'
import { usePageTitle } from '@/lib/usePageTitle'
import { copy } from '@/lib/copy'
import type { Career } from '@/types/api'

interface CareerCardProps {
  career: Career
  isSelected: boolean
  reason?: string
  onSelect: () => void
}

function CareerCard({ career, isSelected, reason, onSelect }: CareerCardProps) {
  const isDisabled = career.eligible_job_count === 0
  return (
    <label
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
        value={career.id}
        checked={isSelected}
        disabled={isDisabled}
        onChange={onSelect}
        className="sr-only"
      />
      <span className="flex items-start justify-between gap-2">
        <span className="text-base font-bold text-text-primary">{career.name}</span>
        {isSelected && (
          <span className="inline-flex shrink-0 items-center gap-1 rounded-md bg-brand px-2 py-0.5 text-sm font-semibold text-white">
            <Check className="size-3" aria-hidden="true" />
            {copy.analysesNew.selectedBadge}
          </span>
        )}
      </span>
      {career.description && (
        <span
          className={`text-sm leading-relaxed text-text-secondary ${isSelected ? '' : 'line-clamp-2'}`}
        >
          {career.description}
        </span>
      )}
      {reason && (
        <span className="text-sm leading-relaxed text-text-primary">
          <span className="font-semibold">{copy.analysesNew.suggestionReasonLabel}</span> {reason}
        </span>
      )}
      <span className="text-sm font-semibold text-text-primary">
        {isDisabled
          ? copy.analysesNew.noJobsReady
          : copy.analysesNew.jobsReady(career.eligible_job_count)}
      </span>
    </label>
  )
}

export function AnalysesNewPage() {
  usePageTitle('Pemetaan industri')
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const rpsParam = searchParams.get('rps') ?? ''
  const rpsId = /^\d+$/.test(rpsParam) ? parseInt(rpsParam, 10) : 0

  const { data: user } = useMe()
  const logout = useLogout()
  const { data: document, isError: isRpsError } = useRpsDetail(rpsId)
  const { data: careers = [], isLoading: isCareersLoading, isError: isCareersError } =
    useCareersList()

  const { data: suggestions } = useCareerSuggestions(rpsId)

  const [selectedCareerId, setSelectedCareerId] = useState<number | null>(null)
  const [othersOpen, setOthersOpen] = useState<boolean | null>(null)
  const [careerQuery, setCareerQuery] = useState('')
  const [skillsOpen, setSkillsOpen] = useState(false)

  // Suggested professions come first, in the order the server ranked them.
  const suggestionByCareer = new Map((suggestions?.items ?? []).map((s, i) => [s.career_id, { ...s, rank: i + 1 }]))
  const orderedCareers = [...careers].sort(
    (a, b) =>
      (suggestionByCareer.get(a.id)?.rank ?? Infinity) - (suggestionByCareer.get(b.id)?.rank ?? Infinity)
  )
  const suggestedCareers = orderedCareers.filter((c) => suggestionByCareer.has(c.id))
  const otherCareers = orderedCareers.filter((c) => !suggestionByCareer.has(c.id))

  // Default: best suggestion that has ready job postings, else the first profession that has them.
  const defaultCareerId = orderedCareers.find((c) => c.eligible_job_count > 0)?.id ?? null
  const currentCareerId = selectedCareerId ?? defaultCareerId
  const selectedCareer = careers.find((c) => c.id === currentCareerId)

  const selectedIsOther = otherCareers.some((c) => c.id === currentCareerId)
  const othersExpanded = othersOpen ?? (suggestedCareers.length === 0 || selectedIsOther)
  const query = careerQuery.trim().toLowerCase()
  const visibleOthers = query
    ? otherCareers.filter((c) => c.name.toLowerCase().includes(query))
    : otherCareers

  const createAnalysis = useCreateAnalysis()
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  function handleLogout() {
    logout.mutate(undefined, {
      onSuccess: () => navigate('/login'),
    })
  }

  if (!rpsId) {
    return <Navigate to="/dashboard" replace state={{ notice: copy.analysesNew.needRps }} />
  }
  if (isRpsError) {
    return <Navigate to="/dashboard" replace state={{ notice: copy.analysesNew.rpsNotFound }} />
  }

  function handleStartAnalysis() {
    setErrorMsg(null)
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
    ''
  const courseCode =
    document?.course?.code || document?.detected_metadata?.code || ''

  return (
    <div className="flex min-h-svh flex-col bg-background">
      <DashboardTopbar user={user} onLogout={handleLogout} />

      <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4 py-6 md:px-8 md:py-8">
        <div className="flex flex-col gap-3 border-b border-border-warm pb-5">
          <div className="flex items-center gap-3">
            <Link
              to={`/rps/${rpsId}`}
              className="flex size-11 shrink-0 items-center justify-center rounded-full border border-border-warm bg-white text-text-primary hover:bg-surface-subtle active:bg-surface-subtle sm:size-9"
              aria-label="Kembali"
            >
              <ArrowLeft className="size-4" />
            </Link>
            <div className="flex flex-col">
              <h1 className="font-display text-xl font-normal text-text-primary md:text-3xl">
                {copy.analysesNew.pageTitle}
              </h1>
              <span className="text-sm text-text-secondary">
                {courseDisplayName}
                {courseCode ? ` · ${courseCode}` : ''}
              </span>
            </div>
          </div>
        </div>

        <AiQuotaBanner />

        {errorMsg && (
          <div role="alert" className="rounded-lg bg-danger/15 p-3 text-sm font-semibold text-danger">
            {errorMsg}
          </div>
        )}

        <section className="flex flex-col gap-5 rounded-xl border border-border-warm bg-white p-4 sm:p-6">
          <div className="flex flex-col gap-1">
            <h2 className="text-base font-bold text-text-primary md:text-lg">
              {copy.analysesNew.step1Title}
            </h2>
            <p className="text-sm text-text-secondary">{copy.analysesNew.step1Subtitle}</p>
          </div>

          {suggestions?.status === 'processing' && (
            <p role="status" className="rounded-lg bg-surface-subtle p-3 text-sm text-text-secondary">
              {copy.analysesNew.suggestionsLoading}
            </p>
          )}

          {isCareersLoading ? (
            <div className="p-8 text-center text-sm text-text-secondary">
              {copy.analysesNew.careersLoading}
            </div>
          ) : isCareersError ? (
            <div role="alert" className="rounded-lg bg-danger/15 p-4 text-sm font-semibold text-danger">
              {copy.analysesNew.careersError}
            </div>
          ) : careers.length === 0 ? (
            <div className="rounded-lg border border-dashed border-border-warm p-6 text-center text-sm text-text-secondary">
              {copy.analysesNew.careersEmpty}
            </div>
          ) : (
            <div role="radiogroup" aria-label={copy.analysesNew.step1Title} className="flex flex-col gap-5">
              {suggestedCareers.length > 0 && (
                <div className="flex flex-col gap-3">
                  <div className="flex flex-col gap-0.5">
                    <h3 className="text-sm font-bold text-text-primary">
                      {copy.analysesNew.suggestedGroup}
                    </h3>
                    <p className="text-sm text-text-secondary">
                      {copy.analysesNew.suggestedGroupNote}
                    </p>
                  </div>
                  <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                    {suggestedCareers.map((c) => (
                      <CareerCard
                        key={c.id}
                        career={c}
                        isSelected={c.id === currentCareerId}
                        reason={suggestionByCareer.get(c.id)?.reason}
                        onSelect={() => setSelectedCareerId(c.id)}
                      />
                    ))}
                  </div>
                </div>
              )}

              {otherCareers.length > 0 && (
                <div className="flex flex-col gap-3">
                  {suggestedCareers.length > 0 ? (
                    <button
                      type="button"
                      onClick={() => setOthersOpen(!othersExpanded)}
                      aria-expanded={othersExpanded}
                      className="flex min-h-11 w-full items-center justify-between gap-2 rounded-lg border border-border-warm px-4 text-left text-sm font-bold text-text-primary hover:bg-surface-subtle"
                    >
                      <span>
                        {copy.analysesNew.otherGroup} ({otherCareers.length})
                      </span>
                      <ChevronDown
                        className={`size-4 transition-transform ${othersExpanded ? 'rotate-180' : ''}`}
                        aria-hidden="true"
                      />
                    </button>
                  ) : (
                    <h3 className="text-sm font-bold text-text-primary">{copy.analysesNew.otherGroup}</h3>
                  )}

                  {othersExpanded && (
                    <>
                      <input
                        type="search"
                        value={careerQuery}
                        onChange={(e) => setCareerQuery(e.target.value)}
                        aria-label={copy.analysesNew.careerSearchLabel}
                        placeholder={copy.analysesNew.careerSearchLabel}
                        className="h-11 w-full rounded-lg border border-field bg-surface-canvas px-3 text-base outline-none placeholder:text-text-secondary focus:ring-2 focus:ring-brand-accent/40 md:max-w-sm md:text-sm"
                      />
                      {visibleOthers.length === 0 ? (
                        <p className="text-sm text-text-secondary">
                          {copy.analysesNew.careerSearchEmpty}
                        </p>
                      ) : (
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                          {visibleOthers.map((c) => (
                            <CareerCard
                              key={c.id}
                              career={c}
                              isSelected={c.id === currentCareerId}
                              onSelect={() => setSelectedCareerId(c.id)}
                            />
                          ))}
                        </div>
                      )}
                    </>
                  )}
                </div>
              )}
            </div>
          )}
        </section>

        {selectedCareer && (
          <div className="flex flex-col items-start gap-2 text-sm leading-relaxed text-text-secondary">
            <p>{copy.analysesNew.skillLimitNote}</p>
            <p>{copy.analysesNew.sampleNote}</p>
            <button
              type="button"
              onClick={() => setSkillsOpen(true)}
              className="min-h-11 font-semibold text-brand-strong underline"
            >
              {copy.analysesNew.viewSkills}
            </button>
          </div>
        )}

        <div className="sticky bottom-0 z-10 -mx-4 flex flex-col gap-2 border-t border-border-warm bg-white px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:static sm:mx-0 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:rounded-xl sm:border sm:p-5">
          <p className="text-sm font-semibold text-text-primary">
            {selectedCareer
              ? copy.analysesNew.selectedSummary(selectedCareer.name, selectedCareer.eligible_job_count)
              : copy.analysesNew.actionRequirement}
          </p>

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

      {skillsOpen && selectedCareer && (
        <CareerSkillsDialog
          careerId={selectedCareer.id}
          careerName={selectedCareer.name}
          onClose={() => setSkillsOpen(false)}
        />
      )}
    </div>
  )
}
