# Mixtape

Make someone a mixtape: choose up to six songs from YouTube, write a letter, send one link. The person who opens the link presses play and the songs play one after another, with the letter beside them. No accounts.

Built with PHP 8, PostgreSQL, Tailwind CSS v4 and plain JavaScript. It runs on Vercel or on any PHP host.

## What it does

- **Builder** (`/new`): pick a cassette color and stickers, paste YouTube links (titles and artists are filled in and editable), reorder songs, write a letter. The cassette preview updates as you go, and the whole page takes the tape's color.
- **Shared page** (`/m/<id>`): a cassette whose reels turn while it plays, the letter, and a tracklist. Songs play in order through the YouTube player. A video that can't be embedded is skipped and marked, with a link to open it on YouTube.
- **Manage page** (`/m/<id>/manage/<token>`): the private link shown after creating. It shows how many times the mixtape was opened and lets the maker delete it.
- Optional: search for songs by name inside the builder (needs a YouTube Data API key, see below).

## Deploy on Vercel

You need a Vercel account, the project on GitHub, and a Postgres database. These steps use Neon, a Postgres service Vercel offers in its Storage tab. Any Postgres works.

1. **Put the project on GitHub.**
2. **Import it into Vercel** (Add New > Project). Leave the framework on "Other" and the build settings alone: `vercel.json` sets them. Before you press Deploy, open *Environment Variables* and add `APP_SECRET` with a long random string (`openssl rand -hex 32` makes one).
3. **Add the database.** In the project, open *Storage*, create a Neon Postgres database and connect it to the project. Vercel adds `DATABASE_URL` and a few similar variables for you.
4. **Create the tables.** Open the database in Neon, go to its SQL Editor, paste in the contents of `db/schema.sql` and run it. The file is safe to run twice. (From a terminal instead: `psql "<the database URL>" -f db/schema.sql`.)
5. **Redeploy**, so the app sees the new variables: *Deployments*, the latest one, *Redeploy*.
6. **Try it.** Open the site and make a mixtape. To check everything from a terminal, run `BASE=https://your-project.vercel.app tests/smoke.sh` (needs bash and curl; it makes one mixtape and deletes it again).

Share links from your production domain. Vercel can ask visitors to sign in on preview-deployment URLs (Deployment Protection), so test with the production address.

### Settings worth a look

- **Function Region** (Project Settings > Functions): pick the region closest to your database. Every page view talks to it, so distance shows. Neon in AWS Frankfurt (`eu-central-1`) pairs with Vercel `fra1`; Neon in AWS N. Virginia (`us-east-1`) pairs with `iad1`, which is Vercel's default.
- **Domain** (Project Settings > Domains): share links use whatever address people visit, so there is nothing else to change.
- **Optional variables:** `YOUTUBE_API_KEY` (song search), `MAX_SONGS`, `LETTER_MAX`, `CREATE_LIMIT_PER_HOUR`, `APP_URL`. See the table below.

### How it runs there

