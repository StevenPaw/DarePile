const BASE = '/api/v1/cards'

export async function fetchCards({ search = '', adult = null, page = 1, limit = 30 } = {}) {
    const params = new URLSearchParams({ page, limit })
    if (search) params.set('search', search)
    if (adult !== null) params.set('adult', adult ? '1' : '0')

    const res = await fetch(`${BASE}?${params}`)
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
    return data
}
