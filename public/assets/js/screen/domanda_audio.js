/* public/assets/js/screen/domanda_audio.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const S = ScreenApp.store;

  const QUESTION_TYPE_ICON_MAP = {
    SARABANDA: 'assets/img/question-types/sarabanda.png',
  };

  function getPreviewAudio() {
    if (!S.previewAudio) {
      S.previewAudio = new Audio();
      S.previewAudio.preload = 'auto';
      S.previewAudio.playsInline = true;
    }
    return S.previewAudio;
  }

  function getAudioContext() {
    if (!S.audioContext) {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return null;
      S.audioContext = new AudioCtx();
    }
    return S.audioContext;
  }

  function getReverseAudioCacheKey(src, previewSec = 0) {
    const cleanSrc = String(src || '').trim();
    const normalizedPreviewSec = Math.max(0, Number(previewSec || 0));
    return `reverse::${cleanSrc}::${normalizedPreviewSec}`;
  }

  async function preloadReverseAudioBuffer(src, previewSec = 0) {
    const cleanSrc = String(src || '').trim();
    if (!cleanSrc) return null;

    const audioCtx = getAudioContext();
    if (!audioCtx) return null;

    const normalizedPreviewSec = Math.max(0, Number(previewSec || 0));
    const cacheKey = getReverseAudioCacheKey(cleanSrc, normalizedPreviewSec);
    const cached = S.audioBufferCache.get(cacheKey) || null;
    if (cached) return cached;

    const response = await fetch(`${cleanSrc}${cleanSrc.includes('?') ? '&' : '?'}_=${Date.now()}`);
    const arrayBuffer = await response.arrayBuffer();
    const decoded = await audioCtx.decodeAudioData(arrayBuffer.slice(0));

    const sampleRate = decoded.sampleRate;
    const maxSamples = normalizedPreviewSec > 0
      ? Math.min(decoded.length, Math.max(1, Math.floor(normalizedPreviewSec * sampleRate)))
      : decoded.length;

    const reversedBuffer = audioCtx.createBuffer(decoded.numberOfChannels, maxSamples, sampleRate);
    for (let channel = 0; channel < decoded.numberOfChannels; channel += 1) {
      const sourceData = decoded.getChannelData(channel);
      const targetData = reversedBuffer.getChannelData(channel);
      for (let i = 0, j = maxSamples - 1; i < maxSamples; i += 1, j -= 1) {
        targetData[i] = sourceData[j];
      }
    }

    S.audioBufferCache.set(cacheKey, reversedBuffer);
    return reversedBuffer;
  }

  function stopBufferSource() {
    if (!S.currentBufferSource) return;
    try { S.currentBufferSource.stop(0); } catch (error) { console.warn(error); }
    try { S.currentBufferSource.disconnect(); } catch (error) { console.warn(error); }
    S.currentBufferSource = null;
  }

  function stopPreviewAudio() {
    stopBufferSource();
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
      reverse_audio: !!S.sarabandaReverseEnabled,
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
    if (!ScreenApp.domandaSupport.isSarabandaQuestionType(S.currentDomandaData)) return false;
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

    const src = ScreenApp.domandaSupport.resolveMediaUrl(preview.audio_path);
    if (!src) return false;

    const previewSec = Number(preview.preview_sec ?? 0);
    const reverseAudio = !!(preview.reverse_audio || S.sarabandaReverseEnabled);

    if (reverseAudio) {
      try {
        const audioCtx = getAudioContext();
        if (!audioCtx) throw new Error('AudioContext non disponibile');
        if (audioCtx.state === 'suspended') {
          await audioCtx.resume();
        }

        const cacheKey = getReverseAudioCacheKey(src, previewSec);
        let reversedBuffer = S.audioBufferCache.get(cacheKey) || null;
        if (!reversedBuffer) {
          reversedBuffer = await preloadReverseAudioBuffer(src, previewSec);
        }
        if (!reversedBuffer) throw new Error('Buffer reverse non disponibile');

        stopPreviewAudio();
        const source = audioCtx.createBufferSource();
        source.buffer = reversedBuffer;
        source.connect(audioCtx.destination);
        S.currentBufferSource = source;

        const durationMs = Math.max(
          250,
          Math.round((reversedBuffer.duration || Math.max(0.25, Number(previewSec || 0.25))) * 1000)
        );
        source.start(0);
        getPreviewAudio().__previewTimer = window.setTimeout(() => {
          stopBufferSource();
          clearPendingAudioPreview();
        }, durationMs);
        source.onended = () => {
          stopBufferSource();
          clearPendingAudioPreview();
        };

        notifyAudioPreviewStarted(preview).catch((error) => console.warn(error));
        clearPendingAudioPreview();
        return true;
      } catch (error) {
        console.warn('Reverse audio preview play failed', error);
        S.pendingAudioPreview = preview;
        return false;
      }
    }

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

    if (previewSec > 0) {
      const stopAtMs = Math.max(250, Math.round(previewSec * 1000));
      audio.__previewTimer = window.setTimeout(() => {
        try { audio.pause(); } catch (error) { console.warn(error); }
        clearPendingAudioPreview();
      }, stopAtMs);
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
      const audioCtx = getAudioContext();
      if (audioCtx && audioCtx.state === 'suspended') {
        await audioCtx.resume();
      }
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
    if (S.audioPreviewPlayInFlight) return;

    S.audioPreviewPlayInFlight = true;
    try {
      await unlockAudioByGesture();
      const preview = await resolveScreenAudioPreviewSource();
      if (!preview) return;

      if (preview.reverse_audio || S.sarabandaReverseEnabled) {
        const src = ScreenApp.domandaSupport.resolveMediaUrl(preview.audio_path || '');
        if (src) {
          await preloadReverseAudioBuffer(src, Number(preview.preview_sec || 0));
        }
      }

      S.pendingAudioPreview = preview;
      await playScreenAudioPreview(preview);
    } finally {
      S.audioPreviewPlayInFlight = false;
    }
  }

  function clearQuestionTypeBadge() {
    const { wrap, image, label } = ScreenApp.domandaSupport.getQuestionTypeBadgeNodes();
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

  function resolveQuestionTypeIconPath(questionType) {
    const rel = QUESTION_TYPE_ICON_MAP[questionType] || '';
    if (!rel) return '';
    const clean = rel.startsWith('/') ? rel.substring(1) : rel;
    return `${window.location.origin}${ScreenApp.api.publicBaseUrl}${clean}`;
  }

  function renderQuestionTypeBadge(domanda) {
    const { wrap, image, label } = ScreenApp.domandaSupport.getQuestionTypeBadgeNodes();
    if (!wrap || !image || !label) return;

    if (!ScreenApp.domandaSupport.isSarabandaQuestionType(domanda)) {
      clearQuestionTypeBadge();
      return;
    }

    const iconUrl = resolveQuestionTypeIconPath('SARABANDA');
    if (!iconUrl) {
      clearQuestionTypeBadge();
      return;
    }

    image.onerror = () => clearQuestionTypeBadge();
    image.src = iconUrl;
    image.alt = 'Tipologia domanda: SARABANDA';
    image.classList.remove('hidden');
    label.innerText = '';
    label.classList.add('hidden');

    wrap.classList.toggle('is-interactive', hasInteractiveBadgeAudio());
    wrap.classList.toggle('is-static', !hasInteractiveBadgeAudio());
    wrap.classList.remove('hidden');
  }

  function bindBadgeAudioEvents() {
    const { wrap } = ScreenApp.domandaSupport.getQuestionTypeBadgeNodes();
    if (wrap) wrap.addEventListener('click', handleQuestionTypeBadgeClick);
  }

  function bindUnlockEvents() {
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
      if (data.preview && (data.preview.reverse_audio || S.sarabandaReverseEnabled)) {
        const src = ScreenApp.domandaSupport.resolveMediaUrl(data.preview.audio_path || '');
        if (src) {
          preloadReverseAudioBuffer(src, Number(data.preview.preview_sec || 0))
            .catch((error) => console.warn('Reverse audio preload failed', error));
        }
      }
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

  ScreenApp.domandaAudio = {
    bindBadgeAudioEvents,
    bindUnlockEvents,
    clearAudioPreviewRuntime,
    clearQuestionTypeBadge,
    enforceAudioStateGuard,
    fetchAudioPreviewStatus,
    renderQuestionTypeBadge,
  };
})();
