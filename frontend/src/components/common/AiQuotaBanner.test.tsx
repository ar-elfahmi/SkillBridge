import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AiQuotaBanner } from '@/components/common/AiQuotaBanner'
import { aiApi } from '@/lib/api/endpoints'

vi.mock('@/lib/api/endpoints', () => ({
  aiApi: { quota: vi.fn() },
}))

function renderBanner() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <AiQuotaBanner />
    </QueryClientProvider>,
  )
}

describe('AiQuotaBanner', () => {
  beforeEach(() => vi.clearAllMocks())

  it('menampilkan pesan dan waktu reset saat kuota harian habis', async () => {
    vi.mocked(aiApi.quota).mockResolvedValue({
      data: { available: false, daily_remaining: 0, resets_at: '2026-11-27T08:00:00+00:00' },
    })
    renderBanner()

    const alert = await screen.findByRole('alert')
    expect(alert.textContent).toContain('Kuota AI harian habis')
    expect(alert.textContent).toContain('15.00 WIB')
  })

  it('tidak menampilkan apa pun saat kuota tersedia', async () => {
    vi.mocked(aiApi.quota).mockResolvedValue({
      data: { available: true, daily_remaining: 50, resets_at: '2026-11-27T08:00:00+00:00' },
    })
    renderBanner()

    await waitFor(() => expect(aiApi.quota).toHaveBeenCalled())
    expect(screen.queryByRole('alert')).toBeNull()
  })
})
