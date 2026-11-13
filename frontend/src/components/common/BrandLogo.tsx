import { cn } from "cn"

/**
 * Logo SkillBridge mengikuti frame "Logo" pada docs/dihsain.pen:
 * mark topi wisuda + wordmark. Mark digambar sebagai path (bukan aset eksternal).
 */
export function BrandLogo({ className }: { className?: string }) {
  return (
    <span data-slot="brand-logo" className={cn("flex items-center gap-3", className)}>
      <img src="/logo.svg" alt="SkillBridge" />
    </span>
  )
}
