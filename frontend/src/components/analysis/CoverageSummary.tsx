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
      <div className="grid grid-cols-3 gap-2 sm:gap-3">
        {rows.map((r) => {
          const { chip, Icon } = STATUS_STYLE[r.key]
          const textColor = chip.split(' ')[1]
          return (
            <div
              key={r.key}
              className="flex flex-col gap-1 rounded-2xl border border-border-warm bg-white p-3 sm:gap-1.5 sm:p-5"
            >
              <span className={`flex size-7 items-center justify-center rounded-full ${chip}`}>
                <Icon className="size-4" aria-hidden="true" />
              </span>
              <span className="font-display text-xl font-bold text-text-primary sm:text-2xl md:text-3xl">
                {fmt(pct(r.count))}%
              </span>
              <span className={`text-[11px] font-bold leading-tight sm:text-xs ${textColor}`}>
                {r.key === 'missing' ? (
                  <>
                    <span className="sm:hidden">{copy.status.missingShort}</span>
                    <span className="hidden sm:inline">{r.label}</span>
                  </>
                ) : (
                  r.label
                )}
              </span>
              <span className="text-[11px] leading-tight text-text-secondary">
                {r.count} {copy.analysesResult.ofTotalSkills} {total} {copy.analysesResult.skillCountSuffix}
              </span>
            </div>
          )
        })}
      </div>

      <div className="flex flex-col gap-4 rounded-2xl border border-border-warm bg-white p-6">
        <h2 className="text-sm font-bold text-text-primary">{copy.coverageLabel}</h2>

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
