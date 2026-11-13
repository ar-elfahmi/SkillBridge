/**
 * Query keys TanStack Query.
 * Satu sumber kebenaran agar invalidasi antar halaman konsisten.
 */

export const queryKeys = {
  me: ['me'] as const,
  careers: ['careers'] as const,
  jobs: (careerId?: number) => ['jobs', careerId ?? 'all'] as const,
  job: (id: number) => ['jobs', 'detail', id] as const,
  rps: ['rps'] as const,
  rpsDetail: (id: number) => ['rps', id] as const,
  competencies: (id: number) => ['rps', id, 'competencies'] as const,
  meSkills: ['me', 'skills'] as const,
  analyses: ['analyses'] as const,
  analysis: (id: number) => ['analyses', id] as const,
}
