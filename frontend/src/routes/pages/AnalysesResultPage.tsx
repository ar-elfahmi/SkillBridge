import { useState } from 'react'
import { Link, useParams, useNavigate } from 'react-router-dom'
import { AlertCircle, ArrowLeft, Check, Clock3, RefreshCw } from 'lucide-react'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
import { CoverageSummary } from '@/components/analysis/CoverageSummary'
import { SkillGapTable } from '@/components/analysis/SkillGapTable'
import { LearningRecommendationList } from '@/components/analysis/LearningRecommendationList'
import {
  useAnalysis,
  useAnalysisMatches,
  useAnalysisRecommendations,
  useLogout,
  useMe,
} from '@/lib/query/hooks'
import { copy } from '@/lib/copy'

type Tab = 'summary' | 'skillGap' | 'recommendations'

export function AnalysesResultPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const analysisId = id ? parseInt(id, 10) : 0

  const { data: user } = useMe()
  const logout = useLogout()
  const { data: analysis, isLoading, isError } = useAnalysis(analysisId)
  const isCompleted = analysis?.status === 'completed'

  const { data: matches = [], isLoading: isMatchesLoading } = useAnalysisMatches(
    analysisId,
    isCompleted
  )
  const { data: reco } = useAnalysisRecommendations(analysisId, isCompleted)

  const [tab, setTab] = useState<Tab>('summary')

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
            Analisis tidak ditemukan atau gagal dimuat.
          </p>
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

  const statusLabel = copy.analysesResult.status[analysis.status]
  const StatusIcon =
    analysis.status === 'completed'
      ? Check
      : analysis.status === 'failed'
        ? AlertCircle
        : Clock3
  const statusClass =
    analysis.status === 'completed'
      ? 'bg-[#E5F0E8] text-success'
      : analysis.status === 'failed'
        ? 'bg-[#F8E4E1] text-danger'
        : 'bg-[#FBF0D9] text-warning'

  const tabs: Array<[Tab, string]> = [
    ['summary', copy.analysesResult.tabs.summary],
    ['skillGap', copy.analysesResult.tabs.skillGap],
    ['recommendations', copy.analysesResult.tabs.recommendations],
  ]

  return (
    <div className="flex min-h-svh flex-col bg-background">
      <DashboardTopbar user={user} onLogout={handleLogout} />

      <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4 py-6 md:px-8 md:py-8">
        {/* Tabs */}
        <div className="flex items-center gap-1 border-b border-border-warm">
          {tabs.map(([key, label]) => (
            <button
              key={key}
              type="button"
              onClick={() => setTab(key)}
              className={`relative px-4 py-2.5 text-xs font-semibold transition-colors md:text-sm ${
                tab === key ? 'text-brand' : 'text-text-secondary hover:text-text-primary'
              }`}
            >
              {label}
              {tab === key && (
                <span className="absolute inset-x-0 -bottom-px h-0.5 rounded-full bg-brand" />
              )}
            </button>
          ))}
        </div>

        {/* Analysis Header */}
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div className="flex items-center gap-3">
            <Link
              to="/dashboard"
              aria-label={copy.analysesResult.backToDashboard}
              className="flex size-10 shrink-0 items-center justify-center rounded-full border border-border-warm bg-white text-text-primary transition-colors hover:bg-surface-subtle"
            >
              <ArrowLeft className="size-4" aria-hidden="true" />
            </Link>
            <div className="flex flex-col gap-1">
              <h1 className="text-base font-bold text-text-primary md:text-xl">
                {analysis.course}
              </h1>
              <span className="text-xs text-text-secondary">
                {analysis.career}
                {analysis.created_at
                  ? ` · ${new Date(analysis.created_at).toLocaleDateString('id-ID', {
                      day: 'numeric',
                      month: 'short',
                      year: 'numeric',
                    })}`
                  : ''}
              </span>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <span
              className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${statusClass}`}
            >
              <StatusIcon className="size-3.5" />
              {statusLabel}
            </span>

            <button
              type="button"
              onClick={() => navigate(0)}
              className="flex items-center gap-1.5 rounded-lg border border-border-warm px-3 py-1.5 text-xs font-semibold text-text-primary hover:bg-surface-subtle"
            >
              <RefreshCw className="size-3.5" />
              {copy.analysesResult.refresh}
            </button>
          </div>
        </div>

        {analysis.status === 'failed' && analysis.error_message && (
          <div className="rounded-xl bg-danger/15 p-3 text-xs font-semibold text-danger">
            {analysis.error_message}
          </div>
        )}

        {/* Tab Content */}
        {analysis.status !== 'completed' ? (
          <div className="rounded-2xl border border-dashed border-border-warm bg-white p-8 text-center text-xs text-text-secondary">
            Analisis masih diproses. Halaman ini akan memperbarui otomatis.
          </div>
        ) : tab === 'summary' ? (
          <CoverageSummary analysis={analysis} />
        ) : tab === 'skillGap' ? (
          <SkillGapTable matches={matches} isLoading={isMatchesLoading} />
        ) : (
          <LearningRecommendationList
            recommendations={reco?.data ?? []}
            selfDeclaredSkills={reco?.meta.self_declared_skills ?? []}
          />
        )}
      </main>

      <footer className="flex h-14 shrink-0 items-center justify-center border-t border-border-warm px-4 md:justify-start md:px-8">
        <p className="text-[11px] font-medium text-text-secondary">{copy.footerBrand}</p>
      </footer>
    </div>
  )
}