import { useMemo, useState } from 'react'
import { ChevronRight } from 'lucide-react'
import { ClassificationBadge } from '@/components/analysis/ClassificationBadge'
import { copy } from '@/lib/copy'
import type { Classification, SkillMatch } from '@/types/api'

type FilterValue = Classification | 'all'

interface SkillGapTableProps {
  matches: SkillMatch[]
  onSelect: (match: SkillMatch) => void
}

const t = copy.analysisResult.skillGap

/** Urutan tampilan: yang belum ditemukan dahulu, lalu sebagian, lalu tercakup. */
const CLASSIFICATION_ORDER: Record<Classification, number> = {
  missing: 0,
  partial: 1,
  covered: 2,
}

const FILTERS: { value: FilterValue; label: string }[] = [
  { value: 'all', label: t.filterAll },
  { value: 'covered', label: copy.status.covered },
  { value: 'partial', label: copy.status.partial },
  { value: 'missing', label: copy.status.missing },
]

/** Tabel skill gap (desktop) dan daftar kartu (mobile) dengan filter status. */
export function SkillGapTable({ matches, onSelect }: SkillGapTableProps) {
  const [filter, setFilter] = useState<FilterValue>('all')

  const rows = useMemo(() => {
    const filtered = filter === 'all' ? matches : matches.filter((m) => m.classification === filter)
    return [...filtered].sort(
      (a, b) =>
        CLASSIFICATION_ORDER[a.classification] - CLASSIFICATION_ORDER[b.classification] ||
        b.demand_count - a.demand_count,
    )
  }, [matches, filter])

  return (
    <section aria-labelledby="skill-gap-title" className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 id="skill-gap-title" className="text-base font-bold text-text-primary">
          {t.title}
        </h2>
        <div role="group" aria-label={t.filterLabel} className="flex flex-wrap gap-1.5">
          {FILTERS.map((option) => {
            const active = filter === option.value
            return (
              <button
                key={option.value}
                type="button"
                aria-pressed={active}
                onClick={() => setFilter(option.value)}
                className={`rounded-xl border px-3 py-1.5 text-xs font-bold transition ${
                  active
                    ? 'border-brand bg-brand-soft text-brand'
                    : 'border-border-warm bg-white text-text-secondary hover:bg-surface-canvas'
                }`}
              >
                {option.label}
              </button>
            )
          })}
        </div>
      </div>

      {rows.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-border-warm bg-white p-8 text-center text-xs text-text-secondary">
          {t.noMatches}
        </p>
      ) : (
        <>
          {/* Desktop: tabel */}
          <div className="hidden overflow-hidden rounded-2xl border border-border-warm bg-white shadow-xs md:block">
            <table className="w-full text-left text-sm">
              <thead className="bg-surface-canvas text-[11px] font-bold uppercase tracking-wider text-text-secondary">
                <tr>
                  <th scope="col" className="px-4 py-3">{t.columnSkill}</th>
                  <th scope="col" className="px-4 py-3">{t.columnStatus}</th>
                  <th scope="col" className="px-4 py-3">{t.columnDemand}</th>
                  <th scope="col" className="px-4 py-3">{t.columnCompetency}</th>
                  <th scope="col" className="px-4 py-3">
                    <span className="sr-only">{t.viewEvidence}</span>
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border-warm">
                {rows.map((match) => (
                  <tr key={match.skill} className="transition hover:bg-surface-canvas/60">
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="font-semibold text-text-primary">{match.skill}</span>
                        {match.self_declared && (
                          <span className="rounded-md border border-sky-200 bg-sky-50 px-1.5 py-0.5 text-[10px] font-bold text-sky-800">
                            {t.selfDeclaredBadge}
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <ClassificationBadge classification={match.classification} />
                    </td>
                    <td className="px-4 py-3 text-xs text-text-secondary">
                      {t.demandValue(match.demand_count)}
                    </td>
                    <td className="max-w-xs px-4 py-3 text-xs text-text-secondary">
                      {match.matched_competency ? (
                        <span className="line-clamp-2">{match.matched_competency.competency}</span>
                      ) : (
                        <span className="italic">{t.noCompetency}</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <button
                        type="button"
                        onClick={() => onSelect(match)}
                        className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-brand hover:bg-brand-soft"
                      >
                        {t.viewEvidence}
                        <ChevronRight className="size-3.5" aria-hidden="true" />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Mobile: kartu */}
          <ul className="space-y-3 md:hidden">
            {rows.map((match) => (
              <li key={match.skill} className="rounded-2xl border border-border-warm bg-white p-4 shadow-xs">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0 space-y-1.5">
                    <p className="font-semibold text-text-primary break-words">{match.skill}</p>
                    <div className="flex flex-wrap items-center gap-2">
                      <ClassificationBadge classification={match.classification} />
                      {match.self_declared && (
                        <span className="rounded-md border border-sky-200 bg-sky-50 px-1.5 py-0.5 text-[10px] font-bold text-sky-800">
                          {t.selfDeclaredBadge}
                        </span>
                      )}
                    </div>
                  </div>
                  <span className="shrink-0 text-[11px] font-semibold text-text-secondary">
                    {t.demandValue(match.demand_count)}
                  </span>
                </div>
                <p className="mt-3 text-xs text-text-secondary">
                  {match.matched_competency ? match.matched_competency.competency : t.noCompetency}
                </p>
                <button
                  type="button"
                  onClick={() => onSelect(match)}
                  className="mt-3 inline-flex w-full items-center justify-center gap-1 rounded-xl border border-border-warm bg-surface-canvas px-3 py-2 text-xs font-bold text-text-primary hover:bg-white"
                >
                  {t.viewEvidence}
                  <ChevronRight className="size-3.5" aria-hidden="true" />
                </button>
              </li>
            ))}
          </ul>
        </>
      )}
    </section>
  )
}
