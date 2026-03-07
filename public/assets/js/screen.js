/**
 * FILE: public/assets/js/screen.js
 * SCOPO: Gestione UI Schermo (stato sessione, domanda, classifica risultati, media placeholder, QR join).
 * UTILIZZATO DA: app/Views/screen/index.php tramite <script src="/chillquizV3/public/assets/js/screen.js"></script>.
 */

const SCREEN_BOOTSTRAP = window.SCREEN_BOOTSTRAP || {};
const BASE_PUBLIC_URL = String(SCREEN_BOOTSTRAP.basePublicUrl || window.location.pathname.replace(/index\.php$/, ''));
const PUBLIC_HOST = String(SCREEN_BOOTSTRAP.publicHost || window.location.host);
const API_BASE = `${BASE_PUBLIC_URL}index.php?url=api`;
const AUDIO_PREVIEW_STORAGE_PREFIX = 'chillquiz_audio_preview_';
let sessioneId = Number(SCREEN_BOOTSTRAP.sessioneId || 0);
let currentState = null;
let pollStato = null;
let pollMedia = null;
let timerTick = null;
let domandaRenderizzata = false;
let currentDomandaData = null;
let mediaAttiva = null;
let lastAudioPreviewToken = '';
let pendingAudioPreview = null;
let previewAudio = null;
const STATO_POLL_MS = 2500;
const MEDIA_POLL_MS = 30000;
let currentTimerStart = 0;
let audioUnlockedByUser = false;
let statoRequestInFlight = false;
let mediaRequestInFlight = false;
let audioPreviewRequestInFlight = false;
const QUESTION_TYPE_ICON_MAP = {
  CLASSIC: 'assets/img/question-types/classic.png',
  MEDIA: 'assets/img/question-types/classic.png',
  SARABANDA: 'assets/img/question-types/sarabanda.png',
  IMPOSTORE: 'assets/img/question-types/impostore.png',
  MEME: 'assets/img/question-types/meme.png',
  MAJORITY: 'assets/img/question-types/majority.png',
  RANDOM_BONUS: 'assets/img/question-types/random_bonus.png',
  BOMB: 'assets/img/question-types/bomb.png',
  CHAOS: 'assets/img/question-types/chaos.png',
  AUDIO_PARTY: 'assets/img/question-types/audio_party.png',
  IMAGE_PARTY: 'assets/img/question-types/image_party.png',
};

function extractSessioneIdFromUrl() {
  const raw = new URLSearchParams(window.location.search).get('url') || '';
  if (raw.startsWith('screen/')) {
    const id = parseInt(raw.split('/')[1], 10);
    if (!Number.isNaN(id) && id > 0) return id;
  }
  return 0;
}

function isDomandaState() {
  return String(currentState || '') === 'domanda';
}

