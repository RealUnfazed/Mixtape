<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

// Minimal .env loader: KEY=value per line, "#" starts a comment. Real env vars win.
(static function (): void {
    $file = APP_ROOT . '/.env';
    if (!is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv("$key=$value");
        }
    }
})();

function env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

/** True when running on Vercel, which sets these variables for every deployment. */
function on_vercel(): bool
{
    return env('VERCEL') !== '' || env('VERCEL_ENV') !== '' || env('VERCEL_REGION') !== '';
}

/** Every request on Vercel passes through its proxy, so its forwarded headers can be trusted there. */
function trust_proxy(): bool
{
    return env('TRUST_PROXY') === '1' || on_vercel();
}

const TAPE_COLORS = ['clover' => 'Clover', 'lemon' => 'Lemon', 'cherry' => 'Cherry', 'bluebell' => 'Bluebell'];
const STICKERS = ['bow' => 'Bow', 'daisies' => 'Daisies', 'sprig' => 'Sprig'];

function limits(): array
{
    static $limits;
    return $limits ??= [
        'max_songs' => max(1, min(20, (int) env('MAX_SONGS', '6'))),
        'letter_max' => max(50, min(2000, (int) env('LETTER_MAX', '1200'))),
        'name_max' => 40,
        'title_max' => 120,
        'artist_max' => 80,
        'create_per_hour' => max(1, (int) env('CREATE_LIMIT_PER_HOUR', '10')),
    ];
}

error_reporting(E_ALL);
ini_set('display_errors', env('APP_DEBUG') === '1' ? '1' : '0');

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/helpers.php';
