const BASE = '/api/v1/game'

async function request(url, options = {}) {
    const res = await fetch(url, {
        headers: { 'Content-Type': 'application/json' },
        ...options,
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
    return data
}

export const createGame = () =>
    request(BASE, { method: 'POST' })

export const getGame = (hash) =>
    request(`${BASE}/${hash}`)

export const setupGame = (hash, { adultType, gameType, players }) =>
    request(`${BASE}/${hash}/setup`, {
        method: 'POST',
        body: JSON.stringify({ adultType, gameType, players }),
    })

export const setCards = (hash, { cardIds, customCards }) =>
    request(`${BASE}/${hash}/cards`, {
        method: 'POST',
        body: JSON.stringify({ cardIds, customCards }),
    })

export const addCustomCard = (hash, dare) =>
    request(`${BASE}/${hash}/card`, {
        method: 'POST',
        body: JSON.stringify({ dare }),
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
