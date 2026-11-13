import { LogOut } from 'lucide-react'
import type { User } from '@/types/api'
import { copy } from '@/lib/copy'

interface DashboardSidebarProps {
  user?: User
  onLogout: () => void
}

export function DashboardSidebar({ user, onLogout }: DashboardSidebarProps) {
  const initials = user?.name
    ? user.name
        .split(' ')
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
    : 'U'

  return (
    <aside className="hidden w-[280px] shrink-0 flex-col gap-4 lg:flex">
      <h3 className="font-semibold text-text-primary">
        {copy.dashboard.sidebar.heading}
      </h3>

      {/* User Profile Card */}
      <div className="flex flex-col items-center gap-3 rounded-2xl border border-border-warm bg-white p-5 text-center shadow-sm">
        <div className="flex size-14 items-center justify-center rounded-full bg-brand text-lg font-bold text-white shadow-sm">
          {initials}
        </div>
        <div className="flex flex-col">
          <span className="text-sm font-bold text-text-primary">{user?.name || 'Mahasiswa'}</span>
          <span className="text-xs text-text-secondary">{user?.email || ''}</span>
        </div>
        <button
          type="button"
          onClick={onLogout}
          className="mt-1 flex items-center gap-1.5 rounded-lg px-3 py-1 text-xs font-semibold text-danger hover:bg-brand-soft/40 transition-colors"
        >
          <LogOut className="size-3.5" aria-hidden="true" />
          <span>Keluar</span>
        </button>
      </div>

      {/* Owned Skills Card */}
      <div className="flex flex-col gap-3 rounded-2xl border border-border-warm bg-white p-4 shadow-sm">
        <div className="flex flex-col">
          <span className="text-xs font-bold text-text-primary">
            {copy.dashboard.sidebar.ownedSkillsTitle}
          </span>
          <span className="text-[11px] text-text-secondary">
            {copy.dashboard.sidebar.ownedSkillsNote}
          </span>
        </div>

        <div className="flex flex-col gap-2">
          {[
            { name: 'React', level: 'Advanced' },
            { name: 'TypeScript', level: 'Intermediate' },
            { name: 'Figma', level: 'Intermediate' },
            { name: 'Node.js', level: 'Beginner' },
          ].map((s) => (
            <div
              key={s.name}
              className="flex items-center justify-between rounded-lg bg-surface-subtle px-3 py-2 text-xs"
            >
              <span className="font-medium text-text-primary">{s.name}</span>
              <span className="text-[11px] text-text-secondary">{s.level}</span>
            </div>
          ))}
        </div>

        <button
          type="button"
          className="text-left text-xs font-bold text-brand hover:underline"
        >
          {copy.dashboard.sidebar.viewAllSkills}
        </button>
      </div>

      {/* Recent Activity Card */}
      <div className="flex flex-col gap-3 rounded-2xl border border-border-warm bg-white p-4 shadow-sm">
        <span className="text-xs font-bold text-text-primary">
          {copy.dashboard.sidebar.recentActivityTitle}
        </span>

        <div className="flex flex-col gap-2">
          <div className="flex flex-col rounded-lg bg-surface-subtle px-3 py-2 text-xs">
            <span className="font-medium text-text-primary">RPS dianalisis</span>
            <span className="text-[11px] text-text-secondary">2 jam lalu</span>
          </div>
          <div className="flex flex-col rounded-lg bg-surface-subtle px-3 py-2 text-xs">
            <span className="font-medium text-text-primary">Skill baru ditambahkan</span>
            <span className="text-[11px] text-text-secondary">Kemarin</span>
          </div>
        </div>
      </div>
    </aside>
  )
}
