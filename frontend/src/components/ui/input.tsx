import * as React from "react"
import { cn } from "cn"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "h-14 min-w-0 w-full rounded-lg border border-border-warm bg-background px-3 text-base text-text-primary transition-colors outline-none md:h-12",
        "placeholder:text-text-secondary",
        "focus-visible:border-brand focus-visible:ring-3 focus-visible:ring-brand/20",
        "aria-invalid:border-danger aria-invalid:ring-3 aria-invalid:ring-danger/20",
        "disabled:cursor-not-allowed disabled:opacity-50",
        className
      )}
      {...props}
    />
  )
}

export { Input }
