<?php

declare(strict_types=1);

use Grewire\Database;

define('GREWIRE_ROOT', dirname(__DIR__));

function load_env(string $path): void
{
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key); $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))) $value = substr($value, 1, -1);
        if (getenv($key) === false) putenv($key . '=' . $value);
    }
}

function env(string $key, mixed $default = null): mixed { $value = getenv($key); return $value === false ? $default : $value; }
function env_bool(string $key, bool $default = false): bool { return in_array(strtolower((string) env($key, $default ? 'true' : 'false')), ['1','true','yes','on'], true); }
function json_response(array $payload, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); exit; }

load_env(GREWIRE_ROOT . '/.env');

spl_autoload_register(static function (string $class): void {
    $prefix = 'Grewire\\';
    if (!str_starts_with($class, $prefix)) return;
    $file = GREWIRE_ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require_once $file;
});

function db(): Database {
    static $database;
    return $database ??= new Database([
        'host'=>(string)env('DB_HOST','127.0.0.1'),
        'port'=>(int)env('DB_PORT',5432),
        'name'=>(string)env('DB_NAME','grewire'),
        'user'=>(string)env('DB_USER','grewire'),
        'password'=>(string)env('DB_PASSWORD','')
    ]);
}

function current_user(): ?array
{
    return Grewire\Auth::user(db()->pdo());
}

function require_user(): array
{
    return Grewire\Auth::requireUser(db()->pdo());
}

function dev_identity(): array
{
    $user = current_user();
    if ($user) {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'authenticated' => true,
        ];
    }

    return [
        'id' => null,
        'username' => '',
        'authenticated' => false,
    ];
}
