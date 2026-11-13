import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RegisterPage } from '@/routes/pages/RegisterPage'
import { ApiRequestError } from '@/lib/api/client'
import { authApi } from '@/lib/api/endpoints'

vi.mock('@/lib/api/endpoints', () => ({
  authApi: { register: vi.fn(), login: vi.fn(), me: vi.fn() },
}))

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/register']}>
        <Routes>
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/dashboard" element={<p>Beranda</p>} />
          <Route path="/login" element={<p>Halaman Masuk</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function fillForm(name: string, email: string, pass: string, passConfirm: string) {
  fireEvent.change(screen.getByLabelText('Nama lengkap'), { target: { value: name } })
  fireEvent.change(screen.getByLabelText('Email'), { target: { value: email } })
  fireEvent.change(screen.getByLabelText('Kata sandi'), { target: { value: pass } })
  fireEvent.change(screen.getByLabelText('Ulangi kata sandi'), { target: { value: passConfirm } })
}

describe('RegisterPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.register).mockReset()
  })

  it('mengirim form lengkap dan pindah ke dashboard saat sukses', async () => {
    vi.mocked(authApi.register).mockResolvedValue({
      data: { id: 2, name: 'Siti', email: 'siti@example.com', role: 'student', created_at: '' },
    })
    renderPage()
    fillForm('Siti Rahma', 'siti@example.com', 'rahasia123', 'rahasia123')
    fireEvent.click(screen.getByRole('button', { name: 'Daftar' }))

    await waitFor(() => {
      expect(authApi.register).toHaveBeenCalledWith(
        'Siti Rahma',
        'siti@example.com',
        'rahasia123',
        'rahasia123',
      )
    })
    expect(await screen.findByText('Beranda')).toBeInTheDocument()
  })

  it('mencegah kirim saat kata sandi dan konfirmasi tidak cocok', () => {
    renderPage()
    fillForm('Siti', 'siti@example.com', 'rahasia123', 'beda123')
    fireEvent.click(screen.getByRole('button', { name: 'Daftar' }))

    expect(authApi.register).not.toHaveBeenCalled()
    expect(screen.getByRole('alert')).toHaveTextContent('Konfirmasi kata sandi tidak cocok.')
  })

  it('menampilkan error server saat email sudah terdaftar', async () => {
    vi.mocked(authApi.register).mockRejectedValue(
      new ApiRequestError(422, {
        message: 'Email sudah terdaftar.',
        errors: { email: ['Email sudah terdaftar.'] },
      }),
    )
    renderPage()
    fillForm('Siti', 'siti@example.com', 'rahasia123', 'rahasia123')
    fireEvent.click(screen.getByRole('button', { name: 'Daftar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Email sudah terdaftar.')
  })

  it('mengubah tipe input kata sandi lewat toggle', () => {
    renderPage()
    const password = screen.getByLabelText('Kata sandi')
    expect(password).toHaveAttribute('type', 'password')
    fireEvent.click(screen.getByRole('button', { name: 'Tampilkan kata sandi' }))
    expect(password).toHaveAttribute('type', 'text')
  })
})
