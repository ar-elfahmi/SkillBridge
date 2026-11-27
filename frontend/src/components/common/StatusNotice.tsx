import type { ReactNode } from 'react'
import { AlertCircle, Check, Info, X } from 'lucide-react'
import { copy } from '@/lib/copy'

type Variant = 'success' | 'error' | 'info'

interface StatusNoticeProps {
  variant: Variant
  children: ReactNode
  onDismiss?: () => void
  /** Tindakan tambahan, mis. tautan "Buka dokumen itu". */
  action?: ReactNode
  /** Nonaktifkan `role` bila sudah berada di dalam wilayah live yang selalu ada. */
  live?: boolean
}

const STYLE: Record<Variant, string> = {
  success: 'bg-success-soft text-success',
  error: 'bg-danger-soft text-danger',
  info: 'bg-brand-soft text-text-primary',
}

const ICON = { success: Check, error: AlertCircle, info: Info } as const

/** Pesan status satu baris. Warna selalu disertai ikon. Pasangan warna sudah lolos kontras 4,5:1. */
export function StatusNotice({ variant, children, onDismiss, action, live = true }: StatusNoticeProps) {
  const Icon = ICON[variant]
  const role = live ? (variant === 'error' ? 'alert' : 'status') : undefined
  return (
    <div
      role={role}
      className={`flex items-start gap-2.5 rounded-lg px-3 py-2.5 text-sm font-semibold ${STYLE[variant]}`}
    >
      <Icon className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
      <div className="flex min-w-0 flex-1 flex-col items-start">
        <p>{children}</p>
        {action}
      </div>
      {onDismiss && (
        <button
          type="button"
          onClick={onDismiss}
          aria-label={copy.dashboard.dismissNotice}
          className="-my-2 -mr-2 flex size-11 shrink-0 items-center justify-center rounded-full hover:bg-black/5 active:bg-black/10"
        >
          <X className="size-4" aria-hidden="true" />
        </button>
      )}
    </div>
  )
}