- Vercel does not run PHP by itself. `vercel.json` uses the community runtime [vercel-php](https://github.com/vercel-community/php), pinned to `0.7.4`, which is PHP 8.3, the version this app was tested with. `0.8.0` is PHP 8.4 and `0.9.0` is PHP 8.5. The app needs PHP 8.1 or newer.
- Files in `public/` (stylesheet, scripts, icon) are served as static files. Every other request goes to `api/index.php`, which loads `src/app.php`, the same code the other hosts run. The stylesheet is committed already compiled, so Vercel has nothing to build.
- The database URL's `sslmode`, `channel_binding` and `connect_timeout` are respected. A remote database gets `sslmode=require` on Vercel unless the URL says otherwise. If the PHP build's Postgres client is old, the app retries once or twice with the fix that Neon or libpq asks for (naming the Neon endpoint, or dropping an option the client does not know). For pooled hosts (Neon's `-pooler`, Supabase's port 6543) PDO fills in query parameters itself, because a PgBouncer pool cannot keep a prepared statement between queries. `DB_EMULATE_PREPARES=1` or `0` overrides this.
- There are no sessions and no cookies. Form tokens are signed with `APP_SECRET` and expire after a day, so any instance can check a token that another instance made.
- The limit on new mixtapes per visitor uses the address Vercel reports. Vercel is detected from its own environment variables. If you switched off *Automatically expose System Environment Variables* in the project settings, set `TRUST_PROXY=1`.
- Neon can pause an idle database, so the first visit after a quiet spell may take a second or two longer.

Status: the layout follows vercel-php's documented setup, and `tests/vercel-sim.sh` runs the app on your own machine the way Vercel would (its variables, the `api/index.php` entry point, forwarded headers, emulated prepared statements). It has not been run on Vercel's servers yet, so if the first deploy misbehaves, the table below covers the likely causes.

### If something goes wrong

Open the project's *Logs* in Vercel and look for the line that starts with an error name.

| You see | Likely cause and fix |
| --- | --- |
| `could not find driver` | The PHP runtime has no Postgres driver. Try `vercel-php@0.8.0` or `0.9.0` in `vercel.json`. |
| `relation "mixtapes" does not exist` | Step 4 was skipped. Run `db/schema.sql` in the database. |
| `APP_SECRET is not set` | Add `APP_SECRET` under Project Settings > Environment Variables, then redeploy. |
| `password authentication failed`, `SSL`, `timeout expired` | Check `DATABASE_URL` (Neon's pooled URL, with `sslmode=require`). Keep the database and the function region close together. |
| `authentication method 10 not supported` | The Postgres client inside that PHP runtime is too old for Neon's password scheme. Try `vercel-php@0.8.0` or `0.9.0`. |
| Every page is Vercel's own `404: NOT_FOUND` | The rewrite to the PHP function did not match. In `vercel.json`, replace `rewrites` and `redirects` with `"routes": [{ "handle": "filesystem" }, { "src": "/(.*)", "dest": "/api/index.php" }]`. |
| Pages show Vercel's sign-in screen | You opened a preview-deployment URL. Use the production domain. |
| Share links have the wrong address | Set `APP_URL` to your domain, for example `https://mixtape.example.com`. |
| Song titles do not fill in | The function could not reach YouTube. People can type the titles instead. |

## Run it locally

You need PHP 8.1+ (with `pdo_pgsql`, `curl`, `mbstring`) and PostgreSQL 13+.

```bash
createdb mixtape
psql mixtape -f db/schema.sql

cp .env.example .env          # then edit DATABASE_URL and APP_SECRET
php -S 127.0.0.1:8080 -t public public/index.php
```

Open http://127.0.0.1:8080. The compiled stylesheet (`public/assets/app.css`) is committed, so Node is not needed to run the app.

### Change the styles

```bash
npm install
npm run build        # or: npm run watch
```

The source is `resources/css/` (Tailwind v4 theme and tokens in `app.css`, the cassette in `cassette.css`, paper things and form parts in `paper.css`). Tailwind utility classes are used in `views/`. `scripts/build-css.mjs` compiles the stylesheet with the `tailwindcss` package alone. If you prefer the official CLI, this does the same job: `npx @tailwindcss/cli -i resources/css/app.css -o public/assets/app.css --minify`.

Commit the rebuilt `public/assets/app.css`: it is what gets served, on Vercel too.

### Search by song name (optional)

Set `YOUTUBE_API_KEY` in `.env` (or in Vercel's environment variables). Create a key in the Google Cloud console and enable "YouTube Data API v3". A search box then appears in the builder. Each search costs 100 units of the default 10,000 units per day, so searches run only when the person presses Enter. Without a key, people paste links.

## Deploy on your own server

Point the web server's document root at `public/`, never at the project folder.

Apache: `public/.htaccess` is included (needs `mod_rewrite` and `AllowOverride All`).

nginx:

```nginx
root /var/www/mixtape/public;
location / { try_files $uri /index.php?$query_string; }
location = /index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
```

Set these in `.env` or the environment (on Vercel, in the project's environment variables):

| Variable | Purpose |
| --- | --- |
| `APP_SECRET` | Long random string. Signs form tokens and hashes visitor IPs for rate limiting. If it is missing, the app derives a private value from the database URL, but set your own. |
| `APP_URL` | Public base URL, if the app sits behind a proxy that hides the real host. |
| `TRUST_PROXY` | Set to `1` only behind a trusted reverse proxy that sets `X-Forwarded-Proto` and `X-Forwarded-For`. Not needed on Vercel. |
| `DATABASE_URL` | `postgres://user:password@host:5432/dbname`, with `?sslmode=require` for most hosted databases. `POSTGRES_URL` is used when it is empty. Or use the separate `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` and `DB_SSLMODE` variables. |
| `DB_EMULATE_PREPARES` | `1` or `0` to force emulated prepared statements on or off. By default they are on only for pooled hosts. |
| `MAX_SONGS`, `LETTER_MAX`, `CREATE_LIMIT_PER_HOUR` | Limits (defaults 6, 1200 characters, 10 per hour per visitor). |
| `YOUTUBE_API_KEY` | Optional. Turns on search by song name. |

Use HTTPS in production. The app sets no cookies.

## Tests

```bash
php tests/run.php        # link parsing, title cleanup, input validation, database URLs, tokens, proxy headers (no database needed)
tests/smoke.sh           # end to end against a running server and database; creates and deletes one mixtape
tests/vercel-sim.sh      # runs the app the way Vercel does and checks what is special there (needs a database)
```

`BASE=https://your.site tests/smoke.sh` runs the same end-to-end check against a deployed site.

## How it fits together

```
api/index.php            entry point on Vercel (loads src/app.php)
public/index.php         entry point for Apache, nginx and `php -S` (loads src/app.php)
src/app.php              routes and handlers
src/helpers.php          escaping, form tokens, client address, headers, page rendering
src/Db.php               database connection from DATABASE_URL
src/Youtube.php          link parsing, title lookup (oEmbed), optional search
src/MixtapeInput.php     validates what the builder sends
src/MixtapeRepo.php      all SQL
db/schema.sql            two tables: mixtapes, mixtape_tracks
views/                   PHP templates; partials/cassette.php is the cassette
public/assets/           app.js (builder), player.js (shared page), home.js, the compiled app.css
vercel.json              Vercel settings: PHP runtime and routing
.vercelignore            files that are not uploaded to Vercel
```

## Good to know

- **What is stored:** the cassette look, songs (video id, title, artist), the letter, the names, a hash of the private link, a view count, and a hash of the creator's IP for rate limiting (the IP itself is never stored).
- **Privacy:** shared pages send `noindex` headers and use `youtube-nocookie.com` for the player. The app sets no cookies. Fonts load from Google Fonts. To self-host them, download Archivo and Gaegu, add `@font-face` rules to `resources/css/app.css`, remove the font `<link>` tags in `views/layout.php`, and drop the two Google hosts from the Content-Security-Policy in `src/helpers.php`.
- **Form tokens:** they are signed and expire, but they are not tied to one visitor. Requests to the JSON API also need a custom header, which other websites cannot send, and deleting a mixtape also needs its private link.
- **Playback:** browsers only allow sound after a click, so the recipient presses Play once. After that the songs chain on their own. Playback uses the official YouTube embed, so songs the uploader has blocked from embedding are skipped.
- **Not included:** a way to report abusive mixtapes, and an admin screen. If you open this to the public, add both.
