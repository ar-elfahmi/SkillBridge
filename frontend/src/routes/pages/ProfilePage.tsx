import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { ArrowLeft, LogOut, Plus, Trash2 } from 'lucide-react'
import { DashboardTopbar } from '@/components/dashboard/DashboardTopbar'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  useAddMySkill,
  useLogout,
  useMe,
  useMySkills,
  useRemoveMySkill,
} from '@/lib/query/hooks'
import type { SkillLevel } from '@/types/api'
import { copy } from '@/lib/copy'

export function ProfilePage() {
  const navigate = useNavigate()
  const { data: user } = useMe()
  const logout = useLogout()
  const { data: skills = [], isLoading } = useMySkills()
  const addSkill = useAddMySkill()
  const removeSkill = useRemoveMySkill()

  const [value, setValue] = useState('')
  const [level, setLevel] = useState<SkillLevel>('intermediate')

  const initials = user?.name
    ? user.name
        .split(' ')
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
    : 'U'

  function handleLogout() {
    logout.mutate(undefined, { onSuccess: () => navigate('/login') })
  }

  function submit(e: React.FormEvent) {
    e.preventDefault()
    const trimmed = value.trim()
    if (!trimmed) return
    addSkill.mutate({ skill: trimmed, level }, { onSuccess: () => setValue('') })
  }

  return (
    <div className="flex min-h-svh flex-col bg-background">
      <DashboardTopbar user={user} onLogout={handleLogout} />

      <main className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 px-4 py-6 md:px-8 md:py-8">
        <div className="flex items-center gap-3">
          <Link
            to="/dashboard"
            aria-label={copy.dashboard.nav.home}
            className="flex size-10 shrink-0 items-center justify-center rounded-full border border-border-warm bg-white text-text-primary transition-colors hover:bg-surface-subtle"
          >
            <ArrowLeft className="size-4" aria-hidden="true" />
          </Link>
          <h1 className="font-display text-2xl font-normal text-text-primary md:text-3xl">
            {copy.profile.title}
          </h1>
        </div>

        {/* Identitas */}
        <section className="flex flex-col items-start gap-4 rounded-2xl border border-border-warm bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-4">
            <div className="flex size-14 shrink-0 items-center justify-center rounded-full bg-brand text-lg font-bold text-white">
              {initials}
            </div>
            <div className="flex min-w-0 flex-col">
              <span className="truncate text-sm font-bold text-text-primary">{user?.name}</span>
              <span className="truncate text-xs text-text-secondary">{user?.email}</span>
            </div>
          </div>

          <button
            type="button"
            onClick={handleLogout}
            className="flex items-center gap-1.5 rounded-lg border border-border-warm px-3 py-1.5 text-xs font-semibold text-text-secondary transition-colors hover:border-danger/40 hover:bg-brand-soft/40 hover:text-danger"
          >
            <LogOut className="size-3.5" aria-hidden="true" />
            {copy.profile.logout}
          </button>
        </section>

        {/* Skill pribadi */}
        <section className="flex flex-col gap-4 rounded-2xl border border-border-warm bg-white p-6 shadow-sm">
          <div className="flex flex-col gap-1">
            <h2 className="text-base font-bold text-text-primary">
              {copy.profile.personalSkillsTitle}
            </h2>
            <p className="max-w-prose text-xs leading-relaxed text-text-secondary">
              {copy.profile.personalSkillsDesc}
            </p>
          </div>

          <form onSubmit={submit} className="flex flex-col gap-2 sm:flex-row">
            <Input
              value={value}
              onChange={(e) => setValue(e.target.value)}
              placeholder={copy.profile.addPlaceholder}
              aria-label={copy.profile.addPlaceholder}
              className="h-10 text-sm"
            />
            <div className="flex gap-2">
              <select
                aria-label={copy.profile.colLevel}
                value={level}
                onChange={(e) => setLevel(e.target.value as SkillLevel)}
                className="h-10 rounded-lg border border-border-warm bg-white px-2 text-xs font-semibold text-text-primary outline-none focus:border-brand"
              >
                {(['beginner', 'intermediate', 'advanced'] as const).map((l) => (
                  <option key={l} value={l}>
                    {copy.analysesResult.levelOptions[l]}
                  </option>
                ))}
              </select>
              <Button
                type="submit"
                disabled={addSkill.isPending || !value.trim()}
                className="h-10 shrink-0 gap-1.5 bg-brand px-3 text-xs font-bold text-white hover:bg-brand-hover"
              >
                <Plus className="size-3.5" aria-hidden="true" />
                {copy.analysesResult.addSkillBtn}
              </Button>
            </div>
          </form>

          {isLoading ? (
            <p className="text-xs text-text-secondary">Memuat skill…</p>
          ) : skills.length === 0 ? (
            <p className="rounded-xl border border-dashed border-border-warm p-6 text-center text-xs text-text-secondary">
              {copy.profile.empty}
            </p>
          ) : (
            <div className="overflow-hidden rounded-xl border border-border-warm">
              <table className="w-full text-left text-xs">
                <thead className="border-b border-border-warm bg-surface-subtle font-semibold text-text-secondary">
                  <tr>
                    <th className="px-4 py-2.5">{copy.profile.colSkill}</th>
                    <th className="px-4 py-2.5">{copy.profile.colReportedAs}</th>
                    <th className="px-4 py-2.5">{copy.profile.colLevel}</th>
                    <th className="px-4 py-2.5 text-right">{copy.profile.colAction}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border-warm">
                  {skills.map((s) => (
                    <tr key={s.id}>
                      <td className="px-4 py-2.5 font-semibold text-text-primary">{s.skill}</td>
                      <td className="px-4 py-2.5 text-text-secondary">{s.reported_as ?? '—'}</td>
                      <td className="px-4 py-2.5 text-text-secondary">
                        {copy.analysesResult.levelOptions[s.level]}
                      </td>
                      <td className="px-4 py-2.5 text-right">
                        <button
                          type="button"
                          onClick={() => removeSkill.mutate(s.id)}
                          aria-label={`Hapus ${s.skill}`}
                          className="inline-flex size-7 items-center justify-center rounded-lg border border-border-warm text-danger transition-colors hover:bg-brand-soft/40"
                        >
                          <Trash2 className="size-3.5" aria-hidden="true" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <p className="text-[11px] text-text-secondary">{copy.profile.noteCanonical}</p>
        </section>
      </main>

      <footer className="flex h-14 shrink-0 items-center justify-center border-t border-border-warm px-4 md:justify-start md:px-8">
        <p className="text-[11px] font-medium text-text-secondary">{copy.footerBrand}</p>
      </footer>
    </div>
  )
}