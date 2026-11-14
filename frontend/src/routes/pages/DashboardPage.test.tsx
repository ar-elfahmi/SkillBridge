import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { DashboardPage } from '@/routes/pages/DashboardPage'
import { analysisApi, authApi, industryApi, meApi, rpsApi } from '@/lib/api/endpoints'
import type { RpsDocument, User } from '@/types/api'

vi.mock('@/lib/api/endpoints', () => ({
  authApi: { me: vi.fn(), logout: vi.fn() },
  rpsApi: { list: vi.fn(), upload: vi.fn() },
  analysisApi: { list: vi.fn() },
  industryApi: { careers: vi.fn(), jobs: vi.fn() },
  meApi: { skills: vi.fn(), addSkill: vi.fn(), removeSkill: vi.fn() },
}))

const mockUser: User = {
  id: 1,
  name: 'Anisa Rahma',
  email: 'anisa@skillbridge.id',
  role: 'student',
  created_at: '',
}

const mockRps: RpsDocument[] = [
  {
    id: 10,
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
  },
]

function renderDashboard() {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/dashboard']}>
        <Routes>
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/rps/:id" element={<p>Detail RPS Halaman</p>} />
          <Route path="/login" element={<p>Halaman Login</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  )
}

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.me).mockResolvedValue({ data: mockUser })
    vi.mocked(rpsApi.list).mockResolvedValue({ data: mockRps })
    vi.mocked(analysisApi.list).mockResolvedValue({ data: [] })
    vi.mocked(industryApi.careers).mockResolvedValue({ data: [] })
    vi.mocked(industryApi.jobs).mockResolvedValue({ data: [] })
    vi.mocked(meApi.skills).mockResolvedValue({ data: [] })
  })

  it('merender elemen utama dashboard (hero unggah, stats, daftar RPS)', async () => {
    renderDashboard()

    expect(screen.getAllByText('UNGGAH RPS')[0]).toBeInTheDocument()
    expect(screen.getByText('Pilih atau tarik PDF RPS')).toBeInTheDocument()
    expect(screen.getByText('Skill yang kurang')).toBeInTheDocument()

    expect(await screen.findByText('Pemrograman Web 2025.pdf')).toBeInTheDocument()
    expect(screen.getByText('Analisis selesai')).toBeInTheDocument()
  })

  it('mengarahkan kartu RPS ke hasil analisis bila sudah dianalisis', async () => {
    vi.mocked(analysisApi.list).mockResolvedValue({
      data: [
        {
          analysis_id: 101,
          status: 'completed',
          career: 'Frontend Developer',
          course: 'Pemrograman Web',
          summary: {
            total_skills: 8,
            covered: 1,
            partial: 0,
            missing: 7,
            coverage_percentage: 12.5,
          },
          model_versions: null,
          error_message: null,
          created_at: '',
        },
      ],
    })

    renderDashboard()

    const card = await screen.findByRole('link', { name: /Pemrograman Web 2025\.pdf/ })
    expect(card).toHaveAttribute('href', '/analyses/101')
  })

  it('mengunggah berkas PDF dan mengarahkan ke halaman detail RPS', async () => {
    vi.mocked(rpsApi.upload).mockResolvedValue({
      data: {
        ...mockRps[0]!,
        id: 99,
        original_name: 'Basis Data.pdf',
      },
    })

    renderDashboard()

    const file = new File(['dummy content'], 'Basis Data.pdf', { type: 'application/pdf' })
    const input = document.querySelector('input[type="file"]') as HTMLInputElement

    fireEvent.change(input, { target: { files: [file] } })

    await waitFor(() => {
      expect(rpsApi.upload).toHaveBeenCalledWith(file)
    })

    expect(await screen.findByText('Detail RPS Halaman')).toBeInTheDocument()
  })
})