function canUseAudioPreview() {
  return isDomandaState() && sessioneId > 0;
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
    const elapsed = Math.max(0, (Date.now() / 1000) - start);
    const remaining = Math.max(0, max - elapsed);
    const visibleRemaining = Math.max(0, Math.ceil(remaining));
    const pct = max > 0 ? (remaining / max) : 0;
    const deg = Math.max(0, Math.min(360, pct * 360));

    indicator.style.setProperty('--progress', `${deg}deg`);
    label.innerText = `${visibleRemaining}s`;

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
  clearQuestionTypeBadge();

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

function getPreviewAudio() {
  if (!previewAudio) {
    previewAudio = new Audio();
    previewAudio.preload = 'auto';
    previewAudio.playsInline = true;
  }
  return previewAudio;
}

function stopPreviewAudio() {
  const audio = getPreviewAudio();
  window.clearTimeout(audio.__previewTimer);
  try { audio.pause(); } catch (e) { console.warn(e); }
}

function clearPendingAudioPreview() {
  pendingAudioPreview = null;
  lastAudioPreviewToken = '';
  if (sessioneId > 0) {
    try {
      window.localStorage.removeItem(`${AUDIO_PREVIEW_STORAGE_PREFIX}${sessioneId}`);
    } catch (e) {
      console.warn(e);
    }
  }
}

function clearAudioPreviewRuntime() {
  stopPreviewAudio();
  clearPendingAudioPreview();
}

function enforceAudioStateGuard() {
  if (canUseAudioPreview()) return;
  clearAudioPreviewRuntime();
}

function readStoredAudioPreview() {
  if (!canUseAudioPreview()) return null;

  try {
    const raw = window.localStorage.getItem(`${AUDIO_PREVIEW_STORAGE_PREFIX}${sessioneId}`);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' ? parsed : null;
  } catch (e) {
    console.warn(e);
    return null;
  }
}

function buildPreviewFromCurrentDomanda() {
  if (!canUseAudioPreview()) return null;

  const domanda = currentDomandaData || null;
  const audioPath = String(domanda?.media_audio_path || '').trim();
  if (!domanda || audioPath === '') return null;

  return {
    token: '',
    sessione_id: sessioneId,
    domanda_id: Number(domanda.id || 0),
    audio_path: audioPath,
    preview_sec: Math.max(0, Number(domanda.media_audio_preview_sec || 0)),
    created_at: Math.floor(Date.now() / 1000),
  };
}

async function fetchLatestAudioPreviewCommand() {
  if (!canUseAudioPreview()) return null;

  try {
    const r = await fetch(`${API_BASE}/audioPreviewStato/${sessioneId || 0}&_=${Date.now()}`, {
      cache: 'no-store',
    });
    const data = await r.json();
    if (!data.success || !data.preview) return null;
    return data.preview;
  } catch (e) {
    console.error(e);
    return null;
  }
}

async function notifyAudioPreviewStarted(preview) {
  if (!preview || !canUseAudioPreview()) return false;

  try {
    const formData = new FormData();
    if (preview.token) {
      formData.append('token', String(preview.token));
    }
    if (preview.domanda_id) {
      formData.append('domanda_id', String(preview.domanda_id));
    }

    const r = await fetch(`${API_BASE}/audioPreviewStarted/${sessioneId || 0}`, {
      method: 'POST',
      body: formData,
    });
    const data = await r.json();
    return !!data.success;
  } catch (e) {
    console.error(e);
    return false;
  }
}

function getQuestionTypeBadgeNodes() {
  return {
    wrap: document.getElementById('question-type-badge-screen'),
    image: document.getElementById('question-type-badge-image-screen'),
    label: document.getElementById('question-type-badge-label-screen'),
  };
}

function hasInteractiveBadgeAudio() {
  if (!canUseAudioPreview()) return false;
  const currentAudio = String(currentDomandaData?.media_audio_path || '').trim() !== '';
  const pendingAudio = String(pendingAudioPreview?.audio_path || '').trim() !== '';
  const storedAudio = String(readStoredAudioPreview()?.audio_path || '').trim() !== '';
  return currentAudio || pendingAudio || storedAudio;
}

async function handleQuestionTypeBadgeClick() {
  if (!canUseAudioPreview()) return;
  if (!hasInteractiveBadgeAudio()) return;

  let preview = pendingAudioPreview;
  if (!preview) {
    preview = readStoredAudioPreview();
  }
  if (!preview) {
    preview = await fetchLatestAudioPreviewCommand();
  }
  if (!preview) {
    preview = buildPreviewFromCurrentDomanda();
  }
  if (!preview) return;

  pendingAudioPreview = preview;
  let played = await playScreenAudioPreview(preview);
  if (played) return;

  await unlockAudioByGesture();
  await playScreenAudioPreview(preview);
}

function clearQuestionTypeBadge() {
  const { wrap, image, label } = getQuestionTypeBadgeNodes();
  if (image) {
    image.removeAttribute('src');
    image.classList.add('hidden');
  }
  if (label) {
    label.innerText = '';
    label.classList.add('hidden');
  }
  if (wrap) {
    wrap.classList.add('hidden');
    wrap.classList.remove('is-interactive');
    wrap.classList.add('is-static');
  }
}

function normalizeQuestionType(domanda) {
  const tipo = String(domanda?.tipo_domanda || 'CLASSIC').trim().toUpperCase();
  const hasAudio = String(domanda?.media_audio_path || '').trim() !== '';
  if (tipo === 'SARABANDA' && !hasAudio) return 'CLASSIC';
  if (tipo) return tipo;
  return 'CLASSIC';
}

function resolveQuestionTypeIconPath(questionType) {
  const rel = QUESTION_TYPE_ICON_MAP[questionType] || '';
  if (!rel) return '';
  const clean = rel.startsWith('/') ? rel.substring(1) : rel;
  return `${window.location.origin}${BASE_PUBLIC_URL}${clean}`;
}

function renderQuestionTypeBadge(domanda) {
  const { wrap, image, label } = getQuestionTypeBadgeNodes();
  if (!wrap || !image || !label) return;

  const questionType = normalizeQuestionType(domanda);
  const iconUrl = resolveQuestionTypeIconPath(questionType);

  if (!iconUrl) {
    clearQuestionTypeBadge();
    return;
  }

  image.onerror = () => {
    clearQuestionTypeBadge();
  };
  image.src = iconUrl;
  image.alt = `Tipologia domanda: ${questionType}`;
  image.classList.remove('hidden');
  label.innerText = '';
  label.classList.add('hidden');

  wrap.classList.toggle('is-interactive', hasInteractiveBadgeAudio());
  wrap.classList.toggle('is-static', !hasInteractiveBadgeAudio());
  wrap.classList.remove('hidden');
}

function clearDomandaMedia() {
  const { wrap, image, audio, caption } = getDomandaMediaNodes();
  clearAudioPreviewRuntime();

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
    wrap.classList.remove('hidden');
    wrap.classList.add('media-slot-empty');
  }
}

