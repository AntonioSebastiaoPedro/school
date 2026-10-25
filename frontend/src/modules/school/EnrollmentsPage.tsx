import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Badge, ErrorBanner, Field, Meter, Notice, PageHead } from '../../components/ui'
import { api } from '../../services/api/client'
import type { Enrollment, GradeLevel, Paginated, SchoolYear, Section, Student } from '../../services/api/types'
import { SHIFTS } from '../../services/api/types'

const STATUS: Record<string, { label: string; tone?: 'ok' | 'warn' | 'bad' }> = {
  pending: { label: 'Pendente', tone: 'warn' },
  approved: { label: 'Aprovada', tone: 'ok' },
  active: { label: 'Activa', tone: 'ok' },
  completed: { label: 'Concluída' },
  cancelled: { label: 'Cancelada', tone: 'bad' },
  transferred: { label: 'Transferida' },
  dropped: { label: 'Desistente', tone: 'bad' },
}

const ageOn = (birth: string | null) => (birth ? Math.floor((Date.now() - new Date(birth).getTime()) / 31_557_600_000) : null)

export function EnrollmentsPage() {
  const queryClient = useQueryClient()
  const [sectionId, setSectionId] = useState('')
  const [studentId, setStudentId] = useState('')

  const sections = useQuery({ queryKey: ['sections'], queryFn: () => api<{ data: Section[] }>('GET', '/sections') })
  const years = useQuery({ queryKey: ['years'], queryFn: () => api<{ data: SchoolYear[] }>('GET', '/school-years') })
  const levels = useQuery({ queryKey: ['levels'], queryFn: () => api<{ data: GradeLevel[] }>('GET', '/grade-levels') })
  const students = useQuery({ queryKey: ['students', ''], queryFn: () => api<Paginated<Student>>('GET', '/students?search=') })
  const enrollments = useQuery({
    queryKey: ['enrollments', sectionId],
    enabled: sectionId !== '',
    queryFn: () => api<Paginated<Enrollment>>('GET', `/enrollments?section_id=${sectionId}`),
  })

  const section = sections.data?.data.find((s) => String(s.id) === sectionId)
  const activeYear = years.data?.data.find((y) => y.status === 'active')
  const level = levels.data?.data.find((l) => l.id === section?.grade_level_id)
  const student = students.data?.data.find((s) => String(s.id) === studentId)
  const age = ageOn(student?.birth_date ?? null)
  const selectable = sections.data?.data.filter((s) => s.school_year_id === activeYear?.id) ?? []
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
    onSuccess: () => Promise.all([refresh(), queryClient.invalidateQueries({ queryKey: ['sections'] })]),
  })

  return (
    <>
      <PageHead title="Matrículas" subtitle={activeYear ? `Ano lectivo ${activeYear.name} · a matrícula fica pendente até ser aprovada` : 'Sem ano lectivo activo'} />

      <div className="cols">
        <section className="card main">
          <h2>Classe e turma</h2>

          <fieldset className="choice-grid" aria-label="Turma">
            <legend className="sr-only">Turma</legend>
            {selectable.map((s) => {
              const full = (s.enrolled ?? 0) >= s.capacity
              const lvl = levels.data?.data.find((l) => l.id === s.grade_level_id)
              return (
                <label key={s.id} className={`choice${sectionId === String(s.id) ? ' selected' : ''}${full ? ' disabled' : ''}`}>
                  <span className="choice-head">
                    <span>
                      {lvl?.name ?? `Classe ${s.grade_level_id}`} · {s.name}
                    </span>
                    <input type="radio" name="turma" value={s.id} disabled={full} checked={sectionId === String(s.id)} onChange={() => setSectionId(String(s.id))} />
                  </span>
                  <span className="muted">{SHIFTS[s.shift]}</span>
                  <Meter value={s.enrolled ?? 0} max={s.capacity} />
                  <span style={{ fontSize: 13 }}>{full ? `Lotada (${s.capacity} de ${s.capacity})` : `${s.enrolled ?? 0} de ${s.capacity} lugares`}</span>
                </label>
              )
            })}
            {selectable.length === 0 && <p className="muted">Crie turmas no ano lectivo activo em «Anos e turmas».</p>}
          </fieldset>

          {section && level && (
            <>
              {age !== null && student && (
                <Notice tone={age >= level.min_age ? 'ok' : 'warn'} icon="check">
                  <strong>Idade do aluno:</strong> {age} anos. Idade mínima da {level.name}: {level.min_age} anos; idade máxima habitual: {level.max_age}. A validação definitiva é feita ao matricular.
                </Notice>
              )}

              <Field label="Aluno">
                <select aria-label="Aluno" value={studentId} onChange={(e) => setStudentId(e.target.value)}>
                  <option value="">Escolha o aluno</option>
                  {students.data?.data.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.student_number} · {s.first_name} {s.last_name}
                    </option>
                  ))}
                </select>
              </Field>

              <Notice>O nº de ordem na turma é atribuído automaticamente quando a matrícula é criada.</Notice>

              <div className="actions" style={{ justifyContent: 'flex-end' }}>
                <button className="primary" disabled={!studentId || enroll.isPending} onClick={() => enroll.mutate()}>
                  Matricular
                </button>
              </div>
              {enroll.data?.warnings.map((w) => (
                <Notice key={w} tone="warn">
                  {w}
                </Notice>
              ))}
            </>
          )}
          <ErrorBanner error={enroll.error ?? decide.error ?? enrollments.error} />
        </section>

        <aside className="aside">
          <section className="card">
            <h3 className="muted">Aluno</h3>
            {student ? (
              <>
                <div>
                  <strong style={{ fontSize: 16 }}>
                    {student.first_name} {student.last_name}
                  </strong>
                  <div className="muted" style={{ fontSize: 13 }}>
                    Nº de processo <span className="mono">{student.student_number}</span>
                  </div>
                </div>
                <dl className="dl">
                  <dt>Género</dt>
                  <dd>{student.gender === 'F' ? 'Feminino' : 'Masculino'}</dd>
                  <dt>Nascimento</dt>
                  <dd>{student.birth_date ?? '—'}</dd>
                </dl>
              </>
            ) : (
              <p className="muted" style={{ margin: 0 }}>Escolha um aluno.</p>
            )}
          </section>
        </aside>
      </div>

      {section && (
        <section className="card flush">
          <h2 style={{ padding: '16px 20px' }}>Matrículas da turma {section.name}</h2>
          <div className="table-wrap">
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
                    <td className="mono">{e.roll_number}</td>
                    <td>
                      <strong>
                        {e.student?.first_name} {e.student?.last_name}
                      </strong>
                    </td>
                    <td>
                      <Badge tone={STATUS[e.status]?.tone}>{STATUS[e.status]?.label ?? e.status}</Badge>
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      <span className="actions" style={{ justifyContent: 'flex-end' }}>
                        {e.status === 'pending' && (
                          <button className="small primary" onClick={() => decide.mutate({ id: e.id, decision: 'approve' })}>
                            Aprovar
                          </button>
                        )}
                        {['pending', 'active'].includes(e.status) && (
                          <button className="small danger" onClick={() => decide.mutate({ id: e.id, decision: 'cancel' })}>
                            Cancelar
                          </button>
                        )}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </>
  )
}
