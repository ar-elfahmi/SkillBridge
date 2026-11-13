import { useCallback, useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { AlertCircle, ArrowLeft, RefreshCw, Sparkles } from 'lucide-react'
import { CoverageSummary } from '@/components/analysis/CoverageSummary'
import { DisclaimerBanner } from '@/components/analysis/DisclaimerBanner'
import { EvidenceDrawer } from '@/components/analysis/EvidenceDrawer'
import { LearningRecommendationList } from '@/components/analysis/LearningRecommendationList'
import { SkillGapTable } from '@/components/analysis/SkillGapTable'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
import { ApiRequestError } from '@/lib/api/client'
import { copy } from '@/lib/copy'
import {
  useAnalysis,
  useAnalysisMatches,
  useAnalysisRecommendations,
  useLogout,
  useMe,
} from '@/lib/query/hooks'
import type {
  AnalysisSummary,
  Recommendation,
  SelfDeclaredSkill,
  SkillMatch,
} from '@/types/api'

type TabId = 'summary' | 'skillGap' | 'recommendations'

const t = copy.analysisResult

const TABS: { id: TabId; label: string }[] = [
  { id: 'summary', label: t.tabs.summary },
  { id: 'skillGap', label: t.tabs.skillGap },
  { id: 'recommendations', label: t.tabs.recommendations },
]

function formatDate(iso: string | null): string | null {
  if (!iso) return null
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

/** Halaman hasil analisis: ringkasan, skill gap, dan rekomendasi belajar. */
export function AnalysesResultPage() {
  const { id: idParam } = useParams()
  const analysisId = Number(idParam)
  const navigate = useNavigate()

  const { data: user } = useMe()
  const logout = useLogout()

  const analysisQuery = useAnalysis(analysisId)
  const analysis = analysisQuery.data
  const isCompleted = analysis?.status === 'completed'

  const [tab, setTab] = useState<TabId>('summary')
  const [selectedMatch, setSelectedMatch] = useState<SkillMatch | null>(null)
  const closeEvidence = useCallback(() => setSelectedMatch(null), [])

  const matchesQuery = useAnalysisMatches(analysisId, isCompleted)
  // Rekomendasi bisa dibuat saat diakses, jadi hanya diminta ketika tab-nya dibuka.
  const recommendationsQuery = useAnalysisRecommendations(
    analysisId,
    isCompleted && tab === 'recommendations',
  )

  function handleLogout() {
    logout.mutate(undefined, {
      onSuccess: () => navigate('/login'),
    })
  }

  const header = (
    <>
      <DashboardTopbar user={user} onLogout={handleLogout} />
      <nav aria-label="Jalur navigasi" className="flex items-center gap-2 text-xs text-text-secondary md:text-sm">
        <Link to="/dashboard" className="flex items-center gap-1 font-medium transition hover:text-brand">
          <ArrowLeft className="size-3.5" aria-hidden="true" />
          {t.breadcrumbRoot}
        </Link>
        <span aria-hidden="true">/</span>
        <span className="font-semibold text-brand">{t.fallbackTitle}</span>
      </nav>
    </>
  )

  const shell = (content: ReactNode) => (
    <div className="flex min-h-svh flex-col bg-background">
      {header}
      <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4 py-6 md:px-8 md:py-8">
        {content}
      </main>
    </div>
  )

  // ID tidak valid atau analisis tidak ditemukan / bukan milik pengguna.
  const notFound =
    !Number.isInteger(analysisId) ||
    analysisId <= 0 ||
    (analysisQuery.error instanceof ApiRequestError &&
      (analysisQuery.error.status === 404 || analysisQuery.error.status === 403))

  if (notFound) {
    return shell(
      <StateCard
        title={t.notFoundTitle}
        note={t.notFoundNote}
        action={<NewAnalysisLink />}
      />,
    )
  }

  if (analysisQuery.isError) {
    return shell(
      <StateCard
        title={t.loadErrorTitle}
        note={t.loadErrorNote}
        action={
          <button
            type="button"
            onClick={() => analysisQuery.refetch()}
            className="inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white hover:bg-brand-hover"
          >
            <RefreshCw className="size-3.5" aria-hidden="true" />
            {t.retry}
          </button>
        }
      />,
    )
  }

  if (analysisQuery.isPending || !analysis) {
    return shell(
      <div className="space-y-4" role="status" aria-live="polite">
        <p className="text-sm font-semibold text-text-secondary">{t.loadingTitle}</p>
        <div className="h-40 animate-pulse rounded-2xl border border-border-warm bg-white" />
        <div className="h-64 animate-pulse rounded-2xl border border-border-warm bg-white" />
      </div>,
    )
  }

  if (analysis.status === 'queued' || analysis.status === 'processing') {
    return shell(
      <StateCard
        title={t.processingTitle}
        note={t.processingNote}
        icon={<RefreshCw className="size-6 animate-spin text-brand" aria-hidden="true" />}
      />,
    )
  }

  if (analysis.status === 'failed') {
    return shell(
      <StateCard
        title={t.failedTitle}
        note={analysis.error_message || t.failedFallback}
        action={<NewAnalysisLink />}
        icon={<AlertCircle className="size-6 text-rose-600" aria-hidden="true" />}
      />,
    )
  }

  const courseLabel = analysis.course || t.fallbackTitle
  const createdLabel = formatDate(analysis.created_at)
  const versions = analysis.model_versions
  const versionLabel = [
    versions?.judge_model,
    versions?.embedding_model,
    versions?.prompt_version && `prompt ${versions.prompt_version}`,
  ]
    .filter(Boolean)
    .join(' · ')

  return shell(
    <>
      <DisclaimerBanner />

      <div className="rounded-2xl border border-border-warm bg-white p-6 shadow-xs">
        <span className="mb-2 inline-flex items-center gap-1 rounded-full bg-brand-soft px-3 py-1 text-xs font-bold uppercase tracking-wider text-brand">
          <Sparkles className="size-3.5" aria-hidden="true" />
          {t.eyebrow}
        </span>
        <h1 className="font-serif text-2xl font-bold text-text-primary md:text-3xl">{courseLabel}</h1>
        <p className="mt-1 text-xs text-text-secondary md:text-sm">
          {analysis.career && <>Profesi target: <strong className="text-text-primary">{analysis.career}</strong></>}
          {analysis.career && createdLabel && ' · '}
          {createdLabel}
        </p>
      </div>

      <div role="tablist" aria-label="Bagian hasil analisis" className="flex gap-1 overflow-x-auto border-b border-border-warm">
        {TABS.map((item) => {
          const active = tab === item.id
          return (
            <button
              key={item.id}
              id={`tab-${item.id}`}
              type="button"
              role="tab"
              aria-selected={active}
              aria-controls={`panel-${item.id}`}
              onClick={() => setTab(item.id)}
              className={`-mb-px whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-bold transition ${
                active
                  ? 'border-brand text-brand'
                  : 'border-transparent text-text-secondary hover:text-text-primary'
              }`}
            >
              {item.label}
            </button>
          )
        })}
      </div>

      <div id="panel-summary" role="tabpanel" aria-labelledby="tab-summary" hidden={tab !== 'summary'}>
        {tab === 'summary' && (
          <SummaryPanel
            summary={analysis.summary}
            isLoading={matchesQuery.isPending}
          />
        )}
      </div>

      <div id="panel-skillGap" role="tabpanel" aria-labelledby="tab-skillGap" hidden={tab !== 'skillGap'}>
        {tab === 'skillGap' && (
          <SkillGapPanel
            matches={matchesQuery.data}
            isLoading={matchesQuery.isPending}
            isError={matchesQuery.isError}
            onRetry={() => matchesQuery.refetch()}
            onSelect={setSelectedMatch}
          />
        )}
      </div>

      <div
        id="panel-recommendations"
        role="tabpanel"
        aria-labelledby="tab-recommendations"
        hidden={tab !== 'recommendations'}
      >
        {tab === 'recommendations' && (
          <RecommendationsPanel
            data={recommendationsQuery.data?.data}
            selfDeclaredSkills={recommendationsQuery.data?.meta.self_declared_skills}
            isLoading={recommendationsQuery.isPending}
            isError={recommendationsQuery.isError}
            onRetry={() => recommendationsQuery.refetch()}
          />
        )}
      </div>

      {versionLabel && (
        <p className="text-[11px] text-text-secondary">Versi model: {versionLabel}</p>
      )}

      <EvidenceDrawer match={selectedMatch} onClose={closeEvidence} />
    </>,
  )
}

function NewAnalysisLink() {
  return (
    <Link
      to="/analyses/new"
      className="inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white hover:bg-brand-hover"
    >
      {t.newAnalysisCta}
    </Link>
  )
}

function StateCard({
  title,
  note,
  action,
  icon,
}: {
  title: string
  note: string
  action?: ReactNode
  icon?: ReactNode
}) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-2xl border border-border-warm bg-white p-10 text-center shadow-xs">
      {icon}
      <h1 className="font-serif text-xl font-bold text-text-primary">{title}</h1>
      <p className="max-w-md text-xs leading-relaxed text-text-secondary">{note}</p>
      {action && <div className="pt-2">{action}</div>}
    </div>
  )
}

