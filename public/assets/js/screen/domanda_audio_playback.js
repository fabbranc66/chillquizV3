/* public/assets/js/screen/domanda_audio_playback.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const S = ScreenApp.store;

  const FAST_FORWARD_SOURCE_SEC = 20;
  const BROKEN_RECORD_SEGMENT_SEC = 2.0;
  const BROKEN_RECORD_JITTER_SEC = 0.02;

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

  function getFastForwardRate(preview) {
    const safePreview = preview || null;
    const fromPreview = Number((safePreview && safePreview.fast_forward_rate) || 0);
    if (fromPreview > 0) return fromPreview;
    const fromState = Number(S.sarabandaFastForwardRate || 0);
    return fromState > 0 ? fromState : 5;
  }

  function getReverseAudioCacheKey(src, previewSec) {
    const cleanSrc = String(src || '').trim();
    const normalizedPreviewSec = Math.max(10, Number(previewSec || 0));
    return `reverse::${cleanSrc}::${normalizedPreviewSec}`;
  }

  async function preloadReverseAudioBuffer(src, previewSec) {
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
    window.clearInterval(audio.__brokenRecordInterval);
    audio.__brokenRecordInterval = null;
    audio.playbackRate = 1;
    try { audio.pause(); } catch (error) { console.warn(error); }
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
    }, durationMs);
    source.onended = () => {
      stopBufferSource();
    };

    return durationMs / 1000;
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

    const previewDurationSec = Number((preview && preview.playback_duration_sec) || 0) > 0
      ? Number(preview.playback_duration_sec)
      : (fastForwardAudio ? (Math.max(FAST_FORWARD_SOURCE_SEC, previewSec) / fastForwardRate) : previewSec);

    if (previewDurationSec > 0) {
      const stopAtMs = Math.max(250, Math.round(previewDurationSec * 1000));
      audio.__previewTimer = window.setTimeout(() => {
        try { audio.pause(); } catch (error) { console.warn(error); }
      }, stopAtMs);
    }

    try {
      applyPlaybackRate();
      await audio.play();
      return previewDurationSec;
    } catch (error) {
      applyPlaybackRate();
      audio.muted = true;
      await audio.play();
      audio.muted = false;
      return previewDurationSec;
    }
  }

  async function playBrokenRecordPreview(src, preview, previewSec) {
    stopPreviewAudio();
    const audio = getPreviewAudio();
    const totalDurationSec = Math.max(1.2, Number((preview && preview.playback_duration_sec) || previewSec || 0));
    const sourceWindowSec = Math.max(1, Number(previewSec || totalDurationSec || 1));
    const segmentSec = Math.min(BROKEN_RECORD_SEGMENT_SEC, Math.max(0.5, sourceWindowSec));
    const jumpForwardSec = 1.0;
    const anchorSec = Math.max(0, Math.min(0.2, Math.max(0, sourceWindowSec - segmentSec)));

    audio.muted = false;
    audio.volume = 1;
    audio.defaultPlaybackRate = 1;
    audio.playbackRate = 1;
    audio.playsInline = true;
    audio.preload = 'auto';
    audio.src = `${src}${src.includes('?') ? '&' : '?'}_=${Date.now()}`;
    audio.currentTime = 0;
    audio.load();

    const stopAtMs = Math.max(1200, Math.round(totalDurationSec * 1000));
    audio.__previewTimer = window.setTimeout(() => {
      try { audio.pause(); } catch (error) { console.warn(error); }
      window.clearInterval(audio.__brokenRecordInterval);
      audio.__brokenRecordInterval = null;
    }, stopAtMs);

    const applyBrokenJump = () => {
      try {
        const jitter = (Math.random() * BROKEN_RECORD_JITTER_SEC * 2) - BROKEN_RECORD_JITTER_SEC;
        const targetTime = Math.max(0, Math.min(anchorSec + jitter, Math.max(0, sourceWindowSec - 0.05)));
        audio.currentTime = targetTime;
        audio.playbackRate = 1;
      } catch (error) {
        console.warn(error);
      }
    };

    const waitCanPlay = () => new Promise((resolve) => {
      const done = () => {
        audio.removeEventListener('canplay', done);
        audio.removeEventListener('loadedmetadata', done);
        resolve();
      };
      audio.addEventListener('canplay', done, { once: true });
      audio.addEventListener('loadedmetadata', done, { once: true });
    });

    try {
      await waitCanPlay();
      applyBrokenJump();
      await audio.play();
    } catch (error) {
      audio.muted = true;
      await audio.play();
      audio.muted = false;
    }

    audio.__brokenRecordInterval = window.setInterval(() => {
      try {
        const nextTime = Math.min(Math.max(0, sourceWindowSec - 0.05), audio.currentTime + jumpForwardSec);
        audio.currentTime = nextTime;
      } catch (error) {
        console.warn(error);
      }
    }, Math.max(250, Math.round(segmentSec * 1000)));

    return totalDurationSec;
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
    const src = ScreenApp.domandaSupport.resolveMediaUrl(((preview && preview.audio_path) || ''));
    if (!src) return;
    await preloadReverseAudioBuffer(src, Math.max(10, Number((preview && preview.preview_sec) || 0)));
  }

  ScreenApp.domandaAudioPlayback = {
    getAudioContext,
    getFastForwardRate,
    getPreviewAudio,
    playBrokenRecordPreview,
    playReversePreview,
    playStandardPreview,
    preloadReverseAudioBuffer,
    stopPreviewAudio,
    unlockAudioByGesture,
    warmReversePreview,
  };
})();
