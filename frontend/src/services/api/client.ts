export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]>

  constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }

  /** Primeira mensagem de erro de um campo, se existir. */
  field(name: string): string | undefined {
    return this.errors[name]?.[0]
  }
}

const KEY = 'institutionId'

function read(): string | null {
  try {
    return localStorage.getItem(KEY)
  } catch {
    return null
  }
}

let institutionId: string | null = read()

export function getInstitutionId(): string | null {
  return institutionId
}

/** A instituição activa é só uma escolha do utilizador: o servidor valida-a contra as suas associações. */
export function setInstitutionId(id: string | null): void {
  institutionId = id
  try {
    if (id === null) localStorage.removeItem(KEY)
    else localStorage.setItem(KEY, id)
  } catch {
    /* armazenamento indisponível: fica só em memória */
  }
}

function xsrfToken(): string | undefined {
  const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : undefined
}

export async function ensureCsrfCookie(): Promise<void> {
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

export async function api<T>(method: Method, path: string, body?: unknown, extraHeaders: Record<string, string> = {}): Promise<T> {
  if (method !== 'GET' && !xsrfToken()) await ensureCsrfCookie()

  const token = xsrfToken()
  const headers: Record<string, string> = { Accept: 'application/json', ...extraHeaders }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (token) headers['X-XSRF-TOKEN'] = token
  if (institutionId) headers['X-Institution-Id'] = institutionId

  const response = await fetch(`/api/v1${path}`, {
    method,
    credentials: 'include',
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })

  if (response.status === 204) return undefined as T

  const data = (await response.json().catch(() => null)) as { message?: string; errors?: Record<string, string[]> } | null

  if (!response.ok) {
    throw new ApiError(response.status, data?.message ?? 'Ocorreu um erro inesperado.', data?.errors ?? {})
  }

  return data as T
}
