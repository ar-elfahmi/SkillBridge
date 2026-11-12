import { api } from '@/lib/api/client'
import type {
  Career,
  JobPosting,
  RpsDocument,
  User,
} from '@/types/api'

/**
 * Fungsi endpoint, satu per route di docs/API.md.
 * Semua request memakai cookie Sanctum (credentials: 'include').
 */

export const authApi = {
  me: () => api.get<{ data: User }>('/auth/me'),
  login: (email: string, password: string) =>
    api.post<{ data: User }>('/auth/login', { email, password }),
  register: (name: string, email: string, password: string, password_confirmation: string) =>
    api.post<{ data: User }>('/auth/register', {
      name,
      email,
      password,
      password_confirmation,
    }),
  logout: () => api.post<void>('/auth/logout'),
}

export const rpsApi = {
  list: () => api.get<{ data: RpsDocument[] }>('/rps'),
  show: (id: number) => api.get<{ data: RpsDocument }>(`/rps/${id}`),
  upload: (file: File) => {
    const form = new FormData()
    form.append('file', file)
    return api.post<{ data: RpsDocument }>('/rps', form)
  },
  remove: (id: number) => api.delete<void>(`/rps/${id}`),
}

export const industryApi = {
  careers: () => api.get<{ data: Career[] }>('/careers'),
  jobs: (careerId?: number) =>
    api.get<{ data: JobPosting[] }>(
      careerId === undefined ? '/jobs' : `/jobs?career_id=${careerId}`,
    ),
  job: (id: number) => api.get<{ data: JobPosting }>(`/jobs/${id}`),
}
