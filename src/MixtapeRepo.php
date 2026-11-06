<?php
declare(strict_types=1);

/** All SQL for mixtapes lives here. */
final class MixtapeRepo
{
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    public static function newId(): string
    {
        $id = '';
        for ($i = 0; $i < 8; $i++) {
            $id .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        return $id;
    }

    public static function recentCount(string $creatorHash): int
    {
        $st = Db::pdo()->prepare("SELECT count(*) FROM mixtapes WHERE creator_hash = :h AND created_at > now() - interval '1 hour'");
        $st->execute([':h' => $creatorHash]);
        return (int) $st->fetchColumn();
    }

    /** @return array{id: string, manageToken: string} */
    public static function create(array $clean, string $creatorHash): array
    {
        $pdo = Db::pdo();
        $token = bin2hex(random_bytes(16));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $id = self::newId();
            try {
                $pdo->beginTransaction();
                $pdo->prepare(
                    'INSERT INTO mixtapes (id, tape_color, stickers, to_name, from_name, letter, manage_hash, creator_hash)
                     VALUES (:id, :color, CAST(:stickers AS text[]), :to_name, :from_name, :letter, :manage_hash, :creator_hash)'
                )->execute([
                    ':id' => $id,
                    ':color' => $clean['tapeColor'],
                    ':stickers' => '{' . implode(',', $clean['stickers']) . '}',
                    ':to_name' => $clean['toName'],
                    ':from_name' => $clean['fromName'],
                    ':letter' => $clean['letter'],
                    ':manage_hash' => hash('sha256', $token),
                    ':creator_hash' => $creatorHash,
                ]);
                $insert = $pdo->prepare(
                    'INSERT INTO mixtape_tracks (mixtape_id, position, video_id, title, artist)
                     VALUES (:id, :position, :video_id, :title, :artist)'
                );
                foreach ($clean['tracks'] as $position => $track) {
                    $insert->execute([
                        ':id' => $id,
                        ':position' => $position,
                        ':video_id' => $track['videoId'],
                        ':title' => $track['title'],
                        ':artist' => $track['artist'],
                    ]);
                }
                $pdo->commit();
                return ['id' => $id, 'manageToken' => $token];
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e->getCode() !== '23505') { // anything except "this id is taken"
                    throw $e;
                }
            }
        }
        throw new RuntimeException('Could not find a free mixtape id.');
    }

    public static function find(string $id): ?array
    {
        if (!preg_match('/^[a-z0-9]{8}$/', $id)) {
            return null;
        }
        $pdo = Db::pdo();
        $st = $pdo->prepare(
            'SELECT id, tape_color, to_json(stickers) AS stickers, to_name, from_name, letter, views, created_at
             FROM mixtapes WHERE id = :id'
        );
        $st->execute([':id' => $id]);
        $row = $st->fetch();
        if (!$row) {
            return null;
        }
        $row['stickers'] = json_decode($row['stickers'], true) ?: [];

        $st = $pdo->prepare('SELECT video_id, title, artist FROM mixtape_tracks WHERE mixtape_id = :id ORDER BY position');
        $st->execute([':id' => $id]);
        $row['tracks'] = $st->fetchAll();
        return $row;
    }

    public static function recordView(string $id): void
    {
        Db::pdo()->prepare('UPDATE mixtapes SET views = views + 1 WHERE id = :id')->execute([':id' => $id]);
    }

    public static function manageTokenMatches(string $id, string $token): bool
    {
        $st = Db::pdo()->prepare('SELECT manage_hash FROM mixtapes WHERE id = :id');
        $st->execute([':id' => $id]);
        $hash = $st->fetchColumn();
        return is_string($hash) && $token !== '' && hash_equals($hash, hash('sha256', $token));
    }

    public static function delete(string $id): void
    {
        Db::pdo()->prepare('DELETE FROM mixtapes WHERE id = :id')->execute([':id' => $id]);
    }
}
