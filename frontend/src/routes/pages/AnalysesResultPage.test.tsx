import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AnalysesResultPage } from '@/routes/pages/AnalysesResultPage'
import { analysisApi, authApi, meApi } from '@/lib/api/endpoints'
import type { Analysis, RecommendationsResponse, SkillMatch, User } from '@/types/api'

vi.mock('@/lib/api/endpoints', () => ({
  authApi: { me: vi.fn(), logout: vi.fn() },
  analysisApi: {
    show: vi.fn(),
    matches: vi.fn(),
    recommendations: vi.fn(),
    refreshRecommendations: vi.fn(),
  },
  meApi: { skills: vi.fn(), addSkill: vi.fn(), removeSkill: vi.fn() },
}))

const mockUser: User = {
  id: 1,
  name: 'Anisa Rahma',
  email: 'anisa@skillbridge.id',
  role: 'student',
  created_at: '',
}

const mockAnalysis: Analysis = {
  analysis_id: 101,
  status: 'completed',
  career: 'Frontend Developer',
  course: 'Pemrograman Web',
  summary: {
    total_skills: 24,
    covered: 10,
    partial: 5,
    missing: 9,
    coverage_percentage: 41.67,
  },
  model_versions: null,
  error_message: null,
  created_at: '2026-11-12T10:30:00.000000Z',
}

const mockMatches: SkillMatch[] = [
  {
    skill: 'React',
    demand_count: 2,
    self_declared: false,
    classification: 'covered',
    similarity_score: 0.92,
    reason: 'Sub-CPMK menyebut React.',
    matched_competency: {
      id: 1,
      competency: 'Membangun antarmuka dengan React',
      evidence: 'Membangun antarmuka dengan React',
      source_reference: 'Sub-CPMK 3',
      page: 4,
    },
    job_evidence: [
      {
        job_posting_id: 10,
        title: 'Frontend Engineer',
        company: 'PT Contoh',
        source_name: 'Remotive',
        source_url: 'https://example.com/lowongan/10',
        evidence: 'React',
      },
      {
        job_posting_id: 11,
        title: 'UI Engineer',
        company: 'PT Tanpa Tautan',
        source_name: null,
        source_url: null,
        evidence: 'React dan TypeScript',
      },
    ],
    job_evidence_total: 3,
  },
  {
    skill: 'Docker',
    demand_count: 0,
    self_declared: false,
    classification: 'missing',
    similarity_score: null,
    reason: 'Tidak ditemukan dalam RPS.',
    matched_competency: null,
    job_evidence: [],
    job_evidence_total: 0,
  },
]

const mockReco: RecommendationsResponse = {
  data: [
    {
      skill: 'TypeScript',
      priority: 'high',
      title: 'Terapkan tipe generik pada API client',
      reason: 'Skill diminta di lowongan tetapi belum muncul penuh di RPS.',
      details: {
        topics: ['Tipe generik', 'Strict mode'],
        learning_keywords: ['typescript', 'generics'],
      },
    },
  ],
  meta: { self_declared_skills: [] },
}

function renderPage(id = 101) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[`/analyses/${id}`]}>
        <Routes>
          <Route path="/analyses/:id" element={<AnalysesResultPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  )
}

