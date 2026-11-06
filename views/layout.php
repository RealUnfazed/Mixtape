<?php
/** @var string $content */
/** @var array $page */
$url = base_url() . ($_SERVER['REQUEST_URI'] ?? '/');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page['title']) ?></title>
  <meta name="description" content="<?= e($page['description']) ?>">
<?php if ($page['noindex']): ?>
  <meta name="robots" content="noindex, nofollow">
<?php endif ?>
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= e($page['title']) ?>">
  <meta property="og:description" content="<?= e($page['description']) ?>">
<?php if ($page['image']): ?>
  <meta property="og:image" content="<?= e($page['image']) ?>">
  <meta name="twitter:card" content="summary_large_image">
<?php endif ?>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&amp;family=Gaegu:wght@400;700&amp;display=swap">
  <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
</head>
<body data-tape="<?= e($page['tape']) ?>" class="min-h-dvh bg-field font-print text-field-ink antialiased">
  <?php partial('sprite') ?>
  <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-5 py-4 sm:px-8">
    <a href="/" class="wordmark">mixtape</a>
<?php if ($page['cta'] ?? true): ?>
    <a href="/new" class="link-quiet">Make a mixtape</a>
<?php endif ?>
  </header>

  <?= $content ?>

  <footer class="mx-auto w-full max-w-6xl px-5 pb-10 pt-16 text-sm text-field-soft sm:px-8">
    <p>No accounts and no ads. A mixtape stays online until the person who made it deletes it.</p>
  </footer>

<?php foreach ($page['scripts'] as $script): ?>
  <script src="<?= e(asset($script)) ?>" defer></script>
<?php endforeach ?>
</body>
</html>
