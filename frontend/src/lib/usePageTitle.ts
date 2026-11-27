import { useEffect } from 'react'

const BRAND = 'SkillBridge'

/** Sets a unique document title per route so screen-reader users can tell pages apart. */
export function usePageTitle(title: string) {
  useEffect(() => {
    document.title = `${title} · ${BRAND}`
    return () => {
      document.title = BRAND
    }
  }, [title])
}
