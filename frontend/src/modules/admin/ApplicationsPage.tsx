import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Badge, ErrorBanner, Notice, PageHead } from '../../components/ui'
import { api } from '../../services/api/client'
import { INSTITUTION_TYPES, REGIMES, type Application, type Paginated } from '../../services/api/types'

const TABS: { status: Application['status']; label: string }[] = [
  { status: 'pending', label: 'Pendentes' },
  { status: 'info_requested', label: 'Informação pedida' },
  { status: 'approved', label: 'Aprovados' },
  { status: 'rejected', label: 'Rejeitados' },
]

const STATUS_BADGE: Record<Application['status'], { label: string; tone?: 'ok' | 'warn' | 'bad' }> = {
  pending: { label: 'Pendente', tone: 'warn' },
  under_review: { label: 'Em análise', tone: 'warn' },
  info_requested: { label: 'Informação pedida', tone: 'warn' },
  approved: { label: 'Aprovado', tone: 'ok' },
  rejected: { label: 'Rejeitado', tone: 'bad' },
}

const date = (iso: string) => new Date(iso).toLocaleDateString('pt-PT')

export function ApplicationsPage() {
  const queryClient = useQueryClient()
  const [status, setStatus] = useState<Application['status']>('pending')
  const [selected, setSelected] = useState<number | null>(null)
  const [notes, setNotes] = useState('')

  const list = useQuery({
    queryKey: ['applications', status],
    queryFn: () => api<Paginated<Application>>('GET', `/admin/institution-applications?status=${status}`),
  })

  const rows = list.data?.data ?? []
  const current = rows.find((r) => r.id === selected) ?? rows[0]

  const decide = useMutation({
    mutationFn: (decision: 'approve' | 'reject' | 'request_info') =>
      api('POST', `/admin/institution-applications/${current?.id}/decision`, { decision, notes: notes || null }),
    onSuccess: () => {
      setNotes('')
      setSelected(null)
      return queryClient.invalidateQueries({ queryKey: ['applications'] })
    },
  })

  const decided = current && ['approved', 'rejected'].includes(current.status)

  return (
    <>
      <PageHead title="Pedidos de adesão" />

      <div className="cols">
        <section className="card flush" aria-label="Lista de pedidos" style={{ flex: '1 1 320px', minWidth: 0 }}>
          <div role="tablist" className="tabs">
            {TABS.map((t) => (
              <button key={t.status} role="tab" aria-selected={status === t.status} onClick={() => { setStatus(t.status); setSelected(null) }}>
                {t.label}
                {status === t.status && list.data ? ` · ${list.data.meta.total}` : ''}
              </button>
            ))}
          </div>
          {rows.length === 0 && <p className="muted" style={{ padding: 16, margin: 0 }}>Sem pedidos neste estado.</p>}
          {rows.map((r) => (
            <button key={r.id} className={`list-row${current?.id === r.id ? ' selected' : ''}`} onClick={() => setSelected(r.id)}>
              <span className="top">
                <span>{r.proposed_name}</span>
                <small>{date(r.submitted_at)}</small>
              </span>
              <small>
                {INSTITUTION_TYPES[r.institution_type]} · {[r.municipality, r.province].filter(Boolean).join(', ') || 'Sem localização'}
              </small>
            </button>
          ))}
        </section>

        <section style={{ flex: '2 1 560px', minWidth: 0, display: 'flex', flexDirection: 'column', gap: 16 }}>
          <ErrorBanner error={list.error ?? decide.error} />
          {current ? (
            <div className="card">
              <div>
                <div className="title-row">
                  <h2 style={{ fontSize: 22 }}>{current.proposed_name}</h2>
                  <Badge tone={STATUS_BADGE[current.status].tone}>{STATUS_BADGE[current.status].label}</Badge>
                </div>
                <p className="muted" style={{ margin: '4px 0 0' }}>
                  {INSTITUTION_TYPES[current.institution_type]} · pedido <span className="mono">#{current.id}</span> · submetido a {date(current.submitted_at)}
                </p>
              </div>

              <div className="fields">
                <div>
                  <div className="eyebrow">Responsável</div>
                  <strong>{current.applicant_name}</strong>
                  <div className="muted">
                    {current.applicant_email}
                    {current.applicant_phone && ` · ${current.applicant_phone}`}
                  </div>
                </div>
                <div>
                  <div className="eyebrow">Identificação</div>
                  <strong>{current.tax_identifier ? `NIF ${current.tax_identifier}` : 'NIF não indicado'}</strong>
                  <div className="muted">{current.school_profile?.regime ? `Regime ${REGIMES[current.school_profile.regime]?.toLowerCase()}` : '—'}</div>
                </div>
                {current.school_profile && (
                  <div>
                    <div className="eyebrow">Direcção da escola</div>
                    <strong>{current.school_profile.director_name}</strong>
                    <div className="muted">{current.school_profile.school_code ? `Escola nº ${current.school_profile.school_code}` : 'Sem código de escola'}</div>
                  </div>
                )}
              </div>

              <Notice tone="info">Aprovar não activa a subscrição. Depois da aprovação, associe um plano e registe o pagamento comercial para libertar as funções operacionais.</Notice>

              {decided ? (
                current.review_notes && (
                  <div>
                    <div className="eyebrow">Nota da decisão</div>
                    <p style={{ margin: '4px 0 0' }}>{current.review_notes}</p>
                  </div>
                )
              ) : (
                <>
                  <label className="field">
                    Nota da decisão (fica no histórico)
                    <textarea rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Ex.: falta o B.I. do responsável; reenviar em PDF legível." />
                  </label>
                  <div className="actions" style={{ justifyContent: 'flex-end' }}>
                    <button className="danger" disabled={decide.isPending} onClick={() => decide.mutate('reject')}>
                      Rejeitar
                    </button>
                    <button disabled={decide.isPending} onClick={() => decide.mutate('request_info')}>
                      Pedir informações
                    </button>
                    <button className="primary" disabled={decide.isPending} onClick={() => decide.mutate('approve')}>
                      Aprovar instituição
                    </button>
                  </div>
                </>
              )}
            </div>
          ) : (
            !list.isLoading && <p className="muted">Escolha um pedido.</p>
          )}

          {current && (
            <div className="card">
              <h3>Histórico do pedido</h3>
              <ol className="timeline">
                <li>
                  <div>
                    <strong>Pedido submetido pelo formulário público</strong>
                    <div className="muted">
                      {date(current.submitted_at)} · {current.applicant_name}
                    </div>
                  </div>
                </li>
                {current.reviewed_at && (
                  <li>
                    <div>
                      <strong>{STATUS_BADGE[current.status].label}</strong>
                      <div className="muted">{date(current.reviewed_at)}</div>
                    </div>
                  </li>
                )}
              </ol>
            </div>
          )}
        </section>
      </div>
    </>
  )
}
