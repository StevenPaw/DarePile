// Absolute links to app routes, e.g. for QR codes

function base() {
    return window.location.origin + window.location.pathname
}

export const addCardUrl = (hash) => `${base()}#/add-card/${hash}`

export const joinUrl = (hash) => `${base()}#/join/${hash}`
