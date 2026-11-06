<?php
declare(strict_types=1);

/** Validates the JSON the builder sends. Returns cleaned data, or a list of messages to show. */
final class MixtapeInput
{
    /** @return array{0: ?array, 1: list<string>} */
    public static function parse(array $in): array
    {
        $limits = limits();
        $errors = [];

        $tape = is_string($in['tapeColor'] ?? null) ? $in['tapeColor'] : 'clover';
        if (!isset(TAPE_COLORS[$tape])) {
            $errors[] = 'Pick one of the cassette colors.';
        }

        $stickers = [];
        foreach ((array) ($in['stickers'] ?? []) as $sticker) {
            if (is_string($sticker) && isset(STICKERS[$sticker])) {
                $stickers[$sticker] = $sticker;
            }
        }

        $to = self::text($in['toName'] ?? '', false);
        $from = self::text($in['fromName'] ?? '', false);
        foreach (['to' => $to, 'from' => $from] as $label => $name) {
            if (mb_strlen($name) > $limits['name_max']) {
                $errors[] = "The “$label” name is over {$limits['name_max']} characters.";
            }
        }

        $letter = self::text($in['letter'] ?? '', true);
        if ($letter === '') {
            $errors[] = 'Write a letter, even one line.';
        } elseif (mb_strlen($letter) > $limits['letter_max']) {
            $errors[] = "The letter is over {$limits['letter_max']} characters.";
        }

        $tracks = [];
        $rawTracks = $in['tracks'] ?? [];
        if (!is_array($rawTracks) || count($rawTracks) < 1) {
            $errors[] = 'Add at least one song.';
        } elseif (count($rawTracks) > $limits['max_songs']) {
            $errors[] = "A mixtape holds up to {$limits['max_songs']} songs.";
        } else {
            foreach (array_values($rawTracks) as $i => $raw) {
                $n = $i + 1;
                $id = is_array($raw) ? Youtube::extractId((string) ($raw['videoId'] ?? '')) : null;
                if ($id === null) {
                    $errors[] = "Song $n isn't a valid YouTube video.";
                    continue;
                }
                $title = self::text($raw['title'] ?? '', false);
                $artist = self::text($raw['artist'] ?? '', false);
                if ($title === '') {
                    $errors[] = "Add a title for song $n.";
                } elseif (mb_strlen($title) > $limits['title_max']) {
                    $errors[] = "The title of song $n is over {$limits['title_max']} characters.";
                } elseif (mb_strlen($artist) > $limits['artist_max']) {
                    $errors[] = "The artist of song $n is over {$limits['artist_max']} characters.";
                } else {
                    $tracks[] = ['videoId' => $id, 'title' => $title, 'artist' => $artist];
                }
            }
        }

        if ($errors) {
            return [null, $errors];
        }
        return [[
            'tapeColor' => $tape,
            'stickers' => array_values($stickers),
            'toName' => $to,
            'fromName' => $from,
            'letter' => $letter,
            'tracks' => $tracks,
        ], []];
    }

    /** Strips control characters and tidies whitespace. Single-line fields lose their line breaks. */
    private static function text(mixed $value, bool $multiline): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        if ($multiline) {
            $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;
        } else {
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        }
        return trim($value);
    }
}
