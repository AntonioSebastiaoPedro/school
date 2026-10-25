import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { GradesPage } from './GradesPage'

const sections = { data: [{ id: 1, name: 'A', school_year_id: 1, grade_level_id: 4, shift: 'morning', capacity: 40 }] }
const curriculum = { data: { 4: [{ subject_id: 9, weekly_hours: 5, is_active: true, subject: { id: 9, code: 'LP', name: 'Língua Portuguesa' } }] } }
const grid = {
  scale: { max_score: 10, pass_mark: 5 },
  locked: false,
  rows: [{ enrollment_id: 3, roll_number: 1, student_name: 'Ana Silva', mac: null, npp: null, npt: null, mt: null, exam: null, resit: null }],
}

function stubApi(onPut: (body: unknown) => void) {
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: string, init?: RequestInit) => {
      let body: unknown = {}
      if (init?.method === 'PUT') {
        onPut(JSON.parse(String(init.body)))
        body = { saved: 1 }
      } else if (url.includes('/sections')) body = sections
      else if (url.includes('/curriculum')) body = curriculum
      else if (url.includes('/grades')) body = grid
      return { status: 200, ok: true, json: async () => body }
    }),
  )
}

afterEach(() => vi.unstubAllGlobals())

describe('GradesPage', () => {
  it('lança uma nota com vírgula decimal e assinala a negativa', async () => {
    document.cookie = 'XSRF-TOKEN=t'
    let sent: unknown
    stubApi((b) => (sent = b))
    const user = userEvent.setup()

    render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <GradesPage />
      </QueryClientProvider>,
    )

    await screen.findByRole('option', { name: /Classe 4/ })
    await user.selectOptions(screen.getByLabelText('Turma'), '1')
    await screen.findByRole('option', { name: /LP/ })
    await user.selectOptions(screen.getByLabelText('Disciplina'), '9')

    const mac = await screen.findByLabelText('MAC de Ana Silva')
    await user.type(mac, '4,5')
    expect(mac).toHaveClass('negative') // 4,5 < mínimo 5

    await user.click(screen.getByRole('button', { name: 'Guardar notas' }))

    await waitFor(() =>
      expect(sent).toEqual({ section_id: 1, subject_id: 9, term: 1, entries: [{ enrollment_id: 3, kind: 'mac', value: 4.5 }] }),
    )
  })

  it('rejeita no cliente uma nota acima da escala', async () => {
    stubApi(() => undefined)
    const user = userEvent.setup()

    render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <GradesPage />
      </QueryClientProvider>,
    )

    await screen.findByRole('option', { name: /Classe 4/ })
    await user.selectOptions(screen.getByLabelText('Turma'), '1')
    await screen.findByRole('option', { name: /LP/ })
    await user.selectOptions(screen.getByLabelText('Disciplina'), '9')
    const mac = await screen.findByLabelText('MAC de Ana Silva')
    await user.type(mac, '11')

    expect(mac).toHaveAttribute('aria-invalid', 'true')
  })
})
