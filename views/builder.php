<?php
/** @var array $limits */
/** @var bool $searchEnabled */
/** @var string $csrf */
?>
<main class="mx-auto grid w-full max-w-6xl gap-10 px-5 pb-6 pt-2 sm:px-8 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-16"
      data-builder data-csrf="<?= e($csrf) ?>" data-max-songs="<?= (int) $limits['max_songs'] ?>"
      data-letter-max="<?= (int) $limits['letter_max'] ?>" data-search="<?= $searchEnabled ? '1' : '0' ?>">

  <section class="w-full max-w-xl lg:sticky lg:top-6 lg:self-start" aria-label="Preview">
    <h1 class="hero-title hero-title--small">Make your mixtape</h1>
    <div class="mt-8">
      <?php partial('cassette', ['tape' => 'clover', 'stickers' => [], 'tracks' => [], 'to' => '', 'label' => 'Preview of your cassette']) ?>
    </div>
  </section>

  <div class="w-full max-w-xl">
    <noscript><p class="text-lg">The mixtape builder needs JavaScript. Turn it on and reload this page.</p></noscript>

    <form id="builder-form" class="grid gap-14" novalidate>
      <section class="grid gap-5" aria-labelledby="step-look">
        <h2 id="step-look" class="step-head"><span class="step__n" aria-hidden="true">1</span>Pick a cassette</h2>
        <div role="radiogroup" aria-label="Cassette color" class="flex flex-wrap gap-5">
<?php foreach (TAPE_COLORS as $key => $name): ?>
          <label class="swatch-pick">
            <input type="radio" name="tapeColor" value="<?= e($key) ?>" class="sr-only"<?= $key === 'clover' ? ' checked' : '' ?>>
            <span class="swatch" data-swatch="<?= e($key) ?>"></span>
            <span class="text-sm font-semibold"><?= e($name) ?></span>
          </label>
<?php endforeach ?>
        </div>
        <div role="group" aria-label="Stickers" class="flex flex-wrap gap-3">
<?php foreach (STICKERS as $key => $name): ?>
          <label class="chip">
            <input type="checkbox" name="stickers" value="<?= e($key) ?>" class="sr-only">
            <svg viewBox="0 0 100 <?= $key === 'bow' ? '80' : '100' ?>" class="size-7" aria-hidden="true"><use href="#st-<?= e($key) ?>"/></svg>
            <span><?= e($name) ?></span>
          </label>
<?php endforeach ?>
        </div>
      </section>

      <section class="grid gap-5" aria-labelledby="step-songs">
        <h2 id="step-songs" class="step-head"><span class="step__n" aria-hidden="true">2</span>Add songs</h2>
        <div class="grid gap-2">
          <label for="song-url" class="font-semibold">Paste a YouTube link</label>
          <div class="flex gap-2">
            <input id="song-url" class="field field--on-field min-w-0 flex-1" type="text" inputmode="url" autocomplete="off" placeholder="https://www.youtube.com/watch?v=…" data-song-url>
            <button type="button" class="btn-solid shrink-0" data-add-song>Add song</button>
          </div>
        </div>
<?php if ($searchEnabled): ?>
        <div class="grid gap-2">
          <label for="song-search" class="font-semibold">Or search by name</label>
          <input id="song-search" class="field field--on-field" type="search" autocomplete="off" placeholder="Song or artist, then press Enter" data-song-search>
          <ul class="results" data-results hidden></ul>
        </div>
<?php endif ?>
        <p class="min-h-6 font-semibold" role="alert" data-song-msg></p>
        <p class="text-field-soft" data-empty>No songs yet. Your first one shows up here.</p>
        <ol class="tracks" data-tracks></ol>
        <p class="text-sm text-field-soft" data-count>0 of <?= (int) $limits['max_songs'] ?> songs</p>
      </section>

      <section class="grid gap-5" aria-labelledby="step-letter">
        <h2 id="step-letter" class="step-head"><span class="step__n" aria-hidden="true">3</span>Write the letter</h2>
        <div class="letter letter--edit">
          <span class="letter__tape" aria-hidden="true"></span>
          <div class="letter__to">
            <label for="to-name" class="sr-only">Their name</label>
            <span aria-hidden="true">Dear</span>
            <input id="to-name" class="letter__input" type="text" maxlength="<?= (int) $limits['name_max'] ?>" placeholder="their name" autocomplete="off" data-to>
            <span aria-hidden="true">,</span>
          </div>
          <label for="letter" class="sr-only">Letter</label>
          <textarea id="letter" class="letter__body letter__textarea" rows="8" maxlength="<?= (int) $limits['letter_max'] ?>" placeholder="Tell them why you picked these songs." data-letter></textarea>
          <div class="letter__from-row">
            <label for="from-name" class="sr-only">Your name</label>
            <input id="from-name" class="letter__input letter__input--from" type="text" maxlength="<?= (int) $limits['name_max'] ?>" placeholder="your name" autocomplete="off" data-from>
          </div>
        </div>
        <p class="text-sm text-field-soft" data-letter-count><?= number_format($limits['letter_max']) ?> characters left</p>
      </section>

      <div class="grid gap-4">
        <div>
          <button type="submit" class="btn-solid" data-submit>Make my mixtape</button>
        </div>
        <ul class="grid gap-1 font-semibold" role="alert" data-errors></ul>
      </div>
    </form>

    <section class="grid gap-6" data-done hidden aria-labelledby="done-title">
      <h2 id="done-title" class="hero-title hero-title--small" tabindex="-1">It's ready</h2>
      <p class="max-w-md text-lg text-field-soft">Send this link. Whoever opens it can play the songs right away.</p>
      <div class="flex gap-2">
        <input id="share-url" class="field field--on-field min-w-0 flex-1" type="text" readonly aria-label="Link to send" data-share-url>
        <button type="button" class="btn-solid shrink-0" data-copy="share-url">Copy link</button>
      </div>
      <a href="/" class="link-quiet w-fit" data-open-link>Open your mixtape</a>

      <div class="private grid gap-3">
        <h3 class="text-lg font-bold">Keep your private link</h3>
        <p class="max-w-md text-field-soft">It's the only way to delete this mixtape later, and nobody can recover it for you.</p>
        <div class="flex gap-2">
          <input id="manage-url" class="field field--on-field min-w-0 flex-1" type="text" readonly aria-label="Private link for deleting" data-manage-url>
          <button type="button" class="btn-solid shrink-0" data-copy="manage-url">Copy</button>
        </div>
      </div>
    </section>
  </div>
</main>

<template id="track-template">
  <li class="track">
    <img class="track__thumb" alt="" width="96" height="54">
    <div class="track__fields">
      <input class="field field--on-field" type="text" maxlength="<?= (int) $limits['title_max'] ?>" aria-label="Song title" placeholder="Song title" data-field="title">
      <input class="field field--on-field" type="text" maxlength="<?= (int) $limits['artist_max'] ?>" aria-label="Artist" placeholder="Artist (optional)" data-field="artist">
    </div>
    <div class="track__actions">
      <button type="button" class="icon-btn" data-move="-1" aria-label="Move up"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 14.5 12 8.5l6 6" /></svg></button>
      <button type="button" class="icon-btn" data-move="1" aria-label="Move down"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9.5l6 6 6-6" /></svg></button>
      <button type="button" class="icon-btn" data-remove aria-label="Remove song"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7l10 10M17 7 7 17" /></svg></button>
    </div>
  </li>
</template>
