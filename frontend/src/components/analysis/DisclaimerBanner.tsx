import { AlertTriangle } from 'lucide-react'
import { copy } from '@/lib/copy'

/** Batasan analisis, ditampilkan di setiap hasil. */
export function DisclaimerBanner() {
  return (
    <div
      role="note"
      className="flex items-start gap-2.5 rounded-xl border border-border-warm bg-surface-canvas p-4 text-xs text-text-secondary"
    >
      <AlertTriangle className="mt-0.5 size-4 shrink-0 text-brand" aria-hidden="true" />
      <p>
        <strong className="font-semibold text-text-primary">Perhatian:</strong>{' '}
        {copy.disclaimer} Status <em>Belum ditemukan dalam RPS</em> hanya berarti skill tersebut
        belum ditemukan secara memadai di RPS yang dianalisis.
      </p>
    </div>
  )
}
