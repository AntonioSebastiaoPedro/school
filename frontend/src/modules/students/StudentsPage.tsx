import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { ErrorBanner, Field, fieldError } from '../../components/ui'
import { api } from '../../services/api/client'
import type { Student } from '../../services/api/types'

export function StudentsPage() {
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [form, setForm] = useState({ first_name: '', last_name: '', gender: 'M', birth_date: '' })

  const students = useQuery({
    queryKey: ['students', search],
    queryFn: () => api<{ data: Student[] }>('GET', `/students?search=${encodeURIComponent(search)}`),
  })

  const create = useMutation({
    mutationFn: () => api('POST', '/students', { ...form, birth_date: form.birth_date || null }),
    onSuccess: () => {
      setForm({ first_name: '', last_name: '', gender: 'M', birth_date: '' })
      return queryClient.invalidateQueries({ queryKey: ['students'] })
    },
  })

  function submit(event: FormEvent) {
    event.preventDefault()
    create.mutate()
  }

  return (
    <section>
      <h2>Alunos</h2>
      <input type="search" placeholder="Pesquisar por nome, nº de processo ou documento" value={search} onChange={(e) => setSearch(e.target.value)} />
      <ErrorBanner error={students.error} />
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
              <td>{s.student_number}</td>
              <td>
                {s.first_name} {s.last_name}
              </td>
              <td>{s.gender}</td>
              <td>{s.birth_date ?? '—'}</td>
              <td>{s.status}</td>
            </tr>
          ))}
        </tbody>
      </table>

      <h3>Novo aluno</h3>
      <form onSubmit={submit} className="grid">
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
        <button disabled={create.isPending}>Registar</button>
      </form>
      <ErrorBanner error={create.error && Object.keys((create.error as { errors?: object }).errors ?? {}).length === 0 ? create.error : null} />
    </section>
  )
}
