/**
 * Tipe DTO frontend, diturunkan dari docs/API.md.
 * Sumber: docs/API.md section 2 (Auth), 3 (RPS), 4 (Jobs), 5 (Analyses).
 * Update saat kontrak API berubah (docs/TEAM.md section 5).
 */

export type Role = 'student'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  created_at: string
}

export type ParseStatus = 'pending' | 'parsing' | 'parsed' | 'failed' | 'needs_ocr'
export type ExtractionStatus = 'pending' | 'processing' | 'completed' | 'failed'
export type ReviewStatus = 'unreviewed' | 'confirmed' | 'rejected'
export type Classification = 'covered' | 'partial' | 'missing'

export interface Course {
  id: number
  name: string | null
  code: string | null
  credits: number | null
  semester: number | null
  study_program: string | null
}

export interface RpsMetadataDraft {
  code: string | null
  name: string | null
  credits: number | null
  semester: number | null
  study_program: string | null
}

export interface RpsDocument {
  id: number
  original_name: string
  parse_status: ParseStatus
  extraction_status: ExtractionStatus
  review_status: ReviewStatus
  course: Course | null
  detected_metadata: RpsMetadataDraft | null
  page_count: number
  error_message: string | null
  created_at: string
}

export interface Career {
  id: number
  name: string
  slug: string
  description: string | null
}

export interface JobPosting {
  id: number
  career_id: number
  career: Career | null
  created_by: number | null
  is_curated: boolean
  title: string
  company: string
  description: string
  qualifications: string | null
  source_name: string | null
  source_url: string | null
  collected_at: string | null
  extraction_status: ExtractionStatus
}

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}
