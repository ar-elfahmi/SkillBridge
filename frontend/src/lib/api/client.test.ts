import { beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * Modul di-reset per test karena menyimpan cache promise CSRF di level modul.
 */
async function loadClient() {
  vi.resetModules()
  return import('@/lib/api/client')
}

function jsonResponse(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), { status })
}

describe('api client', () => {
  beforeEach(() => {
    document.cookie = 'XSRF-TOKEN=token-abc; path=/'
    vi.stubGlobal('fetch', vi.fn(async () => jsonResponse({ data: [] })))
  })

  it('menyerilog cookie pada request GET', async () => {
    const { api } = await loadClient()

    await api.get('/careers')

    const [, init] = vi.mocked(fetch).mock.calls[0]!
    expect(init?.credentials).toBe('include')
  })

  it('mengirim header X-XSRF-TOKEN pada request tulis', async () => {
    const { api } = await loadClient()

    await api.post('/jobs', { title: 'Backend' })

    const writeCall = vi
      .mocked(fetch)
      .mock.calls.find(([url]) => String(url).endsWith('/jobs'))!
    const headers = writeCall[1]?.headers as Record<string, string>
    expect(headers['X-XSRF-TOKEN']).toBe('token-abc')
    expect(headers['Content-Type']).toBe('application/json')
  })

  it('membungkus error non-2xx jadi ApiRequestError', async () => {
    const { api, ApiRequestError } = await loadClient()
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => jsonResponse({ message: 'Silakan masuk terlebih dahulu.' }, 401)),
    )

    await expect(api.get('/auth/me')).rejects.toBeInstanceOf(ApiRequestError)
  })

  it('mengambil cookie CSRF sekali lalu memakai ulang', async () => {
    const { ensureCsrfCookie } = await loadClient()

    await ensureCsrfCookie()
    await ensureCsrfCookie()

    const csrfCalls = vi
      .mocked(fetch)
      .mock.calls.filter(([url]) => String(url).includes('/sanctum/csrf-cookie'))
    expect(csrfCalls).toHaveLength(1)
  })
})
