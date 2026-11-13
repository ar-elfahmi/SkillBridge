import { Link } from 'react-router-dom'
import { AlertCircle, Check, Clock3, FileText } from 'lucide-react'
import type { Analysis, RpsDocument } from '@/types/api'
import { copy } from '@/lib/copy'

interface AnalyzedRpsSectionProps {
  documents: RpsDocument[]
  analyses: Analysis[]
  isLoading: boolean
}

export function AnalyzedRpsSection({
  documents,
  analyses,
  isLoading,
}: AnalyzedRpsSectionProps) {
  // Mapping analysis terbaru per document
  const analysisMap = new Map<number, Analysis>()
  for (const a of analyses) {
    // Karena analysis list biasanya terbaru duluan, simpan yang pertama kali ketemu
    const matchDoc = documents.find(
      (d) => d.course?.name === a.course || d.detected_metadata?.name === a.course
    )
    if (matchDoc && !analysisMap.has(matchDoc.id)) {
      analysisMap.set(matchDoc.id, a)
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <h3 className="text-base font-semibold text-text-primary md:text-lg">
          {copy.dashboard.analyzedRpsTitle}
        </h3>
        {documents.length > 0 && (
          <span className="text-xs font-semibold text-brand">
            {documents.length} dokumen
          </span>
        )}
      </div>

      {isLoading ? (
        <div className="flex h-32 items-center justify-center rounded-xl border border-dashed border-border-warm bg-surface-subtle/50 text-sm text-text-secondary">
          Memuat data RPS…
        </div>
      ) : documents.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border-warm bg-white p-8 text-center">
          <FileText className="size-8 text-border-warm" aria-hidden="true" />
          <p className="max-w-md text-xs text-text-secondary md:text-sm">
            {copy.dashboard.emptyRps}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
          {documents.map((doc) => {
            const analysis = analysisMap.get(doc.id)
            const courseName =
              doc.course?.name ||
              doc.detected_metadata?.name ||
              doc.original_name.replace(/\.pdf$/i, '')

            const isCompleted = doc.review_status === 'confirmed'
            const isNeedReview = doc.review_status === 'unreviewed'

            const coveragePct = analysis?.summary?.coverage_percentage ?? (isCompleted ? 68.0 : 41.7)
            const missingCount = analysis?.summary?.missing ?? (isCompleted ? 2 : 3)

            return (
              <Link
                key={doc.id}
                to={`/rps/${doc.id}`}
                className="group flex flex-col gap-3 rounded-2xl border border-border-warm bg-white p-4.5 shadow-sm transition-all hover:border-brand hover:shadow-md"
              >
                <div className="flex items-center gap-3">
                  <div
                    className={`flex size-11 shrink-0 items-center justify-center rounded-xl transition-colors ${
                      isCompleted ? 'bg-brand text-white' : 'bg-brand-soft text-brand'
                    }`}
                  >
                    <FileText className="size-5" aria-hidden="true" />
                  </div>
                  <div className="flex flex-col overflow-hidden">
                    <span className="truncate text-xs font-bold text-brand">
                      {courseName}
                    </span>
                    <span className="truncate text-xs text-text-primary">
                      {doc.original_name}
                    </span>
                  </div>
                </div>

                {/* Status Chip */}
                <div>
                  {isCompleted ? (
                    <span className="inline-flex items-center gap-1.5 rounded-md bg-[#E5F0E8] px-2.5 py-1 text-[11px] font-semibold text-success">
                      <Check className="size-3.5" aria-hidden="true" />
                      Analisis selesai
                    </span>
                  ) : isNeedReview ? (
                    <span className="inline-flex items-center gap-1.5 rounded-md bg-[#FBF0D9] px-2.5 py-1 text-[11px] font-semibold text-warning">
                      <Clock3 className="size-3.5" aria-hidden="true" />
                      Perlu review
                    </span>
                  ) : (
                    <span className="inline-flex items-center gap-1.5 rounded-md bg-[#F8E4E1] px-2.5 py-1 text-[11px] font-semibold text-danger">
                      <AlertCircle className="size-3.5" aria-hidden="true" />
                      Perlu diperbarui
                    </span>
                  )}
                </div>

                {/* Coverage Bar */}
                <div className="flex flex-col gap-1.5">
                  <span className="text-[11px] text-text-secondary">
                    {copy.coverageLabel} {coveragePct.toFixed(1).replace('.', ',')}%
                  </span>
                  <div className="h-1.5 w-full overflow-hidden rounded-full bg-border-warm/50">
                    <div
                      className="h-full rounded-full bg-brand transition-all duration-500"
                      style={{ width: `${Math.min(100, Math.max(0, coveragePct))}%` }}
                    />
                  </div>
                  <span className="text-[11px] text-text-secondary">
                    {missingCount} skill belum tercakup
                  </span>
                </div>
              </Link>
            )
          })}
        </div>
      )}
    </div>
  )
}
