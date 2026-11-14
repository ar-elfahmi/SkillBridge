import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, X } from 'lucide-react'
import type { Classification, SkillLevel, UserSkill } from '@/types/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { copy } from '@/lib/copy'

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
  const [value, setValue] = useState('')
  const [level, setLevel] = useState<SkillLevel>('intermediate')

  function submit(e: React.FormEvent) {
    e.preventDefault()
    const trimmed = value.trim()
    if (!trimmed) return
    onAdd(trimmed, level)
    setValue('')
  }

  return (
    <div className="flex flex-col gap-3 rounded-2xl border border-border-warm bg-surface-subtle p-5">
      <span className="text-xs font-bold text-text-primary">
        {copy.analysesResult.selfDeclaredTitle}
      </span>

      {skills.length > 0 ? (
        <ul className="flex flex-col gap-2">
          {skills.map((s) => (
            <li
              key={s.id}
              className="flex items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 text-xs"
            >
              <span className="flex min-w-0 flex-col">
                <span className="truncate font-semibold text-text-primary">{s.skill}</span>
                {s.reported_as && (
                  <span className="truncate text-[11px] text-text-secondary">
                    dari kata kunci: {s.reported_as}
                  </span>
                )}
              </span>

              <span className="flex shrink-0 items-center gap-2">
                <span className="text-[11px] text-text-secondary">
                  {copy.analysesResult.levelOptions[s.level]}
                </span>
                <button
                  type="button"
                  onClick={() => onRemove(s.id)}
                  aria-label={`Hapus ${s.skill}`}
                  className="flex size-6 items-center justify-center rounded-md text-text-secondary transition-colors hover:bg-brand-soft/40 hover:text-danger"
                >
                  <X className="size-3.5" aria-hidden="true" />
                </button>
              </span>
            </li>
          ))}
        </ul>
      ) : reportedFromAnalysis.length > 0 ? (
        <ul className="flex flex-col gap-2">
          {reportedFromAnalysis.map((s) => (
            <li
              key={s.skill}
              className="flex items-center justify-between rounded-lg bg-white px-3 py-2 text-xs"
            >
              <span className="font-semibold text-text-primary">{s.skill}</span>
              <span className="text-[10px] text-text-secondary">{copy.status[s.classification]}</span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-[11px] text-text-secondary">{copy.analysesResult.selfDeclaredEmpty}</p>
      )}

      <form onSubmit={submit} className="flex flex-col gap-2 sm:flex-row">
        <Input
          value={value}
          onChange={(e) => setValue(e.target.value)}
          placeholder={copy.analysesResult.selfDeclaredPlaceholder}
          className="h-10 text-sm"
          aria-label={copy.analysesResult.selfDeclaredPlaceholder}
        />
        <div className="flex gap-2">
          <select
            aria-label={copy.analysesResult.levelLabel}
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
            disabled={isAdding || !value.trim()}
            className="h-10 shrink-0 gap-1.5 bg-brand px-3 text-xs font-bold text-white hover:bg-brand-hover"
          >
            <Plus className="size-3.5" aria-hidden="true" />
            {copy.analysesResult.addSkillBtn}
          </Button>
        </div>
      </form>

      <p className="border-t border-border-warm pt-2 text-[11px] text-text-secondary">
        {copy.analysesResult.selfDeclaredNote}
      </p>

      <Link to="/profile" className="w-fit text-[11px] font-bold text-brand hover:underline">
        {copy.analysesResult.selfDeclaredProfileLink}
      </Link>
    </div>
  )
}