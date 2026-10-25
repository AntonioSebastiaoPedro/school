import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Badge, ErrorBanner, Field, fieldError, Meter, PageHead } from '../../components/ui'
import { api } from '../../services/api/client'
import { SHIFTS, type GradeLevel, type SchoolYear, type Section } from '../../services/api/types'

const YEAR_STATUS = { planned: 'Planeado', active: 'Activo', closed: 'Fechado' } as const

export function StructurePage() {
  const queryClient = useQueryClient()
  const [year, setYear] = useState({ name: '', starts_on: '', ends_on: '' })
  const [section, setSection] = useState({ school_year_id: '', grade_level_id: '1', name: '', shift: 'morning' })

  const years = useQuery({ queryKey: ['years'], queryFn: () => api<{ data: SchoolYear[] }>('GET', '/school-years') })
  const levels = useQuery({ queryKey: ['levels'], queryFn: () => api<{ data: GradeLevel[] }>('GET', '/grade-levels') })
  const sections = useQuery({ queryKey: ['sections'], queryFn: () => api<{ data: Section[] }>('GET', '/sections') })

  const refresh = () => queryClient.invalidateQueries()

  const createYear = useMutation({ mutationFn: () => api('POST', '/school-years', year), onSuccess: refresh })
  const activate = useMutation({ mutationFn: (id: number) => api('POST', `/school-years/${id}/activate`), onSuccess: refresh })
  const createSection = useMutation({
    mutationFn: () => api('POST', '/sections', { ...section, school_year_id: Number(section.school_year_id), grade_level_id: Number(section.grade_level_id) }),
    onSuccess: refresh,
  })

  const levelName = (id: number) => levels.data?.data.find((l) => l.id === id)?.name ?? id
  const yearName = (id: number) => years.data?.data.find((y) => y.id === id)?.name ?? id

  return (
    <>
      <PageHead title="Anos e turmas" />

      <section className="card flush">
        <h2 style={{ padding: '16px 20px' }}>Anos lectivos</h2>
        <div className="table-wrap">
          <table>
            <tbody>
              {years.data?.data.map((y) => (
                <tr key={y.id}>
                  <td>
                    <strong>{y.name}</strong>
                  </td>
                  <td>
                    {y.starts_on} a {y.ends_on}
                  </td>
                  <td>
                    <Badge tone={y.status === 'active' ? 'ok' : undefined}>{YEAR_STATUS[y.status]}</Badge>
                  </td>
                  <td style={{ textAlign: 'right' }}>{y.status === 'planned' && <button className="small" onClick={() => activate.mutate(y.id)}>Activar</button>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <ErrorBanner error={activate.error ?? years.error} />
      </section>

      <form
        className="card"
        onSubmit={(e) => {
          e.preventDefault()
          createYear.mutate()
        }}
      >
        <h3>Novo ano lectivo</h3>
        <div className="fields">
          <Field label="Ano (AAAA/AAAA)" error={fieldError(createYear.error, 'name')}>
            <input placeholder="2026/2027" value={year.name} onChange={(e) => setYear({ ...year, name: e.target.value })} required />
          </Field>
          <Field label="Início" error={fieldError(createYear.error, 'starts_on')}>
            <input type="date" value={year.starts_on} onChange={(e) => setYear({ ...year, starts_on: e.target.value })} required />
          </Field>
          <Field label="Fim" error={fieldError(createYear.error, 'ends_on')}>
            <input type="date" value={year.ends_on} onChange={(e) => setYear({ ...year, ends_on: e.target.value })} required />
          </Field>
        </div>
        <div className="actions" style={{ justifyContent: 'flex-end' }}>
          <button className="primary">Criar ano</button>
        </div>
        <ErrorBanner error={fieldError(createYear.error, 'name') ? null : createYear.error} />
      </form>

      <section className="card flush">
        <h2 style={{ padding: '16px 20px' }}>Turmas</h2>
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Ano</th>
                <th>Classe</th>
                <th>Turma</th>
                <th>Turno</th>
                <th style={{ width: 220 }}>Lugares</th>
              </tr>
            </thead>
            <tbody>
              {sections.data?.data.map((s) => (
                <tr key={s.id}>
                  <td>{yearName(s.school_year_id)}</td>
                  <td>{levelName(s.grade_level_id)}</td>
                  <td>
                    <strong>{s.name}</strong>
                  </td>
                  <td>{SHIFTS[s.shift]}</td>
                  <td>
                    <div className="bars">
                      <Meter value={s.enrolled ?? 0} max={s.capacity} />
                      <span className="mono">
                        {s.enrolled ?? 0}/{s.capacity}
                      </span>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <form
        className="card"
        onSubmit={(e) => {
          e.preventDefault()
          createSection.mutate()
        }}
      >
        <h3>Nova turma</h3>
        <div className="fields">
          <Field label="Ano lectivo" error={fieldError(createSection.error, 'school_year_id')}>
            <select value={section.school_year_id} onChange={(e) => setSection({ ...section, school_year_id: e.target.value })} required>
              <option value="" disabled>
                —
              </option>
              {years.data?.data.filter((y) => y.status !== 'closed').map((y) => (
                <option key={y.id} value={y.id}>
                  {y.name}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Classe">
            <select value={section.grade_level_id} onChange={(e) => setSection({ ...section, grade_level_id: e.target.value })}>
              {levels.data?.data.map((l) => (
                <option key={l.id} value={l.id}>
                  {l.name}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Turma" error={fieldError(createSection.error, 'name')}>
            <input value={section.name} onChange={(e) => setSection({ ...section, name: e.target.value })} required />
          </Field>
          <Field label="Turno">
            <select value={section.shift} onChange={(e) => setSection({ ...section, shift: e.target.value })}>
              {Object.entries(SHIFTS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </Field>
        </div>
        <div className="actions" style={{ justifyContent: 'flex-end' }}>
          <button className="primary">Criar turma</button>
        </div>
        <ErrorBanner error={createSection.error} />
      </form>
    </>
  )
}
