import { useSyncExternalStore } from 'react'

/** Mengikuti media query. Tanpa `matchMedia` (mis. jsdom) hasilnya `false`, yaitu tampilan desktop. */
export function useMediaQuery(query: string): boolean {
  return useSyncExternalStore(
    (onChange) => {
      if (typeof window.matchMedia !== 'function') return () => {}
      const list = window.matchMedia(query)
      list.addEventListener('change', onChange)
      return () => list.removeEventListener('change', onChange)
    },
    () => typeof window.matchMedia === 'function' && window.matchMedia(query).matches,
    () => false
  )
}

/** Di bawah breakpoint `md` Tailwind (768px). */
export function useIsMobile(): boolean {
  return useMediaQuery('(max-width: 767px)')
}
