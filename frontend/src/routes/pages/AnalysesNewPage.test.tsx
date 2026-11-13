import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AnalysesNewPage } from '@/routes/pages/AnalysesNewPage'
import { analysisApi, authApi, industryApi, rpsApi } from '@/lib/api/endpoints'
import type { Career, JobPosting, RpsDocument, User } from '@/types/api'

vi.mock('@/lib/api/endpoints', () => ({
  authApi: { me: vi.fn(), logout: vi.fn() },
  rpsApi: { show: vi.fn() },
  industryApi: {
    careers: vi.fn(),
    jobs: vi.fn(),
    createJob: vi.fn(),
  },
  analysisApi: { create: vi.fn() },
}))

const mockUser: User = {
  id: 1,
  name: 'Anisa Rahma',
  email: 'anisa@skillbridge.id',
  role: 'student',
  created_at: '',
}

const mockDoc: RpsDocument = {
  id: 2,
  original_name: 'Pemrograman Web 2025.pdf',
  parse_status: 'parsed',
  extraction_status: 'completed',
  review_status: 'confirmed',
  course: {
    name: 'Pemrograman Web',
    code: 'IF2013',
    credits: 4,
    semester: 4,
    study_program: 'Teknik Informatika',
  },
  detected_metadata: null,
  page_count: 18,
  error_message: null,
  created_at: '',
}

const mockCareers: Career[] = [
  { id: 1, name: 'Frontend Developer', slug: 'frontend-developer', description: 'Membangun UI web' },
  { id: 2, name: 'Backend Developer', slug: 'backend-developer', description: 'Membangun API server' },
]

const mockJobs: JobPosting[] = [
  {
    id: 10,
    career_id: 1,
    career: mockCareers[0]!,
    created_by: null,
    is_curated: true,
    title: 'Frontend Developer',
    company: 'PT Contoh',
    description: 'Membangun web React',
    qualifications: 'React, TypeScript',
    source_name: 'JobStreet',
    source_url: 'https://...',
    collected_at: '2026-11-12',
    dataset_version: 'v1.4',
    extraction_status: 'completed',
    skills: [
      { id: 1, skill: 'React', canonical_skill: 'React', category: 'framework', requirement_text: '', evidence: '' },
      { id: 2, skill: 'TypeScript', canonical_skill: 'TypeScript', category: 'language', requirement_text: '', evidence: '' },
    ],
  },
]

function renderAnalysesNew(rpsId = 2) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[`/analyses/new?rps=${rpsId}`]}>
        <Routes>
          <Route path="/analyses/new" element={<AnalysesNewPage />} />
          <Route path="/analyses/:id" element={<p>Hasil Analisis Halaman</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  )
}

describe('AnalysesNewPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.me).mockResolvedValue({ data: mockUser })
    vi.mocked(rpsApi.show).mockResolvedValue({ data: mockDoc })
    vi.mocked(industryApi.careers).mockResolvedValue({ data: mockCareers })
    vi.mocked(industryApi.jobs).mockResolvedValue({ data: mockJobs })
  })

  it('merender tahapan pemetaan industri: pilih profesi, pilih lowongan, dan pratinjau', async () => {
    renderAnalysesNew()

    expect(await screen.findByText('Pemetaan industri')).toBeInTheDocument()
    expect(screen.getByText('Pilih profesi')).toBeInTheDocument()
    expect(screen.getByText('Pilih lowongan')).toBeInTheDocument()
    expect(screen.getByText('Pratinjau pemetaan awal')).toBeInTheDocument()

    // Cek lowongan muncul
    expect(await screen.findByText('PT Contoh')).toBeInTheDocument()
    expect(screen.getByText('Membangun UI web')).toBeInTheDocument()
  })

  it('memilih lowongan, menampilkan pratinjau skill, dan memicu mulai analisis', async () => {
    vi.mocked(analysisApi.create).mockResolvedValue({
      data: {
        analysis_id: 101,
        status: 'completed',
        career: 'Frontend Developer',
        course: 'Pemrograman Web',
        summary: null,
        model_versions: null,
        error_message: null,
        created_at: '',
      },
    })

    renderAnalysesNew()

    await screen.findByText('PT Contoh')

    // Klik checkbox pilih lowongan
    const checkbox = screen.getByRole('button', { name: 'Pilih lowongan' })
    fireEvent.click(checkbox)

    // Skill React dan TypeScript harus muncul di list dan Step 3 Pratinjau
    expect((await screen.findAllByText('React'))[0]).toBeInTheDocument()
    expect(screen.getAllByText('TypeScript')[0]).toBeInTheDocument()

    // Klik Mulai Analisis
    const submitBtn = screen.getByRole('button', { name: 'Mulai Analisis' })
    fireEvent.click(submitBtn)

    await waitFor(() => {
      expect(analysisApi.create).toHaveBeenCalledWith({
        rps_document_id: 2,
        career_id: 1,
        job_posting_ids: [10],
      })
    })

    expect(await screen.findByText('Hasil Analisis Halaman')).toBeInTheDocument()
  })
})
