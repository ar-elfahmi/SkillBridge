import { useRef, useState } from 'react'
import type { ChangeEvent, DragEvent } from 'react'
import { FileUp, Upload } from 'lucide-react'
import { copy } from '@/lib/copy'

interface UploadRpsHeroProps {
  onFileSelect: (file: File) => void
  isPending: boolean
  error?: string | null
}

export function UploadRpsHero({ onFileSelect, isPending, error }: UploadRpsHeroProps) {
  const [isDragOver, setIsDragOver] = useState(false)
  const fileInputRef = useRef<HTMLInputElement>(null)

  function handleFile(file: File) {
    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
      alert(copy.dashboard.invalidFile)
      return
    }
    if (file.size > 10 * 1024 * 1024) {
      alert(copy.dashboard.fileTooLarge)
      return
    }
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
  }

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
          <span className="w-fit rounded-full bg-white/15 px-3 py-1 text-[11px] font-bold tracking-wide uppercase">
            {copy.dashboard.uploadEyebrow}
          </span>
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
          <div className="flex size-[52px] items-center justify-center rounded-full bg-white text-brand shadow-sm">
            <Upload className="size-6" aria-hidden="true" />
          </div>
          <p className="text-sm font-semibold">{copy.dashboard.uploadDropTitle}</p>
          <p className="text-xs text-white/80">{copy.dashboard.uploadNote}</p>

          <button
            type="button"
            className="mt-1 rounded-lg bg-white px-4 py-1.5 text-xs font-bold text-brand shadow hover:bg-white/90 transition-colors"
          >
            {isPending ? copy.dashboard.uploading : copy.dashboard.chooseFile}
          </button>
        </div>
      </div>

      {/* Mobile Layout (< md) */}
      <div className="flex flex-col gap-4 p-5 md:hidden">
        <div className="flex flex-col gap-2">
          <span className="w-fit rounded-full bg-white/15 px-3 py-0.5 text-[10px] font-bold tracking-wide uppercase">
            {copy.dashboard.uploadEyebrow}
          </span>
          <h2 className="font-display text-2xl font-normal">{copy.dashboard.uploadTitle}</h2>
          <p className="text-xs text-white/90">{copy.dashboard.uploadDescMobile}</p>
        </div>

        <button
          type="button"
          onClick={() => fileInputRef.current?.click()}
          disabled={isPending}
          className="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-white text-sm font-bold text-brand shadow hover:bg-white/90 disabled:opacity-50"
        >
          <FileUp className="size-4" aria-hidden="true" />
          <span>{isPending ? copy.dashboard.uploading : copy.dashboard.chooseFile}</span>
        </button>

        <p className="text-center text-[11px] text-white/80">{copy.dashboard.uploadNote}</p>
      </div>

      {error && (
        <div className="bg-danger/90 px-5 py-2 text-center text-xs font-semibold text-white">
          {error}
        </div>
      )}
    </section>
  )
}
