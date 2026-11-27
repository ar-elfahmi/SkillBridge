import { Check, CircleDot, X } from 'lucide-react'
import type { Analysis } from '@/types/api'
import { copy } from '@/lib/copy'

const STATUS_STYLE = {
  covered: { bar: 'bg-success-solid', chip: 'bg-success-soft text-success', Icon: Check },
  partial: { bar: 'bg-warning-solid', chip: 'bg-warning-soft text-warning', Icon: CircleDot },
  missing: { bar: 'bg-danger', chip: 'bg-danger-soft text-danger', Icon: X },
} as const

interface CoverageSummaryProps {
  analysis: Analysis
  onViewGap?: () => void
}

export function CoverageSummary({ analysis, onViewGap }: CoverageSummaryProps) {
  const summary = analysis.summary
  const total = summary?.total_skills ?? 0

  const rows = [
    { key: 'covered' as const, label: copy.status.covered, count: summary?.covered ?? 0 },
    { key: 'partial' as const, label: copy.status.partial, count: summary?.partial ?? 0 },
    { key: 'missing' as const, label: copy.status.missing, count: summary?.missing ?? 0 },
  ]

  const pct = (n: number) => (total > 0 ? (n / total) * 100 : 0)
  const fmt = (n: number) => n.toFixed(1).replace('.', ',')
  const covered = rows[0]!

  return (
    <section className="flex flex-col gap-6 rounded-xl border border-border-warm bg-white p-5 sm:p-8">
      <div className="flex flex-col gap-1">
        <h2 className="text-sm font-semibold text-text-secondary">{copy.coverageLabel}</h2>
        <p className="font-display text-5xl font-normal leading-none text-text-primary sm:text-6xl">
          {fmt(pct(covered.count))}%
        </p>
        <p className="pt-1 text-base text-text-primary">
          {covered.count} {copy.analysesResult.ofTotalSkills} {total}{' '}
          {copy.analysesResult.skillCountSuffix} tercakup dalam RPS
        </p>
      </div>

      <div
        role="img"
        aria-label={rows.map((r) => `${r.label} ${fmt(pct(r.count))}%`).join(', ')}
        className="flex h-4 w-full gap-0.5 overflow-hidden rounded-full bg-progress-track"
      >
        {rows.map((r) =>
          r.count > 0 ? (
            <div
              key={r.key}
              className={`${STATUS_STYLE[r.key].bar} h-full`}
              style={{ width: `${pct(r.count)}%` }}
            />
          ) : null
        )}
      </div>

      <ul className="flex flex-col divide-y divide-border-warm">
        {rows.map((r) => {
          const { chip, Icon } = STATUS_STYLE[r.key]
          return (
            <li key={r.key} className="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
              <span className={`flex size-9 shrink-0 items-center justify-center rounded-full ${chip}`}>
                <Icon className="size-4" aria-hidden="true" />
              </span>
              <span className="flex min-w-0 flex-1 flex-col">
                <span className="text-base font-semibold text-text-primary">{r.label}</span>
                <span className="text-sm text-text-secondary">
                  {r.count} {copy.analysesResult.ofTotalSkills} {total}{' '}
                  {copy.analysesResult.skillCountSuffix}
                </span>
              </span>
              <span className="text-lg font-bold tabular-nums text-text-primary">
                {fmt(pct(r.count))}%
              </span>
            </li>
          )
        })}
      </ul>

      <div className="flex flex-col gap-3 border-t border-border-warm pt-5 sm:flex-row sm:items-center sm:justify-between">
        <p className="max-w-prose text-sm leading-relaxed text-text-secondary">{copy.disclaimer}</p>
        {onViewGap && (
          <button
            type="button"
            onClick={onViewGap}
            className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-brand px-5 text-sm font-bold text-white transition-colors hover:bg-brand-hover active:bg-brand-hover"
          >
            {copy.analysesResult.viewGapBtn}
          </button>
        )}
      </div>
    </section>
  )
}
