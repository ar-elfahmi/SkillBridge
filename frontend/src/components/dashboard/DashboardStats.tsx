import { copy } from '@/lib/copy'

interface DashboardStatsProps {
  missingSkillsCount: number
  matchedJobsCount: number
  careersCount: number
}

export function DashboardStats({
  missingSkillsCount,
  matchedJobsCount,
  careersCount,
}: DashboardStatsProps) {
  const secondary = [
    {
      label: copy.dashboard.stats.matchedJobsLabel,
      value: matchedJobsCount,
      note: `${matchedJobsCount} ${copy.dashboard.stats.matchedJobsNote}`,
    },
    {
      label: copy.dashboard.stats.careersLabel,
      value: careersCount,
      note: `${careersCount} ${copy.dashboard.stats.careersNote}`,
    },
  ]

  return (
    <div className="grid grid-cols-[1.25fr_1fr_1fr] gap-2 sm:gap-3">
      <div className="flex flex-col gap-1 rounded-lg bg-brand-soft p-3 sm:p-4">
        <span className="text-xs font-semibold leading-tight text-text-primary sm:text-sm">
          {copy.dashboard.stats.missingSkillsLabel}
        </span>
        <span className="font-display text-3xl font-normal leading-none text-brand-strong sm:text-4xl">
          {missingSkillsCount}
        </span>
        <span className="hidden text-xs text-text-secondary sm:block">
          {`${missingSkillsCount} ${copy.dashboard.stats.missingSkillsNote}`}
        </span>
      </div>

      {secondary.map((s) => (
        <div
          key={s.label}
          className="flex flex-col gap-1 rounded-lg border border-border-warm bg-white p-3 sm:p-4"
        >
          <span className="text-xs font-semibold leading-tight text-text-primary sm:text-sm">
            {s.label}
          </span>
          <span className="font-display text-2xl font-normal leading-none text-text-primary sm:text-3xl">
            {s.value}
          </span>
          <span className="hidden text-xs text-text-secondary sm:block">{s.note}</span>
        </div>
      ))}
    </div>
  )
}
