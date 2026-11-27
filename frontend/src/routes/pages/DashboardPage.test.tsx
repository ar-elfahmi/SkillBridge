import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { DashboardPage } from '@/routes/pages/DashboardPage'
import { ApiRequestError } from '@/lib/api/client'
import { aiApi, analysisApi, authApi, industryApi, meApi, rpsApi } from '@/lib/api/endpoints'
import type { ApiError, RpsDocument, User } from '@/types/api'

vi.mock('@/lib/api/endpoints', () => ({
  aiApi: { quota: vi.fn() },
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
    vi.mocked(aiApi.quota).mockResolvedValue({
      data: { available: true, daily_remaining: 100, resets_at: '2026-11-27T08:00:00+00:00' },
    })
    vi.mocked(authApi.me).mockResolvedValue({ data: mockUser })
    vi.mocked(rpsApi.list).mockResolvedValue({ data: mockRps })
    vi.mocked(analysisApi.list).mockResolvedValue({ data: [] })
    vi.mocked(industryApi.careers).mockResolvedValue({ data: [] })
    vi.mocked(industryApi.jobs).mockResolvedValue({ data: [] })
    vi.mocked(meApi.skills).mockResolvedValue({ data: [] })
  })

  it('merender elemen utama dashboard (hero unggah, stats, daftar RPS)', async () => {
    renderDashboard()

    expect(screen.getAllByText('Unggah RPS')[0]).toBeInTheDocument()
    expect(screen.getByText('Pilih atau tarik PDF RPS')).toBeInTheDocument()
    expect(screen.getByText('Skill belum tercakup')).toBeInTheDocument()

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

  it('menampilkan galat inline (bukan alert) untuk berkas non-PDF', async () => {
    const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {})
    renderDashboard()

    const file = new File(['x'], 'catatan.txt', { type: 'text/plain' })
    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    fireEvent.change(input, { target: { files: [file] } })

    expect(await screen.findByRole('alert')).toHaveTextContent('Berkas harus berupa PDF yang valid')
    expect(alertSpy).not.toHaveBeenCalled()
    expect(rpsApi.upload).not.toHaveBeenCalled()
  })

  it('membedakan hasil pencarian kosong dari daftar kosong', async () => {
    renderDashboard()
    await screen.findByText('Pemrograman Web 2025.pdf')

    fireEvent.change(screen.getAllByLabelText('Cari RPS')[0]!, { target: { value: 'zzzzqq' } })

    expect(await screen.findByText('Tidak ada RPS yang cocok dengan "zzzzqq"')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Hapus pencarian' }))
    expect(await screen.findByText('Pemrograman Web 2025.pdf')).toBeInTheDocument()
  })

  it('menampilkan info duplikat netral dengan nama dokumen, tautan, dan tombol tutup', async () => {
    vi.mocked(rpsApi.upload).mockRejectedValue(
      new ApiRequestError(409, { message: 'Duplikat', existing_id: 10 } as ApiError)
    )
    renderDashboard()
    await screen.findByText('Pemrograman Web 2025.pdf')

    const file = new File(['dummy'], 'Salinan.pdf', { type: 'application/pdf' })
    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    fireEvent.change(input, { target: { files: [file] } })

    const notice = await screen.findByText('Dokumen ini sudah ada di daftar Anda: Pemrograman Web.')
    const region = notice.closest('[role="status"]')
    expect(region).not.toBeNull()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Buka dokumen itu' })).toHaveAttribute('href', '/rps/10')

    fireEvent.click(screen.getByRole('button', { name: 'Tutup pemberitahuan' }))
    expect(screen.queryByText(/sudah ada di daftar Anda/)).not.toBeInTheDocument()
  })

  it('memakai Title Case pada nama dokumen di pesan duplikat, sama seperti kartu', async () => {
    vi.mocked(rpsApi.list).mockResolvedValue({
      data: [
        { ...mockRps[0]!, course: { ...mockRps[0]!.course!, name: 'MANAJEMEN PROYEK PERANGKAT LUNAK' } },
      ],
    })
    vi.mocked(rpsApi.upload).mockRejectedValue(
      new ApiRequestError(409, { message: 'Duplikat', existing_id: 10 } as ApiError)
    )
    renderDashboard()
    await screen.findByText('Pemrograman Web 2025.pdf')

    const file = new File(['dummy'], 'Salinan.pdf', { type: 'application/pdf' })
    const input = document.querySelector('input[type="file"]') as HTMLInputElement
    fireEvent.change(input, { target: { files: [file] } })

    expect(
      await screen.findByText('Dokumen ini sudah ada di daftar Anda: Manajemen Proyek Perangkat Lunak.')
    ).toBeInTheDocument()
  })

  it('hanya menandai Baru pada dokumen yang diunggah dalam 2 jam terakhir', async () => {
    const fiveHoursAgo = new Date(Date.now() - 5 * 60 * 60 * 1000).toISOString()
    vi.mocked(rpsApi.list).mockResolvedValue({ data: [{ ...mockRps[0]!, created_at: fiveHoursAgo }] })
    renderDashboard()

    const card = await screen.findByRole('link', { name: /Pemrograman Web 2025\.pdf/ })
    expect(card).not.toHaveTextContent('Baru')
  })

  it('tidak menampilkan tombol hapus bila fungsi API hapus belum ada', async () => {
    renderDashboard()
    await screen.findByText('Pemrograman Web 2025.pdf')
    expect(screen.queryByRole('button', { name: /^Hapus / })).not.toBeInTheDocument()
  })

  it('menampilkan tanggal unggah, profesi analisis, penanda Baru, dan keterangan statistik di ponsel', async () => {
    vi.mocked(rpsApi.list).mockResolvedValue({
      data: [{ ...mockRps[0]!, created_at: new Date().toISOString() }],
    })
    vi.mocked(analysisApi.list).mockResolvedValue({
      data: [
        {
          analysis_id: 5,
          status: 'completed',
          career: 'Frontend Developer',
          course: 'Pemrograman Web',
          summary: { total_skills: 4, covered: 1, partial: 0, missing: 3, coverage_percentage: 25 },
          model_versions: null,
          error_message: null,
          created_at: '',
        },
      ],
    })
    renderDashboard()

    const card = await screen.findByRole('link', { name: /Pemrograman Web 2025\.pdf/ })
    expect(card).toHaveTextContent(/Diunggah \d{1,2} \w+ \d{4}/)
    expect(card).toHaveTextContent('Frontend Developer')
    expect(card).toHaveTextContent('Baru')
    const note = screen.getByText('dari 1 analisis')
    expect(note.className).not.toContain('hidden')
  })

  describe('hapus dokumen', () => {
    beforeEach(() => {
      rpsApi.remove = vi.fn()
    })
    afterEach(() => {
      delete (rpsApi as { remove?: unknown }).remove
    })

    it('meminta konfirmasi dengan dampaknya, Batal dan Escape menutup tanpa menghapus', async () => {
      renderDashboard()

      fireEvent.click(await screen.findByRole('button', { name: 'Hapus Pemrograman Web 2025.pdf' }))
      const dialog = await screen.findByRole('dialog', { name: 'Hapus dokumen ini?' })
      expect(dialog).toHaveTextContent('Analisis yang memakai dokumen ini ikut terhapus')
      expect(dialog).toHaveTextContent('tidak dapat diurungkan')
      expect(screen.getByRole('button', { name: 'Batal' })).toHaveFocus()

      fireEvent.click(screen.getByRole('button', { name: 'Batal' }))
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()

      fireEvent.click(screen.getByRole('button', { name: 'Hapus Pemrograman Web 2025.pdf' }))
      await screen.findByRole('dialog')
      fireEvent.keyDown(document, { key: 'Escape' })
      await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
      expect(rpsApi.remove).not.toHaveBeenCalled()
    })

    it('menghapus dokumen dan mengumumkan hasilnya', async () => {
      vi.mocked(rpsApi.remove).mockResolvedValue(undefined)
      renderDashboard()

      fireEvent.click(await screen.findByRole('button', { name: 'Hapus Pemrograman Web 2025.pdf' }))
      fireEvent.click(await screen.findByRole('button', { name: 'Hapus dokumen' }))

      await waitFor(() => expect(rpsApi.remove).toHaveBeenCalledWith(10))
      expect(await screen.findByText('Dokumen dihapus.')).toBeInTheDocument()
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    })

    it('banner "Dokumen dihapus." hilang sendiri setelah 5 detik', async () => {
      vi.useFakeTimers({ shouldAdvanceTime: true })
      try {
        vi.mocked(rpsApi.remove).mockResolvedValue(undefined)
        renderDashboard()

        fireEvent.click(await screen.findByRole('button', { name: 'Hapus Pemrograman Web 2025.pdf' }))
        fireEvent.click(await screen.findByRole('button', { name: 'Hapus dokumen' }))

        const banner = (await screen.findByText('Dokumen dihapus.')).closest('[role="status"]')
        expect(banner).not.toBeNull()

        act(() => {
          vi.advanceTimersByTime(4000)
        })
        expect(screen.getByText('Dokumen dihapus.')).toBeInTheDocument()
        act(() => {
          vi.advanceTimersByTime(1500)
        })
        expect(screen.queryByText('Dokumen dihapus.')).not.toBeInTheDocument()
      } finally {
        vi.useRealTimers()
      }
    })

    it('banner "Dokumen dihapus." dapat ditutup dengan tombol tutup', async () => {
      vi.mocked(rpsApi.remove).mockResolvedValue(undefined)
      renderDashboard()

      fireEvent.click(await screen.findByRole('button', { name: 'Hapus Pemrograman Web 2025.pdf' }))
      fireEvent.click(await screen.findByRole('button', { name: 'Hapus dokumen' }))
      await screen.findByText('Dokumen dihapus.')

      fireEvent.click(screen.getByRole('button', { name: 'Tutup pemberitahuan' }))
      expect(screen.queryByText('Dokumen dihapus.')).not.toBeInTheDocument()
    })

    it('mempertahankan dialog dan menampilkan galat saat penghapusan gagal', async () => {
      vi.mocked(rpsApi.remove).mockRejectedValue(new ApiRequestError(500, { message: 'Gagal' }))
      renderDashboard()

      fireEvent.click(await screen.findByRole('button', { name: 'Hapus Pemrograman Web 2025.pdf' }))
      fireEvent.click(await screen.findByRole('button', { name: 'Hapus dokumen' }))

      expect(await screen.findByText('Dokumen gagal dihapus. Coba lagi.')).toBeInTheDocument()
      expect(screen.getByRole('dialog')).toBeInTheDocument()
    })
  })
})