import { useQuery, useQueryClient } from '@tanstack/react-query'
import { createContext, useCallback, useContext, useEffect, type ReactNode } from 'react'
import { api, ApiError, getInstitutionId, setInstitutionId } from '../../services/api/client'
import type { Me } from '../../services/api/types'

interface AuthState {
  me: Me | null
  loading: boolean
  institutionId: string | null
  selectInstitution: (id: string) => void
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()

  const { data: me, isLoading } = useQuery({
    queryKey: ['me'],
    queryFn: async () => {
      try {
        return (await api<{ data: Me }>('GET', '/auth/me')).data
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) return null
        throw error
      }
    },
    retry: false,
  })

  // Uma única instituição fica seleccionada sozinha; uma escolha antiga inválida é descartada.
  useEffect(() => {
    if (!me) return
    const ids = me.institutions.map((i) => String(i.institution_id))
    const current = getInstitutionId()
    if (current && !ids.includes(current)) setInstitutionId(null)
    if (!getInstitutionId() && ids.length === 1) {
      setInstitutionId(ids[0])
      void queryClient.invalidateQueries()
    }
  }, [me, queryClient])

  const login = useCallback(
    async (email: string, password: string) => {
      await api('POST', '/auth/login', { email, password })
      await queryClient.invalidateQueries({ queryKey: ['me'] })
    },
    [queryClient],
  )

  const logout = useCallback(async () => {
    await api('POST', '/auth/logout')
    setInstitutionId(null)
    queryClient.clear()
    await queryClient.invalidateQueries({ queryKey: ['me'] })
  }, [queryClient])

  const selectInstitution = useCallback(
    (id: string) => {
      setInstitutionId(id)
      // Os dados em cache pertencem à instituição anterior.
      queryClient.removeQueries({ predicate: (q) => q.queryKey[0] !== 'me' })
      void queryClient.invalidateQueries()
    },
    [queryClient],
  )

  return (
    <AuthContext.Provider
      value={{ me: me ?? null, loading: isLoading, institutionId: getInstitutionId(), selectInstitution, login, logout }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth fora do AuthProvider')
  return ctx
}

/** Papel do utilizador na instituição activa. Só serve para mostrar ou esconder opções: o servidor decide. */
export function useRole(): string | null {
  const { me, institutionId } = useAuth()
  return me?.institutions.find((i) => String(i.institution_id) === institutionId)?.role ?? null
}

export const FINANCE_ROLES = ['owner', 'director', 'finance', 'cashier']
export const REVERSE_ROLES = ['owner', 'director', 'finance']
