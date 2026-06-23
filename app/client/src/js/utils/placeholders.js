/**
 * Placeholder system for card dare texts.
 *
 * Each entry defines:
 *   pattern  – RegExp (with /g flag) to match the placeholder token
 *   preview  – (optional) string shown before the placeholder is resolved,
 *              e.g. before a player is selected. Omit to resolve immediately.
 *   resolve  – function(context) → string; called once per token occurrence
 *
 * context = { players: Array<{id, name}> }
 *
 * To add a new placeholder, append an entry to PLACEHOLDERS.
 * Example: { pattern: /\[Drink\]/g, resolve: () => 'einen Schluck' }
 */
const PLACEHOLDERS = [
    {
        pattern: /\[Player\]/g,
        preview: '???',
        resolve: ({ players }) => {
            const strategies = [
                () => {
                    if (!players.length) return 'eine*r beliebigen Person der Gruppe'
                    return players[Math.floor(Math.random() * players.length)].name
                },
                () => 'der Person links von dir',
                () => 'der Person rechts von dir',
                () => 'der Person gegenüber von dir',
                () => 'eine*r Person deiner Wahl',
                () => 'eine*r beliebigen Person der Gruppe',
                () => 'eine*r von der Gruppe ausgewählten Person der Gruppe',
            ]
            return strategies[Math.floor(Math.random() * strategies.length)]()
        },
    },
    {
        pattern: /\[Time\]/g,
        preview: '???',
        resolve: () => {
            const options = [
                'mind. 10 Sek.',
                'mind. 30 Sek.',
                'mind. 1 Min.',
            ]
            return options[Math.floor(Math.random() * options.length)]
        },
    },
]

/**
 * Resolves all placeholders in `text`.
 * Returns an array of segments: { text: string, highlighted: boolean }
 * Highlighted segments are the resolved replacements (for styling).
 *
 * When preview=true, placeholders with a `preview` field show that text
 * instead of resolving — useful before an executor player is selected.
 * Random choices are made at call time and are stable thereafter.
 */
export function resolvePlaceholders(text, context = {}, { preview = false } = {}) {
    let segments = [{ text, highlighted: false }]

    for (const { pattern, preview: previewText, resolve } of PLACEHOLDERS) {
        const next = []
        for (const seg of segments) {
            if (seg.highlighted) {
                next.push(seg)
                continue
            }
            const parts = seg.text.split(pattern)
            parts.forEach((part, i) => {
                if (part) next.push({ text: part, highlighted: false })
                if (i < parts.length - 1) {
                    const text = (preview && previewText !== undefined)
                        ? previewText
                        : resolve(context)
                    next.push({ text, highlighted: true })
                }
            })
        }
        segments = next
    }

    return segments
}
