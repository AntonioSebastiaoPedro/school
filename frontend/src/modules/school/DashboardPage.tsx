import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { ErrorBanner, Meter, Notice, PageHead, Stat } from '../../components/ui'
import { Icon } from '../../components/Icon'
import { api } from '../../services/api/client'
import type { Enrollment, GradeLevel, Paginated, SchoolYear, Section, Student } from '../../services/api/types'

export function DashboardPage() {
  const students = useQuery({ queryKey: ['students-count'], queryFn: () => api<Paginated<Student>>('GET', '/students?status=active') })
  const pending = useQuery({ queryKey: ['pending-enrollments'], queryFn: () => api<Paginated<Enrollment>>('GET', '/enrollments?status=pending') })
  const sections = useQuery({ queryKey: ['sections'], queryFn: () => api<{ data: Section[] }>('GET', '/sections') })
  const years = useQuery({ queryKey: ['years'], queryFn: () => api<{ data: SchoolYear[] }>('GET', '/school-years') })
  const levels = useQuery({ queryKey: ['levels'], queryFn: () => api<{ data: GradeLevel[] }>('GET', '/grade-levels') })

  const year = years.data?.data.find((y) => y.status === 'active')
  const yearSections = sections.data?.data.filter((s) => !year || s.school_year_id === year.id) ?? []
  const capacity = yearSections.reduce((sum, s) => sum + s.capacity, 0)
  const enrolled = yearSections.reduce((sum, s) => sum + (s.enrolled ?? 0), 0)
  const nearFull = yearSections.filter((s) => s.capacity > 0 && (s.enrolled ?? 0) / s.capacity >= 0.9)
  const levelName = (id: number) => levels.data?.data.find((l) => l.id === id)?.name ?? `Classe ${id}`

  return (
    <>
      <PageHead
        title="Painel"
        subtitle={year ? `Ano lectivo ${year.name}` : 'Sem ano lectivo activo'}
        actions={
          <Link className="btn primary" to="/matriculas">
            <Icon name="plus" />
            Nova matrícula
          </Link>
        }
      />
      <ErrorBanner error={students.error ?? pending.error ?? sections.error} />

      <section aria-label="Indicadores" className="stats">
        <Stat label="Alunos activos" value={students.data?.meta.total ?? '—'} />
        <Stat
          label="Matrículas pendentes"
          value={pending.data?.meta.total ?? '—'}
          hint={pending.data && pending.data.meta.total > 0 ? 'Aguardam aprovação' : undefined}
          tone="warn"
        />
        <Stat label="Turmas" value={yearSections.length} hint={`${enrolled} de ${capacity} lugares ocupados`} />
        <Stat label="Turmas quase lotadas" value={nearFull.length} hint={nearFull.length > 0 ? 'Acima de 90% da capacidade' : undefined} tone="warn" />
      </section>

      <div className="cols">
        <section className="card main">
          <h2>Ocupação das turmas</h2>
          {yearSections.length === 0 && <p className="muted">Ainda não há turmas neste ano lectivo.</p>}
          {yearSections.map((s) => (
            <div key={s.id} className="bars">
              <span style={{ width: 150 }}>
                {levelName(s.grade_level_id)} · {s.name}
              </span>
              <Meter value={s.enrolled ?? 0} max={s.capacity} />
              <span className="mono" style={{ width: 64, textAlign: 'right' }}>
                {s.enrolled ?? 0}/{s.capacity}
              </span>
            </div>
          ))}
        </section>

        <section className="card aside">
          <h2>Requer atenção</h2>
          {pending.data && pending.data.meta.total > 0 && (
            <Notice tone="warn">
              <strong>{pending.data.meta.total} matrículas por aprovar</strong>
              <div>
                <Link to="/matriculas">Abrir matrículas</Link>
              </div>
            </Notice>
          )}
          {nearFull.map((s) => (
            <Notice key={s.id}>
              <strong>
                Turma {levelName(s.grade_level_id)} {s.name} com {s.enrolled} de {s.capacity} lugares
              </strong>
            </Notice>
          ))}
          {!year && <Notice tone="warn">Não há ano lectivo activo. Active um em «Anos e turmas».</Notice>}
          {year && pending.data?.meta.total === 0 && nearFull.length === 0 && <p className="muted">Nada pendente.</p>}
        </section>
      </div>
    </>
  )
}
