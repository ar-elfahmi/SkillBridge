import { Link } from 'react-router-dom'
import { ArrowLeft, Check, Clock3, AlertCircle } from 'lucide-react'
import type { RpsDocument } from '@/types/api'
import { copy } from '@/lib/copy'

interface RpsHeaderProps {
  document: RpsDocument
  currentStep: 1 | 2 | 3
  onStepChange?: (step: 1 | 2 | 3) => void
}

export function RpsHeader({ document, currentStep, onStepChange }: RpsHeaderProps) {
  const isExtracting = document.extraction_status === 'processing'
  const isExtracted = document.extraction_status === 'completed'

  let statusLabel: string = copy.rpsDetail.status.metadataDetected
  let statusIcon = Check
  let chipClass = 'bg-[#E5F0E8] text-success'

  if (document.parse_status === 'parsing') {
    statusLabel = copy.rpsDetail.status.parsing
    statusIcon = Clock3
    chipClass = 'bg-[#FBF0D9] text-warning'
  } else if (document.parse_status === 'needs_ocr') {
    statusLabel = copy.rpsDetail.status.needsOcr
    statusIcon = AlertCircle
    chipClass = 'bg-[#F8E4E1] text-danger'
  } else if (isExtracting) {
    statusLabel = copy.rpsDetail.status.extracting
    statusIcon = Clock3
    chipClass = 'bg-[#FBF0D9] text-warning'
  } else if (isExtracted) {
    statusLabel = copy.rpsDetail.status.extractionCompleted
    statusIcon = Check
    chipClass = 'bg-[#E5F0E8] text-success'
  }

  const StatusIcon = statusIcon

  return (
    <div className="flex flex-col gap-6">
      {/* Top Document Meta Bar */}
      <div className="flex flex-col gap-4 border-b border-border-warm pb-6 md:flex-row md:items-center md:justify-between">
        <div className="flex items-center gap-3">
          <Link
            to="/dashboard"
            aria-label="Kembali"
            className="flex size-10 items-center justify-center rounded-full border border-border-warm bg-white text-text-primary transition-colors hover:bg-surface-subtle"
          >
            <ArrowLeft className="size-4" aria-hidden="true" />
          </Link>

          <div className="flex flex-col">
            <h1 className="font-display text-base font-normal text-text-primary md:text-xl">
              {document.original_name}
            </h1>
            <span className="text-xs text-text-secondary">
              {document.page_count ? `${document.page_count} halaman` : 'Dokumen PDF'}
            </span>
          </div>
        </div>

        <span
          className={`inline-flex w-fit items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${chipClass}`}
        >
          <StatusIcon className="size-3.5" aria-hidden="true" />
          {statusLabel}
        </span>
      </div>

      {/* Stage Stepper (dapat diklik langsung untuk bypass inspeksi desain) */}
      <div className="flex items-center justify-between rounded-xl bg-white p-4 shadow-sm md:px-8">
        {/* Step 1: Konfirmasi metadata */}
        <button
          type="button"
          onClick={() => onStepChange?.(1)}
          className="flex items-center gap-3 text-left transition-opacity hover:opacity-80"
        >
          <div
            className={`flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ${currentStep > 1
              ? 'bg-success text-white'
              : 'bg-brand text-white shadow'
              }`}
          >
            {currentStep > 1 ? <Check className="size-4" /> : '1'}
          </div>
          <span
            className={`text-xs font-semibold md:text-sm ${currentStep === 1 ? 'text-brand' : 'text-text-primary'
              }`}
          >
            {copy.rpsDetail.stepper.step1}
          </span>
        </button>

        <div className="h-0.5 flex-1 bg-border-warm mx-4 hidden sm:block" />

        {/* Step 2: Tinjau kompetensi */}
        <button
          type="button"
          onClick={() => onStepChange?.(2)}
          className="flex items-center gap-3 text-left transition-opacity hover:opacity-80"
        >
          <div
            className={`flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ${currentStep > 2
              ? 'bg-success text-white'
              : currentStep === 2
                ? 'bg-brand text-white shadow'
                : 'bg-surface-canvas text-text-secondary'
              }`}
          >
            {currentStep > 2 ? <Check className="size-4" /> : '2'}
          </div>
          <span
            className={`text-xs font-semibold md:text-sm ${currentStep === 2 ? 'text-brand' : 'text-text-secondary'
              }`}
          >
            {copy.rpsDetail.stepper.step2}
          </span>
        </button>

        <div className="h-0.5 flex-1 bg-border-warm mx-4 hidden sm:block" />

        {/* Step 3: Petakan industri */}
        <Link
          to={`/analyses/new?rps=${document.id}`}
          className="flex items-center gap-3 text-left transition-opacity hover:opacity-80"
        >
          <div
            className={`flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ${currentStep === 3
              ? 'bg-brand text-white shadow'
              : 'bg-surface-canvas text-text-secondary'
              }`}
          >
            3
          </div>
          <span
            className={`text-xs font-semibold md:text-sm ${currentStep === 3 ? 'text-brand' : 'text-text-secondary'
              }`}
          >
            {copy.rpsDetail.stepper.step3}
          </span>
        </Link>
      </div>
    </div>
  )
}
