import * as React from "react"
import { cn } from "cn"

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        "min-w-0 w-full resize-none rounded-lg border border-field bg-background px-3 py-2.5 text-base leading-snug text-text-primary transition-colors outline-none md:text-sm",
        "placeholder:text-text-secondary",
        "focus-visible:border-brand-accent focus-visible:ring-3 focus-visible:ring-brand-accent/30",
        "aria-invalid:border-danger aria-invalid:ring-3 aria-invalid:ring-danger/20",
        "disabled:cursor-not-allowed disabled:opacity-50",
        className
      )}
      {...props}
    />
  )
}

export { Textarea }
