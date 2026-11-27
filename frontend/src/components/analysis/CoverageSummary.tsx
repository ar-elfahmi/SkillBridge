import { Check, CircleDot, X } from 'lucide-react'
import type { Analysis } from '@/types/api'
import { copy } from '@/lib/copy'

const STATUS_STYLE = {
  covered: { bar: 'bg-success-solid', chip: 'bg-success-soft text-success', Icon: Check },
  partial: { bar: 'bg-warning-solid', chip: 'bg-warning-soft text-warning', Icon: CircleDot },
  missing: { bar: 'bg-danger', chip: 'bg-danger-soft text-danger', Icon: X },
} as const

export function CoverageSummary({ analysis }: { analysis: Analysis }) {
  const summary = analysis.summary
  const total = summary?.total_skills ?? 0

  const rows = [
    { key: 'covered' as const, label: copy.status.covered, count: summary?.covered ?? 0 },
    { key: 'partial' as const, label: copy.status.partial, count: summary?.partial ?? 0 },
    { key: 'missing' as const, label: copy.status.missing, count: summary?.missing ?? 0 },
  ]

  const pct = (n: number) => (total > 0 ? (n / total) * 100 : 0)
  const fmt = (n: number) => n.toFixed(1).replace('.', ',')

  return (
    <div className="flex flex-col gap-5">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        {rows.map((r) => {
          const { chip, Icon } = STATUS_STYLE[r.key]
          return (
            <div
              key={r.key}
              className="flex flex-col gap-1.5 rounded-2xl border border-border-warm bg-white p-5 shadow-sm"
            >
              <span
                className={`inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ${chip}`}
              >
                <Icon className="size-3.5" aria-hidden="true" />
                {r.label}
              </span>
              <span className="font-display text-2xl font-bold text-text-primary md:text-3xl">
                {fmt(pct(r.count))}%
              </span>
              <span className="text-[11px] text-text-secondary">
                {r.count} {copy.analysesResult.ofTotalSkills} {total} {copy.analysesResult.skillCountSuffix}
              </span>
            </div>
          )
        })}
      </div>

      <div className="flex flex-col gap-4 rounded-2xl border border-border-warm bg-white p-6 shadow-sm">
        <h3 className="text-sm font-bold text-text-primary">{copy.coverageLabel}</h3>

        <div className="flex h-3.5 w-full gap-0.5 overflow-hidden rounded-full bg-progress-track">
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

        <div className="flex flex-col gap-2.5">
          {rows.map((r) => (
            <div key={r.key} className="flex items-center gap-2.5">
              <span className={`size-3 shrink-0 rounded-full ${STATUS_STYLE[r.key].bar}`} />
              <span className="flex-1 text-xs font-medium text-text-primary">{r.label}</span>
              <span className="text-xs font-bold text-text-primary">{fmt(pct(r.count))}%</span>
            </div>
          ))}
        </div>

        <p className="border-t border-border-warm pt-3 text-[11px] text-text-secondary">
          {copy.disclaimer}
        </p>
      </div>
    </div>
  )
}
