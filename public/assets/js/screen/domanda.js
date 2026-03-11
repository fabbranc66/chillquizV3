/* public/assets/js/screen/domanda.js */
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

  function markOptionsShown(domandaId) {
    const currentDomandaId = Number(domandaId || 0);
    if (currentDomandaId <= 0) return;

    if (Number(S.debugTiming?.domandaId || 0) !== currentDomandaId) {
      S.debugTiming = {
        domandaId: currentDomandaId,
        timerStartedAtMs: 0,
        optionsShownAtMs: 0,
        deltaMs: null,
      };
    }

    if (Number(S.debugTiming.optionsShownAtMs || 0) > 0) return;

    S.debugTiming.optionsShownAtMs = Date.now();
    if (Number(S.debugTiming.timerStartedAtMs || 0) > 0) {
      S.debugTiming.deltaMs = S.debugTiming.optionsShownAtMs - S.debugTiming.timerStartedAtMs;
    }
    persistDebugTiming();
    console.info('[screen] options-shown', S.debugTiming);
  }

  function stopMemeRotation() {
    if (S.memeRotationTimer) {
      window.clearInterval(S.memeRotationTimer);
      S.memeRotationTimer = null;
    }
    S.memeRotationStep = -1;
  }

  function clearOptionRevealTimer() {
    if (S.optionRevealTimer) {
      clearTimeout(S.optionRevealTimer);
      S.optionRevealTimer = null;
    }
  }

  function clearAndHideOptions() {
    const opzioniNode = document.getElementById('opzioni');
    if (!opzioniNode) return;
    opzioniNode.innerHTML = '';
    opzioniNode.classList.add('hidden');
    opzioniNode.classList.remove('is-pending-reveal');
    opzioniNode.style.opacity = '';
    opzioniNode.style.visibility = '';
    opzioniNode.style.pointerEvents = '';
  }

  function preparePendingRevealOptions(domanda) {
    const opzioniNode = document.getElementById('opzioni');
    if (!opzioniNode) return;

    opzioniNode.innerHTML = '';
    opzioniNode.classList.add('hidden');
    opzioniNode.classList.add('is-pending-reveal');
    opzioniNode.style.opacity = '0';
    opzioniNode.style.visibility = 'hidden';
    opzioniNode.style.pointerEvents = 'none';

    (domanda.opzioni || []).forEach((opzione, index) => {
      const el = document.createElement('div');
      el.className = 'opzione';
      el.innerText = opzione.testo || '';
      el.dataset.pendingReveal = '1';
      opzioniNode.appendChild(el);
    });
  }

  function revealPendingOptions(domandaId, effectiveSessione) {
    const opzioniNode = document.getElementById('opzioni');
    if (!opzioniNode) return;
    opzioniNode.classList.remove('hidden');
    opzioniNode.classList.remove('is-pending-reveal');
    opzioniNode.style.opacity = '';
    opzioniNode.style.visibility = '';
    opzioniNode.style.pointerEvents = '';
    opzioniNode.querySelectorAll('[data-pending-reveal="1"]').forEach((node) => {
      node.removeAttribute('data-pending-reveal');
    });
    markOptionsShown(domandaId);
    ScreenApp.state.renderStageTimer(effectiveSessione);
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

    if (!showCorrect) {
      markOptionsShown(domanda?.id);
    }
  }

  function showView() {
    ScreenApp.state.showOnly('domanda');
    const stateImage = document.getElementById('state-image');
    if (stateImage) stateImage.removeAttribute('src');
  }

  function hideView() {
    stopMemeRotation();
    clearOptionRevealTimer();
    ScreenApp.domandaAudio.clearAudioPreviewRuntime();
    S.currentDomandaData = null;
    S.sarabandaPreviewStartedQuestionId = 0;
    S.sarabandaPreviewConsumedQuestionId = 0;
    ScreenApp.state.hideRisultatiView();
    ScreenApp.state.showOnly('placeholder');

    const titolo = document.getElementById('domanda-testo');
    const opzioni = document.getElementById('opzioni');

    if (titolo) titolo.innerText = '';
    clearAndHideOptions();

    ScreenApp.domandaSupport.clearStatusMessage();
    ScreenApp.domandaAudio.clearQuestionTypeBadge();
    ScreenApp.domandaSupport.clearDomandaMedia();
    S.domandaRenderizzata = false;

    if (!ScreenApp.state.isQuestionStage()) {
      ScreenApp.state.renderPlaceholder(S.currentState);
    }
  }

  function showLoadingView() {
    stopMemeRotation();
    clearOptionRevealTimer();
    showView();
    if (S.domandaRenderizzata) return;

    const titolo = document.getElementById('domanda-testo');
    const opzioni = document.getElementById('opzioni');
    if (!titolo || !opzioni) return;

    titolo.innerText = 'Caricamento domanda...';
    ScreenApp.domandaSupport.clearStatusMessage();
    ScreenApp.domandaAudio.clearQuestionTypeBadge();
    ScreenApp.domandaSupport.clearDomandaMedia();
    clearAndHideOptions();
  }

  function renderClassicOptions(domanda, showCorrect, correctOptionId) {
    const opzioni = document.getElementById('opzioni');
    if (!opzioni) return;

    opzioni.innerHTML = '';
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

    if (!showCorrect) {
      markOptionsShown(domanda?.id);
    }
  }

  function render(domanda, sessioneMeta = null) {
    if (!domanda || !Array.isArray(domanda.opzioni)) {
      showLoadingView();
      return;
    }

    S.currentDomandaData = domanda;
    const domandaId = Number(domanda?.id || 0);
    if (Number(S.debugTiming?.domandaId || 0) !== domandaId) {
      S.debugTiming = {
        domandaId,
        timerStartedAtMs: 0,
        optionsShownAtMs: 0,
        deltaMs: null,
      };
      persistDebugTiming();
    }
    const {
      isSarabandaIntro,
      isImpostoreMasked,
      isImageParty,
      isFadeMode,
      isMemeMode,
      showCorrect,
      correctOptionId,
    } = ScreenApp.domandaSupport.getQuestionRenderState(domanda);
    const isPreviewStage = String(S.currentState || '') === 'preview';

    const titolo = document.getElementById('domanda-testo');
    const opzioni = document.getElementById('opzioni');
    if (!titolo || !opzioni) return;

    titolo.innerText = (isImpostoreMasked || isPreviewStage || isImageParty || isFadeMode) ? '' : (domanda.testo || '');
    ScreenApp.domandaSupport.renderStatusMessage(domanda, isMemeMode, isImpostoreMasked, isImageParty, isFadeMode);
    ScreenApp.domandaAudio.renderQuestionTypeBadge(domanda);
    ScreenApp.domandaSupport.renderQuestionMediaForState(domanda, isImpostoreMasked, isSarabandaIntro, isImageParty, isFadeMode);

    if (isPreviewStage) {
      clearAndHideOptions();
      S.domandaRenderizzata = true;
      showView();
      return;
    }

    const timerStart = Number(S.currentTimerStart || 0);
    if (!showCorrect && timerStart <= 0) {
      opzioni.innerHTML = '';
      S.domandaRenderizzata = true;
      showView();
      return;
    }

    const effectiveSessione = sessioneMeta || S.latestSessioneSnapshot || {
      stato: 'domanda',
      timer_start: S.currentTimerStart,
      timer_max: S.currentTimerMax,
    };

    const renderOptions = () => {
      opzioni.classList.remove('hidden');
      if (isMemeMode) {
        renderMemeOptions(domanda, showCorrect, correctOptionId);
        if (!showCorrect) {
          ScreenApp.state.renderStageTimer(effectiveSessione);
        }
        return;
      }

      renderClassicOptions(domanda, showCorrect, correctOptionId);
      if (!showCorrect) {
        ScreenApp.state.renderStageTimer(effectiveSessione);
      }
    };

    clearOptionRevealTimer();
    const delayMs = !showCorrect ? Clock.computeDelayMsFromStart(S, timerStart) : 0;

    if (delayMs > 0) {
      opzioni.innerHTML = '';
      S.optionRevealTimer = setTimeout(() => {
        S.optionRevealTimer = null;
        if (!ScreenApp.state.isQuestionStage()) return;
        if (Number(S.currentDomandaData?.id || 0) !== domandaId) return;
        renderOptions();
      }, delayMs);
    } else {
      renderOptions();
    }

    S.domandaRenderizzata = true;
    showView();
  }

  async function fetchCurrent() {
    if (!ScreenApp.state.isQuestionStage()) {
      hideView();
      return;
    }

    try {
      const url = new URL(`${ScreenApp.api.apiBase}/domanda/${S.sessioneId || 0}`, window.location.origin);
      url.searchParams.set('viewer', 'screen');
      const data = await ScreenApp.api.fetchJson(url.toString());
      if (!ScreenApp.state.isQuestionStage()) return;

      if (!data.success) {
        if (!S.domandaRenderizzata) showLoadingView();
        return;
      }

      render(data.domanda);
    } catch (error) {
      console.error(error);
    }
  }

  ScreenApp.domanda = {
    fetchCurrent,
    hideView,
    isRendered() {
      return !!S.domandaRenderizzata;
    },
    showLoadingView,
    render,
    bindBadgeAudioEvents: ScreenApp.domandaAudio.bindBadgeAudioEvents,
    bindUnlockEvents: ScreenApp.domandaAudio.bindUnlockEvents,
    clearQuestionTypeBadge: ScreenApp.domandaAudio.clearQuestionTypeBadge,
    enforceAudioStateGuard: ScreenApp.domandaAudio.enforceAudioStateGuard,
    fetchAudioPreviewStatus: ScreenApp.domandaAudio.fetchAudioPreviewStatus,
  };
})();
