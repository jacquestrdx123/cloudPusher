/// <reference types="vitest" />

import legacy from '@vitejs/plugin-legacy'
import vue from '@vitejs/plugin-vue'
import path from 'path'
import { VitePWA } from 'vite-plugin-pwa'
import { defineConfig } from 'vite'
import { firebaseConfigPlugin } from './vite.firebase-config'

export default defineConfig({
  server: {
    proxy: {
      // Proxy API calls during local Vite dev to avoid CORS.
      '/api': {
        target: 'https://cloudpusher-backend.on-forge.com',
        changeOrigin: true,
        secure: true,
      },
    },
  },
  plugins: [
    vue(),
    legacy(),
    firebaseConfigPlugin(),
    VitePWA({

      registerType: 'autoUpdate',
      includeAssets: ['favicon.png', 'sounds/notification.mp3'],
      manifest: {
        name: 'cloudPusher',
        short_name: 'cloudPusher',
        description: 'Push notification receiver for cloudPusher',
        theme_color: '#f59e0b',
        background_color: '#111827',
        display: 'standalone',
        orientation: 'portrait',
        start_url: '/',
        icons: [
          {
            src: 'favicon.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'any maskable',
          },
        ],
      },
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,mp3,wav,woff2}'],
        navigateFallback: 'index.html',
      },
    }),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
  },
})
