import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ApplicationsPage } from './ApplicationsPage'

const application = {
  id: 7,
  institution_type: 'school',
  proposed_name: 'Escola Sol Nascente',
  applicant_name: 'Maria Pedro',
  applicant_email: 'maria@escola.ao',
  applicant_phone: '923000000',
  tax_identifier: null,
  province: 'Benguela',
  municipality: null,
  status: 'pending',
  review_notes: null,
  submitted_at: '2026-10-23T14:12:00Z',
  reviewed_at: null,
  school_profile: { director_name: 'Maria Pedro', regime: 'private', school_code: '1234' },
}

describe('ApplicationsPage', () => {
  it('mostra o pedido e envia a decisão com a nota', async () => {
    document.cookie = 'XSRF-TOKEN=t'
    let decision: unknown
    vi.stubGlobal(
      'fetch',
      vi.fn(async (url: string, init?: RequestInit) => {
        if (init?.method === 'POST') {
          decision = { url, body: JSON.parse(String(init.body)) }
          return { status: 200, ok: true, json: async () => ({ data: {} }) }
        }
        return { status: 200, ok: true, json: async () => ({ data: [application], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 } }) }
      }),
    )
    const user = userEvent.setup()

    render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <ApplicationsPage />
      </QueryClientProvider>,
    )

    expect(await screen.findByRole('heading', { name: 'Escola Sol Nascente' })).toBeInTheDocument()
    expect(screen.getByText(/Aprovar não activa a subscrição/)).toBeInTheDocument()

    await user.type(screen.getByLabelText(/Nota da decisão/), 'Documentação completa')
    await user.click(screen.getByRole('button', { name: 'Aprovar instituição' }))

    await waitFor(() =>
      expect(decision).toEqual({
        url: '/api/v1/admin/institution-applications/7/decision',
        body: { decision: 'approve', notes: 'Documentação completa' },
      }),
    )
  })
})
