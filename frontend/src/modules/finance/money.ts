/** Valores em cêntimos inteiros, como no servidor: nunca em vírgula flutuante. */

/** "70 000,00", "70000.5" ou "45000" → cêntimos. Devolve null se não for um valor válido (máx. 2 casas). */
export function parseKz(raw: string): number | null {
  const text = raw.replace(/\s/g, '').replace(',', '.')
  const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(text)
  if (!match) return null
  return Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0') || 0)
}

/** Cêntimos → "70 000,00" (separador de milhares e vírgula decimal, como no desenho). */
export function formatKz(cents: number): string {
  const abs = Math.abs(cents)
  const whole = Math.floor(abs / 100).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
  return `${cents < 0 ? '-' : ''}${whole},${String(abs % 100).padStart(2, '0')}`
}

/** Texto decimal do servidor ("45000.00") → cêntimos. */
export function fromApi(amount: string): number {
  return parseKz(amount) ?? 0
}

/** Cêntimos → texto decimal para enviar ao servidor. */
export function toApi(cents: number): string {
  return `${Math.floor(cents / 100)}.${String(cents % 100).padStart(2, '0')}`
}

export interface Payable {
  id: number
  due_date: string | null
  outstanding: number
}

/** Alocação automática: as cobranças mais antigas primeiro (vencimento, depois id) até esgotar o valor. */
export function allocateOldestFirst(charges: Payable[], amount: number): Record<number, number> {
  const ordered = [...charges].sort((a, b) => (a.due_date ?? '9999').localeCompare(b.due_date ?? '9999') || a.id - b.id)
  const result: Record<number, number> = {}
  let left = amount

  for (const charge of ordered) {
    if (left <= 0) break
    const part = Math.min(left, charge.outstanding)
    if (part > 0) {
      result[charge.id] = part
      left -= part
    }
  }

  return result
}
