import { copy } from '@/lib/copy'

interface DashboardStatsProps {
  missingSkillsCount: number
  matchedJobsCount: number
  careersCount: number
  analysesCount: number
}

export function DashboardStats({
  missingSkillsCount,
  matchedJobsCount,
  careersCount,
  analysesCount,
}: DashboardStatsProps) {
  const secondary = [
    {
      label: copy.dashboard.stats.matchedJobsLabel,
      value: matchedJobsCount,
      note: null,
    },
    {
      label: copy.dashboard.stats.careersLabel,
      value: careersCount,
      note: null,
    },
  ]

  return (
    <div className="grid grid-cols-[1.25fr_1fr_1fr] gap-2 sm:gap-3">
      <div className="flex flex-col gap-1 rounded-lg bg-brand-soft p-3 sm:p-4">
        <span className="text-sm font-semibold leading-tight text-text-primary">
          {copy.dashboard.stats.missingSkillsLabel}
        </span>
        <span className="font-display text-3xl font-normal leading-none text-brand-strong sm:text-4xl">
          {missingSkillsCount}
        </span>
        <span className="hidden text-sm text-text-secondary sm:block">
          {copy.dashboard.stats.missingSkillsNote(analysesCount)}
        </span>
      </div>

      {secondary.map((s) => (
        <div
          key={s.label}
          className="flex flex-col gap-1 rounded-lg border border-border-warm bg-white p-3 sm:p-4"
        >
          <span className="text-sm font-semibold leading-tight text-text-primary">
            {s.label}
          </span>
          <span className="font-display text-2xl font-normal leading-none text-text-primary sm:text-3xl">
            {s.value}
          </span>
          {s.note && <span className="hidden text-sm text-text-secondary sm:block">{s.note}</span>}
        </div>
      ))}
    </div>
  )
}
