import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';
import { readFileSync } from 'fs';

const pkg = JSON.parse(readFileSync(path.resolve(__dirname, 'package.json'), 'utf-8')) as { version: string };

// Version primaer aus dem Release-Tag (CI setzt APP_VERSION=<git-tag>, z. B. "v0.5.8").
// Das fuehrende "v" wird entfernt (Layout praefixt selbst). Fallback fuer lokale/PR-Builds:
// package.json. Verhindert den Footer-Drift (L-016) ohne manuellen package.json-Bump pro Tag.
const appVersion = (process.env.APP_VERSION ?? '').replace(/^v/, '').trim() || pkg.version;

export default defineConfig({
  define: {
    __APP_VERSION__: JSON.stringify(appVersion),
  },
  plugins: [tailwindcss(), react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
    },
  },
});
