<?php

return [
    'proxies' => trim((string) env('TRUSTED_PROXIES', '')) ?: null,
];
