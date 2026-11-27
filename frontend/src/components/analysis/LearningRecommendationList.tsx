import { Loader2 } from 'lucide-react'
import type { Recommendation } from '@/types/api'
import { copy } from '@/lib/copy'

interface LearningRecommendationListProps {
  recommendations: Recommendation[]
  onAlreadyLearned: (skill: string) => void
  declaredSkills: Set<string>
  isLoading?: boolean
}

const PRIORITY_STYLE = {
  high: 'bg-danger-soft text-danger',
  medium: 'bg-warning-soft text-warning',
  low: 'bg-success-soft text-success',
} as const

export function LearningRecommendationList({
  recommendations,
  onAlreadyLearned,
  declaredSkills,
  isLoading = false,
}: LearningRecommendationListProps) {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-1">
        <h2 className="font-display text-xl font-normal text-text-primary md:text-2xl">
          {copy.analysesResult.recoTitle}
        </h2>
        <p className="text-sm text-text-secondary">
          {copy.analysesResult.recoSubtitle}
        </p>
      </div>

      {isLoading ? (
        <div
          role="status"
          aria-live="polite"
          className="flex items-center justify-center gap-2.5 rounded-xl border border-border-warm bg-white p-8 text-sm text-text-secondary"
        >
          <Loader2 className="size-4 shrink-0 animate-spin text-brand-accent" aria-hidden="true" />
          <span>{copy.analysesResult.recoLoading}</span>
        </div>
      ) : recommendations.length === 0 ? (
        <div className="rounded-xl border border-dashed border-border-warm bg-white p-8 text-center text-sm text-text-secondary">
          {copy.analysesResult.emptyReco}
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
          {recommendations.map((r) => (
            <div
              key={r.skill}
              className={`flex flex-col gap-3 rounded-xl border bg-white p-4 transition-colors sm:p-5 ${
                declaredSkills.has(r.skill)
                  ? 'border-success'
                  : 'border-border-warm'
              }`}
            >
              <div className="flex items-start justify-between gap-3">
                <span className="text-sm font-bold text-brand-strong">{r.skill}</span>
                <span
                  className={`rounded-full px-2.5 py-0.5 text-xs font-bold ${
                    PRIORITY_STYLE[r.priority]
                  }`}
                >
                  {copy.analysesResult.priorityLabel[r.priority]}
                </span>
              </div>

              <h3 className="text-lg font-bold leading-snug text-text-primary">{r.title}</h3>
              <p className="text-sm leading-relaxed text-text-primary">{r.reason}</p>

              {r.details.topics.length > 0 && (
                <div className="flex flex-col gap-1.5">
                  <span className="text-xs font-bold text-text-secondary">
                    {copy.analysesResult.topicsLabel}
                  </span>
                  <div className="flex flex-wrap gap-1.5">
                    {r.details.topics.map((t) => (
                      <span
                        key={t}
                        className="rounded-md bg-surface-subtle px-2.5 py-1 text-sm font-medium text-text-primary"
                      >
                        {t}
                      </span>
                    ))}
                  </div>
                </div>
              )}

              {r.details.learning_keywords.length > 0 && (
                <p className="text-sm text-text-secondary">
                  {copy.analysesResult.keywordsPrefix}{' '}
                  {r.details.learning_keywords.join(', ')}
                </p>
              )}

              <button
                type="button"
                onClick={() => onAlreadyLearned(r.skill)}
                disabled={declaredSkills.has(r.skill)}
                className={`min-h-11 w-fit rounded-lg border px-3 text-sm font-semibold transition-colors active:bg-surface-subtle sm:min-h-9 ${
                  declaredSkills.has(r.skill)
                    ? 'cursor-default border-success/40 bg-success/10 text-success'
                    : 'border-border-warm text-text-primary hover:border-brand hover:text-brand-strong'
                }`}
              >
                {copy.analysesResult.alreadyLearned}
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}