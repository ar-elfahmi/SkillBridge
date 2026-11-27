import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Check, ChevronDown, CircleDashed, Minus, Pencil, Plus, Quote, Trash2, X } from 'lucide-react'
import type { CourseSkill, CreateCompetencyPayload } from '@/types/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { copy } from '@/lib/copy'
import { categoryLabel, sourceReferenceLabel } from '@/lib/labels'

interface CompetencyReviewTableProps {
  documentId: number
  competencies: CourseSkill[]
  onApprove: (id: number) => void
  onReject: (id: number) => void
  onDelete: (id: number) => void
  onEdit: (id: number, skill: string, competency: string) => void
  onCreate: (payload: CreateCompetencyPayload) => void
  onConfirmAll: (confirmAllPending?: boolean) => void
  isConfirming: boolean
  isConfirmedAll: boolean
}

export function CompetencyReviewTable({
  documentId,
  competencies,
  onApprove,
  onReject,
  onDelete,
  onEdit,
  onCreate,
  onConfirmAll,
  isConfirming,
  isConfirmedAll,
}: CompetencyReviewTableProps) {
  const [filter, setFilter] = useState<'all' | 'unverified' | 'pending' | 'rejected'>('all')
  const [isAddOpen, setIsAddOpen] = useState(false)
  const [newSkill, setNewSkill] = useState('')
  const [newCompetency, setNewCompetency] = useState('')

  // Edit modal / inline edit state
  const [editingId, setEditingId] = useState<number | null>(null)
  const [editSkill, setEditSkill] = useState('')
  const [editCompetencyText, setEditCompetencyText] = useState('')

  // Filter competencies
  const filtered = competencies.filter((c) => {
    if (filter === 'unverified') return c.verification_status === 'unverified'
    if (filter === 'pending') return c.review_status === 'pending'
    if (filter === 'rejected') return c.review_status === 'rejected'
    return true
  })

  // Counters
  const totalCount = competencies.length
  const unverifiedCount = competencies.filter((c) => c.verification_status === 'unverified').length
  const pendingCount = competencies.filter((c) => c.review_status === 'pending').length

  function handleAddSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!newSkill.trim() || !newCompetency.trim()) return
    onCreate({ skill: newSkill.trim(), competency: newCompetency.trim() })
    setNewSkill('')
    setNewCompetency('')
    setIsAddOpen(false)
  }

  function startEdit(c: CourseSkill) {
    setEditingId(c.id)
    setEditSkill(c.skill)
    setEditCompetencyText(c.competency)
  }

  function saveEdit(id: number) {
    if (!editSkill.trim() || !editCompetencyText.trim()) return
    onEdit(id, editSkill.trim(), editCompetencyText.trim())
    setEditingId(null)
  }

  return (
    <div className="flex flex-col gap-3 sm:gap-6">
      {/* Review Summary Cards & Filters */}
      <div className="flex flex-col gap-3 rounded-xl border border-border-warm bg-white p-3 sm:p-5 md:flex-row md:items-center md:justify-between">
        <p className="text-sm font-semibold text-text-primary sm:hidden">
          {totalCount} kompetensi, {unverifiedCount} belum terverifikasi, {pendingCount} menunggu tinjauan
        </p>
        <div className="hidden grid-cols-3 gap-2 sm:grid md:gap-3">
          <div className="flex flex-col rounded-lg bg-brand-soft px-3 py-2">
            <span className="font-display text-xl font-bold text-text-primary md:text-3xl">
              {totalCount}
            </span>
            <span className="text-sm font-medium leading-tight text-text-primary md:text-sm">
              {copy.rpsDetail.review.totalCompetencies}
            </span>
          </div>

          <div className="flex flex-col rounded-lg bg-warning-soft px-3 py-2">
            <span className="font-display text-xl font-bold text-warning md:text-3xl">
              {unverifiedCount}
            </span>
            <span className="text-sm font-medium leading-tight text-text-primary md:text-sm">
              {copy.rpsDetail.review.unverified}
            </span>
          </div>

          <div className="flex flex-col rounded-lg bg-brand-soft px-3 py-2">
            <span className="font-display text-xl font-bold text-brand-strong md:text-3xl">
              {pendingCount}
            </span>
            <span className="text-sm font-medium leading-tight text-text-primary md:text-sm">
              {copy.rpsDetail.review.pendingReview}
            </span>
          </div>
        </div>

        {/* Filter Dropdown */}
        <div className="relative flex w-full flex-col gap-1 sm:w-60">
          <label htmlFor="competencyFilter" className="text-sm font-semibold text-text-secondary">
            Tampilkan
          </label>
          <select
            id="competencyFilter"
            value={filter}
            onChange={(e) => setFilter(e.target.value as 'all' | 'unverified' | 'pending' | 'rejected')}
            className="w-full appearance-none h-11 rounded-lg border border-field bg-white px-3 pr-9 text-base font-semibold text-text-primary outline-none md:text-sm focus-visible:border-brand-accent focus-visible:ring-2 focus-visible:ring-brand-accent/40"
          >
            <option value="all">{copy.rpsDetail.review.filterAll} ({totalCount})</option>
            <option value="unverified">
              {copy.rpsDetail.review.filterUnverified} ({unverifiedCount})
            </option>
            <option value="pending">{copy.rpsDetail.review.filterPending} ({pendingCount})</option>
            <option value="rejected">{copy.rpsDetail.review.filterRejected}</option>
          </select>
          <ChevronDown className="pointer-events-none absolute bottom-3.5 right-3 size-4 text-text-secondary" />
        </div>
      </div>

      {/* Add Competency Section */}
      {isAddOpen ? (
        <form
          onSubmit={handleAddSubmit}
          className="flex flex-col gap-4 rounded-lg border border-border-warm bg-white p-5 shadow-xs"
        >
          <span className="text-sm font-bold text-text-primary">
            Tambah kompetensi
          </span>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="flex flex-col gap-1">
              <label htmlFor="newSkill" className="text-sm font-semibold text-text-secondary">Nama skill</label>
              <Input
                id="newSkill"
                value={newSkill}
                onChange={(e) => setNewSkill(e.target.value)}
                placeholder="Contoh: Version control"
                required
              />
            </div>
            <div className="flex flex-col gap-1">
              <label htmlFor="newCompetency" className="text-sm font-semibold text-text-secondary">Deskripsi kompetensi</label>
              <Input
                id="newCompetency"
                value={newCompetency}
                onChange={(e) => setNewCompetency(e.target.value)}
                placeholder="Contoh: Mampu mengoperasikan git dan workflow kolaborasi"
                required
              />
            </div>
          </div>
          <div className="flex justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setIsAddOpen(false)}
            >
              Batal
            </Button>
            <Button type="submit" size="sm" className="bg-brand text-white">
              Simpan kompetensi
            </Button>
          </div>
        </form>
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

      {/* Desktop Table View (md+) */}
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
                  Tidak ada kompetensi yang sesuai dengan filter.
                </td>
              </tr>
            ) : (
              filtered.map((c) => {
                const isEditing = editingId === c.id
                return (
                  <tr key={c.id} className="hover:bg-surface-subtle/40 transition-colors">
                    {/* Kompetensi */}
                    <td className="px-4 py-3.5 align-top">
                      {isEditing ? (
                        <div className="flex flex-col gap-1.5">
                          <Input
                            value={editSkill}
                            aria-label="Nama skill"
                            onChange={(e) => setEditSkill(e.target.value)}
                            className="h-8 text-sm"
                          />
                          <Input
                            value={editCompetencyText}
                            aria-label="Deskripsi kompetensi"
                            onChange={(e) => setEditCompetencyText(e.target.value)}
                            className="h-8 text-sm"
                          />
                          <div className="flex gap-1.5">
                            <button
                              type="button"
                              onClick={() => saveEdit(c.id)}
                              className="rounded bg-brand px-2 py-0.5 text-sm font-bold text-white"
                            >
                              Simpan
                            </button>
                            <button
                              type="button"
                              onClick={() => setEditingId(null)}
                              className="rounded border border-border-warm px-2 py-0.5 text-sm"
                            >
                              Batal
                            </button>
                          </div>
                        </div>
                      ) : (
                        <div className="flex flex-col gap-0.5">
                          <span className="font-bold text-text-primary">{c.skill}</span>
                          <span className="text-sm text-text-secondary">
                            {categoryLabel(c.category)}
                          </span>
                          <p className="mt-1 text-sm text-text-primary leading-relaxed">
                            {c.competency}
                          </p>
                        </div>
                      )}
                    </td>

                    {/* Bukti */}
                    <td className="max-w-[240px] px-4 py-3.5 align-top">
                      {c.evidence ? (
                        <p className="italic text-text-secondary leading-relaxed">
                          “{c.evidence}”
                        </p>
                      ) : (
                        <span className="text-text-secondary/70">Tanpa kutipan RPS</span>
                      )}
                    </td>

                    {/* Sumber */}
                    <td className="px-4 py-3.5 align-top text-text-secondary">
                      {c.origin === 'user' ? (
                        <span className="rounded bg-brand-soft px-2 py-0.5 text-sm font-semibold text-brand-strong">
                          Pengguna
                        </span>
                      ) : (
                        <span>
                          {sourceReferenceLabel(c.source_reference, c.source_type)}
                          {c.week ? `, M${c.week}` : ''}
                          {c.page ? `, Hal. ${c.page}` : ''}
                        </span>
                      )}
                    </td>

                    {/* Verifikasi */}
                    <td className="px-4 py-3.5 align-top">
                      {c.verification_status === 'verified' ? (
                        <span className="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-success-soft px-2 py-0.5 text-sm font-semibold text-success">
                          <Check className="size-3" />
                          {copy.rpsDetail.review.verifiedChip}
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-danger-soft px-2 py-0.5 text-sm font-semibold text-danger">
                          <Minus className="size-3" />
                          {copy.rpsDetail.review.unverifiedChip}
                        </span>
                      )}
                    </td>

                    {/* Tinjauan Status */}
                    <td className="px-4 py-3.5 align-top">
                      {c.review_status === 'confirmed' ? (
                        <span className="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-success-soft px-2 py-0.5 text-sm font-semibold text-success">
                          <Check className="size-3" />
                          {copy.rpsDetail.review.confirmedChip}
                        </span>
                      ) : c.review_status === 'rejected' ? (
                        <span className="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-danger-soft px-2 py-0.5 text-sm font-semibold text-danger">
                          <X className="size-3" />
                          {copy.rpsDetail.review.rejectedChip}
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-warning-soft px-2 py-0.5 text-sm font-semibold text-warning">
                          <CircleDashed className="size-3" />
                          {copy.rpsDetail.review.pendingChip}
                        </span>
                      )}
                    </td>

                    {/* Aksi */}
                    <td className="px-4 py-3.5 align-top">
                      <div className="flex items-center justify-center gap-1.5">
                        <button
                          type="button"
                          title="Setujui"
                          aria-label={`Setujui ${c.skill}`}
                          onClick={() => onApprove(c.id)}
                          className="flex size-11 items-center justify-center rounded-lg border border-border-warm text-success hover:bg-success-soft transition-colors"
                        >
                          <Check className="size-3.5" />
                        </button>

                        <button
                          type="button"
                          title="Edit"
                          aria-label={`Edit ${c.skill}`}
                          onClick={() => startEdit(c)}
                          className="flex size-11 items-center justify-center rounded-lg border border-border-warm text-text-secondary hover:bg-surface-subtle transition-colors"
                        >
                          <Pencil className="size-3.5" />
                        </button>

                        {c.origin === 'user' ? (
                          <button
                            type="button"
                            title="Hapus"
                          aria-label={`Hapus ${c.skill}`}
                            onClick={() => onDelete(c.id)}
                            className="flex size-11 items-center justify-center rounded-lg border border-border-warm text-danger hover:bg-danger/10 transition-colors"
                          >
                            <Trash2 className="size-3.5" />
                          </button>
                        ) : (
                          <button
                            type="button"
                            title="Tolak"
                          aria-label={`Tolak ${c.skill}`}
                            onClick={() => onReject(c.id)}
                            className="flex size-11 items-center justify-center rounded-lg border border-border-warm text-danger hover:bg-danger/10 transition-colors"
                          >
                            <X className="size-3.5" />
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                )
              })
            )}
          </tbody>
        </table>
      </div>

      {/* Mobile Cards View (< md) */}
      <div className="md:hidden"><div className="flex flex-col gap-3">
        {filtered.map((c) => (
          <div
            key={c.id}
            className="flex flex-col gap-3 rounded-lg border border-border-warm bg-white p-4"
          >
            <div className="flex items-start justify-between gap-2">
              <div className="flex flex-col">
                <span className="font-bold text-text-primary">{c.skill}</span>
                <span className="text-sm text-text-secondary">
                  {categoryLabel(c.category)}
                </span>
              </div>

              {c.verification_status === 'verified' ? (
                <span className="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-success-soft px-2 py-0.5 text-sm font-semibold text-success">
                  <Check className="size-3" />
                  {copy.rpsDetail.review.verifiedChip}
                </span>
              ) : (
                <span className="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-danger-soft px-2 py-0.5 text-sm font-semibold text-danger">
                  <Minus className="size-3" />
                  {copy.rpsDetail.review.unverifiedChip}
                </span>
              )}
            </div>

            <p className="text-sm text-text-primary leading-relaxed">{c.competency}</p>

            {c.evidence && (
              <div className="flex items-start gap-2 rounded-lg bg-surface-subtle p-2.5 text-sm italic text-text-secondary">
                <Quote className="size-3.5 shrink-0 text-brand-accent mt-0.5" />
                <p>“{c.evidence}”</p>
              </div>
            )}

            <div className="flex items-center justify-between pt-2 border-t border-border-warm/60">
              <span className="text-sm text-text-secondary">
                {c.origin === 'user' ? 'Kompetensi pengguna' : sourceReferenceLabel(c.source_reference, c.source_type)}
              </span>

              <div className="flex items-center gap-1.5">
                <button
                  type="button"
                  onClick={() => onApprove(c.id)}
                  className="rounded-lg bg-success-soft inline-flex min-h-11 items-center px-3 text-sm font-semibold text-success"
                >
                  Setujui
                </button>
                <button
                  type="button"
                  onClick={() => startEdit(c)}
                  className="rounded-lg border border-border-warm inline-flex min-h-11 items-center px-3 text-sm font-semibold text-text-secondary"
                >
                  Edit
                </button>
                {c.origin === 'user' ? (
                  <button
                    type="button"
                    onClick={() => onDelete(c.id)}
                    className="rounded-lg bg-danger-soft inline-flex min-h-11 items-center px-3 text-sm font-semibold text-danger"
                  >
                    Hapus
                  </button>
                ) : (
                  <button
                    type="button"
                    onClick={() => onReject(c.id)}
                    className="rounded-lg bg-danger-soft inline-flex min-h-11 items-center px-3 text-sm font-semibold text-danger"
                  >
                    Tolak
                  </button>
                )}
              </div>
            </div>
          </div>
        ))}
        </div>
      </div>

      {/* Confirmation Bottom Bar */}
      <div className="sticky bottom-0 z-10 -mx-4 flex flex-col gap-2 border-t border-border-warm bg-white p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:static sm:mx-0 sm:gap-3 sm:rounded-xl sm:border sm:p-5 md:flex-row md:items-center md:justify-between">
        <div className="flex flex-col gap-0.5">
          <span className="text-sm font-bold text-text-primary">
            {pendingCount > 0
              ? `${pendingCount} ${copy.rpsDetail.review.confirmTitle}`
              : 'Semua kompetensi telah ditinjau'}
          </span>
          {pendingCount > 0 && (
            <p className="text-sm leading-snug text-text-secondary">
              {copy.rpsDetail.review.confirmNote}
            </p>
          )}
        </div>

        <div className="flex items-center gap-3">
          {isConfirmedAll ? (
            <Link
              to={`/analyses/new?rps=${documentId}`}
              className="flex h-11 items-center justify-center rounded-lg bg-brand px-6 text-sm font-bold text-white shadow hover:bg-brand-hover transition-colors"
            >
              {copy.rpsDetail.review.goToMapping}
            </Link>
          ) : (
            <Button
              type="button"
              disabled={isConfirming}
              onClick={() => onConfirmAll(pendingCount > 0)}
              className="h-11 w-full bg-brand px-6 text-sm font-bold text-white hover:bg-brand-hover sm:w-auto"
            >
              {isConfirming
                ? 'Menyimpan Konfirmasi…'
                : copy.rpsDetail.review.confirmAllBtn}
            </Button>
          )}
        </div>
      </div>
    </div>
  )
}
