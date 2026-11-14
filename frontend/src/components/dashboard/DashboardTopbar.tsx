import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { LogOut, Search, UserRound } from 'lucide-react'
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
  const [menuOpen, setMenuOpen] = useState(false)
  const menuRef = useRef<HTMLDivElement>(null)

  const initials = user?.name
    ? user.name
      .split(' ')
      .map((n) => n[0])
      .slice(0, 2)
      .join('')
      .toUpperCase()
    : 'U'

  useEffect(() => {
    if (!menuOpen) return
    function onPointerDown(e: PointerEvent) {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
        setMenuOpen(false)
      }
    }
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === 'Escape') setMenuOpen(false)
    }
    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.removeEventListener('pointerdown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [menuOpen])

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
        {/* Menu Profil */}
        <div ref={menuRef} className="relative">
          <button
            type="button"
            onClick={() => setMenuOpen((open) => !open)}
            aria-haspopup="menu"
            aria-expanded={menuOpen}
            aria-label={copy.dashboard.profileMenuLabel}
            className="flex shrink-0 items-center gap-2.5 rounded-full bg-white p-1 transition-colors hover:bg-surface-subtle md:pr-3.5"
          >
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-bold text-white shadow-sm ring-brand/30 transition-shadow hover:ring-2">
              {initials}
            </span>
            <span className="hidden min-w-0 flex-col items-start leading-tight md:flex">
              <span className="max-w-[150px] truncate text-xs font-bold text-text-primary">
                {user?.name}
              </span>
              <span className="max-w-[150px] truncate text-[11px] font-medium text-text-secondary">
                {user?.email}
              </span>
            </span>
          </button>

          {menuOpen && (
            <div
              role="menu"
              className="absolute right-0 top-12 z-50 flex w-56 flex-col gap-0.5 rounded-xl border border-border-warm bg-white p-1.5 shadow-lg"
            >
              <Link
                to="/profile"
                role="menuitem"
                onClick={() => setMenuOpen(false)}
                className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-xs font-semibold text-text-primary transition-colors hover:bg-surface-subtle"
              >
                <UserRound className="size-3.5 text-text-secondary" aria-hidden="true" />
                {copy.dashboard.profileMenuLabel}
              </Link>

              <button
                type="button"
                role="menuitem"
                onClick={() => {
                  setMenuOpen(false)
                  onLogout()
                }}
                className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-xs font-semibold text-danger transition-colors hover:bg-brand-soft/40"
              >
                <LogOut className="size-3.5" aria-hidden="true" />
                {copy.dashboard.logoutLabel}
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  )
}
