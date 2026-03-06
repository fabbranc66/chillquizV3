/**
 * FILE: public/assets/js/screen.js
 * SCOPO: Gestione UI Schermo (stato sessione, domanda, classifica risultati, media placeholder, QR join).
 * UTILIZZATO DA: app/Views/screen/index.php tramite <script src="/chillquizV3/public/assets/js/screen.js"></script>.
 */

const SCREEN_BOOTSTRAP = window.SCREEN_BOOTSTRAP || {};
const BASE_PUBLIC_URL = String(SCREEN_BOOTSTRAP.basePublicUrl || window.location.pathname.replace(/index\.php$/, ''));
const PUBLIC_HOST = String(SCREEN_BOOTSTRAP.publicHost || window.location.host);
const API_BASE = `${BASE_PUBLIC_URL}index.php?url=api`;
let sessioneId = Number(SCREEN_BOOTSTRAP.sessioneId || 0);
let currentState = null;
let pollStato = null;
let pollMedia = null;
let timerTick = null;
let domandaRenderizzata = false;
let mediaAttiva = null;
let lastAudioPreviewToken = '';
let pendingAudioPreview = null;
const STATO_POLL_MS = 1000;
const MEDIA_POLL_MS = 10000;

function extractSessioneIdFromUrl() {
  const raw = new URLSearchParams(window.location.search).get('url') || '';
  if (raw.startsWith('screen/')) {
    const id = parseInt(raw.split('/')[1], 10);
    if (!Number.isNaN(id) && id > 0) return id;
  }
  return 0;
}

