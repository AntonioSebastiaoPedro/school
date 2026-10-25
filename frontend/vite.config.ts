/// <reference types="vitest/config" />
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// A API corre em :18480 (ou em VITE_API_PROXY, no contentor); o proxy mantém tudo na mesma origem (cookies de sessão Sanctum).
const api = process.env.VITE_API_PROXY ?? 'http://localhost:18480'

export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': api,
      '/sanctum': api,
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test-setup.ts'],
    globals: true,
  },
})
