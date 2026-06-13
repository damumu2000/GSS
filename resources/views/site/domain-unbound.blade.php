@include('errors.minimal', [
    'code' => '404',
    'pageTitle' => '404 - 域名未绑定站点',
    'title' => '当前域名尚未绑定站点',
    'description' => '该域名已接入系统，但暂未分配可访问站点。访问域名：'.($host ?? '--'),
])
