import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { ErrorBanner } from '../../components/ui'
import { api } from '../../services/api/client'
import type { GradeSheet, Section } from '../../services/api/types'

const TYPES = { term: 'Trimestral', final: 'Final', resit: 'Recurso (6.ª e 9.ª)' } as const

function summary(type: GradeSheet['type'], v: Record<string, number | null> | undefined): string {
  if (!v) return '—'
  const pick = type === 'term' ? ['mt'] : type === 'final' ? ['mfa'] : ['mfa_after']
  return pick.map((k) => v[k] ?? '—').join(' ')
}

export function SheetPage() {
  const [sectionId, setSectionId] = useState('')
  const [type, setType] = useState<keyof typeof TYPES>('final')
  const [term, setTerm] = useState('1')

  const sections = useQuery({ queryKey: ['sections'], queryFn: () => api<{ data: Section[] }>('GET', '/sections') })
  const sheet = useQuery({
    queryKey: ['sheet', sectionId, type, term],
    enabled: sectionId !== '',
    retry: false,
    queryFn: () => api<GradeSheet>('GET', `/grade-sheets/${sectionId}?type=${type}${type === 'term' ? `&term=${term}` : ''}`),
  })

  return (
    <section>
      <h2>Pautas</h2>
      <div className="grid">
        <select aria-label="Turma" value={sectionId} onChange={(e) => setSectionId(e.target.value)}>
          <option value="">Turma</option>
          {sections.data?.data.map((s) => (
            <option key={s.id} value={s.id}>
              Classe {s.grade_level_id} · {s.name}
            </option>
          ))}
        </select>
        <select aria-label="Tipo de pauta" value={type} onChange={(e) => setType(e.target.value as keyof typeof TYPES)}>
          {Object.entries(TYPES).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
        {type === 'term' && (
          <select aria-label="Trimestre" value={term} onChange={(e) => setTerm(e.target.value)}>
            <option value="1">1.º</option>
            <option value="2">2.º</option>
            <option value="3">3.º</option>
          </select>
        )}
      </div>
      <ErrorBanner error={sheet.error} />
      {sheet.data && (
        <table>
          <thead>
            <tr>
              <th>Nº</th>
              <th>Nome completo</th>
              {sheet.data.subjects.map((s) => (
                <th key={s.id} title={s.name}>
                  {s.code}
                </th>
              ))}
              {type !== 'term' && <th>Observação</th>}
            </tr>
          </thead>
          <tbody>
            {sheet.data.rows.map((r) => (
              <tr key={r.enrollment_id}>
                <td>{r.roll_number}</td>
                <td>{r.student_name}</td>
                {sheet.data.subjects.map((s) => (
                  <td key={s.id}>{summary(sheet.data.type, r.values[s.code])}</td>
                ))}
                {type !== 'term' && <td>{r.outcome_label}</td>}
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </section>
  )
}
