<?php
/** @var array $tape */
/** @var string $token */
/** @var string $csrf */
/** @var string $shareUrl */
$opened = (int) $tape['views'];
?>
<main class="mx-auto w-full max-w-2xl px-5 pt-6 sm:px-8">
  <h1 class="hero-title hero-title--small">Your mixtape</h1>
  <p class="mt-4 text-lg text-field-soft">
    Opened <?= $opened ?> <?= $opened === 1 ? 'time' : 'times' ?>. That count includes your own visits.
  </p>

  <div class="mt-8 grid gap-3">
    <label for="share" class="font-semibold">Link to send</label>
    <input id="share" class="field field--on-field" type="text" readonly value="<?= e($shareUrl) ?>">
    <a href="<?= e($shareUrl) ?>" class="link-quiet w-fit">Open the mixtape</a>
  </div>

  <details class="mt-12 max-w-md border-t-2 border-field-line pt-6">
    <summary class="cursor-pointer font-semibold">Delete this mixtape</summary>
    <form method="post" action="/m/<?= e($tape['id']) ?>/delete" class="mt-4 grid gap-4">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <p class="text-field-soft">The link stops working right away. This can't be undone.</p>
      <button type="submit" class="btn-solid w-fit">Delete for good</button>
    </form>
  </details>
</main>
