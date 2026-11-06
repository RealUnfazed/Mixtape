-- Mixtape schema for PostgreSQL 13 or newer.
-- Apply with:  psql "$DATABASE_URL" -f db/schema.sql
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS mixtapes (
    id            text PRIMARY KEY CHECK (id ~ '^[a-z0-9]{8}$'),
    tape_color    text NOT NULL DEFAULT 'clover'
                  CHECK (tape_color IN ('clover', 'lemon', 'cherry', 'bluebell')),
    stickers      text[] NOT NULL DEFAULT '{}'
                  CHECK (stickers <@ ARRAY['bow', 'daisies', 'sprig']::text[]),
    to_name       text NOT NULL DEFAULT '' CHECK (char_length(to_name) <= 40),
    from_name     text NOT NULL DEFAULT '' CHECK (char_length(from_name) <= 40),
    letter        text NOT NULL CHECK (char_length(letter) BETWEEN 1 AND 2000),
    manage_hash   text NOT NULL,           -- sha256 of the private manage token
    creator_hash  text NOT NULL,           -- HMAC of the creator's IP, for rate limiting only
    views         integer NOT NULL DEFAULT 0,
    created_at    timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS mixtapes_creator_recent_idx
    ON mixtapes (creator_hash, created_at DESC);

CREATE TABLE IF NOT EXISTS mixtape_tracks (
    mixtape_id  text NOT NULL REFERENCES mixtapes (id) ON DELETE CASCADE,
    position    smallint NOT NULL CHECK (position BETWEEN 0 AND 19),
    video_id    text NOT NULL CHECK (video_id ~ '^[A-Za-z0-9_-]{11}$'),
    title       text NOT NULL CHECK (char_length(title) BETWEEN 1 AND 120),
    artist      text NOT NULL DEFAULT '' CHECK (char_length(artist) <= 80),
    PRIMARY KEY (mixtape_id, position)
);
