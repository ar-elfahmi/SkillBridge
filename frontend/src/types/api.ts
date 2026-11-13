/**
 * Tipe DTO frontend, diturunkan dari docs/API.md dan backend Http/Resources.
 * Sumber: docs/API.md section 2 (Auth), 3 (RPS), 4 (Jobs), 5 (Analyses).
 * Update saat kontrak API berubah (docs/TEAM.md section 5).
 */

/* =========================================================================
 * 1. ENUMS & UNION TYPES
 * ========================================================================= */

export type Role = 'student'

export type ParseStatus = 'pending' | 'parsing' | 'parsed' | 'failed' | 'needs_ocr'
export type ExtractionStatus = 'pending' | 'processing' | 'completed' | 'failed'
export type ReviewStatus = 'unreviewed' | 'confirmed' | 'rejected'

export type CompetencyOrigin = 'ai' | 'user'
export type CompetencyReviewStatus = 'pending' | 'confirmed' | 'rejected'
export type CompetencySourceType =
  | 'CPL'
  | 'CPMK'
  | 'Sub-CPMK'
  | 'material'
  | 'weekly_plan'
  | 'assignment'
  | 'user'

export type VerificationStatus = 'verified' | 'unverified'
export type SkillCategory =
  | 'language'
  | 'framework'
  | 'tool'
  | 'concept'
  | 'practice'
  | 'other'

export type Classification = 'covered' | 'partial' | 'missing'
export type AnalysisStatus = 'queued' | 'processing' | 'completed' | 'failed'
export type RecommendationPriority = 'high' | 'medium' | 'low'

/* =========================================================================
 * 2. AUTH & USER
 * ========================================================================= */

export interface User {
  id: number
  name: string
  email: string
  role: Role
  created_at: string
}

/* =========================================================================
 * 3. RPS & COMPETENCIES
 * ========================================================================= */

export interface Course {
  id?: number
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

export interface CourseSkill {
  id: number
  skill: string
  canonical_skill: string
  category: SkillCategory | null
  competency: string
  evidence: string | null
  source_type: CompetencySourceType
  source_reference: string | null
  week: number | null
  page: number | null
  verification_status: VerificationStatus
  origin: CompetencyOrigin
  review_status: CompetencyReviewStatus
  user_edited: boolean
}

/* =========================================================================
 * 4. CAREERS & JOB POSTINGS
 * ========================================================================= */

export interface Career {
  id: number
  name: string
  slug: string
  description: string | null
  job_postings_count?: number
}

export interface JobSkill {
  id: number
  skill: string
  canonical_skill: string
  category: SkillCategory | null
  requirement_text: string | null
  evidence: string | null
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
  dataset_version: string | null
  is_synthetic?: boolean
  extraction_status: ExtractionStatus
  skills?: JobSkill[]
  created_at?: string
}

/* =========================================================================
 * 5. ANALYSES & MATCHING
 * ========================================================================= */

export interface AnalysisSummary {
  total_skills: number
  covered: number
  partial: number
  missing: number
  coverage_percentage: number
}

export interface AnalysisModelVersions {
  judge_model?: string
  embedding_model?: string
  embedding_dimensions?: number
  prompt_version?: string
  sim_floor?: number
  sim_high?: number
  top_k?: number
  [key: string]: unknown
}

export interface Analysis {
  analysis_id: number
  status: AnalysisStatus
  career: string
  course: string
  summary: AnalysisSummary | null
  model_versions: AnalysisModelVersions | null
  error_message: string | null
  created_at: string
}

export interface MatchedCompetency {
  id: number
  competency: string
  evidence: string | null
  source_reference: string | null
  page: number | null
}

export interface JobEvidence {
  job_posting_id: number
  company: string
  evidence: string | null
}

export interface SkillMatch {
  skill: string
  demand_count: number
  self_declared: boolean
  classification: Classification
  similarity_score: number | null
  reason: string | null
  matched_competency: MatchedCompetency | null
  job_evidence: JobEvidence[]
}

/* =========================================================================
 * 6. RECOMMENDATIONS
 * ========================================================================= */

export interface RecommendationDetails {
  topics: string[]
  learning_keywords: string[]
}

export interface Recommendation {
  skill: string
  priority: RecommendationPriority
  title: string
  reason: string
  details: RecommendationDetails
}

export interface SelfDeclaredSkill {
  skill: string
  classification: Classification
  label: string
}

export interface RecommendationsResponse {
  data: Recommendation[]
  meta: {
    self_declared_skills: SelfDeclaredSkill[]
  }
}

/* =========================================================================
 * 7. MUTATION PAYLOADS & RESPONSES
 * ========================================================================= */

export interface CreateAnalysisPayload {
  rps_document_id: number
  career_id: number
  job_posting_ids: number[]
}

export interface CreateJobPayload {
  career_id: number
  title: string
  company: string
  description: string
  source_url?: string
}

export interface UpdateRpsMetadataPayload {
  code?: string | null
  name?: string | null
  credits?: number | null
  semester?: number | null
  study_program?: string | null
}

export interface CreateCompetencyPayload {
  skill: string
  competency: string
  category?: SkillCategory
}

export interface UpdateCompetencyPayload {
  review_status?: CompetencyReviewStatus
  skill?: string
  competency?: string
}

export interface DuplicateRpsResponse {
  message: string
  existing_id: number
}

/* =========================================================================
 * 8. COMMON API ERROR
 * ========================================================================= */

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}
