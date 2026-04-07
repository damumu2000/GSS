<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $site->name }} - 页面暂时无法显示</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            color: #1f2937;
            background: #f6f8fb;
        }
        .shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: min(680px, 100%);
            padding: 36px;
            border-radius: 24px;
            background: #ffffff;
            border: 1px solid #e8edf3;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 12px;
            border-radius: 999px;
            background: #fff4ed;
            color: #c2410c;
            font-size: 12px;
            font-weight: 700;
        }
        h1 {
            margin: 16px 0 0;
            color: #111827;
            font-size: 30px;
            line-height: 1.3;
            font-weight: 700;
        }
        .desc {
            margin-top: 14px;
            color: #667085;
            font-size: 15px;
            line-height: 1.9;
        }
        .meta {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eef2f6;
            display: grid;
            gap: 10px;
        }
        .meta-item {
            color: #667085;
            font-size: 14px;
            line-height: 1.8;
        }
        .meta-label {
            color: #98a2b3;
            margin-right: 8px;
        }
        .meta-value {
            color: #1f2937;
            font-weight: 600;
            word-break: break-word;
        }
        @media (max-width: 640px) {
            .shell { padding: 16px; }
            .card { padding: 28px 22px; border-radius: 20px; }
            h1 { font-size: 26px; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <main class="card">
            <span class="badge">模板解析异常</span>
            <h1>页面暂时无法显示</h1>
            <div class="desc">
                当前主题模板存在解析问题，请稍后再试或联系管理员处理。
            </div>

            <section class="meta">
                <div class="meta-item">
                    <span class="meta-label">站点名称</span>
                    <span class="meta-value">{{ $site->name }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">站点标识</span>
                    <span class="meta-value">{{ $site->site_key }}</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">异常模板</span>
                    <span class="meta-value">{{ $template }}.tpl</span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">错误说明</span>
                    <span class="meta-value">{{ $message }}</span>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
