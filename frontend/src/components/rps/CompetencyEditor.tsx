import { useState, type FormEvent } from 'react'
import { FieldError } from '@/components/common/FieldError'
import { StatusNotice } from '@/components/common/StatusNotice'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { copy } from '@/lib/copy'

interface CompetencyEditorProps {
  /** Awalan id agar tampilan desktop dan mobile tidak berbagi id yang sama. */
  idPrefix: string
  initialSkill: string
  initialCompetency: string
  onSave: (skill: string, competency: string) => Promise<unknown>
  onCancel: () => void
}

/** Editor kompetensi yang dipakai tabel desktop dan kartu mobile. Kutipan RPS tidak ikut diubah. */
export function CompetencyEditor({
  idPrefix,
  initialSkill,
  initialCompetency,
  onSave,
  onCancel,
}: CompetencyEditorProps) {
  const [skill, setSkill] = useState(initialSkill)
  const [competency, setCompetency] = useState(initialCompetency)
  const [errors, setErrors] = useState<{ skill?: string; competency?: string }>({})
  const [failed, setFailed] = useState(false)
  const [isSaving, setIsSaving] = useState(false)

  const skillId = `${idPrefix}-skill`
  const competencyId = `${idPrefix}-competency`

  async function submit(e: FormEvent) {
    e.preventDefault()
    const found: typeof errors = {}
    if (!skill.trim()) found.skill = copy.rpsDetail.review.skillRequired
    if (!competency.trim()) found.competency = copy.rpsDetail.review.competencyRequired
    setErrors(found)
    setFailed(false)
    if (found.skill || found.competency) {
      document.getElementById(found.skill ? skillId : competencyId)?.focus()
      return
    }
    setIsSaving(true)
    try {
      await onSave(skill.trim(), competency.trim())
      onCancel()
    } catch {
      setFailed(true)
      setIsSaving(false)
    }
  }

  return (
    <form
      onSubmit={submit}
      noValidate
      onKeyDown={(e) => {
        if (e.key === 'Escape') onCancel()
      }}
      className="flex flex-col gap-3"
    >
      <div className="flex flex-col gap-1">
        <label htmlFor={skillId} className="text-sm font-semibold text-text-secondary">
          {copy.rpsDetail.review.editSkillLabel}
        </label>
        <Input
          id={skillId}
          value={skill}
          autoFocus
          onChange={(e) => setSkill(e.target.value)}
          className="h-11 md:h-11"
          aria-invalid={errors.skill ? true : undefined}
          aria-describedby={errors.skill ? `${skillId}-error` : undefined}
        />
        <FieldError id={`${skillId}-error`} message={errors.skill} />
      </div>
      <div className="flex flex-col gap-1">
        <label htmlFor={competencyId} className="text-sm font-semibold text-text-secondary">
          {copy.rpsDetail.review.editCompetencyLabel}
        </label>
        <Textarea
          id={competencyId}
          rows={3}
          value={competency}
          onChange={(e) => setCompetency(e.target.value)}
          aria-invalid={errors.competency ? true : undefined}
          aria-describedby={errors.competency ? `${competencyId}-error` : undefined}
        />
        <FieldError id={`${competencyId}-error`} message={errors.competency} />
      </div>
      {failed && <StatusNotice variant="error">{copy.rpsDetail.review.editFailed}</StatusNotice>}
      <div className="flex gap-2">
        <Button
          type="submit"
          disabled={isSaving}
          className="h-11 flex-1 bg-brand px-4 text-white hover:bg-brand-hover sm:flex-none"
        >
          {copy.rpsDetail.review.editSave}
        </Button>
        <Button
          type="button"
          variant="outline"
          onClick={onCancel}
          disabled={isSaving}
          className="h-11 flex-1 px-4 sm:flex-none"
        >
          {copy.rpsDetail.review.editCancel}
        </Button>
      </div>
    </form>
  )
}
