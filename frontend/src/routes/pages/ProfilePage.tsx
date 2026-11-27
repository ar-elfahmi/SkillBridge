import { useRef, useState } from 'react'
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
import { usePageTitle } from '@/lib/usePageTitle'
import { copy } from '@/lib/copy'
import { LEVEL_LABEL } from '@/lib/labels'

export function ProfilePage() {
  usePageTitle('Profil')
  const navigate = useNavigate()
  const { data: user } = useMe()
  const logout = useLogout()
  const { data: skills = [], isLoading } = useMySkills()
  const addSkill = useAddMySkill()
  const removeSkill = useRemoveMySkill()

  const [value, setValue] = useState('')
  const [level, setLevel] = useState<SkillLevel>('intermediate')
  const [status, setStatus] = useState('')
  const [undoSkill, setUndoSkill] = useState<{ skill: string; level: SkillLevel } | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)

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
    addSkill.mutate(
      { skill: trimmed, level },
      {
        onSuccess: () => {
          setValue('')
          setUndoSkill(null)
          setStatus(copy.profile.skillAdded(trimmed))
          inputRef.current?.focus()
        },
      }
    )
  }

  function handleRemove(id: number, skill: string, skillLevel: SkillLevel) {
    removeSkill.mutate(id, {
      onSuccess: () => {
        setUndoSkill({ skill, level: skillLevel })
        setStatus(copy.profile.skillRemoved(skill))
      },
    })
  }

  function handleUndo() {
    if (!undoSkill) return
    const restore = undoSkill
    addSkill.mutate(restore, {
      onSuccess: () => {
        setUndoSkill(null)
        setStatus(copy.profile.skillAdded(restore.skill))
      },
    })
  }

  return (
    <div className="flex min-h-svh flex-col bg-background">
      <DashboardTopbar user={user} onLogout={handleLogout} />

      <main className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4 px-4 py-4 sm:gap-6 sm:py-6 md:px-8 md:py-8">
        <div className="flex items-center gap-3">
          <Link
            to="/dashboard"
            aria-label={copy.dashboard.nav.home}
            className="flex size-11 shrink-0 items-center justify-center rounded-full border border-border-warm bg-white text-text-primary transition-colors hover:bg-surface-subtle active:bg-surface-subtle sm:size-10"
          >
            <ArrowLeft className="size-4" aria-hidden="true" />
          </Link>
          <h1 className="font-display text-2xl font-normal text-text-primary md:text-3xl">
            {copy.profile.title}
          </h1>
        </div>

        {/* Identitas */}
        <section className="flex flex-col items-start gap-4 rounded-xl border border-border-warm bg-white p-4 sm:flex-row sm:p-6 sm:items-center sm:justify-between">
          <div className="flex items-center gap-4">
            <div className="flex size-14 shrink-0 items-center justify-center rounded-full bg-brand text-lg font-bold text-white">
              {initials}
            </div>
            <div className="flex min-w-0 flex-col">
              <span className="truncate text-sm font-bold text-text-primary">{user?.name}</span>
              <span className="truncate text-sm text-text-secondary">{user?.email}</span>
            </div>
          </div>

          <button
            type="button"
            onClick={handleLogout}
            className="flex min-h-11 items-center gap-1.5 rounded-lg border border-border-warm px-4 text-sm font-semibold text-text-secondary active:bg-danger/10 sm:min-h-9 sm:px-3 transition-colors hover:border-danger/40 hover:bg-danger/10 hover:text-danger"
          >
            <LogOut className="size-3.5" aria-hidden="true" />
            {copy.profile.logout}
          </button>
        </section>

        {/* Skill pribadi */}
        <section className="flex flex-col gap-4 rounded-xl border border-border-warm bg-white p-4 sm:p-6">
          <div className="flex flex-col gap-1">
            <h2 className="text-base font-bold text-text-primary">
              {copy.profile.personalSkillsTitle}
            </h2>
            <p className="max-w-prose text-sm leading-relaxed text-text-secondary">
              {copy.profile.personalSkillsDesc}
            </p>
          </div>

          <form onSubmit={submit} className="flex flex-col gap-2 sm:flex-row sm:items-end">
            <div className="flex flex-1 flex-col gap-1">
              <label htmlFor="skillName" className="text-sm font-semibold text-text-secondary">
                {copy.profile.skillNameLabel}
              </label>
              <Input
                id="skillName"
                ref={inputRef}
                value={value}
                onChange={(e) => setValue(e.target.value)}
                placeholder={copy.profile.addPlaceholder}
                className="h-11 text-base md:h-11 md:text-sm"
              />
            </div>
            <div className="flex gap-2">
              <select
                aria-label={copy.profile.colLevel}
                value={level}
                onChange={(e) => setLevel(e.target.value as SkillLevel)}
                className="h-11 rounded-lg border border-field bg-white px-2 text-base font-semibold md:text-sm text-text-primary outline-none focus-visible:border-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent/40"
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
                className="h-11 shrink-0 gap-1.5 bg-brand px-3 text-sm font-bold text-white hover:bg-brand-hover"
              >
                <Plus className="size-3.5" aria-hidden="true" />
                {copy.analysesResult.addSkillBtn}
              </Button>
            </div>
          </form>

          <div role="status" aria-live="polite" className="flex min-h-5 flex-wrap items-center gap-2 text-sm text-text-secondary">
            {status && <span>{status}</span>}
            {undoSkill && (
              <button
                type="button"
                onClick={handleUndo}
                disabled={addSkill.isPending}
                className="min-h-11 font-semibold text-brand-strong underline"
              >
                {copy.profile.undo}
              </button>
            )}
          </div>

          {isLoading ? (
            <p className="text-sm text-text-secondary">Memuat skill…</p>
          ) : skills.length === 0 ? (
            <p className="rounded-lg border border-dashed border-border-warm p-6 text-center text-sm text-text-secondary">
              {copy.profile.empty}
            </p>
          ) : (
            <ul className="divide-y divide-border-warm overflow-hidden rounded-lg border border-border-warm">
              {skills.map((s) => (
                <li key={s.id} className="flex items-center justify-between gap-3 px-3 py-2 sm:px-4">
                  <div className="flex min-w-0 flex-col">
                    <span className="truncate text-sm font-semibold text-text-primary">{s.skill}</span>
                    <span className="flex min-w-0 items-center gap-1 text-sm text-text-secondary">
                      <span>{LEVEL_LABEL[s.level]}</span>
                      {s.reported_as && (
                        <>
                          <span aria-hidden="true">·</span>
                          <span className="truncate">{s.reported_as}</span>
                        </>
                      )}
                    </span>
                  </div>
                  <button
                    type="button"
                    onClick={() => handleRemove(s.id, s.skill, s.level)}
                    aria-label={`Hapus ${s.skill}`}
                    className="inline-flex size-11 shrink-0 items-center justify-center rounded-lg border border-border-warm text-danger transition-colors hover:bg-danger/10 active:bg-danger/10"
                  >
                    <Trash2 className="size-4" aria-hidden="true" />
                  </button>
                </li>
              ))}
            </ul>          )}

          <p className="text-sm text-text-secondary">{copy.profile.noteCanonical}</p>
        </section>
      </main>

      <footer className="flex h-14 shrink-0 items-center justify-center border-t border-border-warm px-4 md:justify-start md:px-8">
        <p className="text-sm font-medium text-text-secondary">{copy.footerBrand}</p>
      </footer>
    </div>
  )
}