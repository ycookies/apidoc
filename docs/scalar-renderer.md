# Scalar 渲染器与三级导航

> 版本:1.0.1 起
> 适用:`ycookies/apidoc`(Scramble fork)

本文档汇总 Scalar 渲染器的功能、配置与注意事项。

## 功能概览

| 功能 | 说明 |
|---|---|
| Scalar 渲染器 | 配置 `renderer => 'scalar'` 切换,替代默认的 Stoplight Elements |
| 三级导航 | 侧边栏 `分组 -> 模块 -> 接口` 三级结构(x-tagGroups) |
| 全部展开/收起 | 侧边栏搜索框下方两个独立按钮,固定不随菜单滚动 |
| 界面语言 | 内置 `en de es fr pt ru ar zh-CN`,支持文案覆盖 |
| 资源本地化 | Scalar 前端 JS 包内置,自动发布到 `public/vendor/scalar/`,不依赖 CDN |
| 加载体验 | 加载提示动画、资源加载失败提示(不再白屏) |
| 界面美化 | 中文字体栈、主题色、侧边栏样式(可通过 customCss 覆盖) |

## 切换渲染器

```php
// config/scramble.php
'renderer' => 'scalar',

'renderers' => [
    'scalar' => [
        'view' => 'scramble::scalar',
        'cdn' => 'vendor/scalar/api-reference.js', // 本地资源(默认)
        'theme' => 'laravel',
        'darkMode' => false,
        'credentials' => 'include',
        'localization' => [
            'locale' => 'zh-CN',
        ],
    ],
],
```

`renderers.scalar` 下的所有键(除 `cdn`、`credentials`、`view`)会原样透传给
[`Scalar.createApiReference`](https://github.com/scalar/scalar),即 Scalar 官方支持的配置项都可以写在这里。

注意:`ui.logo` 键存在时会强制走 Elements 渲染器的兼容分支,使用 Scalar 时请勿在 `ui` 中配置 `logo`。

## 三级导航(x-tagGroups)

Stoplight Elements 的侧边栏只有 `tag -> 接口` 两级,且不支持 x-tagGroups;
Scalar 支持该扩展,可实现 `分组 -> 模块 -> 接口` 三级。

### 命名约定

控制器上用 `#[Group('分组-模块')]` 命名(tag 名含 `-` 或 `/` 时拆分):

```php
#[Group('点餐-菜品', '菜品列表', 2)]
class DishApiController extends Controller
```

侧边栏效果:

```
点餐
 ├─ 分类
 ├─ 菜品
 │   ├─ GET /dishes
 │   └─ ...
```

处理规则(`TagGroupsTransformer`):

- tag 改名为去掉分组前缀的短名,侧边栏不重复显示"点餐-菜品";
- 跨分组同名冲突(如 `点餐-订单` 与 `服务预约-订单`):首个出现的组保留短名 `订单`,后续组保留全名 `服务预约-订单`,避免两组合并错乱;
- 通过 `scramble.tag_groups = false` 可整体关闭(默认开启)。

### ⚠️ 未分组 tag 会被隐藏

Scalar 在 spec 存在 x-tagGroups 时,**不显示任何未分组的 tag**。
所有 API 控制器的 `#[Group]` 都必须写成 `分组-模块` 格式,否则该模块不会出现在侧边栏。

### 修改后须清缓存

```bash
php artisan scramble:clear
```

文档有缓存(default 与 admin-api 各一份),改 Group 命名后必须清理,否则看到的还是旧结构。

## 前端资源本地化

Scalar 前端 JS(约 3.7MB)已内置在包内 `resources/assets/scalar/`,服务启动时:

- 目标文件缺失时**自动复制**到 `public/vendor/scalar/api-reference.js`;
- 也支持手动发布:`php artisan vendor:publish --tag=scramble-assets`。

默认 `cdn` 配置指向本地文件,彻底摆脱对 `cdn.jsdelivr.net` 的依赖(国内访问超时会导致文档页白屏)。
如需改回 CDN 或自定义版本,配置完整 URL 即可:

```php
'cdn' => 'https://cdn.jsdelivr.net/npm/@scalar/api-reference@1.65.1',
```

升级包内 Scalar 版本:下载新版本浏览器构建覆盖 `resources/assets/scalar/api-reference.js`,并重新发布。

## 侧边栏全部展开/收起

视图在搜索框下方、菜单列表上方注入两个独立按钮(`全部展开` / `全部收起`):

- 位置固定:侧边栏滚动发生在菜单列表自身,按钮行在滚动区外,不随菜单滚动;
- 窄屏(<1000px,侧边栏收为抽屉)时自动隐藏;
- 实现为逐项触发 Scalar 折叠节点(120ms 间隔逐击,收起时子级优先),兼容 Scalar 的异步渲染。

## 界面语言

```php
'localization' => [
    'locale' => 'zh-CN',  // 内置:en de es fr pt ru ar zh-CN
    // 'translations' => [...],  // 覆盖个别内置文案
    // 'direction' => 'auto',    // auto / ltr / rtl
],
```

## 界面美化(customCss)

视图内置了一组默认样式(中文字体栈、主题色 `#4f6bfe`、侧边栏滚动条等),
配置 `customCss` 可整体覆盖默认样式(配置值优先于内置默认值):

```php
'customCss' => '...', // 任意 CSS 字符串
```

## 常见问题

**文档页空白?**
打开浏览器开发者工具 Network,确认 `vendor/scalar/api-reference.js` 返回 200;
资源缺失时通常是没有发布资源——执行 `php artisan vendor:publish --tag=scramble-assets`,或清缓存触发自动复制。

**模块在侧边栏消失?**
该控制器的 `#[Group]` 没有按 `分组-模块` 命名(见上文"未分组 tag 会被隐藏")。

**改了 Group 名字没生效?**
执行 `php artisan scramble:clear` 后刷新。

**控制台报 Hydration mismatch?**
不要往 `#app` 容器里塞额外节点(Scalar 对其做水合),加载提示等浮层请放在 `#app` 外。
