<?php
/** @var array $tape */
$to = $tape['to_name'];
$from = $tape['from_name'];
$labelTracks = array_map(static fn ($t) => ['title' => $t['title'], 'artist' => $t['artist']], $tape['tracks']);
$playerData = array_map(static fn ($t) => ['videoId' => $t['video_id'], 'title' => $t['title'], 'artist' => $t['artist']], $tape['tracks']);
$json = json_encode($playerData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
?>
<main class="mx-auto grid w-full max-w-6xl gap-10 px-5 pb-6 pt-2 sm:px-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] lg:gap-16">
  <section class="deck w-full max-w-xl lg:sticky lg:top-6 lg:self-start" aria-label="Player">
    <h1 class="hero-title hero-title--small">A mixtape for <?= e($to !== '' ? $to : 'you') ?></h1>
<?php if ($from !== ''): ?>
    <p class="mt-3 text-lg text-field-soft">From <?= e($from) ?></p>
<?php endif ?>

    <div class="mt-8">
      <?php partial('cassette', [
          'tape' => $tape['tape_color'],
          'stickers' => $tape['stickers'],
          'tracks' => $labelTracks,
          'to' => $to,
          'label' => 'The cassette. Its reels turn while a song plays.',
      ]) ?>
    </div>

    <div class="transport mt-9">
      <button type="button" class="t-btn" data-prev aria-label="Previous song" disabled>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 5h2.5v14H6zM20 5v14L9.5 12z"/></svg>
      </button>
      <button type="button" class="btn-solid t-play" data-play disabled>
        <svg class="i-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4.5v15L20 12z"/></svg>
        <svg class="i-pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4.5h4.2v15H6zM13.8 4.5H18v15h-4.2z"/></svg>
        <span data-play-label>Loading player</span>
      </button>
      <button type="button" class="t-btn" data-next aria-label="Next song" disabled>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 5H18v14h-2.5zM4 5v14l10.5-7z"/></svg>
      </button>
    </div>

    <div class="seek mt-5">
      <input type="range" min="0" max="1000" value="0" step="1" aria-label="Position in this song" data-seek disabled>
      <span class="seek__time" data-time>0:00</span>
    </div>
    <p class="mt-3 min-h-6 text-sm text-field-soft" role="status" data-status></p>

    <div class="video mt-4"><div id="yt-player"></div></div>
  </section>

  <div class="grid w-full max-w-xl content-start gap-12">
    <article class="letter" aria-label="Letter">
      <span class="letter__tape" aria-hidden="true"></span>
      <p class="letter__to"><?= $to !== '' ? 'Dear ' . e($to) . ',' : 'Hi,' ?></p>
      <div class="letter__body"><?= e($tape['letter']) ?></div>
<?php if ($from !== ''): ?>
      <p class="letter__from"><?= e($from) ?></p>
<?php endif ?>
    </article>

    <section class="jcard" aria-labelledby="tracklist">
      <h2 id="tracklist" class="jcard__title">Tracklist</h2>
      <ol class="jcard__list">
<?php foreach ($tape['tracks'] as $i => $t): ?>
        <li class="jcard__item" data-item="<?= $i ?>">
          <button type="button" class="jcard__row" data-track="<?= $i ?>">
            <span class="jcard__n" aria-hidden="true"><?= $i + 1 ?></span>
            <img src="<?= e(thumb($t['video_id'])) ?>" alt="" width="96" height="54" loading="lazy" class="jcard__thumb">
            <span class="jcard__text">
              <span class="jcard__song"><?= e($t['title']) ?></span>
<?php if ($t['artist'] !== ''): ?>
              <span class="jcard__artist"><?= e($t['artist']) ?></span>
<?php endif ?>
            </span>
            <span class="jcard__bars" aria-hidden="true"><i></i><i></i><i></i></span>
            <span class="sr-only" data-playing-note></span>
          </button>
          <a class="jcard__yt" href="https://www.youtube.com/watch?v=<?= e(rawurlencode($t['video_id'])) ?>" target="_blank" rel="noopener noreferrer" aria-label="Open <?= e($t['title']) ?> on YouTube (new tab)">YouTube</a>
        </li>
<?php endforeach ?>
      </ol>
    </section>
  </div>
</main>

<script type="application/json" id="tape-data"><?= $json ?></script>
