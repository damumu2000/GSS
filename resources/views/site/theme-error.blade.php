@include('errors.minimal', [
    'code' => '500',
    'pageTitle' => ($site->name ?? '站点').' - 页面暂时无法显示',
    'title' => '页面暂时无法显示',
    'description' => '当前主题模板存在解析问题，请稍后再试或联系管理员处理。',
])
