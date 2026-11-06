<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL of a file in public/assets/, with a version so browsers fetch it again after a change. */
function asset(string $path): string
{
    // Files in a serverless bundle have no useful modification time, so use the deployment's own id there.
    $version = env('VERCEL_GIT_COMMIT_SHA') ?: env('VERCEL_DEPLOYMENT_ID');
    if ($version === '') {
        $file = APP_ROOT . '/public/assets/' . $path;
        $version = is_file($file) ? (string) filemtime($file) : '0';
    }
    return '/assets/' . $path . '?v=' . rawurlencode(substr($version, 0, 12));
}

function thumb(string $videoId, string $size = 'mqdefault'): string
{
    return 'https://i.ytimg.com/vi/' . rawurlencode($videoId) . '/' . $size . '.jpg';
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $forwarded = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
    return trust_proxy() && $forwarded === 'https';
}

function base_url(): string
{
    $configured = rtrim(env('APP_URL'), '/');
    if ($configured !== '') {
        return $configured;
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $scheme = is_https() ? 'https' : 'http';
    if ($host === '' && on_vercel()) {
        // The runtime did not pass a Host header on. Vercel's own variables name this deployment (always https).
        $host = env('VERCEL_PROJECT_PRODUCTION_URL') ?: env('VERCEL_URL');
        $scheme = 'https';
    }
    if ($host === '' || !preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
        $host = 'localhost';
    }
    return "$scheme://$host";
}

/** The visitor's address. Forwarded headers count only when a proxy we trust is in front of the app. */
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!trust_proxy()) {
        return $ip;
    }
    // Vercel replaces these with the real client address, so a visitor cannot make one up.
    $headers = on_vercel() ? ['HTTP_X_VERCEL_FORWARDED_FOR', 'HTTP_X_FORWARDED_FOR'] : ['HTTP_X_FORWARDED_FOR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $hops = array_map('trim', explode(',', $_SERVER[$header]));
            $last = end($hops); // the hop added by the proxy closest to us
            if ($last !== false && filter_var($last, FILTER_VALIDATE_IP) !== false) {
                return $last;
            }
        }
    }
    return $ip;
}

/**
 * The key that signs CSRF tokens and hashes visitor addresses. Set APP_SECRET to a long random string.
 * The fallbacks only keep a deployment that forgot to set it from using a value that is public in the source.
 */
function app_secret(): string
{
    $configured = env('APP_SECRET');
    if ($configured !== '') {
        return $configured;
    }
    $database = env('DATABASE_URL') ?: env('POSTGRES_URL');
    if ($database !== '') {
        return hash('sha256', 'mixtape-secret|' . $database); // private to this deployment
    }
    if (on_vercel()) {
        throw new RuntimeException('APP_SECRET is not set.');
    }
    return 'dev-secret-change-me';
}

/** Hash of the visitor's address. Only used to rate-limit creation; the address itself is never stored. */
function client_hash(): string
{
    return hash_hmac('sha256', client_ip(), app_secret());
}

const CSRF_LIFETIME = 86400;

function csrf_signature(string $scope, string $expires): string
{
    return substr(hash_hmac('sha256', "csrf|$scope|$expires", app_secret()), 0, 32);
}

/**
 * A signed token that expires on its own. Nothing is stored on the server, so it works when each request
 * lands on a different serverless instance. The scope ties a token to one purpose ("api", "delete:<id>").
 */
function csrf_token(string $scope = 'api'): string
{
    $expires = dechex(time() + CSRF_LIFETIME);
    return $expires . '.' . csrf_signature($scope, $expires);
}

function csrf_valid(?string $token, string $scope = 'api'): bool
{
    if (!is_string($token) || !preg_match('/^([0-9a-f]{1,12})\.([0-9a-f]{32})$/', $token, $m)) {
        return false;
    }
    return hexdec($m[1]) >= time() && hash_equals(csrf_signature($scope, $m[1]), $m[2]);
}

function send_security_headers(bool $youtube = false): void
{
    if (headers_sent()) {
        return;
    }
    $scripts = "'self'" . ($youtube ? ' https://www.youtube.com https://s.ytimg.com' : '');
    header('Content-Security-Policy: ' . implode('; ', [
        "default-src 'self'",
        "script-src $scripts",
        "style-src 'self' https://fonts.googleapis.com",
        'font-src https://fonts.gstatic.com',
        "img-src 'self' https://i.ytimg.com data:",
        'frame-src https://www.youtube-nocookie.com https://www.youtube.com',
        "connect-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
    ]));
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

/** Render views/<view>.php inside views/layout.php. */
function render(string $view, array $data = [], array $page = [], int $status = 200): void
{
    $content = (static function () use ($view, $data): string {
        extract($data, EXTR_SKIP);
        ob_start();
        require APP_ROOT . '/views/' . $view . '.php';
        return (string) ob_get_clean();
    })();

    $page += [
        'title' => 'Mixtape',
        'description' => 'Pick songs, write a letter, send one link.',
        'tape' => 'clover',
        'youtube' => false,
        'noindex' => false,
        'cta' => true,
        'scripts' => [],
        'image' => null,
    ];

    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    if ($page['noindex']) {
        header('X-Robots-Tag: noindex, nofollow');
    }
    send_security_headers((bool) $page['youtube']);
    require APP_ROOT . '/views/layout.php';
}

/** Include views/partials/<name>.php with the given variables. */
function partial(string $name, array $data = []): void
{
    (static function () use ($name, $data): void {
        extract($data, EXTR_SKIP);
        require APP_ROOT . '/views/partials/' . $name . '.php';
    })();
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
