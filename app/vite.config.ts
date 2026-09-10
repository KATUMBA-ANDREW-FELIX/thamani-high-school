import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vite'

const legacyAppPath = fileURLToPath(new URL('./app.js', import.meta.url))

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    {
      name: 'copy-legacy-app-script',
      generateBundle() {
        this.emitFile({
          type: 'asset',
          fileName: 'app.js',
          source: readFileSync(legacyAppPath, 'utf8')
        })
      }
    }
  ],

  server: {
    port: 5713,
    strictPort: true,
    proxy: {
      '/api': {
        target: 'http://localhost/backend',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, '')
      }
    }
  }
})