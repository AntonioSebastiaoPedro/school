import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../modules/auth/AuthProvider'

export function Layout() {
  const { me, institutionId, selectInstitution, logout } = useAuth()
  const institutions = me?.institutions ?? []

  return (
    <>
      <header className="topbar">
        <strong>Gestão Educativa</strong>
        <nav>
          <NavLink to="/alunos">Alunos</NavLink>
          <NavLink to="/estrutura">Anos e turmas</NavLink>
          <NavLink to="/matriculas">Matrículas</NavLink>
          <NavLink to="/notas">Notas</NavLink>
          <NavLink to="/pautas">Pautas</NavLink>
        </nav>
        <span className="spacer" />
        {institutions.length > 1 && (
          <select aria-label="Instituição" value={institutionId ?? ''} onChange={(e) => selectInstitution(e.target.value)}>
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
        {institutions.length === 1 && <span>{institutions[0].institution_name}</span>}
        <button onClick={() => void logout()}>Sair</button>
      </header>
      <main>{institutionId ? <Outlet /> : <p>Escolha uma instituição para continuar.</p>}</main>
    </>
  )
}