function SummaryPanel({
  summary,
  isLoading,
}: {
  summary: AnalysisSummary | null
  isLoading: boolean
}) {
  if (!summary) {
    return <p className="rounded-2xl border border-dashed border-border-warm bg-white p-8 text-center text-xs text-text-secondary">{t.empty}</p>
  }
  if (isLoading) {
    return <div className="h-56 animate-pulse rounded-2xl border border-border-warm bg-white" />
  }
  return <CoverageSummary summary={summary} />
}

function SkillGapPanel({
  matches,
  isLoading,
  isError,
  onRetry,
  onSelect,
}: {
  matches: SkillMatch[] | undefined
  isLoading: boolean
  isError: boolean
  onRetry: () => void
  onSelect: (match: SkillMatch) => void
}) {
  if (isLoading) {
    return <div className="h-64 animate-pulse rounded-2xl border border-border-warm bg-white" />
  }
  if (isError || !matches) {
    return (
      <StateCard
        title={t.loadErrorTitle}
        note={t.loadErrorNote}
        action={
          <button
            type="button"
            onClick={onRetry}
            className="inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white hover:bg-brand-hover"
          >
            {t.retry}
          </button>
        }
      />
    )
  }
  if (matches.length === 0) {
    return <p className="rounded-2xl border border-dashed border-border-warm bg-white p-8 text-center text-xs text-text-secondary">{t.empty}</p>
  }
  return <SkillGapTable matches={matches} onSelect={onSelect} />
}

function RecommendationsPanel({
  data,
  selfDeclaredSkills,
  isLoading,
  isError,
  onRetry,
}: {
  data: Recommendation[] | undefined
  selfDeclaredSkills: SelfDeclaredSkill[] | undefined
  isLoading: boolean
  isError: boolean
  onRetry: () => void
}) {
  if (isLoading) {
    return <div className="h-64 animate-pulse rounded-2xl border border-border-warm bg-white" />
  }
  if (isError || !data) {
    return (
      <StateCard
        title={t.loadErrorTitle}
        note={t.loadErrorNote}
        action={
          <button
            type="button"
            onClick={onRetry}
            className="inline-flex items-center gap-1.5 rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white hover:bg-brand-hover"
          >
            {t.retry}
          </button>
        }
      />
    )
  }
  return (
    <LearningRecommendationList
      recommendations={data}
      selfDeclaredSkills={selfDeclaredSkills ?? []}
    />
  )
}
