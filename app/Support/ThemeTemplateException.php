<?php

namespace App\Support;

use RuntimeException;

class ThemeTemplateException extends RuntimeException
{
    public static function syntax(string $message): self
    {
        return new self('模板解析失败：'.$message);
    }

    public static function unsupported(string $statement): self
    {
        return self::syntax('不支持的标签语句 '.$statement);
    }
}
