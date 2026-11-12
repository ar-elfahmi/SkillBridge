import type { ApiError } from '@/types/api'

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'

export class ApiRequestError extends Error {
  readonly status: number
  readonly body: ApiError

  constructor(status: number, body: ApiError) {
    super(body.message)
    this.name = 'ApiRequestError'
    this.status = status
    this.body = body
  }
}

function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(^|;\\s*)${name}=([^;]*)`))
  return match ? decodeURIComponent(match[2]!) : null
}

let csrfReady: Promise<void> | null = null

/**
 * Pastikan cookie XSRF-TOKEN ada sebelum request tulis.
 * Token di-regenerasi tiap session berubah, jadi selalu ambil ulang.
 */
export function ensureCsrfCookie(): Promise<void> {
  csrfReady ??= fetch(`${API_URL.replace(/\/api$/, '')}/sanctum/csrf-cookie`, {
    credentials: 'include',
  })
    .then(() => undefined)
    .catch((error: unknown) => {
      csrfReady = null
      throw error
    })
  return csrfReady
}

type Method = 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE'

async function request<T>(method: Method, path: string, body?: unknown): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }

  if (method !== 'GET') {
    await ensureCsrfCookie()
    const token = readCookie('XSRF-TOKEN')
    if (token) headers['X-XSRF-TOKEN'] = token
  }

  let payload: BodyInit | undefined
  if (body instanceof FormData) {
    payload = body
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    payload = JSON.stringify(body)
  }

  const response = await fetch(`${API_URL}${path}`, {
    method,
    headers,
    body: payload,
    credentials: 'include',
  })

  if (response.status === 204) return undefined as T

  const text = await response.text()
  const parsed: unknown = text ? JSON.parse(text) : null

  if (!response.ok) {
    throw new ApiRequestError(response.status, (parsed as ApiError) ?? { message: 'Permintaan gagal.' })
  }

  return parsed as T
}

export const api = {
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, body),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
  delete: <T>(path: string) => request<T>('DELETE', path),
}
