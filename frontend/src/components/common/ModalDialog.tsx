import { useEffect, useId, useRef, type ReactNode } from 'react'
import { X } from 'lucide-react'
import { useFocusTrap } from '@/lib/useFocusTrap'
import { copy } from '@/lib/copy'

interface ModalDialogProps {
  title: string
  onClose: () => void
  children: ReactNode
  /** `sheet` menempel di bawah layar pada ponsel; `dialog` selalu di tengah. */
  variant?: 'dialog' | 'sheet'
  /** Tombol tutup (X) disembunyikan untuk dialog konfirmasi yang sudah punya tombol Batal. */
  showClose?: boolean
}

const FOCUSABLE =
  'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'

/**
 * Dialog modal: fokus dikunci di dalam, Escape dan klik latar menutup, fokus kembali ke pemicu.
 * Elemen dengan `data-autofocus` mendapat fokus awal; bila tidak ada, elemen fokus pertama.
 */
export function ModalDialog({
  title,
  onClose,
  children,
  variant = 'dialog',
  showClose = true,
}: ModalDialogProps) {
  const titleId = useId()
  const panelRef = useRef<HTMLDivElement>(null)
  const onCloseRef = useRef(onClose)
  useFocusTrap(panelRef, true)

  useEffect(() => {
    onCloseRef.current = onClose
  })

  useEffect(() => {
    const opener = document.activeElement as HTMLElement | null
    const panel = panelRef.current
    const target =
      panel?.querySelector<HTMLElement>('[data-autofocus]') ??
      panel?.querySelector<HTMLElement>(FOCUSABLE) ??
      panel
    target?.focus()

    function onKeyDown(e: KeyboardEvent) {
      if (e.key === 'Escape') {
        e.stopPropagation()
        onCloseRef.current()
      }
    }
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.removeEventListener('keydown', onKeyDown)
      if (opener?.isConnected) opener.focus()
    }
  }, [])

  const placement =
    variant === 'sheet'
      ? 'items-end md:items-center'
      : 'items-center'
  const shape =
    variant === 'sheet'
      ? 'rounded-t-2xl md:rounded-2xl'
      : 'rounded-2xl'

  return (
    <div
      className={`fixed inset-0 z-50 flex justify-center bg-black/40 ${placement} ${variant === 'dialog' ? 'p-4' : 'md:p-4'}`}
      onMouseDown={(e) => {
        if (e.target === e.currentTarget) onClose()
      }}
    >
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        tabIndex={-1}
        className={`flex max-h-[85dvh] w-full flex-col gap-4 overflow-y-auto bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] outline-none md:max-w-md ${shape}`}
      >
        <div className="flex items-start justify-between gap-3">
          <h2 id={titleId} className="text-lg font-bold text-text-primary">
            {title}
          </h2>
          {showClose && (
            <button
              type="button"
              onClick={onClose}
              aria-label={copy.analysesResult.close}
              className="-mr-2 -mt-2 flex size-11 shrink-0 items-center justify-center rounded-full hover:bg-surface-subtle active:bg-surface-subtle"
            >
              <X className="size-4" aria-hidden="true" />
            </button>
          )}
        </div>
        {children}
      </div>
    </div>
  )
}
