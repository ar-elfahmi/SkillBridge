import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { LoginPage } from '@/routes/pages/LoginPage'
import { ApiRequestError } from '@/lib/api/client'
import { authApi } from '@/lib/api/endpoints'

vi.mock('@/lib/api/endpoints', () => ({
  authApi: { login: vi.fn(), me: vi.fn() },
}))

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/login']}>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/dashboard" element={<p>Beranda</p>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function fillAndSubmit(email: string, password: string) {
  fireEvent.change(screen.getByLabelText('Email'), { target: { value: email } })
  fireEvent.change(screen.getByLabelText('Kata sandi'), { target: { value: password } })
  fireEvent.click(screen.getByRole('button', { name: 'Masuk' }))
}

describe('LoginPage', () => {
  beforeEach(() => {
    vi.mocked(authApi.login).mockReset()
  })

  it('mengirim email dan kata sandi lalu pindah ke dashboard', async () => {
    vi.mocked(authApi.login).mockResolvedValue({
      data: { id: 1, name: 'Budi', email: 'budi@example.com', role: 'student', created_at: '' },
    })
    renderPage()
    fillAndSubmit('budi@example.com', 'rahasia123')

    await waitFor(() => {
      expect(authApi.login).toHaveBeenCalledWith('budi@example.com', 'rahasia123')
    })
    expect(await screen.findByText('Beranda')).toBeInTheDocument()
  })

  it('menampilkan pesan 422 tanpa pindah halaman', async () => {
    vi.mocked(authApi.login).mockRejectedValue(
      new ApiRequestError(422, {
        message: 'Email atau kata sandi salah.',
        errors: { email: ['Email atau kata sandi salah.'] },
      }),
    )
    renderPage()
    fillAndSubmit('budi@example.com', 'salah')

    expect(await screen.findByRole('alert')).toHaveTextContent('Email atau kata sandi salah.')
    expect(screen.queryByText('Beranda')).not.toBeInTheDocument()
  })

  it('mengubah tipe input kata sandi lewat toggle', () => {
    renderPage()
    const password = screen.getByLabelText('Kata sandi')

    expect(password).toHaveAttribute('type', 'password')
    fireEvent.click(screen.getByRole('button', { name: 'Tampilkan kata sandi' }))
    expect(password).toHaveAttribute('type', 'text')
  })
})
