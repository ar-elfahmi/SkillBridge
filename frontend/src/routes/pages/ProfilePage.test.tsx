import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ProfilePage } from '@/routes/pages/ProfilePage'
import { authApi, meApi } from '@/lib/api/endpoints'
import type { User, UserSkill } from '@/types/api'

vi.mock('@/lib/api/endpoints', () => ({
  authApi: { me: vi.fn(), logout: vi.fn() },
  meApi: { skills: vi.fn(), addSkill: vi.fn(), removeSkill: vi.fn() },
}))

const mockUser: User = {
  id: 1,
  name: 'Anisa Rahma',
  email: 'anisa@skillbridge.id',
  role: 'student',
  created_at: '',
}

const mockSkills: UserSkill[] = [
  {
    id: 1,
    skill_id: 10,
    skill: 'React',
    canonical_skill: 'React',
    reported_as: 'ReactJS',
    level: 'advanced',
    created_at: '',
  },
  {
    id: 2,
    skill_id: 11,
    skill: 'Docker',
    canonical_skill: 'Docker',
    reported_as: null,
    level: 'beginner',
    created_at: '',
  },
]

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/profile']}>
        <Routes>
          <Route path="/profile" element={<ProfilePage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  )
}

describe('ProfilePage', () => {
  beforeEach(() => {
    vi.mocked(authApi.me).mockResolvedValue({ data: mockUser })
    vi.mocked(meApi.skills).mockResolvedValue({ data: mockSkills })
  })

  it('menampilkan identitas pengguna dan tabel skill pribadi', async () => {
    renderPage()

    expect((await screen.findAllByText('Anisa Rahma'))[0]).toBeInTheDocument()
    expect(screen.getAllByText('anisa@skillbridge.id')[0]).toBeInTheDocument()

    expect(await screen.findByText('React')).toBeInTheDocument()
    expect(screen.getByText('Docker')).toBeInTheDocument()
    expect(screen.getByText('ReactJS')).toBeInTheDocument()
    expect(screen.getAllByText('Mahir').length).toBeGreaterThan(0)
  })

  it('menambahkan skill pribadi lewat form', async () => {
    vi.mocked(meApi.addSkill).mockResolvedValue({
      data: {
        id: 3,
        skill_id: 12,
        skill: 'Figma',
        canonical_skill: 'Figma',
        reported_as: null,
        level: 'intermediate',
        created_at: '',
      },
    })
    renderPage()

    const input = await screen.findByLabelText('Nama skill')
    expect((screen.getByLabelText('Tingkat') as HTMLSelectElement).value).toBe('')
    expect(screen.getByRole('option', { name: 'Pilih tingkat' })).toBeInTheDocument()
    fireEvent.change(input, { target: { value: 'Figma' } })
    fireEvent.click(screen.getByRole('button', { name: 'Tambah skill' }))

    await waitFor(() => {
      expect(meApi.addSkill).toHaveBeenCalledWith({ skill: 'Figma' })
    })
  })

  it('mengirim tingkat yang dipilih pengguna', async () => {
    vi.mocked(meApi.addSkill).mockResolvedValue({
      data: {
        id: 4,
        skill_id: 13,
        skill: 'Figma',
        canonical_skill: 'Figma',
        reported_as: null,
        level: 'advanced',
        created_at: '',
      },
    })
    renderPage()

    fireEvent.change(await screen.findByLabelText('Nama skill'), { target: { value: 'Figma' } })
    fireEvent.change(screen.getByLabelText('Tingkat'), { target: { value: 'advanced' } })
    fireEvent.click(screen.getByRole('button', { name: 'Tambah skill' }))

    await waitFor(() => {
      expect(meApi.addSkill).toHaveBeenCalledWith({ skill: 'Figma', level: 'advanced' })
    })
  })

  it('menghapus skill pribadi', async () => {
    vi.mocked(meApi.removeSkill).mockResolvedValue(undefined)
    renderPage()

    const removeButtons = await screen.findAllByRole('button', { name: /Hapus/ })
    fireEvent.click(removeButtons[0]!)

    await waitFor(() => {
      expect(meApi.removeSkill).toHaveBeenCalledWith(1)
    })
  })
})