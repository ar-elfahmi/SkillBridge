import { Link, isRouteErrorResponse, useRouteError } from 'react-router-dom'
import { usePageTitle } from '@/lib/usePageTitle'
import { copy } from '@/lib/copy'

/** Shown for unknown URLs (404) and for unexpected route errors. Never exposes framework details. */
export function ErrorPage() {
  const error = useRouteError()
  const isNotFound = error == null || (isRouteErrorResponse(error) && error.status === 404)
  usePageTitle(isNotFound ? copy.errorPage.notFoundTitle : copy.errorPage.unexpectedTitle)

  return (
    <main className="mx-auto flex min-h-svh max-w-lg flex-col items-start justify-center gap-4 px-6">
      <h1 className="font-display text-3xl font-normal text-text-primary">
        {isNotFound ? copy.errorPage.notFoundTitle : copy.errorPage.unexpectedTitle}
      </h1>
      <p className="text-sm text-text-secondary">
        {isNotFound ? copy.errorPage.notFoundBody : copy.errorPage.unexpectedBody}
      </p>
      <Link
        to="/dashboard"
        className="inline-flex min-h-11 items-center rounded-lg bg-brand px-5 text-sm font-bold text-white hover:bg-brand-hover"
      >
        {copy.errorPage.backHome}
      </Link>
    </main>
  )
}
