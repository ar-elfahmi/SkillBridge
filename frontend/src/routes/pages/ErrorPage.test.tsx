import { render, screen } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { ErrorPage } from '@/routes/pages/ErrorPage'

describe('ErrorPage', () => {
  it('menampilkan halaman 404 berbahasa Indonesia untuk rute tidak dikenal', async () => {
    const router = createMemoryRouter(
      [{ path: '*', element: <ErrorPage /> }],
      { initialEntries: ['/halaman-tidak-ada'] }
    )
    render(<RouterProvider router={router} />)

    expect(await screen.findByRole('heading', { name: 'Halaman tidak ditemukan' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Kembali ke beranda' })).toHaveAttribute('href', '/dashboard')
    expect(screen.queryByText(/developer/i)).not.toBeInTheDocument()
  })
})
