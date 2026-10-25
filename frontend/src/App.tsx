import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { Layout } from './app/Layout'
import { AuthProvider, useAuth } from './modules/auth/AuthProvider'
import { LoginPage } from './modules/auth/LoginPage'
import { GradesPage } from './modules/grades/GradesPage'
import { SheetPage } from './modules/grades/SheetPage'
import { EnrollmentsPage } from './modules/school/EnrollmentsPage'
import { StructurePage } from './modules/school/StructurePage'
import { StudentsPage } from './modules/students/StudentsPage'

const queryClient = new QueryClient({ defaultOptions: { queries: { refetchOnWindowFocus: false } } })

function Routing() {
  const { me, loading } = useAuth()
  if (loading) return <p>A carregar…</p>
  if (!me) return <LoginPage />

  return (
    <Routes>
      <Route element={<Layout />}>
        <Route path="/alunos" element={<StudentsPage />} />
        <Route path="/estrutura" element={<StructurePage />} />
        <Route path="/matriculas" element={<EnrollmentsPage />} />
        <Route path="/notas" element={<GradesPage />} />
        <Route path="/pautas" element={<SheetPage />} />
        <Route path="*" element={<Navigate to="/alunos" replace />} />
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
