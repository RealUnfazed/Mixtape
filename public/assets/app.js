// The mixtape builder: live cassette preview, song list, letter, and the call that saves it.
(() => {
  const root = document.querySelector('[data-builder]');
  if (!root) return;

  const $ = (selector) => root.querySelector(selector);
  const MAX = Number(root.dataset.maxSongs);
  const LETTER_MAX = Number(root.dataset.letterMax);
  const csrf = root.dataset.csrf;
  const DRAFT_KEY = 'mixtape:draft:v1';

  const form = $('#builder-form');
  const cassette = document.querySelector('.cassette');
  const el = {
    url: $('[data-song-url]'), add: $('[data-add-song]'), msg: $('[data-song-msg]'),
    list: $('[data-tracks]'), empty: $('[data-empty]'), count: $('[data-count]'),
    to: $('[data-to]'), from: $('[data-from]'), letter: $('[data-letter]'), letterCount: $('[data-letter-count]'),
    errors: $('[data-errors]'), submit: $('[data-submit]'), done: $('[data-done]'),
    search: $('[data-song-search]'), results: $('[data-results]'),
  };
  const template = document.getElementById('track-template');
  const state = { tapeColor: 'clover', stickers: [], tracks: [] };

  async function api(path, options = {}) {
    const response = await fetch(path, {
      ...options,
      headers: { Accept: 'application/json', 'X-CSRF-Token': csrf, ...(options.headers || {}) },
    });
    let data = {};
    try { data = await response.json(); } catch { /* not JSON */ }
    if (!response.ok) {
      const error = new Error(data.error || 'Something went wrong. Try again in a moment.');
      error.errors = data.errors;
      throw error;
    }
    return data;
  }

  // ---- Draft: keep work if the page reloads ----------------------------------------------------
  function saveDraft() {
    try {
      localStorage.setItem(DRAFT_KEY, JSON.stringify({
        ...state, to: el.to.value, from: el.from.value, letter: el.letter.value,
      }));
    } catch { /* storage unavailable */ }
  }
  function clearDraft() {
    try { localStorage.removeItem(DRAFT_KEY); } catch { /* storage unavailable */ }
  }
  function loadDraft() {
    try {
      const draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null');
      if (!draft) return;
      const colors = [...form.querySelectorAll('input[name="tapeColor"]')].map((input) => input.value);
      if (colors.includes(draft.tapeColor)) state.tapeColor = draft.tapeColor;
      const stickers = [...form.querySelectorAll('input[name="stickers"]')].map((input) => input.value);
      state.stickers = (draft.stickers || []).filter((s) => stickers.includes(s));
      state.tracks = (draft.tracks || [])
        .filter((t) => /^[A-Za-z0-9_-]{11}$/.test(t.videoId))
        .slice(0, MAX)
        .map((t) => ({ videoId: t.videoId, title: String(t.title || ''), artist: String(t.artist || '') }));
      el.to.value = draft.to || '';
      el.from.value = draft.from || '';
      el.letter.value = draft.letter || '';
      form.querySelectorAll('input[name="tapeColor"]').forEach((input) => { input.checked = input.value === state.tapeColor; });
      form.querySelectorAll('input[name="stickers"]').forEach((input) => { input.checked = state.stickers.includes(input.value); });
    } catch { /* ignore a broken draft */ }
  }

  // ---- Rendering -------------------------------------------------------------------------------
  function renderCassette() {
    cassette.dataset.tape = state.tapeColor;
    document.body.dataset.tape = state.tapeColor;
    cassette.dataset.stickers = state.stickers.join(' ');
    cassette.querySelector('[data-cassette-for]').textContent = `for ${el.to.value.trim() || 'you'}`;
    cassette.querySelectorAll('[data-cassette-tracks] li').forEach((li, i) => {
      li.replaceChildren();
      const track = state.tracks[i];
      if (!track) return;
      const title = document.createElement('span');
      title.className = 'cassette__title';
      title.textContent = track.title;
      li.append(title);
      if (track.artist) {
        const artist = document.createElement('span');
        artist.className = 'cassette__artist';
        artist.textContent = track.artist;
        li.append(' ', artist);
      }
    });
  }

  function renderTracks(focus) {
    el.list.replaceChildren();
    state.tracks.forEach((track, i) => {
      const row = template.content.firstElementChild.cloneNode(true);
      row.querySelector('img').src = `https://i.ytimg.com/vi/${track.videoId}/mqdefault.jpg`;
      const [title, artist] = row.querySelectorAll('[data-field]');
      title.value = track.title;
      artist.value = track.artist;
      title.addEventListener('input', () => { track.title = title.value; renderCassette(); saveDraft(); });
      artist.addEventListener('input', () => { track.artist = artist.value; renderCassette(); saveDraft(); });
      row.querySelector('[data-move="-1"]').disabled = i === 0;
      row.querySelector('[data-move="1"]').disabled = i === state.tracks.length - 1;
      row.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.hasAttribute('data-remove')) removeTrack(i);
        else moveTrack(i, Number(button.dataset.move));
      });
      el.list.append(row);
    });
    const full = state.tracks.length >= MAX;
    el.empty.hidden = state.tracks.length > 0;
    el.count.textContent = `${state.tracks.length} of ${MAX} songs`;
    el.add.disabled = full;
    if (el.search) el.search.disabled = full;
    renderCassette();
    saveDraft();
    if (el.errors) showErrors([]);
    if (focus) focus();
  }

  function addTrack({ videoId, title, artist }) {
    state.tracks.push({ videoId, title: title || '', artist: artist || '' });
    const last = state.tracks.length - 1;
    renderTracks(() => { if (!title) el.list.children[last].querySelector('[data-field="title"]').focus(); });
  }
  function removeTrack(i) {
    state.tracks.splice(i, 1);
    renderTracks(() => (el.list.children[Math.min(i, state.tracks.length - 1)]?.querySelector('[data-remove]') ?? el.url).focus());
  }
  function moveTrack(i, step) {
    const j = i + step;
    if (j < 0 || j >= state.tracks.length) return;
    [state.tracks[i], state.tracks[j]] = [state.tracks[j], state.tracks[i]];
    renderTracks(() => {
      const row = el.list.children[j];
      const wanted = row.querySelector(`[data-move="${step}"]`);
      (wanted.disabled ? row.querySelector(`[data-move="${-step}"]`) : wanted).focus();
    });
  }

  // ---- Adding songs: paste a link, or search when the server has a YouTube API key ------------------
  const say = (text) => { el.msg.textContent = text; };

  async function addFromLink() {
    const value = el.url.value.trim();
    if (!value) return say('Paste a YouTube link first.');
    if (state.tracks.length >= MAX) return say(`A mixtape holds up to ${MAX} songs.`);
    el.add.disabled = true;
    say('');
    try {
      const song = await api(`/api/lookup?url=${encodeURIComponent(value)}`);
      el.url.value = '';
      addTrack(song);
      if (!song.found) say('Song added. We could not fetch its title, so type it in.');
    } catch (error) {
      say(error.message);
    } finally {
      el.add.disabled = state.tracks.length >= MAX;
    }
  }
  el.add.addEventListener('click', addFromLink);
  el.url.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') { event.preventDefault(); addFromLink(); }
  });

  async function runSearch() {
    const query = el.search.value.trim();
    if (query.length < 2) return say('Type at least two characters to search.');
    say('');
    try {
      const { results } = await api(`/api/search?q=${encodeURIComponent(query)}`);
      el.results.replaceChildren();
      results.forEach((song) => {
        const item = document.createElement('li');
        const button = document.createElement('button');
        const image = document.createElement('img');
        const text = document.createElement('span');
        const title = document.createElement('span');
        const artist = document.createElement('span');
        button.type = 'button';
        image.src = song.thumbnail; image.alt = ''; image.width = 96; image.height = 54;
        title.className = 'r-title'; title.textContent = song.title;
        artist.className = 'r-artist'; artist.textContent = song.artist;
        text.append(title, artist);
        button.append(image, text);
        button.addEventListener('click', () => {
          if (state.tracks.length >= MAX) return say(`A mixtape holds up to ${MAX} songs.`);
          addTrack(song);
          el.results.hidden = true;
          el.search.value = '';
        });
        item.append(button);
        el.results.append(item);
      });
      el.results.hidden = results.length === 0;
      if (results.length === 0) say('No songs found. Try different words.');
    } catch (error) {
      say(error.message);
    }
  }
  if (el.search) {
    el.search.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') { event.preventDefault(); runSearch(); }
    });
  }

  // ---- Look, letter, names ---------------------------------------------------------------------
  const fitInput = (input) => { input.size = input.value ? Math.max(input.value.length + 1, 4) : input.placeholder.length; };
  const growLetter = () => {
    el.letter.style.height = 'auto';
    el.letter.style.height = `${el.letter.scrollHeight}px`;
  };
  form.addEventListener('change', (event) => {
    showErrors([]);
    if (event.target.name === 'tapeColor') state.tapeColor = event.target.value;
    if (event.target.name === 'stickers') {
      state.stickers = [...form.querySelectorAll('input[name="stickers"]:checked')].map((input) => input.value);
    }
    renderCassette();
    saveDraft();
  });
  const updateLetterCount = () => {
    const left = LETTER_MAX - el.letter.value.length;
    el.letterCount.textContent = `${left.toLocaleString()} character${left === 1 ? '' : 's'} left`;
  };
  el.letter.addEventListener('input', () => { updateLetterCount(); growLetter(); saveDraft(); });
  el.to.addEventListener('input', () => { fitInput(el.to); renderCassette(); saveDraft(); });
  el.from.addEventListener('input', () => { fitInput(el.from); saveDraft(); });

  // ---- Save -----------------------------------------------------------------------------------
  function showErrors(messages) {
    el.errors.replaceChildren(...messages.map((message) => {
      const item = document.createElement('li');
      item.textContent = message;
      return item;
    }));
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const problems = [];
    if (state.tracks.length === 0) problems.push('Add at least one song.');
    state.tracks.forEach((track, i) => { if (!track.title.trim()) problems.push(`Add a title for song ${i + 1}.`); });
    if (!el.letter.value.trim()) problems.push('Write a letter, even one line.');
    showErrors(problems);
    if (problems.length) return;

    el.submit.disabled = true;
    try {
      const made = await api('/api/mixtapes', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          tapeColor: state.tapeColor, stickers: state.stickers, toName: el.to.value, fromName: el.from.value,
          letter: el.letter.value, tracks: state.tracks,
        }),
      });
      clearDraft();
      form.hidden = true;
      el.done.hidden = false;
      $('[data-share-url]').value = made.url;
      $('[data-manage-url]').value = made.manageUrl;
      $('[data-open-link]').href = made.url;
      $('#done-title').focus();
    } catch (error) {
      showErrors(error.errors && error.errors.length ? error.errors : [error.message]);
      el.submit.disabled = false;
    }
  });

  root.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;
    const input = document.getElementById(button.dataset.copy);
    try {
      await navigator.clipboard.writeText(input.value);
    } catch {
      input.select();
      document.execCommand('copy');
    }
    const label = button.textContent;
    button.textContent = 'Copied';
    setTimeout(() => { button.textContent = label; }, 1800);
  });

  form.addEventListener('input', () => showErrors([]));

  loadDraft();
  updateLetterCount();
  fitInput(el.to);
  fitInput(el.from);
  growLetter();
  renderTracks();
})();
