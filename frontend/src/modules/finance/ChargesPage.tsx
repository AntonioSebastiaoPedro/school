import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Badge, ErrorBanner, Field, fieldError, Notice, PageHead } from '../../components/ui'
import { api } from '../../services/api/client'
import type { Charge, GradeLevel, Paginated, Section } from '../../services/api/types'
import { useRole } from '../auth/AuthProvider'
import { formatKz, fromApi } from './money'

const STATUS: Record<Charge['status'], { label: string; tone?: 'ok' | 'warn' | 'bad' }> = {
  draft: { label: 'Rascunho' },
  open: { label: 'Em aberto', tone: 'warn' },
  partially_paid: { label: 'Parcial', tone: 'warn' },
  paid: { label: 'Paga', tone: 'ok' },
  void: { label: 'Anulada', tone: 'bad' },
  written_off: { label: 'Perdoada' },
}
const FILTERS = { 'open,partially_paid': 'Em aberto', '': 'Todas', paid: 'Pagas', void: 'Anuladas' }
const CATEGORIES = { tuition: 'Propina', enrollment_fee: 'Matrícula', uniform: 'Uniforme', other: 'Outra' }

export function ChargesPage() {
  const queryClient = useQueryClient()
  const role = useRole()
  const canManage = role !== null && ['owner', 'director', 'finance'].includes(role)
  const [filter, setFilter] = useState<keyof typeof FILTERS>('open,partially_paid')
  const [form, setForm] = useState({ section_id: '', category: 'tuition', description: '', amount: '', due_date: '' })
  const [summary, setSummary] = useState<{ created: number; skipped: number } | null>(null)

  const charges = useQuery({ queryKey: ['charges', 'list', filter], queryFn: () => api<Paginated<Charge>>('GET', `/charges?status=${filter}`) })
  const sections = useQuery({ queryKey: ['sections'], queryFn: () => api<{ data: Section[] }>('GET', '/sections') })
  const levels = useQuery({ queryKey: ['levels'], queryFn: () => api<{ data: GradeLevel[] }>('GET', '/grade-levels') })
  const levelName = (id: number) => levels.data?.data.find((l) => l.id === id)?.name ?? `Classe ${id}`

  const refresh = () => Promise.all([queryClient.invalidateQueries({ queryKey: ['charges'] }), queryClient.invalidateQueries({ queryKey: ['finance-summary'] })])

  const bulk = useMutation({
    mutationFn: () => api<{ created: number; skipped: number }>('POST', '/charges/bulk', { ...form, section_id: Number(form.section_id), amount: form.amount.replace(',', '.'), due_date: form.due_date || null }),
    onSuccess: (r) => {
      setSummary(r)
      return refresh()
    },
  })
  const voidCharge = useMutation({ mutationFn: (id: number) => api('POST', `/charges/${id}/void`), onSuccess: refresh })

  const rows = charges.data?.data ?? []
  const totalOutstanding = rows.reduce((sum, c) => sum + fromApi(c.outstanding), 0)

  return (
    <>
      <PageHead title="Cobranças e dívidas" subtitle={charges.data ? `${charges.data.meta.total} cobranças · em dívida nesta lista: ${formatKz(totalOutstanding)} Kz` : undefined} />

      <section className="card flush">
        <div className="tabs" role="tablist">
          {Object.entries(FILTERS).map(([value, label]) => (
            <button key={value} role="tab" aria-selected={filter === value} onClick={() => setFilter(value as keyof typeof FILTERS)}>
              {label}
            </button>
          ))}
        </div>
        <ErrorBanner error={charges.error ?? voidCharge.error} />
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Código</th>
                <th>Aluno</th>
                <th>Descrição</th>
                <th>Vencimento</th>
                <th className="right">Valor</th>
                <th className="right">Em dívida</th>
                <th>Estado</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {rows.map((c) => (
                <tr key={c.id}>
                  <td className="mono">{c.code}</td>
                  <td><strong>{c.student_name}</strong></td>
                  <td>{c.description}</td>
                  <td className={c.overdue ? 'error' : undefined}>{c.due_date ?? '—'}{c.overdue && ' · vencida'}</td>
                  <td className="right">{formatKz(fromApi(c.amount))}</td>
                  <td className="right"><strong>{formatKz(fromApi(c.outstanding))}</strong></td>
                  <td><Badge tone={STATUS[c.status].tone}>{STATUS[c.status].label}</Badge></td>
                  <td className="right">
                    {canManage && ['open', 'partially_paid'].includes(c.status) && fromApi(c.paid) === 0 && (
                      <button className="small danger" onClick={() => voidCharge.mutate(c.id)}>Anular</button>
                    )}
                  </td>
                </tr>
              ))}
              {rows.length === 0 && !charges.isLoading && (
                <tr><td colSpan={8} className="muted" style={{ padding: 16 }}>Sem cobranças neste filtro.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </section>

      {canManage && (
        <form
          className="card"
          onSubmit={(e) => {
            e.preventDefault()
            setSummary(null)
            bulk.mutate()
          }}
        >
          <h2>Gerar cobrança para uma turma</h2>
          <div className="fields">
            <Field label="Turma" error={fieldError(bulk.error, 'section_id')}>
              <select aria-label="Turma" value={form.section_id} onChange={(e) => setForm({ ...form, section_id: e.target.value })} required>
                <option value="">Escolha a turma</option>
                {sections.data?.data.map((s) => (
                  <option key={s.id} value={s.id}>{levelName(s.grade_level_id)} · {s.name}</option>
                ))}
              </select>
            </Field>
            <Field label="Categoria">
              <select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })}>
                {Object.entries(CATEGORIES).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
              </select>
            </Field>
            <Field label="Descrição" error={fieldError(bulk.error, 'description')}>
              <input value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} placeholder="Propina · Novembro" required />
            </Field>
            <Field label="Valor (Kz)" error={fieldError(bulk.error, 'amount')}>
              <input inputMode="decimal" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} required />
            </Field>
            <Field label="Vencimento" error={fieldError(bulk.error, 'due_date')}>
              <input type="date" value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} />
            </Field>
          </div>
          <div className="actions" style={{ justifyContent: 'flex-end' }}>
            <button className="primary" disabled={bulk.isPending}>Gerar cobranças</button>
          </div>
          {summary && <Notice tone="ok" icon="check">{summary.created} cobranças criadas{summary.skipped > 0 && `, ${summary.skipped} já existiam`}. Só alunos com matrícula activa são incluídos.</Notice>}
          <ErrorBanner error={bulk.error && Object.keys((bulk.error as { errors?: object }).errors ?? {}).length === 0 ? bulk.error : null} />
        </form>
      )}
    </>
  )
}
