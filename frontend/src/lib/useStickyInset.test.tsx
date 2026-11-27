import { render } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { useStickyInset } from '@/lib/useStickyInset'

function Bar({ side, staticFrom }: { side: 'top' | 'bottom'; staticFrom?: string }) {
  const ref = useStickyInset(side, staticFrom)
  return <div ref={ref}>bilah</div>
}

describe('useStickyInset', () => {
  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllGlobals()
    document.documentElement.removeAttribute('style')
  })

  it('menulis tinggi bilah ke variabel CSS sesuai sisi dan membersihkannya saat dilepas', () => {
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue({ height: 44.4 } as DOMRect)
    const bottom = render(<Bar side="bottom" />)
    expect(document.documentElement.style.getPropertyValue('--sticky-bottom')).toBe('45px')
    bottom.unmount()
    expect(document.documentElement.style.getPropertyValue('--sticky-bottom')).toBe('')

    const top = render(<Bar side="top" />)
    expect(document.documentElement.style.getPropertyValue('--sticky-top')).toBe('45px')
    top.unmount()
    expect(document.documentElement.style.getPropertyValue('--sticky-top')).toBe('')
  })

  it('tidak mengisi variabel saat bilah tidak lagi lengket (media query cocok)', () => {
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue({ height: 97 } as DOMRect)
    vi.stubGlobal('matchMedia', () => ({ matches: true, addEventListener: () => {}, removeEventListener: () => {} }))
    render(<Bar side="bottom" staticFrom="(min-width: 640px)" />)
    expect(document.documentElement.style.getPropertyValue('--sticky-bottom')).toBe('')
  })

})
