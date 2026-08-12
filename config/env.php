<?php
/**
 * Minimal .env loader (no Composer dependency). Reads KEY=VALUE lines
 * from .env at the project root into getenv()/$_ENV, without
 * overwriting variables already set by the real environment
 * (so real deployments can set env vars however they like).
 */
function load_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = __DIR__ . '/../.env';
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\"'");
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value !== false ? $value : $default;
}
