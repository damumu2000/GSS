<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$manifestPath = $root.'/public/vendor/vendor-assets.json';

if (!is_file($manifestPath)) {
    fwrite(STDERR, "[static-vendors] manifest not found: {$manifestPath}\n");
    exit(1);
}

$manifest = json_decode((string) file_get_contents($manifestPath), true);

if (!is_array($manifest)) {
    fwrite(STDERR, "[static-vendors] invalid manifest json\n");
    exit(1);
}

$latestResolver = static function (string $package): ?string {
    $url = "https://registry.npmjs.org/{$package}/latest";
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: GSS-static-vendor-check\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $payload = json_decode($response, true);
    return is_array($payload) ? ($payload['version'] ?? null) : null;
};

$exitCode = 0;

foreach ($manifest as $name => $asset) {
    $package = (string) ($asset['package'] ?? $name);
    $version = (string) ($asset['version'] ?? '');
    $file = (string) ($asset['file'] ?? '');
    $expectedSha = strtolower((string) ($asset['sha256'] ?? ''));
    $fullPath = $root.'/'.ltrim($file, '/');

    echo "[static-vendors] {$name}\n";
    echo "  package: {$package}\n";
    echo "  pinned : {$version}\n";
    echo "  file   : {$file}\n";

    if (!is_file($fullPath)) {
        echo "  status : missing local file\n\n";
        $exitCode = 1;
        continue;
    }

    $actualSha = strtolower(hash_file('sha256', $fullPath));
    echo "  sha256 : {$actualSha}\n";

    if ($expectedSha !== '' && $actualSha !== $expectedSha) {
        echo "  status : checksum mismatch\n";
        $exitCode = 1;
    } else {
        echo "  status : local file ok\n";
    }

    $latest = $latestResolver($package);
    if ($latest === null || $latest === '') {
        echo "  latest : unavailable\n";
    } else {
        echo "  latest : {$latest}\n";
        if ($latest !== $version) {
            echo "  update : newer version available\n";
        }
    }

    echo "\n";
}

exit($exitCode);
