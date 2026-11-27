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

const docTahap1: RpsDocument = { ...mockDocTahap0, extraction_status: 'completed' }

function competency(overrides: Partial<CourseSkill> & Pick<CourseSkill, 'id' | 'skill'>): CourseSkill {
  return {
    canonical_skill: overrides.skill,
    category: 'concept',
    competency: `Kompetensi ${overrides.skill}`,
    evidence: `Kutipan ${overrides.skill}`,
    source_type: 'weekly_plan',
    source_reference: 'weekly_plan, M2',
    week: 2,
    page: 4,
    verification_status: 'verified',
    origin: 'ai',
    review_status: 'pending',
    user_edited: false,
    ...overrides,
  }
}

const mockCompetencies: CourseSkill[] = [
  competency({ id: 101, skill: 'Algoritma dasar', review_status: 'confirmed' }),
  competency({ id: 102, skill: 'Struktur data', verification_status: 'unverified' }),
  competency({ id: 103, skill: 'Basis data' }),
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
    vi.clearAllMocks()
    vi.mocked(authApi.me).mockResolvedValue({ data: mockUser })
    vi.mocked(rpsApi.competencies).mockResolvedValue({ data: mockCompetencies })
  })

  describe('langkah metadata', () => {
    it('merender form metadata dengan penanda wajib dan teks bantu kode', async () => {
      vi.mocked(rpsApi.show).mockResolvedValue({ data: mockDocTahap0 })

      renderRpsDetail()

      expect(await screen.findByText('Pemrograman Web 2025.pdf')).toBeInTheDocument()
      expect(screen.getByText('Metadata mata kuliah')).toBeInTheDocument()
      expect(screen.getByDisplayValue('IF2013')).toBeInTheDocument()
      expect(screen.getByText('Kode yang sama dipakai ulang.')).toBeInTheDocument()
      const code = screen.getByLabelText('Kode MK')
      expect(code).toHaveAttribute('autocapitalize', 'characters')
      expect(code).toHaveAttribute('aria-required', 'true')
      expect(screen.getByLabelText('SKS')).toHaveAttribute('inputmode', 'numeric')
      expect(screen.getByLabelText('Semester')).toHaveAttribute('inputmode', 'numeric')
      expect(screen.getByLabelText('Nama mata kuliah').tagName).toBe('TEXTAREA')
    })

    it('hanya menampilkan satu aksi utama saat tidak ada perubahan', async () => {
      vi.mocked(rpsApi.show).mockResolvedValue({ data: mockDocTahap0 })
      vi.mocked(rpsApi.startExtraction).mockResolvedValue({
        data: { ...mockDocTahap0, extraction_status: 'processing' },
      })

      renderRpsDetail()

      await screen.findByText('Pemrograman Web 2025.pdf')
      expect(screen.queryByRole('button', { name: 'Simpan perubahan' })).not.toBeInTheDocument()
      fireEvent.click(screen.getByRole('button', { name: 'Mulai ekstraksi' }))

      await waitFor(() => expect(rpsApi.startExtraction).toHaveBeenCalledWith(12))
      expect(rpsApi.updateMetadata).not.toHaveBeenCalled()
    })

    it('menyimpan perubahan lebih dulu lalu memulai ekstraksi', async () => {
      vi.mocked(rpsApi.show).mockResolvedValue({ data: mockDocTahap0 })
      vi.mocked(rpsApi.updateMetadata).mockResolvedValue({ data: mockDocTahap0 })
      vi.mocked(rpsApi.startExtraction).mockResolvedValue({
        data: { ...mockDocTahap0, extraction_status: 'processing' },
      })

      renderRpsDetail()

      await screen.findByText('Pemrograman Web 2025.pdf')
      fireEvent.change(screen.getByLabelText('Kode MK'), { target: { value: 'IF2099' } })
      expect(screen.getByRole('button', { name: 'Simpan perubahan' })).toBeInTheDocument()
      fireEvent.click(screen.getByRole('button', { name: 'Simpan dan mulai ekstraksi' }))

      await waitFor(() => expect(rpsApi.startExtraction).toHaveBeenCalledWith(12))
      expect(rpsApi.updateMetadata).toHaveBeenCalledWith(
        12,
        expect.objectContaining({ code: 'IF2099', name: 'Pemrograman Web' })
      )
    })

    it('menormalkan kode mata kuliah menjadi huruf besar saat disimpan', async () => {
      vi.mocked(rpsApi.show).mockResolvedValue({ data: mockDocTahap0 })
      vi.mocked(rpsApi.updateMetadata).mockResolvedValue({ data: mockDocTahap0 })
      vi.mocked(rpsApi.startExtraction).mockResolvedValue({
        data: { ...mockDocTahap0, extraction_status: 'processing' },
      })
      renderRpsDetail()

      await screen.findByText('Pemrograman Web 2025.pdf')
      fireEvent.change(screen.getByLabelText('Kode MK'), { target: { value: '  sir008 ' } })
      fireEvent.click(screen.getByRole('button', { name: 'Simpan dan mulai ekstraksi' }))

      await waitFor(() => expect(rpsApi.startExtraction).toHaveBeenCalledWith(12))
      expect(rpsApi.updateMetadata).toHaveBeenCalledWith(12, expect.objectContaining({ code: 'SIR008' }))
    })

    it('mengikat galat kode wajib ke kolomnya: aria-invalid, aria-describedby, dan fokus', async () => {
      vi.mocked(rpsApi.show).mockResolvedValue({ data: mockDocTahap0 })

      renderRpsDetail()

      await screen.findByText('Pemrograman Web 2025.pdf')
      const code = screen.getByLabelText('Kode MK')
      fireEvent.change(code, { target: { value: '' } })
      fireEvent.click(screen.getByRole('button', { name: 'Simpan dan mulai ekstraksi' }))

      const error = await screen.findByText('Kode mata kuliah wajib diisi.')
      expect(error).toHaveAttribute('id', 'courseCode-error')
      expect(code).toHaveAttribute('aria-invalid', 'true')
      expect(code).toHaveAttribute('aria-describedby', 'courseCode-error')
      await waitFor(() => expect(code).toHaveFocus())
      expect(rpsApi.startExtraction).not.toHaveBeenCalled()
      expect(rpsApi.updateMetadata).not.toHaveBeenCalled()

      fireEvent.change(code, { target: { value: 'IF1' } })
      expect(code).not.toHaveAttribute('aria-invalid')
    })

    it('menampilkan loader dan menonaktifkan aksi saat metadata masih dibaca', async () => {
      vi.mocked(rpsApi.show).mockResolvedValue({
        data: { ...mockDocTahap0, parse_status: 'pending', detected_metadata: null },
      })

      renderRpsDetail()

      expect(await screen.findByText('Membaca metadata dokumen…')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: 'Mulai ekstraksi' })).toBeDisabled()
    })

    it('membuka kembali form saat metadata sudah tiba', async () => {
      vi.mocked(rpsApi.show).mockResolvedValue({ data: mockDocTahap0 })

      renderRpsDetail()

      expect(await screen.findByText('Metadata mata kuliah')).toBeInTheDocument()
      expect(screen.queryByText('Membaca metadata dokumen…')).not.toBeInTheDocument()
      expect(screen.getByLabelText('Nama mata kuliah')).toBeEnabled()
    })

    it('mengganti form dengan panel proses dan ringkasan metadata saat ekstraksi berjalan', async () => {
      vi.mocked(rpsApi.show).mockResolvedValue({
        data: { ...mockDocTahap0, extraction_status: 'processing' },
      })

      renderRpsDetail()

      expect(await screen.findByText('Mengekstrak kompetensi…')).toBeInTheDocument()
      expect(screen.getByText(/Biasanya kurang dari 1 menit/)).toBeInTheDocument()
      expect(screen.getByText('IF2013')).toBeInTheDocument()
      expect(screen.queryByLabelText('Kode MK')).not.toBeInTheDocument()
      expect(screen.getAllByRole('status').length).toBeGreaterThan(0)
    })

    it('menampilkan ringkasan selesai setelah ekstraksi lalu menutupnya, dan tidak pada kunjungan ulang', async () => {
      vi.mocked(rpsApi.show)
        .mockResolvedValueOnce({ data: mockDocTahap0 })
        .mockResolvedValue({ data: docTahap1 })
      vi.mocked(rpsApi.startExtraction).mockResolvedValue({
        data: { ...mockDocTahap0, extraction_status: 'processing' },
      })

      renderRpsDetail()

      await screen.findByText('Pemrograman Web 2025.pdf')
      fireEvent.click(screen.getByRole('button', { name: 'Mulai ekstraksi' }))

      expect(await screen.findByText('Ekstraksi selesai. 2 kompetensi menunggu tinjauan.')).toBeInTheDocument()
      expect(screen.queryByText(/Proses ekstraksi kompetensi dimulai/)).not.toBeInTheDocument()

      fireEvent.click(screen.getByRole('button', { name: 'Tutup pemberitahuan' }))
      expect(screen.queryByText(/Ekstraksi selesai\. 2 kompetensi/)).not.toBeInTheDocument()
    })

    it('tidak menampilkan ringkasan selesai pada kunjungan ulang', async () => {
      vi.mocked(rpsApi.show).mockResolvedValue({ data: docTahap1 })

      renderRpsDetail()

      expect(await screen.findByText('2 kompetensi belum ditinjau')).toBeInTheDocument()
      expect(screen.queryByText(/Ekstraksi selesai\. /)).not.toBeInTheDocument()
    })
  })

  describe('langkah tinjau kompetensi', () => {
    beforeEach(() => {
      vi.mocked(rpsApi.show).mockResolvedValue({ data: docTahap1 })
    })

    it('merender tabel dan kartu kompetensi dengan sumber lengkap', async () => {
      renderRpsDetail()

      expect(await screen.findByText('Total kompetensi')).toBeInTheDocument()
      expect(await screen.findAllByText('Algoritma dasar')).toHaveLength(2)
      expect(screen.getAllByText('Struktur data')).toHaveLength(2)
      expect(screen.getAllByText('Rencana mingguan, minggu 2, hal. 4').length).toBeGreaterThan(0)
    })

    it('memberi nama aksesibel pada tombol aksi mobile dan desktop', async () => {
      renderRpsDetail()

      await screen.findAllByText('Struktur data')
      expect(screen.getAllByRole('button', { name: 'Setujui Struktur data' })).toHaveLength(2)
      expect(screen.getAllByRole('button', { name: 'Edit Struktur data' })).toHaveLength(2)
      expect(screen.getAllByRole('button', { name: 'Tolak Struktur data' })).toHaveLength(2)
    })

    it('teks bilah bawah sejalan dengan tombol: jumlah belum ditinjau dan aksi setujui semua', async () => {
      renderRpsDetail()

      expect(await screen.findByText('2 kompetensi belum ditinjau')).toBeInTheDocument()
      expect(screen.getByRole('button', { name: 'Setujui semua (2) dan lanjut' })).toBeEnabled()
      expect(screen.getByRole('button', { name: 'Setujui semua yang terverifikasi (1)' })).toBeEnabled()
      expect(screen.queryByText('Semua kompetensi sudah ditinjau.')).not.toBeInTheDocument()
    })

    it('menaruh aksi sekunder di kartu ringkasan, bukan di bilah lengket', async () => {
      renderRpsDetail()

      const secondary = await screen.findByRole('button', { name: 'Setujui semua yang terverifikasi (1)' })
      const primary = screen.getByRole('button', { name: 'Setujui semua (2) dan lanjut' })
      expect(secondary.closest('.sticky')).toBeNull()
      expect(primary.closest('.sticky')).not.toBeNull()
    })

    it('mengisi --sticky-bottom setinggi bilah lengket dan membersihkannya saat dilepas', async () => {
      const rect = vi
        .spyOn(HTMLElement.prototype, 'getBoundingClientRect')
        .mockReturnValue({ height: 97 } as DOMRect)
      const { unmount } = renderRpsDetail()

      await screen.findByText('2 kompetensi belum ditinjau')
      expect(document.documentElement.style.getPropertyValue('--sticky-bottom')).toBe('97px')

      unmount()
      expect(document.documentElement.style.getPropertyValue('--sticky-bottom')).toBe('')
      rect.mockRestore()
    })

    it('membuka dialog konfirmasi dengan jumlah, Batal dan Escape menutup tanpa menyetujui', async () => {
      renderRpsDetail()

      const opener = await screen.findByRole('button', { name: 'Setujui semua (2) dan lanjut' })
      fireEvent.click(opener)

      const dialog = await screen.findByRole('dialog', { name: 'Setujui 2 kompetensi dan lanjut?' })
      expect(dialog).toHaveTextContent('2 kompetensi yang belum ditinjau akan ditandai disetujui')
      expect(dialog).toHaveTextContent('1 di antaranya belum terverifikasi')
      expect(screen.getByRole('button', { name: 'Batal', hidden: false })).toHaveFocus()

      fireEvent.click(screen.getByRole('button', { name: 'Batal' }))
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()

      fireEvent.click(screen.getByRole('button', { name: 'Setujui semua (2) dan lanjut' }))
      expect(await screen.findByRole('dialog')).toBeInTheDocument()
      fireEvent.keyDown(document, { key: 'Escape' })
      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
      expect(rpsApi.confirmReview).not.toHaveBeenCalled()
    })

    it('mengunci fokus di dalam dialog konfirmasi', async () => {
      renderRpsDetail()

      fireEvent.click(await screen.findByRole('button', { name: 'Setujui semua (2) dan lanjut' }))
      await screen.findByRole('dialog')
      const cancel = screen.getByRole('button', { name: 'Batal' })
      const approve = screen.getByRole('button', { name: 'Setujui' })

      approve.focus()
      fireEvent.keyDown(document, { key: 'Tab' })
      expect(cancel).toHaveFocus()
      fireEvent.keyDown(document, { key: 'Tab', shiftKey: true })
      expect(approve).toHaveFocus()
    })

    it('menyetujui semua yang tertunda lalu melanjutkan ke pemetaan industri', async () => {
      vi.mocked(rpsApi.confirmReview).mockResolvedValue({
        data: { ...docTahap1, review_status: 'confirmed' },
      })
      renderRpsDetail()

      fireEvent.click(await screen.findByRole('button', { name: 'Setujui semua (2) dan lanjut' }))
      fireEvent.click(await screen.findByRole('button', { name: 'Setujui' }))

      await waitFor(() => expect(rpsApi.confirmReview).toHaveBeenCalledWith(12, true))
      expect(await screen.findByText('Halaman Pemetaan Baru')).toBeInTheDocument()
    })

    it('menyetujui hanya yang terverifikasi lewat dialog tanpa meninggalkan halaman', async () => {
      vi.mocked(rpsApi.updateCompetency).mockResolvedValue({ data: mockCompetencies[2]! })
      renderRpsDetail()

      fireEvent.click(await screen.findByRole('button', { name: 'Setujui semua yang terverifikasi (1)' }))
      const dialog = await screen.findByRole('dialog', { name: 'Setujui 1 kompetensi terverifikasi?' })
      expect(dialog).toHaveTextContent('1 kompetensi lain tetap menunggu tinjauan')
      fireEvent.click(screen.getByRole('button', { name: 'Setujui' }))

      await waitFor(() =>
        expect(rpsApi.updateCompetency).toHaveBeenCalledWith(12, 103, { review_status: 'confirmed' })
      )
      expect(rpsApi.updateCompetency).toHaveBeenCalledTimes(1)
      expect(rpsApi.confirmReview).not.toHaveBeenCalled()
      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    })

    it('saat semua sudah ditinjau tombol lanjut mengonfirmasi tanpa menyetujui massal', async () => {
      vi.mocked(rpsApi.competencies).mockResolvedValue({
        data: mockCompetencies.map((c) => ({ ...c, review_status: 'confirmed' as const })),
      })
      vi.mocked(rpsApi.confirmReview).mockResolvedValue({
        data: { ...docTahap1, review_status: 'confirmed' },
      })
      renderRpsDetail()

      expect(await screen.findByText('Semua kompetensi sudah ditinjau.')).toBeInTheDocument()
      fireEvent.click(screen.getByRole('button', { name: 'Lanjut ke pemetaan industri' }))

      await waitFor(() => expect(rpsApi.confirmReview).toHaveBeenCalledWith(12, false))
      expect(await screen.findByText('Halaman Pemetaan Baru')).toBeInTheDocument()
    })

    it('setelah dikonfirmasi tampil satu pernyataan dan tautan lanjut selebar bilah tanpa panah', async () => {
      vi.mocked(rpsApi.show).mockResolvedValue({
        data: { ...docTahap1, review_status: 'confirmed' },
      })
      vi.mocked(rpsApi.competencies).mockResolvedValue({
        data: mockCompetencies.map((c) => ({ ...c, review_status: 'confirmed' as const })),
      })
      renderRpsDetail()

      const link = await screen.findByRole('link', { name: 'Lanjut ke pemetaan industri' })
      expect(link).toHaveAttribute('href', '/analyses/new?rps=12')
      expect(link.className).toContain('w-full')
      expect(link.className).toContain('justify-center')
      expect(link.className).toContain('min-h-11')
      expect(link).not.toHaveTextContent('→')
      expect(screen.getAllByText('Semua kompetensi sudah ditinjau.')).toHaveLength(1)
      expect(screen.queryByText(/hasil review/)).not.toBeInTheDocument()
    })

    it('Edit pada kartu mobile membuka editor yang terlihat dan menyimpannya', async () => {
      vi.mocked(rpsApi.updateCompetency).mockResolvedValue({ data: mockCompetencies[1]! })
      renderRpsDetail()

      await screen.findAllByText('Struktur data')
      const editButtons = screen.getAllByRole('button', { name: 'Edit Struktur data' })
      // Tombol terakhir milik kartu mobile.
      fireEvent.click(editButtons[editButtons.length - 1]!)

      const skillInputs = screen.getAllByLabelText('Nama skill')
      const mobileSkill = skillInputs.find((el) => el.id.startsWith('mobile-'))
      expect(mobileSkill).toBeDefined()
      fireEvent.change(mobileSkill!, { target: { value: 'Struktur data lanjut' } })
      const descriptions = screen.getAllByLabelText('Deskripsi kompetensi')
      const mobileDesc = descriptions.find((el) => el.id.startsWith('mobile-'))
      expect(mobileDesc?.tagName).toBe('TEXTAREA')
      fireEvent.change(mobileDesc!, { target: { value: 'Memahami struktur data dasar' } })
      const saveButtons = screen.getAllByRole('button', { name: 'Simpan' })
      fireEvent.click(saveButtons[saveButtons.length - 1]!)

      await waitFor(() =>
        expect(rpsApi.updateCompetency).toHaveBeenCalledWith(12, 102, {
          skill: 'Struktur data lanjut',
          competency: 'Memahami struktur data dasar',
        })
      )
    })

    it('editor tetap bisa dibuka setelah tinjauan dikonfirmasi', async () => {
      vi.mocked(rpsApi.show).mockResolvedValue({
        data: { ...docTahap1, review_status: 'confirmed' },
      })
      renderRpsDetail()

      await screen.findAllByText('Struktur data')
      const editButtons = screen.getAllByRole('button', { name: 'Edit Struktur data' })
      fireEvent.click(editButtons[editButtons.length - 1]!)

      expect(screen.getAllByLabelText('Nama skill').length).toBeGreaterThan(0)
      expect(screen.getAllByRole('button', { name: 'Batal' }).length).toBeGreaterThan(0)
    })

    it('galat form tambah kompetensi berbahasa Indonesia per kolom dengan aria-invalid', async () => {
      renderRpsDetail()

      fireEvent.click(await screen.findByRole('button', { name: 'Tambah kompetensi' }))
      fireEvent.click(screen.getByRole('button', { name: 'Simpan kompetensi' }))

      const skill = screen.getByLabelText('Nama skill')
      const description = screen.getByLabelText('Deskripsi kompetensi')
      expect(await screen.findByText('Nama skill wajib diisi.')).toBeInTheDocument()
      expect(screen.getByText('Deskripsi kompetensi wajib diisi.')).toBeInTheDocument()
      expect(skill).toHaveAttribute('aria-invalid', 'true')
      expect(skill).toHaveAttribute('aria-describedby', 'newSkill-error')
      expect(description).toHaveAttribute('aria-invalid', 'true')
      expect(description.tagName).toBe('TEXTAREA')
      expect(skill).toHaveFocus()
      expect(rpsApi.createCompetency).not.toHaveBeenCalled()
    })

    it('mengirim kompetensi baru dan menutup form', async () => {
      vi.mocked(rpsApi.createCompetency).mockResolvedValue({ data: mockCompetencies[2]! })
      renderRpsDetail()

      fireEvent.click(await screen.findByRole('button', { name: 'Tambah kompetensi' }))
      fireEvent.change(screen.getByLabelText('Nama skill'), { target: { value: 'Git' } })
      fireEvent.change(screen.getByLabelText('Deskripsi kompetensi'), {
        target: { value: 'Mampu memakai git' },
      })
      fireEvent.click(screen.getByRole('button', { name: 'Simpan kompetensi' }))

      await waitFor(() =>
        expect(rpsApi.createCompetency).toHaveBeenCalledWith(12, {
          skill: 'Git',
          competency: 'Mampu memakai git',
        })
      )
      await waitFor(() =>
        expect(screen.queryByRole('button', { name: 'Simpan kompetensi' })).not.toBeInTheDocument()
      )
    })
  })
})
