import { useEffect, useRef } from 'react'
import { X } from 'lucide-react'
import { useJobsList } from '@/lib/query/hooks'
import { useFocusTrap } from '@/lib/useFocusTrap'
import { copy } from '@/lib/copy'

const SKILL_LIMIT = 20

interface CareerSkillsDialogProps {
  careerId: number
  careerName: string
  onClose: () => void
}

/** Lets the student inspect which skills the auto-selected jobs of a profession contribute. */
export function CareerSkillsDialog({ careerId, careerName, onClose }: CareerSkillsDialogProps) {
  const { data: jobs = [], isLoading, isError } = useJobsList(careerId)
  const dialogRef = useRef<HTMLDivElement>(null)
  const closeRef = useRef<HTMLButtonElement>(null)
  useFocusTrap(dialogRef, true)

  useEffect(() => {
    closeRef.current?.focus()
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [onClose])

  const demand = new Map<string, { name: string; count: number }>()
  for (const job of jobs) {
    if (job.extraction_status !== 'completed') continue
    const seen = new Set<string>()
    for (const skill of job.skills ?? []) {
      const key = skill.canonical_skill.toLowerCase()
      if (seen.has(key)) continue
      seen.add(key)
      const entry = demand.get(key)
      if (entry) entry.count += 1
      else demand.set(key, { name: skill.canonical_skill, count: 1 })
    }
  }
  const skills = [...demand.values()]
    .sort((a, b) => b.count - a.count || a.name.localeCompare(b.name))
    .slice(0, SKILL_LIMIT)

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="career-skills-title"
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/30 md:items-center"
      onClick={onClose}
    >
      <div
        ref={dialogRef}
        onClick={(e) => e.stopPropagation()}
        className="flex max-h-[85dvh] w-full flex-col gap-4 overflow-y-auto rounded-t-2xl bg-white p-6 md:max-w-md md:rounded-2xl"
      >
        <div className="flex items-start justify-between gap-3">
          <h2 id="career-skills-title" className="text-lg font-bold text-text-primary">
            {copy.analysesNew.skillsDialogTitle(careerName)}
          </h2>
          <button
            type="button"
            ref={closeRef}
            onClick={onClose}
            aria-label={copy.analysesResult.close}
            className="flex size-11 shrink-0 items-center justify-center rounded-full hover:bg-surface-subtle"
          >
            <X className="size-4" aria-hidden="true" />
          </button>
        </div>

        {isLoading ? (
          <p role="status" className="text-sm text-text-secondary">
            {copy.analysesNew.skillsDialogLoading}
          </p>
        ) : isError ? (
          <p role="alert" className="text-sm font-semibold text-danger">
            {copy.analysesNew.skillsDialogError}
          </p>
        ) : skills.length === 0 ? (
          <p className="text-sm text-text-secondary">{copy.analysesNew.skillsDialogEmpty}</p>
        ) : (
          <ul className="flex flex-col divide-y divide-border-warm">
            {skills.map((s) => (
              <li key={s.name} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                <span className="font-semibold text-text-primary">{s.name}</span>
                <span className="text-text-secondary">
                  {copy.analysesNew.skillsDialogDemand(s.count)}
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  )
}
