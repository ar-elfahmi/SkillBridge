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
    <div className="grid grid-cols-3 gap-2 sm:gap-3">
      {stats.map((s) => {
        const Icon = s.icon
        return (
          <div
            key={s.label}
            className="flex items-center justify-between rounded-xl border border-border-warm bg-white p-3 shadow-sm sm:p-4"
          >
            <div className="flex flex-col gap-1">
              <span className="text-[11px] font-semibold leading-tight text-text-primary sm:text-xs md:text-sm">{s.label}</span>
              <span className="font-display text-xl font-normal text-brand-strong">{s.value}</span>
              <span className="hidden text-[11px] text-text-secondary sm:block">{s.note}</span>
            </div>

            <div className="hidden size-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft sm:flex text-brand-strong">
              <Icon className="size-5" aria-hidden="true" />
            </div>
          </div>
        )
      })}
    </div>
  )
}
