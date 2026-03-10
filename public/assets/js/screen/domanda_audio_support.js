/* public/assets/js/screen/domanda_audio_support.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const S = ScreenApp.store;
  const Playback = ScreenApp.domandaAudioPlayback;

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
    Playback.stopPreviewAudio();
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
      broken_record_audio: !!S.sarabandaBrokenRecordEnabled,
      fast_forward_audio: !!S.sarabandaFastForwardEnabled,
      playback_duration_sec: !!S.sarabandaFastForwardEnabled
        ? Math.max(20, Number(domanda.media_audio_preview_sec || 0)) / Playback.getFastForwardRate()
        : Math.max(0, Number(domanda.media_audio_preview_sec || 0)),
      fast_forward_rate: Playback.getFastForwardRate(),
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
    S.sarabandaPreviewConsumedQuestionId = S.currentTimerQuestionId;
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

  function normalizePreviewAudioModes(preview) {
    const normalized = Object.assign({}, preview || {});
    const reverseEnabled = !!S.sarabandaReverseEnabled;
    const brokenRecordEnabled = !!S.sarabandaBrokenRecordEnabled;
    const fastForwardEnabled = !!S.sarabandaFastForwardEnabled;

    normalized.reverse_audio = reverseEnabled;
    normalized.broken_record_audio = brokenRecordEnabled;
    normalized.fast_forward_audio = fastForwardEnabled;
    normalized.fast_forward_rate = Playback.getFastForwardRate(normalized);

    if (fastForwardEnabled) {
      normalized.playback_duration_sec = Math.max(20, Number(normalized.preview_sec || 0)) / normalized.fast_forward_rate;
    } else {
      normalized.playback_duration_sec = Math.max(0, Number(normalized.preview_sec || 0));
    }

    return normalized;
  }

  async function playScreenAudioPreview(preview) {
    if (!ScreenApp.state.canUseAudioPreview()) {
      clearAudioPreviewRuntime();
      return false;
    }
    if (!preview || !preview.audio_path) return false;

    const normalizedPreview = normalizePreviewAudioModes(preview);
    const src = ScreenApp.domandaSupport.resolveMediaUrl(normalizedPreview.audio_path);
    if (!src) return false;

    const previewSec = Number((normalizedPreview && normalizedPreview.preview_sec) ?? 0);
    const reverseAudio = !!normalizedPreview.reverse_audio;
    const brokenRecordAudio = !!normalizedPreview.broken_record_audio;
    const fastForwardAudio = !!normalizedPreview.fast_forward_audio;
    const fastForwardRate = Playback.getFastForwardRate(normalizedPreview);

    if (reverseAudio) {
      try {
        const reverseDurationSec = await Playback.playReversePreview(src, normalizedPreview, previewSec);
        await notifyAudioPreviewStarted(normalizedPreview, reverseDurationSec);
        clearPendingAudioPreview();
        return true;
      } catch (error) {
        console.warn('Reverse audio preview play failed', error);
        S.pendingAudioPreview = normalizedPreview;
        return false;
      }
    }

    if (brokenRecordAudio) {
      try {
        const brokenDurationSec = await Playback.playBrokenRecordPreview(src, normalizedPreview, previewSec);
        await notifyAudioPreviewStarted(normalizedPreview, brokenDurationSec);
        clearPendingAudioPreview();
        return true;
      } catch (error) {
        console.warn('Broken record audio preview play failed', error);
        S.pendingAudioPreview = normalizedPreview;
        return false;
      }
    }

    try {
      const playbackDurationSec = await Playback.playStandardPreview(src, normalizedPreview, previewSec, fastForwardAudio, fastForwardRate);
      await notifyAudioPreviewStarted(normalizedPreview, playbackDurationSec);
      clearPendingAudioPreview();
      return true;
    } catch (secondError) {
      console.warn('Audio preview play failed', secondError);
      S.pendingAudioPreview = normalizedPreview;
      return false;
    }
  }

  async function unlockAudioByGesture() {
    return Playback.unlockAudioByGesture();
  }

  async function warmReversePreview(preview) {
    return Playback.warmReversePreview(preview);
  }

  ScreenApp.domandaAudioSupport = {
    clearPendingAudioPreview,
    clearAudioPreviewRuntime,
    readStoredAudioPreview,
    buildPreviewFromCurrentDomanda,
    fetchLatestAudioPreviewCommand,
    notifyAudioPreviewStarted,
    normalizePreviewAudioModes,
    resolveScreenAudioPreviewSource,
    playScreenAudioPreview,
    unlockAudioByGesture,
    warmReversePreview,
  };
})();
