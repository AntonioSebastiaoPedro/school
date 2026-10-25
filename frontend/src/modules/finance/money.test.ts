import { allocateOldestFirst, formatKz, fromApi, parseKz, toApi } from './money'

describe('parseKz', () => {
  it('aceita vírgula, ponto e espaços', () => {
    expect(parseKz('70 000,00')).toBe(7_000_000)
    expect(parseKz('70000.5')).toBe(7_000_050)
    expect(parseKz('45000')).toBe(4_500_000)
    expect(parseKz('0,05')).toBe(5)
  })

  it('recusa o que não é valor monetário', () => {
    expect(parseKz('')).toBeNull()
    expect(parseKz('abc')).toBeNull()
    expect(parseKz('1,234')).toBeNull()
    expect(parseKz('-5')).toBeNull()
  })

  it('não tem erros de vírgula flutuante', () => {
    expect(parseKz('0,10')! + parseKz('0,20')!).toBe(30)
  })
})

describe('formatação', () => {
  it('mostra milhares e duas casas', () => {
    expect(formatKz(7_000_000)).toBe('70 000,00')
    expect(formatKz(5)).toBe('0,05')
  })

  it('converte de e para o texto da API', () => {
    expect(fromApi('45000.00')).toBe(4_500_000)
    expect(toApi(7_000_050)).toBe('70000.50')
    expect(toApi(5)).toBe('0.05')
  })
})

describe('allocateOldestFirst', () => {
  const charges = [
    { id: 3, due_date: '2026-11-10', outstanding: 4_500_000 },
    { id: 1, due_date: '2026-09-10', outstanding: 4_500_000 },
    { id: 2, due_date: '2026-10-10', outstanding: 4_500_000 },
  ]

  it('liquida as mais antigas e deixa a última parcial, como no desenho', () => {
    expect(allocateOldestFirst(charges, 7_000_000)).toEqual({ 1: 4_500_000, 2: 2_500_000 })
  })

  it('nunca aloca mais do que o saldo nem do que o valor recebido', () => {
    const all = allocateOldestFirst(charges, 99_000_000)
    expect(Object.values(all).reduce((a, b) => a + b, 0)).toBe(13_500_000)
    expect(allocateOldestFirst(charges, 0)).toEqual({})
  })

  it('cobranças sem vencimento ficam para o fim', () => {
    expect(allocateOldestFirst([{ id: 9, due_date: null, outstanding: 100 }, { id: 8, due_date: '2026-01-01', outstanding: 100 }], 100)).toEqual({ 8: 100 })
  })
})
