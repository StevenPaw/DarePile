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

    async function setup(adultType, gameType, playerNames) {
        return withLoading(async () => {
            game.value = await gameApi.setupGame(hash.value, {
                adultType,
                gameType,
                players: playerNames,
            })
        })
    }

    async function saveCards(cardIds, customCards = []) {
        return withLoading(async () => {
            game.value = await gameApi.setCards(hash.value, { cardIds, customCards })
        })
    }

    async function addCard(dare) {
        return withLoading(async () => {
            const card = await gameApi.addCustomCard(hash.value, dare)
            if (game.value) {
                game.value.cards = [...(game.value.cards ?? []), card]
            }
            return card
        })
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
        availableCards,
        levelMin,
        levelMax,
        createGame,
        loadGame,
        setup,
        saveCards,
        addCard,
        draw,
        doAction,
        resetDeck,
        clearGame,
    }
})
