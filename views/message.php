<?php
/** @var string $heading */
/** @var string $body */
$cta = $cta ?? false;
?>
<main class="mx-auto w-full max-w-6xl px-5 pt-10 sm:px-8">
  <h1 class="hero-title hero-title--small"><?= e($heading) ?></h1>
  <p class="mt-5 max-w-md text-lg leading-relaxed text-field-soft"><?= e($body) ?></p>
<?php if ($cta): ?>
  <a href="/new" class="btn-solid mt-8">Make a mixtape</a>
<?php endif ?>
</main>
