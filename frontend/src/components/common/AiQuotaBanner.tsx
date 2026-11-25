import { AlertTriangle } from 'lucide-react'
import { useAiQuota } from '@/lib/query/hooks'

const resetFormatter = new Intl.DateTimeFormat('id-ID', {
  timeZone: 'Asia/Jakarta',
  dateStyle: 'long',
  timeStyle: 'short',
})

/** Banner saat kuota AI harian habis. Tidak tampil bila kuota tersedia atau gagal dibaca. */
export function AiQuotaBanner() {
  const { data } = useAiQuota()

  if (!data || data.available) return null

  return (
    <div
      role="alert"
      className="flex items-start gap-2 rounded-xl border border-warning/40 bg-warning/10 p-3 text-xs font-semibold text-text-primary"
    >
      <AlertTriangle aria-hidden className="mt-0.5 size-4 shrink-0" />
      <p>
        Kuota AI harian habis. Ekstraksi dan analisis baru dapat dimulai lagi setelah{' '}
        {resetFormatter.format(new Date(data.resets_at))} WIB.
      </p>
    </div>
  )
}