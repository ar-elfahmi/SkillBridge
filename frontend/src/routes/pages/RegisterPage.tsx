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
import { useRegister } from '@/lib/query/hooks'
import { ApiRequestError } from '@/lib/api/client'

const INFO_POINT_ICONS = [Upload, ClipboardCheck, Columns2]

function registerErrorMessage(error: unknown): string {
  if (error instanceof ApiRequestError) {
    if (error.status === 422) {
      const emailErr = error.body.errors?.email?.[0]
      if (emailErr) return emailErr
      const passErr = error.body.errors?.password?.[0]
      if (passErr) return passErr
      if (error.body.message) return error.body.message
    }
    // Error status >= 500 atau status lain jangan tampilkan pesan raw database
    return copy.auth.genericError
  }
  return copy.auth.genericError
}

/** Halaman `/register`. Desain: docs/dihsain.pen frame "02 Register Desktop/Mobile". */
export function RegisterPage() {
  usePageTitle('Daftar')
  const navigate = useNavigate()
  const register = useRegister()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [errors, setErrors] = useState<
    Partial<Record<'name' | 'email' | 'password' | 'passwordConfirmation', string>>
  >({})

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const next: typeof errors = {}
    if (!name.trim()) next.name = copy.auth.nameRequired
    if (!email.trim()) next.email = copy.auth.emailRequired
    else if (!/^\S+@\S+\.\S+$/.test(email.trim())) next.email = copy.auth.emailInvalid
    if (!password) next.password = copy.auth.passwordRequired
    else if (password.length < 8) next.password = copy.auth.passwordTooShort
    if (!passwordConfirmation) next.passwordConfirmation = copy.auth.confirmRequired
    else if (password !== passwordConfirmation) next.passwordConfirmation = copy.auth.passwordMismatch
    setErrors(next)
    if (Object.keys(next).length > 0) return

    register.mutate(
      { name, email, password, passwordConfirmation },
      { onSuccess: () => navigate('/dashboard') },
    )
  }

  const errorMessage = register.isError ? registerErrorMessage(register.error) : null

  return (
    <div className="flex min-h-svh flex-col bg-background">
      <header className="flex h-16 shrink-0 items-center border-b border-border-warm px-4 md:h-[72px] md:px-16">
        <BrandLogo />
      </header>

      <main className="flex flex-1 flex-col md:flex-row md:items-start md:gap-12 md:px-16 md:py-12 justify-center">
        <section className="hidden flex-col gap-6 rounded-xl bg-brand p-10 text-white md:flex md:h-[704px] md:w-[664px]">
          <p className="font-display text-[34px] leading-tight font-normal">
            {copy.auth.registerInfoTitle}
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

          <p className="text-sm font-medium text-white/90">
            {copy.auth.registerInfoNote}
          </p>
        </section>

        <div className="mx-auto flex w-full max-w-[327px] flex-col gap-6 px-6 py-8 md:mx-0 md:h-[704px] md:w-[440px] md:max-w-none md:gap-5 md:rounded-xl md:border md:border-border-warm md:bg-background md:px-8 md:py-8">
          <div className="flex flex-col gap-1">
            <h1 className="font-display text-2xl font-normal text-text-primary">
              {copy.auth.registerTitle}
            </h1>
            <p className="text-sm text-text-secondary">{copy.auth.registerSubtitle}</p>
          </div>

          <form className="flex flex-col gap-5 md:gap-4" onSubmit={handleSubmit} noValidate>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="name">{copy.auth.nameLabel}</Label>
              <Input
                id="name"
                name="name"
                type="text"
                autoComplete="name"
                required
                placeholder={copy.auth.namePlaceholder}
                value={name}
                aria-invalid={errors.name ? true : undefined}
                aria-describedby={errors.name ? 'name-error' : undefined}
                onChange={(event) => setName(event.target.value)}
              />
              <FieldError id="name-error" message={errors.name} />
            </div>

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
                  autoComplete="new-password"
                  required
                  placeholder={copy.auth.passwordCreatePlaceholder}
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
              <p className="text-sm text-text-secondary">{copy.auth.passwordHelper}</p>
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="passwordConfirmation">{copy.auth.passwordConfirmLabel}</Label>
              <Input
                id="passwordConfirmation"
                name="passwordConfirmation"
                type={showPassword ? 'text' : 'password'}
                autoComplete="new-password"
                required
                placeholder={copy.auth.passwordConfirmPlaceholder}
                value={passwordConfirmation}
                aria-invalid={errors.passwordConfirmation ? true : undefined}
                aria-describedby={errors.passwordConfirmation ? 'passwordConfirmation-error' : undefined}
                onChange={(event) => setPasswordConfirmation(event.target.value)}
              />
              <FieldError id="passwordConfirmation-error" message={errors.passwordConfirmation} />
            </div>

            {errorMessage && (
              <div role="alert" className="flex items-center gap-2 rounded-lg bg-danger-soft px-3 py-3">
                <CircleAlert className="size-4 shrink-0 text-danger" aria-hidden="true" />
                <p className="text-sm font-medium text-danger">{errorMessage}</p>
              </div>
            )}

            <Button
              type="submit"
              variant="default"
              className="h-12 w-full rounded-lg bg-brand text-base font-semibold text-white hover:bg-brand-hover"
              disabled={register.isPending}
            >
              {register.isPending ? 'Memproses…' : copy.auth.submitRegister}
            </Button>
          </form>

          <p className="text-center text-sm">
            <span className="text-text-secondary">{copy.auth.haveAccount} </span>
            <Link to="/login" className="inline-flex min-h-11 items-center px-1 font-bold text-brand-strong hover:underline">
              {copy.auth.submitLogin}
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
