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

/** "Rencana mingguan, minggu 1, hal. 1". Minggu tidak diulang bila sudah ada pada referensi. */
export function formatSource(
  reference: string | null | undefined,
  type: string,
  week?: number | null,
  page?: number | null
): string {
  let label = sourceReferenceLabel(reference, type)
  if (week && !/minggu\s*\d+/i.test(label)) label += `, minggu ${week}`
  if (page) label += `, hal. ${page}`
  return label
}

const LOWERCASE_WORDS = new Set(['dan', 'di', 'ke', 'dari', 'untuk', 'yang', 'pada', 'dengan', 'atau', 'serta'])

/**
 * Nama mata kuliah dari PDF sering HURUF BESAR semua. Nama seperti itu ditampilkan sebagai Title Case.
 * Nama yang sudah bercampur huruf dibiarkan agar singkatan seperti "AWS re/Start" tidak rusak.
 */
export function toDisplayTitle(name: string): string {
  const letters = name.replace(/[^\p{L}]/gu, '')
  if (letters.length === 0 || letters !== letters.toUpperCase()) return name
  let first = true
  return name
    .toLowerCase()
    .replace(/[\p{L}\p{N}]+/gu, (word, offset: number) => {
      const original = name.slice(offset, offset + word.length)
      const isFirst = first
      first = false
      if (/^[IVX]+$/.test(original) && original.length <= 4) return original
      if (/\d/.test(word)) return original
      if (!isFirst && LOWERCASE_WORDS.has(word)) return word
      return word.charAt(0).toUpperCase() + word.slice(1)
    })
}

/** Kode mata kuliah unik dan tidak peka huruf besar: dirapikan menjadi huruf besar tanpa spasi tepi. */
export function normalizeCourseCode(code: string | null | undefined): string {
  return (code ?? '').trim().toUpperCase()
}

const RECENT_WINDOW_MS = 2 * 60 * 60 * 1000

/** True bila `createdAt` jatuh dalam 2 jam terakhir. */
export function isRecent(createdAt: string | null | undefined, now: number = Date.now()): boolean {
  if (!createdAt) return false
  const time = new Date(createdAt).getTime()
  return Number.isFinite(time) && now - time >= 0 && now - time < RECENT_WINDOW_MS
}

const dateFormatter = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })

/** "28 Nov 2026"; string kosong bila tanggal tidak valid. */
export function formatDate(value: string | null | undefined): string {
  if (!value) return ''
  const time = new Date(value)
  return Number.isNaN(time.getTime()) ? '' : dateFormatter.format(time)
}