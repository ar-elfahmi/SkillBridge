import { useLayoutEffect, useRef, useState } from 'react'
import type { FormEvent, RefObject } from 'react'
import type { RpsDocument, UpdateRpsMetadataPayload } from '@/types/api'
import { FieldError } from '@/components/common/FieldError'
import { ProcessPanel } from '@/components/common/ProcessPanel'
import { StatusNotice } from '@/components/common/StatusNotice'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { ApiRequestError } from '@/lib/api/client'
import { copy } from '@/lib/copy'
import { normalizeCourseCode } from '@/lib/labels'

interface MetadataPanelProps {
  document: RpsDocument
  onSaveMetadata: (payload: UpdateRpsMetadataPayload) => Promise<unknown>
  onStartExtraction: () => void
  isSaving: boolean
  isExtracting: boolean
  isPreparing: boolean
}

type FieldKey = 'name' | 'code' | 'credits' | 'semester' | 'studyProgram'
type FieldErrors = Partial<Record<FieldKey, string>>

const FIELD_ID: Record<FieldKey, string> = {
  name: 'courseName',
  code: 'courseCode',
  credits: 'credits',
  semester: 'semester',
  studyProgram: 'studyProgram',
}
const FIELD_ORDER: FieldKey[] = ['name', 'code', 'credits', 'semester', 'studyProgram']
const SERVER_FIELD: Record<string, FieldKey> = {
  name: 'name',
  code: 'code',
  credits: 'credits',
  semester: 'semester',
  study_program: 'studyProgram',
}

const REQUIRED_MARK = "after:ml-0.5 after:text-danger after:content-['*']"

function asText(value: string | number | null | undefined): string {
  return value === null || value === undefined ? '' : String(value)
}

const SUPPORTS_FIELD_SIZING =
  typeof CSS !== 'undefined' && typeof CSS.supports === 'function' && CSS.supports('field-sizing', 'content')

/** Textarea tumbuh mengikuti teks tanpa scrollbar. Browser tanpa `field-sizing` memakai scrollHeight. */
const AUTO_GROW_CLASS = 'min-h-14 max-h-48 overflow-hidden [field-sizing:content]'

function useAutoGrow(ref: RefObject<HTMLTextAreaElement | null>, value: string) {
  useLayoutEffect(() => {
    const el = ref.current
    if (!el || SUPPORTS_FIELD_SIZING) return
    el.style.height = 'auto'
    // scrollHeight tidak menghitung border; tanpa selisih ini scrollbar tetap muncul.
    const border = el.offsetHeight - el.clientHeight
    if (el.scrollHeight > 0) el.style.height = `${el.scrollHeight + border}px`
  }, [ref, value])
}

function FieldSkeleton() {
  return <div aria-hidden="true" className="h-12 w-full animate-pulse rounded-lg bg-progress-track motion-reduce:animate-none" />
}

