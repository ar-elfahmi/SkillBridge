import type { Analysis } from '@/types/api'
import { copy } from '@/lib/copy'

const STATUS_COLORS: Record<string, string> = {
  covered: 'bg-success',
  partial: 'bg-warning',
  missing: 'bg-danger',
}

export function CoverageSummary({ analysis }: { analysis: Analysis }) {
  const summary = analysis.summary
  const total = summary?.total_skills ?? 0

  const rows = [
    { key: 'covered' as const, label: copy.status.covered, count: summary?.covered ?? 0, color: STATUS_COLORS.covered },
    { key: 'partial' as const, label: copy.status.partial, count: summary?.partial ?? 0, color: STATUS_COLORS.partial },
    { key: 'missing' as const, label: copy.status.missing, count: summary?.missing ?? 0, color: STATUS_COLORS.missing },
  ]

  const pct = (n: number) => (total > 0 ? (n / total) * 100 : 0)
  const fmt = (n: number) => n.toFixed(1).replace('.', ',')

  return (
    <div className="flex flex-col gap-5">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        {rows.map((r) => (
          <div
            key={r.key}
            className="flex flex-col gap-1 rounded-2xl border border-border-warm bg-white p-5 shadow-sm"
          >
            <span className="text-xs font-semibold text-text-secondary">{r.label}</span>
            <span className="font-display text-2xl font-bold text-text-primary md:text-3xl">
              {fmt(pct(r.count))}%
            </span>
            <span className="text-[11px] text-text-secondary">
              {r.count} {copy.analysesResult.ofTotalSkills} {total} {copy.analysesResult.skillCountSuffix}
            </span>
          </div>
        ))}
      </div>

      <div className="flex flex-col gap-4 rounded-2xl border border-border-warm bg-white p-6 shadow-sm">
        <h3 className="text-sm font-bold text-text-primary">
          {copy.coverageLabel}
        </h3>

        <div className="flex h-3 w-full overflow-hidden rounded-full bg-surface-subtle">
          {rows.map((r) =>
            r.count > 0 ? (
              <div
                key={r.key}
                className={`${r.color} h-full`}
                style={{ width: `${pct(r.count)}%` }}
              />
            ) : null
          )}
        </div>

        <div className="flex flex-col gap-2.5">
          {rows.map((r) => (
            <div key={r.key} className="flex items-center gap-2.5">
              <span className={`size-2.5 shrink-0 rounded-full ${r.color}`} />
              <span className="flex-1 text-xs text-text-primary">{r.label}</span>
              <span className="text-xs font-semibold text-text-secondary">
                {fmt(pct(r.count))}%
              </span>
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