import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Badge, ErrorBanner, Field, PageHead } from '../../components/ui'
import { api } from '../../services/api/client'
import type { GradeSheet, Section } from '../../services/api/types'

const TYPES = { term: 'Trimestral', final: 'Final', resit: 'Recurso (6.ª e 9.ª)' } as const
const OUTCOME_TONE: Record<string, 'ok' | 'warn' | 'bad' | undefined> = { promoted: 'ok', resit: 'warn', failed: 'bad' }

function summary(type: GradeSheet['type'], v: Record<string, number | null> | undefined): string {
  if (!v) return '—'
  const key = type === 'term' ? 'mt' : type === 'final' ? 'mfa' : 'mfa_after'
  return String(v[key] ?? '—')
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
    <>
      <PageHead title="Pautas" subtitle="O resultado mostrado é o mesmo que o fecho do ano grava." />
      <div className="grid">
        <Field label="Turma">
          <select aria-label="Turma" value={sectionId} onChange={(e) => setSectionId(e.target.value)}>
            <option value="">Turma</option>
            {sections.data?.data.map((s) => (
              <option key={s.id} value={s.id}>
                Classe {s.grade_level_id} · {s.name}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Tipo de pauta">
          <select aria-label="Tipo de pauta" value={type} onChange={(e) => setType(e.target.value as keyof typeof TYPES)}>
            {Object.entries(TYPES).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </Field>
        {type === 'term' && (
          <Field label="Trimestre">
            <select aria-label="Trimestre" value={term} onChange={(e) => setTerm(e.target.value)}>
              <option value="1">1.º</option>
              <option value="2">2.º</option>
              <option value="3">3.º</option>
            </select>
          </Field>
        )}
      </div>
      <ErrorBanner error={sheet.error} />
      {sheet.data && (
        <section className="card flush">
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th style={{ width: 48 }}>Nº</th>
                  <th>Nome completo</th>
                  {sheet.data.subjects.map((s) => (
                    <th key={s.id} className="center" title={s.name}>
                      {s.code}
                    </th>
                  ))}
                  {type !== 'term' && <th>Observação</th>}
                </tr>
              </thead>
              <tbody>
                {sheet.data.rows.map((r) => (
                  <tr key={r.enrollment_id}>
                    <td className="mono muted">{r.roll_number}</td>
                    <td>
                      <strong>{r.student_name}</strong>
                    </td>
                    {sheet.data.subjects.map((s) => (
                      <td key={s.id} className="center mono">
                        {summary(sheet.data.type, r.values[s.code])}
                      </td>
                    ))}
                    {type !== 'term' && (
                      <td>
                        <Badge tone={OUTCOME_TONE[r.outcome ?? '']}>{r.outcome_label}</Badge>
                      </td>
                    )}
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
