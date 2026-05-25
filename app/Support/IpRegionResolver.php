<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class IpRegionResolver
{
    public function resolve(?string $ip): string
    {
        $normalizedIp = trim((string) $ip);

        if ($normalizedIp === '' || filter_var($normalizedIp, FILTER_VALIDATE_IP) === false) {
            return '未知来源';
        }

        if (in_array($normalizedIp, ['127.0.0.1', '::1'], true)) {
            return '本地测试环境';
        }

        if ($this->isPrivateOrReservedIp($normalizedIp)) {
            return '内网来源';
        }

        return Cache::remember(
            'site-security:ip-region:'.hash('sha256', $normalizedIp),
            now('Asia/Shanghai')->addDay(),
            fn (): string => $this->lookupPublicRegion($normalizedIp),
        );
    }

    protected function lookupPublicRegion(string $ip): string
    {
        try {
            $resolver = new \Ip2Region();
            $raw = trim((string) $resolver->simple($ip));
        } catch (\Throwable) {
            return '公网来源';
        }

        if ($raw === '' || str_contains($raw, '内网IP') || str_contains($raw, '局域网')) {
            return '公网来源';
        }

        $raw = preg_replace('/【[^】]*】/u', '', $raw) ?? $raw;
        $raw = trim((string) preg_replace('/\s+/u', '', $raw));

        if ($raw === '') {
            return '公网来源';
        }

        if (! str_starts_with($raw, '中国')) {
            $country = preg_replace('/^([^·,，]+).*$/u', '$1', $raw) ?: $raw;

            return '境外·'.$country;
        }

        foreach ([
            '中国香港特别行政区' => '中国香港',
            '中国澳门特别行政区' => '中国澳门',
            '中国台湾省' => '中国台湾',
        ] as $needle => $label) {
            if (str_starts_with($raw, $needle)) {
                return $label;
            }
        }

        $raw = preg_replace('/^中国/u', '', $raw) ?? $raw;
        preg_match('/^(?<province>.*?(?:省|市|自治区|特别行政区))(?<city>.*?(?:市|州|地区|盟))?/u', $raw, $matches);

        $province = $this->normalizeDomesticPart((string) ($matches['province'] ?? ''));
        $city = $this->normalizeDomesticPart((string) ($matches['city'] ?? ''));

        if ($province === '' && $city === '') {
            return '公网来源';
        }

        if ($city === '' || $city === $province) {
            return $province !== '' ? $province : '公网来源';
        }

        return $province.'·'.$city;
    }

    protected function normalizeDomesticPart(string $value): string
    {
        $part = trim($value);

        if ($part === '') {
            return '';
        }

        $part = preg_replace('/(特别行政区|自治区|省|市|地区|自治州|州|盟)$/u', '', $part) ?? $part;

        return trim($part);
    }

    protected function isPrivateOrReservedIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
