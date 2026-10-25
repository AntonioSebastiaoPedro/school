import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { ErrorBanner } from '../../components/ui'
import { api } from '../../services/api/client'
import type { CurriculumRow, GradeGrid, Section } from '../../services/api/types'
import { isNegative, parseGrade } from './parseGrade'

type Kind = 'mac' | 'npp' | 'npt' | 'exam' | 'resit'
const TERM_KINDS: Kind[] = ['mac', 'npp', 'npt']
const ANNUAL_KINDS: Kind[] = ['exam', 'resit']
const LABEL: Record<Kind, string> = { mac: 'MAC', npp: 'NPP', npt: 'NPT', exam: 'NE', resit: 'NER' }

export function GradesPage() {
  const queryClient = useQueryClient()
  const [sectionId, setSectionId] = useState('')
  const [subjectId, setSubjectId] = useState('')
  const [term, setTerm] = useState('1')
  const [edits, setEdits] = useState<Record<string, string>>({})

  const sections = useQuery({ queryKey: ['sections'], queryFn: () => api<{ data: Section[] }>('GET', '/sections') })
  const section = sections.data?.data.find((s) => String(s.id) === sectionId)

  const curriculum = useQuery({
    queryKey: ['curriculum', section?.grade_level_id],
    enabled: !!section,
    queryFn: () => api<{ data: Record<string, CurriculumRow[]> }>('GET', `/curriculum?grade_level_id=${section?.grade_level_id}`),
  })
  const subjects = Object.values(curriculum.data?.data ?? {}).flat().filter((r) => r.is_active)

  const ready = !!section && subjectId !== ''
  const gridKey = ['grid', sectionId, subjectId, term]
  const grid = useQuery({
    queryKey: gridKey,
    enabled: ready,
    queryFn: () => api<GradeGrid>('GET', `/grades?section_id=${sectionId}&subject_id=${subjectId}&term=${term}`),
  })

  const kinds = term === '0' ? ANNUAL_KINDS : TERM_KINDS
  const max = grid.data?.scale.max_score ?? 10
  const passMark = grid.data?.scale.pass_mark ?? 5
  const cell = (enrollmentId: number, kind: Kind) => `${enrollmentId}:${kind}`

  const save = useMutation({
    mutationFn: () => {
      const entries = Object.entries(edits).map(([key, raw]) => {
        const [enrollmentId, kind] = key.split(':')
        const parsed = parseGrade(raw, max)
        if (!parsed.ok) throw new Error(parsed.message)
        return { enrollment_id: Number(enrollmentId), kind, value: parsed.value }
      })
      return api('PUT', '/grades', { section_id: Number(sectionId), subject_id: Number(subjectId), term: Number(term), entries })
    },
    onSuccess: async () => {
      setEdits({})
      await queryClient.invalidateQueries({ queryKey: ['grid'] })
    },
  })

  const lock = useMutation({
    mutationFn: (locked: boolean) => api('PUT', `/grade-sheets/${sectionId}/locks`, { term: Number(term), locked }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['grid'] }),
  })

  const hasExam = term === '0'

  return (
    <section>
      <h2>Lançamento de notas</h2>
      <div className="grid">
        <select aria-label="Turma" value={sectionId} onChange={(e) => { setSectionId(e.target.value); setSubjectId(''); setEdits({}) }}>
          <option value="">Turma</option>
          {sections.data?.data.map((s) => (
            <option key={s.id} value={s.id}>
              Classe {s.grade_level_id} · {s.name}
            </option>
          ))}
        </select>
        <select aria-label="Disciplina" value={subjectId} onChange={(e) => { setSubjectId(e.target.value); setEdits({}) }} disabled={!section}>
          <option value="">Disciplina</option>
          {subjects.map((r) => (
            <option key={r.subject_id} value={r.subject_id}>
              {r.subject.code} · {r.subject.name}
            </option>
          ))}
        </select>
        <select aria-label="Período" value={term} onChange={(e) => { setTerm(e.target.value); setEdits({}) }}>
          <option value="1">1.º trimestre</option>
          <option value="2">2.º trimestre</option>
          <option value="3">3.º trimestre</option>
          <option value="0">Exame / recurso (6.ª e 9.ª)</option>
        </select>
      </div>

      <ErrorBanner error={grid.error ?? save.error ?? lock.error} />

      {grid.data && (
        <>
          <p>
            Escala 0–{max}, mínimo {passMark}. Vazio ou 0 = sem nota.{grid.data.locked && <strong> Pauta fechada.</strong>}
          </p>
          <table>
            <thead>
              <tr>
                <th>Nº</th>
                <th>Aluno</th>
                {kinds.map((k) => (
                  <th key={k}>{LABEL[k]}</th>
                ))}
                {!hasExam && <th>MT</th>}
              </tr>
            </thead>
            <tbody>
              {grid.data.rows.map((row) => (
                <tr key={row.enrollment_id}>
                  <td>{row.roll_number}</td>
                  <td>{row.student_name}</td>
                  {kinds.map((k) => {
                    const key = cell(row.enrollment_id, k)
                    const raw = edits[key] ?? (row[k] === null ? '' : String(row[k]).replace('.', ','))
                    const parsed = parseGrade(raw, max)
                    return (
                      <td key={k}>
                        <input
                          aria-label={`${LABEL[k]} de ${row.student_name}`}
                          inputMode="decimal"
                          size={4}
                          value={raw}
                          disabled={grid.data.locked}
                          aria-invalid={!parsed.ok}
                          className={parsed.ok && isNegative(parsed.value, passMark) ? 'negative' : undefined}
                          onChange={(e) => setEdits({ ...edits, [key]: e.target.value })}
                        />
                      </td>
                    )
                  })}
                  {!hasExam && <td>{row.mt ?? '—'}</td>}
                </tr>
              ))}
            </tbody>
          </table>
          <button disabled={Object.keys(edits).length === 0 || save.isPending || grid.data.locked} onClick={() => save.mutate()}>
            Guardar notas
          </button>
          <button onClick={() => lock.mutate(!grid.data.locked)}>{grid.data.locked ? 'Reabrir pauta' : 'Fechar pauta'}</button>
        </>
      )}
    </section>
  )
}
