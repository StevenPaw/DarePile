import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig(({ command }) => {
    const primary_url = process.env.DDEV_PRIMARY_URL || 'http://localhost'
    const origin = primary_url.replace(/:\d+$/, '') + `:5173`
    return {
        server: {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            origin: origin,
            cors: {
                origin: /https?:\/\/([A-Za-z0-9\-\.]+)?(\.ddev\.site)(?::\d+)?$/,
            },
            watch: {
                usePolling: true,
            },
        },
        resolve: {
            alias: [{ find: '@', replacement: '/app/client/src/js' }],
        },
        base: './',
        publicDir: 'app/client/public',
        build: {
            outDir: './app/client/dist',
            manifest: true,
            sourcemap: true,
            rollupOptions: {
                input: {
                    'main.js': './app/client/src/js/main.js',
                    'main.scss': './app/client/src/scss/main.scss',
                    'editor.scss': './app/client/src/scss/editor.scss',
                },
            },
        },
        css: {
            devSourcemap: true,
        },
        plugins: [
            vue(),
            VitePWA({
                registerType: 'autoUpdate',
                manifest: false,
                workbox: {
                    globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],
                    navigateFallback: null,
                },
            }),
        ],
    }
})
