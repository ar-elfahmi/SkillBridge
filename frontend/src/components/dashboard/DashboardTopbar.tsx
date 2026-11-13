import { useState } from 'react'
import { Bell, LogOut, Search } from 'lucide-react'
import { BrandLogo } from '@/components/common/BrandLogo'
import type { User } from '@/types/api'
import { copy } from '@/lib/copy'

interface DashboardTopbarProps {
  user?: User
  onLogout: () => void
  onSearch?: (query: string) => void
}

export function DashboardTopbar({ user, onLogout, onSearch }: DashboardTopbarProps) {
  const [searchVal, setSearchVal] = useState('')

  const initials = user?.name
    ? user.name
        .split(' ')
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
    : 'U'

  return (
    <header className="flex h-16 shrink-0 items-center justify-between border-b border-border-warm bg-white px-4 md:h-[72px] md:px-8">
      <div className="flex items-center gap-6">
        <BrandLogo />
      </div>

      {/* Desktop Search Bar */}
      <div className="hidden max-w-md flex-1 items-center gap-2.5 rounded-full bg-surface-canvas px-4 py-2 text-sm text-text-primary md:flex">
        <Search className="size-4 shrink-0 text-brand" aria-hidden="true" />
        <input
          type="text"
          placeholder={copy.dashboard.searchPlaceholder}
          value={searchVal}
          onChange={(e) => {
            setSearchVal(e.target.value)
            onSearch?.(e.target.value)
          }}
          className="w-full bg-transparent text-sm placeholder:text-text-secondary outline-none"
        />
      </div>

      {/* Topbar Actions */}
      <div className="flex items-center gap-2.5">
        <button
          type="button"
          aria-label="Notifikasi"
          className="flex size-10 items-center justify-center rounded-full bg-brand-soft text-brand transition-colors hover:bg-brand-soft/80"
        >
          <Bell className="size-4" aria-hidden="true" />
        </button>

        <div
          title={user?.name}
          className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-bold text-white shadow-sm"
        >
          {initials}
        </div>

        {/* Identitas pengguna (desktop) */}
        <div className="hidden min-w-0 flex-col md:flex">
          <span className="truncate text-xs font-semibold text-text-primary">
            {user?.name}
          </span>
          <span className="truncate text-[11px] text-text-secondary">{user?.email}</span>
        </div>

        <button
          type="button"
          onClick={onLogout}
          title="Keluar"
          aria-label="Keluar"
          className="flex size-10 items-center justify-center rounded-full text-text-secondary transition-colors hover:bg-surface-subtle hover:text-danger md:hidden"
        >
          <LogOut className="size-4" aria-hidden="true" />
        </button>

        {/* Tombol keluar (desktop) */}
        <button
          type="button"
          onClick={onLogout}
          aria-label="Keluar"
          className="ml-1 hidden items-center gap-1.5 rounded-lg border border-border-warm px-3 py-1.5 text-xs font-semibold text-text-secondary transition-colors hover:border-danger/40 hover:bg-brand-soft/40 hover:text-danger md:flex"
        >
          <LogOut className="size-3.5" aria-hidden="true" />
          Keluar
        </button>
      </div>
    </header>
  )
}
