import { useEffect, useRef, useState } from 'react'
import { Check, CircleDashed, Minus, Search, SlidersHorizontal, X } from 'lucide-react'
import type { Classification, SkillMatch } from '@/types/api'
import { EvidenceJobList } from '@/components/analysis/EvidenceJobList'
import { ModalDialog } from '@/components/common/ModalDialog'
import { Button } from '@/components/ui/button'
import { copy } from '@/lib/copy'
import { formatScore, sourceReferenceLabel } from '@/lib/labels'
import { useFocusTrap } from '@/lib/useFocusTrap'
import { useIsMobile } from '@/lib/useMediaQuery'

interface SkillGapTableProps {
  matches: SkillMatch[]
  isLoading: boolean
}

type Filter = 'all' | Classification

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

/** Batang kecil permintaan, relatif terhadap skill yang paling sering diminta. */
function DemandBar({ value, max }: { value: number; max: number }) {
  if (value <= 0 || max <= 0) return null
  return (
    <span aria-hidden="true" className="block h-1.5 w-full max-w-40 overflow-hidden rounded-full bg-progress-track">
      <span
        className="block h-full rounded-full bg-brand-accent"
        style={{ width: `${Math.max(4, Math.round((value / max) * 100))}%` }}
      />
    </span>
  )
}

function WeakMatch() {
  return (
    <span className="inline-flex w-fit items-center gap-1 rounded-md bg-surface-subtle px-2 py-0.5 text-sm font-semibold text-text-primary">
      <CircleDashed className="size-3" aria-hidden="true" />
      {copy.analysesResult.weakMatch}
    </span>
  )
}

