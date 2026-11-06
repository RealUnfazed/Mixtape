// The shared mixtape page: plays the songs in order with the YouTube player.
(() => {
  const dataElement = document.getElementById('tape-data');
  if (!dataElement) return;

  const tracks = JSON.parse(dataElement.textContent);
  const deck = document.querySelector('.deck');
  const cassette = deck.querySelector('.cassette');
  const $ = (selector) => deck.querySelector(selector);
  const playButton = $('[data-play]');
  const playLabel = $('[data-play-label]');
  const prevButton = $('[data-prev]');
  const nextButton = $('[data-next]');
  const seek = $('[data-seek]');
  const timeLabel = $('[data-time]');
  const status = $('[data-status]');
  const items = [...document.querySelectorAll('.jcard__item')];

  let player = null;
  let ready = false;
  let index = 0;
  let playing = false;
  let timer = 0;
  let seeking = false;
  const unavailable = new Set();

  const format = (seconds) => {
    const whole = Math.max(0, Math.floor(seconds || 0));
    return `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2, '0')}`;
  };
  let statusTimer = 0;
  let atEnd = false;
  // A message can clear itself after `ttl` ms, so a notice outlives the song change that caused it.
  const say = (text, ttl = 0) => {
    clearTimeout(statusTimer);
    status.textContent = text;
    if (ttl) statusTimer = setTimeout(() => { status.textContent = ''; }, ttl);
  };

  function setProgress(fraction) {
    cassette.style.setProperty('--p', (Math.min(1, Math.max(0, (index + fraction) / tracks.length))).toFixed(3));
  }

  function setPlaying(value) {
    playing = value;
    document.body.classList.toggle('is-playing', value);
    playLabel.textContent = value ? 'Pause' : 'Play';
    if ('mediaSession' in navigator) navigator.mediaSession.playbackState = value ? 'playing' : 'paused';
  }

  function tick() {
    if (!ready) return;
    const duration = player.getDuration() || 0;
    const current = player.getCurrentTime() || 0;
    if (!seeking) {
      seek.value = duration ? Math.round((current / duration) * 1000) : 0;
      timeLabel.textContent = `${format(current)} / ${format(duration)}`;
    }
    setProgress(duration ? current / duration : 0);
  }
  const startTimer = () => { stopTimer(); timer = setInterval(tick, 500); };
  const stopTimer = () => { clearInterval(timer); timer = 0; };

  function select(next, autoplay) {
    index = (next + tracks.length) % tracks.length;
    items.forEach((item, i) => {
      item.classList.toggle('is-current', i === index);
      const row = item.querySelector('.jcard__row');
      if (i === index) row.setAttribute('aria-current', 'true');
      else row.removeAttribute('aria-current');
    });
    seek.value = 0;
    timeLabel.textContent = '0:00';
    setProgress(0);
    if (!ready) return;
    if (autoplay) player.loadVideoById(tracks[index].videoId);
    else player.cueVideoById(tracks[index].videoId);
    updateMediaSession();
  }

  function togglePlay() {
    if (!ready) return;
    if (playing) player.pauseVideo();
    else player.playVideo();
  }

  function skipUnavailable() {
    for (let i = index + 1; i < tracks.length; i += 1) {
      if (!unavailable.has(i)) { select(i, true); return true; }
    }
    return false;
  }

  function onError() {
    unavailable.add(index);
    items[index].classList.add('is-unavailable');
    const title = tracks[index].title;
    if (skipUnavailable()) say(`“${title}” can't be played here, so we skipped it.`, 8000);
    else {
      setPlaying(false);
      say(`“${title}” can't be played here. Use the Open on YouTube links in the tracklist.`);
    }
  }

  function onStateChange(event) {
    const state = window.YT.PlayerState;
    if (event.data === state.PLAYING) {
      if (atEnd) { atEnd = false; say(''); }
      setPlaying(true);
      startTimer();
    }
    else if (event.data === state.PAUSED) { setPlaying(false); stopTimer(); tick(); }
    else if (event.data === state.ENDED) {
      setPlaying(false);
      stopTimer();
      // Play the next song that works; at the end of the tape, rewind and wait.
      if (!skipUnavailable()) {
        select(0, false);
        atEnd = true;
        say("That's the end of the tape. Press play to hear it again.");
      }
    }
  }

  function updateMediaSession() {
    if (!('mediaSession' in navigator)) return;
    const track = tracks[index];
    navigator.mediaSession.metadata = new window.MediaMetadata({
      title: track.title,
      artist: track.artist,
      artwork: [{ src: `https://i.ytimg.com/vi/${track.videoId}/hqdefault.jpg`, sizes: '480x360', type: 'image/jpeg' }],
    });
  }
  function setupMediaSession() {
    if (!('mediaSession' in navigator)) return;
    const handlers = {
      play: () => player.playVideo(),
      pause: () => player.pauseVideo(),
      previoustrack: () => select(index - 1, true),
      nexttrack: () => select(index + 1, true),
    };
    Object.entries(handlers).forEach(([action, handler]) => {
      try { navigator.mediaSession.setActionHandler(action, handler); } catch { /* not supported here */ }
    });
    updateMediaSession();
  }

  function onReady() {
    ready = true;
    [playButton, prevButton, nextButton, seek].forEach((control) => { control.disabled = false; });
    playLabel.textContent = 'Play';
    select(0, false);
    setupMediaSession();
  }

  window.onYouTubeIframeAPIReady = () => {
    player = new window.YT.Player('yt-player', {
      host: 'https://www.youtube-nocookie.com',
      width: '100%',
      height: '100%',
      videoId: tracks[0].videoId,
      playerVars: { playsinline: 1, rel: 0, origin: window.location.origin },
      events: { onReady, onStateChange, onError },
    });
  };

  const script = document.createElement('script');
  script.src = 'https://www.youtube.com/iframe_api';
  script.onerror = () => {
    playLabel.textContent = 'Player unavailable';
    say('The player could not load. Use the Open on YouTube links in the tracklist.');
  };
  document.head.append(script);

  // ---- Controls -----------------------------------------------------------------------------
  playButton.addEventListener('click', togglePlay);
  cassette.addEventListener('click', togglePlay);
  nextButton.addEventListener('click', () => select(index + 1, true));
  prevButton.addEventListener('click', () => {
    if (ready && player.getCurrentTime() > 3) player.seekTo(0, true);
    else select(index - 1, true);
  });
  seek.addEventListener('input', () => {
    seeking = true;
    const duration = player.getDuration() || 0;
    timeLabel.textContent = `${format((seek.value / 1000) * duration)} / ${format(duration)}`;
  });
  seek.addEventListener('change', () => {
    player.seekTo(((player.getDuration() || 0) * seek.value) / 1000, true);
    seeking = false;
  });
  document.querySelector('.jcard').addEventListener('click', (event) => {
    const row = event.target.closest('[data-track]');
    if (!row || !ready) return;
    const wanted = Number(row.dataset.track);
    if (wanted === index && (playing || player.getPlayerState() === window.YT.PlayerState.PAUSED)) togglePlay();
    else select(wanted, true);
  });

  select(0, false);
})();
