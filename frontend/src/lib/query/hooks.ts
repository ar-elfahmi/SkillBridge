import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { authApi } from '@/lib/api/endpoints'
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
