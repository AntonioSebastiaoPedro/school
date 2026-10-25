import { isNegative, parseGrade } from './parseGrade'

describe('parseGrade', () => {
  it('aceita vírgula decimal e arredonda a 2 casas', () => {
    expect(parseGrade('8,456', 10)).toEqual({ ok: true, value: 8.46 })
  })

  it('vazio e 0 significam sem nota', () => {
    expect(parseGrade('', 10)).toEqual({ ok: true, value: null })
    expect(parseGrade('0', 10)).toEqual({ ok: true, value: null })
    expect(parseGrade('0,00', 10)).toEqual({ ok: true, value: null })
  })

  it('respeita o máximo da classe', () => {
    expect(parseGrade('10', 10)).toEqual({ ok: true, value: 10 })
    expect(parseGrade('10,5', 10)).toEqual({ ok: false, message: 'A nota deve estar entre 0 e 10.' })
    expect(parseGrade('20', 20)).toEqual({ ok: true, value: 20 })
    expect(parseGrade('20,5', 20)).toEqual({ ok: false, message: 'A nota deve estar entre 0 e 20.' })
  })

  it('recusa texto e negativos', () => {
    expect(parseGrade('abc', 10).ok).toBe(false)
    expect(parseGrade('-1', 10).ok).toBe(false)
  })
})

describe('isNegative', () => {
  it('só é negativa uma nota positiva abaixo do mínimo', () => {
    expect(isNegative(4, 5)).toBe(true)
    expect(isNegative(5, 5)).toBe(false)
    expect(isNegative(null, 5)).toBe(false)
  })
})
