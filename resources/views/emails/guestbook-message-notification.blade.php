<div style="font-size:14px;line-height:1.8;color:#1f2937;">
    <p>站点：{{ $siteName !== '' ? $siteName : '未命名站点' }}</p>
    <p>通知类型：{{ $trigger === 'replied' ? '留言已回复通知' : '新留言提交通知' }}</p>
    <p>留言编号：{{ str_pad((string) $displayNo, 5, '0', STR_PAD_LEFT) }}</p>
    <p>提交时间：{{ $createdAt !== '' ? $createdAt : '未知' }}</p>
    <p>称呼：{{ $name !== '' ? $name : '未填写' }}</p>
    <p>手机号：{{ $phone !== '' ? $phone : '未填写' }}</p>
    <p>当前状态：{{ $status === 'replied' ? '已回复' : '待回复' }}</p>
    <p>留言内容：</p>
    <div style="padding:12px 14px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc;white-space:pre-wrap;">{{ $contentText !== '' ? $contentText : '无内容' }}</div>
    @if ($replyContent !== '')
        <p style="margin-top:16px;">回复内容：</p>
        <div style="padding:12px 14px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc;white-space:pre-wrap;">{{ $replyContent }}</div>
    @endif
</div>
