import type { CSSProperties } from 'react'
import { CheckCircle2, CircleDot, CircleX } from 'lucide-react'
import type { AnalysisSummary, Classification } from '@/types/api'
import { copy } from '@/lib/copy'

interface CoverageSummaryProps {
  summary: AnalysisSummary
}

const SEGMENTS: {
  key: Classification
  color: string
  Icon: typeof CheckCircle2
  cardClass: string
}[] = [
  {
    key: 'covered',
    color: '#059669',
    Icon: CheckCircle2,
    cardClass: 'border-emerald-200 bg-emerald-50/60 text-emerald-900',
  },
  {
    key: 'partial',
    color: '#d97706',
    Icon: CircleDot,
    cardClass: 'border-amber-200 bg-amber-50/60 text-amber-900',
  },
  {
    key: 'missing',
    color: '#78716c',
    Icon: CircleX,
    cardClass: 'border-stone-300 bg-stone-100/70 text-stone-800',
  },
]

function formatNumber(value: number, fractionDigits = 0): string {
  return value.toLocaleString('id-ID', {
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
  })
}

/** Ringkasan cakupan: persentase utama, kartu hitung, dan diagram donat serta bilah proporsi. */
export function CoverageSummary({ summary }: CoverageSummaryProps) {
  const total = summary.total_skills
  const share = (count: number) => (total > 0 ? (count / total) * 100 : 0)

  // Dihitung di frontend dari hitungan, dengan urutan segmen tetap: tercakup, sebagian, belum.
  const coveredEnd = share(summary.covered)
  const partialEnd = coveredEnd + share(summary.partial)
  const donutStyle: CSSProperties = {
    background:
      total > 0
        ? `conic-gradient(#059669 0 ${coveredEnd}%, #d97706 ${coveredEnd}% ${partialEnd}%, #78716c ${partialEnd}% 100%)`
        : 'conic-gradient(#e7e5e4 0 100%)',
  }

  return (
    <section aria-labelledby="coverage-summary-title" className="space-y-5">
      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        {/* Persentase utama dan donat */}
        <div className="flex flex-col items-center gap-4 rounded-2xl border border-border-warm bg-white p-6 shadow-xs sm:flex-row sm:justify-between lg:flex-col lg:justify-center">
          <div className="text-center sm:text-left lg:text-center">
            <p id="coverage-summary-title" className="text-[11px] font-bold uppercase tracking-wider text-text-secondary">
              {copy.analysisResult.coverageTitle}
            </p>
            <p className="mt-1 font-serif text-4xl font-bold text-text-primary">
              {formatNumber(summary.coverage_percentage, 1)}%
            </p>
            <p className="mt-2 max-w-xs text-xs leading-relaxed text-text-secondary">
              {copy.analysisResult.coverageNote}
            </p>
          </div>

          <div
            role="img"
            aria-label={`Diagram cakupan: ${formatNumber(summary.covered)} tercakup, ${formatNumber(summary.partial)} sebagian, ${formatNumber(summary.missing)} belum ditemukan dalam RPS`}
            className="relative size-32 shrink-0 rounded-full"
            style={donutStyle}
          >
            <div className="absolute inset-4 flex flex-col items-center justify-center rounded-full bg-white text-center">
              <span className="text-xl font-bold text-text-primary">{formatNumber(total)}</span>
              <span className="text-[10px] font-semibold text-text-secondary">skill</span>
            </div>
          </div>
        </div>

        {/* Kartu hitungan per status */}
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          {SEGMENTS.map(({ key, Icon, cardClass }) => {
            const count = summary[key]
            return (
              <div key={key} className={`flex flex-col justify-between rounded-2xl border p-4 ${cardClass}`}>
                <div className="flex items-center gap-1.5 text-xs font-bold">
                  <Icon className="size-3.5" aria-hidden="true" />
                  <span>{copy.status[key]}</span>
                </div>
                <p className="mt-3 text-3xl font-bold">{formatNumber(count)}</p>
                <p className="text-[11px] font-semibold opacity-80">
                  {formatNumber(share(count), 1)}% dari total skill
                </p>
              </div>
            )
          })}
        </div>
      </div>

      {/* Bilah proporsi */}
      <div className="space-y-2 rounded-2xl border border-border-warm bg-white p-5 shadow-xs">
        <div className="flex h-3 w-full overflow-hidden rounded-full bg-surface-subtle" aria-hidden="true">
          {SEGMENTS.map(({ key, color }) => (
            <div
              key={key}
              style={{ width: `${share(summary[key])}%`, backgroundColor: color }}
              className="h-full transition-all"
            />
          ))}
        </div>
        <ul className="flex flex-wrap gap-x-5 gap-y-1 text-xs text-text-secondary">
          {SEGMENTS.map(({ key, color }) => (
            <li key={key} className="flex items-center gap-1.5">
              <span className="size-2.5 rounded-sm" style={{ backgroundColor: color }} aria-hidden="true" />
              {copy.status[key]}
            </li>
          ))}
          <li className="ml-auto font-semibold text-text-primary">
            {copy.analysisResult.totalSkills}: {formatNumber(total)}
          </li>
        </ul>
      </div>
    </section>
  )
}
