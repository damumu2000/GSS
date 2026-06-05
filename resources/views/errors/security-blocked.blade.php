@include('errors.minimal', [
    'code' => '403',
    'pageTitle' => '403 - 请求已被拦截',
    'title' => '当前请求已被安全防护拦截',
    'description' => '安护盾检测到当前请求存在异常，为保护系统与数据安全，已阻止本次访问。',
])
