<?php
declare(strict_types=1);

// The whole app: routes and handlers. Both entry points load this file:
//   public/index.php   Apache, nginx and `php -S`
//   api/index.php      Vercel

require_once __DIR__ . '/bootstrap.php';

$isHead = $_SERVER['REQUEST_METHOD'] === 'HEAD';
$method = $isHead ? 'GET' : $_SERVER['REQUEST_METHOD'];
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';
$isApi = str_starts_with($path, '/api/');

set_exception_handler(static function (Throwable $e) use ($isApi): void {
    error_log($e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if ($isApi) {
        json_response(['error' => 'Something went wrong on our side. Try again in a moment.'], 500);
    }
    render('message', [
        'heading' => 'Something went wrong',
        'body' => 'The page could not load. Try again in a moment.',
    ], ['title' => 'Something went wrong'], 500);
    exit;
});

function not_found(): never
{
    render('message', [
        'heading' => 'No mixtape here',
        'body' => 'This link may be mistyped, or the mixtape was deleted.',
        'cta' => true,
    ], ['title' => 'Mixtape not found', 'noindex' => true], 404);
    exit;
}

function require_api_csrf(): void
{
    if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null, 'api')) {
        json_response(['error' => 'This page has expired. Reload it and try again.'], 403);
    }
}

// ---- Pages -------------------------------------------------------------------------------------

if ($method === 'GET' && $path === '/') {
    render('home', ['deleted' => isset($_GET['deleted'])], [
        'title' => 'Mixtape: send someone a playlist with a letter',
        'scripts' => ['home.js'],
    ]);
    exit;
}

if ($method === 'GET' && $path === '/new') {
    render('builder', [
        'limits' => limits(),
        'searchEnabled' => env('YOUTUBE_API_KEY') !== '',
        'csrf' => csrf_token('api'),
    ], ['title' => 'Make a mixtape', 'scripts' => ['app.js'], 'noindex' => true, 'cta' => false]);
    exit;
}

if ($method === 'GET' && preg_match('~^/m/([a-z0-9]{8})$~', $path, $m)) {
    $tape = MixtapeRepo::find($m[1]) ?? not_found();
    if (!$isHead) {
        MixtapeRepo::recordView($m[1]);
    }
    $count = count($tape['tracks']);
    $for = $tape['to_name'] !== '' ? ' for ' . $tape['to_name'] : '';
    render('show', ['tape' => $tape], [
        'title' => 'A mixtape' . $for,
        'description' => ($tape['from_name'] !== '' ? "From {$tape['from_name']}. " : '') . "$count " . ($count === 1 ? 'song' : 'songs') . ' and a letter. Press play.',
        'tape' => $tape['tape_color'],
        'youtube' => true,
        'noindex' => true,
        'scripts' => ['player.js'],
        'image' => thumb($tape['tracks'][0]['video_id'], 'hqdefault'),
    ]);
    exit;
}

if ($method === 'GET' && preg_match('~^/m/([a-z0-9]{8})/manage/([a-f0-9]{32})$~', $path, $m)) {
    if (!MixtapeRepo::manageTokenMatches($m[1], $m[2])) {
        not_found();
    }
    $tape = MixtapeRepo::find($m[1]) ?? not_found();
    render('manage', [
        'tape' => $tape,
        'token' => $m[2],
        'csrf' => csrf_token('delete:' . $m[1]),
        'shareUrl' => base_url() . '/m/' . $m[1],
    ], [
        'title' => 'Manage your mixtape',
        'tape' => $tape['tape_color'],
        'noindex' => true,
    ]);
    exit;
}

if ($method === 'POST' && preg_match('~^/m/([a-z0-9]{8})/delete$~', $path, $m)) {
    $token = (string) ($_POST['token'] ?? '');
    if (!csrf_valid($_POST['csrf'] ?? null, 'delete:' . $m[1]) || !MixtapeRepo::manageTokenMatches($m[1], $token)) {
        not_found();
    }
    MixtapeRepo::delete($m[1]);
    header('Location: /?deleted=1', true, 303);
    exit;
}

// ---- JSON API ----------------------------------------------------------------------------------

if ($method === 'GET' && $path === '/api/lookup') {
    require_api_csrf();
    $id = Youtube::extractId((string) ($_GET['url'] ?? ''));
    if ($id === null) {
        json_response(['error' => "That doesn't look like a YouTube link. Paste a link from youtube.com or youtu.be."], 422);
    }
    json_response(Youtube::lookup($id));
}

if ($method === 'GET' && $path === '/api/search') {
    require_api_csrf();
    $key = env('YOUTUBE_API_KEY');
    if ($key === '') {
        json_response(['error' => 'Search is not set up here. Paste a YouTube link instead.'], 501);
    }
    $query = trim((string) ($_GET['q'] ?? ''));
    if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
        json_response(['error' => 'Type at least two characters to search.'], 422);
    }
    $results = Youtube::search($query, $key);
    if ($results === null) {
        json_response(['error' => "Search isn't available right now. Paste a YouTube link instead."], 502);
    }
    json_response(['results' => $results]);
}

if ($method === 'POST' && $path === '/api/mixtapes') {
    require_api_csrf();
    $raw = (string) file_get_contents('php://input', false, null, 0, 40001);
    if (strlen($raw) > 40000) {
        json_response(['error' => 'That mixtape is too big to send.'], 413);
    }
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        json_response(['error' => 'The mixtape could not be read. Reload the page and try again.'], 400);
    }
    [$clean, $errors] = MixtapeInput::parse($input);
    if ($errors) {
        json_response(['error' => $errors[0], 'errors' => $errors], 422);
    }
    $creator = client_hash();
    if (MixtapeRepo::recentCount($creator) >= limits()['create_per_hour']) {
        json_response(['error' => "You've made a lot of mixtapes this hour. Try again later."], 429);
    }
    $made = MixtapeRepo::create($clean, $creator);
    $base = base_url();
    json_response([
        'id' => $made['id'],
        'url' => "$base/m/{$made['id']}",
        'manageUrl' => "$base/m/{$made['id']}/manage/{$made['manageToken']}",
    ], 201);
}

if ($isApi) {
    json_response(['error' => 'Not found.'], 404);
}
not_found();
