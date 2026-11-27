import { describe, expect, it } from 'vitest'
import {
  categoryLabel,
  formatDate,
  formatPercent,
  formatScore,
  formatSource,
  isRecent,
  normalizeCourseCode,
  sourceReferenceLabel,
  toDisplayTitle,
} from '@/lib/labels'

describe('labels', () => {
  it('menerjemahkan referensi sumber mentah', () => {
    expect(sourceReferenceLabel('weekly_plan, M1', 'weekly_plan')).toBe('Rencana mingguan, minggu 1')
    expect(sourceReferenceLabel(null, 'material')).toBe('Materi')
  })

  it('menyusun sumber lengkap: minggu dan halaman, tanpa mengulang minggu', () => {
    expect(formatSource('weekly_plan, M1', 'weekly_plan', 1, 1)).toBe('Rencana mingguan, minggu 1, hal. 1')
    expect(formatSource('Sub-CPMK 3', 'Sub-CPMK', 2, 4)).toBe('Sub-CPMK 3, minggu 2, hal. 4')
    expect(formatSource(null, 'material', null, null)).toBe('Materi')
  })

  it('menormalkan nama mata kuliah HURUF BESAR menjadi Title Case dan membiarkan campuran huruf', () => {
    expect(toDisplayTitle('MANAJEMEN PROYEK PERANGKAT LUNAK (PRAKTIKUM)')).toBe(
      'Manajemen Proyek Perangkat Lunak (Praktikum)'
    )
    expect(toDisplayTitle('PEMROGRAMAN DAN BASIS DATA II')).toBe('Pemrograman dan Basis Data II')
    expect(toDisplayTitle('AWS re/Start')).toBe('AWS re/Start')
    expect(toDisplayTitle('')).toBe('')
  })

  it('menandai dokumen baru hanya dalam 2 jam terakhir', () => {
    const now = new Date('2026-11-28T12:00:00Z').getTime()
    expect(isRecent('2026-11-28T10:30:00Z', now)).toBe(true)
    expect(isRecent('2026-11-28T09:00:00Z', now)).toBe(false)
    expect(isRecent('2026-11-26T12:00:00Z', now)).toBe(false)
    expect(isRecent('', now)).toBe(false)
    expect(isRecent(undefined, now)).toBe(false)
  })

  it('menormalkan kode mata kuliah menjadi huruf besar tanpa spasi tepi', () => {
    expect(normalizeCourseCode('  sir008 ')).toBe('SIR008')
    expect(normalizeCourseCode(null)).toBe('')
    expect(normalizeCourseCode(undefined)).toBe('')
  })

  it('memformat tanggal dalam locale Indonesia', () => {
    expect(formatDate('2026-11-28T10:00:00Z')).toMatch(/28 Nov(?:ember)? 2026/)
    expect(formatDate('bukan tanggal')).toBe('')
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