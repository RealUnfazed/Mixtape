<?php
declare(strict_types=1);

// Plain PHP tests, no framework needed.   Run:  php tests/run.php
putenv('MAX_SONGS=3');
putenv('LETTER_MAX=100');
// Define these as empty first so a real .env cannot change what the tests expect.
foreach (['APP_SECRET', 'APP_URL', 'TRUST_PROXY', 'DATABASE_URL', 'POSTGRES_URL', 'DB_EMULATE_PREPARES', 'DB_SSLMODE',
    'VERCEL', 'VERCEL_ENV', 'VERCEL_REGION', 'VERCEL_URL', 'VERCEL_PROJECT_PRODUCTION_URL', 'VERCEL_GIT_COMMIT_SHA', 'VERCEL_DEPLOYMENT_ID'] as $name) {
    putenv("$name=");
}
require __DIR__ . '/../src/bootstrap.php';

$failures = 0;
function check(string $name, mixed $actual, mixed $expected): void
{
    global $failures;
    if ($actual === $expected) {
        echo "  ok    $name\n";
        return;
    }
    $failures++;
    echo "  FAIL  $name\n        expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n        got      " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "Youtube::extractId\n";
$id = 'dQw4w9WgXcQ';
foreach ([
    "https://www.youtube.com/watch?v=$id" => $id,
    "https://www.youtube.com/watch?v=$id&list=PL123&t=42s" => $id,
    "http://youtube.com/watch?feature=share&v=$id" => $id,
    "https://youtu.be/$id?si=abc" => $id,
    "https://m.youtube.com/watch?v=$id" => $id,
    "https://music.youtube.com/watch?v=$id&si=x" => $id,
    "https://www.youtube.com/shorts/$id" => $id,
    "https://www.youtube.com/embed/$id" => $id,
    "https://www.youtube.com/live/$id?feature=share" => $id,
    "www.youtube.com/watch?v=$id" => $id,
    $id => $id,
    "  https://youtu.be/$id  " => $id,
    'https://www.youtube.com/playlist?list=PL123' => null,
    'https://www.youtube.com/watch?v=short' => null,
    "https://vimeo.com/watch?v=$id" => null,
    "https://youtube.com.evil.example/watch?v=$id" => null,
    "https://youtube.com@evil.example/watch?v=$id" => null,
    'not a link' => null,
    '' => null,
] as $input => $expected) {
    check(trim($input) === '' ? '(empty)' : trim($input), Youtube::extractId((string) $input), $expected);
}

echo "Youtube::tidy\n";
check('artist - title (Official Video)', Youtube::tidy('Radiohead - Creep (Official Video)', 'Radiohead'), ['Creep', 'Radiohead']);
check('title only, channel is artist', Youtube::tidy('Never Gonna Give You Up (Official Music Video)', 'Rick Astley'), ['Never Gonna Give You Up', 'Rick Astley']);
check('VEVO suffix', Youtube::tidy('Get Lucky', 'DaftPunkVEVO'), ['Get Lucky', 'DaftPunk']);
check('Topic suffix', Youtube::tidy('Windowlicker', 'Aphex Twin - Topic'), ['Windowlicker', 'Aphex Twin']);
check('en dash and [Lyrics]', Youtube::tidy('Bon Iver – Skinny Love [Lyrics]', 'LyricChannel'), ['Skinny Love', 'Bon Iver']);
check('entities decoded', Youtube::tidy('Guns N&#39; Roses - Sweet Child O&#39; Mine', 'x'), ["Sweet Child O' Mine", "Guns N' Roses"]);
check('hyphen inside a word is kept', Youtube::tidy('Hip-Hop Is Dead', 'Nas'), ['Hip-Hop Is Dead', 'Nas']);

echo "Youtube::parseSearch\n";
$parsed = Youtube::parseSearch(['items' => [
    ['id' => ['videoId' => $id], 'snippet' => ['title' => 'A - B (Official Audio)', 'channelTitle' => 'Chan']],
    ['id' => ['videoId' => 'bad'], 'snippet' => ['title' => 'Skipped', 'channelTitle' => 'Chan']],
    ['id' => ['kind' => 'youtube#channel'], 'snippet' => ['title' => 'Skipped', 'channelTitle' => 'Chan']],
]]);
check('keeps valid videos only', count($parsed), 1);
check('maps fields', [$parsed[0]['title'], $parsed[0]['artist']], ['B', 'A']);

echo "MixtapeInput::parse\n";
$good = [
    'tapeColor' => 'cherry',
    'stickers' => ['bow', 'bow', 'nope'],
    'toName' => "  Mira \n",
    'fromName' => 'Deniz',
    'letter' => "Hi\r\n\r\n\r\n\r\nThere",
    'tracks' => [['videoId' => "https://youtu.be/$id", 'title' => ' Song ', 'artist' => '']],
];
[$clean, $errors] = MixtapeInput::parse($good);
check('valid input has no errors', $errors, []);
check('stickers de-duplicated and filtered', $clean['stickers'] ?? null, ['bow']);
check('names trimmed', [$clean['toName'] ?? null, $clean['fromName'] ?? null], ['Mira', 'Deniz']);
check('blank lines collapsed', $clean['letter'] ?? null, "Hi\n\nThere");
check('track link reduced to id', $clean['tracks'][0]['videoId'] ?? null, $id);

[$none, $errors] = MixtapeInput::parse(['tapeColor' => 'pink', 'letter' => '', 'tracks' => []]);
check('invalid input is rejected', $none, null);
check('reports color, letter and songs', count($errors), 3);

[, $errors] = MixtapeInput::parse([...$good, 'letter' => str_repeat('a', 101)]);
check('letter over the limit', $errors, ['The letter is over 100 characters.']);

[, $errors] = MixtapeInput::parse([...$good, 'tracks' => array_fill(0, 4, $good['tracks'][0])]);
check('too many songs', $errors, ['A mixtape holds up to 3 songs.']);

[, $errors] = MixtapeInput::parse([...$good, 'tracks' => [['videoId' => $id, 'title' => '']]]);
check('missing title', $errors, ['Add a title for song 1.']);

[, $errors] = MixtapeInput::parse([...$good, 'tracks' => [['videoId' => 'nope', 'title' => 'x']]]);
check('bad video id', $errors, ["Song 1 isn't a valid YouTube video."]);

echo "Db::fromUrl\n";
function dsn_of(array $config): string
{
    return Db::dsn($config['settings']);
}
$neon = Db::fromUrl('postgresql://neondb_owner:npg_a%2Bb%40c@ep-cool-123456-pooler.eu-central-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require');
check('Neon URL becomes a DSN', dsn_of($neon), 'pgsql:host=ep-cool-123456-pooler.eu-central-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require;channel_binding=require;connect_timeout=8');
check('user and decoded password (+ and @ survive)', [$neon['user'], $neon['password']], ['neondb_owner', 'npg_a+b@c']);
check('pooled host emulates prepares', $neon['emulate'], true);

$direct = Db::fromUrl('postgres://u:p@db.example.com:5432/app?sslmode=require&connect_timeout=3&pgbouncer=false&supa=base');
check('direct host: only libpq options are kept', dsn_of($direct), 'pgsql:host=db.example.com;port=5432;dbname=app;sslmode=require;connect_timeout=3');
check('direct host uses real prepared statements', $direct['emulate'], false);

$supabase = Db::fromUrl('postgres://postgres.abc:pw@aws-0-eu-west-1.pooler.supabase.com:6543/postgres?pgbouncer=true');
check('Supabase pooler emulates prepares', $supabase['emulate'], true);

$local = Db::fromUrl('postgres://mixtape:mixtape@127.0.0.1:5433/mixtape');
check('local database', dsn_of($local), 'pgsql:host=127.0.0.1;port=5433;dbname=mixtape;connect_timeout=8');

$hostile = Db::fromUrl('postgres://u:p@h.example.com/db?sslmode=disable%3Bhost%3Devil&application_name=a%20b');
check('odd option values are dropped', dsn_of($hostile), 'pgsql:host=h.example.com;port=5432;dbname=db;connect_timeout=8');

try {
    Db::fromUrl('not a url');
    $threw = false;
} catch (RuntimeException) {
    $threw = true;
}
check('a URL without a host is rejected', $threw, true);

putenv('DB_EMULATE_PREPARES=1');
check('DB_EMULATE_PREPARES=1 forces emulation', Db::fromUrl('postgres://u:p@db.example.com/app')['emulate'], true);
putenv('DB_EMULATE_PREPARES=0');
check('DB_EMULATE_PREPARES=0 turns it off for poolers', Db::fromUrl('postgres://u:p@x-pooler.example.com/app')['emulate'], false);
putenv('DB_EMULATE_PREPARES=');

putenv('VERCEL=1');
check('on Vercel a remote database gets sslmode=require', dsn_of(Db::fromUrl('postgres://u:p@db.example.com/app')), 'pgsql:host=db.example.com;port=5432;dbname=app;sslmode=require;connect_timeout=8');
check('on Vercel an explicit sslmode is respected', dsn_of(Db::fromUrl('postgres://u:p@db.example.com/app?sslmode=disable')), 'pgsql:host=db.example.com;port=5432;dbname=app;sslmode=disable;connect_timeout=8');
check('on Vercel localhost is left alone', dsn_of(Db::fromUrl('postgres://u:p@localhost/app')), 'pgsql:host=localhost;port=5432;dbname=app;connect_timeout=8');
putenv('VERCEL=');

putenv('DATABASE_URL=');
putenv('POSTGRES_URL=postgres://v:w@vercel-db.example.com/main');
check('POSTGRES_URL is used when DATABASE_URL is empty', Db::config()['user'], 'v');
putenv('DATABASE_URL=postgres://first:w@a.example.com/main');
check('DATABASE_URL wins over POSTGRES_URL', Db::config()['user'], 'first');
putenv('DATABASE_URL=');
putenv('POSTGRES_URL=');
putenv('DB_HOST=db.internal');
putenv('DB_PASSWORD=pw');
putenv('DB_SSLMODE=require');
check('separate DB_* settings', dsn_of(Db::config()), 'pgsql:host=db.internal;port=5432;dbname=mixtape;sslmode=require;connect_timeout=8');
putenv('DB_HOST');
putenv('DB_PASSWORD');
putenv('DB_SSLMODE=');

echo "Db::adjustForOldClient\n";
$neonConfig = Db::fromUrl('postgres://u:p@ep-cool-123456-pooler.eu-central-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require');
$sniError = 'SQLSTATE[08006] [7] connection to server at "ep-cool-123456-pooler.eu-central-1.aws.neon.tech" (3.64.1.2), port 5432 failed: ERROR:  The endpoint ID is not specified. Either upgrade the Postgres client library (libpq) for SNI support or pass the endpoint ID (the first part of the domain name) as a parameter: \'&options=endpoint%3D<endpoint-id>\'.';
$fixed = Db::adjustForOldClient($neonConfig, $sniError);
check('a missing endpoint ID is added to the options (SNI workaround)', $fixed === null ? null : dsn_of($fixed), 'pgsql:host=ep-cool-123456-pooler.eu-central-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require;channel_binding=require;connect_timeout=8;options=\'endpoint=ep-cool-123456-pooler\'');
check('...only once', $fixed === null ? null : Db::adjustForOldClient($fixed, $sniError), null);
$noChannel = Db::adjustForOldClient($neonConfig, 'SQLSTATE[08006] [7] invalid connection option "channel_binding"');
check('an option the client library does not know is dropped', $noChannel === null ? null : dsn_of($noChannel), 'pgsql:host=ep-cool-123456-pooler.eu-central-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require;connect_timeout=8');
check('other errors are not touched', Db::adjustForOldClient($neonConfig, 'SQLSTATE[08006] [7] FATAL: password authentication failed for user "u"'), null);
check('an option we never set is not touched', Db::adjustForOldClient($neonConfig, 'invalid connection option "nonsense"'), null);
check('the adjusted config keeps user, password and emulation', $fixed === null ? null : [$fixed['user'], $fixed['password'], $fixed['emulate']], ['u', 'p', true]);

echo "app_secret\n";
putenv('APP_SECRET=s3cret');
check('APP_SECRET wins', app_secret(), 's3cret');
putenv('APP_SECRET=');
putenv('DATABASE_URL=postgres://a:b@h.example.com/db');
check('without it, a private value is derived from the database URL', [app_secret() !== 'dev-secret-change-me', strlen(app_secret())], [true, 64]);
putenv('DATABASE_URL=');
check('plain local development default', app_secret(), 'dev-secret-change-me');
putenv('VERCEL=1');
try {
    app_secret();
    $threw = false;
} catch (RuntimeException) {
    $threw = true;
}
check('on Vercel with nothing configured it refuses to run', $threw, true);
putenv('VERCEL=');

echo "CSRF tokens\n";
putenv('APP_SECRET=test-secret');
$token = csrf_token('api');
check('a fresh token is valid', csrf_valid($token, 'api'), true);
check('"api" is the default scope', csrf_valid($token), true);
check('a token is tied to its scope', csrf_valid($token, 'delete:abcd1234'), false);
check('scoped tokens work for their own scope', csrf_valid(csrf_token('delete:abcd1234'), 'delete:abcd1234'), true);
[$expires, $signature] = explode('.', $token);
check('a changed signature is refused', csrf_valid($expires . '.' . str_repeat('0', 32)), false);
check('a changed expiry is refused', csrf_valid(dechex(hexdec($expires) + 1000) . '.' . $signature), false);
$past = dechex(time() - 10);
check('an expired token is refused', csrf_valid($past . '.' . csrf_signature('api', $past)), false);
check('empty, missing and malformed tokens are refused', [csrf_valid(''), csrf_valid(null), csrf_valid('abc'), csrf_valid('1.zz')], [false, false, false, false]);
putenv('APP_SECRET=another-secret');
check('a token signed with a different secret is refused', csrf_valid($token), false);

echo "client_ip, is_https and base_url\n";
$_SERVER['REMOTE_ADDR'] = '10.0.0.9';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
check('forwarded headers are ignored by default', client_ip(), '10.0.0.9');
putenv('TRUST_PROXY=1');
check('behind a trusted proxy the forwarded address is used', client_ip(), '203.0.113.7');
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 203.0.113.7';
check('the hop added by our proxy (the last one) counts', client_ip(), '203.0.113.7');
$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-address';
check('garbage falls back to the connecting address', client_ip(), '10.0.0.9');
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
$_SERVER['HTTP_X_VERCEL_FORWARDED_FOR'] = '192.0.2.5';
check('X-Vercel-Forwarded-For is not trusted off Vercel', client_ip(), '203.0.113.7');
putenv('TRUST_PROXY=');
putenv('VERCEL=1');
check('on Vercel it is preferred', client_ip(), '192.0.2.5');
unset($_SERVER['HTTP_X_VERCEL_FORWARDED_FOR']);
check('on Vercel X-Forwarded-For is the fallback', client_ip(), '203.0.113.7');
putenv('VERCEL=');
putenv('APP_SECRET=ip-secret');
unset($_SERVER['HTTP_X_FORWARDED_FOR']);
$_SERVER['REMOTE_ADDR'] = '10.0.0.9';
$mine = client_hash();
check('client_hash is stable and 64 hex characters', [client_hash() === $mine, preg_match('/^[0-9a-f]{64}$/', $mine)], [true, 1]);
$_SERVER['REMOTE_ADDR'] = '10.0.0.10';
check('client_hash changes with the address', client_hash() !== $mine, true);

unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
$_SERVER['HTTP_HOST'] = 'tape.example.com';
check('plain http by default', base_url(), 'http://tape.example.com');
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
check('a forwarded https is ignored unless the proxy is trusted', base_url(), 'http://tape.example.com');
putenv('TRUST_PROXY=1');
check('https behind a trusted proxy', base_url(), 'https://tape.example.com');
putenv('TRUST_PROXY=');
putenv('APP_URL=https://mix.example.org/');
check('APP_URL wins and loses its trailing slash', base_url(), 'https://mix.example.org');
putenv('APP_URL=');
$_SERVER['HTTP_HOST'] = 'evil.example/<x>';
check('a malformed Host header is replaced', base_url(), 'http://localhost');
unset($_SERVER['HTTP_HOST'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
putenv('VERCEL=1');
putenv('VERCEL_URL=my-app-abc123.vercel.app');
check('no Host header on Vercel: use the deployment URL', base_url(), 'https://my-app-abc123.vercel.app');
putenv('VERCEL_PROJECT_PRODUCTION_URL=mixtape.example.com');
check('...preferring the production domain', base_url(), 'https://mixtape.example.com');
putenv('VERCEL=');
putenv('VERCEL_URL=');
putenv('VERCEL_PROJECT_PRODUCTION_URL=');

echo "asset\n";
check('local files are versioned by their modification time', preg_match('~^/assets/app\.css\?v=\d+$~', asset('app.css')), 1);
putenv('VERCEL_DEPLOYMENT_ID=dpl_abcdefghijklmnop');
check('on Vercel the deployment id is the version', asset('app.css'), '/assets/app.css?v=dpl_abcdefgh');
putenv('VERCEL_GIT_COMMIT_SHA=0123456789abcdef0123');
check('the commit wins when there is one', asset('app.css'), '/assets/app.css?v=0123456789ab');
putenv('VERCEL_DEPLOYMENT_ID=');
putenv('VERCEL_GIT_COMMIT_SHA=');

echo $failures === 0 ? "\nAll passed.\n" : "\n$failures failed.\n";
exit($failures === 0 ? 0 : 1);
