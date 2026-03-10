/* public/assets/js/screen/domanda_audio_support.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const S = ScreenApp.store;

  const FAST_FORWARD_SOURCE_SEC = 20;

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

  function getFastForwardRate(preview = null) {
    const fromPreview = Number(preview?.fast_forward_rate || 0);
    if (fromPreview > 0) return fromPreview;
    const fromState = Number(S.sarabandaFastForwardRate || 0);
    return fromState > 0 ? fromState : 5;
  }

  function getReverseAudioCacheKey(src, previewSec = 0) {
    const cleanSrc = String(src || '').trim();
    const normalizedPreviewSec = Math.max(10, Number(previewSec || 0));
    return `reverse::${cleanSrc}::${normalizedPreviewSec}`;
  }

  async function preloadReverseAudioBuffer(src, previewSec = 0) {
    const cleanSrc = String(src || '').trim();
    if (!cleanSrc) return null;

    const audioCtx = getAudioContext();
    if (!audioCtx) return null;

    const normalizedPreviewSec = Math.max(10, Number(previewSec || 0));
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
    audio.playbackRate = 1;
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
      fast_forward_audio: !!S.sarabandaFastForwardEnabled,
      playback_duration_sec: !!S.sarabandaFastForwardEnabled
        ? Math.max(FAST_FORWARD_SOURCE_SEC, Number(domanda.media_audio_preview_sec || 0)) / getFastForwardRate()
        : Math.max(0, Number(domanda.media_audio_preview_sec || 0)),
      fast_forward_rate: getFastForwardRate(),
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

  function applyPreviewStartAck(data) {
    if (!data || !data.success) return;
    const startAt = Number(data.start_at || data.preview?.start_at || 0);
    if (startAt <= 0) return;

    S.currentTimerStart = startAt;
    S.currentTimerQuestionId = Number(data.preview?.domanda_id || S.currentDomandaData?.id || 0);
    S.sarabandaPreviewStartedQuestionId = S.currentTimerQuestionId;
    if (S.latestSessioneSnapshot && typeof S.latestSessioneSnapshot === 'object') {
      S.latestSessioneSnapshot.timer_start = startAt;
      S.latestSessioneSnapshot.inizio_domanda = startAt;
    }

    if (
      String(S.currentState || '') === 'domanda'
      && ScreenApp.domanda
      && typeof ScreenApp.domanda.render === 'function'
      && S.currentDomandaData
    ) {
      ScreenApp.domanda.render(S.currentDomandaData, S.latestSessioneSnapshot || null);
    }

    if (ScreenApp.state && typeof ScreenApp.state.renderStageTimer === 'function') {
      ScreenApp.state.renderStageTimer(S.latestSessioneSnapshot || {
        stato: 'domanda',
        timer_start: startAt,
        timer_max: Number(S.currentTimerMax || 0),
      });
    }
  }

  async function notifyAudioPreviewStarted(preview, playbackDurationSec = 0) {
    if (!preview || !ScreenApp.state.canUseAudioPreview()) return null;
    try {
      const formData = new FormData();
      if (preview.token) formData.append('token', String(preview.token));
      if (preview.domanda_id) formData.append('domanda_id', String(preview.domanda_id));
      if (Number(playbackDurationSec || 0) > 0) {
        formData.append('playback_duration_sec', String(Number(playbackDurationSec)));
      }

      const data = await ScreenApp.api.fetchJson(`${ScreenApp.api.apiBase}/audioPreviewStarted/${S.sessioneId || 0}`, {
        method: 'POST',
        body: formData,
      });

      applyPreviewStartAck(data);
      return data;
    } catch (error) {
      console.error(error);
      return null;
    }
  }

  async function resolveScreenAudioPreviewSource() {
    return S.pendingAudioPreview
      || readStoredAudioPreview()
      || await fetchLatestAudioPreviewCommand()
      || buildPreviewFromCurrentDomanda();
  }

  async function playReversePreview(src, preview, previewSec) {
    const audioCtx = getAudioContext();
    if (!audioCtx) throw new Error('AudioContext non disponibile');
    if (audioCtx.state === 'suspended') {
      await audioCtx.resume();
    }

    const reversePreviewSec = Math.max(10, previewSec);
    const cacheKey = getReverseAudioCacheKey(src, reversePreviewSec);
    let reversedBuffer = S.audioBufferCache.get(cacheKey) || null;
    if (!reversedBuffer) {
      reversedBuffer = await preloadReverseAudioBuffer(src, reversePreviewSec);
    }
    if (!reversedBuffer) throw new Error('Buffer reverse non disponibile');

    stopPreviewAudio();
    const source = audioCtx.createBufferSource();
    source.buffer = reversedBuffer;
    source.connect(audioCtx.destination);
    S.currentBufferSource = source;

    const durationMs = Math.max(
      250,
      Math.round((reversedBuffer.duration || Math.max(10, Number(reversePreviewSec || 10))) * 1000)
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

    notifyAudioPreviewStarted(preview, durationMs / 1000).catch((error) => console.warn(error));
    clearPendingAudioPreview();
    return true;
  }

  async function playStandardPreview(src, preview, previewSec, fastForwardAudio, fastForwardRate) {
    const audio = getPreviewAudio();
    window.clearTimeout(audio.__previewTimer);
    audio.pause();
    audio.muted = false;
    audio.volume = 1;

    const playbackRate = fastForwardAudio ? fastForwardRate : 1;
    const applyPlaybackRate = () => {
      audio.defaultPlaybackRate = playbackRate;
      audio.playbackRate = playbackRate;
    };

    audio.onloadedmetadata = applyPlaybackRate;
    audio.oncanplay = applyPlaybackRate;
    applyPlaybackRate();
    audio.playsInline = true;
    audio.preload = 'auto';
    audio.src = `${src}${src.includes('?') ? '&' : '?'}_=${Date.now()}`;
    audio.currentTime = 0;
    audio.load();

    const playbackDurationSec = Number(preview.playback_duration_sec ?? 0) > 0
      ? Number(preview.playback_duration_sec)
      : (fastForwardAudio ? (Math.max(FAST_FORWARD_SOURCE_SEC, previewSec) / fastForwardRate) : previewSec);

    if (playbackDurationSec > 0) {
      const stopAtMs = Math.max(250, Math.round(playbackDurationSec * 1000));
      audio.__previewTimer = window.setTimeout(() => {
        try { audio.pause(); } catch (error) { console.warn(error); }
        clearPendingAudioPreview();
      }, stopAtMs);
    }

    try {
      applyPlaybackRate();
      await audio.play();
      await notifyAudioPreviewStarted(preview, playbackDurationSec);
      clearPendingAudioPreview();
      return true;
    } catch (error) {
      try {
        applyPlaybackRate();
        audio.muted = true;
        await audio.play();
        audio.muted = false;
        await notifyAudioPreviewStarted(preview, playbackDurationSec);
        clearPendingAudioPreview();
        return true;
      } catch (secondError) {
        console.warn('Audio preview play failed', secondError);
        S.pendingAudioPreview = preview;
        return false;
      }
    }
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
    const fastForwardAudio = !!(preview.fast_forward_audio || S.sarabandaFastForwardEnabled);
    const fastForwardRate = getFastForwardRate(preview);

    if (reverseAudio) {
      try {
        return await playReversePreview(src, preview, previewSec);
      } catch (error) {
        console.warn('Reverse audio preview play failed', error);
        S.pendingAudioPreview = preview;
        return false;
      }
    }

    return playStandardPreview(src, preview, previewSec, fastForwardAudio, fastForwardRate);
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

  async function warmReversePreview(preview) {
    const src = ScreenApp.domandaSupport.resolveMediaUrl(preview?.audio_path || '');
    if (!src) return;
    await preloadReverseAudioBuffer(src, Math.max(10, Number(preview?.preview_sec || 0)));
  }

  ScreenApp.domandaAudioSupport = {
    getPreviewAudio,
    getAudioContext,
    getFastForwardRate,
    preloadReverseAudioBuffer,
    stopPreviewAudio,
    clearPendingAudioPreview,
    clearAudioPreviewRuntime,
    readStoredAudioPreview,
    buildPreviewFromCurrentDomanda,
    fetchLatestAudioPreviewCommand,
    notifyAudioPreviewStarted,
    resolveScreenAudioPreviewSource,
    playScreenAudioPreview,
    unlockAudioByGesture,
    warmReversePreview,
  };
})();
