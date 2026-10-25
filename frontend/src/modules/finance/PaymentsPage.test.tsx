import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { PaymentsPage } from './PaymentsPage'

vi.mock('../auth/AuthProvider', () => ({
  useAuth: () => ({ me: { name: 'Rui Kapinga', institutions: [{ institution_id: 1, institution_name: 'Escola Teste', role: 'cashier', status: 'active' }] }, institutionId: '1' }),
  useRole: () => 'cashier',
  REVERSE_ROLES: ['owner', 'director', 'finance'],
}))

const student = { id: 5, student_number: '000005', first_name: 'Isabel', last_name: 'Cassoma', gender: 'F', birth_date: null, status: 'active' }
const charge = (id: number, description: string, due: string) => ({
  id, student_id: 5, student_name: 'Isabel Cassoma', student_number: '000005', enrollment_id: 1, code: `CB-${id}`, description, category: 'tuition',
  status: 'open', amount: '45000.00', paid: '0.00', outstanding: '45000.00', due_date: due, overdue: true,
})
const charges = [charge(3, 'Propina · Novembro', '2026-11-10'), charge(1, 'Propina · Setembro', '2026-09-10'), charge(2, 'Propina · Outubro', '2026-10-10')]

function setup(post: (headers: Record<string, string>, body: unknown) => { status: number; body: unknown }) {
  document.cookie = 'XSRF-TOKEN=t'
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: string, init?: RequestInit) => {
      if (init?.method === 'POST') {
        const r = post(init.headers as Record<string, string>, JSON.parse(String(init.body)))
        return { status: r.status, ok: r.status < 400, json: async () => r.body }
      }
      const data = url.includes('/students') ? [student] : url.includes('/charges') ? charges : []
      return { status: 200, ok: true, json: async () => ({ data, meta: { total: data.length } }) }
    }),
  )
  render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <PaymentsPage />
    </QueryClientProvider>,
  )
}

async function fill(user: ReturnType<typeof userEvent.setup>) {
  await screen.findByRole('option', { name: /Isabel/ })
  await user.selectOptions(screen.getByLabelText('Aluno'), '5')
  await screen.findByText('Propina · Setembro')
  await user.type(screen.getByLabelText('Valor recebido'), '70000,00')
}

afterEach(() => vi.unstubAllGlobals())

describe('PaymentsPage', () => {
  it('aloca automaticamente as mais antigas primeiro e deixa a última parcial', async () => {
    setup(() => ({ status: 201, body: {} }))
    const user = userEvent.setup()
    await fill(user)

    expect(screen.getByLabelText('Valor a alocar: Propina · Setembro')).toHaveValue('45000,00')
    expect(screen.getByLabelText('Valor a alocar: Propina · Outubro')).toHaveValue('25000,00')
    expect(screen.getByLabelText('Valor a alocar: Propina · Novembro')).toHaveValue('')
    expect(screen.getByText(/Parcial · faltam 20\s000,00/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Confirmar e emitir recibo' })).toBeEnabled()
  })

  it('só deixa confirmar quando o valor recebido está todo alocado', async () => {
    setup(() => ({ status: 201, body: {} }))
    const user = userEvent.setup()
    await fill(user)

    const oct = screen.getByLabelText('Valor a alocar: Propina · Outubro')
    await user.clear(oct)
    await user.type(oct, '10000,00')

    expect(screen.getByRole('button', { name: 'Confirmar e emitir recibo' })).toBeDisabled()
    expect(screen.getByText(/diferença/)).toBeInTheDocument()
  })

  it('envia o pagamento com a chave de idempotência e as alocações em cêntimos exactos', async () => {
    let sent: { headers: Record<string, string>; body: unknown } | undefined
    setup((headers, body) => {
      sent = { headers, body }
      return { status: 201, body: { data: { id: 1, amount: '70000.00', receipt: { receipt_code: 'RC 2026/00001' } }, replayed: false } }
    })
    const user = userEvent.setup()
    await fill(user)
    await user.click(screen.getByRole('button', { name: 'Confirmar e emitir recibo' }))

    await waitFor(() => expect(sent).toBeDefined())
    expect(sent?.headers['Idempotency-Key']).toMatch(/[0-9a-f-]{36}/)
    expect(sent?.body).toEqual({
      student_id: 5,
      amount: '70000.00',
      method: 'card_terminal',
      reference: null,
      allocations: [
        { charge_id: 1, amount: '45000.00' },
        { charge_id: 2, amount: '25000.00' },
      ],
    })
    expect(await screen.findByText(/RC 2026\/00001/)).toBeInTheDocument()
  })

  it('repetir o mesmo pedido depois de uma falha reutiliza a chave; mudar o pedido gera outra', async () => {
    const keys: string[] = []
    setup((headers) => {
      keys.push(headers['Idempotency-Key'])
      return { status: 500, body: { message: 'Falhou.' } }
    })
    const user = userEvent.setup()
    await fill(user)
    const confirm = screen.getByRole('button', { name: 'Confirmar e emitir recibo' })

    await user.click(confirm)
    await waitFor(() => expect(keys).toHaveLength(1))
    await user.click(confirm)
    await waitFor(() => expect(keys).toHaveLength(2))
    expect(keys[1]).toBe(keys[0])

    await user.type(screen.getByLabelText('Referência / talão'), 'TPA-1')
    await user.click(confirm)
    await waitFor(() => expect(keys).toHaveLength(3))
    expect(keys[2]).not.toBe(keys[0])
  })
})
