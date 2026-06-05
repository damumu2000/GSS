@include('errors.minimal', [
    'code' => '503',
    'pageTitle' => ($site->name ?? '站点').' - 站点模板未启用',
    'title' => $isPreview ? '当前内容暂时无法预览' : '当前站点暂未启用可用模板',
    'description' => $isPreview ? '该内容所属站点当前还没有配置可用模板，因此暂时无法按前台样式预览。' : '该站点当前还没有配置可用模板，因此前台页面暂时无法正常显示。',
])
