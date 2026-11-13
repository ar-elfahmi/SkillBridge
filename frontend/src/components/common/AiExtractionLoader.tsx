import { useState, useEffect } from 'react'
import { Sparkles, FileText, CheckCircle2, ShieldCheck, Brain, Layers } from 'lucide-react'

interface AiExtractionLoaderProps {
  pageCount?: number
  fileName?: string
}

const EXTRACTION_STEPS = [
  {
    icon: FileText,
    title: 'Mengekstrak Layer Teks PDF',
    desc: 'Membaca teks terstruktur di setiap halaman dokumen...',
  },
  {
    icon: Brain,
    title: 'Analisis CPL & CPMK dengan AI',
    desc: 'Memetakan capaian pembelajaran dan kompetensi mata kuliah...',
  },
  {
    icon: ShieldCheck,
    title: 'Verifikasi Bukti Teks Asli (Anti-Halusinasi)',
    desc: 'Mencocokkan setiap kutipan verbatim langsung ke halaman PDF...',
  },
  {
    icon: Layers,
    title: 'Penyusunan Tabel Kompetensi',
    desc: 'Menyiapkan hasil ekstraksi untuk peninjauan Anda...',
  },
]

export function AiExtractionLoader({ pageCount = 14, fileName = 'Dokumen RPS' }: AiExtractionLoaderProps) {
  const [currentStep, setCurrentStep] = useState(0)
  const [progressPercent, setProgressPercent] = useState(15)

  useEffect(() => {
    const stepInterval = setInterval(() => {
      setCurrentStep((prev) => (prev < EXTRACTION_STEPS.length - 1 ? prev + 1 : prev))
    }, 2200)

    const progressInterval = setInterval(() => {
      setProgressPercent((prev) => {
        if (prev >= 92) return 92
        return prev + Math.floor(Math.random() * 8) + 3
      })
    }, 450)

    return () => {
      clearInterval(stepInterval)
      clearInterval(progressInterval)
    }
  }, [])

  return (
    <div className="w-full space-y-6 animate-in fade-in duration-300">
      {/* Main Scanner Container */}
      <div className="relative overflow-hidden rounded-2xl border border-brand/20 bg-gradient-to-b from-brand-soft/40 via-white to-surface-canvas/40 p-6 md:p-8 shadow-sm">
        {/* Ambient Glow in Background */}
        <div className="pointer-events-none absolute -right-16 -top-16 size-64 rounded-full bg-brand/5 blur-3xl" />
        <div className="pointer-events-none absolute -left-16 -bottom-16 size-64 rounded-full bg-amber-500/5 blur-3xl" />

        <div className="flex flex-col lg:flex-row items-center gap-8">
          {/* Left: Document Scanner Visual Micro-Interaction */}
          <div className="relative flex shrink-0 items-center justify-center">
            {/* Animated Radar Pulse Rings */}
            <div className="absolute size-44 rounded-full border border-brand/15 animate-ping opacity-30" />
            <div className="absolute size-52 rounded-full border border-brand/10 animate-pulse opacity-40" />

            {/* Document Card Sheet */}
            <div className="relative size-40 sm:size-44 rounded-2xl border-2 border-brand/30 bg-white p-4 shadow-xl overflow-hidden flex flex-col justify-between">
              {/* Animated Laser Scanning Line */}
              <div
                className="animate-scan-line pointer-events-none absolute inset-x-0 h-1 bg-gradient-to-r from-transparent via-brand to-transparent z-20"
                style={{
                  boxShadow: '0 0 12px 2px rgba(154, 0, 2, 0.8), 0 0 4px rgba(255, 255, 255, 0.9)',
                }}
              />
              <div className="pointer-events-none absolute inset-x-0 h-10 bg-gradient-to-b from-brand/10 to-transparent -top-2 animate-scan-line z-10" />

              {/* Document Header Representation */}
              <div className="flex items-center justify-between border-b border-border-warm/50 pb-2">
                <div className="flex items-center gap-1.5">
                  <FileText className="size-4 text-brand" />
                  <span className="text-[10px] font-bold text-text-primary truncate max-w-[80px]">
                    {fileName.replace(/\.pdf$/i, '')}
                  </span>
                </div>
                <span className="rounded bg-brand-soft px-1.5 py-0.5 text-[9px] font-bold text-brand">
                  {pageCount} Hal
                </span>
              </div>

              {/* Simulated Skeleton Lines of Text */}
              <div className="space-y-2 py-2">
                <div className="h-2 w-3/4 rounded-full bg-gray-200 animate-pulse" />
                <div className="h-2 w-full rounded-full bg-gray-100 animate-pulse delay-75" />
                <div className="h-2 w-5/6 rounded-full bg-gray-200 animate-pulse delay-150" />
                <div className="h-2 w-2/3 rounded-full bg-gray-100 animate-pulse delay-200" />
                <div className="h-2 w-4/5 rounded-full bg-gray-200 animate-pulse delay-300" />
              </div>

              {/* Document Footer */}
              <div className="flex items-center justify-between pt-1 border-t border-dashed border-border-warm/60 text-[9px] text-text-secondary font-medium">
                <span>CPMK</span>
                <span className="inline-flex items-center gap-0.5 text-brand font-bold">
                  <Sparkles className="size-2.5 animate-spin" /> Scanning
                </span>
              </div>
            </div>
          </div>

          {/* Right: Dynamic Rotating Steps & Status Info */}
          <div className="flex-1 space-y-4 text-left w-full">
            <div className="space-y-1">
              <div className="flex items-center gap-2">
                <span className="inline-flex items-center gap-1.5 rounded-full bg-brand px-3 py-1 text-xs font-bold text-white shadow-xs">
                  <Sparkles className="size-3.5 animate-spin" /> AI Ekstraksi Berjalan
                </span>
                <span className="text-xs font-semibold text-text-secondary">
                  Model: Gemini 2.5 Flash
                </span>
              </div>
              <h2 className="text-lg md:text-xl font-bold font-serif text-text-primary pt-1">
                Sedang Membaca & Mengekstrak Kompetensi RPS
              </h2>
              <p className="text-xs text-text-secondary leading-relaxed max-w-xl">
                Sistem membaca bab CPL, CPMK, Sub-CPMK, rencana mingguan, dan tugas dari {pageCount} halaman dokumen{' '}
                <strong className="text-text-primary font-semibold">{fileName}</strong>.
              </p>
            </div>

            {/* Glowing Gradient Progress Bar */}
            <div className="space-y-1.5 pt-1">
              <div className="flex items-center justify-between text-xs font-bold text-text-primary">
                <span className="text-brand flex items-center gap-1">
                  <span className="size-2 rounded-full bg-brand animate-ping inline-block" />
                  {EXTRACTION_STEPS[currentStep].title}
                </span>
                <span>{progressPercent}%</span>
              </div>
              <div className="h-2 w-full rounded-full bg-surface-canvas overflow-hidden border border-border-warm/60">
                <div
                  className="h-full rounded-full bg-gradient-to-r from-brand via-amber-500 to-emerald-500 transition-all duration-300 ease-out"
                  style={{ width: `${progressPercent}%` }}
                />
              </div>
            </div>

            {/* Dynamic Step-by-Step Status Checklist */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-2">
              {EXTRACTION_STEPS.map((step, idx) => {
                const isPassed = currentStep > idx
                const isCurrent = currentStep === idx
                const StepIcon = step.icon

                return (
                  <div
                    key={idx}
                    className={`flex items-start gap-2.5 rounded-xl border p-2.5 text-xs transition-all duration-300 ${
                      isPassed
                        ? 'border-emerald-200 bg-emerald-50/60 text-emerald-950'
                        : isCurrent
                        ? 'border-brand/40 bg-white ring-2 ring-brand/20 shadow-xs scale-[1.01]'
                        : 'border-border-warm/60 bg-surface-canvas/50 text-text-secondary opacity-60'
                    }`}
                  >
                    <div
                      className={`flex size-6 items-center justify-center rounded-full shrink-0 font-bold text-xs transition-colors ${
                        isPassed
                          ? 'bg-emerald-600 text-white'
                          : isCurrent
                          ? 'bg-brand text-white animate-pulse'
                          : 'bg-surface-subtle text-text-secondary'
                      }`}
                    >
                      {isPassed ? <CheckCircle2 className="size-3.5" /> : <StepIcon className="size-3" />}
                    </div>
                    <div className="min-w-0">
                      <p className={`font-bold leading-tight ${isCurrent ? 'text-brand' : ''}`}>
                        {step.title}
                      </p>
                      <p className="text-[10px] text-text-secondary mt-0.5 truncate leading-tight">
                        {step.desc}
                      </p>
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
        </div>
      </div>

      {/* Shimmering Skeleton Cards (Materializing Preview) */}
      <div className="space-y-3">
        <div className="flex items-center justify-between text-xs text-text-secondary font-semibold px-1">
          <span className="flex items-center gap-1.5">
            <span className="size-2 rounded-full bg-brand animate-pulse" />
            Menyiapkan kartu kompetensi...
          </span>
          <span className="italic text-[11px]">Memuat secara otomatis</span>
        </div>

        {[1, 2].map((i) => (
          <div
            key={i}
            className="relative overflow-hidden rounded-2xl border border-border-warm bg-white p-5 shadow-xs"
          >
            {/* Shimmer Sweep Effect */}
            <div className="animate-shimmer pointer-events-none absolute inset-0 z-10" />

            <div className="space-y-3">
              {/* Badge Row Skeleton */}
              <div className="flex items-center gap-2">
                <div className="h-5 w-28 rounded-md bg-gray-200 animate-pulse" />
                <div className="h-5 w-20 rounded-md bg-brand-soft/60 animate-pulse" />
                <div className="h-5 w-24 rounded-md bg-emerald-100/60 animate-pulse" />
              </div>

              {/* Text Description Skeleton */}
              <div className="space-y-1.5">
                <div className="h-3.5 w-full rounded bg-gray-200 animate-pulse" />
                <div className="h-3.5 w-4/5 rounded bg-gray-100 animate-pulse" />
              </div>

              {/* Quote Box Skeleton */}
              <div className="rounded-xl border border-border-warm/60 bg-surface-canvas/60 p-3 space-y-1">
                <div className="h-2.5 w-40 rounded bg-gray-200 animate-pulse" />
                <div className="h-3 w-3/4 rounded bg-gray-100 italic animate-pulse" />
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
