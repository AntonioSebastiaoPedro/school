export interface Membership {
  institution_id: number
  institution_name: string | null
  role: string
  status: string
}

export interface Me {
  id: number
  name: string
  email: string
  is_platform_admin: boolean
  institutions: Membership[]
}

export interface Student {
  id: number
  student_number: string
  first_name: string
  last_name: string
  gender: 'M' | 'F' | null
  birth_date: string | null
  status: string
}

export interface GradeLevel {
  id: number
  grade_number: number
  name: string
  stage: 'preschool' | 'primary' | 'lower_secondary'
  max_score: number | null
  pass_mark: number | null
  has_national_exam: boolean
  assessment_regime: 'none' | 'lenient' | 'strict'
}

export interface SchoolYear {
  id: number
  name: string
  starts_on: string
  ends_on: string
  status: 'planned' | 'active' | 'closed'
}

export interface Section {
  id: number
  name: string
  school_year_id: number
  grade_level_id: number
  shift: 'morning' | 'afternoon' | 'night'
  capacity: number
  enrolled?: number
}

export interface Enrollment {
  id: number
  student_id: number
  status: string
  roll_number: number | null
  student?: Pick<Student, 'id' | 'student_number' | 'first_name' | 'last_name'>
}

export interface Subject {
  id: number
  code: string
  name: string
}

export interface CurriculumRow {
  subject_id: number
  weekly_hours: number
  is_active: boolean
  subject: Subject
}

export interface GradeGridRow {
  enrollment_id: number
  roll_number: number | null
  student_name: string
  mac: number | null
  npp: number | null
  npt: number | null
  mt: number | null
  exam: number | null
  resit: number | null
}

export interface GradeGrid {
  scale: { max_score: number; pass_mark: number }
  locked: boolean
  rows: GradeGridRow[]
}

export interface GradeSheet {
  type: 'term' | 'final' | 'resit'
  subjects: Subject[]
  rows: {
    enrollment_id: number
    roll_number: number | null
    student_name: string
    values: Record<string, Record<string, number | null>>
    outcome: string | null
    outcome_label: string | null
  }[]
}

export const SHIFTS: Record<Section['shift'], string> = { morning: 'Manhã', afternoon: 'Tarde', night: 'Noite' }
