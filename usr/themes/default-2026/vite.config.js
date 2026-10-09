import { resolve } from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';

/**
 * 主题前端构建
 *
 * 产物固定为 dist/style.css 与 dist/app.js (不带 hash),
 * PHP 侧通过 theme_asset() 追加 ?v=<filemtime> 做缓存失效,
 * 这样部署时不需要读取 manifest, 也不会有额外的文件 IO。
 */
export default defineConfig({
  plugins: [tailwindcss()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: false,
    target: 'es2022',
    sourcemap: false,
    rollupOptions: {
      input: {
        app: resolve(import.meta.dirname, 'src/js/app.js'),
        style: resolve(import.meta.dirname, 'src/css/main.css')
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name].js',
        assetFileNames: '[name][extname]'
      }
    }
  }
});
