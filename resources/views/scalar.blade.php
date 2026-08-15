<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API Docs' }}</title>
</head>
<body>
<div id="app"></div>
<!-- 加载提示放在 #app 外:Scalar 会对 #app 做水合(hydration),内部有额外节点会报 mismatch 警告 -->
<div id="scalar-loading" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;position:fixed;inset:0;background:var(--scalar-background-1,#fff);font-family:sans-serif;color:#6b7280;z-index:10;">
    <div style="width:36px;height:36px;border:3px solid #e5e7eb;border-top-color:#6366f1;border-radius:50%;animation:scalar-spin .8s linear infinite;"></div>
    <div>API 文档加载中,请稍候…</div>
    <style>@keyframes scalar-spin{to{transform:rotate(360deg)}}</style>
</div>
<script>
    // 资源加载失败时给出明确提示,而不是一直白屏
    window.scalarLoadFail = function () {
        var el = document.getElementById('scalar-loading');
        if (el) {
            el.innerHTML = '<div style="font-size:15px;">文档资源加载失败,请检查网络后刷新重试</div>';
        }
    };
</script>
<script src="{{ asset($config->renderer()->get('cdn', 'vendor/scalar/api-reference.js')) }}" onerror="scalarLoadFail()"></script>

<script>
    const CSRF_TOKEN_COOKIE_KEY = "XSRF-TOKEN";
    const CSRF_TOKEN_HEADER_KEY = "X-XSRF-TOKEN";
    const getCookieValue = (key) => {
        const cookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith(key));
        return cookie?.split("=")[1];
    };

    // 界面美化:中文字体栈、主色调、侧边栏高亮。可通过配置 customCss 覆盖。
    const defaultCustomCss = `
        :root, .scalar-app {
            --scalar-font: -apple-system, BlinkMacSystemFont, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            --scalar-font-code: "JetBrains Mono", SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace;
            --scalar-color-accent: #4f6bfe;
            --scalar-color-1: #4f6bfe;
            --scalar-sidebar-color-active: var(--scalar-color-accent);
            --scalar-sidebar-font-weight-active: 600;
            -webkit-font-smoothing: antialiased;
        }
        aside::-webkit-scrollbar { width: 6px; }
        aside::-webkit-scrollbar-thumb { background: #d4d4d8; border-radius: 3px; }
        aside::-webkit-scrollbar-thumb:hover { background: #a1a1aa; }

        /* 侧边栏 全部展开/收起 按钮行(插入在搜索框下方、菜单列表上方,不随菜单滚动) */
        .scalar-toggle-row {
            display: flex;
            gap: 6px;
            padding: 2px 12px 10px;
        }
        .scalar-mcp-layer{display:none !important}
        .darklight-reference{padding-top:10px;border-top:1px solid #ddd !important}
        .darklight-reference .no-underline{display:none !important}
        .scalar-toggle-row button {
            flex: 1;
            padding: 4px 0;
            font-size: 12px;
            color: var(--scalar-sidebar-color-1, #6b7280);
            background: transparent;
            border: 1px solid var(--scalar-sidebar-border-color, #e4e4e7);
            border-radius: 6px;
            cursor: pointer;
            transition: all .15s ease;
            text-align:center;
        }
        .scalar-toggle-row button:hover {
            color: var(--scalar-color-accent, #4f6bfe);
            border-color: var(--scalar-color-accent, #4f6bfe);
        }
        @media (max-width: 1000px) { .scalar-toggle-row { display: none; } }
    `;

    Scalar.createApiReference('#app', {
        content: @json($spec),
        customCss: defaultCustomCss,
        ...@json($config->renderer()->all(except: ['cdn', 'credentials'])),
        onBeforeRequest: ({ requestBuilder }) => {
            requestBuilder.headers.set(CSRF_TOKEN_HEADER_KEY, decodeURIComponent(getCookieValue(CSRF_TOKEN_COOKIE_KEY)))
        },
        customFetch: (input, init) => {
            return window.fetch(input, { ...init, credentials: @json($config->renderer()->get('credentials', 'include')) })
        }
    })

    // 侧边栏注入 "全部展开 / 全部收起" 按钮(Scalar 挂载后 aside 才出现)
    ;(function () {
        // 逐个点击状态不符的项,等 DOM 更新后再点下一个:
        // - 展开父级后子级才渲染,批量快进会漏
        // - 收起须子级优先(NodeList 末尾),否则父级先收起、子级节点被卸载导致点击落空
        // - Scalar 在快速连点时会回弹,逐击 + 间隔可稳定收敛
        let runToken = 0;
        const setAll = (expand) => {
            const target = expand ? 'true' : 'false';
            const my = ++runToken;
            const step = () => {
                if (my !== runToken) return; // 已发起新一轮,放弃本次
                const wrong = [...document.querySelectorAll('aside [aria-expanded]')]
                    .filter((el) => el.getAttribute('aria-expanded') !== target);
                const el = expand ? wrong[0] : wrong[wrong.length - 1];
                if (!el) return;
                el.click();
                setTimeout(step, 120);
            };
            step();
        };

        const inject = () => {
            const aside = document.querySelector('aside');
            const list = aside?.querySelector('ul');
            if (!aside || !list || document.getElementById('scalar-toggle-row')) return;

            const row = document.createElement('div');
            row.id = 'scalar-toggle-row';
            row.className = 'scalar-toggle-row';
            ['全部展开', '全部收起'].forEach((label, i) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = label;
                btn.addEventListener('click', () => setAll(i === 0));
                row.appendChild(btn);
            });
            // 插在搜索框与菜单列表之间:列表 ul 自身滚动,按钮行天然固定
            aside.insertBefore(row, list);
        };

        new MutationObserver(() => {
            // Scalar 挂载完成(aside 出现)后移除加载提示
            const loading = document.getElementById('scalar-loading');
            if (loading && document.querySelector('aside')) loading.remove();
            inject();
        })
            .observe(document.body, { childList: true, subtree: true });
    })();
</script>
</body>
</html>
