import type { ReactNode } from 'react'
import { Check, Circle, Loader2 } from 'lucide-react'

export interface ProcessStep {
  label: string
  state: 'done' | 'active' | 'pending'
}

interface ProcessPanelProps {
  title: string
  description?: string
  steps?: ProcessStep[]
  children?: ReactNode
}

const STATE_TEXT = { done: 'selesai', active: 'sedang berjalan', pending: 'menunggu' } as const

/** Panel proses yang berjalan di latar: spinner, perkiraan waktu, langkah, dan aksi opsional. */
export function ProcessPanel({ title, description, steps, children }: ProcessPanelProps) {
  return (
    <section
      role="status"
      aria-live="polite"
      className="flex flex-col gap-4 rounded-xl border border-border-warm bg-white p-5 sm:p-6"
    >
      <div className="flex items-start gap-3">
        <Loader2
          className="mt-0.5 size-5 shrink-0 animate-spin text-brand-accent motion-reduce:animate-none"
          aria-hidden="true"
        />
        <div className="flex flex-col gap-1">
          <h2 className="text-base font-bold text-text-primary">{title}</h2>
          {description && <p className="text-sm text-text-secondary">{description}</p>}
        </div>
      </div>

      {steps && steps.length > 0 && (
        <ol className="flex flex-col gap-2">
          {steps.map((step) => (
            <li
              key={step.label}
              aria-current={step.state === 'active' ? 'step' : undefined}
              className="flex items-center gap-2.5 text-sm"
            >
              {step.state === 'done' ? (
                <Check className="size-4 shrink-0 text-success" aria-hidden="true" />
              ) : step.state === 'active' ? (
                <Loader2
                  className="size-4 shrink-0 animate-spin text-brand-accent motion-reduce:animate-none"
                  aria-hidden="true"
                />
              ) : (
                <Circle className="size-4 shrink-0 text-text-secondary" aria-hidden="true" />
              )}
              <span
                className={
                  step.state === 'pending' ? 'text-text-secondary' : 'font-semibold text-text-primary'
                }
              >
                {step.label}
                <span className="sr-only">: {STATE_TEXT[step.state]}</span>
              </span>
            </li>
          ))}
        </ol>
      )}

      {children}
    </section>
  )
}
