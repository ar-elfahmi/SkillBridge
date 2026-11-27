import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, X } from 'lucide-react'
import type { Classification, SkillLevel, UserSkill } from '@/types/api'
import { ModalDialog } from '@/components/common/ModalDialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { copy } from '@/lib/copy'
import { LEVEL_LABEL } from '@/lib/labels'

interface SelfDeclaredSkillsPanelProps {
  skills: UserSkill[]
  reportedFromAnalysis: Array<{ skill: string; classification: Classification }>
  onAdd: (skill: string, level: SkillLevel) => void
  onRemove: (id: number) => void
  isAdding: boolean
}

export function SelfDeclaredSkillsPanel({
  skills,
  reportedFromAnalysis,
  onAdd,
  onRemove,
  isAdding,
}: SelfDeclaredSkillsPanelProps) {
  const [sheetOpen, setSheetOpen] = useState(false)
  const [value, setValue] = useState('')
  const [level, setLevel] = useState<SkillLevel>('intermediate')

  function submit(e: React.FormEvent) {
    e.preventDefault()
    const trimmed = value.trim()
    if (!trimmed) return
    onAdd(trimmed, level)
    setValue('')
    setSheetOpen(false)
  }

  return (
    <section
      aria-labelledby="self-declared-title"
      className="flex flex-col gap-3 rounded-xl border border-border-warm bg-surface-subtle p-4 sm:p-5"
    >
      <div className="flex items-center justify-between gap-3">
        <h3 id="self-declared-title" className="text-sm font-bold text-text-primary">
          {copy.analysesResult.selfDeclaredTitle}
        </h3>
        <Button
          type="button"
          variant="outline"
          onClick={() => setSheetOpen(true)}
          aria-label={copy.analysesResult.addSkillBtn}
          aria-haspopup="dialog"
          className="h-11 shrink-0 gap-1.5 bg-white px-3 text-sm font-semibold"
        >
          <Plus className="size-4" aria-hidden="true" />
          {copy.analysesResult.addShort}
        </Button>
      </div>

      {skills.length > 0 ? (
        <ul className="flex flex-wrap gap-2">
          {skills.map((s) => (
            <li
              key={s.id}
              className="flex min-h-11 items-center gap-1 rounded-lg border border-border-warm bg-white pl-3 text-sm"
            >
              <span className="font-semibold text-text-primary">{s.skill}</span>
              <span className="text-text-secondary">{LEVEL_LABEL[s.level]}</span>
              <button
                type="button"
                onClick={() => onRemove(s.id)}
                aria-label={`Hapus ${s.skill}`}
                className="flex size-11 items-center justify-center rounded-md text-text-secondary transition-colors hover:bg-danger/10 hover:text-danger active:bg-danger/10"
              >
                <X className="size-4" aria-hidden="true" />
              </button>
            </li>
          ))}
        </ul>
      ) : reportedFromAnalysis.length > 0 ? (
        <ul className="flex flex-wrap gap-2">
          {reportedFromAnalysis.map((s) => (
            <li
              key={s.skill}
              className="flex min-h-11 items-center gap-2 rounded-lg border border-border-warm bg-white px-3 text-sm"
            >
              <span className="font-semibold text-text-primary">{s.skill}</span>
              <span className="text-text-secondary">{copy.status[s.classification]}</span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-sm text-text-secondary">{copy.analysesResult.selfDeclaredEmpty}</p>
      )}

      <p className="border-t border-border-warm pt-2 text-sm text-text-secondary">
        {copy.analysesResult.selfDeclaredNote}
      </p>

      <Link
        to="/profile"
        className="inline-flex min-h-11 w-fit items-center text-sm font-bold text-brand-strong hover:underline"
      >
        {copy.analysesResult.selfDeclaredProfileLink}
      </Link>

      {sheetOpen && (
        <ModalDialog title={copy.analysesResult.addSheetTitle} variant="sheet" onClose={() => setSheetOpen(false)}>
          <form onSubmit={submit} className="flex flex-col gap-3">
            <div className="flex flex-col gap-1">
              <label htmlFor="selfSkillName" className="text-sm font-semibold text-text-secondary">
                {copy.analysesResult.skillNameLabel}
              </label>
              <Input
                id="selfSkillName"
                data-autofocus
                value={value}
                onChange={(e) => setValue(e.target.value)}
                placeholder={copy.analysesResult.selfDeclaredPlaceholder}
                autoComplete="off"
                className="h-11 md:h-11"
              />
            </div>
            <div className="flex flex-col gap-1">
              <label htmlFor="selfSkillLevel" className="text-sm font-semibold text-text-secondary">
                {copy.analysesResult.levelLabel}
              </label>
              <select
                id="selfSkillLevel"
                value={level}
                onChange={(e) => setLevel(e.target.value as SkillLevel)}
                className="h-11 rounded-lg border border-field bg-white px-3 text-base font-semibold text-text-primary outline-none focus-visible:border-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent/40 md:text-sm"
              >
                {(['beginner', 'intermediate', 'advanced'] as const).map((l) => (
                  <option key={l} value={l}>
                    {copy.analysesResult.levelOptions[l]}
                  </option>
                ))}
              </select>
            </div>
            <Button
              type="submit"
              disabled={isAdding || !value.trim()}
              className="h-11 w-full bg-brand px-3 text-sm font-bold text-white hover:bg-brand-hover"
            >
              {copy.analysesResult.addSkillBtn}
            </Button>
          </form>
        </ModalDialog>
      )}
    </section>
  )
}
