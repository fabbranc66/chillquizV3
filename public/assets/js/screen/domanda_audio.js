/* public/assets/js/screen/domanda_audio.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const S = ScreenApp.store;
  const AudioSupport = ScreenApp.domandaAudioSupport;

  const QUESTION_TYPE_ICON_MAP = {
    SARABANDA: 'assets/img/question-types/sarabanda.png',
  };

  function enforceAudioStateGuard() {
    if (ScreenApp.state.canUseAudioPreview()) return;
    AudioSupport.clearAudioPreviewRuntime();
  }

  function hasInteractiveBadgeAudio() {
    if (!ScreenApp.state.canUseAudioPreview()) return false;
    if (!ScreenApp.domandaSupport.isSarabandaQuestionType(S.currentDomandaData)) return false;
    if (!S.sarabandaAudioEnabled) return false;
    const currentAudio = String(S.currentDomandaData?.media_audio_path || '').trim() !== '';
    const pendingAudio = String(S.pendingAudioPreview?.audio_path || '').trim() !== '';
    const storedAudio = String(AudioSupport.readStoredAudioPreview()?.audio_path || '').trim() !== '';
    return currentAudio || pendingAudio || storedAudio;
  }

  async function handleQuestionTypeBadgeClick() {
    if (!ScreenApp.state.canUseAudioPreview() || !hasInteractiveBadgeAudio()) return;
    if (S.audioPreviewPlayInFlight) return;

    S.audioPreviewPlayInFlight = true;
    try {
      await AudioSupport.unlockAudioByGesture();
      const preview = await AudioSupport.resolveScreenAudioPreviewSource();
      if (!preview) return;

      if (preview.reverse_audio || S.sarabandaReverseEnabled) {
        await AudioSupport.warmReversePreview(preview);
      }

      S.pendingAudioPreview = preview;
      await AudioSupport.playScreenAudioPreview(preview);
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
        AudioSupport.clearPendingAudioPreview();
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
        AudioSupport.warmReversePreview(data.preview)
          .catch((error) => console.warn('Reverse audio preload failed', error));
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
    clearAudioPreviewRuntime: AudioSupport.clearAudioPreviewRuntime,
    clearQuestionTypeBadge,
    enforceAudioStateGuard,
    fetchAudioPreviewStatus,
    renderQuestionTypeBadge,
  };
})();
