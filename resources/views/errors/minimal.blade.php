@php
    $code = (string) ($code ?? 404);
    $title = (string) ($title ?? '抱歉，页面无法访问');
    $description = (string) ($description ?? '您请求的页面可能已被删除、更名或暂时不可用。请检查输入的网址是否正确。');
    $pageTitle = (string) ($pageTitle ?? ($code.' - '.$title));
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $pageTitle }}</title>
    <style>
        :root{--primary-color:#0066ff;--text-main:#1f2329;--text-muted:#646a73;--bg-color:#f5f7fa}*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif;background:var(--bg-color);color:var(--text-main);display:flex;align-items:center;justify-content:center;height:100vh;overflow:hidden}.container{text-align:center;padding:2rem;max-width:500px;width:100%;animation:fadeIn .6s ease-out}.error-code{font-size:120px;font-weight:800;line-height:1;background:linear-gradient(135deg,var(--text-main) 30%,var(--text-muted));-webkit-background-clip:text;-webkit-text-fill-color:transparent;letter-spacing:-2px;margin-bottom:16px;position:relative}.error-code::after{content:"";position:absolute;bottom:-10px;left:50%;transform:translateX(-50%);width:40px;height:4px;background:var(--primary-color);border-radius:2px}.error-title{font-size:24px;font-weight:600;color:var(--text-main);margin-top:32px;margin-bottom:12px}.error-desc{font-size:15px;color:var(--text-muted);line-height:1.6}@keyframes fadeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}@media (max-width:480px){.error-code{font-size:90px}.error-title{font-size:20px}}
    </style>
</head>
<body>
    <div class="container">
        <div class="error-code">{{ $code }}</div>
        <h1 class="error-title">{{ $title }}</h1>
        <p class="error-desc">{{ $description }}</p>
    </div>
</body>
</html>
