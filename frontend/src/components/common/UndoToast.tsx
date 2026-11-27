import { useEffect } from 'react'
import { X } from 'lucide-react'
import { copy } from '@/lib/copy'

interface UndoToastProps {
  message: string
  actionLabel: string
  onAction: () => void
  onDismiss: () => void
  /** Lama tampil dalam milidetik. Cukup panjang agar pengguna papan ketik sempat memilih Urungkan. */
  duration?: number
}

/** Pesan singkat dengan satu aksi (mis. Urungkan). Wilayah live, hilang sendiri, dan bisa ditutup. */
export function UndoToast({ message, actionLabel, onAction, onDismiss, duration = 10000 }: UndoToastProps) {
  useEffect(() => {
    const timer = setTimeout(onDismiss, duration)
    return () => clearTimeout(timer)
  }, [message, duration, onDismiss])

  return (
    <div
      role="status"
      aria-live="polite"
      className="fixed inset-x-3 bottom-3 z-40 mx-auto flex max-w-md items-center gap-1 rounded-lg bg-text-primary py-1 pl-4 pr-1 text-sm text-white shadow-lg pb-[max(0.25rem,env(safe-area-inset-bottom))]"
    >
      <span className="flex-1 py-2 font-semibold">{message}</span>
      <button
        type="button"
        onClick={onAction}
        className="min-h-11 shrink-0 rounded-md px-3 font-bold underline hover:bg-white/10 active:bg-white/20"
      >
        {actionLabel}
      </button>
      <button
        type="button"
        onClick={onDismiss}
        aria-label={copy.analysesResult.dismissToast}
        className="flex size-11 shrink-0 items-center justify-center rounded-md hover:bg-white/10 active:bg-white/20"
      >
        <X className="size-4" aria-hidden="true" />
      </button>
    </div>
  )
}
