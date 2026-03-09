/* public/assets/js/screen/domanda.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const S = ScreenApp.store;

  const QUESTION_TYPE_ICON_MAP = {
    SARABANDA: 'assets/img/question-types/sarabanda.png',
  };

  function isSarabandaQuestionType(domanda) {
    return normalizeQuestionType(domanda) === 'SARABANDA';
  }

  function resolveMediaUrl(path) {
    const raw = String(path || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw) || raw.startsWith('data:')) return raw;
    const clean = raw.startsWith('/') ? raw.substring(1) : raw;
    return `${window.location.origin}${ScreenApp.api.publicBaseUrl}${clean}`;
  }

  function getDomandaMediaNodes() {
    return {
      wrap: document.getElementById('domanda-media-screen'),
      image: document.getElementById('domanda-media-image-screen'),
      audio: document.getElementById('domanda-media-audio-screen'),
      caption: document.getElementById('domanda-media-caption-screen'),
    };
  }

  function getDomandaStatusMessageNode() {
    return document.getElementById('domanda-status-message-screen');
  }

  function getQuestionTypeBadgeNodes() {
    return {
      wrap: document.getElementById('question-type-badge-screen'),
      image: document.getElementById('question-type-badge-image-screen'),
      label: document.getElementById('question-type-badge-label-screen'),
    };
  }

  function getPreviewAudio() {
    if (!S.previewAudio) {
      S.previewAudio = new Audio();
      S.previewAudio.preload = 'auto';
      S.previewAudio.playsInline = true;
    }
    return S.previewAudio;
  }

  function stopPreviewAudio() {
    const audio = getPreviewAudio();
    window.clearTimeout(audio.__previewTimer);
    try { audio.pause(); } catch (error) { console.warn(error); }
  }

  function clearPendingAudioPreview() {
    S.pendingAudioPreview = null;
    S.lastAudioPreviewToken = '';
    if (S.sessioneId > 0) {
      try {
        window.localStorage.removeItem(`${S.audioPreviewStoragePrefix}${S.sessioneId}`);
      } catch (error) {
        console.warn(error);
      }
    }
  }

  function clearAudioPreviewRuntime() {
    stopPreviewAudio();
    clearPendingAudioPreview();
  }

  function enforceAudioStateGuard() {
    if (ScreenApp.state.canUseAudioPreview()) return;
    clearAudioPreviewRuntime();
  }

  function readStoredAudioPreview() {
    if (!ScreenApp.state.canUseAudioPreview()) return null;
    try {
      const raw = window.localStorage.getItem(`${S.audioPreviewStoragePrefix}${S.sessioneId}`);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : null;
    } catch (error) {
      console.warn(error);
      return null;
    }
  }

  function buildPreviewFromCurrentDomanda() {
    if (!ScreenApp.state.canUseAudioPreview()) return null;
    const domanda = S.currentDomandaData || null;
    const audioPath = String(domanda?.media_audio_path || '').trim();
    if (!domanda || audioPath === '') return null;

    return {
      token: '',
      sessione_id: S.sessioneId,
      domanda_id: Number(domanda.id || 0),
      audio_path: audioPath,
      preview_sec: Math.max(0, Number(domanda.media_audio_preview_sec || 0)),
      created_at: Math.floor(Date.now() / 1000),
    };
  }

  async function fetchLatestAudioPreviewCommand() {
    if (!ScreenApp.state.canUseAudioPreview()) return null;
    try {
      const data = await ScreenApp.api.fetchJson(`${ScreenApp.api.apiBase}/audioPreviewStato/${S.sessioneId || 0}&_=${Date.now()}`);
      return data.success && data.preview ? data.preview : null;
    } catch (error) {
      console.error(error);
      return null;
    }
  }

  async function notifyAudioPreviewStarted(preview) {
    if (!preview || !ScreenApp.state.canUseAudioPreview()) return false;
    try {
      const formData = new FormData();
      if (preview.token) formData.append('token', String(preview.token));
      if (preview.domanda_id) formData.append('domanda_id', String(preview.domanda_id));

      const data = await ScreenApp.api.fetchJson(`${ScreenApp.api.apiBase}/audioPreviewStarted/${S.sessioneId || 0}`, {
        method: 'POST',
        body: formData,
      });

      return !!data.success;
    } catch (error) {
      console.error(error);
      return false;
    }
  }

  function hasInteractiveBadgeAudio() {
    if (!ScreenApp.state.canUseAudioPreview()) return false;
    if (!isSarabandaQuestionType(S.currentDomandaData)) return false;
    const currentAudio = String(S.currentDomandaData?.media_audio_path || '').trim() !== '';
    const pendingAudio = String(S.pendingAudioPreview?.audio_path || '').trim() !== '';
    const storedAudio = String(readStoredAudioPreview()?.audio_path || '').trim() !== '';
    return currentAudio || pendingAudio || storedAudio;
  }

  async function resolveScreenAudioPreviewSource() {
    return S.pendingAudioPreview
      || readStoredAudioPreview()
      || await fetchLatestAudioPreviewCommand()
      || buildPreviewFromCurrentDomanda();
  }

  async function playScreenAudioPreview(preview) {
    if (!ScreenApp.state.canUseAudioPreview()) {
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
        try { audio.pause(); } catch (error) { console.warn(error); }
        clearPendingAudioPreview();
      }, stopAt * 1000);
    }

    try {
      await audio.play();
      await notifyAudioPreviewStarted(preview);
      clearPendingAudioPreview();
      return true;
    } catch (error) {
      try {
        audio.muted = true;
        await audio.play();
        audio.muted = false;
        await notifyAudioPreviewStarted(preview);
        clearPendingAudioPreview();
        return true;
      } catch (secondError) {
        console.warn('Audio preview play failed', secondError);
        S.pendingAudioPreview = preview;
        return false;
      }
    }
  }

  async function unlockAudioByGesture() {
    if (!ScreenApp.state.canUseAudioPreview()) return false;
    if (S.audioUnlockedByUser) return true;

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
      S.audioUnlockedByUser = true;
      return true;
    } catch (error) {
      return false;
    }
  }

  async function handleQuestionTypeBadgeClick() {
    if (!ScreenApp.state.canUseAudioPreview() || !hasInteractiveBadgeAudio()) return;

    let preview = await resolveScreenAudioPreviewSource();
    if (!preview) return;

    S.pendingAudioPreview = preview;
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
    return tipo || 'CLASSIC';
  }

  function resolveQuestionTypeIconPath(questionType) {
    const rel = QUESTION_TYPE_ICON_MAP[questionType] || '';
    if (!rel) return '';
    const clean = rel.startsWith('/') ? rel.substring(1) : rel;
    return `${window.location.origin}${ScreenApp.api.publicBaseUrl}${clean}`;
  }

  function renderQuestionTypeBadge(domanda) {
    const { wrap, image, label } = getQuestionTypeBadgeNodes();
    if (!wrap || !image || !label) return;

    if (!isSarabandaQuestionType(domanda)) {
      clearQuestionTypeBadge();
      return;
    }
    const questionType = 'SARABANDA';
    const iconUrl = resolveQuestionTypeIconPath(questionType);
    if (!iconUrl) {
      clearQuestionTypeBadge();
      return;
    }

    image.onerror = () => clearQuestionTypeBadge();
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

  function renderDomandaMedia(domanda, imageOnly, overrides = {}) {
    const { wrap, image, audio, caption } = getDomandaMediaNodes();
    if (!wrap) return;

    const imageUrl = resolveMediaUrl(overrides.media_image_path || domanda?.media_image_path);
    const audioUrl = resolveMediaUrl(overrides.media_audio_path || domanda?.media_audio_path);
    const captionText = imageOnly ? '' : String(overrides.media_caption ?? domanda?.media_caption ?? '').trim();
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

  function clearStatusMessage() {
    const statusMessage = getDomandaStatusMessageNode();
    if (!statusMessage) {
      return;
    }

    statusMessage.innerText = '';
    statusMessage.classList.add('hidden');
    statusMessage.classList.remove('is-impostore');
    statusMessage.classList.remove('is-meme');
  }

  function renderStatusMessage(domanda, isMemeMode, isImpostoreMasked) {
    const statusMessage = getDomandaStatusMessageNode();
    if (!statusMessage) {
      return;
    }

    if (isMemeMode) {
      statusMessage.innerText = String(domanda.meme_screen_notice || 'Modalita MEME: le risposte ruotano ogni 0,25 secondi.');
      statusMessage.classList.remove('hidden');
      statusMessage.classList.add('is-meme');
      statusMessage.classList.remove('is-impostore');
      return;
    }

    if (isImpostoreMasked) {
      statusMessage.innerText = String(domanda.impostore_screen_notice || 'Modalita IMPOSTORE: lo schermo non mostra la domanda.');
      statusMessage.classList.remove('hidden');
      statusMessage.classList.add('is-impostore');
      statusMessage.classList.remove('is-meme');
      return;
    }

    clearStatusMessage();
  }

  function renderQuestionMediaForState(domanda, isImpostoreMasked, isSarabandaIntro) {
    if (isImpostoreMasked) {
      renderDomandaMedia(domanda, false, {
        media_image_path: '/assets/img/player/impostore-fake.svg',
        media_caption: 'Immagine mascherata per la modalita impostore',
      });
      return;
    }

    if (isSarabandaIntro) {
      renderDomandaMedia(domanda, true);
      return;
    }

    renderDomandaMedia(domanda, false);
  }

  function renderLoadingOptions(opzioni) {
    if (opzioni.children.length > 0) return;

    opzioni.innerHTML = '';
    for (let i = 0; i < 4; i += 1) {
      const el = document.createElement('div');
      el.className = 'opzione';
      el.innerText = '...';
      opzioni.appendChild(el);
    }
  }

  function stopMemeRotation() {
    if (S.memeRotationTimer) {
      window.clearInterval(S.memeRotationTimer);
      S.memeRotationTimer = null;
    }
    S.memeRotationStep = -1;
  }

  function getMemeSlots(step) {
    const base = [
      { letter: 'A', palette: 1 },
      { letter: 'B', palette: 2 },
      { letter: 'C', palette: 3 },
      { letter: 'D', palette: 4 },
    ];
    const normalized = ((Number(step || 0) % base.length) + base.length) % base.length;
    return normalized === 0 ? base : base.slice(normalized).concat(base.slice(0, normalized));
  }

  function renderMemeOptions(domanda, showCorrect, correctOptionId) {
    const opzioniNode = document.getElementById('opzioni');
    if (!opzioniNode) return;

    const baseOptions = Array.isArray(domanda?.opzioni) ? [...domanda.opzioni] : [];
    stopMemeRotation();
    S.memeRotationStep = 0;
    const slots = getMemeSlots(0);
    opzioniNode.innerHTML = '';

    baseOptions.forEach((opzione, index) => {
      const slot = slots[index] || { letter: String(index + 1), palette: (index % 4) + 1 };
      const el = document.createElement('div');
      el.className = `opzione opzione-meme opzione-kahoot-${slot.palette}`;
      if (showCorrect) {
        if (String(opzione?.id || '') === correctOptionId) el.classList.add('is-correct-reveal');
        else el.classList.add('is-reveal-dim');
      }

      const label = document.createElement('span');
      label.className = 'opzione-lettera';
      label.innerText = slot.letter;

      const text = document.createElement('span');
      text.className = 'opzione-testo';
      text.innerText = String(opzione?.display_text || opzione?.testo || '');
      if (opzione?.is_meme_display) text.classList.add('is-meme-display');

      el.appendChild(label);
      el.appendChild(text);
      opzioniNode.appendChild(el);
    });
  }

  function showView() {
    ScreenApp.state.showOnly('domanda');
    const stateImage = document.getElementById('state-image');
    if (stateImage) stateImage.removeAttribute('src');
  }

  function hideView() {
    stopMemeRotation();
    clearAudioPreviewRuntime();
    S.currentDomandaData = null;
    ScreenApp.state.hideRisultatiView();
    ScreenApp.state.showOnly('placeholder');

    const titolo = document.getElementById('domanda-testo');
    const opzioni = document.getElementById('opzioni');

    if (titolo) titolo.innerText = '';
    if (opzioni) opzioni.innerHTML = '';
    clearStatusMessage();

    clearQuestionTypeBadge();
    clearDomandaMedia();
    S.domandaRenderizzata = false;

    if (!ScreenApp.state.isDomandaState()) {
      ScreenApp.state.renderPlaceholder(S.currentState);
    }
  }

  function showLoadingView() {
    stopMemeRotation();
    showView();
    if (S.domandaRenderizzata) return;

    const titolo = document.getElementById('domanda-testo');
    const opzioni = document.getElementById('opzioni');
    if (!titolo || !opzioni) return;

    titolo.innerText = 'Caricamento domanda...';
    clearStatusMessage();

    clearQuestionTypeBadge();
    clearDomandaMedia();
    renderLoadingOptions(opzioni);
  }

  function render(domanda) {
    if (!domanda || !Array.isArray(domanda.opzioni)) {
      showLoadingView();
      return;
    }

    S.currentDomandaData = domanda;

    const tipoDomanda = normalizeQuestionType(domanda);
    const nowSec = Math.floor(Date.now() / 1000);
    const isSarabandaIntro = tipoDomanda === 'SARABANDA' && (S.currentTimerStart <= 0 || nowSec < S.currentTimerStart);
    const isImpostoreMasked = !!domanda.impostore_masked;
    const showCorrect = !!domanda.show_correct;
    const correctOptionId = String(domanda.correct_option_id || '');
    const hasMemeDecoratedOptions = Array.isArray(domanda.opzioni)
      && domanda.opzioni.some((opzione) => String(opzione?.display_text || '') !== '');
    const isMemeMode = !!domanda.meme_mode || tipoDomanda === 'MEME' || hasMemeDecoratedOptions;

    const titolo = document.getElementById('domanda-testo');
    const opzioni = document.getElementById('opzioni');
    if (!titolo || !opzioni) return;

    titolo.innerText = isImpostoreMasked ? '' : (isSarabandaIntro ? '' : (domanda.testo || ''));
    renderStatusMessage(domanda, isMemeMode, isImpostoreMasked);

    renderQuestionTypeBadge(domanda);
    renderQuestionMediaForState(domanda, isImpostoreMasked, isSarabandaIntro);

    opzioni.innerHTML = '';

    if (isSarabandaIntro) {
      S.domandaRenderizzata = true;
      showView();
      return;
    }

    if (isMemeMode) {
      renderMemeOptions(domanda, showCorrect, correctOptionId);
      S.domandaRenderizzata = true;
      showView();
      return;
    }

    domanda.opzioni.forEach((opzione) => {
      const el = document.createElement('div');
      el.className = 'opzione';
      el.innerText = opzione.testo || '';

      if (showCorrect) {
        if (String(opzione.id || '') === correctOptionId) el.classList.add('is-correct-reveal');
        else el.classList.add('is-reveal-dim');
      }

      opzioni.appendChild(el);
    });

    S.domandaRenderizzata = true;
    showView();
  }

  async function fetchCurrent() {
    if (!ScreenApp.state.isDomandaState()) {
      hideView();
      return;
    }

    try {
      const url = new URL(`${ScreenApp.api.apiBase}/domanda/${S.sessioneId || 0}`, window.location.origin);
      url.searchParams.set('viewer', 'screen');
      const data = await ScreenApp.api.fetchJson(url.toString());
      if (!ScreenApp.state.isDomandaState()) return;

      if (!data.success) {
        if (!S.domandaRenderizzata) showLoadingView();
        return;
      }

      render(data.domanda);
    } catch (error) {
      console.error(error);
    }
  }

  function bindBadgeAudioEvents() {
    const { wrap, image } = getQuestionTypeBadgeNodes();
    if (wrap) wrap.addEventListener('click', handleQuestionTypeBadgeClick);
    if (image) image.addEventListener('click', handleQuestionTypeBadgeClick);
  }

  function bindUnlockEvents() {
    const tryUnlockPendingAudio = async () => {
      if (!ScreenApp.state.canUseAudioPreview()) return;

      let preview = await resolveScreenAudioPreviewSource();
      if (!preview) return;

      S.pendingAudioPreview = preview;
      let played = await playScreenAudioPreview(preview);
      if (played) return;

      await unlockAudioByGesture();
      await playScreenAudioPreview(preview);
    };

    document.addEventListener('pointerdown', tryUnlockPendingAudio);
    document.addEventListener('keydown', tryUnlockPendingAudio);
    window.addEventListener('storage', (event) => {
      if (event.key !== `${S.audioPreviewStoragePrefix}${S.sessioneId}`) return;
      if (!ScreenApp.state.canUseAudioPreview()) {
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
          S.pendingAudioPreview = parsed;
          S.lastAudioPreviewToken = String(parsed.token || S.lastAudioPreviewToken || '');
        }
      } catch (error) {
        console.warn(error);
      }
    });
  }

  async function fetchAudioPreviewStatus() {
    if (!ScreenApp.state.canUseAudioPreview()) {
      enforceAudioStateGuard();
      return;
    }
    if (S.audioPreviewRequestInFlight) return;

    S.audioPreviewRequestInFlight = true;
    try {
      const data = await ScreenApp.api.fetchJson(`${ScreenApp.api.apiBase}/audioPreviewStato/${S.sessioneId || 0}&_=${Date.now()}`);
      if (!data.success || !data.preview) return;

      const token = String(data.preview.token || '');
      if (!token || token === S.lastAudioPreviewToken) return;

      S.lastAudioPreviewToken = token;
      S.pendingAudioPreview = data.preview;
      try {
        window.localStorage.setItem(`${S.audioPreviewStoragePrefix}${S.sessioneId}`, JSON.stringify(data.preview));
      } catch (error) {
        console.warn(error);
      }
    } catch (error) {
      console.error(error);
    } finally {
      S.audioPreviewRequestInFlight = false;
    }
  }

  ScreenApp.domanda = {
    bindBadgeAudioEvents,
    bindUnlockEvents,
    clearQuestionTypeBadge,
    enforceAudioStateGuard,
    fetchAudioPreviewStatus,
    fetchCurrent,
    hideView,
    isRendered() {
      return !!S.domandaRenderizzata;
    },
    showLoadingView,
    render,
  };
})();
