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

  const steps = [
    { index: 1 as const, label: copy.rpsDetail.stepper.step1 },
    { index: 2 as const, label: copy.rpsDetail.stepper.step2 },
    { index: 3 as const, label: copy.rpsDetail.stepper.step3 },
  ]

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
            <h1 className="font-display text-xl font-normal text-text-primary md:text-3xl">
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

      {/* Stage Stepper — dapat diklik untuk bypass inspeksi desain */}
      <ol className="grid grid-cols-[auto_1fr_auto_1fr_auto] items-center gap-3 rounded-xl bg-white p-4 shadow-sm md:gap-5 md:px-6">
        {steps.map((step, i) => {
          const done = currentStep > step.index
          const active = currentStep === step.index
          const marker = done ? 'bg-success text-white' : active ? 'bg-brand text-white shadow-sm' : 'bg-surface-canvas text-text-secondary'

          return (
            <li key={step.index} className="contents">
              {i > 0 && <span aria-hidden="true" className="h-0.5 w-full rounded-full bg-border-warm" />}

              <button
                type="button"
                onClick={() => onStepChange?.(step.index)}
                aria-current={active ? 'step' : undefined}
                aria-label={step.label}
                className="flex items-center gap-2.5 rounded-lg text-left outline-none transition-opacity hover:opacity-80 focus-visible:ring-2 focus-visible:ring-brand/40 md:gap-3"
              >
                <span
                  className={`flex size-8 shrink-0 items-center justify-center rounded-full text-[13px] font-semibold tabular-nums leading-none ${marker}`}
                >
                  {done ? <Check className="size-4" aria-hidden="true" /> : step.index}
                </span>
                <span
                  className={`hidden whitespace-nowrap text-sm font-semibold leading-none sm:inline ${
                    active ? 'text-brand' : done ? 'text-text-primary' : 'text-text-secondary'
                  }`}
                >
                  {step.label}
                </span>
              </button>
            </li>
          )
        })}
      </ol>
    </div>
  )
}
