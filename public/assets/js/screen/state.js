/* public/assets/js/screen/state.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const S = ScreenApp.store;
  const Clock = window.ChillQuizClock;

  function persistDebugTiming() {
    try {
      if (Number(S.sessioneId || 0) <= 0) return;
      window.localStorage.setItem(
        `chillquiz_debug_timing_screen_${Number(S.sessioneId || 0)}`,
        JSON.stringify(S.debugTiming || {})
      );
    } catch (err) {
      console.warn(err);
    }
  }

  function markTimerStarted() {
    const domandaId = Number(S.currentDomandaData?.id || 0);
    if (domandaId <= 0) return;

    if (Number(S.debugTiming?.domandaId || 0) !== domandaId) {
      S.debugTiming = {
        domandaId,
        timerStartedAtMs: 0,
        optionsShownAtMs: 0,
        deltaMs: null,
      };
    }

    if (Number(S.debugTiming.timerStartedAtMs || 0) > 0) return;

    S.debugTiming.timerStartedAtMs = Date.now();
    if (Number(S.debugTiming.optionsShownAtMs || 0) > 0) {
      S.debugTiming.deltaMs = S.debugTiming.optionsShownAtMs - S.debugTiming.timerStartedAtMs;
    }
    persistDebugTiming();
    console.info('[screen] timer-start', S.debugTiming);
  }

  function isDomandaState() {
    return String(S.currentState || '') === 'domanda';
  }

  function canUseAudioPreview() {
    return isDomandaState() && Number(S.sessioneId || 0) > 0;
  }

  function setupSessionQr() {
    if (!S.sessioneId) return;

    const qrImg = document.getElementById('sessione-qr');
    if (!qrImg) return;

    const protocol = window.location.protocol || 'http:';
    const joinUrl = `${protocol}//${ScreenApp.api.publicHost}${ScreenApp.api.publicBaseUrl}index.php?url=player`;
    qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(joinUrl)}`;
  }

  function resetStageTimer() {
    if (S.timerTick) {
      clearTimeout(S.timerTick);
      clearInterval(S.timerTick);
      S.timerTick = null;
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

    if (S.timerTick) {
      clearTimeout(S.timerTick);
      clearInterval(S.timerTick);
      S.timerTick = null;
    }

    const currentSec = Clock.nowSec(S);
    const delayMs = start > currentSec ? Math.round((start - currentSec) * 1000) : 0;

    const tick = () => {
      markTimerStarted();
      const elapsed = Math.max(0, Clock.nowSec(S) - start);
      const remaining = Math.max(0, max - elapsed);
      const visibleRemaining = Math.max(0, Math.ceil(remaining));
      const pct = max > 0 ? (remaining / max) : 0;
      const deg = Math.max(0, Math.min(360, pct * 360));

      indicator.style.setProperty('--progress', `${deg}deg`);
      label.innerText = `${visibleRemaining}s`;

      if (remaining <= 0 && S.timerTick) {
        clearInterval(S.timerTick);
        S.timerTick = null;
      }
    };

    if (delayMs > 0) {
      indicator.style.setProperty('--progress', '0deg');
      label.innerText = '';
      S.timerTick = setTimeout(() => {
        tick();
        S.timerTick = setInterval(tick, 250);
      }, delayMs);
      return;
    }

    tick();
    S.timerTick = setInterval(tick, 250);
  }

  function getStateMeta(state) {
    if (state === 'classifica') return { message: 'Classifica in aggiornamento...' };
    if (state === 'risultati') return { message: 'Risultati del round' };
    if (state === 'conclusa' || state === 'fine') return { message: 'Quiz terminato' };
    return { message: 'In attesa della prossima domanda...' };
  }

  function renderPlaceholder(state) {
    if (state === 'risultati') return;

    const img = document.getElementById('state-image');
    const message = document.getElementById('placeholder-message');
    if (!img || !message) return;

    const meta = getStateMeta(state);
    message.innerText = meta.message;

    if (S.mediaAttiva && S.mediaAttiva.file_path) {
      const mediaPath = String(S.mediaAttiva.file_path || '').startsWith('/')
        ? String(S.mediaAttiva.file_path || '').substring(1)
        : String(S.mediaAttiva.file_path || '');
      img.src = `${window.location.origin}${ScreenApp.api.publicBaseUrl}${mediaPath}`;
      img.alt = S.mediaAttiva.titolo || `Immagine stato: ${meta.message}`;
      return;
    }

    img.removeAttribute('src');
    img.alt = `Immagine stato: ${meta.message}`;
  }

  function showOnly(which) {
    const placeholder = document.getElementById('screen-placeholder');
    const domanda = document.getElementById('screen-domanda');
    const risultati = document.getElementById('screen-risultati');
    if (!placeholder || !domanda || !risultati) return;

    placeholder.classList.toggle('hidden', which !== 'placeholder');
    domanda.classList.toggle('hidden', which !== 'domanda');
    risultati.classList.toggle('hidden', which !== 'risultati');
  }

  function showRisultatiView() {
    showOnly('risultati');
    ScreenApp.domanda?.clearQuestionTypeBadge?.();
    const stateImage = document.getElementById('state-image');
    if (stateImage) stateImage.removeAttribute('src');
  }

  function hideRisultatiView() {
    const risultati = document.getElementById('screen-risultati');
    if (risultati) risultati.classList.add('hidden');
    ScreenApp.risultati?.clearMemeAlert?.();
  }

  ScreenApp.state = {
    isDomandaState,
    canUseAudioPreview,
    setupSessionQr,
    resetStageTimer,
    renderStageTimer,
    getStateMeta,
    renderPlaceholder,
    showOnly,
    showRisultatiView,
    hideRisultatiView,
  };
})();
