import { api } from '@/lib/api/client'
import type {
  Analysis,
  Career,
  CreateAnalysisPayload,
  CreateJobPayload,
  JobPosting,
  RecommendationsResponse,
  RpsDocument,
  SkillMatch,
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
  updateMetadata: (id: number, data: Partial<import('@/types/api').RpsMetadataDraft>) =>
    api.patch<{ data: RpsDocument }>(`/rps/${id}`, data),
  extract: (id: number, code?: string) =>
    api.post<{ message: string; data?: RpsDocument }>(`/rps/${id}/extract`, code ? { code } : {}),
  competencies: (id: number) =>
    api.get<{ data: import('@/types/api').CourseSkill[] }>(`/rps/${id}/competencies`),
  addCompetency: (
    id: number,
    payload: { skill: string; competency: string; category?: string; review_status?: string },
  ) =>
    api.post<{ data: import('@/types/api').CourseSkill }>(`/rps/${id}/competencies`, payload),
  updateCompetency: (
    id: number,
    competencyId: number,
    payload: { review_status?: 'confirmed' | 'rejected'; skill?: string; competency?: string },
  ) =>
    api.patch<{ data: import('@/types/api').CourseSkill }>(
      `/rps/${id}/competencies/${competencyId}`,
      payload,
    ),
  deleteCompetency: (id: number, competencyId: number) =>
    api.delete<void>(`/rps/${id}/competencies/${competencyId}`),
  confirmReview: (id: number, confirmAllPending = true) =>
    api.post<{ data: RpsDocument }>(`/rps/${id}/confirm-review`, {
      confirm_all_pending: confirmAllPending,
    }),
}

export const industryApi = {
  careers: () => api.get<{ data: Career[] }>('/careers'),
  jobs: (careerId?: number, type?: 'all' | 'real' | 'synthetic') => {
    const params = new URLSearchParams()
    if (careerId !== undefined) params.append('career_id', String(careerId))
    if (type && type !== 'all') params.append('type', type)
    params.append('per_page', '50')
    const qs = params.toString()
    return api.get<{ data: JobPosting[] }>(qs ? `/jobs?${qs}` : '/jobs?per_page=50')
  },
  job: (id: number) => api.get<{ data: JobPosting }>(`/jobs/${id}`),
  createJob: (payload: CreateJobPayload) =>
    api.post<{ data: JobPosting }>('/jobs', payload),
}

export const analysisApi = {
  list: () => api.get<{ data: Analysis[] }>('/analyses'),
  show: (id: number) => api.get<{ data: Analysis }>(`/analyses/${id}`),
  create: (payload: CreateAnalysisPayload) =>
    api.post<{ data: Analysis }>('/analyses', payload),
  matches: (id: number) => api.get<{ data: SkillMatch[] }>(`/analyses/${id}/matches`),
  recommendations: (id: number) =>
    api.get<RecommendationsResponse>(`/analyses/${id}/recommendations`),
  remove: (id: number) => api.delete<void>(`/analyses/${id}`),
}

