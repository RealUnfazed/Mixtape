<?php
declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $config = self::config();
        for ($attempt = 0;; $attempt++) {
            try {
                self::$pdo = new PDO(self::dsn($config['settings']), $config['user'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => $config['emulate'],
                ]);
                return self::$pdo;
            } catch (PDOException $e) {
                $adjusted = $attempt < 3 ? self::adjustForOldClient($config, $e->getMessage()) : null;
                if ($adjusted === null) {
                    throw $e;
                }
                $config = $adjusted;
            }
        }
    }

    /**
     * Connection settings from the environment: DATABASE_URL (Neon and most hosts), then POSTGRES_URL
     * (what Vercel's Postgres integrations add), then the separate DB_* variables.
     *
     * @return array{settings: array<string, string>, user: string, password: string, emulate: bool}
     */
    public static function config(): array
    {
        $url = env('DATABASE_URL') ?: env('POSTGRES_URL');
        if ($url !== '') {
            return self::fromUrl($url);
        }
        return self::build(
            env('DB_HOST', '127.0.0.1'),
            (int) env('DB_PORT', '5432'),
            env('DB_NAME', 'mixtape'),
            env('DB_USER', 'mixtape'),
            env('DB_PASSWORD'),
            ['sslmode' => env('DB_SSLMODE')],
        );
    }

    /** Settings from a postgres:// URL. Public so it can be tested without a database. */
    public static function fromUrl(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            throw new RuntimeException('The database URL is not a valid postgres:// URL.');
        }
        parse_str($parts['query'] ?? '', $query);
        return self::build(
            $parts['host'],
            (int) ($parts['port'] ?? 5432),
            rawurldecode(ltrim($parts['path'] ?? '', '/')),
            rawurldecode($parts['user'] ?? ''),
            rawurldecode($parts['pass'] ?? ''),
            $query,
        );
    }

    /** The PDO data source name for a set of libpq settings. */
    public static function dsn(array $settings): string
    {
        $pairs = [];
        foreach ($settings as $key => $value) {
            $pairs[] = "$key=$value";
        }
        return 'pgsql:' . implode(';', $pairs);
    }

    /**
     * Some PHP builds ship an old libpq. When the server's complaint says so, return the config with the matching
     * fix, so one more connection attempt can succeed. Returns null when there is nothing to adjust.
     */
    public static function adjustForOldClient(array $config, string $message): ?array
    {
        $settings = $config['settings'];
        if (preg_match('/invalid connection option "([A-Za-z_]+)"/', $message, $m) && isset($settings[strtolower($m[1])])) {
            unset($settings[strtolower($m[1])]); // for example channel_binding, which libpq older than 13 does not know
        } elseif (stripos($message, 'endpoint ID is not specified') !== false && !isset($settings['options'])) {
            // Neon finds the database by the host name the client announces (SNI). libpq older than 14 does not
            // announce it, so name the endpoint, which is the first part of the host name, in the options instead.
            $settings['options'] = "'endpoint=" . explode('.', $settings['host'])[0] . "'";
        } else {
            return null;
        }
        return ['settings' => $settings] + $config;
    }

    private static function build(string $host, int $port, string $name, string $user, string $password, array $query): array
    {
        $host = trim($host, '[]');
        $settings = ['host' => $host, 'port' => (string) $port];
        if ($name !== '') {
            $settings['dbname'] = $name;
        }
        // The connection-URL options libpq understands. Others that hosts add (pgbouncer=true, supa=...) are ignored.
        foreach (['sslmode', 'channel_binding', 'connect_timeout', 'application_name'] as $key) {
            $value = $query[$key] ?? '';
            if (is_string($value) && $value !== '' && preg_match('/^[A-Za-z0-9_.\-]+$/', $value)) {
                $settings[$key] = $value;
            }
        }
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_starts_with($host, '/');
        if (!isset($settings['sslmode']) && !$local && on_vercel()) {
            $settings['sslmode'] = 'require'; // the database is across the internet from a serverless function
        }
        $settings['connect_timeout'] ??= '8'; // fail fast instead of hanging until the function times out

        // Poolers such as Neon's and Supabase's run PgBouncer in transaction mode, which cannot keep a prepared
        // statement between queries, so PDO must fill in the parameters itself there.
        $pooled = str_contains($host, '-pooler') || str_contains($host, '.pooler.')
            || $port === 6543 || ($query['pgbouncer'] ?? '') === 'true';
        $emulate = match (env('DB_EMULATE_PREPARES')) {
            '1' => true,
            '0' => false,
            default => $pooled,
        };

        return ['settings' => $settings, 'user' => $user, 'password' => $password, 'emulate' => $emulate];
    }
}
