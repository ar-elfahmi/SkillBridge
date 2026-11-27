import type { CompetencySourceType, SkillCategory, SkillLevel } from '@/types/api'

const CATEGORY_LABEL: Record<SkillCategory, string> = {
  language: 'Bahasa pemrograman',
  framework: 'Kerangka kerja',
  tool: 'Alat',
  concept: 'Konsep',
  practice: 'Praktik',
  other: 'Lainnya',
}

const SOURCE_TYPE_LABEL: Record<CompetencySourceType, string> = {
  CPL: 'CPL',
  CPMK: 'CPMK',
  'Sub-CPMK': 'Sub-CPMK',
  material: 'Materi',
  weekly_plan: 'Rencana mingguan',
  assignment: 'Tugas',
  user: 'Ditambahkan pengguna',
}

export const LEVEL_LABEL: Record<SkillLevel, string> = {
  beginner: 'Pemula',
  intermediate: 'Menengah',
  advanced: 'Mahir',
}

export function categoryLabel(category: string | null | undefined): string {
  if (!category) return 'Kompetensi umum'
  return CATEGORY_LABEL[category as SkillCategory] ?? category
}

export function sourceTypeLabel(type: string): string {
  return SOURCE_TYPE_LABEL[type as CompetencySourceType] ?? type
}

/**
 * Turns raw source references such as "weekly_plan, M1" into readable text
 * ("Rencana mingguan, minggu 1"). Unknown tokens are kept as they are.
 */
export function sourceReferenceLabel(reference: string | null | undefined, type: string): string {
  if (!reference) return sourceTypeLabel(type)
  return reference
    .split(',')
    .map((part) => part.trim())
    .filter(Boolean)
    .map((part) => {
      const week = /^M(\d+)$/i.exec(part)
      if (week) return `minggu ${week[1]}`
      const known = SOURCE_TYPE_LABEL[part as CompetencySourceType]
      return known ?? part
    })
    .join(', ')
}

const decimal = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const percent = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 })

/** Two decimals with a comma, e.g. 0,77. */
export function formatScore(value: number): string {
  return decimal.format(value)
}

/** One decimal with a comma, e.g. 14,3. */
export function formatPercent(value: number): string {
  return percent.format(value)
}