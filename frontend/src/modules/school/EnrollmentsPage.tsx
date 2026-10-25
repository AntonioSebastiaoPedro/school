import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { ErrorBanner } from '../../components/ui'
import { api } from '../../services/api/client'
import type { Enrollment, SchoolYear, Section, Student } from '../../services/api/types'

export function EnrollmentsPage() {
  const queryClient = useQueryClient()
  const [sectionId, setSectionId] = useState('')
  const [studentId, setStudentId] = useState('')

  const sections = useQuery({ queryKey: ['sections'], queryFn: () => api<{ data: Section[] }>('GET', '/sections') })
  const years = useQuery({ queryKey: ['years'], queryFn: () => api<{ data: SchoolYear[] }>('GET', '/school-years') })
  const students = useQuery({ queryKey: ['students', ''], queryFn: () => api<{ data: Student[] }>('GET', '/students?search=') })
  const enrollments = useQuery({
    queryKey: ['enrollments', sectionId],
    enabled: sectionId !== '',
    queryFn: () => api<{ data: Enrollment[] }>('GET', `/enrollments?section_id=${sectionId}`),
  })

  const section = sections.data?.data.find((s) => String(s.id) === sectionId)
  const refresh = () => queryClient.invalidateQueries({ queryKey: ['enrollments'] })

  const enroll = useMutation({
    mutationFn: () =>
      api<{ warnings: string[] }>('POST', '/enrollments', {
        student_id: Number(studentId),
        school_year_id: section?.school_year_id,
        grade_level_id: section?.grade_level_id,
        section_id: section?.id,
      }),
    onSuccess: () => {
      setStudentId('')
      return Promise.all([refresh(), queryClient.invalidateQueries({ queryKey: ['sections'] })])
    },
  })
  const decide = useMutation({
    mutationFn: (v: { id: number; decision: 'approve' | 'cancel' }) => api('POST', `/enrollments/${v.id}/decision`, { decision: v.decision }),
    onSuccess: refresh,
  })

  const yearName = (id: number) => years.data?.data.find((y) => y.id === id)?.name

  return (
    <section>
      <h2>Matrículas</h2>
      <select aria-label="Turma" value={sectionId} onChange={(e) => setSectionId(e.target.value)}>
        <option value="">Escolha a turma</option>
        {sections.data?.data.map((s) => (
          <option key={s.id} value={s.id}>
            {yearName(s.school_year_id)} · classe {s.grade_level_id} · turma {s.name}
          </option>
        ))}
      </select>

      {section && (
        <>
          <table>
            <thead>
              <tr>
                <th>Nº</th>
                <th>Aluno</th>
                <th>Estado</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {enrollments.data?.data.map((e) => (
                <tr key={e.id}>
                  <td>{e.roll_number}</td>
                  <td>
                    {e.student?.first_name} {e.student?.last_name}
                  </td>
                  <td>{e.status}</td>
                  <td>
                    {e.status === 'pending' && <button onClick={() => decide.mutate({ id: e.id, decision: 'approve' })}>Aprovar</button>}
                    {['pending', 'active'].includes(e.status) && <button onClick={() => decide.mutate({ id: e.id, decision: 'cancel' })}>Cancelar</button>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <form
            className="grid"
            onSubmit={(e) => {
              e.preventDefault()
              enroll.mutate()
            }}
          >
            <select aria-label="Aluno" value={studentId} onChange={(e) => setStudentId(e.target.value)} required>
              <option value="">Escolha o aluno</option>
              {students.data?.data.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.student_number} · {s.first_name} {s.last_name}
                </option>
              ))}
            </select>
            <button disabled={enroll.isPending}>Matricular</button>
          </form>
          {enroll.data?.warnings.map((w) => (
            <p key={w} className="warning">
              {w}
            </p>
          ))}
        </>
      )}
      <ErrorBanner error={enroll.error ?? decide.error ?? enrollments.error} />
    </section>
  )
}
