import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Icon } from '../../components/Icon'
import { Badge, ErrorBanner, Field, PageHead, Stat } from '../../components/ui'
import { api } from '../../services/api/client'
import type { CurriculumRow, GradeGrid, GradeLevel, Section } from '../../services/api/types'
import { isNegative, parseGrade } from './parseGrade'

type Kind = 'mac' | 'npp' | 'npt' | 'exam' | 'resit'
const TERM_KINDS: Kind[] = ['mac', 'npp', 'npt']
const ANNUAL_KINDS: Kind[] = ['exam', 'resit']
const LABEL: Record<Kind, string> = { mac: 'MAC', npp: 'NPP', npt: 'NPT', exam: 'NE', resit: 'NER' }
const fmt = (n: number) => n.toFixed(1).replace('.', ',')

export function GradesPage() {
  const queryClient = useQueryClient()
  const [sectionId, setSectionId] = useState('')
  const [subjectId, setSubjectId] = useState('')
  const [term, setTerm] = useState('1')
  const [edits, setEdits] = useState<Record<string, string>>({})

  const sections = useQuery({ queryKey: ['sections'], queryFn: () => api<{ data: Section[] }>('GET', '/sections') })
  const levels = useQuery({ queryKey: ['levels'], queryFn: () => api<{ data: GradeLevel[] }>('GET', '/grade-levels') })
  const section = sections.data?.data.find((s) => String(s.id) === sectionId)
  const level = levels.data?.data.find((l) => l.id === section?.grade_level_id)

  const curriculum = useQuery({
    queryKey: ['curriculum', section?.grade_level_id],
    enabled: !!section,
    queryFn: () => api<{ data: Record<string, CurriculumRow[]> }>('GET', `/curriculum?grade_level_id=${section?.grade_level_id}`),
  })
  const subjects = Object.values(curriculum.data?.data ?? {}).flat().filter((r) => r.is_active)

  const ready = !!section && subjectId !== ''
  const grid = useQuery({
    queryKey: ['grid', sectionId, subjectId, term],
    enabled: ready,
    queryFn: () => api<GradeGrid>('GET', `/grades?section_id=${sectionId}&subject_id=${subjectId}&term=${term}`),
  })

  const annual = term === '0'
  const kinds = annual ? ANNUAL_KINDS : TERM_KINDS
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

  const rows = grid.data?.rows ?? []
  const locked = grid.data?.locked ?? false
  const mts = rows.map((r) => r.mt).filter((v): v is number => v !== null)
  const filled = rows.filter((r) => r.mac !== null && r.npp !== null && r.npt !== null).length
  const negatives = mts.filter((v) => v < passMark).length
  const average = mts.length ? fmt(mts.reduce((a, b) => a + b, 0) / mts.length) : '—'

  const firstInvalid = rows
    .flatMap((r, i) => kinds.map((k) => ({ row: i + 1, kind: k, raw: edits[cell(r.enrollment_id, k)] })))
    .find((c) => c.raw !== undefined && !parseGrade(c.raw, max).ok)

  const termLabel = annual ? 'Exame e recurso' : `Pauta do ${term}.º trimestre`

  return (
    <>
      <PageHead
        title={ready ? termLabel : 'Lançamento de notas'}
        badge={grid.data && <Badge tone={locked ? undefined : 'ok'}>{locked ? 'Fechada' : 'Aberta'}</Badge>}
        subtitle={level && level.max_score ? `${level.name} · escala 0–${level.max_score} · nota mínima ${level.pass_mark}` : 'Escolha a turma, a disciplina e o período'}
        actions={
          grid.data && (
            <>
              <button disabled={Object.keys(edits).length === 0 || save.isPending || locked} onClick={() => save.mutate()}>
                Guardar notas
              </button>
              <button className={locked ? '' : 'primary'} onClick={() => lock.mutate(!locked)}>
                <Icon name="lock" />
                {locked ? 'Reabrir pauta' : 'Fechar pauta'}
              </button>
            </>
          )
        }
      />

      <div className="grid">
        <Field label="Turma">
          <select
            aria-label="Turma"
            value={sectionId}
            onChange={(e) => {
              setSectionId(e.target.value)
              setSubjectId('')
              setEdits({})
            }}
          >
            <option value="">Turma</option>
            {sections.data?.data.map((s) => (
              <option key={s.id} value={s.id}>
                Classe {s.grade_level_id} · {s.name}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Disciplina">
          <select
            aria-label="Disciplina"
            value={subjectId}
            disabled={!section}
            onChange={(e) => {
              setSubjectId(e.target.value)
              setEdits({})
            }}
          >
            <option value="">Disciplina</option>
            {subjects.map((r) => (
              <option key={r.subject_id} value={r.subject_id}>
                {r.subject.code} · {r.subject.name}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Período">
          <select
            aria-label="Período"
            value={term}
            onChange={(e) => {
              setTerm(e.target.value)
              setEdits({})
            }}
          >
            <option value="1">1.º trimestre</option>
            <option value="2">2.º trimestre</option>
            <option value="3">3.º trimestre</option>
            <option value="0">Exame / recurso (6.ª e 9.ª)</option>
          </select>
        </Field>
        {grid.data && !annual && (
          <div className="stats compact">
            <Stat label="Notas lançadas" value={`${filled} / ${rows.length}`} />
            <Stat label="MT negativas" value={<span className={negatives ? 'error' : undefined}>{negatives}</span>} />
            <Stat label="Média da turma" value={average} />
          </div>
        )}
      </div>

      <ErrorBanner error={grid.error ?? save.error ?? lock.error} />

      {grid.data && (
        <section className="card flush">
          <div className="table-wrap">
            <table>
              <caption>
                {annual
                  ? 'NE — nota do exame nacional · NER — nota do exame de recurso (só 6.ª e 9.ª classe) · vazio ou 0 = sem nota'
                  : 'MAC — avaliação contínua · NPP — prova do professor · NPT — prova trimestral · MT — média calculada com os componentes preenchidos (vazio = sem nota)'}
              </caption>
              <thead>
                <tr>
                  <th style={{ width: 48 }}>Nº</th>
                  <th>Aluno</th>
                  {kinds.map((k) => (
                    <th key={k} className="center" style={{ width: 96 }}>
                      {LABEL[k]}
                    </th>
                  ))}
                  {!annual && (
                    <th className="center" style={{ width: 120 }}>
                      MT
                    </th>
                  )}
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.enrollment_id}>
                    <td className="mono muted">{row.roll_number}</td>
                    <td>
                      <strong>{row.student_name}</strong>
                    </td>
                    {kinds.map((k) => {
                      const key = cell(row.enrollment_id, k)
                      const raw = edits[key] ?? (row[k] === null ? '' : String(row[k]).replace('.', ','))
                      const parsed = parseGrade(raw, max)
                      return (
                        <td key={k} className="center">
                          <input
                            aria-label={`${LABEL[k]} de ${row.student_name}`}
                            className={`grade-input${parsed.ok && isNegative(parsed.value, passMark) ? ' negative' : ''}`}
                            inputMode="decimal"
                            value={raw}
                            disabled={locked}
                            aria-invalid={!parsed.ok}
                            onChange={(e) => setEdits({ ...edits, [key]: e.target.value })}
                          />
                        </td>
                      )
                    })}
                    {!annual && (
                      <td className="center">
                        <span className={`pill${row.mt === null ? '' : row.mt < passMark ? ' bad' : ' ok'}`}>{row.mt === null ? '—' : `${fmt(row.mt)}${row.mt < passMark ? ' neg.' : ''}`}</span>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {(firstInvalid || locked) && (
            <div className="table-foot warn">
              <span>
                {firstInvalid ? (
                  <>
                    <strong>Valor fora da escala:</strong> linha {firstInvalid.row}, {LABEL[firstInvalid.kind]} = {firstInvalid.raw}. A escala é de 0 a {max}.
                  </>
                ) : (
                  'A pauta está fechada: reabra-a para alterar notas.'
                )}
              </span>
            </div>
          )}
        </section>
      )}
    </>
  )
}
