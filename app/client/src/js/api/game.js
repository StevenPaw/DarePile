const BASE = '/api/v1/game'

async function request(url, options = {}) {
    const res = await fetch(url, {
        headers: { 'Content-Type': 'application/json' },
        ...options,
    })
    const data = await res.json()
    if (!res.ok) {
        const error = new Error(data.error || `HTTP ${res.status}`)
        error.status = res.status
        throw error
    }
    return data
}

export const createGame = () =>
    request(BASE, { method: 'POST' })

export const getGame = (hash, token = null) =>
    request(`${BASE}/${hash}${token ? `?token=${encodeURIComponent(token)}` : ''}`)

// settings: { adultType, gameType, handSize, players } — omitted keys stay unchanged
export const setupGame = (hash, settings) =>
    request(`${BASE}/${hash}/setup`, {
        method: 'POST',
        body: JSON.stringify(settings),
    })

export const setCards = (hash, { cardIds, customCards }) =>
    request(`${BASE}/${hash}/cards`, {
        method: 'POST',
        body: JSON.stringify({ cardIds, customCards }),
    })

// type: 'Dare' ("Pflicht") or 'Truth' ("Wahrheit")
export const addCustomCard = (hash, dare, level = 1, adultsOnly = false, type = 'Dare') =>
    request(`${BASE}/${hash}/card`, {
        method: 'POST',
        body: JSON.stringify({ dare, level, adultsOnly, type }),
    })

export const drawCard = (hash, cardId) =>
    request(`${BASE}/${hash}/draw`, {
        method: 'POST',
        body: JSON.stringify({ cardId }),
    })

export const playerAction = (hash, { cardId, playerId, type }) =>
    request(`${BASE}/${hash}/action`, {
        method: 'POST',
        body: JSON.stringify({ cardId, playerId, type }),
    })

export const resetDeck = (hash) =>
    request(`${BASE}/${hash}/reset`, { method: 'POST' })

// ── "Hand" mode ("Handkarten") ─────────────────────────────────
// Player actions need the token the player's device got when joining.
export const handAction = (hash, action, body = {}) =>
    request(`${BASE}/${hash}/hand-${action}`, {
        method: 'POST',
        body: JSON.stringify(body),
    })