function setupSessionQr() {
  if (!sessioneId) return;

  const qrImg = document.getElementById('sessione-qr');
  if (!qrImg) return;

  const protocol = window.location.protocol || 'http:';
  const joinUrl = `${protocol}//${PUBLIC_HOST}${BASE_PUBLIC_URL}index.php?url=player`;
  qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(joinUrl)}`;
}

function resetStageTimer() {
  if (timerTick) {
    clearInterval(timerTick);
    timerTick = null;
  }

  const indicator = document.getElementById('stage-timer-indicator');
  const label = document.getElementById('stage-timer-label');

  if (indicator) indicator.style.setProperty('--progress', '0deg');
  if (label) label.innerText = '0s';
}

function renderStageTimer(sessione) {
  const indicator = document.getElementById('stage-timer-indicator');
  const label = document.getElementById('stage-timer-label');

  if (!indicator || !label) return;

  const max = Number(sessione?.timer_max || 0);
  const start = Number(sessione?.timer_start || 0);
  const stato = String(sessione?.stato || '');

  if (stato !== 'domanda' || max <= 0 || start <= 0) {
    resetStageTimer();
    return;
  }

  if (timerTick) {
    clearInterval(timerTick);
    timerTick = null;
  }

  const tick = () => {
    const elapsed = Math.max(0, Math.floor(Date.now() / 1000) - start);
    const remaining = Math.max(0, max - elapsed);
    const pct = max > 0 ? (remaining / max) : 0;
    const deg = Math.max(0, Math.min(360, pct * 360));

    indicator.style.setProperty('--progress', `${deg}deg`);
    label.innerText = `${remaining}s`;

    if (remaining <= 0 && timerTick) {
      clearInterval(timerTick);
      timerTick = null;
    }
  };

  tick();
  timerTick = setInterval(tick, 250);
}

function getStateMeta(state) {
  if (state === 'classifica') return { message: 'Classifica in aggiornamento...' };
  if (state === 'risultati') return { message: 'Risultati del round' };
  if (state === 'conclusa' || state === 'fine') return { message: 'Quiz terminato' };
  return { message: 'In attesa della prossima domanda...' };
}

function renderStateImage(state) {
  if (state === 'risultati') {
    return;
  }

  const img = document.getElementById('state-image');
  const message = document.getElementById('placeholder-message');
  if (!img || !message) return;

  const meta = getStateMeta(state);
  message.innerText = meta.message;

  if (mediaAttiva && mediaAttiva.file_path) {
    const mediaPath = mediaAttiva.file_path.startsWith('/') ? mediaAttiva.file_path.substring(1) : mediaAttiva.file_path;
    img.src = `${window.location.origin}${BASE_PUBLIC_URL}${mediaPath}`;
    img.alt = mediaAttiva.titolo || `Immagine stato: ${meta.message}`;
    return;
  }

  img.removeAttribute('src');
  img.alt = `Immagine stato: ${meta.message}`;
}

function hideRisultatiView() {
  document.getElementById('screen-risultati').classList.add('hidden');
}

function showRisultatiView() {
  document.getElementById('screen-placeholder').classList.add('hidden');
  document.getElementById('screen-domanda').classList.add('hidden');
  document.getElementById('screen-risultati').classList.remove('hidden');

  const stateImage = document.getElementById('state-image');
  if (stateImage) stateImage.removeAttribute('src');
}

function renderClassificaRisultati(classifica) {
  const listEl = document.getElementById('scoreboard-list');
  if (!listEl) return;

  if (!Array.isArray(classifica) || classifica.length === 0) {
    listEl.innerHTML = '<div class="scoreboard-empty">Nessun giocatore in classifica.</div>';
    return;
  }

  const ordinata = [...classifica]
    .sort((a, b) => Number(b.capitale_attuale ?? 0) - Number(a.capitale_attuale ?? 0))
    .slice(0, 10);

  listEl.innerHTML = ordinata.map((p, index) => {
    const nome = p.nome || 'Giocatore';
    const punti = Number(p.capitale_attuale ?? 0);
    return `
      <div class="scoreboard-item">
        <div class="score-rank">#${index + 1}</div>
        <div>${nome}</div>
        <div class="score-points">${punti}</div>
      </div>
    `;
  }).join('');
}

async function fetchClassificaRisultati() {
  if (!sessioneId) return;

  try {
    const r = await fetch(`${API_BASE}/classifica/${sessioneId || 0}`);
    const data = await r.json();

    if (!data.success) {
      renderClassificaRisultati([]);
      return;
    }

    renderClassificaRisultati(data.classifica || []);
  } catch (e) {
    console.error(e);
  }
}

function resolveMediaUrl(path) {
  const raw = String(path || '').trim();
  if (!raw) return '';
  if (/^https?:\/\//i.test(raw) || raw.startsWith('data:')) return raw;

  const clean = raw.startsWith('/') ? raw.substring(1) : raw;
  return `${window.location.origin}${BASE_PUBLIC_URL}${clean}`;
}

function getDomandaMediaNodes() {
  return {
    wrap: document.getElementById('domanda-media-screen'),
    image: document.getElementById('domanda-media-image-screen'),
    audio: document.getElementById('domanda-media-audio-screen'),
    caption: document.getElementById('domanda-media-caption-screen'),
  };
}

function clearDomandaMedia() {
  const { wrap, image, audio, caption } = getDomandaMediaNodes();

  if (image) {
    image.removeAttribute('src');
    image.classList.add('hidden');
  }

  if (audio) {
    window.clearTimeout(audio.__previewTimer);
    audio.pause();
    audio.removeAttribute('src');
    delete audio.dataset.mediaSrc;
    audio.load();
    audio.classList.add('hidden');
  }

  if (caption) {
    caption.innerText = '';
    caption.classList.add('hidden');
  }

  if (wrap) {
    wrap.classList.add('hidden');
  }
}

function renderDomandaMedia(domanda) {
  const { wrap, image, audio, caption } = getDomandaMediaNodes();
  if (!wrap) return;

  const imageUrl = resolveMediaUrl(domanda?.media_image_path);
  const audioUrl = resolveMediaUrl(domanda?.media_audio_path);
  const captionText = String(domanda?.media_caption || '').trim();

  let hasAny = false;

  if (image && imageUrl) {
    image.src = imageUrl;
    image.classList.remove('hidden');
    hasAny = true;
  } else if (image) {
    image.removeAttribute('src');
    image.classList.add('hidden');
  }

  if (audio && audioUrl) {
    const currentMediaSrc = String(audio.dataset.mediaSrc || '');
    if (currentMediaSrc !== audioUrl) {
      audio.src = audioUrl;
      audio.dataset.mediaSrc = audioUrl;
    }
    audio.classList.remove('hidden');
  } else if (audio) {
    window.clearTimeout(audio.__previewTimer);
    audio.pause();
    audio.removeAttribute('src');
    audio.load();
    audio.classList.add('hidden');
  }

  if (caption && captionText) {
    caption.innerText = captionText;
    caption.classList.remove('hidden');
    hasAny = true;
  } else if (caption) {
    caption.innerText = '';
    caption.classList.add('hidden');
  }

  wrap.classList.toggle('hidden', !hasAny);
}

async function playScreenAudioPreview(preview) {
  const { audio } = getDomandaMediaNodes();
  if (!audio || !preview || !preview.audio_path) return false;

  const src = resolveMediaUrl(preview.audio_path);
  if (!src) return false;

  window.clearTimeout(audio.__previewTimer);
  audio.pause();
  audio.classList.remove('hidden');
  audio.muted = false;
  audio.volume = 1;
  audio.preload = 'auto';
  audio.src = src;
  audio.dataset.mediaSrc = src;
  audio.currentTime = 0;
  audio.load();

  const previewSec = Number(preview.preview_sec ?? 0);
  if (previewSec > 0) {
    const stopAt = Math.max(1, Math.floor(previewSec));
    audio.__previewTimer = window.setTimeout(() => {
      try { audio.pause(); } catch (e) { console.warn(e); }
    }, stopAt * 1000);
  }

  try {
    await audio.play();
    pendingAudioPreview = null;
    return true;
  } catch (e) {
    console.warn('Audio preview play failed', e);
    pendingAudioPreview = preview;
    return false;
  }
}

async function fetchAudioPreviewStatus() {
  if (!sessioneId) return;

  try {
    const r = await fetch(`${API_BASE}/audioPreviewStato/${sessioneId || 0}&_=${Date.now()}`, {
      cache: 'no-store',
    });
    const data = await r.json();
    if (!data.success || !data.preview) return;

    const token = String(data.preview.token || '');
    if (!token || token === lastAudioPreviewToken) return;

    lastAudioPreviewToken = token;
    playScreenAudioPreview(data.preview);
  } catch (e) {
    console.error(e);
  }
}

function hideDomandaView() {
  hideRisultatiView();
  document.getElementById('screen-domanda').classList.add('hidden');
  document.getElementById('screen-placeholder').classList.remove('hidden');
  document.getElementById('domanda-testo').innerText = '';
  document.getElementById('opzioni').innerHTML = '';
  clearDomandaMedia();
  domandaRenderizzata = false;

  if (currentState !== 'domanda') {
    renderStateImage(currentState);
  }
}

function showDomandaView() {
  document.getElementById('screen-placeholder').classList.add('hidden');
  document.getElementById('screen-domanda').classList.remove('hidden');

  const stateImage = document.getElementById('state-image');
  if (stateImage) stateImage.removeAttribute('src');
}

function showDomandaLoadingView() {
  showDomandaView();

  if (domandaRenderizzata) return;

  const titolo = document.getElementById('domanda-testo');
  const opzioni = document.getElementById('opzioni');
  if (!titolo || !opzioni) return;

  titolo.innerText = 'Caricamento domanda...';
  clearDomandaMedia();

  if (opzioni.children.length > 0) return;

  opzioni.innerHTML = '';
  for (let i = 0; i < 4; i += 1) {
    const el = document.createElement('div');
    el.className = 'opzione';
    el.innerText = '...';
    opzioni.appendChild(el);
  }
}

function renderDomanda(domanda) {
  if (!domanda || !Array.isArray(domanda.opzioni)) {
    showDomandaLoadingView();
    return;
  }

  const titolo = document.getElementById('domanda-testo');
  const opzioni = document.getElementById('opzioni');

  titolo.innerText = domanda.testo || '';
  renderDomandaMedia(domanda);
  opzioni.innerHTML = '';

  domanda.opzioni.forEach((o) => {
    const el = document.createElement('div');
    el.className = 'opzione';
    el.innerText = o.testo || '';
    opzioni.appendChild(el);
  });

  domandaRenderizzata = true;
  showDomandaView();
}

async function fetchDomandaIfActive() {
  if (currentState !== 'domanda') {
    hideDomandaView();
    return;
  }

  try {
    const r = await fetch(`${API_BASE}/domanda/${sessioneId || 0}`);
    const data = await r.json();
    if (currentState !== 'domanda') return;

    if (!data.success) {
      if (!domandaRenderizzata) showDomandaLoadingView();
      return;
    }

    renderDomanda(data.domanda);
  } catch (e) {
    console.error(e);
  }
}

async function fetchMediaAttiva() {
  try {
    const r = await fetch(`${API_BASE}/mediaAttiva`);
    const data = await r.json();

    if (!data.success) return;

    mediaAttiva = data.media || null;

    if (currentState !== 'domanda' && currentState !== 'risultati') {
      renderStateImage(currentState);
    }
  } catch (e) {
    console.error(e);
  }
}

async function fetchStato() {
  if (!sessioneId) {
    hideDomandaView();
    resetStageTimer();
    return;
  }

  try {
    const r = await fetch(`${API_BASE}/stato/${sessioneId || 0}`);
    const data = await r.json();
    if (!data.success) {
      if (currentState === 'risultati') {
        showRisultatiView();
      } else {
        hideDomandaView();
      }
      resetStageTimer();
      return;
    }

    currentState = data.sessione?.stato || null;
    renderStageTimer(data.sessione || null);

    if (currentState === 'domanda') {
      hideRisultatiView();
      showDomandaLoadingView();
      fetchDomandaIfActive();
    } else if (currentState === 'risultati' || currentState === 'conclusa') {
      showRisultatiView();
      fetchClassificaRisultati();
    } else {
      hideDomandaView();
    }
  } catch (e) {
    console.error(e);
    hideDomandaView();
    resetStageTimer();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const fromUrl = extractSessioneIdFromUrl();
  if (!sessioneId && fromUrl) {
    sessioneId = fromUrl;
  }

  setupSessionQr();
  hideDomandaView();
  fetchMediaAttiva();
  fetchStato();
  fetchAudioPreviewStatus();

  const tryUnlockPendingAudio = () => {
    if (!pendingAudioPreview) return;
    playScreenAudioPreview(pendingAudioPreview);
  };

  document.addEventListener('pointerdown', tryUnlockPendingAudio);
  document.addEventListener('keydown', tryUnlockPendingAudio);

  if (pollStato) clearInterval(pollStato);
  pollStato = setInterval(() => {
    fetchStato();
    fetchAudioPreviewStatus();
  }, STATO_POLL_MS);

  if (pollMedia) clearInterval(pollMedia);
  pollMedia = setInterval(() => {
    fetchMediaAttiva();
  }, MEDIA_POLL_MS);
});
