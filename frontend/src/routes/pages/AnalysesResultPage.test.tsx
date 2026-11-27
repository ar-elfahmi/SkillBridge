import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
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
  rps_document_id: 1,
  job_count: 2,
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

  it('menampilkan panel proses bertahap tanpa tab selama analisis diproses', async () => {
    vi.mocked(analysisApi.show).mockResolvedValue({
      data: { ...mockAnalysis, status: 'processing', summary: null },
    })
    renderPage()

    expect(await screen.findByText('Menganalisis skill…')).toBeInTheDocument()
    expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
    const steps = screen.getAllByRole('listitem')
    expect(steps).toHaveLength(3)
    expect(steps[0]).toHaveTextContent('Menunggu antrian: selesai')
    expect(steps[1]).toHaveTextContent('Membandingkan skill lowongan dengan RPS: sedang berjalan')
    expect(steps[1]).toHaveAttribute('aria-current', 'step')
    expect(steps[2]).toHaveTextContent('Menyusun rekomendasi: menunggu')
    expect(screen.getByRole('status')).toHaveTextContent('Menganalisis skill…')
    const homeLinks = screen.getAllByRole('link', { name: 'Kembali ke beranda' })
    expect(homeLinks.at(-1)).toHaveAttribute('href', '/dashboard')
    expect(homeLinks.at(-1)).toHaveTextContent('Kembali ke beranda')
  })

  it('menandai langkah antrian sebagai aktif saat analisis masih antre', async () => {
    vi.mocked(analysisApi.show).mockResolvedValue({
      data: { ...mockAnalysis, status: 'queued', summary: null },
    })
    renderPage()

    await screen.findByText('Menganalisis skill…')
    const steps = screen.getAllByRole('listitem')
    expect(steps[0]).toHaveAttribute('aria-current', 'step')
    expect(steps[1]).toHaveTextContent('menunggu')
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

  describe('bukti lowongan, filter, dan rekomendasi', () => {
    function jobEvidence(id: number, quote: string | null) {
      return {
        job_posting_id: id,
        title: `Lowongan ${id}`,
        company: `PT ${id}`,
        source_name: 'Remotive',
        source_url: `https://example.com/lowongan/${id}`,
        evidence: quote,
      }
    }

    async function openSkill(name: string) {
      await screen.findByText('Pemrograman Web')
      fireEvent.click(screen.getByRole('tab', { name: 'Skill' }))
      const buttons = await screen.findAllByRole('button', { name })
      fireEvent.click(buttons[0]!)
      return screen.findByRole('dialog')
    }

    it('membatasi bukti lowongan ke 3 kelompok, membuang awalan "- ", dan menggabungkan kutipan identik', async () => {
      vi.mocked(analysisApi.matches).mockResolvedValue({
        data: [
          {
            ...mockMatches[1]!,
            skill: 'Requirements Analysis',
            demand_count: 7,
            job_evidence: [
              jobEvidence(1, '- Requirements Analysis'),
              jobEvidence(2, '- requirements   analysis'),
              jobEvidence(3, '- Requirements Analysis'),
              jobEvidence(4, '- Stakeholder management'),
              jobEvidence(5, '- Elicitation'),
              jobEvidence(6, '- Documentation'),
              jobEvidence(7, '- Agile delivery'),
            ],
            job_evidence_total: 7,
          },
        ],
      })
      renderPage()

      const dialog = await openSkill('Requirements Analysis')

      expect(dialog).toHaveTextContent('Disebut serupa di 3 lowongan')
      expect(dialog).not.toHaveTextContent('“- ')
      expect(dialog).toHaveTextContent('“Requirements Analysis”')
      expect(dialog).toHaveTextContent('Stakeholder management')
      expect(dialog).toHaveTextContent('Elicitation')
      expect(dialog).not.toHaveTextContent('Documentation')
      expect(dialog).not.toHaveTextContent('Agile delivery')

      fireEvent.click(screen.getByRole('button', { name: 'Lihat 2 lowongan lain' }))
      expect(dialog).toHaveTextContent('Documentation')
      expect(dialog).toHaveTextContent('Agile delivery')
      expect(screen.queryByRole('button', { name: /Lihat \d+ lowongan lain/ })).not.toBeInTheDocument()
    })

    it('mengurutkan skill menurut permintaan dan menandai kecocokan lemah pada status Sebagian', async () => {
      vi.mocked(analysisApi.matches).mockResolvedValue({
        data: [
          { ...mockMatches[1]!, skill: 'Docker', demand_count: 1 },
          {
            ...mockMatches[0]!,
            skill: 'Python',
            demand_count: 9,
            classification: 'partial',
            matched_competency: {
              id: 5,
              competency: 'Menyusun jadwal proyek',
              evidence: null,
              source_reference: null,
              page: null,
            },
          },
        ],
      })
      renderPage()

      await screen.findByText('Pemrograman Web')
      fireEvent.click(screen.getByRole('tab', { name: 'Skill' }))
      const names = (await screen.findAllByRole('button', { name: /^(Python|Docker)$/ })).map((b) => b.textContent)
      expect(names).toEqual(['Python', 'Docker'])
      expect(screen.getAllByText('Kecocokan lemah').length).toBeGreaterThan(0)
    })

    describe('di ponsel', () => {
      beforeEach(() => {
        vi.stubGlobal('matchMedia', (query: string) => ({
          matches: true,
          media: query,
          addEventListener: vi.fn(),
          removeEventListener: vi.fn(),
        }))
      })
      afterEach(() => {
        vi.unstubAllGlobals()
      })

      it('memakai satu baris cari dan tombol filter yang membuka sheet status', async () => {
        renderPage()

        await screen.findByText('Pemrograman Web')
        fireEvent.click(screen.getByRole('tab', { name: 'Skill' }))
        await screen.findAllByRole('button', { name: /^React/ })
        // Chip status dan kotak centang tidak ada di baris kontrol.
        expect(screen.queryByRole('button', { name: /Tercakup \(/ })).not.toBeInTheDocument()
        expect(screen.queryByLabelText('Sembunyikan yang sudah dilaporkan')).not.toBeInTheDocument()

        fireEvent.click(screen.getByRole('button', { name: 'Filter' }))
        const sheet = await screen.findByRole('dialog', { name: 'Filter skill' })
        expect(sheet).toHaveTextContent('Sembunyikan yang sudah dilaporkan')
        const missingOption = within(sheet).getByRole('button', { name: /Belum ditemukan/ })
      expect(missingOption).toHaveAttribute('aria-pressed', 'false')
      fireEvent.click(missingOption)
      expect(within(sheet).getByRole('button', { name: /Belum ditemukan/ })).toHaveAttribute('aria-pressed', 'true')
        fireEvent.click(screen.getByRole('button', { name: 'Selesai' }))

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
        expect(screen.queryByRole('button', { name: /^React/ })).not.toBeInTheDocument()
        expect(screen.getByRole('button', { name: /Docker/ })).toBeInTheDocument()
        expect(screen.getByRole('button', { name: /Filter/ })).toHaveTextContent('1')
      })
    })

    it('menormalkan nama mata kuliah HURUF BESAR di judul', async () => {
      vi.mocked(analysisApi.show).mockResolvedValue({
        data: { ...mockAnalysis, course: 'MANAJEMEN PROYEK PERANGKAT LUNAK (PRAKTIKUM)' },
      })
      renderPage()

      const heading = await screen.findByRole('heading', { level: 1 })
      expect(heading).toHaveTextContent('Manajemen Proyek Perangkat Lunak (Praktikum)')
      expect(heading.className).toContain('line-clamp-2')
    })

    it('memberi judul bawaan "Pelajari {skill}" saat judul rekomendasi kosong', async () => {
      vi.mocked(analysisApi.recommendations).mockResolvedValue({
        data: [{ ...mockReco.data[0]!, skill: 'Python', title: '' }],
        meta: { self_declared_skills: [] },
      })
      renderPage()

      await screen.findByText('Pemrograman Web')
      fireEvent.click(screen.getByRole('tab', { name: 'Rekomendasi' }))
      expect(await screen.findByText('Pelajari Python')).toBeInTheDocument()
    })

    it('memberi judul bawaan saat judul rekomendasi null atau hanya spasi', async () => {
      vi.mocked(analysisApi.recommendations).mockResolvedValue({
        data: [
          { ...mockReco.data[0]!, skill: 'Python', title: null as unknown as string },
          { ...mockReco.data[0]!, skill: 'Docker', title: '   ' },
        ],
        meta: { self_declared_skills: [] },
      })
      renderPage()

      await screen.findByText('Pemrograman Web')
      fireEvent.click(screen.getByRole('tab', { name: 'Rekomendasi' }))
      expect(await screen.findByText('Pelajari Python')).toBeInTheDocument()
      expect(screen.getByText('Pelajari Docker')).toBeInTheDocument()
    })

    it('meminta tingkat lewat bottom sheet, menampilkan toast, dan Urungkan menghapus skill', async () => {
      vi.mocked(analysisApi.recommendations).mockResolvedValue({
        data: [
          mockReco.data[0]!,
          { ...mockReco.data[0]!, skill: 'Docker', title: 'Docker dasar' },
        ],
        meta: { self_declared_skills: [] },
      })
      vi.mocked(meApi.addSkill).mockResolvedValue({
        data: {
          id: 77,
          skill_id: 1,
          skill: 'TypeScript',
          canonical_skill: 'TypeScript',
          reported_as: 'TypeScript',
          level: 'advanced',
          created_at: '',
        },
      })
      vi.mocked(meApi.removeSkill).mockResolvedValue(undefined)
      renderPage()

      await screen.findByText('Pemrograman Web')
      fireEvent.click(screen.getByRole('tab', { name: 'Rekomendasi' }))
      const toggles = await screen.findAllByRole('button', { name: /TypeScript/ })
      fireEvent.click(toggles[0]!)
      fireEvent.click(screen.getByRole('button', { name: 'Tandai sudah dipelajari' }))

      const sheet = await screen.findByRole('dialog', { name: 'Tingkat Anda untuk TypeScript' })
      expect(sheet).toHaveTextContent('Pemula')
      expect(sheet).toHaveTextContent('Menengah')
      fireEvent.click(screen.getByRole('button', { name: 'Mahir' }))

      await waitFor(() =>
        expect(meApi.addSkill).toHaveBeenCalledWith({
          skill: 'TypeScript',
          level: 'advanced',
          analysis_id: 101,
        })
      )
      expect(await screen.findByText('TypeScript ditambahkan ke skill Anda.')).toBeInTheDocument()
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
      // Fokus pindah ke kartu rekomendasi berikutnya.
      await waitFor(() => expect(screen.getByRole('button', { name: /Docker/ })).toHaveFocus())

      fireEvent.click(screen.getByRole('button', { name: 'Urungkan' }))
      await waitFor(() => expect(meApi.removeSkill).toHaveBeenCalledWith(77))
      await waitFor(() =>
        expect(screen.queryByText('TypeScript ditambahkan ke skill Anda.')).not.toBeInTheDocument()
      )
    })

    it('Escape menutup sheet tingkat tanpa menyimpan', async () => {
      renderPage()

      await screen.findByText('Pemrograman Web')
      fireEvent.click(screen.getByRole('tab', { name: 'Rekomendasi' }))
      fireEvent.click(await screen.findByRole('button', { name: /TypeScript/ }))
      fireEvent.click(screen.getByRole('button', { name: 'Tandai sudah dipelajari' }))
      await screen.findByRole('dialog')

      fireEvent.keyDown(document, { key: 'Escape' })
      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
      expect(meApi.addSkill).not.toHaveBeenCalled()
    })
  })
})