describe('AnalysesResultPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.me).mockResolvedValue({ data: mockUser })
    vi.mocked(analysisApi.show).mockResolvedValue({ data: mockAnalysis })
    vi.mocked(analysisApi.matches).mockResolvedValue({ data: mockMatches })
    vi.mocked(analysisApi.recommendations).mockResolvedValue(mockReco)
    vi.mocked(meApi.skills).mockResolvedValue({ data: [] })
  })

  it('merender tab Ringkasan dengan cakupan skill', async () => {
    renderPage()

    expect(await screen.findByText('Pemrograman Web')).toBeInTheDocument()
    expect(screen.getByText('Analisis selesai')).toBeInTheDocument()
    expect(screen.getAllByText('41,7%').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Tercakup').length).toBeGreaterThan(0)
  })

  it('berpindah ke tab Skill Gap dan menampilkan tabel skill', async () => {
    renderPage()

    await screen.findByText('Pemrograman Web')
    const tabBtn = screen.getByRole('tab', { name: 'Skill' })
    fireEvent.click(tabBtn)

    expect((await screen.findAllByText('React'))[0]).toBeInTheDocument()
    expect(screen.getAllByText('Docker')[0]).toBeInTheDocument()
  })

  it('membuka panel bukti sebagai dialog dan menutupnya dengan Escape', async () => {
    renderPage()

    await screen.findByText('Pemrograman Web')
    fireEvent.click(screen.getByRole('tab', { name: 'Skill' }))

    const skillButtons = await screen.findAllByRole('button', { name: 'React' })
    fireEvent.click(skillButtons[0]!)

    expect(await screen.findByRole('dialog')).toBeInTheDocument()

    fireEvent.keyDown(document, { key: 'Escape' })
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('menampilkan bukti lowongan dengan judul, tautan aman, dan sisa lowongan', async () => {
    renderPage()

    await screen.findByText('Pemrograman Web')
    fireEvent.click(screen.getByRole('tab', { name: 'Skill' }))
    const skillButtons = await screen.findAllByRole('button', { name: 'React' })
    fireEvent.click(skillButtons[0]!)

    const dialog = await screen.findByRole('dialog')
    expect(dialog).toHaveTextContent('Frontend Engineer')
    expect(dialog).toHaveTextContent('UI Engineer')

    const links = screen.getAllByRole('link', { name: /Buka lowongan/ })
    expect(links).toHaveLength(1)
    expect(links[0]).toHaveAttribute('href', 'https://example.com/lowongan/10')
    expect(links[0]).toHaveAttribute('rel', 'noopener noreferrer')
    expect(dialog).toHaveTextContent('dan 1 lowongan lain')
  })

  it('berpindah ke tab Rekomendasi Belajar', async () => {
    renderPage()

    await screen.findByText('Pemrograman Web')
    fireEvent.click(screen.getByRole('tab', { name: 'Rekomendasi' }))

    expect(await screen.findByText('Prioritas tinggi (1)')).toBeInTheDocument()
    const card = await screen.findByRole('button', { name: /Terapkan tipe generik pada API client/ })
    expect(card).toHaveAttribute('aria-expanded', 'false')
    expect(screen.queryByText('Tipe generik')).not.toBeInTheDocument()

    fireEvent.click(card)
    expect(card).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getByText('Tipe generik')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Tandai sudah dipelajari' })).toBeInTheDocument()
  })

  it('menampilkan spinner loading saat rekomendasi belajar sedang dimuat', async () => {
    // Return pending promise to simulate in-flight query
    vi.mocked(analysisApi.recommendations).mockReturnValue(new Promise(() => {}))

    renderPage()

    await screen.findByText('Pemrograman Web')
    fireEvent.click(screen.getByRole('tab', { name: 'Rekomendasi' }))

    expect(screen.getByText('Menyiapkan rekomendasi belajar…')).toBeInTheDocument()
    expect(screen.queryByText('Terapkan tipe generik pada API client')).not.toBeInTheDocument()
  })

  it('terus mengecek rekomendasi sampai job rekomendasi selesai', async () => {
    vi.mocked(analysisApi.recommendations)
      .mockResolvedValueOnce({ data: [], meta: { self_declared_skills: [] } })
      .mockResolvedValue(mockReco)
    renderPage()

    await screen.findByText('Pemrograman Web')
    fireEvent.click(screen.getByRole('tab', { name: 'Rekomendasi' }))

    expect(await screen.findByText('Menyiapkan rekomendasi belajar…')).toBeInTheDocument()
    expect(
      await screen.findByText('Terapkan tipe generik pada API client', {}, { timeout: 7000 }),
    ).toBeInTheDocument()
    expect(analysisApi.recommendations).toHaveBeenCalledTimes(2)
  }, 12000)
})
