<?php
/** @var bool $deleted */
$limits = limits();
$sample = [
    ['title' => 'golden hour', 'artist' => 'for the drive'],
    ['title' => 'the one we danced to', 'artist' => ''],
    ['title' => 'sunday morning', 'artist' => 'slow'],
    ['title' => 'turn it up', 'artist' => 'loud'],
];
?>
<?php if ($deleted): ?>
<p role="status" class="mx-auto mb-2 w-full max-w-6xl px-5 text-sm font-semibold sm:px-8">Your mixtape was deleted. The link no longer works.</p>
<?php endif ?>

<main>
  <section class="mx-auto grid w-full max-w-6xl items-center gap-10 px-5 pb-6 pt-6 sm:px-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-14 lg:pt-12">
    <div>
      <h1 class="hero-title">Make someone a mixtape.</h1>
      <p class="mt-6 max-w-md text-lg leading-relaxed text-field-soft">
        Choose songs from YouTube, write them a letter, and send one link. They press play and the whole tape plays.
      </p>
      <a href="/new" class="btn-solid mt-8">Start a mixtape</a>
    </div>

    <div class="w-full max-w-xl">
      <div class="tilt">
        <?php partial('cassette', [
            'tape' => 'clover',
            'stickers' => ['bow', 'daisies'],
            'tracks' => $sample,
            'to' => '',
            'label' => 'A green cassette with a handwritten tracklist',
        ]) ?>
      </div>
      <div class="mt-8 flex items-center justify-center gap-3" role="group" aria-label="Cassette color">
<?php foreach (TAPE_COLORS as $key => $name): ?>
        <button type="button" class="swatch" data-swatch="<?= e($key) ?>" aria-label="<?= e($name) ?>" aria-pressed="<?= $key === 'clover' ? 'true' : 'false' ?>"></button>
<?php endforeach ?>
      </div>
    </div>
  </section>

  <section class="mx-auto w-full max-w-6xl px-5 pt-16 sm:px-8" aria-labelledby="how">
    <h2 id="how" class="section-title">How it works</h2>
    <ol class="mt-8 grid gap-8 sm:grid-cols-3">
      <li class="step">
        <span class="step__n" aria-hidden="true">1</span>
        <h3 class="step__title">Choose a cassette</h3>
        <p>Pick a color and a few stickers so the tape looks like it came from you.</p>
      </li>
      <li class="step">
        <span class="step__n" aria-hidden="true">2</span>
        <h3 class="step__title">Add songs and a letter</h3>
        <p>Paste up to <?= (int) $limits['max_songs'] ?> YouTube links, then say what the tape is for, in up to <?= number_format($limits['letter_max']) ?> characters.</p>
      </li>
      <li class="step">
        <span class="step__n" aria-hidden="true">3</span>
        <h3 class="step__title">Send the link</h3>
        <p>They open it, press play, and read your letter while the songs play. No account needed.</p>
      </li>
    </ol>
  </section>
</main>
