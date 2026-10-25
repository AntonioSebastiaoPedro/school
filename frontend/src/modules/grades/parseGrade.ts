export type ParsedGrade = { ok: true; value: number | null } | { ok: false; message: string }

/**
 * Valida uma nota escrita pelo utilizador. Aceita vírgula decimal; vazio ou 0 significam
 * "sem nota". O servidor repete esta validação: aqui serve só para dar resposta imediata.
 */
export function parseGrade(raw: string, max: number): ParsedGrade {
  const text = raw.trim().replace(',', '.')
  if (text === '') return { ok: true, value: null }

  const number = Number(text)
  if (!Number.isFinite(number)) return { ok: false, message: 'Nota inválida.' }

  const value = Math.round(number * 100) / 100
  if (value === 0) return { ok: true, value: null }
  if (value < 0 || value > max) return { ok: false, message: `A nota deve estar entre 0 e ${max}.` }

  return { ok: true, value }
}

/** Nota negativa: positiva mas abaixo da mínima. 0 nunca é negativa. */
export function isNegative(value: number | null, passMark: number): boolean {
  return value !== null && value > 0 && value < passMark
}
