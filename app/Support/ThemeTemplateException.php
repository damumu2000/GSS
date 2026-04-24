<?php

namespace App\Support;

use RuntimeException;

class ThemeTemplateException extends RuntimeException
{
    protected string $detailMessage;

    protected ?string $templateName;

    protected ?int $templateLine;

    public function __construct(string $detailMessage, ?string $templateName = null, ?int $templateLine = null)
    {
        $this->detailMessage = $detailMessage;
        $this->templateName = $templateName;
        $this->templateLine = $templateLine;

        parent::__construct($this->buildMessage());
    }

    public static function syntax(string $message, ?string $templateName = null, ?int $templateLine = null): self
    {
        return new self($message, $templateName, $templateLine);
    }

    public static function unsupported(string $statement): self
    {
        return self::syntax('不支持的标签语句 '.$statement);
    }

    public function hasLocation(): bool
    {
        return $this->templateName !== null || $this->templateLine !== null;
    }

    public function withLocation(?string $templateName = null, ?int $templateLine = null): self
    {
        if ($this->templateName === null && $templateName !== null && $templateName !== '') {
            $this->templateName = $templateName;
        }

        if ($this->templateLine === null && $templateLine !== null && $templateLine > 0) {
            $this->templateLine = $templateLine;
        }

        $this->message = $this->buildMessage();

        return $this;
    }

    protected function buildMessage(): string
    {
        $locations = [];

        if ($this->templateName !== null && $this->templateName !== '') {
            $locations[] = '模板 '.$this->templateName.'.tpl';
        }

        if ($this->templateLine !== null && $this->templateLine > 0) {
            $locations[] = '第 '.$this->templateLine.' 行';
        }

        $suffix = $locations === [] ? '' : '（'.implode('，', $locations).'）';

        return '模板解析失败：'.$this->detailMessage.$suffix;
    }
}
