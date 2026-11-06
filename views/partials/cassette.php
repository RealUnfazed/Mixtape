<?php
/**
 * The cassette. Everything is sized in container units, so it scales to any width.
 * Expects: $tape (color key), $stickers (list), $tracks (list of title/artist), $to (string),
 * and optionally $label (string, accessible description).
 */
$stickers = $stickers ?? [];
$tracks = $tracks ?? [];
$to = trim($to ?? '');
?>
<div class="cassette" data-tape="<?= e($tape) ?>" data-stickers="<?= e(implode(' ', $stickers)) ?>" role="img" aria-label="<?= e($label ?? 'A cassette tape') ?>">
  <div class="cassette__body">
    <i class="cassette__screw cassette__screw--tl"></i><i class="cassette__screw cassette__screw--tr"></i>
    <i class="cassette__screw cassette__screw--bl"></i><i class="cassette__screw cassette__screw--br"></i>

    <div class="cassette__label">
      <div class="cassette__stripe">
        <span class="cassette__side">A</span>
        <span class="cassette__for" data-cassette-for>for <?= e($to !== '' ? $to : 'you') ?></span>
      </div>
      <ol class="cassette__tracks" data-cassette-tracks>
        <?php for ($i = 0; $i < 6; $i++): $t = $tracks[$i] ?? null; ?>
          <li><?php if ($t): ?><span class="cassette__title"><?= e($t['title']) ?></span> <span class="cassette__artist"><?= e($t['artist']) ?></span><?php endif ?></li>
        <?php endfor ?>
      </ol>
    </div>

    <div class="cassette__window">
      <span class="cassette__pack cassette__pack--left"></span>
      <span class="cassette__pack cassette__pack--right"></span>
      <svg class="cassette__reel cassette__reel--left" viewBox="0 0 100 100"><use href="#reel" width="100" height="100"/></svg>
      <svg class="cassette__reel cassette__reel--right" viewBox="0 0 100 100"><use href="#reel" width="100" height="100"/></svg>
    </div>

    <div class="cassette__foot"><i></i><i></i><i></i><i></i></div>

    <svg class="cassette__sticker cassette__sticker--bow" viewBox="0 0 100 80"><use href="#st-bow"/></svg>
    <svg class="cassette__sticker cassette__sticker--daisies" viewBox="0 0 100 100"><use href="#st-daisies"/></svg>
    <svg class="cassette__sticker cassette__sticker--sprig" viewBox="0 0 100 100"><use href="#st-sprig"/></svg>
  </div>
</div>
