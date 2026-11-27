import { useRef, useState } from 'react'
import type { ChangeEvent, DragEvent, ReactNode } from 'react'
import { FileUp, Upload } from 'lucide-react'
import { copy } from '@/lib/copy'

interface UploadRpsHeroProps {
  onFileSelect: (file: File) => void
  isPending: boolean
  error?: string | null
  errorAction?: ReactNode
}

export function UploadRpsHero({ onFileSelect, isPending, error, errorAction }: UploadRpsHeroProps) {
  const [isDragOver, setIsDragOver] = useState(false)
  const [fileError, setFileError] = useState<string | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  function handleFile(file: File) {
    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
      setFileError(copy.dashboard.invalidFile)
      return
    }
    if (file.size > 10 * 1024 * 1024) {
      setFileError(copy.dashboard.fileTooLarge)
      return
    }
    setFileError(null)
    onFileSelect(file)
  }

  function handleDrop(e: DragEvent<HTMLDivElement>) {
    e.preventDefault()
    setIsDragOver(false)
    const file = e.dataTransfer.files?.[0]
    if (file) handleFile(file)
  }

  function handleChange(e: ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    if (file) handleFile(file)
    e.target.value = ''
  }

  const shownError = fileError ?? error ?? null

  return (
    <section className="relative overflow-hidden rounded-2xl bg-brand text-white shadow-sm">
      <input
        ref={fileInputRef}
        type="file"
        accept="application/pdf,.pdf"
        className="hidden"
        onChange={handleChange}
        disabled={isPending}
      />

      {/* Desktop Layout (md+) */}
      <div className="hidden md:flex md:items-center md:justify-between md:gap-7 md:p-7">
        <div className="flex max-w-[420px] flex-col gap-3">
          <h2 className="font-display text-[32px] leading-tight font-normal">
            {copy.dashboard.uploadTitle}
          </h2>
          <p className="text-sm leading-relaxed text-white/90">
            {copy.dashboard.uploadDescDesktop}
          </p>
        </div>

        <div
          onDragOver={(e) => {
            e.preventDefault()
            setIsDragOver(true)
          }}
          onDragLeave={() => setIsDragOver(false)}
          onDrop={handleDrop}
          onClick={() => fileInputRef.current?.click()}
          className={`flex h-[194px] w-[360px] cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-4 text-center transition-all ${
            isDragOver
              ? 'border-white bg-white/25 scale-[1.02]'
              : 'border-white/40 bg-white/10 hover:border-white hover:bg-white/15'
          } ${isPending ? 'pointer-events-none opacity-70' : ''}`}
        >
          <div className="flex size-[52px] items-center justify-center rounded-full bg-white text-brand-strong shadow-sm">
            <Upload className="size-6" aria-hidden="true" />
          </div>
          <p className="text-sm font-semibold">{copy.dashboard.uploadDropTitle}</p>
          <p className="text-xs text-white/80">{copy.dashboard.uploadNote}</p>

          <button
            type="button"
            className="mt-1 rounded-lg bg-white px-4 py-1.5 text-xs font-bold text-brand-strong shadow hover:bg-white/90 transition-colors"
          >
            {isPending ? copy.dashboard.uploading : copy.dashboard.chooseFile}
          </button>
        </div>
      </div>

      {/* Mobile Layout (< md) */}
      <div className="flex flex-col gap-3 p-4 sm:p-5 md:hidden">
        <div className="flex flex-col gap-2">
          <h2 className="font-display text-xl font-normal sm:text-2xl">{copy.dashboard.uploadTitle}</h2>
          <p className="text-xs text-white/90">{copy.dashboard.uploadDescMobile}</p>
        </div>

        <button
          type="button"
          onClick={() => fileInputRef.current?.click()}
          disabled={isPending}
          className="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-white text-sm font-bold text-brand-strong shadow hover:bg-white/90 active:bg-white/80 disabled:opacity-50"
        >
          <FileUp className="size-4" aria-hidden="true" />
          <span>{isPending ? copy.dashboard.uploading : copy.dashboard.chooseFile}</span>
        </button>

        <p className="text-center text-xs text-white/90">{copy.dashboard.uploadNote}</p>
      </div>

      {shownError && (
        <div
          role="alert"
          className="flex flex-wrap items-center justify-center gap-x-3 bg-danger px-5 py-2 text-center text-sm font-semibold text-white"
        >
          <span>{shownError}</span>
          {!fileError && errorAction}
        </div>
      )}
    </section>
  )
}
