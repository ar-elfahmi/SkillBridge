import { useState } from 'react'
import { ChevronDown, Loader2 } from 'lucide-react'
import type { Recommendation, RecommendationPriority, SkillLevel } from '@/types/api'
import { ModalDialog } from '@/components/common/ModalDialog'
import { copy } from '@/lib/copy'
import { LEVEL_LABEL } from '@/lib/labels'

interface LearningRecommendationListProps {
  recommendations: Recommendation[]
  onAlreadyLearned: (skill: string, level: SkillLevel) => void
  declaredSkills: Set<string>
  isLoading?: boolean
}

const PRIORITY_STYLE = {
  high: 'bg-danger-soft text-danger',
  medium: 'bg-warning-soft text-warning',
  low: 'bg-success-soft text-success',
} as const

const PRIORITY_ORDER: RecommendationPriority[] = ['high', 'medium', 'low']
const LEVELS: SkillLevel[] = ['beginner', 'intermediate', 'advanced']

interface RecommendationCardProps {
  recommendation: Recommendation
  isDeclared: boolean
  onMarkLearned: (skill: string, opener: HTMLElement) => void
}

function RecommendationCard({ recommendation: r, isDeclared, onMarkLearned }: RecommendationCardProps) {
  const [open, setOpen] = useState(false)
  const panelId = `reco-${r.skill.replace(/\W+/g, '-')}`
  // Judul kosong/null atau yang hanya mengulang nama skill diganti kalimat bawaan.
  const rawTitle = r.title?.trim() ?? ''
  const title =
    rawTitle && rawTitle.toLowerCase() !== r.skill.trim().toLowerCase() ? rawTitle : `Pelajari ${r.skill}`

  return (
    <div
      data-reco-card
      className={`flex flex-col rounded-xl border bg-white transition-colors ${
        isDeclared ? 'border-success' : 'border-border-warm'
      }`}
    >
      <button
        type="button"
        data-reco-toggle
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-controls={panelId}
        className="flex min-h-11 w-full items-start justify-between gap-3 rounded-xl p-4 text-left"
      >
        <span className="flex min-w-0 flex-col gap-1">
          <span className="text-base font-bold text-brand-strong">{r.skill}</span>
          <span className="text-sm font-semibold leading-snug text-text-primary">{title}</span>
        </span>
        <span className="flex shrink-0 items-center gap-2">
          <span className={`rounded-full px-2.5 py-0.5 text-sm font-bold ${PRIORITY_STYLE[r.priority]}`}>
            {copy.analysesResult.priorityLabel[r.priority]}
          </span>
          <ChevronDown
            className={`size-4 text-text-secondary transition-transform ${open ? 'rotate-180' : ''}`}
            aria-hidden="true"
          />
        </span>
      </button>

      {open && (
        <div id={panelId} className="flex flex-col gap-3 border-t border-border-warm p-4">
          <p className="text-sm leading-relaxed text-text-primary">{r.reason}</p>

          {r.details.topics.length > 0 && (
            <div className="flex flex-col gap-1.5">
              <span className="text-sm font-bold text-text-secondary">{copy.analysesResult.topicsLabel}</span>
              <div className="flex flex-wrap gap-1.5">
                {r.details.topics.map((t) => (
                  <span
                    key={t}
                    className="rounded-md bg-surface-subtle px-2.5 py-1 text-sm font-medium text-text-primary"
                  >
                    {t}
                  </span>
                ))}
              </div>
            </div>
          )}

          {r.details.learning_keywords.length > 0 && (
            <p className="text-sm text-text-secondary">
              {copy.analysesResult.keywordsPrefix} {r.details.learning_keywords.join(', ')}
            </p>
          )}

          <button
            type="button"
            onClick={(e) => onMarkLearned(r.skill, e.currentTarget)}
            disabled={isDeclared}
            className={`min-h-11 w-fit rounded-lg border px-3 text-sm font-semibold transition-colors active:bg-surface-subtle ${
              isDeclared
                ? 'cursor-default border-success/40 bg-success/10 text-success'
                : 'border-border-warm text-text-primary hover:border-brand hover:text-brand-strong'
            }`}
          >
            {copy.analysesResult.alreadyLearned}
          </button>
        </div>
      )}
    </div>
  )
}

