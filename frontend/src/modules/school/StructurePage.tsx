import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { ErrorBanner, Field, fieldError } from '../../components/ui'
import { api } from '../../services/api/client'
import { SHIFTS, type GradeLevel, type SchoolYear, type Section } from '../../services/api/types'

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
    <section>
      <h2>Anos lectivos</h2>
      <table>
        <tbody>
          {years.data?.data.map((y) => (
            <tr key={y.id}>
              <td>{y.name}</td>
              <td>
                {y.starts_on} a {y.ends_on}
              </td>
              <td>{y.status}</td>
              <td>{y.status === 'planned' && <button onClick={() => activate.mutate(y.id)}>Activar</button>}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <ErrorBanner error={activate.error ?? years.error} />
      <form
        className="grid"
        onSubmit={(e: FormEvent) => {
          e.preventDefault()
          createYear.mutate()
        }}
      >
        <Field label="Ano (AAAA/AAAA)" error={fieldError(createYear.error, 'name')}>
          <input placeholder="2026/2027" value={year.name} onChange={(e) => setYear({ ...year, name: e.target.value })} required />
        </Field>
        <Field label="Início" error={fieldError(createYear.error, 'starts_on')}>
          <input type="date" value={year.starts_on} onChange={(e) => setYear({ ...year, starts_on: e.target.value })} required />
        </Field>
        <Field label="Fim" error={fieldError(createYear.error, 'ends_on')}>
          <input type="date" value={year.ends_on} onChange={(e) => setYear({ ...year, ends_on: e.target.value })} required />
        </Field>
        <button>Criar ano</button>
      </form>
      <ErrorBanner error={fieldError(createYear.error, 'name') ? null : createYear.error} />

      <h2>Turmas</h2>
      <table>
        <thead>
          <tr>
            <th>Ano</th>
            <th>Classe</th>
            <th>Turma</th>
            <th>Turno</th>
            <th>Vagas</th>
          </tr>
        </thead>
        <tbody>
          {sections.data?.data.map((s) => (
            <tr key={s.id}>
              <td>{yearName(s.school_year_id)}</td>
              <td>{levelName(s.grade_level_id)}</td>
              <td>{s.name}</td>
              <td>{SHIFTS[s.shift]}</td>
              <td>
                {s.enrolled ?? 0}/{s.capacity}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      <form
        className="grid"
        onSubmit={(e: FormEvent) => {
          e.preventDefault()
          createSection.mutate()
        }}
      >
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
        <button>Criar turma</button>
      </form>
      <ErrorBanner error={createSection.error} />
    </section>
  )
}
