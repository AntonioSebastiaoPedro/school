import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { Icon, type IconName } from '../components/Icon'
import { useAuth } from '../modules/auth/AuthProvider'

const ACADEMIC: { to: string; label: string; icon: IconName }[] = [
  { to: '/alunos', label: 'Alunos e encarregados', icon: 'people' },
  { to: '/matriculas', label: 'Matrículas', icon: 'enroll' },
  { to: '/estrutura', label: 'Anos e turmas', icon: 'classes' },
  { to: '/notas', label: 'Lançamento de notas', icon: 'grade' },
  { to: '/pautas', label: 'Pautas', icon: 'sheet' },
]

const TITLES: Record<string, string> = {
  '/': 'Painel',
  '/alunos': 'Alunos e encarregados',
  '/matriculas': 'Matrículas',
  '/estrutura': 'Anos e turmas',
  '/notas': 'Lançamento de notas',
  '/pautas': 'Pautas',
  '/admin/adesoes': 'Pedidos de adesão',
}

const ROLES: Record<string, string> = {
  owner: 'Proprietário',
  director: 'Direcção',
  secretary: 'Secretaria',
  teacher: 'Professor',
  finance: 'Financeiro',
  cashier: 'Caixa',
  trainer: 'Formador',
}

function initials(name: string): string {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0].toUpperCase()).join('')
}

export function Layout() {
  const { me, institutionId, selectInstitution, logout } = useAuth()
  const { pathname } = useLocation()
  if (!me) return null

  const institutions = me.institutions
  const admin = pathname.startsWith('/admin')
  const current = institutions.find((i) => String(i.institution_id) === institutionId)
  const roleLabel = admin ? 'Administrador SaaS' : current ? (ROLES[current.role] ?? current.role) : ''

  return (
    <div className={`shell${admin ? ' admin' : ''}`}>
      <nav className="sidebar" aria-label="Navegação principal">
        <div className="brand">
          <div className="brand-mark">PE</div>
          <div>
            Plataforma Educativa
            {admin && <small>Administração SaaS</small>}
          </div>
        </div>

        {!admin && institutions.length > 1 && (
          <select className="switcher" aria-label="Instituição" value={institutionId ?? ''} onChange={(e) => selectInstitution(e.target.value)}>
            <option value="" disabled>
              Escolha a instituição
            </option>
            {institutions.map((i) => (
              <option key={i.institution_id} value={i.institution_id}>
                {i.institution_name}
              </option>
            ))}
          </select>
        )}
        {!admin && institutions.length === 1 && (
          <div className="switcher switcher-static">
            <span>{institutions[0].institution_name}</span>
            <small>{roleLabel}</small>
          </div>
        )}

        <div className="nav">
          {institutions.length > 0 && (
            <>
              <NavLink to="/" end>
                <Icon name="home" />
                Painel
              </NavLink>
              <div className="nav-group">Académico</div>
              {ACADEMIC.map((item) => (
                <NavLink key={item.to} to={item.to}>
                  <Icon name={item.icon} />
                  {item.label}
                </NavLink>
              ))}
            </>
          )}
          {me.is_platform_admin && (
            <>
              <div className="nav-group">Plataforma</div>
              <NavLink to="/admin/adesoes">
                <Icon name="doc" />
                Pedidos de adesão
              </NavLink>
            </>
          )}
        </div>
      </nav>

      <div className="shell-main">
        <header className="topbar">
          <nav aria-label="Caminho" className="crumbs">
            <span>{admin ? 'Administração' : (current?.institution_name ?? 'Plataforma')}</span>
            <span aria-hidden="true">/</span>
            <strong>{TITLES[pathname] ?? ''}</strong>
          </nav>
          <div className="user">
            <div className="avatar" aria-hidden="true">
              {initials(me.name)}
            </div>
            <div className="user-name">
              <span>{me.name}</span>
              <small>{roleLabel}</small>
            </div>
            <button className="small" onClick={() => void logout()}>
              Sair
            </button>
          </div>
        </header>
        <main className="content">{admin || institutionId ? <Outlet /> : <p>Escolha uma instituição para continuar.</p>}</main>
      </div>
    </div>
  )
}
