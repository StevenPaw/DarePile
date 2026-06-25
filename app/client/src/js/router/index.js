import { createRouter, createWebHashHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import SetupPlayersView from '../views/SetupPlayersView.vue'
import SetupCardsView from '../views/SetupCardsView.vue'
import GameView from '../views/GameView.vue'
import StatsView from '../views/StatsView.vue'
import AddCardView from '../views/AddCardView.vue'
import CardOverviewView from '../views/CardOverviewView.vue'
import LegalView from '../views/LegalView.vue'

const router = createRouter({
    history: createWebHashHistory(),
    routes: [
        { path: '/', name: 'home', component: HomeView },
        { path: '/game/:hash/setup/players', name: 'setup-players', component: SetupPlayersView },
        { path: '/game/:hash/setup/cards', name: 'setup-cards', component: SetupCardsView },
        { path: '/game/:hash/play', name: 'game', component: GameView },
        { path: '/game/:hash/stats', name: 'stats', component: StatsView },
        { path: '/add-card/:hash', name: 'add-card', component: AddCardView },
        { path: '/cards', name: 'card-overview', component: CardOverviewView },
        { path: '/legal', name: 'legal', component: LegalView },
    ],
})

export default router
