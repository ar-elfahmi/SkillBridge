interface FieldErrorProps {
  id: string
  message?: string
}

/** Inline error under a form field; pair with aria-invalid and aria-describedby on the input. */
export function FieldError({ id, message }: FieldErrorProps) {
  if (!message) return null
  return (
    <p id={id} role="alert" className="text-sm font-medium text-danger">
      {message}
    </p>
  )
}