import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AnalysesNewPage } from '@/routes/pages/AnalysesNewPage'
import { aiApi, analysisApi, authApi, industryApi, rpsApi } from '@/lib/api/endpoints'
import type { Career, RpsDocument, User } from '@/types/api'

vi.mock('@/lib/api/endpoints', () => ({
  aiApi: { quota: vi.fn() },
  authApi: { me: vi.fn(), logout: vi.fn() },
  rpsApi: { show: vi.fn(), careerSuggestions: vi.fn() },
  industryApi: { careers: vi.fn() },
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
  { id: 1, name: 'Data Analyst', slug: 'data-analyst', description: 'Mengolah data', eligible_job_count: 0 },
  { id: 2, name: 'Frontend Developer', slug: 'frontend-developer', description: 'Membangun UI web', eligible_job_count: 5 },
  { id: 3, name: 'Backend Developer', slug: 'backend-developer', description: 'Membangun API server', eligible_job_count: 8 },
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
    vi.mocked(aiApi.quota).mockResolvedValue({
      data: { available: true, daily_remaining: 100, resets_at: '2026-11-27T08:00:00+00:00' },
    })
    vi.mocked(authApi.me).mockResolvedValue({ data: mockUser })
    vi.mocked(rpsApi.show).mockResolvedValue({ data: mockDoc })
    vi.mocked(industryApi.careers).mockResolvedValue({ data: mockCareers })
    vi.mocked(rpsApi.careerSuggestions).mockResolvedValue({ data: { status: 'unavailable', items: [] } })
    vi.mocked(analysisApi.create).mockReset()
  })

  it('menampilkan profesi dengan ringkasan, tanpa pilihan lowongan', async () => {
    renderAnalysesNew()

    expect(await screen.findByText('Frontend Developer')).toBeInTheDocument()
    expect(screen.getByText('Membangun UI web')).toBeInTheDocument()
    expect(screen.getByText('5 lowongan siap dipakai')).toBeInTheDocument()
    expect(screen.queryByText('Pilih lowongan')).not.toBeInTheDocument()
    expect(screen.queryByText(/Tambah lowongan/)).not.toBeInTheDocument()
    expect(screen.getByText(/hingga 20 skill/)).toBeInTheDocument()
  })

  it('memilih profesi pertama yang siap sebagai default dan menonaktifkan yang belum siap', async () => {
    renderAnalysesNew()

    await screen.findByText('Frontend Developer')
    expect(screen.getByRole('radio', { name: /Frontend Developer/ })).toBeChecked()
    expect(screen.getByRole('radio', { name: /Data Analyst/ })).toBeDisabled()
    expect(screen.getByText('Belum ada lowongan siap dipakai')).toBeInTheDocument()
  })

  it('mengurutkan saran AI di atas dan menjadikannya pilihan awal', async () => {
    vi.mocked(rpsApi.careerSuggestions).mockResolvedValue({
      data: {
        status: 'completed',
        items: [
          { career_id: 3, name: 'Backend Developer', reason: 'RPS membahas REST API dan basis data.' },
          { career_id: 1, name: 'Data Analyst', reason: 'Ada unsur data.' },
        ],
      },
    })

    renderAnalysesNew()

    expect(await screen.findByText('RPS membahas REST API dan basis data.')).toBeInTheDocument()
    const radios = screen.getAllByRole('radio')
    expect(radios[0]).toHaveAccessibleName(/Backend Developer/)
    expect(radios[0]).toBeChecked()
    // Saran untuk profesi tanpa lowongan siap tetap tampil, tetapi tidak bisa dipilih.
    expect(screen.getByRole('radio', { name: /Data Analyst/ })).toBeDisabled()
    expect(screen.getAllByText('Saran AI untuk RPS ini')).toHaveLength(2)
  })

  it('menampilkan status menyiapkan saran saat masih diproses', async () => {
    vi.mocked(rpsApi.careerSuggestions).mockResolvedValue({ data: { status: 'processing', items: [] } })

    renderAnalysesNew()

    expect(await screen.findByText('Menyiapkan saran profesi untuk RPS Anda…')).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: /Frontend Developer/ })).toBeChecked()
  })

  it('memulai analisis hanya dengan RPS dan profesi pilihan', async () => {
    vi.mocked(analysisApi.create).mockResolvedValue({
      data: {
        analysis_id: 101,
        status: 'queued',
        career: 'Backend Developer',
        course: 'Pemrograman Web',
        summary: null,
        model_versions: null,
        error_message: null,
        created_at: '',
      },
    })

    renderAnalysesNew()

    await screen.findByText('Backend Developer')
    fireEvent.click(screen.getByRole('radio', { name: /Backend Developer/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Mulai Analisis' }))

    await waitFor(() => {
      expect(analysisApi.create).toHaveBeenCalledWith({ rps_document_id: 2, career_id: 3 })
    })
    expect(await screen.findByText('Hasil Analisis Halaman')).toBeInTheDocument()
  })

  it('menampilkan pesan error dari server saat analisis ditolak', async () => {
    vi.mocked(analysisApi.create).mockRejectedValue({
      body: { message: 'Belum ada lowongan yang siap dipakai untuk profesi ini.' },
    })

    renderAnalysesNew()

    await screen.findByText('Frontend Developer')
    fireEvent.click(screen.getByRole('button', { name: 'Mulai Analisis' }))

    expect(
      await screen.findByText('Belum ada lowongan yang siap dipakai untuk profesi ini.')
    ).toBeInTheDocument()
  })
})