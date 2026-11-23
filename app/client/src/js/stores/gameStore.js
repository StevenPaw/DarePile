import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import * as gameApi from '../api/game.js'

function rangeKey(gameHash) {
    return `darepile_range_${gameHash}`
}

function saveRange(gameHash, min, max) {
    localStorage.setItem(rangeKey(gameHash), JSON.stringify({ min, max }))
}

function loadRange(gameHash) {
    try {
        const raw = localStorage.getItem(rangeKey(gameHash))
        if (!raw) return null
        const { min, max } = JSON.parse(raw)
        if (Number.isInteger(min) && Number.isInteger(max) && min >= 1 && max <= 10 && min <= max) {
            return { min, max }
        }
    } catch { /* ignore corrupt data */ }
    return null
}

export const useGameStore = defineStore('game', () => {
    const game = ref(null)
    const loading = ref(false)
    const error = ref(null)

    const hash = computed(() => game.value?.hash ?? null)
    const players = computed(() => game.value?.players ?? [])
    const cards = computed(() => game.value?.cards ?? [])
    const usedCardIds = computed(() => game.value?.usedCardIds ?? [])
    const gameType = computed(() => game.value?.gameType ?? 'Cardpile')
    // Player whose turn it is ("Turns" mode only, otherwise null)
    const currentPlayer = computed(() =>
        players.value.find((p) => p.id === game.value?.currentPlayerId) ?? null
    )

    const levelMin = ref(1)
    const levelMax = ref(10)

    const availableCards = computed(() =>
        cards.value.filter((c) =>
            !usedCardIds.value.includes(c.id) &&
            c.level >= levelMin.value &&
            c.level <= levelMax.value
        )
    )

    async function withLoading(fn) {
        loading.value = true
        error.value = null
        try {
            return await fn()
        } catch (e) {
            error.value = e.message
            throw e
        } finally {
            loading.value = false
        }
    }

    async function createGame() {
        return withLoading(async () => {
            const data = await gameApi.createGame()
            localStorage.setItem('darepile_hash', data.hash)
            game.value = {
                id: data.id,
                hash: data.hash,
                adultType: 'No Adult Dares',
                gameType: 'Cardpile',
                players: [],
                cards: [],
                usedCardIds: [],
            }
            return data.hash
        })
    }

    async function loadGame(gameHash) {
        return withLoading(async () => {
            game.value = await gameApi.getGame(gameHash)
            const saved = loadRange(gameHash)
            if (saved) {
                levelMin.value = saved.min
                levelMax.value = saved.max
            }
        })
    }

    // settings: { adultType, gameType, handSize, players } — omitted keys stay unchanged
    async function setup(settings) {
        return withLoading(async () => {
            game.value = await gameApi.setupGame(hash.value, settings)
        })
    }

    // "Hand" mode host actions: deal, range, reshuffle, roll-missing, remove
    async function handAction(action, body = {}) {
        game.value = await gameApi.handAction(hash.value, action, body)
    }

    async function saveCards(cardIds, customCards = []) {
        return withLoading(async () => {
            game.value = await gameApi.setCards(hash.value, { cardIds, customCards })
        })
    }

    async function addCard(dare, level = 1, adultsOnly = false) {
        return withLoading(async () => {
            const card = await gameApi.addCustomCard(hash.value, dare, level, adultsOnly)
            if (game.value) {
                game.value.cards = [...(game.value.cards ?? []), card]
            }
            return card
        })
    }

    // Polling: by default only the card list is taken over (card selection);
    // full: true replaces the whole game state ("Handkarten" mode)
    async function refreshGame(gameHash, { full = false } = {}) {
        try {
            const data = await gameApi.getGame(gameHash)
            if (game.value && game.value.hash === gameHash) {
                if (full) {
                    game.value = data
                } else {
                    game.value.cards = data.cards
                }
            }
        } catch { /* silent */ }
    }

    async function draw(cardId) {
        return withLoading(async () => {
            game.value = await gameApi.drawCard(hash.value, cardId)
        })
    }

    async function doAction(cardId, playerId, type) {
        return withLoading(async () => {
            game.value = await gameApi.playerAction(hash.value, { cardId, playerId, type })
        })
    }

    async function resetDeck() {
        return withLoading(async () => {
            game.value = await gameApi.resetDeck(hash.value)
        })
    }

    watch([levelMin, levelMax], ([min, max]) => {
        if (hash.value) saveRange(hash.value, min, max)
    })

    function clearGame() {
        game.value = null
        localStorage.removeItem('darepile_hash')
    }

    return {
        game,
        loading,
        error,
        hash,
        players,
        cards,
        usedCardIds,
        gameType,
        currentPlayer,
        availableCards,
        levelMin,
        levelMax,
        createGame,
        loadGame,
        setup,
        handAction,
        saveCards,
        addCard,
        refreshGame,
        draw,
        doAction,
        resetDeck,
        clearGame,
    }
})
