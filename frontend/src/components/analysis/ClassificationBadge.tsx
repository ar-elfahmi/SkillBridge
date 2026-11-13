import { CheckCircle2, CircleDot, CircleX } from 'lucide-react'
import type { Classification } from '@/types/api'
import { copy } from '@/lib/copy'

const STYLES: Record<Classification, { className: string; Icon: typeof CheckCircle2 }> = {
  covered: {
    className: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    Icon: CheckCircle2,
  },
  partial: {
    className: 'border-amber-200 bg-amber-50 text-amber-800',
    Icon: CircleDot,
  },
  missing: {
    className: 'border-stone-300 bg-stone-100 text-stone-700',
    Icon: CircleX,
  },
}

/** Badge status klasifikasi. Selalu memakai ikon dan teks, tidak hanya warna. */
export function ClassificationBadge({ classification }: { classification: Classification }) {
  const { className, Icon } = STYLES[classification]
  return (
    <span
      className={`inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-[11px] font-bold ${className}`}
    >
      <Icon className="size-3" aria-hidden="true" />
      {copy.status[classification]}
    </span>
  )
}
