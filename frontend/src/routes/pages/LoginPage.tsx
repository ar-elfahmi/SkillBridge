import { useState } from 'react'
import type { FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { CircleAlert, ClipboardCheck, Columns2, Eye, EyeOff, Upload } from 'lucide-react'
import { BrandLogo } from '@/components/common/BrandLogo'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { FieldError } from '@/components/common/FieldError'
import { usePageTitle } from '@/lib/usePageTitle'
import { copy } from '@/lib/copy'
import { useLogin } from '@/lib/query/hooks'
import { ApiRequestError } from '@/lib/api/client'

const INFO_POINT_ICONS = [Upload, ClipboardCheck, Columns2]

function loginErrorMessage(error: unknown): string {
  if (error instanceof ApiRequestError && error.status === 422) return copy.auth.loginFailed
  return copy.auth.genericError
}

/** Halaman `/login`. Desain: docs/dihsain.pen frame "01 Login Desktop/Mobile". */
export function LoginPage() {
  usePageTitle('Masuk')
  const navigate = useNavigate()
  const login = useLogin()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [errors, setErrors] = useState<{ email?: string; password?: string }>({})

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const next: { email?: string; password?: string } = {}
    if (!email.trim()) next.email = copy.auth.emailRequired
    else if (!/^\S+@\S+\.\S+$/.test(email.trim())) next.email = copy.auth.emailInvalid
    if (!password) next.password = copy.auth.passwordRequired
    setErrors(next)
    if (next.email || next.password) return
    login.mutate({ email, password }, { onSuccess: () => navigate('/dashboard') })
  }

  return (
    <div className="flex min-h-svh flex-col bg-background">
      <header className="flex h-16 shrink-0 items-center border-b border-border-warm px-4 md:h-[72px] md:px-16">
        <BrandLogo />
      </header>

      <main className="flex flex-1 flex-col md:flex-row md:items-start md:gap-12 md:px-16 md:py-12 justify-center">
        <section className="hidden flex-col gap-6 rounded-xl bg-brand p-10 text-white md:flex md:h-[544px] md:w-[664px]">
          <p className="font-display text-[34px] leading-tight font-normal">
            {copy.auth.loginInfoTitle}
          </p>

          <ul className="flex flex-col gap-3">
            {copy.auth.loginInfoPoints.map((label, index) => {
              const Icon = INFO_POINT_ICONS[index]!
              return (
                <li key={label} className="flex items-center gap-3">
                  <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-white/15">
                    <Icon className="size-4" aria-hidden="true" />
                  </span>
                  <span className="text-sm font-medium">{label}</span>
                </li>
              )
            })}
          </ul>
        </section>

        <div className="mx-auto flex w-full max-w-[327px] flex-col gap-6 px-6 py-8 md:mx-0 md:h-[544px] md:w-[440px] md:max-w-none md:gap-5 md:rounded-xl md:border md:border-border-warm md:bg-background md:px-8 md:py-8">
          <h1 className="font-display text-2xl font-normal text-text-primary">{copy.auth.loginTitle}</h1>
          <p className="text-sm text-text-secondary">{copy.auth.loginSubtitle}</p>

          <form className="flex flex-col gap-6 md:gap-5" onSubmit={handleSubmit} noValidate>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="email">{copy.auth.emailLabel}</Label>
              <Input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                required
                placeholder={copy.auth.emailPlaceholder}
                value={email}
                aria-invalid={errors.email ? true : undefined}
                aria-describedby={errors.email ? 'email-error' : undefined}
                onChange={(event) => setEmail(event.target.value)}
              />
              <FieldError id="email-error" message={errors.email} />
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="password">{copy.auth.passwordLabel}</Label>
              <div className="relative">
                <Input
                  id="password"
                  name="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  required
                  placeholder={copy.auth.passwordPlaceholder}
                  className="pr-12"
                  value={password}
                  aria-invalid={errors.password ? true : undefined}
                  aria-describedby={errors.password ? 'password-error' : undefined}
                  onChange={(event) => setPassword(event.target.value)}
                />
                <button
                  type="button"
                  className="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-text-secondary hover:text-text-primary"
                  aria-label={
                    showPassword ? copy.auth.hidePassword : copy.auth.showPassword
                  }
                  aria-pressed={showPassword}
                  onClick={() => setShowPassword((visible) => !visible)}
                >
                  {showPassword ? (
                    <EyeOff className="size-5" aria-hidden="true" />
                  ) : (
                    <Eye className="size-5" aria-hidden="true" />
                  )}
                </button>
              </div>
              <FieldError id="password-error" message={errors.password} />
            </div>

            {login.isError && !errors.email && !errors.password && (
              <div role="alert" className="flex items-center gap-2 rounded-lg bg-danger-soft px-3 py-3">
                <CircleAlert className="size-4 shrink-0 text-danger" aria-hidden="true" />
                <p className="text-sm font-medium text-danger">
                  {loginErrorMessage(login.error)}
                </p>
              </div>
            )}

            <Button
              type="submit"
              variant="default"
              className="h-12 w-full rounded-lg bg-brand text-base font-semibold text-white hover:bg-brand-hover"
              disabled={login.isPending}
            >
              {login.isPending ? 'Memproses…' : copy.auth.submitLogin}
            </Button>
          </form>

          <p className="text-center text-sm">
            <span className="text-text-secondary">{copy.auth.needAccount} </span>
            <Link to="/register" className="inline-flex min-h-11 items-center px-1 font-bold text-brand-strong hover:underline">
              {copy.auth.submitRegister}
            </Link>
          </p>
        </div>
      </main>

      <footer className="flex h-16 shrink-0 items-center justify-center px-6 md:h-[88px] md:justify-start md:px-16">
        <p className="text-xs font-medium text-text-secondary">{copy.footerBrand}</p>
      </footer>
    </div>
  )
}
