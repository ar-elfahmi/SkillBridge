import { useEffect, useRef } from 'react'
import { BookOpen, Briefcase, Quote, Sparkles, X } from 'lucide-react'
import { ClassificationBadge } from '@/components/analysis/ClassificationBadge'
import { copy } from '@/lib/copy'
import type { SkillMatch } from '@/types/api'

interface EvidenceDrawerProps {
  match: SkillMatch | null
  onClose: () => void
}

const t = copy.analysisResult.evidence

/**
 * Panel bukti per skill. Di desktop tampil dari kanan; di mobile tampil sebagai bottom sheet.
 */
export function EvidenceDrawer({ match, onClose }: EvidenceDrawerProps) {
  const closeButtonRef = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    if (!match) return
    closeButtonRef.current?.focus()
    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [match, onClose])

  if (!match) return null

  const competency = match.matched_competency

  return (
    <div className="fixed inset-0 z-50">
      <button
        type="button"
        aria-label={t.close}
        onClick={onClose}
        className="absolute inset-0 bg-stone-900/30 backdrop-blur-[1px]"
        tabIndex={-1}
      />

      <aside
        role="dialog"
        aria-modal="true"
        aria-labelledby="evidence-drawer-title"
        className="absolute inset-x-0 bottom-0 flex max-h-[85svh] flex-col gap-5 overflow-y-auto rounded-t-2xl border-t border-border-warm bg-white p-5 shadow-xl md:inset-y-0 md:bottom-auto md:right-0 md:left-auto md:max-h-none md:w-[440px] md:rounded-none md:border-l md:border-t-0 md:p-6"
      >
        <header className="flex items-start justify-between gap-3">
          <div className="min-w-0 space-y-2">
            <p className="text-[11px] font-bold uppercase tracking-wider text-text-secondary">{t.title}</p>
            <h2 id="evidence-drawer-title" className="font-serif text-xl font-bold text-text-primary break-words">
              {match.skill}
            </h2>
            <div className="flex flex-wrap items-center gap-2">
              <ClassificationBadge classification={match.classification} />
              <span className="text-xs text-text-secondary">
                {t.demandLabel} {match.demand_count} lowongan
              </span>
            </div>
          </div>
          <button
            ref={closeButtonRef}
            type="button"
            onClick={onClose}
            aria-label={t.close}
            className="shrink-0 rounded-lg p-1.5 text-text-secondary transition hover:bg-surface-canvas hover:text-text-primary"
          >
            <X className="size-4" aria-hidden="true" />
          </button>
        </header>

        {match.self_declared && (
          <p className="rounded-xl border border-sky-200 bg-sky-50 p-3 text-xs text-sky-900">
            <span className="font-bold">{copy.analysisResult.skillGap.selfDeclaredBadge}.</span> {t.selfDeclaredNote}
          </p>
        )}

        <section className="space-y-2">
          <h3 className="flex items-center gap-1.5 text-xs font-bold text-text-primary">
            <Sparkles className="size-3.5 text-brand" aria-hidden="true" />
            {t.reasonTitle}
          </h3>
          <p className="text-sm leading-relaxed text-text-secondary">{match.reason || t.noReason}</p>
        </section>

        <section className="space-y-2">
          <h3 className="flex items-center gap-1.5 text-xs font-bold text-text-primary">
            <BookOpen className="size-3.5 text-brand" aria-hidden="true" />
            {t.rpsTitle}
          </h3>
          {competency ? (
            <article className="space-y-2 rounded-xl border border-border-warm bg-surface-canvas p-4">
              <p className="text-sm font-semibold text-text-primary">{competency.competency}</p>
              {competency.evidence && (
                <blockquote className="flex gap-2 border-l-2 border-brand pl-3 text-xs italic leading-relaxed text-text-secondary">
                  <Quote className="mt-0.5 size-3 shrink-0" aria-hidden="true" />
                  <span>{competency.evidence}</span>
                </blockquote>
              )}
              <dl className="grid grid-cols-2 gap-2 text-[11px] text-text-secondary">
                <div>
                  <dt className="font-bold text-text-primary">{t.source}</dt>
                  <dd>{competency.source_reference || '—'}</dd>
                </div>
                <div>
                  <dt className="font-bold text-text-primary">{t.page}</dt>
                  <dd>{competency.page ?? t.pageUnknown}</dd>
                </div>
              </dl>
            </article>
          ) : (
            <p className="text-xs text-text-secondary">{t.noRps}</p>
          )}
        </section>

        <section className="space-y-2">
          <h3 className="flex items-center gap-1.5 text-xs font-bold text-text-primary">
            <Briefcase className="size-3.5 text-brand" aria-hidden="true" />
            {t.jobTitle}
          </h3>
          {match.job_evidence.length > 0 ? (
            <ul className="space-y-2">
              {match.job_evidence.map((job) => (
                <li
                  key={job.job_posting_id}
                  className="rounded-xl border border-border-warm bg-white p-3 text-xs text-text-secondary"
                >
                  <p className="font-bold text-text-primary">{job.company}</p>
                  {job.evidence && <p className="mt-1 italic leading-relaxed">“{job.evidence}”</p>}
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-xs text-text-secondary">{t.noJob}</p>
          )}
        </section>

        {match.similarity_score !== null && (
          <section className="space-y-1 rounded-xl border border-dashed border-border-warm p-3">
            <h3 className="text-xs font-bold text-text-primary">
              {t.similarityTitle}: {match.similarity_score.toLocaleString('id-ID', { maximumFractionDigits: 2 })}
            </h3>
            <p className="text-[11px] text-text-secondary">{t.similarityNote}</p>
          </section>
        )}
      </aside>
    </div>
  )
}
