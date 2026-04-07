<?php

return [
    'accepted' => '请确认并接受该项内容。',
    'array' => '该项内容格式不正确。',
    'boolean' => '该项内容格式不正确。',
    'confirmed' => '两次输入的内容不一致，请重新确认。',
    'email' => '请输入正确的邮箱地址。',
    'exists' => '所选内容不存在，请重新选择。',
    'image' => '请上传图片格式的文件。',
    'in' => '所选内容无效，请重新选择。',
    'integer' => '请输入正确的整数。',
    'max' => [
        'string' => '内容长度不能超过 :max 个字符。',
        'file' => '上传文件不能超过 :max KB。',
        'array' => '最多只能选择 :max 项内容。',
    ],
    'mimes' => '文件格式不支持，请重新选择。',
    'min' => [
        'string' => '内容长度不能少于 :min 个字符。',
        'array' => '请至少选择 :min 项内容。',
    ],
    'size' => [
        'string' => '内容长度必须为 :size 个字符。',
        'array' => '内容数量必须为 :size 项。',
        'file' => '文件大小必须为 :size KB。',
        'numeric' => '数值必须为 :size。',
    ],
    'numeric' => '请输入正确的数字。',
    'regex' => '内容格式不正确，请按要求重新填写。',
    'required' => '该项为必填项，请填写内容。',
    'string' => '内容格式不正确，请重新填写。',
    'unique' => '该内容已存在，请更换后再试。',
    'uploaded' => '文件上传失败，可能超过了服务器允许的上传大小，请检查文件体积后重试。',

    'custom' => [
        'code' => [
            'regex' => '角色标识格式不正确，请使用小写字母、数字和下划线，并以字母开头。',
        ],
        'password' => [
            'min' => '密码长度不能少于 :min 位。',
        ],
        'role_ids' => [
            'required' => '请至少选择一个角色。',
            'min' => '请至少选择一个角色。',
        ],
    ],

    'attributes' => [
        'username' => '用户名',
        'password' => '密码',
        'name' => '名称',
        'title' => '标题',
        'code' => '角色标识',
        'description' => '角色说明',
        'site_id' => '站点',
        'site_key' => '站点标识',
        'theme_code' => '主题标识',
        'template' => '模板文件',
        'template_source' => '模板内容',
        'role_ids' => '角色',
        'channel_ids' => '栏目',
        'file' => '上传文件',
        'action' => '操作类型',
        'ids' => '选择项',
        'status' => '状态',
        'version' => '版本号',
    ],
];
