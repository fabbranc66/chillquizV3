// 07a_domanda_render.js
(function () {
  const Player = window.Player;
  const S = Player.state;
  const D = Player.dom;
  const { isQuestionStage } = Player.utils;
  const Alert = Player.uiAlert;
  const Copy = Player.copy;
  const Support = Player.domandaSupport;
  const RenderSupport = Player.domandaRenderSupport;
  const Clock = window.ChillQuizClock;

  function resetDomandaView() {
    RenderSupport.clearOptionRevealTimer();
    Support.stopMemeRotation();
    Support.stopPixelateRender();
    S.renderedDomandaKey = '';
    S.currentDomandaData = null;
    if (D.domandaTesto) D.domandaTesto.innerText = '';
    RenderSupport.clearStatusMessage();
    RenderSupport.clearAndHideOptions();
    Support.resetDomandaMedia();
    Support.clearQuestionTypeBadge();
  }

  async function fetchDomanda() {
    if (!isQuestionStage(S.currentState)) return;

    const requestNonce = ++S.domandaFetchNonce;

    try {
      const response = await fetch(Support.buildDomandaRequestUrl());
      const data = await response.json();

      if (!data.success) return;
      if (!isQuestionStage(S.currentState)) return;
      if (requestNonce !== S.domandaFetchNonce) return;

      renderDomanda(data.domanda);
    } catch (err) {
      console.error(err);
    }
  }

  async function fetchTipoDomandaBadge() {
    if (!S.sessioneId) {
      Support.clearQuestionTypeBadge();
      return;
    }

    try {
      const response = await fetch(Support.buildDomandaRequestUrl());
      const data = await response.json();

      if (!data.success || !data.domanda) {
        return;
      }

      const domandaId = Number(data.domanda.id || 0);
      const tipoDomanda = Support.normalizeBadgeQuestionType(data.domanda);

      if (S.badgeQuestionId === domandaId && S.badgeTipoDomanda === tipoDomanda) {
        return;
      }

      S.badgeQuestionId = domandaId;
      S.badgeTipoDomanda = tipoDomanda;
      Support.renderQuestionTypeBadge(tipoDomanda);
    } catch (err) {
      console.error(err);
    }
  }

  function renderDomanda(domanda, sessioneMeta = null) {
    if (!isQuestionStage(S.currentState)) return;

    if (!domanda || !Array.isArray(domanda.opzioni)) {
      resetDomandaView();
      return;
    }

    S.currentDomandaData = domanda;

    if (!D.domandaTesto || !D.opzioniDiv) return;

    const domandaId = Number(domanda.id || 0);
    if (Number((S.debugTiming && S.debugTiming.domandaId) || 0) !== domandaId) {
      S.debugTiming = {
        domandaId,
        timerStartedAtMs: 0,
        optionsShownAtMs: 0,
        deltaMs: null,
      };
      RenderSupport.persistDebugTiming();
    }
    const tipoDomanda = Support.normalizeBadgeQuestionType(domanda);
    const isPreviewStage = String(S.currentState || '') === 'preview';
    const isSarabandaIntro = tipoDomanda === 'SARABANDA' && isPreviewStage;
    const showCorrect = !!domanda.show_correct;
    const correctOptionId = String(domanda.correct_option_id || '');
    const isImpostoreMasked = !!domanda.impostore_masked;
    const isImpostore = !!domanda.is_impostore;
    const isImageParty = tipoDomanda === 'IMAGE_PARTY' && String(domanda.media_image_path || '').trim() !== '';
    const isFadeMode = tipoDomanda === 'FADE' && String(domanda.media_image_path || '').trim() !== '';
    const hasMemeDecoratedOptions = Array.isArray(domanda.opzioni)
      && domanda.opzioni.some((opzione) => String((opzione && opzione.display_text) || '') !== '');
    const isMemeMode = !!domanda.meme_mode || tipoDomanda === 'MEME' || hasMemeDecoratedOptions;
    const renderKey = Support.buildDomandaRenderKey(domanda);

    if (renderKey !== '' && renderKey === S.renderedDomandaKey && !isSarabandaIntro) {
      return;
    }

    D.domandaTesto.innerText = (isPreviewStage || isMemeMode || isImpostoreMasked || isImageParty || isFadeMode) ? '' : (domanda.testo || '');
    RenderSupport.renderStatusMessage(domanda, isMemeMode, isImpostoreMasked, isImpostore, isImageParty, isFadeMode);

    S.badgeQuestionId = domandaId;
    S.badgeTipoDomanda = tipoDomanda;
    Support.renderQuestionTypeBadge(tipoDomanda);

    if (isImpostoreMasked) {
      RenderSupport.renderDomandaMedia(domanda, false, {
        media_image_path: '/assets/img/player/impostore-fake.svg',
        media_caption: 'Immagine mascherata per l\'impostore',
      });
    } else if (isImageParty) {
      RenderSupport.renderDomandaMedia(domanda, false, { is_image_party: true });
    } else if (isFadeMode) {
      RenderSupport.renderDomandaMedia(domanda, false, { is_fade_mode: true });
    } else if (isSarabandaIntro) {
      RenderSupport.renderDomandaMedia(domanda, true);
    } else {
      RenderSupport.renderDomandaMedia(domanda, false);
    }

    if (isPreviewStage) {
      RenderSupport.clearAndHideOptions();
      S.questionShownAtPerf = 0;
      S.questionShownDomandaId = domandaId;
      S.questionShownTimerStart = Number(S.domandaTimerStart || 0);
      S.renderedDomandaKey = `${renderKey}::preview`;
      return;
    }

    const timerStart = Number(S.domandaTimerStart || 0);
    if (Support.shouldMarkQuestionShown(domandaId, timerStart)) {
      Support.markQuestionShown(domandaId, timerStart);
    }

    if (!showCorrect && timerStart <= 0) {
      D.opzioniDiv.innerHTML = '';
      return;
    }

    const effectiveSessione = sessioneMeta || S.latestSessioneSnapshot || {
      stato: 'domanda',
      timer_start: S.domandaTimerStart,
      timer_max: S.domandaTimerMax,
    };

    const renderOptions = () => {
      D.opzioniDiv.classList.remove('hidden');
      D.opzioniDiv.innerHTML = '';

      if (isMemeMode) {
        S.renderedDomandaKey = renderKey;
        RenderSupport.renderMemeButtons(domanda, showCorrect, correctOptionId);
        return;
      }

      domanda.opzioni.forEach((opzione, index) => {
        const btn = document.createElement('button');
        btn.innerText = opzione.testo || '';
        btn.dataset.id = String(opzione.id || '');

        const paletteIndex = (index % 4) + 1;
        btn.classList.add(`opzione-kahoot-${paletteIndex}`);

        if (showCorrect) {
          btn.disabled = true;
          if (btn.dataset.id === correctOptionId) {
            btn.classList.add('is-correct-reveal');
          } else {
            btn.classList.add('is-reveal-dim');
          }
          if (
            Number(S.selectedAnswerDomandaId || 0) === domandaId
            && btn.dataset.id === String(S.selectedAnswerOptionId || '')
          ) {
            btn.classList.add('is-player-choice-reveal');
          }
        } else {
          RenderSupport.bindImmediateAnswer(btn, domanda.id, opzione.id);
        }

        D.opzioniDiv.appendChild(btn);
      });

      if (!showCorrect) {
        RenderSupport.markOptionsShown(domandaId);
        Player.pollingSupport.renderTimer(effectiveSessione);
      }

      S.renderedDomandaKey = renderKey;
    };

    RenderSupport.clearOptionRevealTimer();
    const delayMs = !showCorrect ? Clock.computeDelayMsFromStart(S, timerStart) : 0;

    if (delayMs > 0) {
      D.opzioniDiv.innerHTML = '';
      S.optionRevealTimer = setTimeout(() => {
        S.optionRevealTimer = null;
        if (!isQuestionStage(S.currentState)) return;
        if (Number(S.badgeQuestionId || 0) !== domandaId) return;
        renderOptions();
      }, delayMs);
      return;
    }

    renderOptions();
  }

  async function inviaRisposta(domandaId, opzioneId) {
    if (S.rispostaInviata) return;
    if (!S.puntataInviata) {
      Alert.show({
        title: Copy.betRequiredBeforeAnswerTitle,
        message: Copy.betRequiredBeforeAnswerMessage,
        tone: 'warn',
      });
      return;
    }
    S.rispostaInviata = true;
    Support.stopMemeRotation();
    Support.freezeAnsweredQuestion(domandaId, opzioneId);

    const buttons = document.querySelectorAll('#opzioni button');
    buttons.forEach((btn) => {
      btn.disabled = true;
      if (String(btn.dataset.id) === String(opzioneId)) {
        btn.classList.add('selected');
      } else {
        btn.classList.add('dimmed');
      }
    });

    try {
      const formData = new FormData();
      formData.append('partecipazione_id', String(S.partecipazioneId || 0));
      formData.append('domanda_id', String(domandaId || 0));
      formData.append('opzione_id', String(opzioneId || 0));

      const perfNow = (typeof performance !== 'undefined' && typeof performance.now === 'function')
        ? performance.now()
        : Date.now();
      const shownAt = Number(S.questionShownAtPerf || 0);
      if (shownAt > 0 && perfNow >= shownAt) {
        const elapsedClient = Math.max(0, ((perfNow - shownAt) / 1000));
        formData.append('tempo_client', elapsedClient.toFixed(3));
      }

      const response = await fetch(`${S.API_BASE}/risposta/${S.sessioneId || 0}`, {
        method: 'POST',
        body: formData,
      });

      const data = await response.json();

      if (!data.success) {
        Alert.show({
          title: Copy.answerFailedTitle,
          message: data.error || Copy.answerFailedMessage,
          tone: 'error',
        });
        S.rispostaInviata = false;
        return;
      }

      Alert.hide();
      Support.stopMemeRotation();
      if (Player.classifica && typeof Player.classifica.setImmediateResult === 'function') {
        Player.classifica.setImmediateResult(data.risultato);
      }
      Player.classifica.renderRisultatoPersonaleImmediato(data.risultato);
    } catch (err) {
      console.error(err);
      Alert.show({
        title: Copy.networkErrorTitle,
        message: Copy.answerNetworkErrorMessage,
        tone: 'error',
      });
      S.rispostaInviata = false;
    }
  }

  Player.domanda = {
    fetchDomanda,
    fetchTipoDomandaBadge,
    renderDomanda,
    resetDomandaView,
    inviaRisposta,
    resetDomandaMedia: Support.resetDomandaMedia,
    clearQuestionTypeBadge: Support.clearQuestionTypeBadge,
  };
})();
