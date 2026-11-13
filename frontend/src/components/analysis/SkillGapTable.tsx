import { useState } from 'react'
import { Check, CircleDashed, Minus, Search, X } from 'lucide-react'
import type { Classification, SkillMatch } from '@/types/api'
import { copy } from '@/lib/copy'

interface SkillGapTableProps {
  matches: SkillMatch[]
  isLoading: boolean
}

const CLASSIFICATION_ICON = {
  covered: Check,
  partial: CircleDashed,
  missing: Minus,
} as const

const CLASSIFICATION_STYLE = {
  covered: 'bg-[#E5F0E8] text-success',
  partial: 'bg-[#FBF0D9] text-warning',
  missing: 'bg-[#F8E4E1] text-danger',
} as const

export function SkillGapTable({ matches, isLoading }: SkillGapTableProps) {
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState<'all' | Classification>('all')
  const [hideReported, setHideReported] = useState(false)
  const [drawerMatch, setDrawerMatch] = useState<SkillMatch | null>(null)

  const filtered = matches.filter((m) => {
    if (filter !== 'all' && m.classification !== filter) return false
    if (hideReported && m.self_declared) return false
    if (!search.trim()) return true
    return m.skill.toLowerCase().includes(search.trim().toLowerCase())
  })

  const counts = {
    all: matches.length,
    covered: matches.filter((m) => m.classification === 'covered').length,
    partial: matches.filter((m) => m.classification === 'partial').length,
    missing: matches.filter((m) => m.classification === 'missing').length,
  }

  return (
    <div className="flex flex-col gap-4">
      {/* Toolbar */}
      <div className="flex flex-col gap-3 rounded-2xl border border-border-warm bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between">
        <div className="flex flex-1 max-w-sm items-center gap-2 rounded-full border border-border-warm bg-surface-subtle px-3 py-1.5 text-xs">
          <Search className="size-3.5 text-text-secondary" />
          <input
            type="text"
            placeholder={copy.analysesResult.skillGapSearch}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full bg-transparent outline-none placeholder:text-text-secondary"
          />
        </div>

        <div className="flex flex-wrap items-center gap-1 rounded-lg bg-surface-subtle p-1 text-xs">
          {(
            [
              ['all', copy.analysesResult.tabs.summary ? 'Semua' : 'Semua', counts.all],
              ['covered', copy.status.covered, counts.covered],
              ['partial', copy.status.partial, counts.partial],
              ['missing', copy.status.missing, counts.missing],
            ] as const
          ).map(([key, label, count]) => (
            <button
              key={key}
              type="button"
              onClick={() => setFilter(key as 'all' | Classification)}
              className={`rounded-md px-2.5 py-1 font-semibold transition-colors ${
                filter === key ? 'bg-white text-brand shadow-xs' : 'text-text-secondary'
              }`}
            >
              {label} ({count})
            </button>
          ))}
        </div>
      </div>

      {/* Toggle */}
      <label className="flex w-fit cursor-pointer items-center gap-2 text-xs text-text-secondary">
        <input
          type="checkbox"
          checked={hideReported}
          onChange={(e) => setHideReported(e.target.checked)}
          className="size-3.5 accent-[#9a0002]"
        />
        {copy.analysesResult.hideReported}
      </label>

      {isLoading ? (
        <div className="rounded-2xl border border-border-warm bg-white p-8 text-center text-xs text-text-secondary">
          Memuat data skill gap…
        </div>
      ) : filtered.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-border-warm bg-white p-8 text-center text-xs text-text-secondary">
          {copy.analysesResult.emptyGap}
        </div>
      ) : (
        <>
          {/* Desktop Table */}
          <div className="hidden overflow-hidden rounded-2xl border border-border-warm bg-white shadow-sm md:block">
            <table className="w-full text-left text-xs">
              <thead className="border-b border-border-warm bg-surface-subtle font-semibold text-text-secondary">
                <tr>
                  <th className="px-4 py-3.5">{copy.analysesResult.colSkill}</th>
                  <th className="px-4 py-3.5">{copy.analysesResult.colStatus}</th>
                  <th className="px-4 py-3.5">{copy.analysesResult.colDemand}</th>
                  <th className="px-4 py-3.5">{copy.analysesResult.colCompetency}</th>
                  <th className="px-4 py-3.5">{copy.analysesResult.colFlag}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border-warm">
                {filtered.map((m) => {
                  const Icon = CLASSIFICATION_ICON[m.classification]
                  return (
                    <tr
                      key={m.skill}
                      onClick={() => setDrawerMatch(m)}
                      className="cursor-pointer transition-colors hover:bg-surface-subtle/50"
                    >
                      <td className="px-4 py-3.5 font-bold text-text-primary">{m.skill}</td>
                      <td className="px-4 py-3.5">
                        <span
                          className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-semibold ${
                            CLASSIFICATION_STYLE[m.classification]
                          }`}
                        >
                          <Icon className="size-3" />
                          {copy.status[m.classification]}
                        </span>
                      </td>
                      <td className="px-4 py-3.5 text-text-secondary">
                        {m.demand_count > 0
                          ? `${m.demand_count} lowongan`
                          : copy.analysesResult.notInDemand}
                      </td>
                      <td className="px-4 py-3.5 text-text-secondary">
                        {m.matched_competency ? (
                          <span>
                            {m.matched_competency.competency}
                            {m.matched_competency.source_reference && (
                              <span className="block text-[11px] text-text-secondary/80">
                                {m.matched_competency.source_reference}
                                {m.matched_competency.page
                                  ? `, Hal. ${m.matched_competency.page}`
                                  : ''}
                              </span>
                            )}
                          </span>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td className="px-4 py-3.5">
                        {m.self_declared && (
                          <span className="rounded bg-brand-soft px-2 py-0.5 text-[10px] font-semibold text-brand">
                            {copy.analysesResult.reportedFlag}
                          </span>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          {/* Mobile Cards */}
          <div className="flex flex-col gap-3 md:hidden">
            {filtered.map((m) => {
              const Icon = CLASSIFICATION_ICON[m.classification]
              return (
                <button
                  key={m.skill}
                  type="button"
                  onClick={() => setDrawerMatch(m)}
                  className="flex flex-col gap-2 rounded-xl border border-border-warm bg-white p-4 text-left shadow-sm"
                >
                  <div className="flex items-start justify-between gap-2">
                    <span className="text-sm font-bold text-text-primary">{m.skill}</span>
                    <span
                      className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-semibold ${
                        CLASSIFICATION_STYLE[m.classification]
                      }`}
                    >
                      <Icon className="size-3" />
                      {copy.status[m.classification]}
                    </span>
                  </div>
                  <span className="text-[11px] text-text-secondary">
                    {m.demand_count > 0
                      ? `Diminta di ${m.demand_count} lowongan`
                      : copy.analysesResult.notInDemand}
                  </span>
                  {m.matched_competency && (
                    <span className="text-[11px] text-text-secondary">
                      {m.matched_competency.competency}
                    </span>
                  )}
                  {m.self_declared && (
                    <span className="w-fit rounded bg-brand-soft px-2 py-0.5 text-[10px] font-semibold text-brand">
                      {copy.analysesResult.reportedFlag}
                    </span>
                  )}
                </button>
              )
            })}
          </div>
        </>
      )}

      {/* Evidence Drawer */}
      {drawerMatch && (
        <div
          className="fixed inset-0 z-50 flex items-end justify-end bg-black/30 md:items-stretch"
          onClick={() => setDrawerMatch(null)}
        >
          <div
            onClick={(e) => e.stopPropagation()}
            className="flex h-[85vh] w-full flex-col gap-4 overflow-y-auto rounded-t-2xl bg-white p-6 md:h-full md:max-w-md md:rounded-none"
          >
            <div className="flex items-start justify-between gap-3">
              <div className="flex flex-col">
                <span className="text-[11px] font-semibold uppercase tracking-wide text-text-secondary">
                  {copy.analysesResult.evidenceTitle}
                </span>
                <h3 className="text-lg font-bold text-text-primary">{drawerMatch.skill}</h3>
              </div>
              <button
                type="button"
                onClick={() => setDrawerMatch(null)}
                aria-label={copy.analysesResult.close}
                className="flex size-8 items-center justify-center rounded-full hover:bg-surface-subtle"
              >
                <X className="size-4" />
              </button>
            </div>

            <span
              className={`w-fit rounded-md px-2.5 py-1 text-[11px] font-semibold ${
                CLASSIFICATION_STYLE[drawerMatch.classification]
              }`}
            >
              {copy.status[drawerMatch.classification]}
            </span>

            {drawerMatch.matched_competency && (
              <div className="flex flex-col gap-1.5 rounded-xl bg-surface-subtle p-3.5">
                <span className="text-[11px] font-bold text-text-secondary">
                  {copy.analysesResult.evidenceRps}
                </span>
                <p className="text-xs italic text-text-primary">
                  “{drawerMatch.matched_competency.evidence || drawerMatch.matched_competency.competency}”
                </p>
                <span className="text-[11px] text-text-secondary">
                  {drawerMatch.matched_competency.source_reference || '—'}
                  {drawerMatch.matched_competency.page
                    ? ` · Hal. ${drawerMatch.matched_competency.page}`
                    : ''}
                </span>
              </div>
            )}

            {drawerMatch.job_evidence.length > 0 && (
              <div className="flex flex-col gap-2">
                <span className="text-[11px] font-bold text-text-secondary">
                  {copy.analysesResult.evidenceJob}
                </span>
                {drawerMatch.job_evidence.map((je) => (
                  <div
                    key={`${je.job_posting_id}-${je.evidence}`}
                    className="flex flex-col gap-1 rounded-lg border border-border-warm p-3"
                  >
                    <span className="text-xs font-semibold text-brand">{je.company}</span>
                    <p className="text-xs italic text-text-secondary">“{je.evidence}”</p>
                  </div>
                ))}
              </div>
            )}

            {drawerMatch.reason && (
              <div className="flex flex-col gap-1.5">
                <span className="text-[11px] font-bold text-text-secondary">
                  {copy.analysesResult.evidenceReason}
                </span>
                <p className="text-xs leading-relaxed text-text-primary">{drawerMatch.reason}</p>
              </div>
            )}

            {drawerMatch.similarity_score !== null && (
              <div className="flex items-center justify-between rounded-lg bg-surface-subtle px-3 py-2 text-[11px]">
                <span className="text-text-secondary">{copy.similarityLabel}</span>
                <span className="font-semibold text-text-primary">
                  {drawerMatch.similarity_score.toFixed(2)}
                </span>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}