export function SkillGapTable({ matches, isLoading }: SkillGapTableProps) {
  const isMobile = useIsMobile()
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState<Filter>('all')
  const [hideReported, setHideReported] = useState(false)
  const [filterOpen, setFilterOpen] = useState(false)
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

  // Urutan: yang paling sering diminta lebih dulu; urutan semula dipertahankan untuk jumlah sama.
  const filtered = matches
    .filter((m) => {
      if (filter !== 'all' && m.classification !== filter) return false
      if (hideReported && m.self_declared) return false
      if (!search.trim()) return true
      return m.skill.toLowerCase().includes(search.trim().toLowerCase())
    })
    .map((m, index) => ({ m, index }))
    .sort((a, b) => b.m.demand_count - a.m.demand_count || a.index - b.index)
    .map(({ m }) => m)

  const maxDemand = matches.reduce((max, m) => Math.max(max, m.demand_count), 0)

  const counts = {
    all: matches.length,
    covered: matches.filter((m) => m.classification === 'covered').length,
    partial: matches.filter((m) => m.classification === 'partial').length,
    missing: matches.filter((m) => m.classification === 'missing').length,
  }

  const statusOptions = [
    ['all', copy.analysesResult.filterAll, counts.all],
    ['covered', copy.status.covered, counts.covered],
    ['partial', copy.status.partial, counts.partial],
    ['missing', copy.status.missingShort, counts.missing],
  ] as const

  const activeFilters = (filter !== 'all' ? 1 : 0) + (hideReported ? 1 : 0)

  const hideReportedToggle = (
    <label className="flex min-h-11 w-fit cursor-pointer items-center gap-2.5 text-sm text-text-secondary">
      <input
        type="checkbox"
        checked={hideReported}
        onChange={(e) => setHideReported(e.target.checked)}
        className="size-5 accent-brand-accent"
      />
      {copy.analysesResult.hideReported}
    </label>
  )

  return (
    <div className="flex flex-col gap-4">
      {/* Toolbar */}
      {isMobile ? (
        <div className="flex items-center gap-2">
          <div className="flex min-h-11 flex-1 items-center gap-2 rounded-lg border border-field bg-white px-3 text-sm focus-within:ring-2 focus-within:ring-brand-accent/40">
            <Search className="size-4 shrink-0 text-text-secondary" aria-hidden="true" />
            <input
              type="text"
              placeholder={copy.analysesResult.skillGapSearch}
              aria-label={copy.analysesResult.skillGapSearch}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="h-11 w-full min-w-0 bg-transparent outline-none placeholder:text-text-secondary"
            />
          </div>
          <Button
            type="button"
            variant="outline"
            onClick={() => setFilterOpen(true)}
            aria-haspopup="dialog"
            className="h-11 shrink-0 gap-1.5 px-3 text-sm font-semibold"
          >
            <SlidersHorizontal className="size-4" aria-hidden="true" />
            {copy.analysesResult.filterButton}
            {activeFilters > 0 && (
              <>
                <span aria-hidden="true" className="rounded-md bg-brand px-1.5 text-sm text-white">
                  {activeFilters}
                </span>
                <span className="sr-only">{copy.analysesResult.filterActive(activeFilters)}</span>
              </>
            )}
          </Button>
        </div>
      ) : (
        <>
          <div className="flex flex-col gap-3 rounded-xl border border-border-warm bg-white p-4 md:flex-row md:items-center md:justify-between">
            <div className="flex max-w-sm flex-1 items-center gap-2 rounded-lg border border-field bg-surface-subtle px-3 py-1.5 text-sm focus-within:ring-2 focus-within:ring-brand-accent/40">
              <Search className="size-3.5 text-text-secondary" aria-hidden="true" />
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
              className="flex flex-wrap items-center gap-1 rounded-lg bg-surface-subtle p-1 text-sm"
            >
              {statusOptions.map(([key, label, count]) => (
                <button
                  key={key}
                  type="button"
                  onClick={() => setFilter(key)}
                  aria-pressed={filter === key}
                  className={`inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-md px-3 text-sm font-semibold transition-colors active:bg-white/70 ${
                    filter === key ? 'bg-white text-brand-strong shadow-xs' : 'text-text-secondary'
                  }`}
                >
                  {filter === key && <Check className="size-3.5" aria-hidden="true" />}
                  {label} ({count})
                </button>
              ))}
            </div>
          </div>
          {hideReportedToggle}
        </>
      )}

      {isLoading ? (
        <div className="rounded-xl border border-border-warm bg-white p-8 text-center text-sm text-text-secondary">
          Memuat data skill gap…
        </div>
      ) : filtered.length === 0 ? (
        <div className="rounded-xl border border-dashed border-border-warm bg-white p-8 text-center text-sm text-text-secondary">
          {copy.analysesResult.emptyGap}
        </div>
      ) : isMobile ? (
        <div className="flex flex-col gap-3">
          {filtered.map((m) => {
            const Icon = CLASSIFICATION_ICON[m.classification]
            return (
              <button
                key={m.skill}
                type="button"
                onClick={() => openDrawer(m)}
                className="flex flex-col gap-2 rounded-lg border border-border-warm bg-white p-4 text-left"
              >
                <div className="flex flex-wrap items-start justify-between gap-x-2 gap-y-1">
                  <span className="min-w-0 max-w-full break-words text-sm font-bold text-text-primary">{m.skill}</span>
                  <span
                    className={`inline-flex max-w-full items-center gap-1 rounded-md px-2 py-0.5 text-sm font-semibold ${
                      CLASSIFICATION_STYLE[m.classification]
                    }`}
                  >
                    <Icon className="size-3" aria-hidden="true" />
                    {copy.status[m.classification]}
                  </span>
                </div>
                <span className="text-sm text-text-secondary">
                  {m.demand_count > 0 ? `Diminta di ${m.demand_count} lowongan` : copy.analysesResult.notInDemand}
                </span>
                <DemandBar value={m.demand_count} max={maxDemand} />
                {m.matched_competency && (
                  <span className="text-sm text-text-secondary">{m.matched_competency.competency}</span>
                )}
                {m.classification === 'partial' && <WeakMatch />}
                {m.self_declared && (
                  <span className="w-fit rounded bg-brand-soft px-2 py-0.5 text-sm font-semibold text-brand-strong">
                    {copy.analysesResult.reportedFlag}
                  </span>
                )}
              </button>
            )
          })}
        </div>
      ) : (
        <div className="overflow-hidden rounded-xl border border-border-warm bg-white">
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
                        <Icon className="size-3" aria-hidden="true" />
                        {copy.status[m.classification]}
                      </span>
                    </td>
                    <td className="px-4 py-4 text-text-secondary">
                      <div className="flex flex-col gap-1.5">
                        <span>{m.demand_count > 0 ? `${m.demand_count} lowongan` : copy.analysesResult.notInDemand}</span>
                        <DemandBar value={m.demand_count} max={maxDemand} />
                      </div>
                    </td>
                    <td className="px-4 py-4 text-text-secondary">
                      {m.matched_competency ? (
                        <span className="flex flex-col items-start gap-1">
                          <span>{m.matched_competency.competency}</span>
                          {m.matched_competency.source_reference && (
                            <span className="block text-sm text-text-secondary">
                              {sourceReferenceLabel(m.matched_competency.source_reference, '')}
                              {m.matched_competency.page ? `, hal. ${m.matched_competency.page}` : ''}
                            </span>
                          )}
                          {m.classification === 'partial' && <WeakMatch />}
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
      )}

      {/* Sheet filter (ponsel) */}
      {filterOpen && (
        <ModalDialog title={copy.analysesResult.filterTitle} variant="sheet" onClose={() => setFilterOpen(false)}>
          <div role="group" aria-label={copy.analysesResult.colStatus} className="flex flex-col">
            <span className="pb-1 text-sm font-bold text-text-secondary">{copy.analysesResult.colStatus}</span>
            {statusOptions.map(([key, label, count]) => (
              <button
                key={key}
                type="button"
                aria-pressed={filter === key}
                onClick={() => setFilter(key)}
                className={`flex min-h-11 w-full items-center gap-2 rounded-md px-2 text-left text-sm font-semibold ${
                  filter === key ? 'bg-brand-soft text-brand-strong' : 'text-text-primary'
                }`}
              >
                <span className="flex size-4 shrink-0 items-center justify-center">
                  {filter === key && <Check className="size-4" aria-hidden="true" />}
                </span>
                {label} ({count})
              </button>
            ))}
          </div>
          {hideReportedToggle}
          <Button
            type="button"
            onClick={() => setFilterOpen(false)}
            className="h-11 w-full bg-brand text-white hover:bg-brand-hover"
          >
            {copy.analysesResult.filterDone}
          </Button>
        </ModalDialog>
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
                <h2 id="skill-drawer-title" className="text-lg font-bold text-text-primary">
                  {drawerMatch.skill}
                </h2>
              </div>
              <button
                type="button"
                ref={closeRef}
                onClick={closeDrawer}
                aria-label={copy.analysesResult.close}
                className="flex size-11 shrink-0 items-center justify-center rounded-full hover:bg-surface-subtle active:bg-surface-subtle"
              >
                <X className="size-4" aria-hidden="true" />
              </button>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <span
                className={`w-fit rounded-md px-2.5 py-1 text-sm font-semibold ${
                  CLASSIFICATION_STYLE[drawerMatch.classification]
                }`}
              >
                {copy.status[drawerMatch.classification]}
              </span>
              {drawerMatch.classification === 'partial' && <WeakMatch />}
            </div>

            {drawerMatch.matched_competency && (
              <div className="flex flex-col gap-1.5 rounded-lg bg-surface-subtle p-3.5">
                <span className="text-sm font-bold text-text-secondary">{copy.analysesResult.evidenceRps}</span>
                <p className="text-sm italic text-text-primary">
                  “{drawerMatch.matched_competency.evidence || drawerMatch.matched_competency.competency}”
                </p>
                {(drawerMatch.matched_competency.source_reference || drawerMatch.matched_competency.page) && (
                  <span className="text-sm text-text-secondary">
                    {[
                      drawerMatch.matched_competency.source_reference
                        ? sourceReferenceLabel(drawerMatch.matched_competency.source_reference, '')
                        : null,
                      drawerMatch.matched_competency.page ? `hal. ${drawerMatch.matched_competency.page}` : null,
                    ]
                      .filter(Boolean)
                      .join(', ')}
                  </span>
                )}
              </div>
            )}

            {drawerMatch.job_evidence.length > 0 && (
              <EvidenceJobList jobs={drawerMatch.job_evidence} total={drawerMatch.job_evidence_total} />
            )}

            {drawerMatch.reason && (
              <div className="flex flex-col gap-1.5">
                <span className="text-sm font-bold text-text-secondary">{copy.analysesResult.evidenceReason}</span>
                <p className="text-sm leading-relaxed text-text-primary">{drawerMatch.reason}</p>
              </div>
            )}

            {drawerMatch.similarity_score !== null && (
              <div className="flex items-center justify-between rounded-lg bg-surface-subtle px-3 py-2 text-sm">
                <span className="text-text-secondary">{copy.similarityLabel}</span>
                <span className="font-semibold text-text-primary">{formatScore(drawerMatch.similarity_score)}</span>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
