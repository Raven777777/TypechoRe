# tools/assets

`tools/build_release.py` 打发布包时用来压缩前端资源的 [Parcel](https://github.com/parcel-bundler/parcel)
工具链。它只服务于构建，不参与运行时，`node_modules/` 已被 Git 忽略。

```bash
npm ci --prefix tools/assets    # 首次使用 / 依赖变更后
python tools/build_release.py   # 打包时自动调用 Parcel
```

## 为什么只压缩「自包含」资源

Parcel 是打包器，不是单纯的压缩器。把仓库里的文件直接丢给它会有两个副作用：

1. **CSS 的相对 `url(...)` 会被当成资源依赖**：引用的图片/字体被改名（加 content hash）
   并扁平化到输出目录根部，发布包「与仓库目录结构一致」的前提就没了；
   引用目标不存在时 Parcel 还会直接构建失败（`Cannot load file ...`）。
2. **经典脚本会被包进 IIFE**：没有 `import`/`export` 的 UMD 或挂到 `window` 上的库，
   顶层变量会变成 IIFE 内的局部变量，`window.jQuery` 之类的全局入口随之消失。

所以 `tools/minify_assets.py` 只处理确定安全的资源：

| 类型 | 处理方式 |
| --- | --- |
| `.css` | 压缩前把每个 `url(...)` 换成 `url(#__typechore_asset_N__)` 片段占位，压缩后换回原始文本；含相对 `@import` 的样式表跳过 |
| `.html` / `.htm` | 仅在没有任何相对引用（`src`/`href`/`srcset`…）时压缩 |

JS 不进这条流水线：发布集里的 JS 全是已压缩的产物（Vite 的 `dist/app.js`、`admin/js/` 里的
jQuery 等 vendor 包、`*.min.js`），再压一遍只有 1%~2% 的收益，而 Parcel 会把没有顶层
`import`/`export` 的脚本包进 IIFE，`window.jQuery`、`window.Markdown` 这些全局入口随之丢失。
要减小 JS 只能在源头优化（Vite 构建 / 换更小的依赖）。

压缩后如果比原文更大（或 Parcel 对某个文件失败），保留原文并在输出里说明原因。

## 依赖与可选的加速

Parcel 会把它的压缩器（CSS: `@parcel/optimizer-css` 基于 lightningcss，JS: `@parcel/optimizer-swc`
基于 `@swc/core`）一起装进 `node_modules/`，不需要额外下载二进制。

没有 Node 或没装依赖时，`python tools/build_release.py` 会在 `auto` 模式下打印警告并跳过压缩，
发布包仍然可用（只是没压缩）；CI 使用 `--require-minify` 把「压缩没生效」当成失败。
