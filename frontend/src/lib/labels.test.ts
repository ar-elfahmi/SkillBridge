import { describe, expect, it } from 'vitest'
import { categoryLabel, formatPercent, formatScore, sourceReferenceLabel } from '@/lib/labels'

describe('labels', () => {
  it('menerjemahkan referensi sumber mentah', () => {
    expect(sourceReferenceLabel('weekly_plan, M1', 'weekly_plan')).toBe('Rencana mingguan, minggu 1')
    expect(sourceReferenceLabel(null, 'material')).toBe('Materi')
  })

  it('menerjemahkan kategori dan memberi nilai bawaan', () => {
    expect(categoryLabel('tool')).toBe('Alat')
    expect(categoryLabel('concept')).toBe('Konsep')
    expect(categoryLabel(null)).toBe('Kompetensi umum')
  })

  it('memformat angka dengan koma desimal', () => {
    expect(formatScore(0.77)).toBe('0,77')
    expect(formatPercent(14.2857)).toBe('14,3')
  })
})