export function LearningRecommendationList({
  recommendations,
  onAlreadyLearned,
  declaredSkills,
  isLoading = false,
}: LearningRecommendationListProps) {
  const [openGroups, setOpenGroups] = useState<Partial<Record<RecommendationPriority, boolean>>>({})
  const [hideLearned, setHideLearned] = useState(false)
  const [sheet, setSheet] = useState<{ skill: string; opener: HTMLElement } | null>(null)

  const hasDeclared = recommendations.some((r) => declaredSkills.has(r.skill))
  const shown = hideLearned ? recommendations.filter((r) => !declaredSkills.has(r.skill)) : recommendations
  // Hanya kelompok pertama yang terbuka; sisanya terlipat sampai dibuka.
  const groups = PRIORITY_ORDER.map((priority) => ({
    priority,
    items: shown.filter((r) => r.priority === priority),
  })).filter((g) => g.items.length > 0)

  function chooseLevel(level: SkillLevel) {
    if (!sheet) return
    const { skill, opener } = sheet
    const card = opener.closest('[data-reco-card]')
    const toggle = card?.querySelector<HTMLElement>('[data-reco-toggle]') ?? null
    const toggles = Array.from(document.querySelectorAll<HTMLElement>('[data-reco-toggle]'))
    const index = toggle ? toggles.indexOf(toggle) : -1
    const next = toggles[index + 1] ?? toggles[index - 1] ?? null

    setSheet(null)
    onAlreadyLearned(skill, level)
    // Fokus pindah ke kartu berikutnya agar pengguna papan ketik tidak kehilangan posisi.
    setTimeout(() => {
      const target = next?.isConnected ? next : toggle?.isConnected ? toggle : null
      target?.focus()
    }, 0)
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-1">
        <h2 className="font-display text-xl font-normal text-text-primary md:text-2xl">
          {copy.analysesResult.recoTitle}
        </h2>
        <p className="text-sm text-text-secondary">{copy.analysesResult.recoSubtitle}</p>
      </div>

      {hasDeclared && (
        <button
          type="button"
          aria-pressed={hideLearned}
          onClick={() => setHideLearned((v) => !v)}
          className={`inline-flex min-h-11 w-fit items-center rounded-lg border px-3 text-sm font-semibold ${
            hideLearned
              ? 'border-brand bg-brand-soft text-brand-strong'
              : 'border-border-warm bg-white text-text-primary hover:bg-surface-subtle'
          }`}
        >
          {copy.analysesResult.hideLearned}
        </button>
      )}

      {isLoading ? (
        <div
          role="status"
          aria-live="polite"
          className="flex items-center justify-center gap-2.5 rounded-xl border border-border-warm bg-white p-8 text-sm text-text-secondary"
        >
          <Loader2 className="size-4 shrink-0 animate-spin text-brand-accent motion-reduce:animate-none" aria-hidden="true" />
          <span>{copy.analysesResult.recoLoading}</span>
        </div>
      ) : recommendations.length === 0 ? (
        <div className="rounded-xl border border-dashed border-border-warm bg-white p-8 text-center text-sm text-text-secondary">
          {copy.analysesResult.emptyReco}
        </div>
      ) : (
        <div className="flex flex-col gap-4">
          {groups.map((group, index) => {
            const expanded = openGroups[group.priority] ?? index === 0
            const groupId = `reco-group-${group.priority}`
            return (
              <section key={group.priority} className="flex flex-col gap-3">
                <button
                  type="button"
                  onClick={() => setOpenGroups((prev) => ({ ...prev, [group.priority]: !expanded }))}
                  aria-expanded={expanded}
                  aria-controls={groupId}
                  className="flex min-h-11 w-full items-center justify-between gap-2 text-left"
                >
                  <h3 className="text-base font-bold text-text-primary">
                    {copy.analysesResult.priorityGroup(group.priority, group.items.length)}
                  </h3>
                  <ChevronDown
                    className={`size-4 text-text-secondary transition-transform ${expanded ? 'rotate-180' : ''}`}
                    aria-hidden="true"
                  />
                </button>
                {expanded && (
                  <div id={groupId} className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    {group.items.map((r) => (
                      <RecommendationCard
                        key={r.skill}
                        recommendation={r}
                        isDeclared={declaredSkills.has(r.skill)}
                        onMarkLearned={(skill, opener) => setSheet({ skill, opener })}
                      />
                    ))}
                  </div>
                )}
              </section>
            )
          })}
        </div>
      )}

      {sheet && (
        <ModalDialog title={copy.analysesResult.levelSheetTitle(sheet.skill)} variant="sheet" onClose={() => setSheet(null)}>
          <p className="text-sm text-text-secondary">{copy.analysesResult.levelSheetNote}</p>
          <div className="flex flex-col gap-2">
            {LEVELS.map((level) => (
              <button
                key={level}
                type="button"
                onClick={() => chooseLevel(level)}
                className="min-h-11 w-full rounded-lg border border-border-warm px-4 text-left text-sm font-semibold text-text-primary hover:border-brand hover:bg-surface-subtle active:bg-surface-subtle"
              >
                {LEVEL_LABEL[level]}
              </button>
            ))}
          </div>
        </ModalDialog>
      )}
    </div>
  )
}
