import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useRef, useState } from 'react'
import { Badge, ErrorBanner, Field, fieldError, Notice, PageHead } from '../../components/ui'
import { api, ApiError } from '../../services/api/client'
import { METHODS, type Charge, type Paginated, type Payment, type PaymentMethod, type Student } from '../../services/api/types'
import { REVERSE_ROLES, useAuth, useRole } from '../auth/AuthProvider'
import { allocateOldestFirst, formatKz, fromApi, parseKz, toApi } from './money'

const fmt = (cents: number) => `${formatKz(cents)} Kz`
const dueLabel = (c: Charge) => (c.due_date ? `${c.due_date.slice(8)}/${c.due_date.slice(5, 7)}${c.overdue ? ' · vencida' : ''}` : '—')

export function PaymentsPage() {
  const queryClient = useQueryClient()
  const { me, institutionId } = useAuth()
  const role = useRole()
  const [studentId, setStudentId] = useState('')
  const [search, setSearch] = useState('')
  const [amountText, setAmountText] = useState('')
  const [method, setMethod] = useState<PaymentMethod>('card_terminal')
  const [reference, setReference] = useState('')
  const [manual, setManual] = useState<Record<number, string> | null>(null)
  const [done, setDone] = useState<Payment | null>(null)
  const [reversing, setReversing] = useState<{ id: number; reason: string } | null>(null)

  // Uma chave por intenção de pagamento: repetir o mesmo pedido (rede, duplo clique) reutiliza-a e não duplica.
  const attempt = useRef<{ signature: string; key: string } | null>(null)

  const students = useQuery({ queryKey: ['students', search], queryFn: () => api<Paginated<Student>>('GET', `/students?search=${encodeURIComponent(search)}`) })
  const student = students.data?.data.find((s) => String(s.id) === studentId)

  const charges = useQuery({
    queryKey: ['charges', 'payable', studentId],
    enabled: studentId !== '',
    queryFn: () => api<Paginated<Charge>>('GET', `/charges?student_id=${studentId}&status=open,partially_paid`),
  })
  const history = useQuery({
    queryKey: ['payments', studentId],
    enabled: studentId !== '',
    queryFn: () => api<Paginated<Payment>>('GET', `/payments?student_id=${studentId}`),
  })

  const payable = (charges.data?.data ?? []).map((c) => ({ ...c, cents: fromApi(c.outstanding) }))
  const debt = payable.reduce((sum, c) => sum + c.cents, 0)
  const received = parseKz(amountText) ?? 0

  const auto = allocateOldestFirst(payable.map((c) => ({ id: c.id, due_date: c.due_date, outstanding: c.cents })), received)
  const allocation: Record<number, number> = manual
    ? Object.fromEntries(Object.entries(manual).map(([id, text]) => [Number(id), parseKz(text) ?? 0]))
    : auto
  const allocated = Object.values(allocation).reduce((a, b) => a + b, 0)
  const overAllocated = payable.some((c) => (allocation[c.id] ?? 0) > c.cents)
  const canConfirm = received > 0 && allocated === received && !overAllocated

  const edit = (id: number, text: string) => {
    const base = manual ?? Object.fromEntries(Object.entries(auto).map(([k, v]) => [k, toApi(v).replace('.', ',')]))
    setManual({ ...base, [id]: text })
  }
  const toggle = (id: number, on: boolean) => {
    const base = manual ?? Object.fromEntries(Object.entries(auto).map(([k, v]) => [k, toApi(v).replace('.', ',')]))
    const next = { ...base }
    if (on) next[id] = toApi(Math.max(0, Math.min(payable.find((c) => c.id === id)?.cents ?? 0, received - allocated))).replace('.', ',')
    else delete next[id]
    setManual(next)
  }

  const body = {
    student_id: Number(studentId),
    amount: toApi(received),
    method,
    reference: reference || null,
    allocations: Object.entries(allocation).filter(([, v]) => v > 0).map(([id, v]) => ({ charge_id: Number(id), amount: toApi(v) })),
  }

  const confirm = useMutation({
    mutationFn: () => {
      const signature = JSON.stringify(body)
      if (attempt.current?.signature !== signature) attempt.current = { signature, key: crypto.randomUUID() }
      return api<{ data: Payment; replayed: boolean }>('POST', '/payments', body, { 'Idempotency-Key': attempt.current.key })
    },
    onSuccess: async (result) => {
      setDone(result.data)
      attempt.current = null
      setAmountText('')
      setReference('')
      setManual(null)
      await queryClient.invalidateQueries({ queryKey: ['charges'] })
      await queryClient.invalidateQueries({ queryKey: ['payments'] })
      await queryClient.invalidateQueries({ queryKey: ['finance-summary'] })
    },
  })

  const reverse = useMutation({
    mutationFn: () => api('POST', `/payments/${reversing?.id}/reverse`, { reason: reversing?.reason }),
    onSuccess: async () => {
      setReversing(null)
      await queryClient.invalidateQueries({ queryKey: ['charges'] })
      await queryClient.invalidateQueries({ queryKey: ['payments'] })
      await queryClient.invalidateQueries({ queryKey: ['finance-summary'] })
    },
  })

  const institution = me?.institutions.find((i) => String(i.institution_id) === institutionId)?.institution_name
  const lines = payable.filter((c) => (allocation[c.id] ?? 0) > 0)

  return (
    <>
      <PageHead title="Registar pagamento" />

      {done && (
        <Notice tone="ok" icon="check">
          <strong>Pagamento registado.</strong> Recibo <span className="mono">{done.receipt?.receipt_code}</span> emitido · {fmt(fromApi(done.amount))}.
        </Notice>
      )}

      <div className="cols">
        <div className="main" style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          <section className="card">
            <div className="grid">
              <Field label="Pesquisar aluno">
                <input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Nome ou nº de processo" />
              </Field>
              <Field label="Aluno">
                <select
                  aria-label="Aluno"
                  value={studentId}
                  onChange={(e) => {
                    setStudentId(e.target.value)
                    setManual(null)
                    setDone(null)
                  }}
                >
                  <option value="">Escolha o aluno</option>
                  {students.data?.data.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.student_number} · {s.first_name} {s.last_name}
                    </option>
                  ))}
                </select>
              </Field>
            </div>
            {student && (
              <div className="row-between" style={{ display: 'flex', justifyContent: 'space-between', gap: 16, flexWrap: 'wrap' }}>
                <div>
                  <strong style={{ fontSize: 16 }}>
                    {student.first_name} {student.last_name}
                  </strong>
                  <div className="muted" style={{ fontSize: 13 }}>
                    Nº de processo <span className="mono">{student.student_number}</span>
                  </div>
                </div>
                <div className="right">
                  <div className="muted" style={{ fontSize: 12 }}>
                    Saldo em dívida
                  </div>
                  <div style={{ fontSize: 22, fontWeight: 700 }} className={debt > 0 ? 'error' : undefined}>
                    {fmt(debt)}
                  </div>
                </div>
              </div>
            )}
          </section>

          {student && (
            <>
              <section className="card">
                <h2 style={{ fontSize: 16 }}>Dados do pagamento</h2>
                <div className="fields">
                  <Field label="Valor recebido (Kz)" error={fieldError(confirm.error, 'amount') ?? (amountText && parseKz(amountText) === null ? 'Valor inválido (máx. 2 casas decimais).' : undefined)}>
                    <input aria-label="Valor recebido" inputMode="decimal" value={amountText} onChange={(e) => { setAmountText(e.target.value); setManual(null) }} placeholder="0,00" style={{ fontWeight: 700, fontSize: 16 }} />
                  </Field>
                  <Field label="Método" error={fieldError(confirm.error, 'method')}>
                    <select aria-label="Método" value={method} onChange={(e) => setMethod(e.target.value as PaymentMethod)}>
                      {Object.entries(METHODS).map(([value, label]) => (
                        <option key={value} value={value}>
                          {label}
                        </option>
                      ))}
                    </select>
                  </Field>
                  <Field label="Referência / talão">
                    <input value={reference} onChange={(e) => setReference(e.target.value)} />
                  </Field>
                </div>
              </section>

              <section className="card flush">
                <div className="row-between" style={{ display: 'flex', justifyContent: 'space-between', padding: '16px 20px', flexWrap: 'wrap', gap: 8 }}>
                  <h2 style={{ fontSize: 16 }}>Alocar às cobranças em aberto</h2>
                  <span className="muted" style={{ fontSize: 13 }}>
                    {manual ? <button className="small ghost" onClick={() => setManual(null)}>Alocar automaticamente</button> : 'Alocação automática: mais antigas primeiro'}
                  </span>
                </div>
                <div className="table-wrap">
                  <table>
                    <thead>
                      <tr>
                        <th style={{ width: 40 }}><span className="sr-only">Seleccionar</span></th>
                        <th>Cobrança</th>
                        <th>Vencimento</th>
                        <th className="right">Em dívida</th>
                        <th className="right">A alocar</th>
                        <th>Resultado</th>
                      </tr>
                    </thead>
                    <tbody>
                      {payable.map((c) => {
                        const value = allocation[c.id] ?? 0
                        const text = manual ? (manual[c.id] ?? '') : value > 0 ? toApi(value).replace('.', ',') : ''
                        return (
                          <tr key={c.id} className={value > 0 ? undefined : 'muted'}>
                            <td>
                              <input type="checkbox" className="checkbox" aria-label={c.description} checked={value > 0 || (manual !== null && c.id in manual)} onChange={(e) => toggle(c.id, e.target.checked)} />
                            </td>
                            <td><strong>{c.description}</strong></td>
                            <td><span className={c.overdue ? 'error' : undefined} style={{ fontWeight: c.overdue ? 600 : 400 }}>{dueLabel(c)}</span></td>
                            <td className="right">{formatKz(c.cents)}</td>
                            <td className="right">
                              <input className="amount-input" aria-label={`Valor a alocar: ${c.description}`} inputMode="decimal" value={text} onChange={(e) => edit(c.id, e.target.value)} aria-invalid={value > c.cents} />
                            </td>
                            <td>
                              {value === 0 ? 'Não alocada' : value === c.cents ? <Badge tone="ok">Liquidada</Badge> : <Badge tone="warn">Parcial · faltam {formatKz(c.cents - value)}</Badge>}
                            </td>
                          </tr>
                        )
                      })}
                      {payable.length === 0 && !charges.isLoading && (
                        <tr><td colSpan={6} className="muted" style={{ padding: 16 }}>Este aluno não tem cobranças em aberto.</td></tr>
                      )}
                    </tbody>
                  </table>
                </div>
                <div className="table-foot" style={{ background: 'var(--surface-alt)' }}>
                  <span>
                    Alocado <strong>{fmt(allocated)}</strong> de {fmt(received)} recebidos
                    {received > 0 && allocated !== received && <span className="error"> · diferença {fmt(received - allocated)}</span>}
                  </span>
                  <span>
                    Saldo após pagamento <strong className={debt - allocated > 0 ? 'error' : undefined}>{fmt(Math.max(0, debt - allocated))}</strong>
                  </span>
                </div>
              </section>
            </>
          )}
        </div>

        <aside className="aside">
          <section className="card receipt" aria-label="Pré-visualização do recibo">
            <div className="row">
              <div>
                <strong>{institution}</strong>
              </div>
              <div className="right">
                <div className="muted" style={{ fontSize: 12 }}>Recibo</div>
                <div className="mono">atribuído ao confirmar</div>
              </div>
            </div>
            <hr />
            <dl className="dl">
              <dt>Aluno</dt>
              <dd>{student ? `${student.first_name} ${student.last_name}` : '—'}</dd>
              <dt>Data</dt>
              <dd>{new Date().toLocaleDateString('pt-PT')}</dd>
              <dt>Método</dt>
              <dd>{METHODS[method]}{reference && ` · ${reference}`}</dd>
            </dl>
            <hr />
            {lines.map((c) => (
              <div key={c.id} className="row">
                <span>{c.description}{(allocation[c.id] ?? 0) < c.cents ? ' (parcial)' : ''}</span>
                <span>{formatKz(allocation[c.id] ?? 0)}</span>
              </div>
            ))}
            <div className="row total">
              <span>Total</span>
              <span>{fmt(allocated)}</span>
            </div>
            <div className="muted" style={{ fontSize: 12 }}>Operador: {me?.name}</div>
          </section>

          <Notice tone="info">Um pagamento confirmado não pode ser apagado. Correcções são feitas por estorno, com autorização e motivo.</Notice>
          <ErrorBanner error={confirm.error instanceof ApiError && Object.keys(confirm.error.errors).length > 0 ? null : confirm.error} />
          {confirm.error instanceof ApiError && confirm.error.errors['allocations.0.amount'] && <p role="alert" className="error">{confirm.error.errors['allocations.0.amount'][0]}</p>}
          <button className="primary xl" disabled={!student || !canConfirm || confirm.isPending} onClick={() => confirm.mutate()}>
            Confirmar e emitir recibo
          </button>
        </aside>
      </div>

      {student && (
        <section className="card flush">
          <h2 style={{ padding: '16px 20px', fontSize: 16 }}>Pagamentos deste aluno</h2>
          <div className="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Recibo</th>
                  <th>Data</th>
                  <th>Método</th>
                  <th className="right">Valor</th>
                  <th>Estado</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {history.data?.data.map((p) => (
                  <tr key={p.id}>
                    <td className="mono">{p.receipt?.receipt_code ?? p.payment_code}</td>
                    <td>{new Date(p.paid_at).toLocaleDateString('pt-PT')}</td>
                    <td>{METHODS[p.method]}</td>
                    <td className="right">{formatKz(fromApi(p.amount))}</td>
                    <td><Badge tone={p.status === 'confirmed' ? 'ok' : 'bad'}>{p.status === 'confirmed' ? 'Confirmado' : 'Estornado'}</Badge></td>
                    <td className="right">
                      {p.status === 'confirmed' && role && REVERSE_ROLES.includes(role) && (
                        <button className="small danger" onClick={() => setReversing({ id: p.id, reason: '' })}>Estornar</button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {reversing && (
            <div className="table-foot warn" style={{ alignItems: 'flex-end' }}>
              <label className="field" style={{ flex: 1 }}>
                Motivo do estorno (fica registado)
                <textarea rows={2} value={reversing.reason} onChange={(e) => setReversing({ ...reversing, reason: e.target.value })} />
              </label>
              <div className="actions">
                <button onClick={() => setReversing(null)}>Cancelar</button>
                <button className="danger" disabled={reversing.reason.trim().length < 5 || reverse.isPending} onClick={() => reverse.mutate()}>Confirmar estorno</button>
              </div>
            </div>
          )}
          <ErrorBanner error={reverse.error} />
        </section>
      )}
    </>
  )
}
