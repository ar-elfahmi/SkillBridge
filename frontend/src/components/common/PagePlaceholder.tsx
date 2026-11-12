import type { ReactNode } from 'react'

/**
 * Placeholder halaman selagi scaffold. Digranti halaman nyata pada task frontend masing-masing.
 */
export function PagePlaceholder({ title, task }: { title: string; task: string; children?: ReactNode }) {
  return (
    <main className="mx-auto flex min-h-svh max-w-5xl flex-col gap-2 p-6">
      <h1 className="text-2xl font-semibold">{title}</h1>
      <p className="text-sm text-muted-foreground">
        Halaman belum diimplementasikan — menunggu task {task}.
      </p>
    </main>
  )
}
