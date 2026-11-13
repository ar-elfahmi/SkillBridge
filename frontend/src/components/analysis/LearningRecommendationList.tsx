import { Lightbulb, Tag, UserCheck } from 'lucide-react'
import { copy } from '@/lib/copy'
import type { Recommendation, RecommendationPriority, SelfDeclaredSkill } from '@/types/api'

interface LearningRecommendationListProps {
  recommendations: Recommendation[]
  selfDeclaredSkills: SelfDeclaredSkill[]
}

const t = copy.analysisResult.recommendations

const PRIORITY_STYLES: Record<RecommendationPriority, string> = {
  high: 'border-rose-200 bg-rose-50 text-rose-800',
  medium: 'border-amber-200 bg-amber-50 text-amber-800',
  low: 'border-sky-200 bg-sky-50 text-sky-800',
}

/** Kartu rekomendasi belajar. Tidak memuat tautan sumber belajar (CLAUDE.md aturan 11). */
export function LearningRecommendationList({
  recommendations,
  selfDeclaredSkills,
}: LearningRecommendationListProps) {
  return (
    <section aria-labelledby="recommendations-title" className="space-y-5">
      <div className="space-y-1">
        <h2 id="recommendations-title" className="flex items-center gap-2 text-base font-bold text-text-primary">
          <Lightbulb className="size-4 text-brand" aria-hidden="true" />
          {t.title}
        </h2>
        <p className="text-xs leading-relaxed text-text-secondary">{t.note}</p>
      </div>

      {recommendations.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-border-warm bg-white p-8 text-center text-xs text-text-secondary">
          {t.empty}
        </p>
      ) : (
        <ul className="grid gap-4 md:grid-cols-2">
          {recommendations.map((rec) => (
            <li
              key={`${rec.skill}-${rec.title}`}
              className="flex flex-col gap-3 rounded-2xl border border-border-warm bg-white p-5 shadow-xs"
            >
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-[11px] font-bold uppercase tracking-wider text-text-secondary">
                  {rec.skill}
                </span>
                <span
                  className={`rounded-full border px-2 py-0.5 text-[11px] font-bold ${PRIORITY_STYLES[rec.priority]}`}
                >
                  {t.priority[rec.priority]}
                </span>
              </div>

              <h3 className="font-serif text-lg font-bold text-text-primary">{rec.title}</h3>
              <p className="text-xs leading-relaxed text-text-secondary">{rec.reason}</p>

              {rec.details.topics.length > 0 && (
                <div className="space-y-1.5">
                  <p className="text-[11px] font-bold text-text-primary">{t.topics}</p>
                  <ul className="flex flex-wrap gap-1.5">
                    {rec.details.topics.map((topic) => (
                      <li
                        key={topic}
                        className="rounded-md border border-border-warm bg-surface-canvas px-2 py-0.5 text-[11px] text-text-secondary"
                      >
                        {topic}
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              {rec.details.learning_keywords.length > 0 && (
                <div className="space-y-1.5">
                  <p className="flex items-center gap-1 text-[11px] font-bold text-text-primary">
                    <Tag className="size-3" aria-hidden="true" />
                    {t.keywords}
                  </p>
                  <ul className="flex flex-wrap gap-1.5">
                    {rec.details.learning_keywords.map((keyword) => (
                      <li key={keyword} className="rounded-md bg-brand-soft/40 px-2 py-0.5 text-[11px] font-medium text-brand">
                        {keyword}
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}

      {selfDeclaredSkills.length > 0 && (
        <div className="space-y-2 rounded-2xl border border-sky-200 bg-sky-50/60 p-5">
          <h3 className="flex items-center gap-1.5 text-xs font-bold text-sky-900">
            <UserCheck className="size-3.5" aria-hidden="true" />
            {t.selfDeclaredTitle}
          </h3>
          <ul className="flex flex-wrap gap-1.5">
            {selfDeclaredSkills.map((item) => (
              <li
                key={item.skill}
                className="rounded-md border border-sky-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-sky-900"
              >
                {item.skill}
              </li>
            ))}
          </ul>
          <p className="text-[11px] text-sky-900/80">{t.selfDeclaredNote}</p>
        </div>
      )}
    </section>
  )
}
