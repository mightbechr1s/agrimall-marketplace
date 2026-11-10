import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  root: 'public',
  build: {
    outDir: 'assets/dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: 'assets/js/islands/main.jsx',
    },
  },
  server: {
    port: 5173,
    strictPort: true,
  },
});
