import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { Layout } from './app/Layout'
import { ApplicationsPage } from './modules/admin/ApplicationsPage'
import { AuthProvider, useAuth } from './modules/auth/AuthProvider'
import { LoginPage } from './modules/auth/LoginPage'
import { ChargesPage } from './modules/finance/ChargesPage'
import { PaymentsPage } from './modules/finance/PaymentsPage'
import { GradesPage } from './modules/grades/GradesPage'
import { SheetPage } from './modules/grades/SheetPage'
import { DashboardPage } from './modules/school/DashboardPage'
import { EnrollmentsPage } from './modules/school/EnrollmentsPage'
import { StructurePage } from './modules/school/StructurePage'
import { StudentsPage } from './modules/students/StudentsPage'

const queryClient = new QueryClient({ defaultOptions: { queries: { refetchOnWindowFocus: false } } })

function Routing() {
  const { me, loading } = useAuth()
  if (loading) return <p>A carregar…</p>
  if (!me) return <LoginPage />

  // Quem só administra a plataforma (sem escola) começa nos pedidos de adesão.
  const home = me.institutions.length === 0 && me.is_platform_admin ? '/admin/adesoes' : '/'

  return (
    <Routes>
      <Route element={<Layout />}>
        <Route path="/" element={home === '/' ? <DashboardPage /> : <Navigate to={home} replace />} />
        <Route path="/alunos" element={<StudentsPage />} />
        <Route path="/estrutura" element={<StructurePage />} />
        <Route path="/matriculas" element={<EnrollmentsPage />} />
        <Route path="/notas" element={<GradesPage />} />
        <Route path="/pautas" element={<SheetPage />} />
        <Route path="/pagamentos" element={<PaymentsPage />} />
        <Route path="/cobrancas" element={<ChargesPage />} />
        {me.is_platform_admin && <Route path="/admin/adesoes" element={<ApplicationsPage />} />}
        <Route path="*" element={<Navigate to={home} replace />} />
      </Route>
    </Routes>
  )
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <Routing />
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  )
}
