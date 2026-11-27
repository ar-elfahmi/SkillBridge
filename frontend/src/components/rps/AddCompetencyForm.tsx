import { useState, type FormEvent } from 'react'
import type { CreateCompetencyPayload } from '@/types/api'
import { FieldError } from '@/components/common/FieldError'
import { StatusNotice } from '@/components/common/StatusNotice'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { ApiRequestError } from '@/lib/api/client'
import { copy } from '@/lib/copy'

interface AddCompetencyFormProps {
  onCreate: (payload: CreateCompetencyPayload) => Promise<unknown>
  onCancel: () => void
}

type Errors = { skill?: string; competency?: string }

export function AddCompetencyForm({ onCreate, onCancel }: AddCompetencyFormProps) {
  const [skill, setSkill] = useState('')
  const [competency, setCompetency] = useState('')
  const [errors, setErrors] = useState<Errors>({})
  const [failed, setFailed] = useState(false)
  const [isSaving, setIsSaving] = useState(false)

  async function submit(e: FormEvent) {
    e.preventDefault()
    const found: Errors = {}
    if (!skill.trim()) found.skill = copy.rpsDetail.review.skillRequired
    if (!competency.trim()) found.competency = copy.rpsDetail.review.competencyRequired
    setErrors(found)
    setFailed(false)
    if (found.skill || found.competency) {
      document.getElementById(found.skill ? 'newSkill' : 'newCompetency')?.focus()
      return
    }
    setIsSaving(true)
    try {
      await onCreate({ skill: skill.trim(), competency: competency.trim() })
      onCancel()
    } catch (err: unknown) {
      const server: Errors = {}
      if (err instanceof ApiRequestError && err.body.errors) {
        server.skill = err.body.errors.skill?.[0]
        server.competency = err.body.errors.competency?.[0]
      }
      if (server.skill || server.competency) setErrors(server)
      else setFailed(true)
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
      className="flex flex-col gap-4 rounded-lg border border-border-warm bg-white p-4 sm:p-5"
    >
      <h3 className="text-sm font-bold text-text-primary">{copy.rpsDetail.review.addTitle}</h3>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-1">
          <label htmlFor="newSkill" className="text-sm font-semibold text-text-secondary">
            {copy.rpsDetail.review.editSkillLabel}
          </label>
          <Input
            id="newSkill"
            value={skill}
            onChange={(e) => setSkill(e.target.value)}
            placeholder={copy.rpsDetail.review.addSkillPlaceholder}
            className="h-11 md:h-11"
            aria-invalid={errors.skill ? true : undefined}
            aria-describedby={errors.skill ? 'newSkill-error' : undefined}
          />
          <FieldError id="newSkill-error" message={errors.skill} />
        </div>
        <div className="flex flex-col gap-1">
          <label htmlFor="newCompetency" className="text-sm font-semibold text-text-secondary">
            {copy.rpsDetail.review.editCompetencyLabel}
          </label>
          <Textarea
            id="newCompetency"
            rows={3}
            value={competency}
            onChange={(e) => setCompetency(e.target.value)}
            placeholder={copy.rpsDetail.review.addCompetencyPlaceholder}
            aria-invalid={errors.competency ? true : undefined}
            aria-describedby={errors.competency ? 'newCompetency-error' : undefined}
          />
          <FieldError id="newCompetency-error" message={errors.competency} />
        </div>
      </div>
      {failed && <StatusNotice variant="error">{copy.rpsDetail.review.addFailed}</StatusNotice>}
      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button
          type="button"
          variant="outline"
          onClick={onCancel}
          disabled={isSaving}
          className="h-11 w-full px-4 sm:w-auto"
        >
          {copy.rpsDetail.review.bulkCancel}
        </Button>
        <Button
          type="submit"
          disabled={isSaving}
          className="h-11 w-full bg-brand px-4 text-white hover:bg-brand-hover sm:w-auto"
        >
          {isSaving ? copy.rpsDetail.review.addSaving : copy.rpsDetail.review.addSave}
        </Button>
      </div>
    </form>
  )
}
