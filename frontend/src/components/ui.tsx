import type { ReactNode } from 'react'
import { ApiError } from '../services/api/client'
import { Icon, type IconName } from './Icon'

export function ErrorBanner({ error }: { error: unknown }) {
  if (!error) return null
  const message = error instanceof ApiError ? error.message : 'Não foi possível concluir o pedido.'
  return (
    <p role="alert" className="error">
      {message}
    </p>
  )
}

export function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return (
    <label className="field">
      <span>{label}</span>
      {children}
      {error && <small className="error">{error}</small>}
    </label>
  )
}

export function fieldError(error: unknown, name: string): string | undefined {
  return error instanceof ApiError ? error.field(name) : undefined
}

export function PageHead({ title, subtitle, badge, actions }: { title: string; subtitle?: ReactNode; badge?: ReactNode; actions?: ReactNode }) {
  return (
    <div className="page-head">
      <div>
        <div className="title-row">
          <h1>{title}</h1>
          {badge}
        </div>
        {subtitle && <p>{subtitle}</p>}
      </div>
      {actions && <div className="actions">{actions}</div>}
    </div>
  )
}

export function Badge({ tone, children }: { tone?: 'ok' | 'warn' | 'bad'; children: ReactNode }) {
  return <span className={`badge${tone ? ` ${tone}` : ''}`}>{children}</span>
}

export function Stat({ label, value, hint, tone }: { label: string; value: ReactNode; hint?: ReactNode; tone?: 'warn' | 'bad' }) {
  return (
    <div className="stat">
      <span className="muted">{label}</span>
      <span className="value">{value}</span>
      {hint && <span className={`hint${tone ? ` ${tone}` : ''}`}>{hint}</span>}
    </div>
  )
}

export function Notice({ tone, icon = 'info', children }: { tone?: 'ok' | 'info' | 'warn' | 'bad'; icon?: IconName; children: ReactNode }) {
  return (
    <div className={`notice${tone ? ` ${tone}` : ''}`} role={tone === 'ok' ? 'status' : undefined}>
      <Icon name={icon} size={20} />
      <div>{children}</div>
    </div>
  )
}

/** Barra de ocupação de uma turma: laranja quando quase cheia, cinzenta quando lotada. */
export function Meter({ value, max }: { value: number; max: number }) {
  const ratio = max > 0 ? value / max : 0
  const state = ratio >= 1 ? ' full' : ratio >= 0.9 ? ' near' : ''
  return (
    <span className={`meter${state}`} role="img" aria-label={`${value} de ${max} lugares`}>
      <span style={{ width: `${Math.min(100, Math.round(ratio * 100))}%` }} />
    </span>
  )
}
