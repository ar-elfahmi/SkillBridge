import type { User } from '@/types/api'
import { copy } from '@/lib/copy'

interface DashboardSidebarProps {
  user?: User
  rpsCount: number
  analysesCount: number
}

export function DashboardSidebar({
  user,
  rpsCount,
  analysesCount,
}: DashboardSidebarProps) {
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
          <span className="mt-1 inline-block rounded-full bg-surface-subtle px-2.5 py-0.5 text-[10px] font-semibold text-text-secondary">
            Mahasiswa
          </span>
        </div>
      </div>

      {/* Ringkasan Akun & Status */}
      <div className="flex flex-col gap-3 rounded-2xl border border-border-warm bg-white p-4 shadow-sm">
        <span className="text-xs font-bold text-text-primary">Status</span>
        <div className="flex flex-col gap-2">
          <div className="flex items-center justify-between rounded-lg bg-surface-subtle px-3 py-2 text-xs">
            <span className="text-text-secondary">Dokumen RPS</span>
            <span className="font-bold text-text-primary">{rpsCount}</span>
          </div>
          <div className="flex items-center justify-between rounded-lg bg-surface-subtle px-3 py-2 text-xs">
            <span className="text-text-secondary">Analisis Selesai</span>
            <span className="font-bold text-text-primary">{analysesCount}</span>
          </div>
        </div>
      </div>
    </aside>
  )
}
