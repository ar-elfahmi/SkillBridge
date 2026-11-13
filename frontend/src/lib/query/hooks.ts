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
    enabled: Number.isInteger(id) && id > 0,
    refetchInterval: (query) => {
      const data = query.state.data?.data
      if (!data) return false
      // Polling jika masih pending/parsing atau ekstraksi kompetensi masih processing
      if (
        data.parse_status === 'pending' ||
        data.parse_status === 'parsing' ||
        data.extraction_status === 'processing'
      ) {
        return 2500
      }
      return false
    },
  })
}

export function useUpdateRpsMetadata() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof rpsApi.updateMetadata>[1] }) =>
      rpsApi.updateMetadata(id, payload),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.rpsDetail(vars.id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.rps })
    },
  })
}

export function useStartExtraction() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => rpsApi.startExtraction(id),
    onSuccess: (_, id) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.rpsDetail(id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.competencies(id) })
    },
  })
}

export function useCompetenciesList(rpsId: number, enabled = true) {
  return useQuery({
    queryKey: queryKeys.competencies(rpsId),
    queryFn: () => rpsApi.competencies(rpsId),
    select: (res) => res.data,
    enabled: enabled && Number.isInteger(rpsId) && rpsId > 0,
  })
}

export function useUpdateCompetency() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      docId,
      competencyId,
      payload,
    }: {
      docId: number
      competencyId: number
      payload: Parameters<typeof rpsApi.updateCompetency>[2]
    }) => rpsApi.updateCompetency(docId, competencyId, payload),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.competencies(vars.docId) })
      queryClient.invalidateQueries({ queryKey: queryKeys.rpsDetail(vars.docId) })
    },
  })
}

export function useCreateCompetency() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: number
      payload: Parameters<typeof rpsApi.createCompetency>[1]
    }) => rpsApi.createCompetency(id, payload),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.competencies(vars.id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.rpsDetail(vars.id) })
    },
  })
}

export function useConfirmReview() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, confirmAllPending }: { id: number; confirmAllPending?: boolean }) =>
      rpsApi.confirmReview(id, confirmAllPending),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.rpsDetail(vars.id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.competencies(vars.id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.rps })
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

export function useJobsList(careerId?: number) {
  return useQuery({
    queryKey: queryKeys.jobs(careerId),
    queryFn: () => industryApi.jobs(careerId),
    select: (res) => res.data,
  })
}

export function useCreateJob() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: Parameters<typeof industryApi.createJob>[0]) =>
      industryApi.createJob(payload),
    onSuccess: (_, vars) => {
      queryClient.invalidateQueries({ queryKey: queryKeys.jobs(vars.career_id) })
      queryClient.invalidateQueries({ queryKey: queryKeys.jobs() })
    },
  })
}

export function useCreateAnalysis() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: Parameters<typeof analysisApi.create>[0]) =>
      analysisApi.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.analyses })
    },
  })
}