function renderDomandaMedia(domanda, imageOnly = false) {
  const { wrap, image, audio, caption } = getDomandaMediaNodes();
  if (!wrap) return;

  const imageUrl = resolveMediaUrl(domanda?.media_image_path);
  const audioUrl = resolveMediaUrl(domanda?.media_audio_path);
  const captionText = imageOnly ? '' : String(domanda?.media_caption || '').trim();

  let hasAny = false;

  if (image && imageUrl) {
    image.src = imageUrl;
    image.classList.remove('hidden');
    hasAny = true;
  } else if (image) {
    image.removeAttribute('src');
    image.classList.add('hidden');
  }

  if (!imageOnly && audio && audioUrl) {
    const currentMediaSrc = String(audio.dataset.mediaSrc || '');
    if (currentMediaSrc !== audioUrl) {
      audio.src = audioUrl;
      audio.dataset.mediaSrc = audioUrl;
    }
    audio.classList.remove('hidden');
  } else if (!imageOnly && audio) {
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

  wrap.classList.remove('hidden');
  wrap.classList.toggle('has-media', hasAny);
  wrap.classList.toggle('media-slot-empty', !hasAny);
}

async function playScreenAudioPreview(preview) {
  if (!canUseAudioPreview()) {
    clearAudioPreviewRuntime();
    return false;
  }
  if (!preview || !preview.audio_path) return false;

  const src = resolveMediaUrl(preview.audio_path);
  if (!src) return false;

  const audio = getPreviewAudio();
  window.clearTimeout(audio.__previewTimer);
  audio.pause();
  audio.muted = false;
  audio.volume = 1;
  audio.playsInline = true;
  audio.preload = 'auto';
  audio.src = `${src}${src.includes('?') ? '&' : '?'}_=${Date.now()}`;
  audio.currentTime = 0;
  audio.load();

  const previewSec = Number(preview.preview_sec ?? 0);
  if (previewSec > 0) {
    const stopAt = Math.max(1, Math.floor(previewSec));
    audio.__previewTimer = window.setTimeout(() => {
      try { audio.pause(); } catch (e) { console.warn(e); }
      clearPendingAudioPreview();
    }, stopAt * 1000);
  }

  try {
    await audio.play();
    await notifyAudioPreviewStarted(preview);
    clearPendingAudioPreview();
    return true;
  } catch (e) {
    try {
      audio.muted = true;
      await audio.play();
      audio.muted = false;
      await notifyAudioPreviewStarted(preview);
      clearPendingAudioPreview();
      return true;
    } catch (e2) {
      console.warn('Audio preview play failed', e2);
      pendingAudioPreview = preview;
      return false;
    }
  }
}

async function unlockAudioByGesture() {
  if (!canUseAudioPreview()) return false;
  if (audioUnlockedByUser) return true;

  const audio = getPreviewAudio();

  try {
    audio.src = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';
    audio.muted = true;
    audio.volume = 0;
    audio.playsInline = true;
    audio.preload = 'auto';
    await audio.play();
    audio.pause();
    audio.currentTime = 0;
    audio.muted = false;
    audio.volume = 1;
    audioUnlockedByUser = true;
    return true;
  } catch (e) {
    return false;
  }
}

async function fetchAudioPreviewStatus() {
  if (!canUseAudioPreview()) {
    enforceAudioStateGuard();
    return;
  }
  if (audioPreviewRequestInFlight) return;
  audioPreviewRequestInFlight = true;

  try {
    const r = await fetch(`${API_BASE}/audioPreviewStato/${sessioneId || 0}&_=${Date.now()}`, {
      cache: 'no-store',
    });
    const data = await r.json();
    if (!data.success || !data.preview) return;

    const token = String(data.preview.token || '');
    if (!token || token === lastAudioPreviewToken) return;

    lastAudioPreviewToken = token;
    pendingAudioPreview = data.preview;
    try {
      window.localStorage.setItem(`${AUDIO_PREVIEW_STORAGE_PREFIX}${sessioneId}`, JSON.stringify(data.preview));
    } catch (e) {
      console.warn(e);
    }
  } catch (e) {
    console.error(e);
  } finally {
    audioPreviewRequestInFlight = false;
  }
}

function hideDomandaView() {
  clearAudioPreviewRuntime();
  currentDomandaData = null;
  hideRisultatiView();
  document.getElementById('screen-domanda').classList.add('hidden');
  document.getElementById('screen-placeholder').classList.remove('hidden');
  document.getElementById('domanda-testo').innerText = '';
  document.getElementById('opzioni').innerHTML = '';
  clearQuestionTypeBadge();
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
  clearQuestionTypeBadge();
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

  currentDomandaData = domanda;

  const tipoDomanda = normalizeQuestionType(domanda);
  const nowSec = Math.floor(Date.now() / 1000);
  const isSarabandaIntro = tipoDomanda === 'SARABANDA' && (currentTimerStart <= 0 || nowSec < currentTimerStart);

  const titolo = document.getElementById('domanda-testo');
  const opzioni = document.getElementById('opzioni');

  titolo.innerText = isSarabandaIntro ? '' : (domanda.testo || '');
  renderQuestionTypeBadge(domanda);
  if (isSarabandaIntro) {
    renderDomandaMedia(domanda, true);
  } else {
    renderDomandaMedia(domanda, false);
  }
  opzioni.innerHTML = '';

  if (isSarabandaIntro) {
    domandaRenderizzata = true;
    showDomandaView();
    return;
  }

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
  if (!isDomandaState()) {
    hideDomandaView();
    return;
  }

  try {
    const r = await fetch(`${API_BASE}/domanda/${sessioneId || 0}`);
    const data = await r.json();
    if (!isDomandaState()) return;

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
  if (mediaRequestInFlight) return;
  mediaRequestInFlight = true;
  try {
    const r = await fetch(`${API_BASE}/mediaAttiva`);
    const data = await r.json();

    if (!data.success) return;

    mediaAttiva = data.media || null;

    if (!isDomandaState() && currentState !== 'risultati') {
      renderStateImage(currentState);
    }
  } catch (e) {
    console.error(e);
  } finally {
    mediaRequestInFlight = false;
  }
}

async function fetchStato() {
  if (!sessioneId) {
    hideDomandaView();
    resetStageTimer();
    return;
  }
  if (statoRequestInFlight) {
    return;
  }
  statoRequestInFlight = true;

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
      enforceAudioStateGuard();
      return;
    }

    currentState = data.sessione?.stato || null;
    currentTimerStart = Number(data.sessione?.timer_start || 0);
    renderStageTimer(data.sessione || null);

    if (isDomandaState()) {
      hideRisultatiView();
      showDomandaLoadingView();
      fetchDomandaIfActive();
    } else if (currentState === 'risultati' || currentState === 'conclusa') {
      showRisultatiView();
      fetchClassificaRisultati();
      enforceAudioStateGuard();
    } else {
      hideDomandaView();
      enforceAudioStateGuard();
    }
  } catch (e) {
    console.error(e);
    hideDomandaView();
    resetStageTimer();
    enforceAudioStateGuard();
  } finally {
    statoRequestInFlight = false;
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

  const tryUnlockPendingAudio = async () => {
    if (!canUseAudioPreview()) return;

    let preview = pendingAudioPreview;
    if (!preview) {
      preview = readStoredAudioPreview();
    }
    if (!preview) {
      preview = await fetchLatestAudioPreviewCommand();
    }
    if (!preview) {
      preview = buildPreviewFromCurrentDomanda();
    }
    if (!preview) return;

    pendingAudioPreview = preview;
    let played = await playScreenAudioPreview(preview);
    if (played) return;

    await unlockAudioByGesture();
    await playScreenAudioPreview(preview);
  };

  const { wrap: badgeWrap, image: badgeImage } = getQuestionTypeBadgeNodes();
  if (badgeWrap) {
    badgeWrap.addEventListener('click', handleQuestionTypeBadgeClick);
  }
  if (badgeImage) {
    badgeImage.addEventListener('click', handleQuestionTypeBadgeClick);
  }

  document.addEventListener('pointerdown', tryUnlockPendingAudio);
  document.addEventListener('keydown', tryUnlockPendingAudio);
  window.addEventListener('storage', (event) => {
    if (event.key !== `${AUDIO_PREVIEW_STORAGE_PREFIX}${sessioneId}`) return;
    if (!canUseAudioPreview()) {
      enforceAudioStateGuard();
      return;
    }
    if (!event.newValue) {
      clearPendingAudioPreview();
      return;
    }
    try {
      const parsed = JSON.parse(event.newValue);
      if (parsed && typeof parsed === 'object') {
        pendingAudioPreview = parsed;
        lastAudioPreviewToken = String(parsed.token || lastAudioPreviewToken || '');
      }
    } catch (e) {
      console.warn(e);
    }
  });

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
