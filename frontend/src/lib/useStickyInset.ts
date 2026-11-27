import { useCallback, useEffect, useRef } from 'react'

type Side = 'top' | 'bottom'

/**
 * Menulis tinggi bilah lengket ke variabel CSS `--sticky-top` / `--sticky-bottom` di `<html>`.
 * `html` memakainya sebagai `scroll-padding`, sehingga elemen yang difokuskan lewat papan ketik
 * tidak tertutup bilah (WCAG 2.2, 2.4.11). `staticFrom` adalah media query saat bilah tidak lagi lengket.
 */
export function useStickyInset(side: Side, staticFrom?: string) {
  const elementRef = useRef<HTMLElement | null>(null)
  const property = `--sticky-${side}`

  const apply = useCallback(() => {
    const root = document.documentElement
    const el = elementRef.current
    const isStatic =
      !!staticFrom && typeof window.matchMedia === 'function' && window.matchMedia(staticFrom).matches
    if (!el || isStatic) {
      root.style.removeProperty(property)
      return
    }
    root.style.setProperty(property, `${Math.ceil(el.getBoundingClientRect().height)}px`)
  }, [property, staticFrom])

  useEffect(() => {
    window.addEventListener('resize', apply)
    return () => {
      window.removeEventListener('resize', apply)
      document.documentElement.style.removeProperty(property)
    }
  }, [apply, property])

  return useCallback(
    (el: HTMLElement | null) => {
      elementRef.current = el
      apply()
      if (!el || typeof ResizeObserver !== 'function') return
      const observer = new ResizeObserver(apply)
      observer.observe(el)
      return () => observer.disconnect()
    },
    [apply]
  )
}