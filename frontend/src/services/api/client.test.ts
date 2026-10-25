import { api, ApiError, setInstitutionId } from './client'

function mockFetch(responses: Array<{ status: number; body?: unknown }>) {
  const calls: Array<{ url: string; init?: RequestInit }> = []
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: string, init?: RequestInit) => {
      calls.push({ url, init })
      const r = responses.shift() ?? { status: 200, body: {} }
      return { status: r.status, ok: r.status < 400, json: async () => r.body }
    }),
  )
  return calls
}

afterEach(() => {
  vi.unstubAllGlobals()
  setInstitutionId(null)
  document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
})

describe('api client', () => {
  it('envia a instituição activa e as credenciais', async () => {
    const calls = mockFetch([{ status: 200, body: { data: [] } }])
    setInstitutionId('7')

    await api('GET', '/students')

    const headers = calls[0].init?.headers as Record<string, string>
    expect(calls[0].url).toBe('/api/v1/students')
    expect(headers['X-Institution-Id']).toBe('7')
    expect(calls[0].init?.credentials).toBe('include')
  })

  it('obtém o cookie CSRF antes de um pedido que altera dados', async () => {
    const calls = mockFetch([{ status: 204 }, { status: 204 }])

    await api('POST', '/auth/logout')

    expect(calls[0].url).toBe('/sanctum/csrf-cookie')
    expect(calls[1].url).toBe('/api/v1/auth/logout')
  })

  it('devolve ApiError com os erros por campo', async () => {
    mockFetch([{ status: 422, body: { message: 'Dados inválidos.', errors: { gender: ['Obrigatório.'] } } }])

    const error = await api('GET', '/x').catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(422)
    expect((error as ApiError).field('gender')).toBe('Obrigatório.')
  })
})
