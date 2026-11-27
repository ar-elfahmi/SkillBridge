import { Link } from 'react-router-dom'
import { AlertCircle, Check, Clock3, FileText, Trash2 } from 'lucide-react'
import type { Analysis, RpsDocument } from '@/types/api'
import { copy } from '@/lib/copy'
import { formatDate, formatPercent, isRecent, toDisplayTitle } from '@/lib/labels'

interface AnalyzedRpsSectionProps {
  documents: RpsDocument[]
  analyses: Analysis[]
  isLoading: boolean
  searchQuery?: string
  onClearSearch?: () => void
  /** Hanya diberikan bila API hapus dokumen tersedia; tanpa ini tombol hapus tidak ditampilkan. */
  onDelete?: (doc: RpsDocument, displayName: string) => void
}

export function AnalyzedRpsSection({
  documents,
  analyses,
  isLoading,
  searchQuery = '',
  onClearSearch,
  onDelete,
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
    <div className="flex flex-col gap-2 sm:gap-4">
      <div className="flex shrink-0 items-center justify-between">
        <h2 className="text-base font-semibold text-text-primary md:text-lg">
          {copy.dashboard.analyzedRpsTitle}
        </h2>
        {documents.length > 0 && (
          <span className="text-sm font-semibold text-brand-strong">
            {documents.length} dokumen
          </span>
        )}
      </div>

      {isLoading ? (
        <div className="flex h-32 items-center justify-center rounded-lg border border-dashed border-border-warm bg-surface-subtle/50 text-sm text-text-secondary">
          Memuat data RPS…
        </div>
      ) : documents.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border-warm bg-white p-8 text-center">
          <FileText className="size-8 text-border-warm" aria-hidden="true" />
          <p className="max-w-md text-sm text-text-secondary">
            {searchQuery ? copy.dashboard.searchEmpty(searchQuery) : copy.dashboard.emptyRps}
          </p>
          {searchQuery && onClearSearch && (
            <button
              type="button"
              onClick={onClearSearch}
              className="min-h-11 rounded-lg px-4 text-sm font-semibold text-brand-strong underline"
            >
              {copy.dashboard.clearSearch}
            </button>
          )}
        </div>
      ) : (
        <div><div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {documents.map((doc) => {
            const analysis = analysisMap.get(doc.id)
            const courseName =
              doc.course?.name ||
              doc.detected_metadata?.name ||
              doc.original_name.replace(/\.pdf$/i, '')

            const isCompleted = doc.review_status === 'confirmed'
            const isNeedReview = doc.review_status === 'unreviewed'

            // Sudah dianalisis → langsung ke hasil analisis; belum → ke detail RPS
            const hasResult = analysis?.status === 'completed'
            const target = hasResult
              ? `/analyses/${analysis.analysis_id}`
              : `/rps/${doc.id}`

            return (
              <div
                key={doc.id}
                className="group relative flex flex-col rounded-xl border border-border-warm bg-white transition-all hover:border-brand hover:shadow-md focus-within:border-brand"
              >
                <Link
                  to={target}
                  className="flex flex-col gap-2 rounded-xl p-3 active:border-brand sm:gap-3 sm:p-4.5"
                >
                  <div className="flex items-start gap-3 pr-10">
                    <div
                      className={`flex size-11 shrink-0 items-center justify-center rounded-lg transition-colors ${
                        isCompleted ? 'bg-brand text-white' : 'bg-brand-soft text-brand-strong'
                      }`}
                    >
                      <FileText className="size-5" aria-hidden="true" />
                    </div>
                    <div className="flex min-w-0 flex-col">
                      <span title={courseName} className="line-clamp-2 text-sm font-bold text-brand-strong">
                        {toDisplayTitle(courseName)}
                      </span>
                      <span className="truncate text-sm text-text-primary">{doc.original_name}</span>
                      <span className="truncate text-sm text-text-secondary">
                        {[
                          doc.created_at ? copy.dashboard.uploadedOn(formatDate(doc.created_at)) : null,
                          analysis?.career ?? null,
                        ]
                          .filter(Boolean)
                          .join(' · ')}
                      </span>
                    </div>
                  </div>

                  {/* Status Chip */}
                  <div className="flex flex-wrap items-center gap-2">
                    {isCompleted ? (
                      <span className="inline-flex items-center gap-1.5 rounded-md bg-success-soft px-2.5 py-1 text-sm font-semibold text-success">
                        <Check className="size-3.5" aria-hidden="true" />
                        Analisis selesai
                      </span>
                    ) : isNeedReview ? (
                      <span className="inline-flex items-center gap-1.5 rounded-md bg-warning-soft px-2.5 py-1 text-sm font-semibold text-warning">
                        <Clock3 className="size-3.5" aria-hidden="true" />
                        Perlu ditinjau
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1.5 rounded-md bg-danger-soft px-2.5 py-1 text-sm font-semibold text-danger">
                        <AlertCircle className="size-3.5" aria-hidden="true" />
                        Perlu diperbarui
                      </span>
                    )}
                    {isRecent(doc.created_at) && (
                      <span className="rounded-md bg-brand px-2 py-0.5 text-sm font-semibold text-white">
                        {copy.dashboard.newBadge}
                      </span>
                    )}
                    {!analysis?.summary && (
                      <span className="text-sm text-text-secondary">
                        {doc.page_count ? `${doc.page_count} halaman` : 'Dokumen RPS'}
                      </span>
                    )}
                  </div>

                  {/* Coverage Bar (hanya ditampilkan jika ada riwayat analisis nyata) */}
                  {analysis?.summary && (
                    <div className="flex flex-col gap-1.5">
                      <div className="flex flex-wrap items-baseline justify-between gap-x-3 text-sm text-text-secondary">
                        <span>
                          {copy.coverageLabel} {formatPercent(analysis.summary.coverage_percentage)}%
                        </span>
                        <span>{analysis.summary.missing} skill belum tercakup</span>
                      </div>
                      <div className="h-1.5 w-full overflow-hidden rounded-full bg-progress-track">
                        <div
                          className="h-full rounded-full bg-brand-accent transition-all duration-500"
                          style={{ width: `${Math.min(100, Math.max(0, analysis.summary.coverage_percentage))}%` }}
                        />
                      </div>
                    </div>
                  )}
                </Link>

                {onDelete && (
                  <button
                    type="button"
                    onClick={() => onDelete(doc, toDisplayTitle(courseName))}
                    aria-label={copy.dashboard.deleteDoc.open(doc.original_name)}
                    className="absolute right-1.5 top-1.5 flex size-11 items-center justify-center rounded-lg text-text-secondary transition-colors hover:bg-danger-soft hover:text-danger active:bg-danger-soft"
                  >
                    <Trash2 className="size-4" aria-hidden="true" />
                  </button>
                )}
              </div>
            )
          })}
        </div>
        </div>
      )}
    </div>
  )
}
