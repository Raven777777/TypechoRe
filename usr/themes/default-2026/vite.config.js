import { resolve } from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';

/**
 * 主题前端构建
 *
 * 产物固定为 dist/style.css 与 dist/app.js (不带 hash),
 * PHP 侧通过 theme_asset() 追加 ?v=<filemtime> 做缓存失效,
 * 这样部署时不需要读取 manifest, 也不会有额外的文件 IO。
 *
 * swup 及它的插件走动态 import, 会被拆进 dist/chunks/。这些文件名带内容 hash:
 * PHP 不认识它们 (引用关系由 app.js 自己维护), 唯一能跟着 app.js 版本号一起
 * 失效的办法就是让内容变化反映到文件名上, 否则改了切页逻辑仍可能命中旧缓存。
 */
export default defineConfig({
  /* 相对 base: 主题被部署在 /usr/themes/<name>/dist/ 这样的子路径下, 而且目录名
     (主题名) 是用户可以改的。默认的 base '/' 会让动态 import 的 modulepreload
     预载链接指向站点根 (/chunks/...), 必然 404; './' 让所有资源都相对
     import.meta.url (也就是 app.js 自己的位置) 解析, 与 import() 的行为一致。 */
  base: './',
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
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: '[name][extname]'
      }
    }
  }
});
