import { api } from '@/lib/api/client'
import type {
  Analysis,
  Career,
  CourseSkill,
  CreateAnalysisPayload,
  CreateCompetencyPayload,
  JobPosting,
  RpsDocument,
  UpdateCompetencyPayload,
  UpdateRpsMetadataPayload,
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
  updateMetadata: (id: number, payload: UpdateRpsMetadataPayload) =>
    api.patch<{ data: RpsDocument }>(`/rps/${id}`, payload),
  upload: (file: File) => {
    const form = new FormData()
    form.append('file', file)
    return api.post<{ data: RpsDocument }>('/rps', form)
  },
  remove: (id: number) => api.delete<void>(`/rps/${id}`),

  // Ekstraksi & Tinjauan Kompetensi (T3.5, T3.6)
  startExtraction: (id: number) =>
    api.post<{ data: RpsDocument }>(`/rps/${id}/extract`),
  competencies: (id: number) =>
    api.get<{ data: CourseSkill[] }>(`/rps/${id}/competencies`),
  createCompetency: (id: number, payload: CreateCompetencyPayload) =>
    api.post<{ data: CourseSkill }>(`/rps/${id}/competencies`, payload),
  updateCompetency: (
    docId: number,
    competencyId: number,
    payload: UpdateCompetencyPayload
  ) =>
    api.patch<{ data: CourseSkill }>(
      `/rps/${docId}/competencies/${competencyId}`,
      payload
    ),
  deleteCompetency: (docId: number, competencyId: number) =>
    api.delete<void>(`/rps/${docId}/competencies/${competencyId}`),
  confirmReview: (id: number, confirmAllPending = false) =>
    api.post<{ data: RpsDocument }>(`/rps/${id}/confirm-review`, {
      confirm_all_pending: confirmAllPending,
    }),
}

export const industryApi = {
  careers: () => api.get<{ data: Career[] }>('/careers'),
  jobs: (careerId?: number) =>
    api.get<{ data: JobPosting[] }>(
      careerId === undefined ? '/jobs' : `/jobs?career_id=${careerId}`,
    ),
  job: (id: number) => api.get<{ data: JobPosting }>(`/jobs/${id}`),
}

export const analysisApi = {
  list: () => api.get<{ data: Analysis[] }>('/analyses'),
  show: (id: number) => api.get<{ data: Analysis }>(`/analyses/${id}`),
  create: (payload: CreateAnalysisPayload) =>
    api.post<{ data: Analysis }>('/analyses', payload),
  remove: (id: number) => api.delete<void>(`/analyses/${id}`),
}

