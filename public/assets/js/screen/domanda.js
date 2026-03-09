/* public/assets/js/screen/domanda.js */
(function () {
  window.ScreenApp = window.ScreenApp || {};
  const ScreenApp = window.ScreenApp;
  const S = ScreenApp.store;

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
    ScreenApp.domandaAudio.clearAudioPreviewRuntime();
    S.currentDomandaData = null;
    ScreenApp.state.hideRisultatiView();
    ScreenApp.state.showOnly('placeholder');

    const titolo = document.getElementById('domanda-testo');
    const opzioni = document.getElementById('opzioni');

    if (titolo) titolo.innerText = '';
    if (opzioni) opzioni.innerHTML = '';

    ScreenApp.domandaSupport.clearStatusMessage();
    ScreenApp.domandaAudio.clearQuestionTypeBadge();
    ScreenApp.domandaSupport.clearDomandaMedia();
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
    ScreenApp.domandaSupport.clearStatusMessage();
    ScreenApp.domandaAudio.clearQuestionTypeBadge();
    ScreenApp.domandaSupport.clearDomandaMedia();
    ScreenApp.domandaSupport.renderLoadingOptions(opzioni);
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
  }

  function render(domanda) {
    if (!domanda || !Array.isArray(domanda.opzioni)) {
      showLoadingView();
      return;
    }

    S.currentDomandaData = domanda;
    const {
      isSarabandaIntro,
      isImpostoreMasked,
      isMemeMode,
      showCorrect,
      correctOptionId,
    } = ScreenApp.domandaSupport.getQuestionRenderState(domanda);

    const titolo = document.getElementById('domanda-testo');
    const opzioni = document.getElementById('opzioni');
    if (!titolo || !opzioni) return;

    titolo.innerText = isImpostoreMasked ? '' : (isSarabandaIntro ? '' : (domanda.testo || ''));
    ScreenApp.domandaSupport.renderStatusMessage(domanda, isMemeMode, isImpostoreMasked);
    ScreenApp.domandaAudio.renderQuestionTypeBadge(domanda);
    ScreenApp.domandaSupport.renderQuestionMediaForState(domanda, isImpostoreMasked, isSarabandaIntro);

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

    renderClassicOptions(domanda, showCorrect, correctOptionId);
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
