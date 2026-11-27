import { useCallback, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { Link, useParams, useNavigate } from 'react-router-dom'
import { AlertCircle, ArrowLeft, Check, Clock3, Info } from 'lucide-react'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
import { ProcessPanel, type ProcessStep } from '@/components/common/ProcessPanel'
import { CoverageSummary } from '@/components/analysis/CoverageSummary'
import { SkillGapTable } from '@/components/analysis/SkillGapTable'
import { LearningRecommendationList } from '@/components/analysis/LearningRecommendationList'
import { SelfDeclaredSkillsPanel } from '@/components/analysis/SelfDeclaredSkillsPanel'
import { queryKeys } from '@/lib/query/keys'
import {
  useAddMySkill,
  useAnalysis,
  useAnalysisMatches,
  useAnalysisRecommendations,
  useLogout,
  useMe,
  useMySkills,
  useRefreshRecommendations,
  useRemoveMySkill,
} from '@/lib/query/hooks'
import { UndoToast } from '@/components/common/UndoToast'
import { formatDate, toDisplayTitle } from '@/lib/labels'
import { usePageTitle } from '@/lib/usePageTitle'
import { useStickyInset } from '@/lib/useStickyInset'
import { copy } from '@/lib/copy'
import { ApiRequestError } from '@/lib/api/client'
import type { SkillLevel } from '@/types/api'

type Tab = 'summary' | 'skillGap' | 'recommendations'

export function AnalysesResultPage() {
  usePageTitle('Hasil analisis')
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const analysisId = id ? parseInt(id, 10) : 0

  const queryClient = useQueryClient()
  const { data: user } = useMe()
  const logout = useLogout()
  const { data: analysis, isLoading, isError, error: loadError, refetch } = useAnalysis(analysisId)
  const isCompleted = analysis?.status === 'completed'

  const { data: matches = [], isLoading: isMatchesLoading } = useAnalysisMatches(
    analysisId,
    isCompleted
  )
  const expectedReco = (analysis?.summary?.partial ?? 0) + (analysis?.summary?.missing ?? 0)
  const {
    data: reco,
    isLoading: isRecoLoading,
    isWaiting: isRecoWaiting,
  } = useAnalysisRecommendations(analysisId, isCompleted, expectedReco)

  const { data: mySkills = [] } = useMySkills()
  const addSkill = useAddMySkill()
  const removeSkill = useRemoveMySkill()
  const refreshReco = useRefreshRecommendations()

  const [tab, setTab] = useState<Tab>('summary')
  // Skill pribadi berubah setelah rekomendasi dimuat → tampilkan banner perbarui.
  const [recoStale, setRecoStale] = useState(false)
  const tabsRef = useStickyInset('top')

  // Skill pribadi yang cocok dengan skill industri pada analisis ini
  const declaredSkillNames = new Set(
    matches.filter((m) => m.self_declared).map((m) => m.skill),
  )

  const [toast, setToast] = useState<{ message: string; userSkillId: number } | null>(null)
  const dismissToast = useCallback(() => setToast(null), [])

  function refetchMatches() {
    return queryClient.invalidateQueries({ queryKey: [...queryKeys.analysis(analysisId), 'matches'] })
  }

  function declareSkill(skill: string, level: SkillLevel = 'intermediate') {
    addSkill.mutate(
      { skill, level, analysis_id: analysisId },
      {
        // self_declared dihitung backend saat /matches dibaca, jadi cukup refetch.
        onSuccess: (res) => {
          void refetchMatches()
          setToast({ message: copy.analysesResult.learnedToast(skill), userSkillId: res.data.id })
        },
      }
    )
  }

  function undoDeclare() {
    if (!toast) return
    removeSkill.mutate(toast.userSkillId, { onSuccess: () => void refetchMatches() })
    setToast(null)
  }
  function handleLogout() {
    logout.mutate(undefined, { onSuccess: () => navigate('/login') })
  }

  if (isLoading) {
    return (
      <div className="flex min-h-svh flex-col bg-background">
        <DashboardTopbar user={user} onLogout={handleLogout} />
        <main className="mx-auto flex w-full max-w-5xl flex-1 items-center justify-center p-6 text-sm text-text-secondary">
          Memuat hasil analisis…
        </main>
      </div>
    )
  }

  if (isError || !analysis) {
    return (
      <div className="flex min-h-svh flex-col bg-background">
        <DashboardTopbar user={user} onLogout={handleLogout} />
        <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center gap-3 p-6 text-center">
          <p className="text-base font-semibold text-danger">
            {loadError instanceof ApiRequestError && loadError.status === 404
              ? copy.analysesResult.analysisNotFound
              : copy.analysesResult.loadFailed}
          </p>
          <div className="flex flex-wrap justify-center gap-2">
            {!(loadError instanceof ApiRequestError && loadError.status === 404) && (
              <button
                type="button"
                onClick={() => void refetch()}
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

  const statusLabel = copy.analysesResult.status[analysis.status]
  const StatusIcon =
    analysis.status === 'completed'
      ? Check
      : analysis.status === 'failed'
        ? AlertCircle
        : Clock3
  const statusClass =
    analysis.status === 'completed'
      ? 'bg-success-soft text-success'
      : analysis.status === 'failed'
        ? 'bg-danger-soft text-danger'
        : 'bg-warning-soft text-warning'

  const processSteps: ProcessStep[] = [
    { label: copy.analysesResult.process.stepQueue, state: analysis.status === 'queued' ? 'active' : 'done' },
    { label: copy.analysesResult.process.stepCompare, state: analysis.status === 'processing' ? 'active' : analysis.status === 'queued' ? 'pending' : 'done' },
    { label: copy.analysesResult.process.stepReco, state: 'pending' },
  ]

  const tabs: Array<[Tab, string]> = [
    ['summary', copy.analysesResult.tabs.summary],
    ['skillGap', copy.analysesResult.tabs.skillGap],
    ['recommendations', copy.analysesResult.tabs.recommendations],
  ]

  return (
    <div className="flex min-h-svh flex-col bg-background">
      <DashboardTopbar user={user} onLogout={handleLogout} />

      <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4 py-6 md:px-8 md:py-8">
        {/* Analysis Header */}
        <div className="flex items-start gap-3">
          <Link
            to="/dashboard"
            aria-label={copy.analysesResult.backToDashboard}
            className="flex size-11 shrink-0 items-center justify-center rounded-full border border-border-warm bg-white text-text-primary transition-colors hover:bg-surface-subtle active:bg-surface-subtle"
          >
            <ArrowLeft className="size-4" aria-hidden="true" />
          </Link>
          <div className="flex min-w-0 flex-col gap-1">
            <h1
              title={analysis.course}
              className="line-clamp-2 font-display text-2xl font-normal text-text-primary md:text-3xl"
            >
              {toDisplayTitle(analysis.course)}
            </h1>
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-text-secondary">
              <span>
                {analysis.career}
                {analysis.created_at ? ` · ${formatDate(analysis.created_at)}` : ''}
              </span>
              <span
                className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-sm font-semibold ${statusClass}`}
              >
                <StatusIcon className="size-3.5" aria-hidden="true" />
                {statusLabel}
              </span>
            </div>
          </div>
        </div>
        {/* Tabs */}
        {analysis.status === 'completed' && (<div
          ref={tabsRef}
          role="tablist"
          className="sticky top-0 z-20 -mx-4 flex items-center gap-1 border-b border-border-warm bg-background px-4 md:mx-0 md:px-0"
        >
          {tabs.map(([key, label]) => (
            <button
              key={key}
              type="button"
              role="tab"
              aria-selected={tab === key}
              onClick={() => setTab(key)}
              className={`relative min-h-11 flex-1 px-2 text-center text-sm font-semibold leading-tight transition-colors active:bg-surface-subtle sm:flex-none sm:px-4 md:text-base ${
                tab === key ? 'text-brand-strong' : 'text-text-secondary hover:text-text-primary'
              }`}
            >
              {label}
              {tab === key && (
                <span className="absolute inset-x-0 -bottom-px h-0.5 rounded-full bg-brand-accent" />
              )}
            </button>
          ))}
        </div>)}

        {analysis.status === 'failed' && analysis.error_message && (
          <div className="rounded-lg bg-danger/15 p-3 text-sm font-semibold text-danger">
            {analysis.error_message}
          </div>
        )}

        {/* Tab Content */}
        {analysis.status === 'failed' ? null : analysis.status !== 'completed' ? (
          <ProcessPanel
            title={copy.analysesResult.process.title}
            description={copy.analysesResult.process.note}
            steps={processSteps}
          >
            <Link
              to="/dashboard"
              className="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-border-warm px-4 text-sm font-semibold text-text-primary hover:bg-surface-subtle sm:w-fit"
            >
              {copy.analysesResult.backToDashboard}
            </Link>
          </ProcessPanel>
        ) : tab === 'summary' ? (
          <CoverageSummary analysis={analysis} onViewGap={() => setTab('skillGap')} />
        ) : tab === 'skillGap' ? (
          <SkillGapTable matches={matches} isLoading={isMatchesLoading} jobCount={analysis?.job_count} />
        ) : (
          <div className="flex flex-col gap-4">
            {recoStale && (
              <div className="flex flex-col gap-2 rounded-lg border border-border-warm bg-warning-soft/60 p-3 text-sm text-warning sm:flex-row sm:items-center sm:justify-between">
                <span className="flex items-center gap-2">
                  <Info className="size-4 shrink-0" aria-hidden="true" />
                  {copy.analysesResult.recoStaleBanner}
                </span>
                <button
                  type="button"
                  onClick={() =>
                    refreshReco.mutate(analysisId, { onSuccess: () => setRecoStale(false) })
                  }
                  disabled={refreshReco.isPending}
                  className="w-fit rounded-lg bg-brand px-3 py-1.5 text-sm font-bold text-white hover:bg-brand-hover disabled:opacity-50"
                >
                  {refreshReco.isPending
                    ? copy.analysesResult.recoRefreshPending
                    : copy.analysesResult.recoRefreshBtn}
                </button>
              </div>
            )}

            <LearningRecommendationList
              recommendations={reco?.data ?? []}
              onAlreadyLearned={declareSkill}
              declaredSkills={declaredSkillNames}
              isLoading={isRecoLoading || isRecoWaiting || refreshReco.isPending}
            />

            <SelfDeclaredSkillsPanel
              skills={mySkills}
              reportedFromAnalysis={reco?.meta.self_declared_skills ?? []}
              onAdd={declareSkill}
              onRemove={(id) =>
                removeSkill.mutate(id, {
                  onSuccess: () =>
                    queryClient.invalidateQueries({
                      queryKey: [...queryKeys.analysis(analysisId), 'matches'],
                    }),
                })
              }
              isAdding={addSkill.isPending}
            />
          </div>
        )}
      </main>

      <footer className="flex h-14 shrink-0 items-center justify-center border-t border-border-warm px-4 md:justify-start md:px-8">
        <p className="text-xs font-medium text-text-secondary">{copy.footerBrand}</p>
      </footer>

      {toast && (
        <UndoToast
          message={toast.message}
          actionLabel={copy.analysesResult.undo}
          onAction={undoDeclare}
          onDismiss={dismissToast}
        />
      )}
    </div>
  )
}