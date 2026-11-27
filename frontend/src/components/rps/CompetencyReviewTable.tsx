import { useState, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { Check, ChevronDown, CircleDashed, Minus, Pencil, Plus, Trash2, X } from 'lucide-react'
import type { CourseSkill, CreateCompetencyPayload } from '@/types/api'
import { ModalDialog } from '@/components/common/ModalDialog'
import { StatusNotice } from '@/components/common/StatusNotice'
import { AddCompetencyForm } from '@/components/rps/AddCompetencyForm'
import { CompetencyEditor } from '@/components/rps/CompetencyEditor'
import { Button } from '@/components/ui/button'
import { copy } from '@/lib/copy'
import { categoryLabel, formatSource } from '@/lib/labels'
import { useStickyInset } from '@/lib/useStickyInset'

interface CompetencyReviewTableProps {
  documentId: number
  competencies: CourseSkill[]
  onApprove: (id: number) => void
  onApproveMany: (ids: number[]) => Promise<unknown>
  onReject: (id: number) => void
  onDelete: (id: number) => void
  onEdit: (id: number, skill: string, competency: string) => Promise<unknown>
  onCreate: (payload: CreateCompetencyPayload) => Promise<unknown>
  onConfirmAll: (confirmAllPending: boolean) => void
  isConfirming: boolean
  isConfirmedAll: boolean
}

type Filter = 'all' | 'unverified' | 'pending' | 'rejected'
type BulkKind = 'all' | 'verified'

/** Lencana status netral: makna dibawa ikon dan teks, bukan warna. */
function NeutralChip({ icon, children }: { icon: ReactNode; children: ReactNode }) {
  return (
    <span className="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-surface-subtle px-2 py-0.5 text-sm font-semibold text-text-primary">
      {icon}
      {children}
    </span>
  )
}

function VerificationChip({ c }: { c: CourseSkill }) {
  return c.verification_status === 'verified' ? (
    <NeutralChip icon={<Check className="size-3" aria-hidden="true" />}>
      {copy.rpsDetail.review.verifiedChip}
    </NeutralChip>
  ) : (
    <NeutralChip icon={<Minus className="size-3" aria-hidden="true" />}>
      {copy.rpsDetail.review.unverifiedChip}
    </NeutralChip>
  )
}

function ReviewChip({ c }: { c: CourseSkill }) {
  if (c.review_status === 'confirmed') {
    return (
      <NeutralChip icon={<Check className="size-3" aria-hidden="true" />}>
        {copy.rpsDetail.review.confirmedChip}
      </NeutralChip>
    )
  }
  if (c.review_status === 'rejected') {
    return (
      <NeutralChip icon={<X className="size-3" aria-hidden="true" />}>
        {copy.rpsDetail.review.rejectedChip}
      </NeutralChip>
    )
  }
  return (
    <NeutralChip icon={<CircleDashed className="size-3" aria-hidden="true" />}>
      {copy.rpsDetail.review.pendingChip}
    </NeutralChip>
  )
}

const ACTION_BASE =
  'inline-flex items-center justify-center gap-1.5 rounded-lg border border-border-warm bg-white text-sm font-semibold text-text-primary transition-colors hover:bg-surface-subtle active:bg-surface-subtle disabled:opacity-50'

export function CompetencyReviewTable({
  documentId,
  competencies,
  onApprove,
  onApproveMany,
  onReject,
  onDelete,
  onEdit,
  onCreate,
  onConfirmAll,
  isConfirming,
  isConfirmedAll,
}: CompetencyReviewTableProps) {
  const [filter, setFilter] = useState<Filter>('all')
  const [isAddOpen, setIsAddOpen] = useState(false)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [bulk, setBulk] = useState<BulkKind | null>(null)
  const [isBulkRunning, setIsBulkRunning] = useState(false)
  const [bulkError, setBulkError] = useState(false)
  const barRef = useStickyInset('bottom', '(min-width: 640px)')

  const filtered = competencies.filter((c) => {
    if (filter === 'unverified') return c.verification_status === 'unverified'
    if (filter === 'pending') return c.review_status === 'pending'
    if (filter === 'rejected') return c.review_status === 'rejected'
    return true
  })

  const totalCount = competencies.length
  const unverifiedCount = competencies.filter((c) => c.verification_status === 'unverified').length
  const pending = competencies.filter((c) => c.review_status === 'pending')
  const pendingCount = pending.length
  const pendingVerified = pending.filter((c) => c.verification_status === 'verified')
  const pendingUnverifiedCount = pendingCount - pendingVerified.length

  async function runBulk() {
    if (!bulk) return
    if (bulk === 'all') {
      setBulk(null)
      onConfirmAll(true)
      return
    }
    setIsBulkRunning(true)
    setBulkError(false)
    try {
      await onApproveMany(pendingVerified.map((c) => c.id))
      setBulk(null)
    } catch {
      setBulkError(true)
    } finally {
      setIsBulkRunning(false)
    }
  }

  function renderEditor(c: CourseSkill, idPrefix: string) {
    return (
      <CompetencyEditor
        idPrefix={`${idPrefix}-${c.id}`}
        initialSkill={c.skill}
        initialCompetency={c.competency}
        onSave={(skill, competency) => onEdit(c.id, skill, competency)}
        onCancel={() => setEditingId(null)}
      />
    )
  }

  const rejectLabel = (c: CourseSkill) => (c.origin === 'user' ? copy.rpsDetail.review.remove : copy.rpsDetail.review.reject)

  return (
    <div className="flex flex-col gap-3 sm:gap-6">
      {/* Ringkasan dan filter */}
      <div className="flex flex-col gap-3 rounded-xl border border-border-warm bg-white p-3 sm:p-5 md:flex-row md:items-center md:justify-between">
        <div className="flex flex-col items-start gap-2 md:gap-3">
        <p className="text-sm font-semibold text-text-primary sm:hidden">
          {totalCount} kompetensi, {unverifiedCount} belum terverifikasi, {pendingCount} menunggu tinjauan
        </p>
        <div className="hidden w-full grid-cols-3 gap-2 sm:grid md:gap-3">

          <div className="flex flex-col rounded-lg bg-brand-soft px-3 py-2">
            <span className="font-display text-xl font-bold text-text-primary md:text-3xl">{totalCount}</span>
            <span className="text-sm font-medium leading-tight text-text-primary">
              {copy.rpsDetail.review.totalCompetencies}
            </span>
          </div>
          <div className="flex flex-col rounded-lg bg-warning-soft px-3 py-2">
            <span className="font-display text-xl font-bold text-warning md:text-3xl">{unverifiedCount}</span>
            <span className="text-sm font-medium leading-tight text-text-primary">
              {copy.rpsDetail.review.unverified}
            </span>
          </div>
          <div className="flex flex-col rounded-lg bg-brand-soft px-3 py-2">
            <span className="font-display text-xl font-bold text-brand-strong md:text-3xl">{pendingCount}</span>
            <span className="text-sm font-medium leading-tight text-text-primary">
              {copy.rpsDetail.review.pendingReview}
            </span>
          </div>
        </div>
        {!isConfirmedAll && pendingVerified.length > 0 && pendingVerified.length < pendingCount && (
          <Button
            type="button"
            variant="outline"
            disabled={isConfirming}
            onClick={() => setBulk('verified')}
            className="min-h-11 px-4 text-sm font-semibold"
          >
            {copy.rpsDetail.review.approveVerifiedBtn(pendingVerified.length)}
          </Button>
        )}
        </div>

        <div className="relative flex w-full flex-col gap-1 sm:w-60">
          <label htmlFor="competencyFilter" className="text-sm font-semibold text-text-secondary">
            Tampilkan
          </label>
          <select
            id="competencyFilter"
            value={filter}
            onChange={(e) => setFilter(e.target.value as Filter)}
            className="h-11 w-full appearance-none rounded-lg border border-field bg-white px-3 pr-9 text-base font-semibold text-text-primary outline-none focus-visible:border-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent/40 md:text-sm"
          >
            <option value="all">
              {copy.rpsDetail.review.filterAll} ({totalCount})
            </option>
            <option value="unverified">
              {copy.rpsDetail.review.filterUnverified} ({unverifiedCount})
            </option>
            <option value="pending">
              {copy.rpsDetail.review.filterPending} ({pendingCount})
            </option>
            <option value="rejected">{copy.rpsDetail.review.filterRejected}</option>
          </select>
          <ChevronDown
            className="pointer-events-none absolute bottom-3.5 right-3 size-4 text-text-secondary"
            aria-hidden="true"
          />
        </div>
      </div>

      {/* Tambah kompetensi */}
      {isAddOpen ? (
        <AddCompetencyForm onCreate={onCreate} onCancel={() => setIsAddOpen(false)} />
      ) : (
        <div className="flex justify-end">
          <Button
            type="button"
            variant="outline"
            onClick={() => setIsAddOpen(true)}
            className="flex h-11 w-full items-center justify-center gap-1.5 border-dashed border-brand text-brand-strong hover:bg-brand-soft/20 active:bg-brand-soft sm:w-auto"
          >
            <Plus className="size-4" aria-hidden="true" />
            <span>{copy.rpsDetail.review.addCompetency}</span>
          </Button>
        </div>
      )}

      {/* Tabel desktop (md+) */}
      <div className="hidden overflow-hidden rounded-xl border border-border-warm bg-white md:block">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-border-warm bg-surface-subtle font-semibold text-text-secondary">
            <tr>
              <th className="px-4 py-3.5">{copy.rpsDetail.review.colCompetency}</th>
              <th className="px-4 py-3.5">{copy.rpsDetail.review.colEvidence}</th>
              <th className="px-4 py-3.5">{copy.rpsDetail.review.colSource}</th>
              <th className="px-4 py-3.5">{copy.rpsDetail.review.colVerification}</th>
              <th className="px-4 py-3.5">{copy.rpsDetail.review.colReview}</th>
              <th className="px-4 py-3.5 text-center">{copy.rpsDetail.review.colAction}</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border-warm">
            {filtered.length === 0 ? (
              <tr>
                <td colSpan={6} className="p-8 text-center text-text-secondary">
                  {copy.rpsDetail.review.noMatch}
                </td>
              </tr>
            ) : (
              filtered.map((c) => (
                <tr key={c.id} className="transition-colors hover:bg-surface-subtle/40">
                  <td className="px-4 py-3.5 align-top">
                    {editingId === c.id ? (
                      renderEditor(c, 'desktop')
                    ) : (
                      <div className="flex flex-col gap-0.5">
                        <span className="font-bold text-text-primary">{c.skill}</span>
                        <span className="text-sm text-text-secondary">{categoryLabel(c.category)}</span>
                        <p className="mt-1 text-sm leading-relaxed text-text-primary">{c.competency}</p>
                      </div>
                    )}
                  </td>

                  <td className="max-w-[240px] px-4 py-3.5 align-top">
                    {c.evidence ? (
                      <p className="italic leading-relaxed text-text-secondary">“{c.evidence}”</p>
                    ) : (
                      <span className="text-text-secondary">{copy.rpsDetail.review.noEvidence}</span>
                    )}
                  </td>

                  <td className="px-4 py-3.5 align-top text-text-secondary">
                    {c.origin === 'user' ? (
                      <span className="rounded bg-brand-soft px-2 py-0.5 text-sm font-semibold text-brand-strong">
                        Pengguna
                      </span>
                    ) : (
                      formatSource(c.source_reference, c.source_type, c.week, c.page)
                    )}
                  </td>

                  <td className="px-4 py-3.5 align-top">
                    <VerificationChip c={c} />
                  </td>
                  <td className="px-4 py-3.5 align-top">
                    <ReviewChip c={c} />
                  </td>

                  <td className="px-4 py-3.5 align-top">
                    <div className="flex items-center justify-center gap-1.5">
                      <button
                        type="button"
                        title={copy.rpsDetail.review.approve}
                        aria-label={`${copy.rpsDetail.review.approve} ${c.skill}`}
                        disabled={c.review_status === 'confirmed'}
                        onClick={() => onApprove(c.id)}
                        className={`${ACTION_BASE} size-11`}
                      >
                        <Check className="size-4" aria-hidden="true" />
                      </button>
                      <button
                        type="button"
                        title={copy.rpsDetail.review.edit}
                        aria-label={`${copy.rpsDetail.review.edit} ${c.skill}`}
                        onClick={() => setEditingId(c.id)}
                        className={`${ACTION_BASE} size-11`}
                      >
                        <Pencil className="size-4" aria-hidden="true" />
                      </button>
                      <button
                        type="button"
                        title={rejectLabel(c)}
                        aria-label={`${rejectLabel(c)} ${c.skill}`}
                        disabled={c.origin !== 'user' && c.review_status === 'rejected'}
                        onClick={() => (c.origin === 'user' ? onDelete(c.id) : onReject(c.id))}
                        className={`${ACTION_BASE} size-11`}
                      >
                        {c.origin === 'user' ? (
                          <Trash2 className="size-4" aria-hidden="true" />
                        ) : (
                          <X className="size-4" aria-hidden="true" />
                        )}
                      </button>
                    </div>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Kartu mobile (< md): kutipan dilipat, aksi satu baris */}
      <div className="flex flex-col gap-3 md:hidden">
        {filtered.length === 0 && (
          <p className="rounded-lg border border-dashed border-border-warm bg-white p-6 text-center text-sm text-text-secondary">
            {copy.rpsDetail.review.noMatch}
          </p>
        )}
        {filtered.map((c) => (
          <article key={c.id} className="flex flex-col gap-2 rounded-lg border border-border-warm bg-white p-3">
            <div className="flex flex-col">
              <h3 className="font-bold text-text-primary">{c.skill}</h3>
              <span className="text-sm text-text-secondary">{categoryLabel(c.category)}</span>
            </div>

            {editingId === c.id ? (
              renderEditor(c, 'mobile')
            ) : (
              <>
                <p className="text-sm leading-relaxed text-text-primary">{c.competency}</p>

                <div className="flex flex-wrap items-center gap-1.5">
                  <VerificationChip c={c} />
                  <ReviewChip c={c} />
                </div>

                {c.evidence && (
                  <details className="group rounded-lg bg-surface-subtle">
                    <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 px-3 text-sm font-semibold text-text-primary">
                      {copy.rpsDetail.review.showEvidence}
                      <ChevronDown
                        className="size-4 transition-transform group-open:rotate-180"
                        aria-hidden="true"
                      />
                    </summary>
                    <p className="px-3 pb-3 text-sm italic text-text-secondary">“{c.evidence}”</p>
                  </details>
                )}

                <p className="text-sm text-text-secondary">
                  {c.origin === 'user'
                    ? copy.rpsDetail.review.userCompetency
                    : formatSource(c.source_reference, c.source_type, c.week, c.page)}
                </p>

                <div className="grid grid-cols-3 gap-2">
                  <button
                    type="button"
                    aria-label={`${copy.rpsDetail.review.approve} ${c.skill}`}
                    disabled={c.review_status === 'confirmed'}
                    onClick={() => onApprove(c.id)}
                    className={`${ACTION_BASE} min-h-11`}
                  >
                    {copy.rpsDetail.review.approve}
                  </button>
                  <button
                    type="button"
                    aria-label={`${copy.rpsDetail.review.edit} ${c.skill}`}
                    onClick={() => setEditingId(c.id)}
                    className={`${ACTION_BASE} min-h-11`}
                  >
                    {copy.rpsDetail.review.edit}
                  </button>
                  <button
                    type="button"
                    aria-label={`${rejectLabel(c)} ${c.skill}`}
                    disabled={c.origin !== 'user' && c.review_status === 'rejected'}
                    onClick={() => (c.origin === 'user' ? onDelete(c.id) : onReject(c.id))}
                    className={`${ACTION_BASE} min-h-11`}
                  >
                    {rejectLabel(c)}
                  </button>
                </div>
              </>
            )}
          </article>
        ))}
      </div>

      {/* Bilah konfirmasi */}
      <div ref={barRef} className="sticky bottom-0 z-10 -mx-4 flex flex-col gap-2 border-t border-border-warm bg-white p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:static sm:mx-0 sm:gap-3 sm:rounded-xl sm:border sm:p-5 md:flex-row md:items-center md:justify-between">
        <p className="text-sm font-bold text-text-primary">
          {pendingCount > 0 ? copy.rpsDetail.review.pendingSummary(pendingCount) : copy.rpsDetail.review.allReviewed}
        </p>

        {isConfirmedAll ? (
          <Link
            to={`/analyses/new?rps=${documentId}`}
            className="flex min-h-11 w-full items-center justify-center rounded-lg bg-brand px-6 text-center text-sm font-bold text-white transition-colors hover:bg-brand-hover md:w-auto"
          >
            {copy.rpsDetail.review.goToMapping}
          </Link>
        ) : pendingCount > 0 ? (
          <div className="flex w-full md:w-auto">
            <Button
              type="button"
              disabled={isConfirming}
              onClick={() => setBulk('all')}
              className="min-h-11 w-full bg-brand px-6 text-sm font-bold text-white hover:bg-brand-hover md:w-auto"
            >
              {isConfirming ? copy.rpsDetail.review.confirming : copy.rpsDetail.review.approveAllBtn(pendingCount)}
            </Button>

          </div>
        ) : (
          <Button
            type="button"
            disabled={isConfirming}
            onClick={() => onConfirmAll(false)}
            className="min-h-11 w-full bg-brand px-6 text-sm font-bold text-white hover:bg-brand-hover md:w-auto"
          >
            {isConfirming ? copy.rpsDetail.review.confirming : copy.rpsDetail.review.goToMapping}
          </Button>
        )}
      </div>

      {bulk && (
        <ModalDialog
          title={
            bulk === 'all'
              ? copy.rpsDetail.review.bulkAllTitle(pendingCount)
              : copy.rpsDetail.review.bulkVerifiedTitle(pendingVerified.length)
          }
          onClose={() => !isBulkRunning && setBulk(null)}
          showClose={false}
        >
          <p className="text-sm leading-relaxed text-text-primary">
            {bulk === 'all'
              ? copy.rpsDetail.review.bulkAllBody(pendingCount, pendingUnverifiedCount)
              : copy.rpsDetail.review.bulkVerifiedBody(pendingVerified.length, pendingCount - pendingVerified.length)}
          </p>
          {bulkError && <StatusNotice variant="error">{copy.rpsDetail.review.bulkFailed}</StatusNotice>}
          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button
              type="button"
              variant="outline"
              data-autofocus
              disabled={isBulkRunning}
              onClick={() => setBulk(null)}
              className="h-11 w-full px-5 sm:w-auto"
            >
              {copy.rpsDetail.review.bulkCancel}
            </Button>
            <Button
              type="button"
              disabled={isBulkRunning}
              onClick={() => void runBulk()}
              className="h-11 w-full bg-brand px-5 text-white hover:bg-brand-hover sm:w-auto"
            >
              {copy.rpsDetail.review.bulkApprove}
            </Button>
          </div>
        </ModalDialog>
      )}
    </div>
  )
}
