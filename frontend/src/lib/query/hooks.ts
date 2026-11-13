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