export function MetadataPanel({
  document,
  onSaveMetadata,
  onStartExtraction,
  isSaving,
  isExtracting,
  isPreparing,
}: MetadataPanelProps) {
  const meta = document.course || document.detected_metadata

  const [name, setName] = useState(asText(meta?.name))
  const [code, setCode] = useState(asText(meta?.code))
  const [credits, setCredits] = useState(asText(meta?.credits))
  const [semester, setSemester] = useState(asText(meta?.semester))
  const [studyProgram, setStudyProgram] = useState(asText(meta?.study_program))
  const [synced, setSynced] = useState(meta)
  const [errors, setErrors] = useState<FieldErrors>({})
  const [saveSuccess, setSaveSuccess] = useState(false)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)
  const nameRef = useRef<HTMLTextAreaElement>(null)
  const studyRef = useRef<HTMLTextAreaElement>(null)
  useAutoGrow(nameRef, name)
  useAutoGrow(studyRef, studyProgram)

  // Metadata dari server tiba atau berubah (mis. setelah dibaca AI): isi ulang kolom.
  if (meta !== synced) {
    setSynced(meta)
    if (meta) {
      setName(asText(meta.name))
      setCode(asText(meta.code))
      setCredits(asText(meta.credits))
      setSemester(asText(meta.semester))
      setStudyProgram(asText(meta.study_program))
    }
  }

  const isDirty =
    name.trim() !== asText(meta?.name).trim() ||
    normalizeCourseCode(code) !== asText(meta?.code).trim() ||
    credits !== asText(meta?.credits) ||
    semester !== asText(meta?.semester) ||
    studyProgram.trim() !== asText(meta?.study_program).trim()

  function clearError(key: FieldKey) {
    setSaveSuccess(false)
    if (errors[key]) setErrors((prev) => ({ ...prev, [key]: undefined }))
  }

  function focusFirst(found: FieldErrors) {
    const first = FIELD_ORDER.find((key) => found[key])
    if (first) {
      // Tunda satu tick: kolom masih nonaktif selama permintaan simpan belum selesai.
      setTimeout(() => globalThis.document.getElementById(FIELD_ID[first])?.focus(), 0)
    }
  }

  function validate(): FieldErrors {
    const found: FieldErrors = {}
    if (!name.trim()) found.name = copy.rpsDetail.metadata.nameRequired
    if (!code.trim()) found.code = copy.rpsDetail.metadata.codeRequired
    if (credits && Number(credits) > 20) found.credits = copy.rpsDetail.metadata.creditsInvalid
    if (semester && (Number(semester) < 1 || Number(semester) > 14)) {
      found.semester = copy.rpsDetail.metadata.semesterInvalid
    }
    return found
  }

  async function save(): Promise<boolean> {
    const found = validate()
    setErrors(found)
    setErrorMsg(null)
    if (Object.keys(found).length > 0) {
      focusFirst(found)
      return false
    }
    try {
      await onSaveMetadata({
        name: name.trim() || null,
        code: normalizeCourseCode(code) || null,
        credits: credits ? parseInt(credits, 10) : null,
        semester: semester ? parseInt(semester, 10) : null,
        study_program: studyProgram.trim() || null,
      })
      return true
    } catch (err: unknown) {
      const serverErrors: FieldErrors = {}
      if (err instanceof ApiRequestError && err.body.errors) {
        for (const [field, messages] of Object.entries(err.body.errors)) {
          const key = SERVER_FIELD[field]
          if (key && messages[0]) serverErrors[key] = messages[0]
        }
      }
      if (Object.keys(serverErrors).length > 0) {
        setErrors(serverErrors)
        focusFirst(serverErrors)
      } else {
        setErrorMsg(copy.rpsDetail.metadata.saveFailed)
      }
      return false
    }
  }

  async function handleSave(e: FormEvent) {
    e.preventDefault()
    setSaveSuccess(false)
    if (await save()) setSaveSuccess(true)
  }

  async function handleStart() {
    setSaveSuccess(false)
    if (isDirty) {
      if (!(await save())) return
    } else {
      const found = validate()
      setErrors(found)
      if (Object.keys(found).length > 0) {
        focusFirst(found)
        return
      }
    }
    onStartExtraction()
  }

  const busy = isSaving || isExtracting || isPreparing

  if (isExtracting) {
    const summary: Array<[string, string]> = [
      [copy.rpsDetail.metadata.courseNameLabel, name.trim()],
      [copy.rpsDetail.metadata.courseCodeLabel, normalizeCourseCode(code)],
      [copy.rpsDetail.metadata.creditsLabel, credits],
      [copy.rpsDetail.metadata.semesterLabel, semester],
      [copy.rpsDetail.metadata.studyProgramLabel, studyProgram.trim()],
    ]
    return (
      <div className="flex flex-col gap-3 sm:gap-6">
        <ProcessPanel
          title={copy.rpsDetail.metadata.extractingTitle}
          description={copy.rpsDetail.metadata.extractingNote}
        />
        <section
          aria-label={copy.rpsDetail.metadata.extractingSummary}
          className="flex flex-col gap-3 rounded-xl border border-border-warm bg-white p-4 sm:p-6"
        >
          <h2 className="text-sm font-bold text-text-secondary">
            {copy.rpsDetail.metadata.extractingSummary}
          </h2>
          <dl className="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            {summary.map(([label, value]) => (
              <div key={label} className="flex flex-col">
                <dt className="text-text-secondary">{label}</dt>
                <dd className="font-semibold text-text-primary">
                  {value || copy.rpsDetail.metadata.notSet}
                </dd>
              </div>
            ))}
          </dl>
        </section>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-3 sm:gap-6">
      <div className="flex flex-col gap-3 rounded-xl border border-border-warm bg-white p-4 sm:gap-6 sm:p-6 md:p-8">
        <div className="flex flex-col gap-1">
          <h2 className="font-display text-lg font-normal text-text-primary sm:text-xl md:text-2xl">
            {copy.rpsDetail.metadata.title}
          </h2>
          <p className="text-sm leading-snug text-text-secondary">{copy.rpsDetail.metadata.note}</p>
        </div>

        <form onSubmit={handleSave} noValidate aria-busy={isPreparing} className="flex flex-col gap-4 sm:gap-5">
          {isPreparing && (
            <div
              role="status"
              aria-live="polite"
              className="flex flex-col gap-0.5 rounded-lg bg-brand-soft/60 px-4 py-3"
            >
              <p className="text-sm font-semibold text-text-primary">
                {copy.rpsDetail.metadata.preparing}
              </p>
              <p className="text-sm text-text-secondary">{copy.rpsDetail.metadata.preparingNote}</p>
            </div>
          )}

          <fieldset disabled={busy} className="flex min-w-0 flex-col gap-4 sm:gap-5">
            <legend className="sr-only">Isian metadata</legend>
            {!isPreparing && (
              <p className="text-sm text-text-secondary">{copy.rpsDetail.metadata.requiredNote}</p>
            )}

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="courseName" className={REQUIRED_MARK}>
                {copy.rpsDetail.metadata.courseNameLabel}
              </Label>
              {isPreparing ? (
                <FieldSkeleton />
              ) : (
                <Textarea
                  id="courseName"
                  ref={nameRef}
                  rows={2}
                  className={AUTO_GROW_CLASS}
                  value={name}
                  onChange={(e) => {
                    setName(e.target.value.replace(/\s*\n\s*/g, ' '))
                    clearError('name')
                  }}
                  placeholder="Contoh: Pemrograman Web"
                  aria-required="true"
                  aria-invalid={errors.name ? true : undefined}
                  aria-describedby={errors.name ? 'courseName-error' : undefined}
                />
              )}
              <FieldError id="courseName-error" message={errors.name} />
            </div>

            <div className="grid grid-cols-2 items-start gap-3 sm:gap-5">
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="courseCode" className={REQUIRED_MARK}>
                  {copy.rpsDetail.metadata.courseCodeLabel}
                </Label>
                {isPreparing ? (
                  <FieldSkeleton />
                ) : (
                  <Input
                    id="courseCode"
                    value={code}
                    onChange={(e) => {
                      setCode(e.target.value)
                      clearError('code')
                    }}
                    placeholder="Contoh: IF2013"
                    autoCapitalize="characters"
                    autoComplete="off"
                    spellCheck={false}
                    className="h-11 md:h-12"
                    aria-required="true"
                    aria-invalid={errors.code ? true : undefined}
                    aria-describedby={errors.code ? 'courseCode-error' : 'courseCode-hint'}
                  />
                )}
                <FieldError id="courseCode-error" message={errors.code} />
                {!errors.code && (
                  <p id="courseCode-hint" className="text-sm text-text-secondary">
                    {copy.rpsDetail.metadata.codeHint}
                  </p>
                )}
              </div>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="credits">{copy.rpsDetail.metadata.creditsLabel}</Label>
                {isPreparing ? (
                  <FieldSkeleton />
                ) : (
                  <Input
                    id="credits"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    maxLength={2}
                    value={credits}
                    onChange={(e) => {
                      setCredits(e.target.value.replace(/\D/g, ''))
                      clearError('credits')
                    }}
                    placeholder="Contoh: 3"
                    className="h-11 md:h-12"
                    aria-invalid={errors.credits ? true : undefined}
                    aria-describedby={errors.credits ? 'credits-error' : undefined}
                  />
                )}
                <FieldError id="credits-error" message={errors.credits} />
              </div>
            </div>

            <div className="flex flex-col gap-1.5 sm:max-w-[calc(50%-0.625rem)]">
              <Label htmlFor="semester">{copy.rpsDetail.metadata.semesterLabel}</Label>
              {isPreparing ? (
                <FieldSkeleton />
              ) : (
                <Input
                  id="semester"
                  inputMode="numeric"
                  pattern="[0-9]*"
                  maxLength={2}
                  value={semester}
                  onChange={(e) => {
                    setSemester(e.target.value.replace(/\D/g, ''))
                    clearError('semester')
                  }}
                  placeholder="Contoh: 4"
                  className="h-11 md:h-12"
                  aria-invalid={errors.semester ? true : undefined}
                  aria-describedby={errors.semester ? 'semester-error' : undefined}
                />
              )}
              <FieldError id="semester-error" message={errors.semester} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="studyProgram">{copy.rpsDetail.metadata.studyProgramLabel}</Label>
              {isPreparing ? (
                <FieldSkeleton />
              ) : (
                <Textarea
                  id="studyProgram"
                  ref={studyRef}
                  rows={1}
                  className={AUTO_GROW_CLASS}
                  value={studyProgram}
                  onChange={(e) => {
                    setStudyProgram(e.target.value.replace(/\s*\n\s*/g, ' '))
                    clearError('studyProgram')
                  }}
                  placeholder="Contoh: Teknik Informatika"
                  aria-invalid={errors.studyProgram ? true : undefined}
                  aria-describedby={errors.studyProgram ? 'studyProgram-error' : undefined}
                />
              )}
              <FieldError id="studyProgram-error" message={errors.studyProgram} />
            </div>
          </fieldset>

          {errorMsg && <StatusNotice variant="error">{errorMsg}</StatusNotice>}

          <div role="status" aria-live="polite">
            {saveSuccess && (
              <StatusNotice variant="success" live={false}>
                {copy.rpsDetail.metadata.saved}
              </StatusNotice>
            )}
          </div>

          <div className="flex flex-col gap-2 sm:mt-1 sm:flex-row-reverse sm:justify-start sm:gap-3">
            <Button
              type="button"
              onClick={() => void handleStart()}
              disabled={busy}
              className="h-11 w-full bg-brand text-white hover:bg-brand-hover active:bg-brand-hover sm:w-auto sm:px-5"
            >
              {isDirty ? copy.rpsDetail.metadata.startExtraction : copy.rpsDetail.metadata.startExtractionClean}
            </Button>

            {isDirty && (
              <Button type="submit" variant="outline" disabled={busy} className="h-11 w-full sm:w-auto sm:px-5">
                {isSaving ? copy.rpsDetail.metadata.saving : copy.rpsDetail.metadata.saveChanges}
              </Button>
            )}
          </div>
        </form>
      </div>
    </div>
  )
}
