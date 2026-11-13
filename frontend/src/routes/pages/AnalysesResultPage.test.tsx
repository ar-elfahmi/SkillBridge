import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AnalysesResultPage } from '@/routes/pages/AnalysesResultPage'
import { analysisApi, authApi } from '@/lib/api/endpoints'
import type { Analysis, RecommendationsResponse, SkillMatch, User } from '@/types/api'

vi.mock('@/lib/api/endpoints', () => ({
  authApi: { me: vi.fn(), logout: vi.fn() },
  analysisApi: { show: vi.fn(), matches: vi.fn(), recommendations: vi.fn() },
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
    job_evidence: [{ job_posting_id: 10, company: 'PT Contoh', evidence: 'React' }],
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
    const tabBtn = screen.getByRole('button', { name: 'Skill Gap' })
    fireEvent.click(tabBtn)

    expect((await screen.findAllByText('React'))[0]).toBeInTheDocument()
    expect(screen.getAllByText('Docker')[0]).toBeInTheDocument()
  })

  it('berpindah ke tab Rekomendasi Belajar', async () => {
    renderPage()

    await screen.findByText('Pemrograman Web')
    fireEvent.click(screen.getByRole('button', { name: 'Rekomendasi Belajar' }))

    expect(await screen.findByText('Terapkan tipe generik pada API client')).toBeInTheDocument()
    expect(screen.getByText('Tipe generik')).toBeInTheDocument()
  })
})