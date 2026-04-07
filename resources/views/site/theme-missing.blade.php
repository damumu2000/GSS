<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $site->name }} - 站点模板未绑定</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            color: #1f2937;
            background: #f6f8fb;
        }

        .theme-missing-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .theme-missing-card {
            width: min(640px, 100%);
            padding: 36px;
            border-radius: 24px;
            background: #ffffff;
            border: 1px solid #e8edf3;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
        }

        .theme-missing-badge {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            background: #f2f4f7;
            color: #667085;
            font-size: 12px;
            font-weight: 700;
        }

        h1 {
            margin: 16px 0 0;
            color: #111827;
            font-size: 30px;
            line-height: 1.3;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .theme-missing-desc {
            margin-top: 14px;
            color: #667085;
            font-size: 15px;
            line-height: 1.9;
        }

        .theme-missing-meta {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eef2f6;
            display: grid;
            gap: 10px;
        }

        .theme-missing-meta-item {
            color: #667085;
            font-size: 14px;
            line-height: 1.8;
        }

        .theme-missing-meta-label {
            color: #98a2b3;
            margin-right: 8px;
        }

        .theme-missing-meta-value {
            color: #1f2937;
            font-weight: 600;
            word-break: break-word;
        }

        @media (max-width: 640px) {
            .theme-missing-shell {
                padding: 16px;
            }

            .theme-missing-card {
                padding: 28px 22px;
                border-radius: 20px;
            }

            h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <div class="theme-missing-shell">
        <main class="theme-missing-card">
            <span class="theme-missing-badge">{{ $isPreview ? '预览不可用' : '站点暂不可访问' }}</span>
            <h1>{{ $isPreview ? '当前内容暂时无法预览' : '当前站点暂未绑定可用模板' }}</h1>
            <div class="theme-missing-desc">
                {{ $isPreview ? '该内容所属站点当前还没有配置可用主题，因此暂时无法按前台样式进行预览。' : '该站点当前还没有配置可用主题，因此前台页面暂时无法正常显示。' }}
                请先在后台为该站点绑定主题，并在模板管理中选择一个可用主题。
            </div>

            <section class="theme-missing-meta">
                <div class="theme-missing-meta-item">
                    <span class="theme-missing-meta-label">站点名称</span>
                    <span class="theme-missing-meta-value">{{ $site->name }}</span>
                </div>
                <div class="theme-missing-meta-item">
                    <span class="theme-missing-meta-label">站点标识</span>
                    <span class="theme-missing-meta-value">{{ $site->site_key }}</span>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
