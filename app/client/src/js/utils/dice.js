// Result of a finished dice round ("Handkarten" mode)

/**
 * @param {Array} players game players with diceRoll / rollEligible
 * @returns {{ winners: Array, tie: boolean }} players with the highest roll in the round
 */
export function rollResult(players) {
    const inRound = players.filter((p) => p.rollEligible && p.diceRoll > 0)
    const max = Math.max(0, ...inRound.map((p) => p.diceRoll))
    const winners = inRound.filter((p) => p.diceRoll === max)
    return { winners, tie: winners.length > 1 }
}

/** "Anna", "Anna und Ben", "Anna, Ben und Cleo" */
export function joinNames(players) {
    const names = players.map((p) => p.name)
    return names.length > 1 ? `${names.slice(0, -1).join(', ')} und ${names.at(-1)}` : (names[0] ?? '')
}
