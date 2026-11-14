import { Briefcase, Target, UserRound } from 'lucide-react'
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
  const stats = [
    {
      label: copy.dashboard.stats.missingSkillsLabel,
      value: missingSkillsCount,
      note: `${missingSkillsCount} ${copy.dashboard.stats.missingSkillsNote}`,
      icon: Target,
    },
    {
      label: copy.dashboard.stats.matchedJobsLabel,
      value: matchedJobsCount,
      note: `${matchedJobsCount} ${copy.dashboard.stats.matchedJobsNote}`,
      icon: Briefcase,
    },
    {
      label: copy.dashboard.stats.careersLabel,
      value: careersCount,
      note: `${careersCount} ${copy.dashboard.stats.careersNote}`,
      icon: UserRound,
    },
  ]

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
      {stats.map((s) => {
        const Icon = s.icon
        return (
          <div
            key={s.label}
            className="flex items-center justify-between rounded-xl border border-border-warm bg-white p-4 shadow-sm"
          >
            <div className="flex flex-col gap-1">
              <span className="text-xs font-semibold text-text-primary md:text-sm">{s.label}</span>
              <span className="font-display text-xl font-normal text-brand">{s.value}</span>
              <span className="text-[11px] text-text-secondary">{s.note}</span>
            </div>

            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand">
              <Icon className="size-5" aria-hidden="true" />
            </div>
          </div>
        )
      })}
    </div>
  )
}
