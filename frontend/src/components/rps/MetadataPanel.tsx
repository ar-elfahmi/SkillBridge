import { useState, useEffect } from 'react'
import type { FormEvent } from 'react'
import { Info } from 'lucide-react'
import type { RpsDocument, UpdateRpsMetadataPayload } from '@/types/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { copy } from '@/lib/copy'

interface MetadataPanelProps {
  document: RpsDocument
  onSaveMetadata: (payload: UpdateRpsMetadataPayload) => Promise<unknown>
  onStartExtraction: () => void
  isSaving: boolean
  isExtracting: boolean
}

export function MetadataPanel({
  document,
  onSaveMetadata,
  onStartExtraction,
  isSaving,
  isExtracting,
}: MetadataPanelProps) {
  const meta = document.course || document.detected_metadata

  const [name, setName] = useState(meta?.name || '')
  const [code, setCode] = useState(meta?.code || '')
  const [credits, setCredits] = useState<string>(
    meta?.credits !== null && meta?.credits !== undefined ? String(meta.credits) : ''
  )
  const [semester, setSemester] = useState<string>(
    meta?.semester !== null && meta?.semester !== undefined ? String(meta.semester) : ''
  )
  const [studyProgram, setStudyProgram] = useState(meta?.study_program || '')
  const [saveSuccess, setSaveSuccess] = useState(false)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  useEffect(() => {
    if (meta) {
      setName(meta.name || '')
      setCode(meta.code || '')
      setCredits(meta.credits !== null && meta.credits !== undefined ? String(meta.credits) : '')
      setSemester(meta.semester !== null && meta.semester !== undefined ? String(meta.semester) : '')
      setStudyProgram(meta.study_program || '')
    }
  }, [meta])

  async function handleSave(e: FormEvent) {
    e.preventDefault()
    setErrorMsg(null)
    setSaveSuccess(false)
    try {
      await onSaveMetadata({
        name: name.trim() || null,
        code: code.trim() || null,
        credits: credits ? parseInt(credits, 10) : null,
        semester: semester ? parseInt(semester, 10) : null,
        study_program: studyProgram.trim() || null,
      })
      setSaveSuccess(true)
      setTimeout(() => setSaveSuccess(false), 3000)
    } catch (err: unknown) {
      setErrorMsg('Gagal menyimpan perubahan metadata.')
    }
  }

  function handleStart() {
    if (!code.trim()) {
      setErrorMsg('Kode mata kuliah wajib diisi sebelum memulai ekstraksi kompetensi.')
      return
    }
    onStartExtraction()
  }

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-6 rounded-2xl border border-border-warm bg-white p-6 shadow-sm md:p-8">
        <div className="flex flex-col gap-1">
          <h2 className="font-display text-xl font-normal text-text-primary md:text-2xl">
            {copy.rpsDetail.metadata.title}
          </h2>
          <p className="text-xs text-text-secondary md:text-sm">
            {copy.rpsDetail.metadata.note}
          </p>
        </div>

        <form onSubmit={handleSave} className="flex flex-col gap-5">
          {/* Row 1: Nama mata kuliah */}
          <div className="flex flex-col gap-1.5">
            <Label htmlFor="courseName">{copy.rpsDetail.metadata.courseNameLabel}</Label>
            <Input
              id="courseName"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Contoh: Pemrograman Web"
              required
            />
          </div>

          {/* Row 2: Kode MK & SKS */}
          <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="courseCode">{copy.rpsDetail.metadata.courseCodeLabel}</Label>
              <Input
                id="courseCode"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                placeholder="Contoh: IF2013"
                required
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="credits">{copy.rpsDetail.metadata.creditsLabel}</Label>
              <Input
                id="credits"
                type="number"
                min="0"
                max="20"
                value={credits}
                onChange={(e) => setCredits(e.target.value)}
                placeholder="Contoh: 3"
              />
            </div>
          </div>

          {/* Row 3: Semester & Program Studi */}
          <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="semester">{copy.rpsDetail.metadata.semesterLabel}</Label>
              <Input
                id="semester"
                type="number"
                min="1"
                max="14"
                value={semester}
                onChange={(e) => setSemester(e.target.value)}
                placeholder="Contoh: 4"
              />
            </div>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="studyProgram">{copy.rpsDetail.metadata.studyProgramLabel}</Label>
              <Input
                id="studyProgram"
                value={studyProgram}
                onChange={(e) => setStudyProgram(e.target.value)}
                placeholder="Contoh: Teknik Informatika"
              />
            </div>
          </div>

          {errorMsg && (
            <p className="rounded-lg bg-danger/10 px-3 py-2 text-xs font-semibold text-danger">
              {errorMsg}
            </p>
          )}

          {saveSuccess && (
            <p className="rounded-lg bg-success/10 px-3 py-2 text-xs font-semibold text-success">
              Perubahan metadata berhasil disimpan.
            </p>
          )}

          {/* Actions */}
          <div className="mt-2 flex flex-col items-center gap-3 sm:flex-row sm:justify-end">
            <Button
              type="submit"
              variant="outline"
              disabled={isSaving || isExtracting}
              className="w-full sm:w-auto"
            >
              {isSaving ? 'Menyimpan…' : copy.rpsDetail.metadata.saveChanges}
            </Button>

            <Button
              type="button"
              onClick={handleStart}
              disabled={isExtracting || isSaving}
              className="w-full bg-brand text-white hover:bg-brand-hover sm:w-auto"
            >
              {isExtracting ? 'Memproses Ekstraksi…' : copy.rpsDetail.metadata.startExtraction}
            </Button>
          </div>
        </form>
      </div>

      {/* Derivation Note Panel */}
      <div className="flex items-center gap-3 rounded-xl border border-border-warm bg-surface-subtle p-4 text-xs text-text-secondary">
        <Info className="size-4 shrink-0 text-brand" aria-hidden="true" />
        <p>{copy.rpsDetail.metadata.derivationNote}</p>
      </div>
    </div>
  )
}
