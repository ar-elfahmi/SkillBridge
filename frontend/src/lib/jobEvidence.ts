import type { JobEvidence } from '@/types/api'

export interface EvidenceGroup {
  key: string
  quote: string
  jobs: JobEvidence[]
}

/** Membuang penanda daftar mentah seperti "- " di awal kutipan. */
export function cleanQuote(quote: string | null | undefined): string {
  return (quote ?? '').replace(/^\s*(?:[-*\u2022\u2013])\s+/, '').trim()
}

/** Kutipan identik (abaikan huruf besar dan spasi ganda) digabung menjadi satu kelompok. */
export function groupJobEvidence(jobs: JobEvidence[]): EvidenceGroup[] {
  const groups = new Map<string, EvidenceGroup>()
  for (const job of jobs) {
    const quote = cleanQuote(job.evidence)
    const key = quote ? quote.toLowerCase().replace(/\s+/g, ' ') : `tanpa-kutipan-${job.job_posting_id}`
    const existing = groups.get(key)
    if (existing) existing.jobs.push(job)
    else groups.set(key, { key, quote, jobs: [job] })
  }
  return [...groups.values()]
}
