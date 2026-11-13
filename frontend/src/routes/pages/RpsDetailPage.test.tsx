import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RpsDetailPage } from '@/routes/pages/RpsDetailPage'
import { authApi, rpsApi } from '@/lib/api/endpoints'
import type { CourseSkill, RpsDocument, User } from '@/types/api'

vi.mock('@/lib/api/endpoints', () => ({
  authApi: { me: vi.fn(), logout: vi.fn() },
  rpsApi: {
    show: vi.fn(),
    updateMetadata: vi.fn(),
    startExtraction: vi.fn(),
    competencies: vi.fn(),
    updateCompetency: vi.fn(),
    createCompetency: vi.fn(),
    confirmReview: vi.fn(),
  },
}))

const mockUser: User = {
  id: 1,
  name: 'Anisa Rahma',
  email: 'anisa@skillbridge.id',
  role: 'student',
  created_at: '',
}

const mockDocTahap0: RpsDocument = {
  id: 12,
  original_name: 'Pemrograman Web 2025.pdf',
  parse_status: 'parsed',
  extraction_status: 'pending',
  review_status: 'unreviewed',
  course: null,
  detected_metadata: {
    code: 'IF2013',
    name: 'Pemrograman Web',
    credits: 4,
    semester: 4,
    study_program: 'Teknik Informatika',
  },
  page_count: 18,
  error_message: null,
  created_at: '',
}

const mockCompetencies: CourseSkill[] = [
  {
    id: 101,
    skill: 'Algoritma dasar',
    canonical_skill: 'Algoritma',
    category: 'concept',
    competency: 'Mampu membuat algoritma sederhana',
    evidence: 'Mahasiswa mampu membuat algoritma sederhana',
    source_type: 'Sub-CPMK',
    source_reference: 'Sub-CPMK 3',
    week: 2,
    page: 4,
    verification_status: 'verified',
    origin: 'ai',
    review_status: 'confirmed',
    user_edited: false,
  },
  {
    id: 102,
    skill: 'Struktur data',
    canonical_skill: 'Struktur Data',
    category: 'concept',
    competency: 'Memahami array dan list',
    evidence: 'Memahami array dan linked list',
    source_type: 'Sub-CPMK',
    source_reference: 'Sub-CPMK 5',
    week: 4,
    page: 9,
    verification_status: 'unverified',
    origin: 'ai',
    review_status: 'pending',
    user_edited: false,
  },
]

function renderRpsDetail(docId = 12) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[`/rps/${docId}`]}>
        <Routes>
          <Route path="/rps/:id" element={<RpsDetailPage />} />
          <Route path="/dashboard" element={<p>Halaman Dashboard</p>} />
          <Route path="/analyses/new" element={<p>Halaman Pemetaan Baru</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  )
}

describe('RpsDetailPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.me).mockResolvedValue({ data: mockUser })
  })

  it('merender Tahap 0 (Metadata form) saat ekstraksi belum selesai', async () => {
    vi.mocked(rpsApi.show).mockResolvedValue({ data: mockDocTahap0 })

    renderRpsDetail()

    expect(await screen.findByText('Pemrograman Web 2025.pdf')).toBeInTheDocument()
    expect(screen.getByText('Metadata mata kuliah')).toBeInTheDocument()
    expect(screen.getByDisplayValue('IF2013')).toBeInTheDocument()
    expect(screen.getByText('Konfirmasi & mulai ekstraksi')).toBeInTheDocument()
  })

  it('mengirim trigger mulai ekstraksi saat tombol diklik', async () => {
    vi.mocked(rpsApi.show).mockResolvedValue({ data: mockDocTahap0 })
    vi.mocked(rpsApi.startExtraction).mockResolvedValue({
      data: { ...mockDocTahap0, extraction_status: 'processing' },
    })

    renderRpsDetail()

    await screen.findByText('Pemrograman Web 2025.pdf')
    const startBtn = screen.getByText('Konfirmasi & mulai ekstraksi')
    fireEvent.click(startBtn)

    await waitFor(() => {
      expect(rpsApi.startExtraction).toHaveBeenCalledWith(12)
    })
  })

  it('merender Tahap 1 (Review Tabel Kompetensi) saat ekstraksi completed', async () => {
    const docTahap1: RpsDocument = {
      ...mockDocTahap0,
      extraction_status: 'completed',
    }
    vi.mocked(rpsApi.show).mockResolvedValue({ data: docTahap1 })
    vi.mocked(rpsApi.competencies).mockResolvedValue({ data: mockCompetencies })

    renderRpsDetail()

    expect(await screen.findByText('Total kompetensi')).toBeInTheDocument()
    // Karena desktop (tabel) & mobile (kartu) keduanya di-render dalam DOM responsive, gunakan getAllByText
    expect(await screen.findAllByText('Algoritma dasar')).toHaveLength(2)
    expect(screen.getAllByText('Struktur data')).toHaveLength(2)
    expect(screen.getAllByText('Konfirmasi hasil ekstraksi')[0]).toBeInTheDocument()
  })
})
