import { useState } from 'react'
import { ExternalLink } from 'lucide-react'
import type { JobEvidence } from '@/types/api'
import { groupJobEvidence, type EvidenceGroup } from '@/lib/jobEvidence'
import { copy } from '@/lib/copy'

const VISIBLE_GROUPS = 3

function JobLink({ job }: { job: JobEvidence }) {
  if (!job.source_url) return null
  return (
    <a
      href={job.source_url}
      target="_blank"
      rel="noopener noreferrer"
      className="inline-flex min-h-11 w-fit items-center gap-1 text-sm font-semibold text-brand-strong underline"
    >
      {copy.analysesResult.evidenceJobOpen}
      {job.source_name ? ` (${job.source_name})` : ''}
      <ExternalLink className="size-3" aria-hidden="true" />
      <span className="sr-only">{copy.analysesResult.newTab}</span>
    </a>
  )
}

function SingleJob({ group }: { group: EvidenceGroup }) {
  const job = group.jobs[0]!
  return (
    <div className="flex flex-col gap-1 rounded-lg border border-border-warm p-3">
      {job.title && <span className="text-sm font-semibold text-text-primary">{job.title}</span>}
      <span className="text-sm font-semibold text-brand-strong">{job.company}</span>
      {group.quote && <p className="text-sm italic text-text-secondary">“{group.quote}”</p>}
      <JobLink job={job} />
    </div>
  )
}

function SharedQuote({ group }: { group: EvidenceGroup }) {
  return (
    <div className="flex flex-col gap-1 rounded-lg border border-border-warm p-3">
      <p className="text-sm italic text-text-secondary">“{group.quote}”</p>
      <span className="text-sm font-semibold text-text-primary">
        {copy.analysesResult.evidenceSimilar(group.jobs.length)}
      </span>
      <details className="group">
        <summary className="flex min-h-11 cursor-pointer list-none items-center text-sm font-semibold text-brand-strong underline">
          {copy.analysesResult.evidenceJobList}
        </summary>
        <ul className="flex flex-col divide-y divide-border-warm">
          {group.jobs.map((job) => (
            <li key={job.job_posting_id} className="flex flex-col py-1.5 text-sm">
              <span className="font-semibold text-text-primary">{job.title}</span>
              <span className="text-brand-strong">{job.company}</span>
              <JobLink job={job} />
            </li>
          ))}
        </ul>
      </details>
    </div>
  )
}

interface EvidenceJobListProps {
  jobs: JobEvidence[]
  /** Total lowongan yang meminta skill, termasuk yang tidak dikirim API. */
  total: number
}

/** Bukti lowongan: tiga kelompok pertama, kutipan identik digabung, sisanya di balik tombol. */
export function EvidenceJobList({ jobs, total }: EvidenceJobListProps) {
  const [showAll, setShowAll] = useState(false)
  const groups = groupJobEvidence(jobs)
  const visible = showAll ? groups : groups.slice(0, VISIBLE_GROUPS)
  const hiddenJobs = groups.slice(VISIBLE_GROUPS).reduce((sum, g) => sum + g.jobs.length, 0)
  const notSent = total - jobs.length

  return (
    <div className="flex flex-col gap-2">
      <span className="text-sm font-bold text-text-secondary">{copy.analysesResult.evidenceJob}</span>
      {visible.map((group) =>
        group.jobs.length > 1 ? (
          <SharedQuote key={group.key} group={group} />
        ) : (
          <SingleJob key={group.key} group={group} />
        )
      )}
      {!showAll && hiddenJobs > 0 && (
        <button
          type="button"
          onClick={() => setShowAll(true)}
          className="min-h-11 w-full rounded-lg border border-border-warm px-3 text-sm font-semibold text-text-primary hover:bg-surface-subtle"
        >
          {copy.analysesResult.evidenceShowMore(hiddenJobs)}
        </button>
      )}
      {notSent > 0 && (
        <span className="text-sm text-text-secondary">{copy.analysesResult.evidenceJobMore(notSent)}</span>
      )}
    </div>
  )
}
