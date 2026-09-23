# 更新日志

本主题基于 Typecho 默认主题（Typecho Replica Theme）修改，以下仅记录本 Fork 的改动。

## 1.3.1（2026-09-22）

### 修复

- 标签云在页面刷新或跳转后从初始朝向重新开始，出现跳闪、不连贯：改为将每个标签的 3D 坐标与鼠标偏移写入 `sessionStorage`，初始化时同步恢复后再绘制，使同一标签页内的旋转保持连续。
  - 以「标签名 + 文章数」的 md5 作为指纹，仅在同一批标签时才恢复，避免标签变化后位置错乱。
- 标签云长标签文本被容器尺寸裁切：`.tagcloud-wrap` 改为 `overflow: visible`，并为标签项设置 `white-space: nowrap`，长文本不再被截断。

### 优化

- 标签云仅在容器半径变化时才重建，避免窗口缩放时反复重建（原库 `destroy()` 不回收动画帧与 `window` 鼠标监听，会累积泄漏）。
- 移除标签云多余的 `beforeunload` 监听（`pagehide` 与 `visibilitychange` 已覆盖），避免影响 bfcache。
- 标签云样式由 `functions.php` 内联 `<style>` 迁移至 `style.css`，便于浏览器缓存。
- `style.css`：删除重复的 `.post-content { line-height: 1.5; }` 与空的 `@media (min-width: 992px) {}`。
- `index.php`：简化 `if` 条件中多余的括号。

### 变更

- `functions.php`：`tagCloudRender()` 增加标签云状态持久化脚本，并移除内联样式。
- `style.css`：新增 `/* Tag cloud */` 样式段。
- `index.php`：版本号更新为 `1.3.1`。

## 1.3.0（2026-09-21）

### 新增

- 侧边栏 3D 标签云：
  - 显示在右侧边栏「最新文章」上方；
  - 云高度固定为 `218px`（与侧栏内容宽度一致，呈正方形）；
  - 鼠标移动带动旋转，移动端自动降级为静态显示。
- 主题设置新增「标签云」分组：
  - 启用标签云（开关）；
  - 显示数量（最多显示多少个标签，`0` 表示全部）；
  - 最小字号 / 最大字号（单位 px，三项同排显示）。
- 新增输出函数 `tagCloudRender()`，负责取标签、计算字号并渲染云。

### 优化

- 字号按标签文章数**对数缩放**（并做 0.85 次幂压缩），避免热门标签字号过大，整体更均衡。
- 标签默认按创建顺序排列，不按文章数排序。
- 禁止在云上拖拽时选中文字：容器 `user-select:none`，链接 `draggable="false"` 且 `-webkit-user-drag:none`。

### 安全

- 标签名与链接地址全部经 `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` 转义，文章数经 `intval` 处理。
- 数据以 `json_encode` 注入脚本，并启用 `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE`，防止脚本标签/引号逃逸与非法 UTF-8 导致的脚本错误。
- 脚本与资源均为**本地同源引用**，无任何外部 CDN 或外链请求。

### 变更

- `sidebar.php`：在 `#secondary` 顶部输出标签云。
- `functions.php`：新增标签云设置项与 `tagCloudRender()`。
- `404.php`：补充 `id="main"`（布局一致性）。

### 第三方资源引用

- **TagCloud.js** v2.5.0
  - 作者：Cong Min（@cong-min）
  - 仓库：https://github.com/cong-min/TagCloud （现重定向至 https://github.com/mcc108/TagCloud ）
  - 许可证：MIT
  - 本地路径：`usr/themes/default/js/TagCloud.min.js`
  - 文件大小：7248 字节
  - SHA256：`129909BFDE549841B081F114855AA2FAECB5179F825ADA9F514B614F1D9DF68C`

> 该库为纯前端 3D 标签云组件，无任何依赖、无网络请求；保留其 MIT 许可与版权注释。后续如需升级，请核对来源与哈希。

## 1.2

- 基于 Typecho 官方默认主题（Typecho Replica Theme）。
