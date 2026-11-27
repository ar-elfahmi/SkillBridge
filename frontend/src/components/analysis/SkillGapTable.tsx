import { useEffect, useRef, useState } from 'react'
import { Check, CircleDashed, ExternalLink, Minus, Search, X } from 'lucide-react'
import type { Classification, SkillMatch } from '@/types/api'
import { copy } from '@/lib/copy'
import { formatScore, sourceReferenceLabel } from '@/lib/labels'
import { useFocusTrap } from '@/lib/useFocusTrap'

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
  covered: 'bg-success-soft text-success',
  partial: 'bg-warning-soft text-warning',
  missing: 'bg-danger-soft text-danger',
} as const

export function SkillGapTable({ matches, isLoading }: SkillGapTableProps) {
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState<'all' | Classification>('all')
  const [hideReported, setHideReported] = useState(false)
  const [drawerMatch, setDrawerMatch] = useState<SkillMatch | null>(null)
  const closeRef = useRef<HTMLButtonElement>(null)
  const triggerRef = useRef<HTMLElement | null>(null)
  const dialogRef = useRef<HTMLDivElement>(null)
  useFocusTrap(dialogRef, drawerMatch !== null)

  function openDrawer(match: SkillMatch) {
    triggerRef.current = document.activeElement as HTMLElement | null
    setDrawerMatch(match)
  }

  function closeDrawer() {
    setDrawerMatch(null)
    triggerRef.current?.focus()
  }

  useEffect(() => {
    if (!drawerMatch) return
    closeRef.current?.focus()
    function onKeyDown(e: KeyboardEvent) {
      if (e.key !== 'Escape') return
      setDrawerMatch(null)
      triggerRef.current?.focus()
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [drawerMatch])

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
      <div className="flex flex-col gap-3 rounded-xl border border-border-warm bg-white p-4 md:flex-row md:items-center md:justify-between">
        <div className="flex flex-1 max-w-sm items-center gap-2 rounded-lg border border-field bg-surface-subtle px-3 py-1.5 text-sm focus-within:ring-2 focus-within:ring-brand-accent/40">
          <Search className="size-3.5 text-text-secondary" />
          <input
            type="text"
            placeholder={copy.analysesResult.skillGapSearch}
            aria-label={copy.analysesResult.skillGapSearch}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="h-11 w-full bg-transparent outline-none placeholder:text-text-secondary"
          />
        </div>

        <div
          role="group"
          aria-label={copy.analysesResult.colStatus}
          className="-mx-1 flex snap-x items-center gap-1 overflow-x-auto rounded-lg bg-surface-subtle p-1 text-sm"
        >
          {(
            [
              ['all', 'Semua', counts.all],
              ['covered', copy.status.covered, counts.covered],
              ['partial', copy.status.partial, counts.partial],
              ['missing', copy.status.missingShort, counts.missing],
            ] as const
          ).map(([key, label, count]) => (
            <button
              key={key}
              type="button"
              onClick={() => setFilter(key as 'all' | Classification)}
              aria-pressed={filter === key}
              className={`inline-flex min-h-11 shrink-0 snap-start items-center gap-1.5 whitespace-nowrap rounded-md px-3 text-sm font-semibold transition-colors active:bg-white/70 ${
                filter === key ? 'bg-white text-brand-strong shadow-xs' : 'text-text-secondary'
              }`}
            >
              {filter === key && <Check className="size-3.5" aria-hidden="true" />}
              {label} ({count})
            </button>
          ))}
        </div>
      </div>

      {/* Toggle */}
      <label className="flex min-h-11 w-fit cursor-pointer items-center gap-2.5 text-sm text-text-secondary">
        <input
          type="checkbox"
          checked={hideReported}
          onChange={(e) => setHideReported(e.target.checked)}
          className="size-5 accent-brand-accent"
        />
        {copy.analysesResult.hideReported}
      </label>

      {isLoading ? (
        <div className="rounded-xl border border-border-warm bg-white p-8 text-center text-sm text-text-secondary">
          Memuat data skill gap…
        </div>
      ) : filtered.length === 0 ? (
        <div className="rounded-xl border border-dashed border-border-warm bg-white p-8 text-center text-sm text-text-secondary">
          {copy.analysesResult.emptyGap}
        </div>
      ) : (
        <>
          {/* Desktop Table */}
          <div className="hidden overflow-hidden rounded-xl border border-border-warm bg-white md:block">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-border-warm bg-surface-subtle font-semibold text-text-secondary">
                <tr>
                  <th className="px-4 py-4">{copy.analysesResult.colSkill}</th>
                  <th className="px-4 py-4">{copy.analysesResult.colStatus}</th>
                  <th className="px-4 py-4">{copy.analysesResult.colDemand}</th>
                  <th className="px-4 py-4">{copy.analysesResult.colCompetency}</th>
                  <th className="px-4 py-4">{copy.analysesResult.colFlag}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border-warm">
                {filtered.map((m) => {
                  const Icon = CLASSIFICATION_ICON[m.classification]
                  return (
                    <tr
                      key={m.skill}
                      onClick={() => openDrawer(m)}
                      className="cursor-pointer transition-colors hover:bg-surface-subtle/50"
                    >
                      <td className="px-4 py-1 font-bold text-text-primary">
                        <button
                          type="button"
                          onClick={(e) => {
                            e.stopPropagation()
                            openDrawer(m)
                          }}
                          className="inline-flex min-h-11 items-center text-left font-bold hover:underline"
                        >
                          {m.skill}
                        </button>
                      </td>
                      <td className="px-4 py-4">
                        <span
                          className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-sm font-semibold ${
                            CLASSIFICATION_STYLE[m.classification]
                          }`}
                        >
                          <Icon className="size-3" />
                          {copy.status[m.classification]}
                        </span>
                      </td>
                      <td className="px-4 py-4 text-text-secondary">
                        {m.demand_count > 0
                          ? `${m.demand_count} lowongan`
                          : copy.analysesResult.notInDemand}
                      </td>
                      <td className="px-4 py-4 text-text-secondary">
                        {m.matched_competency ? (
                          <span>
                            {m.matched_competency.competency}
                            {m.matched_competency.source_reference && (
                              <span className="block text-sm text-text-secondary/80">
                                {sourceReferenceLabel(m.matched_competency.source_reference, '')}
                                {m.matched_competency.page
                                  ? `, Hal. ${m.matched_competency.page}`
                                  : ''}
                              </span>
                            )}
                          </span>
                        ) : (
                          '-'
                        )}
                      </td>
                      <td className="px-4 py-4">
                        {m.self_declared && (
                          <span className="rounded bg-brand-soft px-2 py-0.5 text-sm font-semibold text-brand-strong">
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
                  onClick={() => openDrawer(m)}
                  className="flex flex-col gap-2 rounded-lg border border-border-warm bg-white p-4 text-left"
                >
                  <div className="flex items-start justify-between gap-2">
                    <span className="text-sm font-bold text-text-primary">{m.skill}</span>
                    <span
                      className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-sm font-semibold ${
                        CLASSIFICATION_STYLE[m.classification]
                      }`}
                    >
                      <Icon className="size-3" />
                      {copy.status[m.classification]}
                    </span>
                  </div>
                  <span className="text-sm text-text-secondary">
                    {m.demand_count > 0
                      ? `Diminta di ${m.demand_count} lowongan`
                      : copy.analysesResult.notInDemand}
                  </span>
                  {m.matched_competency && (
                    <span className="text-sm text-text-secondary">
                      {m.matched_competency.competency}
                    </span>
                  )}
                  {m.self_declared && (
                    <span className="w-fit rounded bg-brand-soft px-2 py-0.5 text-sm font-semibold text-brand-strong">
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
          role="dialog"
          aria-modal="true"
          aria-labelledby="skill-drawer-title"
          className="fixed inset-0 z-50 flex items-end justify-end bg-black/30 md:items-stretch"
          onClick={closeDrawer}
        >
          <div
            ref={dialogRef}
            onClick={(e) => e.stopPropagation()}
            className="flex h-[85dvh] w-full flex-col gap-4 overflow-y-auto rounded-t-2xl bg-white p-6 md:h-full md:max-w-md md:rounded-none"
          >
            <div className="flex items-start justify-between gap-3">
              <div className="flex flex-col">
                <span className="text-sm font-semibold text-text-secondary">
                  {copy.analysesResult.evidenceTitle}
                </span>
                <h2 id="skill-drawer-title" className="text-lg font-bold text-text-primary">{drawerMatch.skill}</h2>
              </div>
              <button
                type="button"
                ref={closeRef}
                onClick={closeDrawer}
                aria-label={copy.analysesResult.close}
                className="flex size-11 shrink-0 items-center justify-center rounded-full hover:bg-surface-subtle active:bg-surface-subtle sm:size-8"
              >
                <X className="size-4" aria-hidden="true" />
              </button>
            </div>

            <span
              className={`w-fit rounded-md px-2.5 py-1 text-sm font-semibold ${
                CLASSIFICATION_STYLE[drawerMatch.classification]
              }`}
            >
              {copy.status[drawerMatch.classification]}
            </span>

            {drawerMatch.matched_competency && (
              <div className="flex flex-col gap-1.5 rounded-lg bg-surface-subtle p-3.5">
                <span className="text-sm font-bold text-text-secondary">
                  {copy.analysesResult.evidenceRps}
                </span>
                <p className="text-sm italic text-text-primary">
                  “{drawerMatch.matched_competency.evidence || drawerMatch.matched_competency.competency}”
                </p>
                {(drawerMatch.matched_competency.source_reference || drawerMatch.matched_competency.page) && (
                  <span className="text-sm text-text-secondary">
                    {[
                      drawerMatch.matched_competency.source_reference
                        ? sourceReferenceLabel(drawerMatch.matched_competency.source_reference, '')
                        : null,
                      drawerMatch.matched_competency.page
                        ? `Hal. ${drawerMatch.matched_competency.page}`
                        : null,
                    ]
                      .filter(Boolean)
                      .join(' · ')}
                  </span>
                )}
              </div>
            )}

            {drawerMatch.job_evidence.length > 0 && (
              <div className="flex flex-col gap-2">
                <span className="text-sm font-bold text-text-secondary">
                  {copy.analysesResult.evidenceJob}
                </span>
                {drawerMatch.job_evidence.map((je) => (
                  <div
                    key={je.job_posting_id}
                    className="flex flex-col gap-1 rounded-lg border border-border-warm p-3"
                  >
                    {je.title && (
                      <span className="text-sm font-semibold text-text-primary">{je.title}</span>
                    )}
                    <span className="text-sm font-semibold text-brand-strong">{je.company}</span>
                    {je.evidence && (
                      <p className="text-sm italic text-text-secondary">“{je.evidence}”</p>
                    )}
                    {je.source_url && (
                      <a
                        href={je.source_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex min-h-11 w-fit items-center gap-1 text-sm font-semibold text-brand-strong underline"
                      >
                        {copy.analysesResult.evidenceJobOpen}
                        {je.source_name ? ` (${je.source_name})` : ''}
                        <ExternalLink className="size-3" aria-hidden="true" />
                        <span className="sr-only">{copy.analysesResult.newTab}</span>
                      </a>
                    )}
                  </div>
                ))}
                {drawerMatch.job_evidence_total > drawerMatch.job_evidence.length && (
                  <span className="text-sm text-text-secondary">
                    {copy.analysesResult.evidenceJobMore(
                      drawerMatch.job_evidence_total - drawerMatch.job_evidence.length
                    )}
                  </span>
                )}
              </div>
            )}

            {drawerMatch.reason && (
              <div className="flex flex-col gap-1.5">
                <span className="text-sm font-bold text-text-secondary">
                  {copy.analysesResult.evidenceReason}
                </span>
                <p className="text-sm leading-relaxed text-text-primary">{drawerMatch.reason}</p>
              </div>
            )}

            {drawerMatch.similarity_score !== null && (
              <div className="flex items-center justify-between rounded-lg bg-surface-subtle px-3 py-2 text-sm">
                <span className="text-text-secondary">{copy.similarityLabel}</span>
                <span className="font-semibold text-text-primary">
                  {formatScore(drawerMatch.similarity_score)}
                </span>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}