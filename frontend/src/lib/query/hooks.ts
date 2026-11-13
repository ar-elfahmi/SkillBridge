import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { analysisApi, authApi, industryApi, rpsApi } from '@/lib/api/endpoints'
import { queryKeys } from '@/lib/query/keys'
import type { User } from '@/types/api'

/** Sesi pengguna aktif. 401 = belum login, bukan error fatal. */
export function useMe() {
  return useQuery({
    queryKey: queryKeys.me,
    queryFn: authApi.me,
    retry: false,
    select: (response) => response.data,
  })
}

export function useRegister() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      name,
      email,
      password,
      passwordConfirmation,
    }: {
      name: string
      email: string
      password: string
      passwordConfirmation: string
    }) => authApi.register(name, email, password, passwordConfirmation),
    onSuccess: (response: { data: User }) => {
      queryClient.setQueryData(queryKeys.me, response)
    },
  })
}

export function useLogin() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ email, password }: { email: string; password: string }) =>
      authApi.login(email, password),
    onSuccess: (response: { data: User }) => {
      queryClient.setQueryData(queryKeys.me, response)
    },
  })
}

export function useLogout() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: authApi.logout,
    onSuccess: () => {
      queryClient.setQueryData(queryKeys.me, null)
    },
  })
}

export function useRpsList() {
  return useQuery({
    queryKey: queryKeys.rps,
    queryFn: rpsApi.list,
    select: (res) => res.data,
  })
}

export function useUploadRps() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (file: File) => rpsApi.upload(file),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.rps })
    },
  })
}

export function useRpsDetail(id: number) {
  return useQuery({
    queryKey: queryKeys.rpsDetail(id),
    queryFn: () => rpsApi.show(id),
    select: (res) => res.data,
    refetchInterval: (query) => {
      const doc = query.state.data?.data
      if (!doc) return false
      if (
        doc.parse_status === 'parsing' ||
        doc.parse_status === 'pending' ||
        doc.extraction_status === 'processing'
      ) {
        return 2000
      }
      return false
    },
  })
}

export function useRpsCompetencies(id: number) {
  return useQuery({
    queryKey: queryKeys.rpsCompetencies(id),
    queryFn: () => rpsApi.competencies(id),
    select: (res) => res.data,
  })
}

export function useUpdateRpsMetadata() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: Partial<import('@/types/api').RpsMetadataDraft> }) =>
      rpsApi.updateMetadata(id, data),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.rpsDetail(id) })
    },
  })
}

export function useExtractRps() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, code }: { id: number; code?: string }) => rpsApi.extract(id, code),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.rpsDetail(id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.rpsCompetencies(id) })
    },
  })
}

export function useAddCompetency() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      rpsId,
      payload,
    }: {
      rpsId: number
      payload: { skill: string; competency: string; category?: string; review_status?: string }
    }) => rpsApi.addCompetency(rpsId, payload),
    onSuccess: (_, { rpsId }) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.rpsCompetencies(rpsId) })
    },
  })
}

export function useUpdateCompetency() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      rpsId,
      competencyId,
      payload,
    }: {
      rpsId: number
      competencyId: number
      payload: { review_status?: 'confirmed' | 'rejected'; skill?: string; competency?: string }
    }) => rpsApi.updateCompetency(rpsId, competencyId, payload),
    onSuccess: (_, { rpsId }) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.rpsCompetencies(rpsId) })
    },
  })
}

export function useDeleteCompetency() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ rpsId, competencyId }: { rpsId: number; competencyId: number }) =>
      rpsApi.deleteCompetency(rpsId, competencyId),
    onSuccess: (_, { rpsId }) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.rpsCompetencies(rpsId) })
    },
  })
}

export function useConfirmRpsReview() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ rpsId, confirmAllPending = true }: { rpsId: number; confirmAllPending?: boolean }) =>
      rpsApi.confirmReview(rpsId, confirmAllPending),
    onSuccess: (_, { rpsId }) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.rpsDetail(rpsId) })
      queryClient.invalidateQueries({ queryKey: queryKeys.rpsCompetencies(rpsId) })
    },
  })
}

export function useAnalysesList() {
  return useQuery({
    queryKey: queryKeys.analyses,
    queryFn: analysisApi.list,
    select: (res) => res.data,
  })
}

export function useCareersList() {
  return useQuery({
    queryKey: queryKeys.careers,
    queryFn: industryApi.careers,
    select: (res) => res.data,
  })
}

export function useJobsList(careerId?: number, type?: 'all' | 'real' | 'synthetic') {
  return useQuery({
    queryKey: [...queryKeys.jobs(careerId), type ?? 'all'],
    queryFn: () => industryApi.jobs(careerId, type),
    select: (res) => res.data,
  })
}

export function useCreateAnalysis() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: import('@/types/api').CreateAnalysisPayload) =>
      analysisApi.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.analyses })
    },
  })
}

export function useCreateJob() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: import('@/types/api').CreateJobPayload) =>
      industryApi.createJob(payload),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.jobs(vars.career_id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.jobs() })
    },
  })
}


/** Ringkasan analisis. Polling selagi antrean atau pemrosesan belum selesai. */
export function useAnalysis(id: number) {
  return useQuery({
    queryKey: queryKeys.analysis(id),
    queryFn: () => analysisApi.show(id),
    select: (res) => res.data,
    enabled: Number.isInteger(id) && id > 0,
    refetchInterval: (query) => {
      const status = query.state.data?.data.status
      return status === 'queued' || status === 'processing' ? 2500 : false
    },
  })
}

export function useAnalysisMatches(id: number, enabled: boolean) {
  return useQuery({
    queryKey: queryKeys.analysisMatches(id),
    queryFn: () => analysisApi.matches(id),
    select: (res) => res.data,
    enabled: enabled && Number.isInteger(id) && id > 0,
  })
}

export function useAnalysisRecommendations(id: number, enabled: boolean) {
  return useQuery({
    queryKey: queryKeys.analysisRecommendations(id),
    queryFn: () => analysisApi.recommendations(id),
    enabled: enabled && Number.isInteger(id) && id > 0,
  })
}
