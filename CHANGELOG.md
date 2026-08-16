# Changelog

All notable changes to `scramble` will be documented in this file.

## 1.0.2 - 2026-08-16

- **Scalar 侧边栏样式微调**:一级分组标题字号提至 16px(层级更分明);隐藏 `.scalar-mcp-layer` 弹层;底部明暗切换区加分隔线、移除外部链接
- **仓库瘦身(无运行时影响)**:移除上游 `tests/`、`.github/`、`phpstan-baseline.neon` 等未随包维护的文件,补 `.gitignore`
- 提醒:升级后若文档页仍渲染 Elements,检查项目 `config/scramble.php` 的 `ui` 块中是否残留 `logo` 键(即使值为空串也会触发旧版 Elements 兼容分支,忽略 `renderer => 'scalar'`)

## 1.0.1 - 2026-08-15

- **Scalar 渲染器增强**(详见 [docs/scalar-renderer.md](docs/scalar-renderer.md)):
  - 三级导航:新增 `TagGroupsTransformer`,把 `分组-模块` 命名的 tag 拆成 `x-tagGroups`,侧边栏呈现 `分组 -> 模块 -> 接口` 三级结构;`OpenApi` 根对象支持序列化扩展属性(`x-*`)
  - 前端资源本地化:Scalar 浏览器构建内置包内并自动发布到 `public/vendor/scalar/`,不再依赖 jsdelivr CDN;支持 `vendor:publish --tag=scramble-assets`
  - 侧边栏新增"全部展开 / 全部收起"按钮(搜索框下方,固定不随菜单滚动)
  - 文档页加载提示与资源加载失败提示
  - 界面美化:中文字体栈、主题色、侧边栏样式,支持 `customCss` 配置覆盖
  - 界面语言:内置 `zh-CN` 等八种语言,支持 `localization.locale` 配置与文案覆盖

