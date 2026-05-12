<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>系统邮件测试</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;color:#1f2937;font:14px/1.7 -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
    <div style="max-width:640px;margin:0 auto;padding:24px;border:1px solid #e5e7eb;border-radius:16px;background:#ffffff;">
        <h1 style="margin:0 0 12px;font-size:20px;line-height:1.4;">系统邮件测试成功</h1>
        <p style="margin:0 0 10px;">这是一封来自平台系统设置的测试邮件，用于确认当前邮件服务已经可以正常调用。</p>
        <p style="margin:0 0 10px;">发件名称：{{ $fromName }}</p>
        <p style="margin:0;">当前驱动：{{ $driver }}</p>
    </div>
</body>
</html>
