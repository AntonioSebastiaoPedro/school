import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Badge, ErrorBanner, Field, fieldError, PageHead } from '../../components/ui'
import { ApiError, api } from '../../services/api/client'
import type { Paginated, Student } from '../../services/api/types'

const EMPTY = { first_name: '', last_name: '', gender: 'M', birth_date: '' }

export function StudentsPage() {
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [form, setForm] = useState(EMPTY)

  const students = useQuery({
    queryKey: ['students', search],
    queryFn: () => api<Paginated<Student>>('GET', `/students?search=${encodeURIComponent(search)}`),
  })

  const create = useMutation({
    mutationFn: () => api('POST', '/students', { ...form, birth_date: form.birth_date || null }),
    onSuccess: () => {
      setForm(EMPTY)
      return queryClient.invalidateQueries({ queryKey: ['students'] })
    },
  })

  const generalError = create.error instanceof ApiError && Object.keys(create.error.errors).length > 0 ? null : create.error

  function submit(event: FormEvent) {
    event.preventDefault()
    create.mutate()
  }

  return (
    <>
      <PageHead title="Alunos e encarregados" subtitle={students.data ? `${students.data.meta.total} alunos` : undefined} />

      <section className="card flush">
        <div style={{ padding: 16 }}>
          <input
            type="search"
            style={{ width: '100%' }}
            aria-label="Pesquisar"
            placeholder="Pesquisar por nome, nº de processo ou documento"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        <ErrorBanner error={students.error} />
        <div className="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Nº de processo</th>
                <th>Nome</th>
                <th>Género</th>
                <th>Nascimento</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              {students.data?.data.map((s) => (
                <tr key={s.id}>
                  <td className="mono">{s.student_number}</td>
                  <td>
                    <strong>
                      {s.first_name} {s.last_name}
                    </strong>
                  </td>
                  <td>{s.gender === 'F' ? 'Feminino' : s.gender === 'M' ? 'Masculino' : '—'}</td>
                  <td>{s.birth_date ?? '—'}</td>
                  <td>
                    <Badge tone={s.status === 'active' ? 'ok' : undefined}>{s.status}</Badge>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>

      <form onSubmit={submit} className="card">
        <h2>Novo aluno</h2>
        <div className="fields">
          <Field label="Nome" error={fieldError(create.error, 'first_name')}>
            <input value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} required />
          </Field>
          <Field label="Apelido" error={fieldError(create.error, 'last_name')}>
            <input value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} required />
          </Field>
          <Field label="Género" error={fieldError(create.error, 'gender')}>
            <select value={form.gender} onChange={(e) => setForm({ ...form, gender: e.target.value })}>
              <option value="M">Masculino</option>
              <option value="F">Feminino</option>
            </select>
          </Field>
          <Field label="Data de nascimento" error={fieldError(create.error, 'birth_date')}>
            <input type="date" value={form.birth_date} onChange={(e) => setForm({ ...form, birth_date: e.target.value })} />
          </Field>
        </div>
        <div className="actions" style={{ justifyContent: 'flex-end' }}>
          <button className="primary" disabled={create.isPending}>
            Registar aluno
          </button>
        </div>
        <ErrorBanner error={generalError} />
      </form>
    </>
  )
}
