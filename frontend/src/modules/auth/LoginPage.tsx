import { useState, type FormEvent } from 'react'
import { ErrorBanner, Field, fieldError } from '../../components/ui'
import { useAuth } from './AuthProvider'

export function LoginPage() {
  const { login } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)

  async function submit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await login(email, password)
    } catch (e) {
      setError(e)
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="narrow">
      <h1>Gestão Educativa</h1>
      <form onSubmit={submit}>
        <Field label="E-mail" error={fieldError(error, 'email')}>
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="username" />
        </Field>
        <Field label="Palavra-passe">
          <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required autoComplete="current-password" />
        </Field>
        <button disabled={busy}>Entrar</button>
        <ErrorBanner error={error && !fieldError(error, 'email') ? error : null} />
      </form>
    </main>
  )
}
