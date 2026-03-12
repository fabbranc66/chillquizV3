/**
 * FILE: public/assets/js/screen.js
 * SCOPO: Gestione UI Schermo (stato sessione, domanda, classifica risultati, media placeholder, QR join).
 * UTILIZZATO DA: app/Views/screen/index.php tramite asset JS screen.
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
let memeRotationTimer = null;
let memeRotationStep = -1;
const QUESTION_TYPE_ICON_MAP = {
  CLASSIC: 'assets/img/question-types/classic.png',
  MEDIA: 'assets/img/question-types/classic.png',
  SARABANDA: 'assets/img/question-types/sarabanda.png',
  IMPOSTORE: 'assets/img/question-types/classic.png',
  MEME: 'assets/img/question-types/classic.png',
  MAJORITY: 'assets/img/question-types/classic.png',
  RANDOM_BONUS: 'assets/img/question-types/classic.png',
  BOMB: 'assets/img/question-types/classic.png',
  CHAOS: 'assets/img/question-types/classic.png',
  AUDIO_PARTY: 'assets/img/question-types/classic.png',
  IMAGE_PARTY: 'assets/img/question-types/classic.png',
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

  const totalPlayers = Array.isArray(classifica) ? classifica.length : 0;
  const isDesktop = !!(window.matchMedia && window.matchMedia('(min-width: 1024px)').matches);
  const columns = isDesktop
    ? (totalPlayers <= 8 ? 1 : (totalPlayers <= 16 ? 2 : (totalPlayers <= 24 ? 3 : 4)))
    : 1;

  const parseScore = (value) => {
    if (typeof value === 'number') {
      return Number.isFinite(value) ? value : 0;
    }
    const normalized = String(value ?? '').trim().replace(/[^\d-]/g, '');
    if (normalized === '' || normalized === '-') return 0;
    const parsed = Number.parseInt(normalized, 10);
    return Number.isFinite(parsed) ? parsed : 0;
  };

  const ordinata = [...classifica]
    .sort((a, b) => parseScore(b.capitale_attuale ?? 0) - parseScore(a.capitale_attuale ?? 0))
    .map((row, index) => ({ ...row, __rank: index + 1 }));

  const rows = columns > 1 ? Math.ceil(ordinata.length / columns) : ordinata.length;
  const visualOrder = [];

  if (columns > 1) {
    for (let row = 0; row < rows; row += 1) {
      for (let col = 0; col < columns; col += 1) {
        const sourceIndex = (col * rows) + row;
        if (sourceIndex < ordinata.length) {
          visualOrder.push(ordinata[sourceIndex]);
        }
      }
    }
  } else {
    visualOrder.push(...ordinata);
  }

  const formatThousands = (value) => {
    const toGroupedIt = (n) => {
      const sign = n < 0 ? '-' : '';
      const abs = Math.abs(Math.trunc(n));
      return `${sign}${String(abs).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
    };

    if (typeof value === 'number') {
      return Number.isFinite(value) ? toGroupedIt(value) : '0';
    }
    const normalized = String(value ?? '').trim().replace(/[^\d-]/g, '');
    if (normalized === '' || normalized === '-') return '0';
    const parsed = Number.parseInt(normalized, 10);
    if (!Number.isFinite(parsed)) return '0';
    return toGroupedIt(parsed);
  };

  listEl.style.setProperty('--scoreboard-cols', String(Math.max(1, Number(columns || 1))));
  if (totalPlayers <= 32 && Number(columns || 1) > 0) {
    const viewportWidth = Math.max(320, window.innerWidth || document.documentElement.clientWidth || 0);
    const fullWrapWidth = Math.min(2400, Math.max(320, viewportWidth - 20));
    const colGap = 10;
    const colWidth = Math.max(180, (fullWrapWidth - (3 * colGap)) / 4);
    const maxWidth = (columns * colWidth) + ((columns - 1) * colGap);
    listEl.style.maxWidth = `${maxWidth}px`;
    listEl.style.marginInline = 'auto';
  } else {
    listEl.style.maxWidth = '';
    listEl.style.marginInline = '';
  }

  listEl.innerHTML = visualOrder.map((p, index) => {
    const nome = p.nome || 'Giocatore';
    const punti = p.capitale_attuale ?? 0;
    const rank = Number(p.__rank || (index + 1));
    return `
      <div class="scoreboard-item">
        <div class="score-rank">#${rank}</div>
        <div class="score-name" title="${nome}">${nome}</div>
        <div class="score-points">${formatThousands(punti)}</div>
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

function getDomandaStatusMessageNode() {
  return document.getElementById('domanda-status-message-screen');
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

function stopMemeRotation() {
  if (memeRotationTimer) {
    window.clearInterval(memeRotationTimer);
    memeRotationTimer = null;
  }
  memeRotationStep = -1;
}

function getMemeRotationStep(domanda) {
  const rotationMs = Math.max(100, Number(domanda?.meme_rotation_ms || 300));
  const elapsedMs = currentTimerStart > 0
    ? Math.max(0, Math.floor(((Date.now() / 1000) - currentTimerStart) * 1000))
    : 0;
  return Math.floor(elapsedMs / rotationMs);
}

function getMemeSlots(step) {
  const base = [
    { letter: 'A', palette: 1 },
    { letter: 'B', palette: 2 },
    { letter: 'C', palette: 3 },
    { letter: 'D', palette: 4 },
  ];
  const normalized = ((Number(step || 0) % base.length) + base.length) % base.length;
  if (normalized === 0) return base;
  return base.slice(normalized).concat(base.slice(0, normalized));
}

function renderMemeOptions(domanda, showCorrect, correctOptionId) {
  const opzioniNode = document.getElementById('opzioni');
  if (!opzioniNode) return;

  const baseOptions = Array.isArray(domanda?.opzioni) ? [...domanda.opzioni] : [];
  stopMemeRotation();
  memeRotationStep = 0;
  const slots = getMemeSlots(0);
  opzioniNode.innerHTML = '';

  baseOptions.forEach((opzione, index) => {
    const slot = slots[index] || { letter: String(index + 1), palette: (index % 4) + 1 };
    const el = document.createElement('div');
    el.className = `opzione opzione-meme opzione-kahoot-${slot.palette}`;
    if (showCorrect) {
      if (String(opzione?.id || '') === correctOptionId) {
        el.classList.add('is-correct-reveal');
      } else {
        el.classList.add('is-reveal-dim');
      }
    }

    const label = document.createElement('span');
    label.className = 'opzione-lettera';
    label.innerText = slot.letter;

    const text = document.createElement('span');
    text.className = 'opzione-testo';
    text.innerText = String(opzione?.display_text || opzione?.testo || '');
    if (opzione?.is_meme_display) {
      text.classList.add('is-meme-display');
    }

    el.appendChild(label);
    el.appendChild(text);
    opzioniNode.appendChild(el);
  });
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
  stopMemeRotation();
  clearAudioPreviewRuntime();
  currentDomandaData = null;
  hideRisultatiView();
  document.getElementById('screen-domanda').classList.add('hidden');
  document.getElementById('screen-placeholder').classList.remove('hidden');
  document.getElementById('domanda-testo').innerText = '';
  const statusMessage = getDomandaStatusMessageNode();
  if (statusMessage) {
    statusMessage.innerText = '';
    statusMessage.classList.add('hidden');
    statusMessage.classList.remove('is-impostore');
    statusMessage.classList.remove('is-meme');
  }
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
  stopMemeRotation();
  showDomandaView();

  if (domandaRenderizzata) return;

  const titolo = document.getElementById('domanda-testo');
  const opzioni = document.getElementById('opzioni');
  const statusMessage = getDomandaStatusMessageNode();
  if (!titolo || !opzioni) return;

  titolo.innerText = 'Caricamento domanda...';
  if (statusMessage) {
    statusMessage.innerText = '';
    statusMessage.classList.add('hidden');
    statusMessage.classList.remove('is-impostore');
    statusMessage.classList.remove('is-meme');
  }
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
  const isImpostoreMasked = !!domanda.impostore_masked;
  const showCorrect = !!domanda.show_correct;
  const correctOptionId = String(domanda.correct_option_id || '');
  const hasMemeDecoratedOptions = Array.isArray(domanda.opzioni)
    && domanda.opzioni.some((opzione) => String(opzione?.display_text || '') !== '');
  const isMemeMode = !!domanda.meme_mode || tipoDomanda === 'MEME' || hasMemeDecoratedOptions;

  const titolo = document.getElementById('domanda-testo');
  const opzioni = document.getElementById('opzioni');
  const statusMessage = getDomandaStatusMessageNode();

  titolo.innerText = isImpostoreMasked ? '' : (isSarabandaIntro ? '' : (domanda.testo || ''));
  if (statusMessage) {
    if (isMemeMode) {
      statusMessage.innerText = String(domanda.meme_screen_notice || 'Modalita MEME: le risposte ruotano ogni 0,3 secondi.');
      statusMessage.classList.remove('hidden');
      statusMessage.classList.add('is-meme');
      statusMessage.classList.remove('is-impostore');
    } else if (isImpostoreMasked) {
      statusMessage.innerText = String(domanda.impostore_screen_notice || 'Modalita IMPOSTORE: lo schermo non mostra la domanda.');
      statusMessage.classList.remove('hidden');
      statusMessage.classList.add('is-impostore');
      statusMessage.classList.remove('is-meme');
    } else {
      statusMessage.innerText = '';
      statusMessage.classList.add('hidden');
      statusMessage.classList.remove('is-impostore');
      statusMessage.classList.remove('is-meme');
    }
  }
  renderQuestionTypeBadge(domanda);
  if (isImpostoreMasked) {
    clearDomandaMedia();
    const mediaNodes = getDomandaMediaNodes();
    if (mediaNodes.wrap) {
      mediaNodes.wrap.classList.add('hidden');
    }
  } else if (isSarabandaIntro) {
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

  if (isMemeMode) {
    renderMemeOptions(domanda, showCorrect, correctOptionId);
    domandaRenderizzata = true;
    showDomandaView();
    return;
  }

  domanda.opzioni.forEach((o) => {
    const el = document.createElement('div');
    el.className = 'opzione';
    el.innerText = o.testo || '';

    if (showCorrect) {
      if (String(o.id || '') === correctOptionId) {
        el.classList.add('is-correct-reveal');
      } else {
        el.classList.add('is-reveal-dim');
      }
    }

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
    const url = new URL(`${API_BASE}/domanda/${sessioneId || 0}`, window.location.origin);
    url.searchParams.set('viewer', 'screen');
    const r = await fetch(url.toString());
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

