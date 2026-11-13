import type { Recommendation, SelfDeclaredSkill } from '@/types/api'
import { copy } from '@/lib/copy'

interface LearningRecommendationListProps {
  recommendations: Recommendation[]
  selfDeclaredSkills: SelfDeclaredSkill[]
}

const PRIORITY_STYLE = {
  high: 'bg-[#F8E4E1] text-danger',
  medium: 'bg-[#FBF0D9] text-warning',
  low: 'bg-[#E5F0E8] text-success',
} as const

export function LearningRecommendationList({
  recommendations,
  selfDeclaredSkills,
}: LearningRecommendationListProps) {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-1">
        <h2 className="font-display text-xl font-normal text-text-primary md:text-2xl">
          {copy.analysesResult.recoTitle}
        </h2>
        <p className="text-xs text-text-secondary md:text-sm">
          {copy.analysesResult.recoSubtitle}
        </p>
      </div>

      {recommendations.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-border-warm bg-white p-8 text-center text-xs text-text-secondary">
          {copy.analysesResult.emptyReco}
        </div>
      ) : (
        <div className="flex flex-col gap-3">
          {recommendations.map((r) => (
            <div
              key={r.skill}
              className="flex flex-col gap-3 rounded-2xl border border-border-warm bg-white p-5 shadow-sm"
            >
              <div className="flex items-start justify-between gap-3">
                <span className="text-sm font-bold text-brand">{r.skill}</span>
                <span
                  className={`rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide ${
                    PRIORITY_STYLE[r.priority]
                  }`}
                >
                  {copy.analysesResult.priorityLabel[r.priority]}
                </span>
              </div>

              <h3 className="text-base font-bold text-text-primary">{r.title}</h3>
              <p className="text-xs leading-relaxed text-text-secondary">{r.reason}</p>

              {r.details.topics.length > 0 && (
                <div className="flex flex-col gap-1.5">
                  <span className="text-[11px] font-bold text-text-secondary">
                    {copy.analysesResult.topicsLabel}
                  </span>
                  <div className="flex flex-wrap gap-1.5">
                    {r.details.topics.map((t) => (
                      <span
                        key={t}
                        className="rounded-md bg-surface-subtle px-2.5 py-1 text-[11px] font-medium text-text-primary"
                      >
                        {t}
                      </span>
                    ))}
                  </div>
                </div>
              )}

              {r.details.learning_keywords.length > 0 && (
                <p className="text-[11px] text-text-secondary">
                  {copy.analysesResult.keywordsPrefix}{' '}
                  {r.details.learning_keywords.join(', ')}
                </p>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Self Declared Panel */}
      <div className="flex flex-col gap-3 rounded-2xl border border-border-warm bg-surface-subtle p-5">
        <span className="text-xs font-bold text-text-primary">
          {copy.analysesResult.selfDeclaredTitle}
        </span>

        {selfDeclaredSkills.length > 0 ? (
          <div className="flex flex-col gap-2">
            {selfDeclaredSkills.map((s) => (
              <div
                key={s.skill}
                className="flex items-center justify-between rounded-lg bg-white px-3 py-2 text-xs"
              >
                <span className="font-semibold text-text-primary">{s.skill}</span>
                <span className="text-[10px] text-text-secondary">{copy.status[s.classification]}</span>
              </div>
            ))}
          </div>
        ) : (
          <p className="text-[11px] text-text-secondary">
            Belum ada skill yang dilaporkan.
          </p>
        )}

        <p className="border-t border-border-warm pt-2 text-[11px] text-text-secondary">
          {copy.analysesResult.selfDeclaredNote}
        </p>
      </div>
    </div>
  )
}